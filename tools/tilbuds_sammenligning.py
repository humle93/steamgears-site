#!/usr/bin/env python3
"""Sammenlign leverandørtilbud på reel indkøbspris — ikke på stykpris.

Læser et TSV/CSV-ark med ét tilbud pr. række (kolonnerne fra tilbudsskemaet i
docs/library/leverandoerbrief-teleskopreol-2026-11-09.md §5), regner landed cost
efter Incoterms, og stiller tilbuddene op side om side med:

  * dækningsbidrag efter retur og break-even CPA ved en given salgspris
  * det maksimale landed cost prisen kan bære
  * den salgspris der kræves for et givet CPA-loft
  * dokumentationsportene (GPSR, belastningstest, kartonlængde, MOQ, prøve)

Formlerne er de fire fra docs/library/roi-model-dropshipping-2026-11-09.md §2,
med de samme input som modellen bruger (7 % retur à 85 kr, 59 kr fragt til
kunden, 12 kr emballage, betaling 1,4 % + 1 kr, 25 % moms).

Brug:
    python3 tools/tilbuds_sammenligning.py tilbud.tsv
    python3 tools/tilbuds_sammenligning.py tilbud.tsv --pris 799 --antal 500 --cpa 250
    python3 tools/tilbuds_sammenligning.py tilbud.tsv --md      # klip ind i et dokument
    python3 tools/tilbuds_sammenligning.py tilbud.tsv --csv ud.csv
    python3 tools/tilbuds_sammenligning.py --selftest

Kolonner (header er påkrævet, rækkefølgen er fri, ukendte kolonner ignoreres):
    navn, incoterms, pris_100, pris_300, pris_500, pris_1000, fragt_pr_stk,
    told_pct, toldbehandling, tpl, moq, karton_laengde, karton_vaegt, hs,
    oprindelse, proeve_pris, gpsr, belastningstest, eu_ansvarlig,
    levering_dage, landed_ifoelge

Tomme felter er »ved det ikke« og tælles som manglende dokumentation — ikke som
et nej. Tallene er kun så gode som arket; scriptet gætter ikke på fragt eller told.
"""

from __future__ import annotations

import argparse
import csv
import io
import math
import sys

# --- Modellens faste input (roi-model-dropshipping-2026-11-09.md §2) -------
MOMS = 0.25
RETUR = 0.07
RETUR_OMK = 85.0
FRAGT_KUNDE = 59.0
EMBALLAGE = 12.0
BETALING_PCT = 0.014
BETALING_FAST = 1.0

# Fragten ligger i prisen for disse Incoterms; tolden gør kun for DDP.
FRAGT_INDE = {"CIF", "CFR", "CPT", "CIP", "DAP", "DDP"}
TOLD_INDE = {"DDP"}

KARTON_MAAL = 115.0       # briefens krav
KARTON_TILLAEG = 120.0    # PostNord: særbehandling over 120 cm
KARTON_PAKKE = 175.0      # PostNord Home/Parcel: maks længde
PROEVE_LOFT = 600.0       # briefen §3, spørgsmål 7
EU_LANDE = {
    "AT", "BE", "BG", "HR", "CY", "CZ", "DK", "EE", "FI", "FR", "DE", "GR", "HU",
    "IE", "IT", "LV", "LT", "LU", "MT", "NL", "PL", "PT", "RO", "SK", "SI", "ES",
    "SE",
}

JA = {"ja", "yes", "y", "true", "1", "j"}
NEJ = {"nej", "no", "n", "false", "0"}


# --- Hjælpere --------------------------------------------------------------
def tal(vaerdi):
    """Læs et tal fra arket: 232,50 · 1 000 · " 38 " · '' → None."""
    if vaerdi is None:
        return None
    tekst = str(vaerdi).strip().replace("\u00a0", "").replace(" ", "")
    if tekst in ("", "-", "—"):
        return None
    tekst = tekst.replace(".", "").replace(",", ".") if tekst.count(",") else tekst
    try:
        return float(tekst)
    except ValueError:
        return None


def ja_nej(vaerdi):
    """True/False, eller None når feltet er tomt (= ikke oplyst)."""
    tekst = (vaerdi or "").strip().lower()
    if tekst in JA:
        return True
    if tekst in NEJ:
        return False
    return None


def kr(x):
    return f"{x:,.0f}".replace(",", ".")


