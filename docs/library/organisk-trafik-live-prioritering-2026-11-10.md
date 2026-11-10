# Organisk søgetrafik — de hurtigste realistiske gevinster

**Målsite:** steamgears.eu (WordPress + WooCommerce). Read-only research pr. 10/11 2026: ingen CMS-, indholds-, kode- eller publiceringsændringer er udført i denne tur.

## Konklusion

Den korteste vej er ikke at udgive flere URL'er. Brug et friskt Search Console-udtræk til at vælge blandt de sider, Google allerede viser, og ret først den bedst dokumenterede side-til-query-uoverensstemmelse: `/steampunk-costume-ideas/`, hvor H1 lover “30+” og metadata lover 15 looks, mens søgeordene `steampunk archetypes`/`steampunk character archetypes` slet ikke findes i teksten.

To ting skal siges ligeud, fordi de ændrer prioriteringen:

1. **Den gamle refresh-arbejdsordre er delvist forældet.** Live-kontrollen viser, at `/how-to-make-a-steampunk-costume/` allerede har 20 unikke interne links og et HowTo-schema med 6 trin. Den skal ikke “repareres” igen. `/steampunk-accessories/` har en Rank Math-titel på 59 tegn — ikke 70, som den gamle diagnose sagde.
2. **Merchant-schema er ikke længere en åben opgave.** Alle 20 publicerede produkter har live `Product`-JSON-LD med pris i DKK, lagerstatus, returpolitik og leveringstid. Det, der stadig er ubekræftet, er Merchant Center-forbindelsen og et godkendt feed — og det kan ingen lokal måling afgøre.

Ingen af delene er i sig selv en trafikgaranti. Googles egen dokumentation siger, at structured data gør en visning *mulig*, ikke sikker, og at gen-crawl tager dage til uger.

## Verificerede fund

| Fund | Bevis | Hvad det kan og ikke kan sige |
|---|---|---|
| GSC-baseline: 2 klik, 193 visninger, 67 queries, 66 sider, impression-vægtet position 23,6 i 28 dage (12/10–08/11) | Projektets GSC-snapshot 09/11, [`weekly-performance-2026-11-09.md`](weekly-performance-2026-11-09.md) | Meget lav volumen. 23,6 er et vægtet gennemsnit, ikke hver sides rang, og data er ikke friskere end 08/11. |
| Stærkeste dokumenterede query: `steampunk costume ideas`, 23 visninger, 1 klik, position 11,4 | Samme snapshot | Lille stikprøve, men tættere på side 1 end sitets gennemsnit. Bekræft med nyt udtræk før handling. |
| Nye queries 09/11: `steampunk archetypes` (position 3,3), `steampunk character archetypes` (3,5, 1 klik), `steampunk terms` (8,3) | Samme snapshot | Positionerne er gode, men grundlaget er 1–2 visninger. Ordet “archetypes” findes **0 gange** i post 15's indhold; “terms” 0 gange. |
| Watchlist-fald: goggles 56→14 visninger, costume ideas 37→13, how-to 32→20 og position 9,2→20,8, accessories 15→8, vs-dieselpunk 16→9 | Samme snapshot + [`refresh-kanal-1-2026-11-10.md`](refresh-kanal-1-2026-11-10.md) | Sammenligning mod forrige vindue, ikke aktuel rang. Især goggles' 7→49 er knyttet til **1 impression** og er derfor skrøbeligt. |
| GA4 uge 02–08/11: 150 sessions, 159 pageviews, 0 purchase-events. Ugen før: 1.359 sessions, hvoraf 1.046 var Bangladesh via Cross-network | Samme ugentlige rapport | De 150 er **ikke** et organisk tal. Den store uge er en trafik-anomali, ikke et fald i organisk søgning. |
| `/steampunk-costume-ideas/`: HTTP 200, canonical til sig selv, 1.515 ord, 35 links, 4 billeder uden manglende alt-tekst. H1 = “30+ Steampunk Costume Ideas…” (68 tegn). Rank Math title = “Steampunk Costume Ideas: 15 Captains, Inventors & More” (54), description 152 tegn og lover “15 characters” | Live MCP-læsning 10/11; post 15, sidst ændret 08/11 | Verificeret løfte-mismatch mellem H1 og metadata. Det er **ikke** målt, at det koster CTR; Google kan selv generere title link og snippet. |
| `/how-to-make-a-steampunk-costume/`: HTTP 200, 1.996 ord, 14 billeder, 46 links, **20 unikke interne hrefs**, HowTo med 6 trin + FAQPage | Live MCP-læsning 10/11; post 8, sidst ændret 08/11 | Den gamle “nul links / intet schema”-diagnose er afkræftet. Tællingen gælder links i indholdet — ikke om Google har gen-crawlet. |
| `/diy-steampunk-goggles/`: HTTP 200, 1.941 ord, 11 billeder, HowTo + FAQPage; SEO title 48 tegn, description 155 | Live læsning 10/11; post 10 | Indhold og schema findes allerede. Faldet skal forklares med intent/SERP, ikke med “mangler schema”. |
| `/steampunk-accessories/`: HTTP 200, SEO title **59 tegn**, description 148; H1 70 tegn | Live læsning 10/11; post 30 | Den gamle 70-tegns *SEO title*-diagnose passer ikke på det nuværende felt. H1 og SEO title er forskellige felter. |
| `/steampunk-vs-dieselpunk/`: HTTP 200, SEO title 58, description 136, korrekt canonical | Live læsning 10/11; post 798 | Beskrivelsen er under projektets 140–155-mål, men en ny meta description garanterer ikke, at Google viser den. |
| **Alle 20 publicerede produkter** har live Product-JSON-LD med `offers`, `priceCurrency: DKK`, `availability: InStock`, `itemCondition: NewCondition`, `hasMerchantReturnPolicy` (14 dage, 27 EU-lande) og `shippingDetails` med `handlingTime` 3–7 + `transitTime` 6–10 for DK og EU | `python3 tools/schema_check.py` 10/11: **20/20 complete**, exit 0. Rå JSON-LD læst på `/product/copper-pilot-goggles/` | Merchant listing-markup er på plads. **Men** `shippingDetails` har ingen `shippingRate` (butikken har ingen fast fragtpris), og **ingen af de 20 har `aggregateRating`** — så fragtlinjen kan ikke kvalificere, og stjerne-resultater er ikke mulige uden anmeldelser. |
| Google for WooCommerce 3.10.0 og Site Kit 1.191.0 er installerede og aktive; butiksvaluta er DKK; Rank Math melder aktiv sitemap-index med estimeret 20 produkt-URL'er | Live plugin-/option-læsning 10/11 | Installation er **ikke** bevis på Merchant Center-forbindelse eller godkendt feed. Option-læsning på de sædvanlige GLA-nøgler gav intet fund, så forbindelsen kan ikke bekræftes herfra. |
| GSC Crawl Stats og Search Console API har tidligere svaret 401 fra sandkassen; til gengæld svarede 121/121 URL'er 200 i crawl-auditen 02/11 | [`restpunkt-gennemgang-2026-11-08.md`](restpunkt-gennemgang-2026-11-08.md), [`seo-audit-2026-11-02.md`](seo-audit-2026-11-02.md) | Neutral HTTP 200 beviser ikke, hvad Googlebot modtog. En 455 fra en cloud-IP er ikke bevis på en Google-blokering. |

