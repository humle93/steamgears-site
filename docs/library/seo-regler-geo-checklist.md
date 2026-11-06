# GEO-checkliste for Steamgears-guides (genbrugelig)

*Destilleret fra geo-optimering-log.md (26/10, goggles + costume ideas). Mål: blive citéret af AI Overviews — citatet er det nye klik under zero-click.*

## 1. Skabelon-trin (pr. guide)

1. **Quick answer-boks øverst** — svar-først, 1-2 sætninger direkte på keywordet.
2. **"In one line"** i hver hovedsektion/metode.
3. **"What we used" / "How We Tested"** — konkret, first-hand: stykliste, pris, tidsforbrug, dato + "Steamgears workshop test". Fejl der fejlede = troværdigheds-markers (fx "limer før maling var forkert").
4. **Byline** med lokation ("styled and photographed in the Steamgears workshop, Aabenraa") + dato.
5. **About-box** — entitetstekst, sameAs (FB/Pinterest/IG/YouTube), link til About-siden og hub-siden.
6. **FAQ udvidet** — minimum 5-6 spørgsmål; dæk keyword-spørgsmål + praktiske detaljer (key pieces, farver, pris).
7. Min. 2 billeder i artiklen (huskerutin: 15/dag fordelt på stakken).

## 2. Skema-tjek

- **Ét HowTo-schema pr. side** — dublet-modstridende skemaer er en reel faldgrube; tjek hvad der lå der i forvejen FØR insert.
- **FAQPage-schema skal matche FAQ-indholdet 1:1** — opdater schema når FAQ udvides, ikke kun teksten.
- **Dublet-tjek efter insert:** mønstre i JSON-LD matchede to gange også visible tekst → tæl forekomster af søge-mønsteret i hele sidens HTML (schema + body) efter hver insert.
- Tjek schema i rendering (API-output), ikke kun i editoren.

## 3. Cache-fælder (W3TC / Simply)

- Front-cache har TTL — **API-diff er sandheden, screenshot kan vise stale version.**
- Verificér altid via REST-API-output (fx `?rest_route=`/wp/v2/pages med rendered content), ikke via browser-screenshot af frontenden.
- Ved tvivl: flush cache og vent på TTL, hvis purge ikke kan trigges.

## 4. WAF- og curl-timing (Simply-hosting)

- **curl-bursts udløser "Checking your browser" (HTTP 454)** → vent 5-10 min, eller brug browser-session i stedet.
- **WP REST via Junos curl-wrapper:** `--data @file` fejler med rest_invalid_json → brug inline: `--data "$(jq -c . payload.json)"`.
- **Pacing:** mellemrum mellem API-kald; ved 429/454 = stop, vent, genoptag — aldrig retry-spam.
- Batch-rækkefølge: lav payload i fil først, verifikér JSON, dernæst ét enkelt POST.

## 5. Post-publish

- Notér dato + guide i geo-optimering-log.md (én linje).
- **GSC-check om 2-3 uger:** impressions på spørgsmåls-keywords = AI-citations-signal.
