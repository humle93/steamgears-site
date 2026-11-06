# Research: næste annoncer + guides (27/10 2026)

Formål: grundig research foran næste indholds- og promobatch + udkast (WP-drafts).

## 1. Live SERP-tjek (27/10, web_search, gl=us)

| Query | Top-resultater lige nu | Konklusion |
|---|---|---|
| steampunk halloween costume men | Pinterest Ideas ×3, vintagedancer "essentials", eBay/Amazon retail, psylofashion generisk guide | **Ingen dedikeret 2026 guide til mænd med Halloween-vinkel + priser.** Hul bekræftet |
| steampunk outfit ideas for men | steampunkstyler-kategori, Pinterest, Reddit-tråd, Amazon (Spirit Halloween), vintagedancer | Kun shops/community — **ingen "complete looks"-guide med rigtige priser** (plan #2 bekræftet) |
| steampunk costume couples | Amazon-butiksside, Facebook-gruppe-post, abracadabra-kategori, Pinterest | **Pinterest/FB dominerer; ingen ordentlig guide** (plan #3 bekræftet) |
| steampunk outfit dress to impress | ⚠️ thespike.gg har NU en dedikeret side + YouTube-video | **Ikke længere 0-konkurrence** (26/10-vurderingen forældet) — nedprioriteres |
| how much does a steampunk outfit cost | Walmart, vintagedancer, scarletdarkness — kun shop-kataloger | **Pris-dokumentet findes stadig ikke i SERP'en** (plan #4 bekræftet) |

## 2. Autocomplete (live 27/10, suggestqueries gl=us)

- "steampunk costume men" → mens costume ideas / outfit men / suit men / **costume male diy** / **costume male spirit halloween**
- "steampunk outfit men" → mens outfit ideas / style men / pants mens / attire men
- "steampunk halloween costume men" → kun selve hovedtermen (termen er ny/voksende — ingen lange varianter endnu = lav konkurrence)

## 3. Deadline-drevet prioritering

Halloween = fredag 31/10. Peak-søgning tirsdag–torsdag → **guide A skal ud i dag/i morgen**. B er den største vedvarende hul-guide (plan #2), C er Pinterest-ammo ( matcher Pin-strategien og couple-trenden).

| Prio | Udkast | Keyword | Hvorfor |
|---|---|---|---|
| A 🔴 | **Steampunk Halloween Costumes for Men: 10 Last-Minute Looks** | steampunk halloween costume men | Tvilling af post 791 (women) — bevist skabelon; hul bekræftet; deadline 31/10 |
| B 🟠 | **Steampunk Outfit Ideas for Men: 12 Complete Looks** | steampunk outfit ideas for men | Plan #2;SERP = kun shops; menns-kluster vokser i autocomplete |
| C 🟡 | **Steampunk Couple Costumes: 10 Pairs (Ready to Buy)** | steampunk costume for couples | Første rigtige par-guide; Pinterest-ammo; cross-sell women+men katalog |

Droppet denne gang: DTI/Roblox (konkurrence kom til), pris-FAQ (#4) — god kandidat til næste batch efter Halloween.

## 4. Katalog-fakta (Woo REST, live 27/10, 86 publicerede produkter)

Priser brugt i udkast (USD, live):
- Frakker: Medieval Assassin Coat $39.95 · Vampire Tailcoat $39.95 · Wine Red Velvet Tailcoat $64.95
- Corsets (hendes-side til par-guiden): $44.95 / $59.95 / $64.95; underbust $59.95
- Hatte m. goggles: $34.95 / $39.95 / $49.95 · Goggles: $24.95–29.95
- Tees $26.95 · Hoodies $49.95 · Posters $21.90
- **NYE produkter 765–767:** Goggle DIY Starter Kit $29.90 · Leather Utility Belt & Holster $24.90 · Corset & Vest PDF Pattern $6.90 · Checklist-PDF gratis (809)

## 5. Format-regler (fra seo-regler-2026-skabelonen)

Kort svar i første 40 ord → pristabel → looks med rigtige priser → sg-buy/sg-shoprow → FAQ ×4-5 + FAQPage JSON-LD → "last updated"-dato → interne links. Tekstfrie billeder. Ved UDGIVELSE: min. 3 indgående links fra eksisterende sider (ny fast regel) + Rank Math via REST updateMeta + GSC-inspektion.

## 6. Billeder (6 stk., genereret 27/10, tekstfrie ✅ QA via image read)

Canvas "Men's + Couples Guide Illustrations" (+ jpg-kopier i denne mappe/images/):
1. mens-halloween-hero.jpg — hero, mand i tailcoat + hat m. goggles
2. mens-halloween-section.jpg — flat lay: holster-bælte, goggles, lommeur
3. mens-outfits-hero.jpg — casual workshop-look
4. mens-outfits-section.jpg — velvet frock coat close-up
5. couples-hero.jpg — par på luftskibsdæk
6. couples-section.jpg — par-hænder m. kompas/lommeur

## 7. ⚠️ Teknisk fund: steamgears-cdn-kilde**mappen er tom**

Kilde-dir /library/Website Design/Steamgears CDN/ indeholder kun posters-print-dpi/ — men de gamle billeder (pin-halloween-01.jpg m.fl.) lever STADIG i publiceret version 88 (HTTP 200 verificeret). **Republish NU ville sandsynligvis slette alle live guide-billeder.** Før næste CDN-udgivelse: download alle live filer tilbage til kilde-mappen (enumerate via live HTML) — derefter republish med de 6 nye. Drafts peger på de nye planlagte CDN-URL'er (/steamgears-cdn/guides-men-couples/…) — de er 404 indtil republish. Alternativ ved publish: swap til eksisterende live-URL'er.

## 8. Annoncer/promo-udkast (organic — betalte ads stadig droppet pr. traffic-plan 21/10)

Fil: promo-kit.md i denne mappe — Pin Pack #5 (10 pins), 3× FB/IG-captions, 1× Shorts-script. CTA-targets = guide-URL'erne (ikke 404-sider).

## 9. Udgivelseslog — Guide A (27/10)

- ✅ Post 891 PUBLISHED 04:17 (morgenrutine) — /steampunk-halloween-costume-men/ live, HTTP 200
- ✅ 12:05: NY SEKTION tilføjet pr. Hennings brief: "Build it in one hour from your own wardrobe" (skjorte, bukser, vest + 3 detaljer: hat m. goggles $34.95, aviator/driver goggles $24.95-29.95, utility belt $24.90 — buy-CTA'er til alle)
- ✅ CDN-billeder live (steamgears-cdn-2/guides-men-couples/, HTTP 200 ×2)
- ✅ 3 indgående links verificeret: /steampunk-costumes/ hub, post 791 (women), /steampunk-costume-ideas/
- ✅ Meta description + canonical satte (live-tjek)
- ✅ GSC-inspektion: "URL is unknown to Google" (normalt for ny URL, samme dag) — inspektionslink i workspace
- ⏳ Sitemap: post-sitemap.xml TTL-cachet, URL endnu ikke med (derf cache refresh)
- ✅ Buffer: Pin 1 (hero) SENDT 27/10 15:11, FB-post SENDT, IG-post "sending" — alle med CTA til guiden
- ⚠️ Buffer-limit: "10/10 scheduled" fejl trods 17 i køen — 2 fjerne promos (16-17/11: "Six complete looks" FB, "Velvet tailcoat" IG) slettet pr. Hennings godkendelse. Pin 2 (flat lay) gemt som Buffer-idea (id 6ae087d94b99da33edf66f9d) → planlæg 30/10 når køen har frigjort plads efter 28/10-opslagene udsendes
- 📌 husk: re-create de 2 slettede promos efter Halloween (tekster i denne fil/Buffer-historik)
