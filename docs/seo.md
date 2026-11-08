# SEO — regler, skabeloner og måling for steamgears.eu

_Dette er repoets **eneste** SEO-dokument. Følgende er slået sammen her og slettet (gendannelig med
`git show b7a0438:<sti>`): `docs/library/seo.md`,
`seo.md`, `seo.md`,
`seo.md`, `seo.md`,
`seo.md`, `seo.md`,
`knowledge_base/seo.md`, `docs/seo-regler-2026/seo.md`,
`docs/seo-handlingsplan-24-10/` (fakta-tabel + Gemini-gap-analyse) og `docs/content-plan-24-10/`
(keyword- og SERP-scan). De to sidste var 24/10-analyser; deres udfald er ført ind i
[`roadmap.md`](roadmap.md) og i projektstatus-historikken._

Sidens status og næste skridt står i [`roadmap.md`](roadmap.md) (S2 og S4). Dette dokument er reglerne.

---

## 1. Grundregler (gælder alle sider)

| Område | Regel |
| --- | --- |
| **Title + H1** | Primært søgeord inden for de første 50 tegn. Titel max 62 tegn, H1 max ~70. Kun **én H1** pr. side — den ekstra H1 i hero-sektionerne på `/`, `/free-starter-kit/` og `/steampunk-halloween/` er stadig ikke rettet, fordi det ændrer typografien og skal ses i browser først |
| **Meta description** | 140–155 tegn, søgeord først, konkret løfte eller CTA. Ingen auto-trunkerede excerpts (runde 1/11 rettede de sidste 3) |
| **Produkttekster** | Aldrig Printful-standardtekst. 150–300 ord unik tekst med materialer, pasform og cosplay-anvendelse |
| **Billeder** | Alt-tekst på alt. Beskriv motivet, ikke filnavnet. 210 billeder verificeret 2/11 |
| **Intern linking** | Hvert indlæg linker mindst ét produkt via `/go/<id>/` og mindst to guides plus den relevante hub |
| **Schema** | Product + Offer + ItemAvailability på produkter, HowTo på guides, BreadcrumbList sitebredt. Valuta i schema følger butikkens valuta — **USD i dag**, se S4.3 |
| **Canonical** | Rank Math sætter canonical = permalink. Afvig aldrig uden grund |
| **noindex** | Kun cookie- og opt-out-sider samt `digital-downloads`-kategorien |

**Slugs må ikke ændres** på publicerede produkter eller sider. Der er 39 indgående produktlinks og 24
rettede linkmål; et slug-skift brækker dem alle.

## 2. GEO-skabelon (blive citeret af AI Overviews)

Zero-click er normalen: AI Overviews står for ca. 13 % af queries. Målet er at blive **kilden** der citeres,
og at holde klikket med et tilbud AI ikke kan give — komplette looks med priser og købslinks.

1. **Quick answer-boks** øverst: 1–2 sætninger direkte på søgeordet.
2. **»In one line«** i hver hovedsektion/metode.
3. **»What we used« / »How We Tested«** — first-hand: stykliste, pris, tidsforbrug, dato. Fejl der fejlede
   er troværdighedsmarkører (fx »limer før maling var forkert«).
4. **Byline** med lokation: »styled and photographed in the Steamgears workshop, Aabenraa« + dato.
5. **About-box** med entitetstekst, sameAs (FB/Pinterest/IG/YouTube), link til `/about/` og hub.
6. **FAQ** med minimum 5–6 spørgsmål, der dækker søgeordets spørgsmål plus praktiske detaljer.
7. **Minimum 2 egne billeder** pr. guide.

Status: **18/18 poster i guide-stakken** bærer GEO-skabelonen (afsluttet 26/10). Nye guides skal bygges med
den fra dag ét, ikke tilføjes bagefter.

## 3. Guide-skabelon for nye guides (casual-sporet)

De tre regler fra research 26/10, som gælder alle nye guides:

**Regel 1 — first-hand experience (obligatorisk).** Googles AI-indholdsguide (opdateret 1/10 2026) kræver
menneskelig faktatjek og førstehåndserfaring; AI-content-mills devalueres. Min. 2 egne fotos pr.
outfit-sektion, »what we used«-boks med rigtige produkter, procesnoter, maker-profil der linker `/about/`.

