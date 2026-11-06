# Site-målinger 6/11 2026 — shipping/returns, payload og analytics

Read-only målinger af steamgears.eu, lavet som grundlag for forslagene i
[`../roadmap.md`](../roadmap.md) §9. Dokumentet ændrer ikke noget på sitet; det erstatter de tal, der blev
antaget 1.–3/11, hvor de ikke længere holder.

## Metode

- Neutral `Mozilla/5.0`-UA (samme som [`tools/waf_check.sh`](../../tools/waf_check.sh) bruger), ingen credential.
- Én forespørgsel pr. sti til status, titel, H1, canonical og bytes; derefter tre gentagelser af responstiden
  samme dag for at skelne kold cache fra varm.
- `curl` fra denne maskine, ikke en browser og ikke Lighthouse. Tallene er sammenlignelige før/efter, ikke
  Core Web Vitals.
- Sitemap-medlemskab læst i `page-sitemap.xml`; footer-links talt som `href` i forsidens HTML.

## Resultat

| Sti | Status | Varm responstid | HTML | Titel |
| --- | --- | --- | --- | --- |
| `/` | 200 | 2,46–2,57 s | 215.638 B | Steamgears-forsiden |
| `/shop/` | 200 | 2,39–2,43 s | 199.874 B | Shop |
| `/faq/` | 200 | 3,06 s (kold måling) | 201.852 B | FAQ |
| `/steampunk-costumes/` | 200 | 2,46–2,60 s (kold: 9,41 s) | 210.683 B | Hub |
| `/shipping/` | 200 | 5,24 s (kold måling) | 201.806 B | Shipping — Timelines, Tracking & Split Deliveries |
| `/returns/` | 200 | 3,30 s (kold måling) | 202.583 B | Returns — Start A Return, Refunds & Faulty Items |

Forsiden: **33 `<script>`-tags**, **28 inline `<style>`-blokke**, to analytics-id'er (`G-3MYLFKY8MY` via
`googletagmanager.com/gtag/js` og `GT-PL9L67B4`).

`/shipping/` og `/returns/` har begge rigtig `<h1>`, canonical mod deres egen sti og står i `page-sitemap.xml`.
De er derfor ikke soft-404'er. **Ingen af dem linkes fra forsiden:** forsidens HTML indeholder nul
`href="https://steamgears.eu/shipping/"` og nul `…/returns/`.

`robots.txt` har fortsat **to `User-agent: *`-grupper** (S0.2 er stadig åben).

## Hvad det ændrer

1. **S2.2 blev lukket 6/11 i round 30** (siderne 1396 og 1397 publiceret). Målingen her siger det samme om
   indholdet — rigtig titel, H1, canonical, i sitemappen — men den viser også, at forsiden ikke linker dem: der er
   **nul footer-links**. Det sidste stykke hørte derfor under navigation (S2.3), ikke under en genåbnet S2.2.
   **Rettelse senere samme dag:** linkene blev sat i footeren i round 31 og er målt i
   [`footer-og-navigation-2026-11-06.md`](footer-og-navigation-2026-11-06.md) — 1 forekomst hver pr. side, begge 200.
2. **Payload er den næste strukturelle omkostning, ikke hub-404'erne.** Hver målt side er 200–216 KB HTML, og
   forsiden henter 33 scripts. Det er P2 (nyt S2.7).
3. **Merchant-schema kan udfyldes nu.** `phase-0-baseline-2026-11-03.md` konstaterede, at `shippingDetails` og
   `MerchantReturnPolicy` mangler; med P1 findes siderne der beskriver begge dele. Det er P4 (nyt S4.13).
4. **Analytics er ikke bevist dobbelt.** Der er to id'er, men om begge fyrer `page_view`, kan kun en browser-session
   afgøre. Det er P5 (nyt S0.9) — en måling før en rettelse.

## Hvad målingen ikke beviser

- At checkout eller fragt virker: der er ikke lagt noget i kurven, og der er ikke målt fragtrater.
- At 9,41 s er den normale tid for `/steampunk-costumes/`: gentagelserne lå på 2,46–2,60 s. Den kolde
  forespørgsel er nævnt for at vise spredningen, ikke som en baseline.
- At et analytics-id er overflødigt: to id'er i HTML'en er ikke det samme som to `page_view`-events.
- At `/shipping/` og `/returns/` er fagligt korrekte: kun deres titel, H1, canonical og sitemap-plads er set.
  Indholdet skal læses mod copyen i [`roadmap-status-2026-11-02.md`](roadmap-status-2026-11-02.md) §5.

## Filer

- Roadmap-forslagene: [`../roadmap.md`](../roadmap.md) §9 (P1–P5).
- Den tidligere 404-måling: [`phase-0-baseline-2026-11-03.md`](phase-0-baseline-2026-11-03.md).
- Schema-arbejdet: [`produkt-schema-fix-2026-11-02.md`](produkt-schema-fix-2026-11-02.md).
