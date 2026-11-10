# Hurtigst mulig organisk trafik — hvad der virker i november 2026

> **Rettelse 10/11 2026 — læs [`organisk-trafik-live-prioritering-2026-11-10.md`](organisk-trafik-live-prioritering-2026-11-10.md) først.** En live-kontrol af de fem watchlist-sider har afkræftet tre af diagnoserne nedenfor: (1) `/how-to-make-a-steampunk-costume/` har **20 unikke interne links** og et HowTo-schema med 6 trin — påstanden om nul links og intet schema gælder ikke længere; (2) `/steampunk-accessories/` har en Rank Math-titel på **59 tegn**, ikke 70; (3) alle 20 produktsider har nu **komplet** Product-JSON-LD med returpolitik og leveringstid (`schema_check` 20/20), så merchant-schema er ikke en åben opgave. Tallene i §2 er **eksterne branchetal**, ikke målinger på steamgears.eu, og de skal ikke læses som effekt på dette site. Resten af dokumentet står uændret som historik.

**Spørgsmålet:** hvordan øger vi hurtigst muligt den organiske trafik til steamgears.eu?

**Kort svar:** den hurtigste trafik ligger ikke i nye sider. Den ligger i de **66 sider Google allerede viser** (193 visninger, 2 klik, position 23,6), i **gratis produktlister** gennem Merchant Center — produktfeedet er stadig den eneste kanal der kan give købsklar trafik inden for dage — og i **Pinterest**, der er en søgemaskine med 60–90 dages modningstid i stedet for 6–12 måneder. AI-citater er endnu ikke trafik (0,1–0,3 % af alle besøg), og `llms.txt` er spildt arbejde: 97 % af alle filer i verden bliver aldrig læst.

---

## 1. Hvad der er målt på sitet (baseline)

Kilde: GSC Search Analytics 12/10–08/11 og GA4 property 559297232, målt 9/11 2026
([`weekly-performance-2026-11-09.md`](weekly-performance-2026-11-09.md)).

| Måltal | Værdi | Hvad det betyder |
| --- | --- | --- |
| Klik (28 d) | **2** | Sitet får stort set ingen organisk trafik |
| Visninger (28 d) | **193** | Google viser siderne — men på side 2–3 |
| Queries / sider med visning | 67 / **66** | Næsten hver side har lidt synlighed, ingen har nok |
| Impression-vægtet position | **23,6** | Gennemsnitligt side 3 |
| Sessions (uge 44) | 150, **0 køb** | Trafikken konverterer ikke endnu |
| Katalog | 20 publicerede produkter | Lille, men alle har `/go/`-links og schema |

Toppen: `steampunk costume ideas` (23 visninger, 1 klik, **position 11,4**), `steampunk goggles`
(21 visninger, position 30,2), `steampunk gears` (13), `dieselpunk vs steampunk` (10, position 13,9).

**Den vigtigste enkeltmåling:** 193 visninger og 2 klik er en CTR på 1,0 % ved position 23,6. Det er
ikke et indholdsproblem — det er et **side 3-problem**. Sider på position 11–20 er den eneste gruppe hvor
en indsats på 1–3 timer pr. side kan flytte klik inden for uger.

Fem sider er allerede faldet og står på watchlisten: `/diy-steampunk-goggles/` (56 → 14 visninger),
`/steampunk-costume-ideas/` (37 → 13), `/how-to-make-a-steampunk-costume/` (32 → 20, position 9,2 → 20,8),
`/steampunk-accessories/` (15 → 8), `/steampunk-vs-dieselpunk/` (16 → 9, 1 klik → 0).

---

## 2. Virkeligheden i 2026: hvor er klikkene blevet af?

| Fund | Tal | Konsekvens for os |
| --- | --- | --- |
| AI Overviews på kommercielle søgninger | **86,8 %** | Vi skal ikke regne med klik på informationssøgninger |
| Organisk CTR med AI Overview | 1,41 % → **0,64 %** (−55 %) | Position 1 er ikke længere position 1 |
| Position 1's CTR-fald ved AI Overview | **−58 %** (Ahrefs) | Selv en førsteplads giver ca. 40 % af gammel trafik |
| Zero-click i USA | **68 %** (2024: 60,5 %) | Trafikken skal hentes, hvor svaret ikke kan give klikket |
| AI Overview-rate for Shopping | **3,2 %** — lavest af alle brancher | **Produkt- og købssøgninger er den sikre zone** |
| AI Overview-rate for transactional-intent | **5 %** | Købsintention giver stadig klik |
| AI-referrals andel af alle besøg | **0,11–0,32 %** | AI er ikke en trafikkanal i 2026, kun et brandsignal |
| AI-besøgendes konvertering | 2,66 % mod 1,91 % gennemsnit | De få der kommer, køber oftere |
| `llms.txt` der aldrig bliver hentet | **97 %** af 38 360 filer | Drop det; brug tiden på siderne |
| Refresh af side med visninger | resultat i **2–6 uger** | Nye sider: 2–4 måneder (2–6 på ny side) |
| Nye sider på lavautoritets-site | **2–4 mdr.** til stabil rang | Forklarer hvorfor 66 sider giver 2 klik |

