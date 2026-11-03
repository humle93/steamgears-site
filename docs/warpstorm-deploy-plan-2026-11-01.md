# Warpstorm — deploy-plan: hvordan hele sitet sættes op via API, plugin for plugin

Plan for at gøre warpstorm.online **maskinelt deployerbar**: plugins, sider, butik og social — så meget som muligt uden at nogen klikker i wp-admin.

Skrevet 1/11 2026. Bygger på [warpstorm-2026-11-01.md](library/warpstorm-2026-11-01.md) (niche og butiksmodel) og [butiksaudit-2026-11-01.md](library/butiksaudit-2026-11-01.md) (hvad der ellers mangler før et salg).

---

## 0. Kort svar

**Sitet kan deployes automatisk — men kun hvis man accepterer, at der er _to_ API'er, ikke ét.**

- **Plugins og sider** går over WordPress' egen REST: `POST /wp-json/wp/v2/plugins`. Det er kernefunktionalitet siden WP 5.5, kræver kun en administrator og en applikationsadgangskode, og virker i dag på warpstorm (WP 7.1.3).
- **Menuer, indstillinger og WooCommerce-produkter** går over Royal MCP (`/wp-json/royal-mcp/v1/mcp`, JSON-RPC), fordi REST ikke har nogen menu-endpoint.
- **Royal MCP kan _ikke_ installere plugins.** Det står i deres egen scope-beskrivelse: *«No plugin installs or upgrades. It can list plugins, not install, activate or update them.»* Derfor er plugin-trinnet nødt til at gå over REST.

**Tre ting kan ikke automatiseres, uanset hvor meget API man har:** Complianz-wizarden, betalingsaftalen med indløser og produktfotografiet. Det er dem, der sætter kalendertiden, ikke koden.

> **warpstorm.online er et testdeploy.** Alt flyttes senere til det rigtige domæne. Derfor er intet i dette dokument bundet til domænet: siderne bygges med relative links, runneren læser `WP_SITE` fra miljøet, og manifestets `site`-felt er kun dokumentation (runneren advarer, hvis de to er forskellige). Alt domænebundet arbejde — SEO-canonicals, Search Console, e-mail-afsendelse, GPSR-adresse — er **udskudt til efter flytningen**, se §11.

| | Estimat |
|---|---|
| **Ren opsætning, når adgang er på plads** | **1,5–2 arbejdsdage** (heraf 25 min. automatiseret) |
| **Plus fysisk arbejde** (terræn printes, fotograferes, 8 SKU) | **+3–5 dage** |
| **Plus ventetid** (MobilePay-indløser, evt. Meta/Pinterest-review) | **2–10 dage** |
| **Til første ordre kan tages** | **ca. 8–12 dage**, hvoraf 2 er arbejdsdage |

---

## 1. De fire lag, og hvad der rører hvad

| Lag | Hvad | API | Kan automatiseres |
|---|---|---|---|
| 1. Host | PHP 8.x, MySQL, cron, backup | Ingen (Simplys kontrolpanel) | **Nej** — skal bekræftes manuelt én gang |
| 2. WordPress-kerne | WP 7.1.3, tema Extendable, app-password | REST `/wp/v2/*` | Ja |
| 3. Plugins | Woo, compliance, betaling, SEO, social | REST `/wp/v2/plugins` | **Ja** (kun wordpress.org-slugs) |
| 4. Indhold og butik | Sider, produkter, menuer, indstillinger | REST (sider) + MCP (resten) | Ja, med forbehold |
| 5. Social | Autopilot + Publer | MCP-abilities `steamgears-autopilot/*` + Publer MCP | Ja, når nøgler findes |

**Rækkefølgen er ikke valgfri:** lag 3 skal være på plads, før lag 4 kan konfigureres (Woo-indstillinger findes ikke før Woo er aktivt), og lag 5 skal først aktiveres, når der er noget at poste om.

---

## 2. Hvad API'et faktisk kan — og hvad det koster i sikkerhed

### 2.1 Plugin-installation via REST (verificeret)

`POST /wp-json/wp/v2/plugins` med `{"slug": "woocommerce", "status": "active"}`. Endpointet slår selv slug'en op hos wordpress.org via `plugins_api()`, kører `Plugin_Upgrader` og aktiverer bagefter. Kræver `install_plugins` + `activate_plugins` — dvs. almindelig administrator.