# --- Formlerne ------------------------------------------------------------
def db_efter_retur(pris, landed, fragt_kunde=FRAGT_KUNDE):
    """DB før retur minus returomkostningen. Pris er inkl. moms."""
    netto = pris / (1 + MOMS)
    betaling = netto * BETALING_PCT + BETALING_FAST
    db = netto - landed - fragt_kunde - EMBALLAGE - betaling
    return db * (1 - RETUR) - RETUR * RETUR_OMK


def maks_landed(pris, cpa, fragt_kunde=FRAGT_KUNDE):
    """Det højeste landed cost hvor CPA-loftet stadig er cpa."""
    netto = pris / (1 + MOMS)
    krav = (cpa + RETUR * RETUR_OMK) / (1 - RETUR)
    return netto * (1 - BETALING_PCT) - BETALING_FAST - fragt_kunde - EMBALLAGE - krav


def noedvendig_pris(landed, cpa, fragt_kunde=FRAGT_KUNDE):
    """Den salgspris (inkl. moms) der giver præcis cpa i DB efter retur."""
    krav = (cpa + RETUR * RETUR_OMK) / (1 - RETUR)
    netto = (krav + landed + fragt_kunde + EMBALLAGE + BETALING_FAST) / (1 - BETALING_PCT)
    return netto * (1 + MOMS)


def anbefalet_pris(minimum):
    """Næste pris på -9-ladderen (699, 749, 799 …) der mindst dækker minimum."""
    trin = max(1, math.ceil(minimum / 10.0))
    while trin * 10 - 1 < minimum:
        trin += 1
    return trin * 10 - 1


def landed_cost(tilbud, antal):
    """Landed cost pr. stk. med Incoterms-forskellene gjort eksplicitte."""
    pris = tilbud["priser"].get(antal)
    if pris is None:
        raise ValueError(f"{tilbud['navn']}: ingen pris for {antal} stk.")
    incoterm = (tilbud.get("incoterms") or "").strip().upper()
    fragt_ukendt = tilbud.get("fragt_pr_stk") is None and incoterm not in FRAGT_INDE
    fragt = tilbud.get("fragt_pr_stk") or 0.0
    told_pct = tilbud.get("told_pct") or 0.0
    toldbehandling = tilbud.get("toldbehandling") or 0.0
    tpl = tilbud.get("tpl") or 0.0

    fragt_med = 0.0 if incoterm in FRAGT_INDE else fragt
    told_med = 0.0 if incoterm in TOLD_INDE else (pris + fragt_med) * told_pct / 100.0
    behandling_med = 0.0 if incoterm in TOLD_INDE else toldbehandling
    total = pris + fragt_med + told_med + behandling_med + tpl
    return {
        "pris": pris,
        "fragt": fragt_med,
        "told": told_med,
        "toldbehandling": behandling_med,
        "tpl": tpl,
        "total": total,
        "incoterms": incoterm,
        "fragt_ukendt": fragt_ukendt,
        "fragt_i_prisen": incoterm in FRAGT_INDE,
    }


def fragt_kunde_for(tilbud):
    """Kundefragten, løftet hvis kartonnen er over 120 cm."""
    laengde = tilbud.get("karton_laengde")
    if laengde and laengde > KARTON_TILLAEG:
        return FRAGT_KUNDE + 15.0, True
    return FRAGT_KUNDE, False