**Regel 2 — design mod zero-click.** Guiden skal tilbyde det AI ikke kan: komplette outfit-kombinationer med
priser og direkte købslinks, »Complete this look«-sektion med shop-row pr. hovedsektion, konkrete tal
(budget, tid, hvor hvert produkt købes), og en unik vinkel pr. guide (fx »casual steampunk under $80«) i
stedet for generisk »hvad er casual steampunk«.

**Regel 3 — schema fra dag ét.** HowTo på hele guiden, Product + Offer + ItemAvailability på linkede
produkter, AggregateRating kun hvor der findes reelle tal, valideret i Rank Math før publish.

Fast struktur: H1 + hook med tal → hurtigsvar-boks → outfit-breakdown (foto + »what we used« + buy-link) →
»Complete this look« → budget-tabel → FAQ (min. 4, med FAQPage) → intern linkning (min. 2 guides + hub).

## 4. Schema-tjek før og efter publicering

- **Ét HowTo-schema pr. side.** Tjek hvad der allerede ligger, før du indsætter — dublet-modstridende skemaer
  er en reel faldgrube.
- **FAQPage skal matche den synlige FAQ 1:1.** Udvider du FAQ, skal schema opdateres i samme handling.
- **Dublet-tjek:** tæl forekomster af spørgsmålet i hele sidens HTML (schema + body) efter hver indsættelse.
  Hvert spørgsmål skal stå præcis **2×**: synligt og i schema.
- **Verificér i API-output**, ikke i editoren: `context=edit` eller rendered HTML via REST.

## 5. Cache- og WAF-fælder (Simply + W3TC)

- **Front-cachen har TTL.** Et screenshot kan vise en forældet version. **API-diff er sandheden** — læs
  `?rest_route=`/`wp/v2/...` med rendered content, ikke browseren.
- **curl-bursts udløser `454 Checking your browser`.** Vent 5–10 min eller brug en browser-session. Dette
  workspace rammer 454 på alle HTML-visninger, men REST-svarer 200 — brug REST til verifikation.
- **Pacing:** 5–6 sekunder mellem skrivende kald. Ved 429/454: stop, vent, genoptag — aldrig retry-spam.
- **Payload:** byg JSON i en fil, valider den, send ét enkelt POST med inline `--data "$(jq -c . fil)"`.
  `--data @fil` fejler med `rest_invalid_json` på denne opsætning.
- Purge W3TC efter hver batch og verificér i rendered HTML.

## 6. Måling

**Baseline (GA4-property 559297232, 13–26/10 2026).** Guide-landingsider: 32 sessions, 54 sidevisninger,
3 shop-besøg, **0 add_to_cart, 0 key events**. Sitebredt ~3.333 session_starts, hvoraf 3.075 landede på `/`.
Guide-andelen er ca. 1 % af trafikken. To forbehold: GEO-opdateringen blev rullet 26/10, så hele vinduet er
baseline, og 25–26/10 havde et trafikspike (1.874 + 1.267 sessions, 97 % på `/`) der forurener gennemsnit.

**GSC-baseline (12–24/10).** goggles 5 klik/73 visninger/pos 7,58 · how-to-make 2/28/8,46 · ideas 2/45/13 ·
budget 0/15/6,33 · beginners 0/16/9,5 · diy-vs-buy 0/1/7 · under-100 0 visninger. 25–31/10: 3 klik, ~110
visninger, goggles pos 8,7 og how-to-make pos 9,3 — de første side-1-positioner er ved at etablere sig.

**Faste tjek.** GSC `query_stats` ugentligt (drift efter volatiliteten 20–21/10). AI-citationssignalet er
impressions på spørgeords-keywords 2–3 uger efter en GEO-runde. GA4-udtrækket genkøres med samme filtre
(mod `/workspace/tmp/ga4_b1.json`) for at kunne sammenligne.

**Kendt instrumenteringsfejl:** GA4 fyrer **dobbelt** på hvert pageview — `G-0HC9BLQ88R` (GTM Kit) og
`G-3MYLFKY8MY` (gtag/Site Kit). Alle events tælles dobbelt, indtil den ene fjernes.

## 7. Research — hvad der er værd at huske

- **23/10:** rygter om oktober-opdatering; AI Overviews dominerende i US; skærpede krav om førstehåndserfaring
  i produktindhold. Kilder: Google Search Status Dashboard, Search Engine Watch.