## Prioriteret plan

| # | Handling | Indsats | Mulig tid til signal | Begrundelse og måling |
|---|---|---:|---|---|
| **0** | **Hent frisk GSC-data først.** Ejer eksporterer Search Console Performance for seneste og forrige 28 dage — både **Pages** og **Queries** — med clicks, impressions, CTR og position, filtreret på web. Knyt de største queries til URL'er, og skeln informationssøgning fra købsintention. | 15–30 min | Samme dag for diagnosen; ingen trafikeffekt i sig selv | Snapshotet slutter 08/11, og de rå rækker findes ikke i repo eller API. Uden dem kan hverken faldet på goggles eller de nye “archetypes”-queries bekræftes. Google anbefaler at analysere efter både query og side; gennemsnitsposition alene bør ikke styre arbejdet. |
| **1** | **`/steampunk-costume-ideas/`:** afstem H1 (“30+”), antallet af reelt beskrevne looks og Rank Math title/description (15) til **ét sandt løfte**. Hvis `steampunk archetypes`/`character archetypes` fortsat har impressions, tilføj en kort sektion med de arketyper, der faktisk er på siden — i den eksisterende URL, ikke en ny. | 30–60 min | Gen-crawl: dage til uger (Google). Klik-effekt usikker; mål over 28 dage | Bedst dokumenterede side-nære mulighed: queryen har 23 visninger og position 11,4, og mismatchet er verificeret i det faktiske indhold. **Hypotese:** rettet løfte + dækkede søgeord kan løfte relevans/CTR. Mål query + URL før/efter mod en kontrolside og notér redigeringsdato. |
| **2** | **Merchant Center-status, ikke mere schema.** Kontrollér i Merchant Center: konto, feed-kilde, destinationslande, item-status og disapprovals for de 20 varer. Fix kun det, der faktisk står som afvist. | 30–60 min | Ukendt indtil feed er godkendt; Google lover ingen dato | Schema-delen er allerede live og komplet (20/20). Den reelle åbne flaskehals er feed/forbindelse samt de to målte svagheder: **ingen `shippingRate`** (fragtlinjen kan ikke kvalificere) og **ingen ratings** (ingen stjerner). Mål Merchant Center-impressions/klik og GSC Search Appearance — ikke GSC-webklik som “listing-klik”. |
| **3** | **Produktfladerne, hvis feedet er godkendt:** 5–8 billeder pr. vare (roadmap målte 8/11 under 3 pr. produkt) og ægte anmeldelser via den allerede byggede review-anmodning. | 1–2 t for top 10 + ventetid på ordrer | Uger til måneder; afhænger af ordrer | Billedantal og ratings er de to ting, Shopping-flader bruger til at vælge og vise en vare. Anmeldelser kan ikke fabrikeres — de kommer først efter leverede ordrer, så dette er ikke en “i morgen”-gevinst. Mål item-rapportens klik/CTR pr. vare. |
| **4** | **`/diy-steampunk-goggles/` — kun ved bekræftet fald.** Hvis punkt 0 viser, at siden stadig taber en query med reel volumen, gennemgå den aktuelle SERP og ret én konkret intent-mismatch. Behold de tre byggemetoder, HowTo og FAQ. | 30–60 min | Dage til uger; effekt usikker | Det historiske 7→49 blev målt på **1 impression**. At skrive en fjerde guide eller tilføje schema igen er arbejde på et problem, der muligvis ikke findes. |
| **5** | **Nye sider: nej, indtil query-gap er målt.** Match de 66 sider med impressions mod søgeintention. Kun queries med vedvarende visninger og uden dækkende side får nyt indhold; ellers udvides en eksisterende side. | 30–60 min dataarbejde | Nye URL'er: uger til måneder | En ny URL på et lille site konkurrerer med allerede indekseret indhold og kan ikke kaldes den hurtigste vej ud fra én position-række. Mål 4 og 8 uger; flet ind, hvis der ikke kommer visninger. |

