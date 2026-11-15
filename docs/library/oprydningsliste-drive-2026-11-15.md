# Oprydnings- og fejlrettelsesliste for steamgears.eu — gældende fra 15/11 2026

**Kilde:** Google Doc *«Steamgears.eu - Oprydnings- og Fejlrettelsesliste (2026-11-15)»*, oprettet 15/11 2026
11:51, ændret 12:01, 8 346 bytes, id `1new93jBcR1kQqmurn2LnKAi7NHqtYflo2SerRTKIRFs`, mappe `1aUhfZ1i…`
(samme mappe som Pirat Piger-masterne og backup-README'en).
**Hentet ind:** 15/11 2026, eksporteret som `text/plain` og gennemlæst i sin helhed.
**Status i repoet:** dette er den liste arbejdet følger fra 15/11. Punkterne er gengivet med listens eget
indhold; hvor den støder mod noget der er **målt** i dette repo, står det i §8 — en påstand i listen er ikke
en måling.

| Mærke | Betydning |
| --- | --- |
| ÅBEN | Åben — ikke udført |
| MÅL | Skal måles før den udføres (listen og repoets målinger er uenige) |
| SITE | Kan kun udføres på det levende site over MCP/wp-admin, ikke i dette repo |
| EJER | Kræver ejerens beslutning, ikke en rettelse |

---

## 1. Frontend & tekstfejl (høj prioritet — synligt for brugere)

| # | Punkt | Fejl ifølge listen | Handling | Status |
| --- | --- | --- | --- | --- |
| 1.1 | Stavefejl i cookie-banner (Complianz) | »we te technologies« / »we the technologies«, »process data soch as beowsing behavior« | Erstat med korrekt engelsk standardtekst i cookie-pluginnets indstillinger | SITE ÅBEN |
| 1.2 | Footer-copyright | »© 2026 Steamgears-Steampunk cosplay guides & gear-stangers.« — eller klippet midt i sætningen på mobil | Ret i Theme Customizer / footer-widget til »© 2026 Steamgears — Steampunk cosplay guides & gear.« | SITE ÅBEN |
| 1.3 | Slåfejl i »Build It Yourself«-kortet på forsiden | »from thrin-store finds and brass paint« | `thrin-store` → `thrift-store` | SITE ÅBEN |
| 1.4 | Sprog på »Posters & Wall Art«-sektionen | Den danske arbejdstitel »The Pirat Piger series is live« står midt i den engelske forsidetekst | Erstat »Pirat Piger« med engelsk serienavn (f.eks. »Sky Corsairs« eller »Pirate Maidens«) | EJER MÅL |

Den tekst listen foreslår til cookie-banneret:

> To provide the best experiences, we use technologies like cookies to store and/or access device information.
> Consenting to these technologies will allow us to process data such as browsing behavior or unique IDs on
> this site. Not consenting or withdrawing consent may adversely affect certain features and functions.

## 2. WooCommerce & katalog-synkronisering

| # | Punkt | Fejl ifølge listen | Handling | Status |
| --- | --- | --- | --- | --- |
| 2.1 | Produktantal stemmer ikke | WP/Woo REST-backup viser **74** produkter, shop-siden viser »Showing 1–16 of **83** results« | 1) Tjek for 9 »spøgelsesprodukter«, kladder eller slettede varer i `wp_wc_product_meta_lookup`. 2) WooCommerce → Status → Tools → *Regenerate Product Lookup Tables*, og ryd objekt-/sidecache | MÅL SITE |

## 3. Mediefiler & billedformat-mismatch (serie v5)

| # | Punkt | Fejl ifølge listen | Handling | Status |
| --- | --- | --- | --- | --- |
| 3.1 | v5-mockups er PNG med `.jpg`-endelse | `v5-01-mech-fox-mock.jpg` … `v5-10-planisphere-mock.jpg` er reelt ukomprimerede `image/png` på 1,3–1,8 MB stykket | Konvertér til ægte JPG eller WebP, mål under 250 KB pr. fil, så MIME-type og endelse matcher og Smart Optimizer ikke fejler | SITE ÅBEN |
| 3.2 | WebP/AVIF + lazy loading på guide-billeder | Tunge trin-for-trin-billeder i DIY-guiderne sænker LCP/CLS på mobil | Alle guide-billeder i WebP med `loading="lazy"`, undtagen hero over folden | SITE ÅBEN |

## 4. Teknisk SEO, schema & tracking

