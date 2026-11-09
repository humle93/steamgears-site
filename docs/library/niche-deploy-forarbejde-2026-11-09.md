# Fire nicher, klar til udrulning i ét hug (9/11 2026)

Forarbejdet er færdigt: **fire komplette butikspakker** med priser, kollektioner, sider og
annoncehooks, en validator der regner økonomien efter og afviser det der ikke kan bære sig, og
én kommando der bygger alle fire importfiler. Det, der stadig mangler, er ikke kode — det er
seks ejerhandlinger pr. niche og et Shopify-token, som hverken repoet eller sandkassen har.

> **Scope, med vilje præcist:** »fire nicher« er de fire kandidater, der blev vejet 9/11
> (bil-interiør, lejebolig-opbevaring, sensorisk ro, søvn). Kun bil-interiør blev valgt; de tre
> andre er `status: fravalgt` eller `vurderet` og ligger her, fordi beslutningen skal kunne
> ændres uden at starte forfra. Der findes **ikke** fire butikskoder at deploye.

## 1. Hvad der ligger i pakken

| Fil | Hvad det er |
| --- | --- |
| `deploy/nicher/nicher.json` | **Kilden.** Fire nicher med brand, vært, publikum, fem kollektioner, varer med de syv input, hooks, KPI-gates og ejerhandlinger |
| `deploy/nicher/<slug>.json` | Genereret payload pr. niche: varer med beregnet økonomi, fem sider med `[EJER: …]`, menu, schema, plan-nøgle |
| `deploy/nicher/all.json` | Alle fire i én fil — det en deploy-kørsel læser |
| `deploy/nicher/shopify-<slug>.csv` | **Produkter, klar til Shopify → Produkter → Importér.** Kladder, tom `Image Src`, `Status: draft` |
| `deploy/nicher/<slug>-sider.md` | De fem sider som tekst plus kollektioner, ejerpunkter, hooks og KPI-gates |
| `tools/deploy/niche_preflight.py` | Bygger payloads (`--build`), validerer og tjekker drift (`--check`), viser økonomien (`--stats`) |
| `tools/deploy/deploy_nicher.py` | Bygger importfilerne i ét hug; `--woo` er WooCommerce-vejen over MCP |

## 2. Økonomien, regnet efter — ikke gættet

Alle fire er kørt gennem de fire formler i
[roi-model-dropshipping-2026-11-09.md](roi-model-dropshipping-2026-11-09.md) §2. Momsen går ud af
omsætningen, men ikke ud af annoncerne; retur koster både fragt og håndtering.

| Niche | Varer | Hero break-even-CPA | Break-even ROAS | Bundle-løft | Ordrer/md til faste | Ejerfelter |
| --- | --- | --- | --- | --- | --- | --- |
| **KABINE** (bil-interiør) | 5 | **222,95 kr.** | 2,24x | +44,07 kr. | 7 | 14 |
| **OPLOFT** (lejebolig) | 6 | 252,58 kr. | 2,97x | +68,88 kr. | 6 | 14 |
| **STILLE** (sensorisk ro) | 5 | 173,44 kr. | 3,17x | +196,26 kr. | 9 | 14 |
| **IMMER** (søvn) | 5 | 241,53 kr. | 3,31x | +98,60 kr. | 6 | 14 |

Fire ting står ud, og de styrer rækkefølgen:

- **KABINE er den tyndeste, ikke den stærkeste.** 222,95 kr. er den laveste break-even-CPA af de
  fire, og ROAS-kravet er det laveste (2,24x) — men det er også den eneste niche, hvor der ikke
  findes en vare over 300 kr. som reserve. Bil-interiør blev valgt på kreativ-pris og returrate,
  ikke på margen.
- **IMMER har den højeste ROAS og den næsthøjeste CPA.** Søvn er dyrest at annoncere (mættet
  kreativt niveau), men varen bærer det, fordi 799 kr. giver 241,53 kr. efter retur.
- **STILLE har det største bundle-løft** (+196,26 kr.) og den højeste returrate at slås med:
  12 % på bæretøj, fordi pasformen er følsom. Uden bundlen er nichen svagere end tallene ser ud.
- **OPLOFT kræver 2,97x i ROAS** — det højeste krav pr. krone — fordi reoler er dyre at sende
  (79 kr.) og dyre at få retur (149 kr.). Det er den niche, hvor et fejlfundet fragtbud hurtigst
  vælter regnestykket.

**Faste omkostninger er sat til 1.400 kr./md** for alle fire (webshop, apps, domæne, regnskab,
forsikring, emballageansvar). Det er et estimat fra KABINE-planen, ikke et tilbud, og det står i
`assumptions` i hver payload.

## 3. Validatoren fangede seks fejl i mit eget oplæg

Første kørsel var ikke grøn, og det er pointen med at have den:

| Fejl | Hvorfor den er alvorlig |
| --- | --- |
| To varer havde `ads: true` med break-even-CPA under 120 kr. | Der findes ingen annoncepris, hvor de er rentable — de skal kun være mer-salg |
| OPLOFT havde ingen mer-salg-vare | En niche uden bump mister den eneste post, der er ren margin |
| To mer-salg-varer havde fragt på | En bump lægges i pakken kunden allerede betaler for; fragt gør den til et selvstændigt køb |
| To SKU'er kolliderede | SKU'en blev dannet af en afkortet slug; to varer med samme start blev samme vare i butikken |
| To nicher delte kollektionen `lyd` og `pakker` | Uden ændringen ville den anden import genbruge den førstes kollektioner |

Reglerne står i `tools/deploy/niche_preflight.py`, og scriptet kører nu som værn nr. 38 i
[`tools/natlige-vaern.sh`](../../tools/natlige-vaern.sh). `--check` fejler også ved **drift**, så en
håndredigeret payload ikke kan snige sig ind i en import.

Den ene advarsel, der er tilbage, er bevidst: samme kollektions-slug på **to forskellige værter**
er harmløs, men en kollision hvis nogen lægger to nicher på samme WooCommerce-site. Validatoren
siger det højt i stedet for at gætte, hvad der er meningen.

## 4. Sådan køres alle fire i ét hug

```bash
python3 tools/deploy/niche_preflight.py --check    # 4 nicher, 0 fejl, ingen drift
python3 tools/deploy/deploy_nicher.py              # skriver 4 CSV + 4 sidefiler
```

Derefter, pr. niche, i denne rækkefølge — den er ikke vilkårlig:

1. **Shopify → Produkter → Importér** `shopify-<slug>.csv`. Alt kommer ind som **kladde**, så
   intet er offentligt, før nogen vælger det. Fortryd importen inden 24 timer, hvis noget er galt.
2. **Opret de fem kollektioner** fra `<slug>-sider.md` §Kollektioner. CSV'en sætter `Type` til
   kollektions-sluggen, så produkerne kan samles i ét filter pr. kollektion.
3. **Indsæt de fem sider** (Start her, Om os, Levering og retur, Mål og pasform/Størrelsesguide,
   Kontakt) som kladder.
4. **Udfyld de 14 `[EJER: …]`-felter** pr. niche. De er juridiske eller aftalebaserede: CVR,
   adresse, telefon, fragtpriser, zoner, returadresse, svartid, mål og tolerancer, maksimal
   belastning og det ene, butikken gør anderledes.
5. **Beslut publicering.** Ingen agent publicerer de fire butikker; det er ejerens klik, fordi
   priser, lager og navn er ejerens ansvar.

**Rollback:** Shopify → Produkter → Importér → *Fortryd import*, eller slet de handles importen
oprettede. Hver payload har sin egen `plan_key` (`niche-<slug>-<antal>p`), så en kørsel kan
identificeres bagefter.

**WooCommerce-vejen** (`deploy_nicher.py --woo`) er en anden vej og ikke det samme indhold:
plugin'ets `niche-apply` bygger sin egen katalog ud fra en nichestreng og skriver kun kladder. Den
kræver et MCP-token, som sandkassen ikke har, og den er beregnet til en demo på steamgears.eu —
ikke til at publicere de fire butikker.

## 5. Hvad der stadig blokerer, pr. niche

Alle fire har de samme seks ejerhandlinger, fordi de alle er nye butikker:

| # | Handling | Blokerer |
| --- | --- | --- |
| E1 | CVR, telefon, adresse, bankaftale | Ingen faktura, ingen MobilePay, ingen lovlig handel |
| E2 | Leverandør med GPSR-dokumentation og prøver | Indkøbspris, kvalitet og leveringstid er gæt |
| E3 | Registrering i producentregistret (emballage) | Markedsføring må ikke starte |
| E4 | Domæne og Shopify-konto | Importen har ingen butik at lande i |
| E5 | Startdato: Q4-priser eller januar | CPM 30-60 % højere i uge 3-4 |
| E6 | Annoncekonto med betalingsmetode | Ingen annoncer |

**Ikke målt:** ingen af de fire CSV-filer er importeret i en rigtig Shopify-butik. Filformatet er
Shopifys standardkolonner med `Option1 Name: Title` og `Status: draft`, men den første import er
stadig den første import — den skal ses efter i butikken, før de fem kollektioner og siderne lægges
ovenpå. Der er heller ingen leverandørtilbud bag priserne; alle indkøbs-, fragt- og annoncepriser
er estimater, og det står i hver payload.

## 6. Sådan ændres beslutningen

Kilden er `deploy/nicher/nicher.json`. Ret priser, varer eller status dér — ikke i de genererede
filer — og kør `--build` igen. Skal en fravalgt niche gøres til den valgte, er det `status` og
`host`, der ændres; resten af pakken er den samme. En ny niche tilføjes ved at kopiere en blok og
køre `--check`, som afviser den, hvis økonomien ikke kan bære en annonce.
