# Warpstorm — hvad der er live, hvad der blokerer, og hvad næste produkt skal være

Statusmåling og beslutningsgrundlag for **warpstorm.online**, testdeployet af den 26/10 2026. Supplerer [niche-research-2026-11-01.md](niche-research-2026-11-01.md), som handlede om en anden niche; denne fil erstatter den som aktiv linje.

---

## 0. Kort svar

**Warpstorm kan ikke sælge Warhammer — overhovedet.** Games Workshop kræver fysisk butik for et trade-abonnent: *«To trade with us, you need to have a physical location retailing to the general public»* (GW's egen trade-FAQ). En online-only fanside kan ikke få en konto, og uden konto er der ingen Citadel-produkter at sælge.

**Men siden har tre indtægtsveje, og kun én af dem har en margin, der er værd at bygge på:**

| Model | Bruttomargin | Kapital | Status i dag |
|---|---|---|---|
| Affiliate (5 %, Wayland har 90 dages cookie) | 0 % risiko, ~2,50 € per 50 €-kurv | 0 | **Ingen affiliate-links er sat ind endnu** |
| Videresalg af tredjepartsmaling og -værktøj | 25–35 % | 500–2.000 € | Priskonkurrence mod Kutami/Fantasywelt, som giver −14 til −20 % |
| **Eget design: 3D-printet terræn og baser** | **85–95 %** | printer + filament | Ikke bygget |

**Anbefaling: tænd affiliate i dag (gratis, øjeblikkelig), byg terræn som det første egentlige produkt, og lad kits vente.** Rækkefølgen er styret af margin, ikke af hvad der er sjovest at lave.

---

## 1. Hvad der faktisk er live (målt 1/11 2026)

| Måling | Værdi |
|---|---|
| Platform | WordPress 7.1.3, tema **Extendable**, Rank Math, W3 Total Cache |
| Indhold | **17 posts, 7 sider** — /start-here/, /workbench/, /lore/ + 14 guides |
| Design | **GRIMDARK CSS v1** inline; tokens `#0A0A0C` / `#8C1D2C` / `#B8963E` / `#E8E0D0` / `#9A9A9A` |
| Butik | **Nej.** `/shop/` svarer **404**, men CSS'en indeholder allerede `.sg-shop-portal`, `.sg-look-grid` og `.sg-ctb` |
| Affiliate-links | **0.** /best-hobby-tools/ har ingen udgående links ud over sociale medier |
| Affiliate-side | Findes (/affiliate-disclosure/) — men i **amerikansk stil** |
| IP-position | Forsidefooter: *«unofficial fan site … not affiliated with or endorsed by Games Workshop»* ✅ |
| Ydeevne | TTFB 0,81 s, 123 KB forside |

**Den vigtigste sætning i hele denne fil:** design-systemet er foran indholdet. Der findes shop-komponenter, der aldrig har været brugt, og en shop-side, der ikke findes. Det er billigt at rette — men kun hvis produktet vælges rigtigt først.

---

## 2. Den hårde begrænsning: Games Workshop

GW's trade-vilkår, læst direkte:

- **«To trade with us, you need to have a physical location retailing to the general public.»**
- **«If you have a physical store stocking Games Workshop products, you have the option of selling online as well, under specific terms.»**
- Der er minimumskøb ved opstart, MAP-priser, og regler om at videresælge i original emballage.

Konsekvens: **ingen Citadel-maling, ingen bokse, ingen spil.** En fanside uden fysisk butik er udelukket, uanset hvor godt indholdet er.

**Og IP-reglerne rammer også monetiseringen.** GW's fan-site-regler kræver at siden er tydeligt uofficiel, ikke bruger logoer, ikke kopierer tekst, billeder eller regler — og den generelle klausul siger at *«such fan content cannot be sold or otherwise monetised»*. Den linje er bredere end EU-retten kræver, men den er den risiko, man bliver målt på: en anmodning til infringements@gwplc.com er nok til at få siden fjernet fra søgning og sociale medier.

**Praktisk tommelfingerregel for alt nyt indhold:** skriv egne ord, brug egne billeder, omtal navne beskrivende («Space Marines er …»), og sælg aldrig noget, der ligner et GW-produkt. Tredjeparts-maling, baser, terræn og værktøj er derimod almindelig kompatibel-vare-handel og helt legitimt — så længe man ikke bruger GW's logoer eller varemærker som sit eget produktnavn.

---

## 3. Markedet — det er stort, og det vokser

| Måling | Værdi | Kilde |
|---|---|---|
| Games Workshop FY2026 | Omsætning **£659,7 mio.**, kerneomsætning £626,8 mio., bruttomargin 71,1 % | GW-regnskab via Freedom Isnt Free |
| Vækst | Kerneomsætning **+17,3 %** i H1 FY2026 | samme |
| Uafhængige forhandlere | **9.100** konti i 71 lande (op fra 8.100) | samme |
| Europa | **~42 %** af verdens tabletop-wargame-marked | Growth Market Reports |
| Næste efterspørgselschok | Warhammer 40.000 **11. udgave, juni 2026** | samme |
| Hurtigst voksende format | Skirmish (Kill Team) **+7,6 %/år** | samme |

Det er en kategori i vækst med en deadline: **11. udgave i juni 2026 nulstiller folks hære**, og det er den største enkeltdriver for tilbehørssalg, der findes.

---

## 4. Hvorfor 5 % affiliate ikke bliver en forretning

Verificerede satser: **Wayland Games 5 % med 90 dages cookie** (usædvanligt lang — hobbykøb har lang beslutningstid), **Element Games 5 % / 7 dage**, **Miniature Market 5 % / 7 dage**.

Regnestykket: en 50 €-kurv giver **2,50 €**. For 1.000 €/md. skal der henvises for **20.000 €/md.** — og de tre store EU-forhandlere kører i forvejen −14 til −20 % på GW-priser. Affiliate er en **bisætning**, ikke en forretning. Den skal tændes, fordi den er gratis, men den skal ikke planlægges efter.

**AOV i kategorien:** tilbehør alene **35–60 €**, blandet med miniaturer/terræn **65–100 €**. De tyske konkurrenters fri fragt-grænser ligger på 40–50 €, hvilket er deres eget bud på realistisk kurvstørrelse.

**Konkurrencebillede:** UK-kluster (Wayland, Element, Firestorm) svækket af Brexit for EU-kunder; DE-kluster (Kutami, Fantasywelt, Battlefield Berlin) tager EU-andele ved at dumpe GW-priser. Kutami annoncerer **«permanent 20 % rabat»** på det meste GW. At konkurrere på malingpris er at melde sig ind i den kamp med den mindste indkøbsrabat.

---

## 5. Det produkt, der faktisk har margin

**3D-printet terræn og baser i eget design.**

Regnestykket: et terrænstykke på ~200 g PLA koster **0,50–1,50 €** i materiale plus printtid. Det sælges på Etsy og i webshops i **39–79 €**-klassen. Det er 85–95 % bruttomargin og den eneste vare i denne niche, hvor prisen ikke sammenlignes med GW's RRP, fordi der ikke findes et tilsvarende officielt produkt.

Tre forbehold, der skal siges højt:

1. **Det kræver en printer.** Har I ikke en resin/FDM-printer i forvejen, er investeringen 250–400 €, og den skal regnes med.
2. **Designet skal være originalt.** Ingen scanninger, ingen «replacement for [GW-produkt]», ingen GW-former. Gotisk sci-fi-ruin som *genre* er fri; en kopi af et bestemt GW-kit er det ikke.
3. **Printtid er arbejdstid.** 6–10 timer per plade betyder, at sortimentet skal være modulært og batch-printes, ikke one-off.

**Næstbedst: baser.** 40 baser koster under 0,50 € at printe og sælger for 10–14 €. Perfekt kurvfyld, ingen IP-risiko hvis formerne er generiske.

**Dårligst: kits.** En sammensat begyndersæt med tredjepartsmaling og -værktøj lander på 25–35 % margin, fordi man køber ind til 40–60 % af retail og sælger med rabat for at være attraktiv. Det er ikke *«low cost, high resell»* — det er *low cost, low resell*.

---

## 6. Sourcing, hvis man alligevel vil have maling og værktøj

Kun leverandører med en dokumenteret vej ind:

| Leverandør | Land | Vej ind | Dokumenteret | Brug til |
|---|---|---|---|---|
| **Redgrass Games** | BE | Retailer-registrering + retail@ | Kræver **VAT-nummer + live webshop-URL**; **betaling før afsendelse, ingen kredit** | Vådpaletter — det bedste enkeltprodukt i kategorien |
| **The Army Painter** | DK | «First Choice Retailer»-program | Programmet er bygget op om fysiske **racks** i butikker; intet ord om ren e-handel | Maling, hvis de accepterer webshop |
| **Feldherr** | DE | B2B med EU-VAT | Kun **momsfri køb til retailpris** — ingen købmandsrabat | Opbevaring, hvis man vil have varen |
| **Green Stuff World** | ES | `/en/579-wholesaler` | Reseller-niveau bekræftet af loyalitetsvilkårene; MOQ ikke læsbar | Baser, effekter, værktøj |
| **Vallejo, AK Interactive, Proxxon** | ES/DE | **Kun via national distributør** | Fabrikkerne henviser til distributørlister | Maling via distributør, ikke fabrik |
| **Occam Distribution** | UK | «Become a Stockist» | Aggregator; brandliste ikke verificeret | Samlet indkøb, hvis UK-fragt er acceptabel |

**Ingen i denne branche offentliggør MOQ eller rabat.** Første mail skal stille fem spørgsmål: åbningsordre, rabattrin, accepterer I ren e-handel, kan I blindshippe, og leverer I GPSR-ansvarlig med adresse.

---

## 7. Compliance — den fejl, der er let at lave

Sidens nuværende affiliate-side er **amerikansk**: en generel sætning om at siden er «reader-supported». Det holder ikke i Danmark.

Forbrugerombudsmanden kræver efter **markedsføringsloven § 6, stk. 4**, at kommerciel intention er tydelig, og anbefaler konkret:

- **«reklame for [xx]»** eller **«annonce for [xx]»** helt i starten af indholdet
- **«reklamelink for [xx]»** oven over eller direkte ved **hvert** affiliate-link
- markering **per opslag**, ikke kun på en underside
- **platformstandard-labels er ikke nok alene**, og en fodnote i bunden er ikke nok

Den 2. juli 2025 **politianmeldte** Forbrugerombudsmanden selskabet bag en række affiliate-«test»-sider for præcis dette — og for at give indtryk af at have testet produkter, man aldrig havde haft i hænderne. Det er den relevante præcedens: ikke en advarsel, en anmeldelse.

**Derfor skal layoutet vise mønstret, ikke bare beskrive det.** Prototypen i [`concepts/warpstorm-supply-depot/`](../../concepts/warpstorm-supply-depot/) har `Reklamelink for …` ved hver ekstern butiksknap og en `Reklame`-markør øverst i de kommercielle blokke.

---

## 8. Sidelayout — hvad prototypen indeholder

Bygget i **GRIMDARK-tokens** og med de eksisterende klassenavne (`.grim-hero`, `.grim-btn`, `.grim-table`, `.sg-shop-portal`), så den kan klippes ind som et blokmønster i Extendable uden ny CSS-fil.

```
/shop/                     ← hub (prototypen: shop.html)
  /shop/terrain/           ← eget terræn (penge-siden)
  /shop/bases/             ← baser (kurvfyld, 95 % margin)
  /shop/kits/              ← sammensatte begyndersæt (tynd margin)
  /shop/paints-tools/      ← affiliate, mærket «Reklamelink for …»
/product/sector-ruins/     ← PDP (prototypen: kit.html)
```

| # | Sektion på /shop/ | Formål |
|---|---|---|
| 1 | Hero: «Everything the starter box doesn't include» | Fortsætter sidens LEARN → BUILD → SHOP-linje |
| 2 | Tre veje: Terræn / Baser / Maling & værktøj | Terræn først — det er marginen |
| 3 | Hero-produkt: Sector Ruins | Én vej ind, ikke 20 varer |
| 4 | Sammenligningstabel mod officielle priser | Svarer på «hvorfor ikke bare købe Citadel?» |
| 5 | Affiliate-blok med `Reklamelink for …` | Demonstrerer den danske markering |
| 6 | Tillidsbånd: EU-lager, 2–4 dage, GPSR-ansvarlig, uofficiel | Trust + lovpligtig identitet |
| 7 | Guide → shop-krydsalg (`.sg-ctb` findes allerede) | Binder de 17 posts til butikken |

**PDP-moduler:** galleri → titel/pris → hvad er i kassen → varianter (ruin-sæt / tilføj baser) → kurv → levering/retur → spec-tabel → **GPSR** → «ikke tilknyttet Games Workshop» → relaterede.

---

## 9. 90 dage, i rækkefølge

| Uge | Handling | Krav |
|---|---|---|
| 1 | Affiliate-links i de 17 posts + `reklamelink`-markering | Wayland-konto (kræver ~1.500 følgere eller aktiv blog) |
| 1 | Ret /affiliate-disclosure/ til dansk standard | § 6, stk. 4 |
| 2–3 | Print 3 terrænstykker, fotografer, læg /shop/ live med 3 SKU | Printer |
| 4 | Baser som kurvfyld ved 60 €+ | Samme printer |
| 5–8 | To nye guides målrettet «cheap terrain», «bases» — de to penge-queries | Indhold |
| 9–12 | Mål: ATC ≥ 4 %, AOV ≥ 55 €, retur < 5 % | Måling |

**Beslutningsregel:** hvis terræn sælger 10+ stk. på 90 dage, er det produktet — udvid sortimentet og drop kits. Hvis /shop/ får under 500 sessions, er problemet trafik, og det løses i indholdet, ikke i butikken.

---

## 10. Hvad jeg ikke kunne verificere

- **GW's europæiske trade-vilkår (PDF, juni 2025)** kunne ikke læses — kun de nordamerikanske 2013/2018-dokumenter og trade-FAQ'en er læst i fuld tekst. EU-reglerne om onlinesalg kan være anderledes formuleret, men kravet om fysisk butik står på GW's egen FAQ.
- **Alle MOQ- og rabatsatser** ud over hvad der står i tabellen i §6 er ikke offentliggjort nogen steder i branchen.
- **Omsætning for Kutami, Fantasywelt og Wayland** er ikke offentlig; kun Waylands 2023-resultat (£10,9 mio. overskud) er læst.
- **Om Army Painter accepterer en ren webshop** — programmet er rack-orienteret, men intet sted står der eksplicit nej.
- **Om I har en 3D-printer.** Hele §5 hviler på det; har I ikke, skal investeringen med i regnestykket.

---

## 11. Ændringslog

| Dato | Ændring |
|---|---|
| 2026-11-01 | Første version. Måling af warpstorm.online (17 posts, 7 sider, WP 7.1.3/Extendable, GRIMDARK CSS v1, /shop/ 404, 0 affiliate-links). GW trade-regler, IP-regler, dansk affiliate-markering, markedstal og leverandørshortlist gennemgået. Anbefaling: affiliate nu, eget terræn som produkt, kits sidst. |