| # | Punkt | Fejl ifølge listen | Handling | Status |
| --- | --- | --- | --- | --- |
| 4.1 | Dobbelt GA4-tracking | Konflikt mellem to properties, **G-3MYLFKY8MY** og **G-GE85RJJKD1**, som kan splitte e-commerce-data | Bekræft hvilken der er primær, fjern det sekundære tag fra header/GTM, verificér at Stripe live sender `purchase` til den rigtige property | MÅL |
| 4.2 | Manglende JSON-LD | DIY-guider mangler fuld HowTo-schema; produktsider mangler komplet Product/Offer (pris, lagerstatus, valuta) | Aktivér og validér HowTo på alle guides og Product på alle produkter via Rank Math / Smart Optimizer | MÅL SITE |
| 4.3 | Interne links fra guides til shop | Risiko for isolerede indholdssiloer: trafik på DIY-guider ledes ikke videre til relevante kategorier | Faste produkt-callouts og stærke ankerlinks i hver guide til de matchende kollektioner (Goggles, Corsets, T-shirts, Posters) | SITE ÅBEN |

## 5. Sikkerhed & database-oprydning (post-Juno)

| # | Punkt | Fejl ifølge listen | Handling | Status |
| --- | --- | --- | --- | --- |
| 5.1 | Rester efter Juno-webhooks og plugin | Efterladte `_webhook_*`-postmeta, gamle `.juno.page`/Azure-endpoints eller rester fra »Site Assistant« kan udløse sikkerhedsadvarsler i Telegram | 1) WooCommerce → Settings → Advanced → Webhooks: slet alt der ikke peger på Command Center (Ultra.cc). 2) Rens `wp_postmeta` for forældede `_webhook_*`-nøgler og eksterne script-tags i post-content | SITE ÅBEN |

## 6. Posters-only-konsolidering (50 plakater, 6 kategorier)

| # | Regel | Indhold | Status |
| --- | --- | --- | --- |
| 6.1 | Alt tøj og gear på kladde | Shoppen viser ifølge listen 83 produkter, herunder 57 T-shirts & hoodies samt »Atelier-placeholders (ID 940, 941, 942)«, selv om der kun køres plakater. Sæt T-shirts, hoodies, goggles, corsets og Atelier-smykker på **draft** (slet ikke), så de kan genaktiveres. Kun *Posters & Wall Art* skal stå som publish | MÅL SITE |
| 6.2 | Loft på præcis 50 unikke posters | 30×40 og 50×70 cm; ca. 60 % kvindelige modeller/karakterer og 40 % mekaniske dyr, botanik, luftskibe og blueprints. Fjern motiv-dubletter mellem v4 og v5 — vælg den stærkeste, f.eks. mellem `v4-03-fox` og `v5-01-mech-fox`, og mellem `v4-05-balloon` og `v5-06-poster-balloon` | EJER |
| 6.3 | 37 kategorier → 6 | Tynde kategorisider straffes. Skjul/slet tøj- og udstyrskategorier og lav 301-redirects i Rank Math til hovedkategorien. Behold: 1) All Posters & Wall Art, 2) Sky Corsairs & Airships, 3) Clockwork Creatures, 4) Botanical & Apothecary Prints, 5) Blueprints & Workshop Art, 6) Art Nouveau & Portraits | MÅL SITE |

Fordelingen listen foreslår af de 50: Sky Corsairs & Airships 12–15 · Clockwork Creatures 10–12 ·
Botanical & Apothecary 8–10 · Blueprints & Workshop 8–10 · Art Nouveau & Diesel Deco 5–8.

## 7. Supplerende UI-, mobil- og systemfund (dybde-audit)

| # | Punkt | Fejl ifølge listen | Handling | Status |
| --- | --- | --- | --- | --- |
| 7.1 | »Choose Your Path« på forsiden | Forsiden promoverer stadig »SHOP CORSETS & CLOTHING« og »57 designs across T-shirts & hoodies« | Ryk »Decorate Your Workshop (Posters & Wall Art)« op som primær sektion, og lad kortene linke til plakatkollektionerne og DIY-guiderne | SITE ÅBEN |
| 7.2 | Afskåret logo/header på mobil | På mobil-screenshots klippes logoet, så der står `STEAMGEA`, `STEAMGEARE` eller et løst `Å` | Justér CSS-padding og font-size i `steamgears-clockworkpunk-v2.css`, så STEAMGEARS ikke bryder på 360–412 px | SITE ÅBEN |
| 7.3 | Cookie-banner over hero på desktop | »Manage Consent«-boksen dækker hero-overskriften (»Forge Your…«) | Placér banneret fast i bunden (bottom bar eller floating bottom-right) | SITE ÅBEN |
| 7.4 | Pris-typografi på mobil | Dollartegnet i »$26.95« ligner visuelt et 8-tal (»826.95«) | Tjek og ret typografien i prisfeltet på mobil | SITE ÅBEN |
| 7.5 | SQLite-backup på Ultra.cc | Systemdokumentationen refererer `steamgears.db`, mens Command Center v8.x bruger `command_center.db` | Bekræft at cronjobbet tager backup af den **aktive** `.db`-fil | SITE ÅBEN |