Begrænsninger, som planen skal tage højde for:

- **Kun wordpress.org.** Premium-zips og vores egen Autopilot-zip kan ikke installeres ad denne vej. Det er derfor, Autopilot er et manuelt trin i manifestet.
- **Ingen rollback.** Aktiverer man et plugin, der fataler, går sitet ned. Derfor aktiveres ét ad gangen med et helbredstjek imellem — ikke 13 i én blæst.
- **Skrivbart `wp-content/plugins` og `FS_METHOD=direct`** skal være på plads. Hos Simply er det normalt tilfældet; gør det ikke, returnerer kaldet `unable_to_connect_to_filesystem`.

### 2.2 Applikationsadgangskoder (verificeret)

En app-password logger ind **som brugeren, med brugerens fulde rettigheder**. Der er ingen scoping: en app-password på en administrator er reelt root på sitet — inklusive `POST /wp/v2/plugins`, brugeroprettelse og alle custom routes.

Den kan til gengæld **ikke** bruges til at logge ind i wp-admin. Den er kun til API.

**Sikkerhedsregler, som planen følger:**

1. **Dedikeret bruger**, ikke ejerens konto: `deploy-bot` med administrator i provisioning-vinduet, derefter nedgraderet til `shop_manager`.
2. **Egen app-password per integration.** Aldrig genbrug.
3. **Kun over HTTPS** (Basic auth er trivielt at aflæse i klartekst).
4. **Tilbagekald efter provisioning.** App-password slettes, når fasen er lukket. Plugins opdateres bagefter i wp-admin.
5. **Alt, der kan installere plugins, er RCE-ækvivalent.** Derfor: provisioning køres én gang, fra vores side, med en kode, der kan læses igennem — og agentens adgang nedgraderes bagefter.

### 2.3 Royal MCP (verificeret)

Gratis plugin på wordpress.org (`royal-mcp`), ~113 kerneværktøjer plus integrationsværktøjer, der først indlæses når det matchende plugin er aktivt (WooCommerce: 29, Rank Math: 5, Complianz, Redirection, ACF …). Auth: OAuth 2.1 med PKCE **eller** API-nøgle som `Authorization: Bearer <key>`. Endpoint: `/wp-json/royal-mcp/v1/mcp`, Streamable HTTP.

Relevant for os: Woo-værktøjerne dukker først op, når Woo er aktivt — manifestets `wc_*`-trin kan derfor ikke verificeres før fase 3.

**Vigtigt for forventningerne:** værktøjsnavnene er ikke stabile på tværs af versioner og integrationer. Derfor har runneren en `tools`-kommando, og manifestets MCP-trin er markeret `optional` — mangler værktøjet, springes trinnet over i stedet for at fejle.

---

## 3. Det skal du give mig (og intet af det kan jeg skaffe selv)

| # | Hvad | Hvor | Tid |
|---|---|---|---|
| 1 | **App-password** på en dedikeret admin-bruger | Brugere → ny bruger `deploy-bot` (admin) → Applikationsadgangskoder | 5 min |
| 2 | **WP_SITE** (allerede kendt: `https://warpstorm.online`) | — | 0 |
| 3 | **Publer-workspace** til warpstorm + de konti, der skal bruges | Publer → Social Accounts | 20 min |
| 4 | **AI-nøgle** til Autopilot (Claude/OpenAI) | Settings → Autopilot | 5 min |
| 5 | **Stripe-konto** (findes?) + **MobilePay-indløser** | QuickPay/Reepay/Stripe DK | 1 t + 2–5 dages godkendelse |
| 6 | **CVR, firmanavn, adresse, telefon, domæne-mail** | — | 10 min (men blokerer alt offentligt) |
| 7 | **Bekræftelse på 3D-printer** + materiale | — | 0 |
| 8 | **Adgang til at uploade én zip** (Autopilot) | Plugins → Upload | 5 min |

Uden 1, 3 og 6 kan jeg køre alt det andet, men intet må gå live.

---

## 4. Plugin-matrix

Rækkefølgen er den, manifestet kører i — sikre og uafhængige først.