- **26/10 (Gemini 3.8 Flash):** AI-indholdsguiden opdateret 1/10 med krav om menneskeligt faktatjek ·
  **Universal Cart/UCP** — agentic checkout i AI Mode gør Merchant Center-feeds til en salgskanal, ikke bare
  synlighed · ingen bekræftet core update, men kraftig volatilitet 20–21/10.
- **Konsekvens for os:** guides skal bære first-hand-signaler; produktfeeds (pris, lager, fragt, retur) bliver
  konverteringskritiske; tynde generiske artikler skal beriges eller fjernes.

## 8. Kendte, ikke-løste punkter

1. ~~**To H1** på `/`, `/free-starter-kit/`, `/steampunk-halloween/`~~ — **lukket 8/11:** målt 2 → **1** på begge sider (indholdets blok-H1 gjort til H2 på 801 og 713), alle 200.
2. ~~**`robots.txt` har to `User-agent: *`-grupper**~~ — **lukket 8/11:** målt 3 grupper → **1** via `sgap-robots-consolidation.php`, alle 14 `Disallow`/1 `Allow`/1 `Sitemap` bevaret, 200.
3. ~~**Navigationen er `wp:page-list`**~~ — **lukket 8/11 som verificeret:** `page-list` er 0 forekomster, nav viser de 11 tilsigtede punkter og ingen kurv/kasse. Specen i [`library/navigation-spec-2026-11-02.md`](library/navigation-spec-2026-11-02.md) er altså anvendt.
4. ⛔ **Ejer-blokeret — performance (målt 8/11):** produktside **268 KB** HTML, **34,8 KB** inline CSS, **88** script-tags (var 458/151/105), og `payload_audit` viser **8/8 skabeloner inden for det vedtagne loft**. Det der stadig ikke er gjort, er W3TC's egen minify/lazyload/database-cache: indstillingerne ligger i `wp-content/w3tc-config/master.php`, ikke i options, og kan derfor ikke sættes gennem de tilgængelige abilities. **Ejeren skal levere:** (a) slå **Minify (JS+CSS), Lazy load images og Database cache** til i W3TC → General Settings, og (b) bagefter gennemføre **én rigtig testordre** i checkout, så vi ved at kurven og betalingen stadig virker. Uden (a) kan agenten ikke måle effekten, og uden (b) må den ikke slå det til. Derefter måler agenten de fire skabeloner igen og skriver resultatet her.
   er slået fra og kan ikke sættes via MCP. Dette er den største tilbageværende SEO-risiko.
5. ⛔ **Ejer-blokeret — GPSR-blokken mangler postadresse** på alle produkter (24 i kataloget 8/11: 20 publiceret, 4 kladde). **Ejeren skal levere CVR-nummer, registreret firmanavn og postadresse** (samme oplysninger som roadmap S4.2); så snart de findes, indsættes de i `sgap-product-schema` på under en time.
6. ~~**Printful kan overskrive titler og tekster**~~ — **lukket 8/11 som verificeret:** ingen POD-connector installeret (hverken Printful eller Printify), `printify_settings` tom, så der er ingen sync der kan overskrive.
7. ~~**`/steampunk-costumes/` hub**~~ — **lukket 8/11:** målt 200, `robots` = `follow, index` og med i `page-sitemap.xml`; der er ingen fejl på sitet. Den eneste opfølgning er GSC's indekseringsstatus, som ligger hos ejeren under S0.6.

## 9. Kilder

- [Google Search Essentials / SEO Starter Guide](https://developers.google.com/search/docs/fundamentals/seo-starter-guide)
- [Using generative AI content](https://developers.google.com/search/docs/fundamentals/using-gen-ai-content) (opdateret 1/10 2026)
- [Ecommerce SEO](https://developers.google.com/search/docs/specialty/ecommerce/overview) · [URL-struktur](https://developers.google.com/search/docs/specialty/ecommerce/designing-a-url-structure-for-ecommerce-sites) · [Product structured data](https://developers.google.com/search/docs/appearance/structured-data/product)
- [Google Search Status Dashboard](https://status.search.google.com/) · [Universal Cart](https://blog.google/products-and-platforms/products/shopping/universal-cart-launch/)
- Høstede kilder ligger i [`knowledge_base/`](../knowledge_base/) — de læses automatisk af `seo_agent.py`
  sammen med dette dokument.
