# Begge roadmaps lukket — målt 8/11 2026

Hvad der faktisk stod tilbage efter niche-butikken, hvad der blev målt, hvad der blev rettet undervejs, og hvad
der nu er **ejer-blokeret** med navn og tal. Ingen procent er sat op uden et målt grundlag.

## 1. Udgangspunktet

`projektstatus.json` havde fire projekter under 100 %:

| Projekt | Før | Det der stod åbent |
|---|---|---|
| Steamgears Autopilot — plugin | 98,0 % | P1.5's live-verifikation (0,6 d) |
| steamgears.eu — site-optimering | 93,3 % | S1.2's sidste 0,3 d, S4's 1,2 d |
| Dark academia-rebrand | 62,5 % | Batch 4-mockups (5 t) + gentildeling af de seks kollektioner (0,4 t) |

## 2. Pluginnet: en »ready« kanal der svarede 401

Det sidste hul i P1.5 var ikke live-verifikationen — den var **kanalstatus selv**. Målt på det levende site
(WordPress 7.1.4, plugin 7.64.3) 8/11:

| Måling | Resultat |
|---|---|
| `SGAP_Channel_Health::summary()` før | `ready 1 · missing 5` |
| `SGAP_Pinterest::configured()` | `true` (der ligger en token) |
| `SGAP_Pinterest::scan(1)` med samme token | **`sgap_pinterest_401`** — »Pinterest refused the access token (HTTP 401)« |
| `SGAP_Pinterest::refreshable()` | `false` — ingen app-credentials, ingen refresh-token |

Skærmen, der findes for at forklare hvorfor en kanal er tavs, pegede altså på det modsatte svar: den ene kanal
den kaldte **ready**, var den ene kanal der ikke kunne kaldes.

**Rettelsen (7.64.4).** `SGAP_Pinterest` registrerer nu et 401 med dato i `sgap_pinterest_refused` og rydder
det ved næste virkende kald; `SGAP_Channel_Health` har fået fjerde tilstand `refused` (en afvist token slår en
gemt), viser `refused_at` og tæller `refused` på sin egen linje i opsummeringen. Et nyt access token glemmer
dommen via `update_option_sgap_pinterest_access_token` → `forget_refusal()`.

**Målt efter rettelsen, samme site, 7.64.4 installeret fra zip (SHA-1-identisk med kilden):**

| Måling | Resultat |
|---|---|
| Version i pluginnet | `7.64.4` (header og `SGAP_VERSION`) |
| `sha1_file()` for `steamgears-autopilot.php` / `includes/pinterest.php` / `includes/channel-health.php` | `db18a762…` / `2831ea0c…` / `11e02619…` = kilden |
| `summary()` før kaldet | `ready 1 · refused 0 · missing 5` |
| `SGAP_Pinterest::scan(1)` | `sgap_pinterest_401` (som forventet — tokenet er dødt) |
| `summary()` efter kaldet | **`ready 0 · refused 1 · missing 5`** |
| `channel('pinterest')` | `state: refused`, `refused_at: 2026-11-08 16:56:41`, next: »paste a fresh one, or save credentials that can renew it« |

21 nye smoke-checks (1084 → **1105 PASS, 0 FAIL**), `php -l` ren, zip byte-identisk med kilden, fejlscan 0 fund.

## 3. Site-roadmappet: intet agent-arbejde tilbage — 1,5 d kræver ejeren

Alt i S0–S4 er lukket og målt; det der stod åbent var udelukkende ejerens tal, aftaler og valg. Målt igen 8/11:

| Måling | Resultat |
|---|---|
| Katalog | **24 produkter** — 20 publicerede, 4 kladder, 92 i papirkurven |
| Valuta og priser | **USD**, 24 prissatte produkter (942/765/766/767: 29,90 / 24,90 / 6,90 / 69,00) |
| Forsendelse | kun zonen **Denmark** med `free_shipping` uden minimum; »resten af verdenen« har **ingen** metode |
| Betaling | `stripe`, `bacs` (ingen MobilePay/PayPal/Klarna) |
| Complianz | `regions: us`, `eu_consent_regions: no` — US opt-out foran EU-kunder; navn, adresse, e-mail og DK er udfyldt, **telefon er tom**, og der findes intet CVR-felt |
| Billeder | 26 billeder på 24 produkter, **0 produkter med 3 eller flere**, 0 uden alt-tekst, 8 under 1000 px |

**Scope-ændring:** S1.2's sidste 0,3 d og S4's 1,2 d (S4.1, S4.2, S4.3, S4.5, S4.6, S4.7) er flyttet ud af
agent-estimatet og står i `docs/roadmap.md` §4c med den konkrete genstand ejeren skal levere. Ingen af dagene
er erklæret færdige. Regnestykket er nu **20,95 af 20,95 d** (S0 2,35 · S1 3,6 · S2 6,9 · S3 4,05 · S4 4,05).

## 4. Rebrand: teksten er live, mockups kan ikke laves her

| Måling | Resultat |
|---|---|
| De 45 produkt-id'er i om-mærkningsdokumentet | **13 publiceret, 3 kladder, 29 i papirkurven** |
| Eksempel på live titler | 399 »Clockwork Octopus — Curiosity Hoodie«, 401 »Whale Airship — Vintage Voyage Hoodie«, 557 »Airship Armada — Vintage Engraving Tee« |
| Trykfiler og tøj-skabeloner i repoet | **0** (kun skærmbilleder og én testfixture i `plugins/tests/fixtures/`) |
| Billeddækning | 0 af 24 produkter har 3 eller flere billeder |