| # | Plugin | Slug | Hvorfor | Kan MCP'en styre den? | Konfiguration der stadig er manuel |
|---|---|---|---|---|---|
| 1 | WooCommerce | `woocommerce` | Butikken findes ikke uden | Ja (29 værktøjer) | Land, valuta, fragtzoner |
| 2 | Complianz | `complianz-gdpr` | EU opt-in; nuværende installation på søstersitet kører US opt-out | Delvist | **Hele wizarden** — 1,5–2 t |
| 3 | EU Withdrawal Compliance | `eu-withdrawal-compliance` | To-trins fortrydelse med bekræftelse på varigt medium; lovpligtigt for EU-forbrugere fra 19/6 2026 | Nej | Knap-tekst på dansk, modelformular |
| 4 | Lodestone Product Compliance (GPSR) | `lodestone-product-compliance` | Art. 19-felter på hver produkt­side; auto-afgør om EU-ansvarlig kræves | Nej | Operatørposter + bulk-tildeling |
| 5 | Stripe | `woocommerce-gateway-stripe` | Kort, Apple/Google Pay | Ja | API-nøgler, webhook |
| 6 | Vipps MobilePay | `woo-vipps` | Dansk betalingsvane; **14-dages capture-vindue i DK** | Ja | Merchant-aftale |
| 7 | Redirection | `redirection` | `/shop/` svarer 404 i dag; også 301 for prototype-links | Ja (8 værktøjer) | Ingen |
| 8 | WP Mail SMTP | `wp-mail-smtp` | Ordre- og fortrydelsesmails skal faktisk frem | Delvist | SMTP-udbyder |
| 9 | Imagify | `imagify` | Terrænfotos bliver tunge | Nej | API-nøgle |
| 10 | Rank Math | `seo-by-rank-math` | Kører allerede | Ja (5 værktøjer) | Sitemap er OK |
| 11 | W3 Total Cache | `w3-total-cache` | Kører allerede; **skal undtage kurv/checkout** | Ja | Cache-undtagelser |
| 12 | Royal MCP | `royal-mcp` | Kører allerede — det er vores kontrolflade | — | Ingen |
| 13 | **Steamgears Autopilot** | egen zip | Social- og indholdsmotoren | Ja, via abilities | **Manuel upload** — ikke på wordpress.org |
| — | Pris­historik (Omnibus) | `woocommerce` officiel udvidelse (betalt) | Kræves hvis der køres udsalg | Nej | Køb + indstilling |
| — | Anmeldelser | **ikke besluttet** | Judge.me lukkede WooCommerce 31/10 2025; Loox er Shopify-only | — | Vælg Woo-native plugin — se §9 |

**Bemærk to fælder i tabellen:** Judge.me er ikke en mulighed længere (sunset 31/10 2025, data slettes), og Woo's egen «IOSS»-udvidelse er til **ikke-EU-sælgere** — en dansk butik skal bruge OSS, ikke IOSS.

---

## 5. Faser, tid og verifikation

Alle tider er for én person, der kender systemet. «Kode» betyder automatiseret; «hånd» betyder klik i wp-admin.

### P0 — Adgang og sikkerhed · 40 min · blokerer alt

| Trin | Tid |
|---|---|
| Opret `deploy-bot` (admin), generér app-password, sæt `WP_SITE`/`WP_USER`/`WP_APP_PASSWORD` som miljøvariabler | 15 min |
| `python3 tools/deploy/deploy.py plugins` — bekræft at læseadgang virker | 5 min |
| `python3 tools/deploy/deploy.py tools` — bekræft hvilke MCP-værktøjer der faktisk findes | 5 min |
| Tag backup (UpdraftPlus eller hostens snapshot) **før** første skrivning | 15 min |

**Gate:** plugin-listen kan læses, og der findes en backup. Ellers stop.

### P1 — Plugins · 25 min kode + 60 min kontrol

Manifestet aktiverer ét plugin ad gangen. Efter hver aktivering kaldes `GET /wp-json/wp/v2/plugins` igen, så en fatal fanges med det samme i stedet for ved næste sidevisning.

| Trin | Tid |
|---|---|
| `deploy.py run --only plugin-woocommerce --apply` — derefter indlæs forsiden | 10 min |
| Resten af plugin-trinnene, grupperet 3 ad gangen med sideindlæsning imellem | 15 min |
| Verificér Woo-dashboard, Rank Math-sitemap, at `/shop/` stadig 404'er (endnu) | 20 min |
| Autopilot-zip uploades manuelt og aktiveres | 10 min |