Kilder: Debutify (6/11 2026), Ahrefs (4/2 2026 og 17/8 2026), Seer/Sistrix-gennemgang hos ZDS (11/7 2026),
Search Engine Land (2/9 2026), Outrank (19/10 2026), Aimerce AI Commerce Report (17/9 2026),
SE Ranking via AI Business Weekly (18/7 2026), Ahrefs' llms.txt-studie (15/6 2026),
TheStacc refresh-studie (10/7 2026), Workshop Digital (18/5 2026).

**Læsningen er entydig:** den organiske trafik findes stadig — 43 % af al e-handelstrafik og 23,6 % af alle
ordrer — men den er flyttet til (a) sider der svarer på noget AI ikke kan svare på, og (b) **produktflader**,
hvor Shopping er den branche med færrest AI-svar. For en butik med 20 produkter og position 23,6 er det en
fordel, ikke en ulempe: vi skal ikke konkurrere om informationssøgninger, vi skal konkurrere om køb.

---

## 3. Kanalerne rangeret efter tid-til-trafik

| # | Kanal | Tid til første målbare trafik | Indsats | Hvad der kræves |
| --- | --- | --- | --- | --- |
| 1 | **Refresh af de 66 sider med visninger** | **1–4 uger** | 1–3 t/side | GSC-data, titel/meta, interne links, opdateret indhold |
| 2 | **Gratis produktlister (Merchant Center)** | **3–14 dage** til godkendelse | 2–4 t engangs | Feed, politikker, fragt/retur, `identifier_exists` |
| 3 | **Merchant listings via schema** (uden feed) | 1–3 uger | 0 t (findes) | Product + Offer + `shippingDetails`/`hasMerchantReturnPolicy` |
| 4 | **Pinterest** (visual search) | **60–90 dage** til reel volumen | 30–60 min/dag | 5–10 friske pins/dag, claiming, Rich Pins, boards |
| 5 | **Long-tail-sider fra egne queries** | 2–4 måneder | 4–8 t/side | Emner hvor vi har visninger men ingen side |
| 6 | **Autoritet/links** (omtaler, presse, communities) | 3–6+ måneder | løbende | Indhold der fortjener links; Reddit-spor A er allerede planlagt |
| 7 | **AI-citater (GEO/AEO)** | usikker, lille | 0 ekstra hvis siden er god | Answer-first struktur; `llms.txt` virker ikke |

Rækkefølgen er ikke en holdning: den følger de målte modningstider. Kanal 1–3 kan give trafik **inden for
denne måned**; kanal 4–5 inden for kvartalet; kanal 6–7 er 2027-arbejde.

---

## 4. Planen — fire faser, hver med en gate

### Fase 0 — instrumentér og luk de billige huller (dag 0–3)

1. **Tænd GSC's Generative AI-report** (rullet ud 3/6 2026) og læs hvilke sider AI-svar bruger. Den viser kun
   visninger, ikke klik — læg dem ikke sammen med Performance-rapporten (dobbeltregning).
2. **Segmentér GSC på intention.** Købssøgninger (produkt + mærke + pris) er den gruppe vi kan vinde; rene
   informationssøgninger er den gruppe AI tager. Al rapportering fremover skal skille de to.
3. **Ret de fem watchlist-sider.** De har allerede vist at de kan rangere; det er den billigste trafik i hele
   planen. Konkret pr. side: opdater fakta og årstal, tilføj 2–3 nye interne links **til** siden, og skriv
   titel/meta om mod det query der faktisk giver visninger.
4. **Titel-omskrivning på de 10 sider med position 5–20.** `steampunk costume ideas` på position 11,4 er det
   tætteste vi har på side 1. Målet er CTR, ikke blot rang: en side på position 8 med 2 % CTR slår en side på
   position 5 med 0,5 %.

**Gate 0:** de fem sider er oppe igen (visninger ≥ 80 % af før-niveau) inden 4 uger, ellers undersøges SERP'en
for den enkelte query før der skrives mere indhold.

### Fase 1 — gratis produktlister (uge 1–2)