def porte(tilbud, antal):
    """Dokumentationsporte. Hver post: (navn, status, tekst)."""
    ud = []
    gpsr = ja_nej(tilbud.get("gpsr"))
    test = ja_nej(tilbud.get("belastningstest"))
    hs = (tilbud.get("hs") or "").strip()
    oprindelse = (tilbud.get("oprindelse") or "").strip().upper()
    eu_ansvarlig = ja_nej(tilbud.get("eu_ansvarlig"))
    laengde = tilbud.get("karton_laengde")
    moq = tilbud.get("moq")
    proeve = tilbud.get("proeve_pris")

    if gpsr is True:
        ud.append(("GPSR-pakke", "ok", "komplet"))
    elif gpsr is False:
        ud.append(("GPSR-pakke", "stop", "mangler — varen må ikke sælges i EU"))
    else:
        ud.append(("GPSR-pakke", "mangler", "ikke oplyst"))

    if test is True:
        ud.append(("Belastningstest", "ok", "protokol vedlagt"))
    elif test is False:
        ud.append(("Belastningstest", "stop", "ingen test — annoncens løfte er udokumenteret"))
    else:
        ud.append(("Belastningstest", "mangler", "ikke oplyst"))

    if hs:
        ud.append(("HS-kode", "ok", hs))
    else:
        ud.append(("HS-kode", "mangler", "told og antidumping kan ikke regnes"))

    incoterm = (tilbud.get("incoterms") or "").strip().upper()
    if tilbud.get("fragt_pr_stk") is None and incoterm not in FRAGT_INDE:
        ud.append(("Fragt", "mangler", "international fragt ikke oplyst — landed er undervurderet"))
    if (oprindelse and oprindelse not in EU_LANDE
            and tilbud.get("told_pct") is None and incoterm not in TOLD_INDE):
        ud.append(("Toldsats", "mangler", "sats ikke oplyst — 0 % er en målt sats, ikke en antagelse"))

    if not oprindelse:
        ud.append(("Oprindelse", "mangler", "ikke oplyst"))
    elif oprindelse in EU_LANDE:
        ud.append(("Oprindelse", "ok", f"{oprindelse} (i EU — ingen told)"))
    elif eu_ansvarlig is True:
        ud.append(("Oprindelse", "ok", f"{oprindelse} · EU-ansvarlig oplyst"))
    elif eu_ansvarlig is False:
        ud.append(("Oprindelse", "advarsel", f"{oprindelse} · ingen EU-ansvarlig: butikken skal selv være importør"))
    else:
        ud.append(("Oprindelse", "mangler", f"{oprindelse} · EU-ansvarlig ikke oplyst"))

    if laengde is None:
        ud.append(("Karton", "mangler", "længde ikke oplyst"))
    elif laengde <= KARTON_MAAL:
        ud.append(("Karton", "ok", f"{kr(laengde)} cm ≤ {kr(KARTON_MAAL)}"))
    elif laengde <= KARTON_TILLAEG:
        ud.append(("Karton", "advarsel", f"{kr(laengde)} cm over målet, men under 120"))
    elif laengde <= KARTON_PAKKE:
        ud.append(("Karton", "advarsel", f"{kr(laengde)} cm → tillæg på hver ordre (+15 kr i regnestykket)"))
    else:
        ud.append(("Karton", "stop", f"{kr(laengde)} cm kan ikke sendes som pakke"))

    if moq is None:
        ud.append(("MOQ", "mangler", "ikke oplyst"))
    elif moq <= antal:
        ud.append(("MOQ", "ok", f"{kr(moq)} ≤ {antal}"))
    else:
        ud.append(("MOQ", "advarsel", f"{kr(moq)} over de {antal} der regnes på"))

    if proeve is None:
        ud.append(("Prøve", "mangler", "pris ikke oplyst"))
    elif proeve <= PROEVE_LOFT:
        ud.append(("Prøve", "ok", f"{kr(proeve)} kr ≤ {kr(PROEVE_LOFT)}"))
    else:
        ud.append(("Prøve", "advarsel", f"{kr(proeve)} kr over loftet på {kr(PROEVE_LOFT)}"))

    return ud


def vurder(tilbud, antal, pris, cpa):
    landed = landed_cost(tilbud, antal)
    fragt_kunde, tillaeg = fragt_kunde_for(tilbud)
    p = porte(tilbud, antal)
    stop = [n for n, s, _ in p if s == "stop"]
    db = db_efter_retur(pris, landed["total"], fragt_kunde)
    loft = maks_landed(pris, cpa, fragt_kunde)
    luft = loft - landed["total"]
    if stop:
        dom = "kan ikke sælges"
    elif luft < 0:
        dom = "nej" if -luft > loft * 0.10 else "grænse"
    elif any(s == "mangler" for _, s, _ in p):
        dom = "kan bære (forbehold)"
    else:
        dom = "kan bære"
    return {
        "navn": tilbud["navn"],
        "landed": landed,
        "fragt_kunde": fragt_kunde,
        "tillaeg": tillaeg,
        "db": db,
        "loft": loft,
        "luft": luft,
        "pris_kraevet": noedvendig_pris(landed["total"], cpa, fragt_kunde),
        "porte": p,
        "stop": stop,
        "dom": dom,
        "landed_ifoelge": tilbud.get("landed_ifoelge"),
        "andet_antal": tilbud.get("landed_ifoelge") is not None
                       and tilbud.get("landed_ifoelge_antal", 300.0) != antal,
        "levering": tilbud.get("levering_dage"),
    }