**Gate:** forsiden svarer 200, wp-admin kan åbnes, WooCommerce → Status viser grøn på database og server.

### P2 — Compliance · 5 timer · **kan ikke automatiseres**

Det er her, de fleste butikker fejler, og det er ren håndarbejde.

| Trin | Tid | Note |
|---|---|---|
| Complianz-wizard: region EU, consent-type opt-in, granulære kategorier, «afvis» lige så synlig som «accepter» | 1,5–2 t | Kræver cooki-scan bagefter |
| Fortrydelsesplugin: dansk knap-tekst, modelformular, bekræftelsesmail med tidsstempel | 1 t | Test med en rigtig ordre |
| GPSR-operatørposter ind og bulk-tildeles kataloget | 1 t | Blokeret af CVR + adresse |
| Juridisk side: firmanavn, CVR, adresse, telefon, domæne-mail | 30 min | Blokeret af samme |

**Gate:** en testordre kan fortrydes fra My Account, og bekræftelsesmailen lander med dato og klokkeslæt.

### P3 — Butik og sider · 30 min kode + 4–6 timer indhold

| Trin | Tid |
|---|---|
| `python3 tools/deploy/build_payloads.py` (bygger siderne fra prototypen) | 1 min |
| `deploy.py run --only page-shop page-sector-ruins --apply` — opretter dem som **kladder** | 5 min |
| Gennemlæs i browser, ret layout, publicér | 1–2 t |
| 8 SKU med titel, pris, GPSR-felter, fragtklasse — **data ligger klar i `deploy/warpstorm/catalogue.json`**, genereres til manifestet med `build_catalogue.py` | 1–2 t (gennemlæsning, ikke indtastning) |
| Fragtzoner DK/EU og fri fragt over 60 € | 30 min |

**Gate:** produkt kan lægges i kurv, checkout viser moms og fragt korrekt, og GPSR-blokken står på produktsiden.

### P4 — Betaling · 1 time kode + 2–10 dage ventetid

| Trin | Tid |
|---|---|
| Stripe-forbindelse og testbetaling | 45 min |
| MobilePay: aftale med indløser, test i sandbox | 15 min + godkendelse |
| **Capture inden 14 dage** — beslut hvem der holder øje | 5 min |

**Gate:** en rigtig 1-krone-ordre med hver gateway, refunderet bagefter.

### P5 — Social (Autopilot) · 4 timer

| Trin | Tid | Note |
|---|---|---|
| Autopilot: AI-nøgle, kanaler, sprog, stille timer, dagligt loft | 45 min | Engelsk indhold → engelske captions |
| Publer-workspace + konti forbindes | 30 min | Facebook, Instagram, Pinterest, Bluesky først |
| RSS-feeds oprettes i Publer (én pr. kanal) med auto-post | 45 min | Feed-URL'er listes i Autopilot-dashboardet |
| Første 10–20 opslag køres i **draft-tilstand** og godkendes manuelt | 1,5 t | Lær systemet at kende, før det kører frit |
| `steamgears-autopilot/status` via MCP — bekræft at køen ikke er tom | 10 min | |

**Gate:** ét opslag er publiseret automatisk, og `/go/<id>/`-linket tæller klik.

**Vigtigt om Autopilot:** pluginnet er bygget til steamgears.eu — navn, beskrivelse og feed-URL'er er brandede. Det virker på warpstorm, fordi feeds bygges ud fra `home_url()`, men navnet står i wp-admin som «Steamgears Autopilot». Enten accepteres det, eller der laves en site-agnostisk variant (**0,5 dag**) — se beslutning 3 i §9.

### P6 — Slutkontrol · 1,5 time

| Trin | Tid |
|---|---|
| Fuld ordre fra en ikke-admin konto: kurv → betaling → mail → fortrydelse | 45 min |
| Sitemap, robots, 404 på `/shop/` væk, 301 fra prototype-links | 20 min |
| Tilbagekald app-password, nedgradér `deploy-bot` til `shop_manager` | 10 min |
| Skriv i [warpstorm-2026-11-01.md](library/warpstorm-2026-11-01.md) hvad der faktisk blev gjort | 15 min |

---

## 6. Samlet tidsestimat