---

## 8. Forbehold: hvor listen og de målte fakta ikke er enige

Fire punkter må ikke udføres, før de er målt igen. De er valgt ud, fordi repoet allerede har en dateret måling
der siger noget andet — og fordi tre af dem ville ændre noget, der er **verificeret færdigt**.

1. **GA4-konflikten (4.1) findes ikke i vores egne målinger.** Målt 7/11 (S0.9) fyrede der **ét** `g/collect`
   med `en=page_view` på `/`, `/?nocache=1` og `/shop/` — alle på **G-0HC9BLQ88R**, sat gennem Rank Maths
   option `rank_math_google_analytic_options`. Begge id'er i listen giver **0 træf** i hele repoet. Enten er
   taggene skiftet efter 7/11, eller listen er målt på noget andet end det levende site. Et tag må ikke fjernes
   på en påstand alene: mål `g/collect` igen først.
2. **Produktantallet (2.1, 6.1) stemmer ikke.** Målt 8/11: **24** produkter i DKK — 20 publicerede og 4
   kladder — efter at **46 produktkladder** blev ryddet i S1.4. Listen taler om 83 i frontend og 57 T-shirts &
   hoodies. Den kan være lavet på en ældre backup eller tælle alle statusser med; »9 spøgelsesprodukter« skal
   derfor **verificeres**, ikke slettes.
3. **ID 940/941 er ikke placeholders.** Listen vil have dem sat på kladde sammen med 942. Men 940/941 blev
   bevidst **publiceret og målt** 8/11 i S1.2; kun 942/765/766/767 er kladder med pris og SKU. At følge
   punkt 6.1 ville rulle et færdigt, verificeret punkt tilbage.
4. **»Pirat Piger« er ikke kun en dansk arbejdstitel (1.4).** Navnet bærer en hel EN-landingsside
   ([`posters-seo/steampunk-pirate-posters.html`](../../posters-seo/steampunk-pirate-posters.html)),
   Pinterest-boardplanen og flere dokumenter i dette bibliotek. Ændres forsideteksten alene, får serien to
   navne. Enten omdøbes hele serien — side, pins og produkttitler — eller navnet beholdes. Det er ejerens valg,
   ikke en rettelse.

## 9. Hvad der kan udføres herfra, og hvad der ikke kan

- **SITE-punkterne kan ikke udføres i dette repo.** Repoet er dokumentation og assets for steamgears.eu;
  WordPress-kernen ligger hos Simply, og ændringer på selve sitet sker over MCP (Novamira), jf. `AGENTS.md` §5.
  Den adgang findes ikke i den session der skrev dette dokument.
- **3.1 (v5-mockups) og 7.2 (`steamgears-clockworkpunk-v2.css`) har ingen filer i repoet** — søgning på
  `**/v5-*mock*` og `**/*clockworkpunk*` giver 0 træf. Filerne ligger på sitet/Drive, ikke her.
- **Det der kan gøres her:** målinger (`tools/`), dokumentation, og de rettelser der ligger i repoets egne
  filer — f.eks. `posters-seo/`, `deploy/` og `mu-plugins/`. Intet af listen kræver i dag en ændring i de
  filer.

## 10. Næste skridt

1. Mål 4.1, 2.1 og 6.1 på det levende site, og skriv de målte tal ind her — så listen og
   [`../projektstatus.md`](../projektstatus.md) taler om de samme produkter og det samme tag.
2. Ejerens beslutninger: 1.4 (serienavn), 6.2 (hvilken version af overlappende motiver der beholdes) og
   6.1 (om tøj/gear skal kunne genaktiveres senere).
3. Derefter de rene tekst- og CSS-rettelser (1.1–1.3, 7.2–7.4) over MCP, og 3.1 som en filkonvertering med
   mål under 250 KB pr. fil.

Ingen projektprocent er flyttet af dette dokument: listen er læst og indlæst, der er ikke bygget eller målt
noget nyt. Status pr. 15/11 står uændret i [`../projektstatus.md`](../projektstatus.md).