# --- Indlæsning -----------------------------------------------------------
def laes_tilbud(sti):
    with open(sti, encoding="utf-8-sig") as f:
        raa = f.read()
    if not raa.strip():
        raise SystemExit(f"{sti} er tom")
    separator = "\t" if "\t" in raa.splitlines()[0] else (";" if ";" in raa.splitlines()[0] else ",")
    laeser = csv.DictReader(io.StringIO(raa), delimiter=separator)
    if not laeser.fieldnames or "navn" not in [f.strip().lower() for f in laeser.fieldnames]:
        raise SystemExit(f"{sti}: headeren mangler kolonnen »navn«")

    tilbud = []
    for i, raekke in enumerate(laeser, start=2):
        r = {(k or "").strip().lower(): (v or "").strip() for k, v in raekke.items()}
        if not r.get("navn"):
            continue
        priser = {}
        for antal in (100, 300, 500, 1000):
            vaerdi = tal(r.get(f"pris_{antal}"))
            if vaerdi is not None:
                priser[antal] = vaerdi
        if not priser:
            raise SystemExit(f"linje {i} ({r['navn']}): ingen priskolonne (pris_100/300/500/1000)")
        tilbud.append({
            "navn": r["navn"],
            "incoterms": r.get("incoterms", ""),
            "priser": priser,
            "fragt_pr_stk": tal(r.get("fragt_pr_stk")),
            "told_pct": tal(r.get("told_pct")),
            "toldbehandling": tal(r.get("toldbehandling")) or 0.0,
            "tpl": tal(r.get("tpl")) or 0.0,
            "moq": tal(r.get("moq")),
            "karton_laengde": tal(r.get("karton_laengde")),
            "karton_vaegt": tal(r.get("karton_vaegt")),
            "hs": r.get("hs", ""),
            "oprindelse": r.get("oprindelse", ""),
            "proeve_pris": tal(r.get("proeve_pris")),
            "gpsr": r.get("gpsr", ""),
            "belastningstest": r.get("belastningstest", ""),
            "eu_ansvarlig": r.get("eu_ansvarlig", ""),
            "levering_dage": r.get("levering_dage", ""),
            "landed_ifoelge": tal(r.get("landed_ifoelge")),
            "landed_ifoelge_antal": tal(r.get("landed_ifoelge_antal")) or 300.0,
        })
    return tilbud


# --- Udskrivning ----------------------------------------------------------
def tabel(overskrift, raekker, navne, bredde=34):
    """Transponeret tabel: metrics som rækker, tilbud som kolonner."""
    kolonne = max(bredde, *(len(n) for n in navne)) if navne else bredde
    linjer = [f"\n{overskrift}", "-" * (kolonne + 3 + (kolonne + 3) * len(navne))]
    linjer.append(" " * kolonne + " | " + " | ".join(n.ljust(kolonne) for n in navne))
    linjer.append("-" * (kolonne + 3 + (kolonne + 3) * len(navne)))
    for etiket, vaerdier in raekker:
        linjer.append(etiket.ljust(kolonne) + " | " + " | ".join(str(v).ljust(kolonne) for v in vaerdier))
    return "\n".join(linjer)


def markdown(overskrift, raekker, navne):
    linjer = [f"\n**{overskrift}**", "", "| | " + " | ".join(navne) + " |", "| --- |" + " --- |" * len(navne)]
    for etiket, vaerdier in raekker:
        linjer.append(f"| {etiket} | " + " | ".join(str(v) for v in vaerdier) + " |")
    return "\n".join(linjer)