Merchant Center er den eneste kanal hvor en butik med 20 produkter kan stå i Google **uden** at vinde en
tekst-søgning: gratis produktlister vises i Search, Shopping-fanen, Billeder, Lens, YouTube og Gemini, og
AI-shopping trækker på samme feed. Kravene er kendte og mekaniske:

- Feed via **Google for WooCommerce** (allerede den anbefalede vej til WooCommerce); produkter bliver
  `Active` 3–5 dage efter godkendelse.
- **Fragt og retur** skal stå i feedet, ellers afvises eller nedprioriteres produkterne. Det er samme
  manglende data som GPSR-blokken og de manglende forsendelseszoner i roadmap §4c — feedet tvinger dem på plads.
- **`identifier_exists: false`** for Printful-produkter uden GTIN. Det er den fejl der oftest giver afvisning
  på POD-butikker.
- Suppler med **merchant listings** fra den schema der allerede ligger: `Product` + `Offer` +
  `shippingDetails` + `hasMerchantReturnPolicy`. Schema alene kan give produktflader uden feed.

**Gate 1:** ≥ 15 af 20 produkter `Active`, 0 afvisninger på fragt/retur, og de første gratis-liste-klik i
Merchant Centers Performance-rapport inden 14 dage. Kommer der ingen visninger, er det feedet — ikke
søgeordene — der er problemet.

### Fase 2 — Pinterest som anden søgemaskine (uge 2–10)

Pinterest er den eneste kanal i planen hvor en ny side kan få trafik på under tre måneder, fordi pins rangeres
på **pin-niveau**, ikke på domæneautoritet. Reglerne er målbare:

- **Claim domænet** og slå Rich Pins til, så pris og lager følger med fra produktsiden.
- **5–10 friske pins om dagen**, 2:3, 1000 × 1500 px. »Frisk« betyder nyt billede eller ny titel — ikke samme
  pin på et nyt board, som algoritmen straffer.
- **Static pins** giver langt de fleste outbound clicks; collager/video giver rækkevidde, ikke klik.
- Nøgletallet er **outbound clicks**, ikke visninger. Sund CTR for produkt-pins: 0,5–1,5 %. Vækst i første år:
  15–25 % måned for måned.
- Indholdet findes allerede: 18 guides + 20 produkter = rigeligt til 60 dage à 5 pins uden at gentage et motiv.

**Gate 2:** ≥ 30 outbound clicks/dag ved dag 90, og mindst 25 % af dem skal lande på en produktside (måles med
UTM på alle tre parametre). Under 10 clicks/dag ved dag 60: skift creative, ikke kadence.

### Fase 3 — long-tail-sider hvor vi allerede har visninger (uge 4–12)

Nye sider rangerer på 2–4 måneder på et site som vores, så de skal skrives nu for at virke i
forårs-/halloweensæsonen 2027. Udvælgelsen er data-drevet: **queries hvor vi har visninger men ingen dedikeret
side** (fx `steampunk gears`, `steampunk archetypes`, `steampunk terms`), plus købsintention omkring de 20
produkter. En side om ugen, hver med quick answer, first-hand-sektion, FAQ og 2+ egne billeder — GEO-skabelonen
i [`../seo.md`](../seo.md) §2, som de 18 guides allerede følger.

**Gate 3:** hver ny side skal have ≥ 10 visninger inden 8 uger, ellers flettes den ind i en eksisterende side i
stedet for at ligge som endnu en tynd URL.

---

## 5. Det der ikke virker — og som koster mere end det giver

| Fristelse | Hvorfor nej (målt) |
| --- | --- |
| `llms.txt` og »GEO-filer« | 97 % af 38 360 filer bliver aldrig hentet; Google siger den ikke bruges til Search |
| Masse-AI-indhold for at »dække flere søgeord« | AI Overviews æder informationsklik; Google kræver first-hand-signaler (1/10 2026) |
| At jagte head-terms som »steampunk« | 96,55 % af indekserede sider får nul organisk trafik; magtlovgivning gælder |
| Købte links / linkkataloger | Spam-updates marts, juni, august og september 2026 |
| At publicere nye sider i stedet for at rette de gamle | Refresh virker i 2–6 uger; nye sider i 2–4 måneder |
| At måle »samlet organisk« som ét tal | AI Overviews flytter klik mellem intentioner; uden segmentering ser alt ud som et fald |
| At vente med halloween-indhold til september | Indeksering og rang tager uger til måneder; siden skal være klar 6–10 uger før |

---

## 6. Måling: hvad der skal stå på tavlen