| Fase | Kode | Hånd | Kalender |
|---|---|---|---|
| P0 Adgang | 5 min | 35 min | samme dag |
| P1 Plugins | 25 min | 1 t | samme dag |
| P2 Compliance | 0 | 5 t | 1–2 dage |
| P3 Butik | 30 min | 4–6 t | 1–2 dage |
| P4 Betaling | 15 min | 1 t | **+2–10 dage ventetid** |
| P5 Social | 15 min | 3,5 t | 1–2 dage |
| P6 Slutkontrol | 0 | 1,5 t | samme dag |
| **Sum** | **~1,5 t** | **~17 t ≈ 2,2 arbejdsdage** | **8–12 dage til første ordre** |

Fysisk arbejde kommer oveni og er ikke medregnet: terræn skal printes (9 timer pr. plade), fotograferes og beskrives — **3–5 dage**, og det er den reelle flaskehals, ikke teknikken.

**Kritisk vej:** CVR/adresse → GPSR og juridisk side → offentliggørelse. Betalingsgodkendelse løber parallelt.

**Kapacitetsgrænse, som ikke står i nogen tidsplan ovenfor:** en af hver af de 8 SKU'er er **52 printtimer**. En enkelt printer, der kører godt, klarer ca. 19 timer i døgnet — så et fuldt lager er tre døgns uafbrudt print, *før* der kan fotograferes. **Start med fire SKU'er, ikke otte.** Detaljeret printkø, slicer-indstillinger, materialeforbrug og optagelsesliste står i [warpstorm-print-og-foto-2026-11-02.md](warpstorm-print-og-foto-2026-11-02.md).

---

## 7. Det jeg har bygget (kører nu, uden adgangskoder)

| Fil | Hvad den gør |
|---|---|
| [`tools/deploy/deploy.py`](../tools/deploy/deploy.py) | Runner til begge API'er. `tools`, `plugins`, `run [--apply] [--only] [--offline]`. Idempotent: plugins sammenlignes på status, sider matches på slug. Dry run som standard. |
| [`tools/deploy/build_payloads.py`](../tools/deploy/build_payloads.py) | Bygger WordPress-sider ud fra prototypen: trækker `<main>` ud, inliner CSS, omskriver links, wrapper i `wp:html`. |
| [`deploy/warpstorm/manifest.json`](../deploy/warpstorm/manifest.json) | 22 ordnede trin: 12 plugins, 5 sider, 3 MCP-kald, 1 manuelt. |
| [`deploy/warpstorm/payloads/`](../deploy/warpstorm/payloads/) | De to genererede sider (27 KB hver), kladder. |

Verificeret uden netværk: `python3 tools/deploy/deploy.py run --offline` → **20 ok, 1 skip, 0 fejl**. Det er planen, der kører — ikke løfter.

Sådan tages den i brug:

```bash
export WP_SITE=https://warpstorm.online
export WP_USER=deploy-bot
export WP_APP_PASSWORD='xxxx xxxx xxxx xxxx xxxx xxxx'

python3 tools/deploy/deploy.py plugins        # læseadgang
python3 tools/deploy/deploy.py tools          # hvilke MCP-værktøjer findes
python3 tools/deploy/deploy.py run --offline  # plan
python3 tools/deploy/deploy.py run --apply --only plugin-woocommerce
```

---

## 8. Risici og tilbagerulning

| Risiko | Sandsynlighed | Håndtering |
|---|---|---|
| Et plugin fataler ved aktivering og tager sitet ned | Lav–middel | Én ad gangen; backup i P0; adgang til filsystem via host, hvis wp-admin dør |
| MCP-værktøjsnavne passer ikke med manifestet | **Høj** | Alle MCP-trin er `optional`; `deploy.py tools` er sandheden, ikke manifestet |
| `POST /wp/v2/plugins` afvises af hosten | Lav | Falder tilbage til manuel upload af de 12 zips (30 min) |
| Autopilot poster til den forkerte kanal | Middel | Første 20 opslag i draft-tilstand; Publer-konti tjekkes med `check-publer` |
| App-password lækker | Lav | Kun i miljøvariabler, aldrig i filer; tilbagekaldes i P6 |
| Complianz står stadig i US-tilstand | **Høj hvis P2 springes over** | Gate i P2; ellers er butikken ulovlig fra første besøg |
| Der sælges GW-lignende 3D-print | Middel | Egen designlinje; ingen scanninger, ingen «replacement for …»-navne |