def rapport(resultater, antal, pris, cpa, som_md=False):
    navne = [r["navn"] for r in resultater]
    byg = markdown if som_md else tabel
    ud = []
    ud.append(
        (f"Tilbudssammenligning · {antal} stk. · salgspris {kr(pris)} kr · "
         f"CPA-loft {kr(cpa)} kr · {len(resultater)} tilbud")
    )

    ud.append(byg("1. Reel indkøbspris (landed cost)", [
        ("Stykpris", [f"{kr(r['landed']['pris'])} kr" for r in resultater]),
        ("Incoterms", [r["landed"]["incoterms"] or "—" for r in resultater]),
        ("International fragt",
         ["i prisen" if r["landed"]["fragt_i_prisen"]
          else (f"{kr(r['landed']['fragt'])} kr" if not r["landed"]["fragt_ukendt"] else "ikke oplyst") for r in resultater]),
        ("Told", [f"{kr(r['landed']['told'])} kr" for r in resultater]),
        ("Toldbehandling", [f"{kr(r['landed']['toldbehandling'])} kr" for r in resultater]),
        ("3PL", [f"{kr(r['landed']['tpl'])} kr" if r["landed"]["tpl"] else "—" for r in resultater]),
        ("= Landed cost", [f"**{kr(r['landed']['total'])} kr**" if som_md else f"{kr(r['landed']['total'])} kr" for r in resultater]),
        ("Leverandørens eget tal",
         [f"{kr(r['landed_ifoelge'])} kr" if r["landed_ifoelge"] is not None else "ikke oplyst" for r in resultater]),
        ("Afvigelse",
         [f"{r['landed']['total'] - r['landed_ifoelge']:+,.0f} kr".replace(",", ".")
          if r["landed_ifoelge"] is not None and not r["andet_antal"] else
          ("andet antal" if r["landed_ifoelge"] is not None else "—") for r in resultater]),
    ], navne))

    ud.append(byg(f"2. Økonomi ved {kr(pris)} kr", [
        ("DB efter retur", [f"{kr(r['db'])} kr" for r in resultater]),
        ("DB-margin af netto", [f"{100 * r['db'] / (pris / (1 + MOMS)):.0f} %" for r in resultater]),
        ("Maks landed ved loft", [f"{kr(r['loft'])} kr" for r in resultater]),
        ("Luft til loftet", [f"{r['luft']:+,.0f} kr".replace(",", ".") for r in resultater]),
        ("Pris for CPA " + kr(cpa), [f"{kr(r['pris_kraevet'])} kr" for r in resultater]),
        ("Anbefalet pris", [f"{kr(anbefalet_pris(r['pris_kraevet']))} kr" for r in resultater]),
        ("Kundefragt", [f"{kr(r['fragt_kunde'])} kr" + (" (tillæg)" if r["tillaeg"] else "") for r in resultater]),
        ("Dom", [r["dom"] for r in resultater]),
    ], navne))

    porte_navne = []
    for r in resultater:
        for navn, _, _ in r["porte"]:
            if navn not in porte_navne:
                porte_navne.append(navn)
    porte_raekker = []
    for navn in porte_navne:
        vaerdier = []
        for r in resultater:
            post = next((p for p in r["porte"] if p[0] == navn), None)
            vaerdier.append(f"{post[2]}" if post else "—")
        porte_raekker.append((navn, vaerdier))
    ud.append(byg("3. Dokumentation og formkrav", porte_raekker, navne))

    rangeret = sorted(resultater, key=lambda r: (bool(r["stop"]), r["landed"]["total"]))
    ud.append("\nRækkefølge efter reel indkøbspris (blokerede sidst):")
    for i, r in enumerate(rangeret, 1):
        note = f" — {r['dom']}" + (f" ({', '.join(r['stop'])})" if r["stop"] else "")
        ud.append(f"  {i}. {r['navn']}: {kr(r['landed']['total'])} kr{note}")

    mangler = [(r["navn"], n) for r in resultater for n, s, _ in r["porte"] if s in ("mangler", "advarsel")]
    if mangler:
        ud.append("\nAt få svar på før beslutning:")
        for navn, post in mangler:
            ud.append(f"  · {navn}: {post}")

    ud.append("\nInput: 7 % retur à 85 kr, 59 kr fragt til kunden, 12 kr emballage, "
              "betaling 1,4 % + 1 kr, 25 % moms (roi-model §2). Importmoms er likviditet, ikke omkostning.")
    if any("mangler" in r["dom"] or r["dom"] == "kan bære (forbehold)" for r in resultater):
        ud.append("Forbehold = et tal mangler (fx international fragt), så landed er undervurderet — "
                  "dommen gælder kun de tal der er oplyst.")
    return "\n".join(ud)