| Måltal | Nu (28 d) | Dag 30 | Dag 60 | Dag 90 |
| --- | --- | --- | --- | --- |
| Organiske klik | 2 | 10 | 25 | 50 |
| Visninger | 193 | 400 | 800 | 1 200 |
| Sider på position ≤ 10 | 0–1 | 3 | 6 | 10 |
| Gratis produktliste-klik | 0 | 5 | 20 | 40 |
| Pinterest outbound clicks/dag | 0 | 5 | 15 | 30 |
| Add-to-cart fra organisk | 0 | 1 | 5 | 12 |

Tallene er **mål**, ikke prognoser: de er sat ud fra at hver fase kun leverer det dens gate kræver. Den eneste
måling der ikke må manipuleres er GSC's egne 28-dages tal — alt andet (GA4, Pinterest, Merchant Center) er
sekundært og skal kun bruges til at forklare hvorfor GSC-tallene bevæger sig.

---

## 7. Forbehold — hvad dette dokument ikke ved

- **Ingen live-måling i denne kørsel.** Tallene er 9/11-snapshotet fra `weekly-performance-2026-11-09.md`,
  ikke et nyt GSC-udtræk; GSC kræver ejerens OAuth.
- **Autoritet er ikke målt.** Vi kender ikke domænets backlinkprofil, kun at det er lille nok til at 66 sider
  giver 2 klik. En DR-måling ville ændre vægtningen mellem links og indhold.
- **Merchant Center er ikke oprettet.** Om butikken kan godkendes afhænger af fragt-, retur- og
  virksomhedsdata der stadig mangler (roadmap §4c, GPSR-blokken).
- **Pinterest-tokenet virker ikke endnu.** Appen mangler `boards:read`, så kanalens API-vej er blokeret indtil
  ejerens genforbindelse; den manuelle vej virker i dag.
- **Sæsonen er ikke modelleret.** Halloween er den største søgepeak for kategorien, og den ligger forbi for i år.
  Planen er derfor rettet mod julegavevinduet (november–december) og halloween 2027.

---

## 8. Kilder

- Debutify, *Organic Search 2026: Ecommerce Stats & AI-Era Growth Plan*, 6/11 2026 —
  <https://debutify.com/blog/organic-search-for-ecommerce-2026>
- Ahrefs, *Update: AI Overviews Reduce Clicks by 58 %*, 4/2 2026 —
  <https://ahrefs.com/blog/ai-overviews-reduce-clicks-update>
- Ahrefs, *12 Fast & Proven Ways to Increase Organic Traffic* (opdateret 17/8 2026) —
  <https://ahrefs.com/blog/how-to-increase-organic-traffic>
- Ahrefs, *We Analyzed 137K Sites: 97 % of llms.txt Files Never Get Read*, 15/6 2026 —
  <https://ahrefs.com/blog/llmstxt-study>
- ZDS, *AI Overviews and Traffic: What the Data Shows*, 11/7 2026 —
  <https://zds.es/en/insights/ai-overviews-and-traffic-what-the-data-shows>
- Search Engine Land, *3 SEO priorities to win organic traffic in 2027*, 2/9 2026 —
  <https://searchengineland.com/seo-priorities-win-organic-traffic-454482>
- Optimum Web, *Organic Traffic Drop in 2026*, 29/9 2026 —
  <https://www.optimum-web.com/blog/organic-traffic-drop-causes-2026>
- Outrank, *How to Increase Website Traffic Organically (2026 Data)*, 19/10 2026 —
  <https://www.outrank.so/blog/how-to-increase-website-traffic-organically>
- Google Merchant Center Help, *Free listings for products* —
  <https://support.google.com/merchants/answer/13889434>
- WooCommerce, *Product feed information and free listings* —
  <https://woocommerce.com/document/google-for-woocommerce/get-started/product-feed-information-and-free-listings>
- Pinterest Business Help, *Pin performance and distribution* —
  <https://help.pinterest.com/en/business/article/pin-performance-and-distribution>
- Digimau, *Pinterest SEO: 2026 Visual Search Strategy Guide*, 2/10 2026 —
  <https://www.digimau.com/pinterest-seo-strategy-guide-2026>
- Sprout Social, *How to win with the Pinterest algorithm in 2026*, 26/1 2026 —
  <https://sproutsocial.com/insights/pinterest-algorithm>
- TheStacc, *Content Update Impact Study*, 10/7 2026 —
  <https://thestacc.com/blog/content-update-impact-study>
- Raptive, *We analyzed 103 000 content refreshes*, 26/6 2026 —
  <https://raptive.com/blog/refresh-analysis-and-google-traffic-growth>
- Aimerce, *The AI Commerce Report*, 17/9 2026 — <https://www.aimerce.ai/report/ai-commerce>
- AI Business Weekly, *AI Search Statistics 2026*, 18/7 2026 —
  <https://aibusinessweekly.net/p/ai-search-statistics-2026-market-share-zero-click-geo>