---

## 9. Beslutninger der mangler dig

1. **Anmeldelser** — Judge.me er ude (sunset), Loox er Shopify-only. Skal vi bruge Woo's indbyggede anmeldelser + et gratis Woo-native plugin, eller betale for noget med Omnibus-prishistorik indbygget?
2. **MobilePay-indløser** — QuickPay (dansk, Dankort, support på dansk), Reepay/Frisbii (har en dårlig support-anmeldelse i 2024) eller Stripe (nemmest, men MobilePay bliver en del af Stripe-aftalen)?
3. **Autopilot på warpstorm** — accepter «Steamgears»-navnet i wp-admin, eller brug 0,5 dag på en site-agnostisk variant?
4. **Publiceringsmodel for de to sider** — bliver de kladder, til fotografierne findes (anbefalet), eller skal de live som «kommer snart»?
5. **CVR og adresse** — uden dem kan hverken GPSR eller den juridiske side fuldføres, og intet må gå live.

---

## 11. Flyt til nyt domæne

Testdeployet kastes væk, når sitet flyttes. Det meste følger med automatisk; det her gør ikke, og rækkefølgen betyder noget.

**Følger med af sig selv:** indhold, sider, produkter, plugin-konfiguration, tema, medier, WooCommerce-ordrer (hvis nogen), Autopilot-indstillinger.

**Skal sættes op igen efter flytningen — i denne rækkefølge:**

| # | Hvad | Hvorfor det ikke følger med | Tid |
|---|---|---|---|
| 1 | **WordPress-adresse og site-adresse** (`wp_options`: `siteurl`, `home`) | Databasen har stadig den gamle URL; ellers redirecter alt | 10 min |
| 2 | **Search-replace på URL'er** i indhold og widgets (WP-CLI `search-replace`, eller Better Search Replace) | Gamle absolutte links i indlæg og menuer | 20 min |
| 3 | **Publikums-/Publer-feeds** | Feed-URL'er bygges af `home_url()` — Publer holder den gamle | 30 min |
| 4 | **App-password på ny** | Det gamle hører til den gamle bruger/site | 5 min |
| 5 | **OAuth-callbacks** (Royal MCP, Stripe, Meta/Pinterest hvis direkte) | Hviderede callbacks er domænebundne | 20–40 min |
| 6 | **Stripe-webhook** | Peger på den gamle URL og fejler stille | 10 min |
| 7 | **Rank Math: sitemap, canonical, Search Console** | Ny property, nyt sitemap, gammel sitemap dør | 30 min |
| 8 | **SMTP-afsenderdomæne** (SPF/DKIM) | Mails fra det nye domæne bliver spam uden | 30 min + DNS-propagering |
| 9 | **GPSR-operatørblok og juridiske sider** | Adresse og domæne-mail står bogstaveligt i teksten | 20 min |
| 10 | **Cache og 301 fra testdomænet** | Ellers bliver delte links hængende på et domæne, der snart lukker | 15 min |

**Samlet: ca. 3–4 timer**, plus DNS-propagering. Læg det som et selvstændigt sprint efter flytningen — og **før** der annonceres eller postes noget, der linker til det nye domæne.

**Det her skal _ikke_ gøres nu:** canonicals, Search Console, e-mail-verificering, betalingswebhooks, GPSR-blok. Det er spildt arbejde på et domæne, der alligevel forsvinder.

## 12. Ændringslog

| Dato | Ændring |
|---|---|
| 2026-11-01 | Tilføjet §11 om flytning til nyt domæne. Domænet er et testdeploy, så SEO-canonicals, Search Console, mail og GPSR-adresse er udskudt; resten er gjort domæne-uafhængigt. |
| 2026-11-01 | Første version. Verificeret: `POST /wp/v2/plugins` som provisioneringsvej, Royal MCP's manglende plugin-installation, app-password-konsekvenser, plugin-matrix inkl. 2026-krav (fortrydelsesfunktion 19/6, GPSR, Omnibus, OSS vs IOSS, Judge.me-sunset). Bygget: runner, payload-builder, manifest med 22 trin, verificeret offline (20 ok / 1 skip / 0 fejl). Ikke verificeret: faktiske MCP-værktøjsnavne på warpstorm, plugin-kompatibilitet med WP 7.1.3, og om Simply tillader `FS_METHOD=direct`. |