**Udfør ikke nu:** gentag ikke link-/HowTo-indsatsen på `/how-to-make-a-steampunk-costume/`, og forkort ikke SEO-titlen på `/steampunk-accessories/` ud fra den gamle 70-tegns værdi. Begge bygger på tilstande, live-kontrollen har afkræftet.

## Måleprotokol

1. Gem GSC-udtræk med dato, søgetype (web) og datovindue; brug præcis samme filtre næste gang.
2. Før ændring: gem 28-dages page- og query-rækker for den valgte URL. Notér clicks, impressions og CTR; position er diagnostik, ikke succeskriterium alene.
3. Efter ændring: notér redigeringsdato, vent på gen-crawl, og sammenlign først efter 28 fulde dage — også mod kontrolsider og sitets total.
4. GA4 må kun bruges til organiske landinger og downstream events med samme consent-/attributionsfilter. Rapportens 150 sessioner er ikke et organisk segment.
5. Hold Merchant Center-klik og GSC-webklik adskilt. Læg aldrig forskellige kilder sammen og kald det “organisk trafik”.

## Hypoteser og manglende data

- **Hypotese:** afstemt H1/title/løfte på `/steampunk-costume-ideas/` forbedrer relevans og klikrate, hvis siden fortsat ligger omkring side 1–2. Ikke testet; Googles title link kan komme fra flere kilder end title-tagget.
- **Hypotese:** Merchant Center-gratislisting er den hurtigste købskanal, fordi markup allerede er komplet. Men konto, feedstatus, destinationer og disapprovals er **ikke** verificeret, og et feed kræver fragt-/returdata, som butikken kun delvist har (ingen fast fragtpris).
- **Mangler:** frisk GSC-query-/side-eksport, URL Inspection/Crawl Stats, Merchant Center-status, SERP-/browser-inspektion samt en organic-only GA4-rapport. Repo og MCP gav live WordPress-data, men ingen Google-kontodata.
- **Ikke målt:** trafikeffekt. Kun read-only research er udført. Tid-til-effekt er Googles generelle gen-crawl-vejledning (dage til uger) eller eksplicit markeret som usikker — ikke en prognose for Steamgears.

## Kilder

**Førstehåndsdata:** `weekly-performance-2026-11-09.md` (GSC/GA4), `refresh-kanal-1-2026-11-10.md` (historisk watchlist), `docs/seo.md`, `docs/roadmap.md` (S4.7, S4c), live read-only MCP-kontrol 10/11 af post 8, 10, 15, 30, 798, Rank Math, sitemap, plugins, valuta samt `tools/schema_check.py` (20/20).

**Google Search Central:** [Performance report](https://support.google.com/webmasters/answer/7576553), [Title links](https://developers.google.com/search/docs/appearance/title-link), [Search snippets](https://developers.google.com/search/docs/appearance/snippet), [Product structured data](https://developers.google.com/search/docs/appearance/structured-data/product), [Merchant listings](https://developers.google.com/search/docs/appearance/structured-data/merchant-listing), [Recrawl](https://developers.google.com/search/docs/crawling-indexing/ask-google-to-recrawl).