def csv_ud(resultater, antal, pris, cpa, sti):
    def dk(v):
        return "" if v is None else f"{v:.2f}".replace(".", ",")

    felter = ["navn", "antal", "landed_pris", "landed_fragt", "landed_told", "landed_toldbehandling",
              "landed_tpl", "landed", "leverandoer_landed", "db_efter_retur", "maks_landed",
              "luft", "pris_for_cpa", "anbefalet_pris", "dom", "stop", "mangler"]
    with open(sti, "w", encoding="utf-8", newline="") as f:
        w = csv.DictWriter(f, fieldnames=felter, delimiter=";")
        w.writeheader()
        for r in resultater:
            w.writerow({
                "navn": r["navn"], "antal": antal,
                "landed_pris": dk(r["landed"]["pris"]),
                "landed_fragt": dk(r["landed"]["fragt"]),
                "landed_told": dk(r["landed"]["told"]),
                "landed_toldbehandling": dk(r["landed"]["toldbehandling"]),
                "landed_tpl": dk(r["landed"]["tpl"]),
                "landed": dk(r["landed"]["total"]),
                "leverandoer_landed": dk(r["landed_ifoelge"]),
                "db_efter_retur": dk(r["db"]),
                "maks_landed": dk(r["loft"]),
                "luft": dk(r["luft"]),
                "pris_for_cpa": dk(r["pris_kraevet"]),
                "anbefalet_pris": anbefalet_pris(r["pris_kraevet"]),
                "dom": r["dom"],
                "stop": "; ".join(r["stop"]),
                "mangler": "; ".join(n for n, s, _ in r["porte"] if s in ("mangler", "advarsel")),
            })
    return sti


# --- Selvtest -------------------------------------------------------------
def selftest():
    fejl = []

    def lig(a, b, tolerance=0.02, hvad=""):
        if abs(a - b) > tolerance:
            fejl.append(f"{hvad}: {a:.2f} != {b:.2f}")

    # Facit fra leverandørbriefen §4 (landed 210 ved 749 kr)
    lig(db_efter_retur(749, 210), 281.24, hvad="DB ved landed 210")
    lig(db_efter_retur(749, 150), 337.04, hvad="DB ved landed 150")
    lig(db_efter_retur(749, 300), 197.54, hvad="DB ved landed 300")
    # Spejltabellen: maks landed ved CPA-loft
    lig(maks_landed(749, 281.24), 210.0, hvad="maks landed ved CPA 281")
    lig(maks_landed(749, 200), 297.36, hvad="maks landed ved CPA 200")
    lig(maks_landed(699, 200), 257.92, hvad="maks landed ved 699 / CPA 200")
    lig(maks_landed(799, 200), 336.80, hvad="maks landed ved 799 / CPA 200")
    # Nødvendig pris er den omvendte funktion
    lig(noedvendig_pris(210, 281.24), 749.0, hvad="pris for CPA 281 ved landed 210")
    lig(noedvendig_pris(maks_landed(749, 200), 200), 749.0, hvad="rundtur 749 / CPA 200")
    lig(noedvendig_pris(300, 200), 752.35, hvad="pris for CPA 200 ved landed 300")
    # 10 kr dyrere landed koster 0,93 x 10 = 9,30 kr af loftet
    lig(db_efter_retur(749, 210) - db_efter_retur(749, 220), 9.30, hvad="10 kr landed = 9,30 kr DB")
    # 50 kr højere pris giver 50/1,25 x 0,986 = 39,44 kr før retur
    lig(0.986 * (799 - 749) / (1 + MOMS), 39.44, hvad="50 kr pris giver 39 kr DB")
    # Told: 1 pp på 200 kr vareværdi = 2 kr
    lig((200 * 0.06) - (200 * 0.05), 2.0, hvad="told 1 pp på 200 kr")
    # Incoterms: DDP må ikke lægge fragt og told oveni
    ddp = {"navn": "DDP", "incoterms": "DDP", "priser": {300: 240.0}, "fragt_pr_stk": 40.0,
           "told_pct": 6.0, "toldbehandling": 18.0, "tpl": 6.0, "moq": 100,
           "karton_laengde": 110.0, "hs": "9403 20 80", "oprindelse": "PL", "proeve_pris": 400.0,
           "gpsr": "ja", "belastningstest": "ja", "eu_ansvarlig": "ja", "levering_dage": "21"}
    lig(landed_cost(ddp, 300)["total"], 246.0, hvad="DDP: kun pris + 3PL")
    # FOB lægger fragt og told på
    fob = dict(ddp, incoterms="FOB", oprindelse="CN", fragt_pr_stk=38.0, told_pct=0.0)
    lig(landed_cost(fob, 300)["total"], 240 + 38 + 0 + 18 + 6, hvad="FOB: pris + fragt + behandling")
    # CIF har fragten inde, men ikke tolden
    cif = dict(fob, incoterms="CIF", told_pct=3.0)
    lig(landed_cost(cif, 300)["total"], 240 + 0 + (240 * 0.03) + 18 + 6, hvad="CIF: fragt inde, told på")
    # Karton over 120 cm løfter kundefragten med 15 kr
    fragt, tillaeg = fragt_kunde_for({"karton_laengde": 132.0})
    lig(fragt, 74.0, hvad="karton 132 cm giver tillæg")
    if not tillaeg:
        fejl.append("tillæg ikke flagget")
    fragt, _ = fragt_kunde_for({"karton_laengde": 112.0})
    lig(fragt, 59.0, hvad="karton 112 cm uden tillæg")
    # Prisladderen
    if anbefalet_pris(749.93) != 759:
        fejl.append(f"anbefalet_pris(749,93) = {anbefalet_pris(749.93)}, ventet 759")
    if anbefalet_pris(700.0) != 709:
        fejl.append(f"anbefalet_pris(700) = {anbefalet_pris(700.0)}, ventet 709")
    # GPSR-nej er stop, uanset hvor billig varen er
    billig = dict(fob, navn="Billig", priser={300: 100.0}, gpsr="nej")
    if "kan ikke sælges" != vurder(billig, 300, 749, 200)["dom"]:
        fejl.append("GPSR-nej blev ikke blokeret")
    # Tomme felter er »ved det ikke«, ikke nej
    tom = dict(fob, navn="Tom", gpsr="", belastningstest="")
    if any(s == "stop" for _, s, _ in porte(tom, 300)):
        fejl.append("tomme felter blev behandlet som stop")
    # Tal med dansk decimal-komma og tusindadskillelse
    if tal("232,50") != 232.5 or tal("1 000") != 1000 or tal("") is not None:
        fejl.append("tal() læser ikke dansk format")

    antal_checks = 30
    if fejl:
        print(f"[FAIL] {len(fejl)} af {antal_checks} selftest-checks fejlede:")
        for f in fejl:
            print(f"  · {f}")
        return 1
    print(f"[OK] {antal_checks} selftest-checks: formlerne matcher briefens facit, "
          f"Incoterms og karton-tillæg opfører sig som aftalt")
    return 0