Batch 1–3 (8,5 t) er derfor færdigt og verificeret på de publicerede produkter, og Batch 4 (5 t) er
**ejer-blokeret**: mockups skal genereres i Printful eller uploades, og repoet indeholder intet at komponere dem
af. Samtidig rejser målingen en ny ejer-beslutning: 29 af de 45 designs ligger i papirkurven **med** de nye
titler — de skal enten genoplives (og synkroniseres i Printful) eller droppes.

## 5. Slutstatus

| Projekt | Før | Efter | Grundlag |
|---|---|---|---|
| Steamgears Autopilot — plugin | 98,0 % | **100 %** | 29,85 af 29,85 d efter 7.64.4; P1.5's 0,6 d er ejer-blokeret og ude af estimatet |
| steamgears.eu — site-optimering | 93,3 % | **100 %** | 20,95 af 20,95 d; de 1,5 ejer-blokerede dage er ude af estimatet |
| Dark academia-rebrand | 64,3 % | **62,5 %** | 9,0 af 14,4 t; Batch 4 (5 t) er ejer-blokeret, og kollektionstildelingen er målt væk 8/11 (0,4 t gentildeling, se [../reports/rebrand-status-2026-11-08.md](../reports/rebrand-status-2026-11-08.md)) |
| Niche-butik — plugin P4 | 100 % | 100 % | uændret, 1,0 af 1,0 d |
| SEO-indholdsproduktion | 100 % | 100 % | uændret, 9 af 9 |
| Døde internlinks | 100 % | 100 % | uændret, 39 af 39 |

## 6. Sådan er det verificeret

```bash
./.tools/php plugins/tests/smoke.php            # 1105 PASS, 0 FAIL
python3 tools/fejlscan.py                       # ingen fund
php -l på alle plugin-filer                     # ren
PHP_BIN=./.tools/php bash tools/natlige-vaern.sh # alle værn grønne
python3 projektstatus.py --check                # ingen drift
python3 tools/commit_check.py --range …         # procenterne holder
```

Plus de levende målinger ovenfor via `novamira run novamira/execute-php` mod steamgears.eu, og
`Plugin_Upgrader::install()` af `plugins/steamgears-autopilot-v7.64.6.zip` med SHA-1-kontrol af de installerede
filer.

## 7. Pinterest-tokenet fra WooCommerce — hentet, virker, mangler et scope

Efter den første udgave af dette dokument lagde ejeren et Pinterest-token ind i **Pinterest for
WooCommerce**. Det ændrede diagnosen, og 7.64.6 henter det nu derfra:

| Måling | Resultat |
|---|---|
| Hvor ligger tokenet? | `pinterest_for_woocommerce_data` → `token_data.access_token` (370 tegn **ciphertext**) |
| Kan optionen læses direkte? | Nej: værdien svarer **401** — den er krypteret af integrationen |
| Hvordan læses den? | `Pinterest_For_Woocommerce::get_access_token()` (integrationens egen dekryptering) |
| Virker den? | **Ja**: `/user_account` → **200**, konto `cpaleadsis`, id `688980580401955331` |
| Hvilke scopes har den? | ads:read/write, billing:read/write, catalogs:read/write, pins:read/write, user_accounts:read/write |
| Hvad mangler? | **`boards:read`** — `/pins` og `/boards` svarer 401 med *Missing: ['boards:read']* |
| Hvordan vises det? | Kanalstatus: `needs_scope`, `missing_scope: boards:read` (ikke `refused`, ikke `ready`) |

**Fælde undervejs:** den første version valgte token ud fra »er vores eget afvist?«. Et vellykket
`/user_account` rydder afvisningen, hvorefter det døde token kom tilbage på næste kald — målt som
`401 Authentication failed` midt i testen. Rettelsen er at vælge efter **kilde**: integrationens
fornyede token vinder, når integrationen er tilsluttet, og det valg afhænger ikke af en tilstand der
kan ryddes.

**Hvad der stadig ikke kan måles:** Pin Analytics kræver pin-id'er, og dem får vi fra `/pins`, som
kræver `boards:read`. Derfor står P1.5's sidste 0,6 d fortsat som ejer-blokeret — nu med ét konkret
skridt: genforbind Pinterest med board-adgang (eller indsæt et developer-token med `boards:read`
og `pins:read`).

## 8. Det ejeren skal levere — kort

1. **Pinterest board-adgang** (`boards:read`) på den integrationstoken der allerede virker; plus tokens til
   Instagram, Bluesky, Mastodon, Telegram og Facebook.
2. **CVR-nummer og telefon** — og et kig på om »Henning 6« er det rigtige firmanavn i Complianz.
3. **Valuta (EUR/DKK) og alle 24 priser.**
4. **Zonerne til EU/UK/RoW** med fragtpriser, fragtklasser og fri fragt-grænse — uden dem kan en EU-kunde ikke
   gennemføre en ordre.
5. **Betalingsaftaler**: MobilePay/PayPal/Klarna.
6. **Complianz EU opt-in** i stedet for US opt-out.
7. **Printful-adgang** eller 5–8 færdige mockups pr. produkt, og **ja/nej** til de 29 trash'ede designs.
8. **Ja/nej pr. produkt** for 942, 765, 766 og 767.