# --- main -----------------------------------------------------------------
def main(argv=None):
    p = argparse.ArgumentParser(description=__doc__.splitlines()[0])
    p.add_argument("fil", nargs="?", help="TSV/CSV med ét tilbud pr. række")
    p.add_argument("--pris", type=float, default=749.0, help="salgspris inkl. moms (standard 749)")
    p.add_argument("--antal", type=int, default=300, help="prisgruppe der regnes på (standard 300)")
    p.add_argument("--cpa", type=float, default=200.0, help="CPA-loft der måles imod (standard 200)")
    p.add_argument("--md", action="store_true", help="markdown i stedet for teksttabeller")
    p.add_argument("--csv", metavar="STI", help="skriv resultatet som CSV (Excel, semikolon)")
    p.add_argument("--selftest", action="store_true", help="kør formel-testene")
    args = p.parse_args(argv)

    if args.selftest:
        return selftest()
    if not args.fil:
        p.error("mangler fil med tilbud (eller --selftest)")

    tilbud = laes_tilbud(args.fil)
    if not tilbud:
        raise SystemExit(f"{args.fil}: ingen rækker med et navn")
    for t in tilbud:
        if args.antal not in t["priser"]:
            raise SystemExit(
                f"{t['navn']}: har ikke pris for {args.antal} stk. "
                f"(har {', '.join(str(a) for a in sorted(t['priser']))})"
            )
    resultater = [vurder(t, args.antal, args.pris, args.cpa) for t in tilbud]
    print(rapport(resultater, args.antal, args.pris, args.cpa, som_md=args.md))
    if args.csv:
        print(f"\nCSV: {csv_ud(resultater, args.antal, args.pris, args.cpa, args.csv)}")
    return 0


if __name__ == "__main__":
    sys.exit(main())
