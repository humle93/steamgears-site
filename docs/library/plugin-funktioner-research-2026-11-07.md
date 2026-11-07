# Research: fire plugin-funktioner, målt mod deres API'er (7/11 2026)

**Hvorfor:** to åbne punkter i pluginnet — **P1.5** (platform-analytics) og beslutningen om
**Action Scheduler** (P0.4's valgfrie opfølger) — har stået som »blokeret eksternt« og »2,0 d« uden at nogen
havde læst API-dokumentationen. Denne research svarer på hvad der faktisk kræves, og hvad det koster.
**Intet er bygget i dette pas.**

## 0. Konklusionen først

| Spørgsmål | Svar | Konsekvens |
| --- | --- | --- |
| Skal vi bundle Action Scheduler? | **Nej.** WooCommerce 11.x leverer den allerede på sitet | 2,0 d bliver ~0,5 d: brug den hvis den er der, fald tilbage til WP-Cron |
| Er Pinterest-analytics blokeret af Standard-access? | **Nej.** Pin Analytics er med i Trial | P1.5's Pinterest-del kan bygges med det samme |
| Kræver Instagram-insights App Review? | **Ikke for egen konto.** Standard Access til egen business kræver intet review | P1.5's IG-del er byggbar nu; kun »flere kunder« kræver review |
| Kan Bluesky og Mastodon give engagement? | **Ja, uden token.** Begge er offentlige reads | Billigste slice: ~0,5 d, ingen godkendelse |

Så P1.5's »4,0 d + ekstern blokering« er forkert på begge punkter: arbejdet kan deles i tre, og kun den
sidste del (hvis appen skal betjene andre end ejeren) kræver Metas kø.

## 1. Action Scheduler — biblioteket er allerede på sitet

- **Beregnet til at blive bundlet.** »Action Scheduler is designed to be used and released in plugins«;
  man inkluderer `action-scheduler/action-scheduler.php`, og biblioteket registrerer selv versionen og
  indlæser den nyeste kopi på sitet. Licens **GPL-3.0**. ([FAQ](https://actionscheduler.org/faq),
  [GitHub](https://github.com/woocommerce/action-scheduler))
- **Versionspolitik: L-2.** Biblioteket kræver »seneste WordPress minus to« og den PHP-minimumversion, den
  WordPress så har. På WP 7.1/PHP 8.5 er det ikke en begrænsning.
- **Ingen afhængighed af WP-Cron, men samme trigger som WP-Cron som standard:** den startes af WP-Cron og af
  `shutdown` på admin-requests. Den kan køres via WP-CLI (`wp action-scheduler run`), og det er den vej
  Woo's egen dokumentation anbefaler til pålidelighed.
- **API'et spejler WP-Cron:** `as_schedule_single_action()`, `as_schedule_recurring_action()`,
  `as_enqueue_async_action()`, `as_has_scheduled_action()`. Der er `$group` til at holde vores jobs samlet, og
  `$unique` til at afvise dubletter — **fra 4.0.0 tager `$unique` også højde for argumenterne**, så to
  identiske hooks med forskellige args ikke længere kolliderer.
- **4.0.0 (juni 2026) ændrer oprydningen:** fejlede actions **purges nu som standard**. Det er relevant for
  os: en dead-letter-liste må ikke bygges på Action Schedulers »failed«-status alene, for den bliver ryddet.
- **Tabellerne tilhører sitet, ikke pluginnet**, og version-håndteringen vælger den nyeste kopi. Det er
  præcis derfor vi **ikke** skal bundle: WooCommerce 11.x har den, og en ekstra kopi i vores zip ville være
  død vægt, der skal vedligeholdes ved hvert Woo-slip.

**Anbefaling:** vent ikke på en 2,0 d-migration. Skriv en tynd adapter på ~0,5 d:
`function_exists('as_schedule_recurring_action')` → brug Action Scheduler med en `sgap`-gruppe,
ellers fald tilbage til `wp_schedule_event()`. Så arver vi Woo's pålidelighed uden at eje biblioteket, og
P0.4's Site Health-test bliver ved med at være sandheden om forsinkelsen.

## 2. Pinterest API v5 — analytics er med i Trial

- **Pin-analytics:** `GET /v5/pins/{pin_id}/analytics` og `GET /v5/pins/analytics` (op til 100 pin-ids).
  Scopes: **`boards:read` + `pins:read`**. ([pin analytics](https://developers.pinterest.com/docs/api/v5/pins-analytics),
  [multi](https://developers.pinterest.com/docs/api/v5/multi_pins-analytics/))
- **Vindue:** `start_date` kan højst gå **90 dage tilbage**; `end_date` højst 90 dage efter `start_date`.
  Det betyder at historik skal hentes løbende — vi kan ikke backfille et år senere.
- **Metrics i svaret:** `IMPRESSION`, `SAVE`, `SAVE_RATE`, `PIN_CLICK`, `OUTBOUND_CLICK` (plus video-felter).
  Det dækker præcis de tre tal vi mangler: rækkevidde, gem og klik.
- **Konto-niveau:** `GET /v5/user_account/analytics/top_pins` giver de **top 50 pins** sorteret på
  `ENGAGEMENT`/`SAVE`/`IMPRESSION`/`OUTBOUND_CLICK`, med scopes `pins:read` + **`user_accounts:read`**.
- **Rate limits:** kategorien `org_analytics` er **1.000 requests/dag på Trial** og **60/min pr. bruger på
  Standard**. Trial er nok til et dagligt job for 20–40 pins.
- **Adgang:** Pinterests egen oversigt over access tiers viser **»Pin Analytics: Yes« i både Trial og
  Standard**. Trial kræver kun godkendt app — ikke Standard.
  ([access tiers](https://developers.pinterest.com/docs/key-concepts/access-tiers/))

**Konsekvens for P1.5:** Pinterest-delen er **ikke** blokeret af Standard-access. Den er blokeret af et
token, og det token kan være et Trial-token. Repoets tidligere note om »Pinterest API token still 401« er
altså et token-problem, ikke et adgangsproblem — det skal verificeres mod det faktiske token, ikke antages.

## 3. Instagram — review-kravet gælder kun andre folks konti

To opsætninger, og de kræver forskellige permissions:

| | Instagram Login | Facebook Login |
| --- | --- | --- |
| Host | `graph.instagram.com` | `graph.facebook.com` |
| Permissions (insights) | `instagram_business_basic` + `instagram_business_manage_insights` | `instagram_basic` + `instagram_manage_insights` + `pages_read_engagement` |

- **App Review:** Metas egen tabel siger at scenariet »my app is only for a business I own or manage« er
  **Standard Access — App Review not required**, for begge login-typer. Advanced Access (til at betjene
  andre virksomheder) kræver review **og** Business Verification.
  ([App Review for Instagram API](https://developers.facebook.com/documentation/instagram-platform/app-review))
- **Metrics:** konto-niveau giver `reach`, `views`, `accounts_engaged`, `total_interactions`, `saves`,
  `shares`, `profile_visits`; per medie `GET /{media-id}/insights` giver `views`, `reach`, `likes`,
  `comments`, `saved`, `shares`, `total_interactions` og reels-tid.
- **`impressions` er væk:** deprecateret i v22.0 og for alle versioner **21. april 2025**; `views` afløser
  den. En ingestion der spørger efter `impressions` får fejl på nye medier.
  ([Account Insights](https://developers.facebook.com/docs/instagram-api/reference/ig-user/insights),
  [changelog](https://developers.facebook.com/documentation/instagram-platform/changelog))

**Konsekvens for P1.5:** egen IG-konto kan læses **nu** med Standard Access og `*_manage_insights`. Det er
først hvis værktøjet skal sælges til andre, at Meta-køen (App Review + Business Verification) kommer ind.

## 4. Bluesky og Mastodon — engagement uden godkendelse

- **Bluesky:** `app.bsky.*`-læsninger er **offentlige** og kan kaldes mod `https://public.api.bsky.app`
  **uden token**; skrivninger og konto-private data kræver auth. `app.bsky.feed.getPosts` returnerer
  opslagene med deres tællinger (`likeCount`, `repostCount`, `replyCount`).
  ([getPosts](https://docs.bsky.app/docs/api/app-bsky-feed-get-posts),
  [rate limits](https://docs.bsky.app/docs/advanced-guides/rate-limits))
- **Mastodon:** `GET /api/v1/statuses/:id` er **offentlig for offentlige status'er** og returnerer
  `favourites_count`, `reblogs_count`, `replies_count` og (4.5+) `quotes_count`.
  ([Status-objektet](https://docs.joinmastodon.org/entities/Status/),
  [statuses](https://docs.joinmastodon.org/methods/statuses))
- **Ingen af dem har impressions eller reach.** De tal findes ikke på protokollerne, og de skal stå som
  `null` i `sgap_events` — ikke gættes ud fra følgere.

## 5. Sådan deles P1.5 (4,0 d → 3,0 d)

| Slice | Indhold | Krav | Effort |
| --- | --- | --- | --- |
| **P1.5a** | Bluesky + Mastodon: læs `likeCount`/`favourites_count`, `repostCount`/`reblogs_count`, svar; skriv til `sgap_events` | Ingen — offentlige reads | **0,5 d** |
| **P1.5b** | Pinterest: dagligt job der henter 90-dages vindue for publicerede pins, `IMPRESSION`/`SAVE`/`OUTBOUND_CLICK` | Trial-token med `pins:read`, `boards:read` | **1,0 d** |
| **P1.5c** | Instagram: konto- og medie-insights med `views`/`reach`/`saves`/`shares` | Egen konto: Standard Access, intet review | **1,5 d** |
| — | Samlet mindre end de 4,0 d, fordi tre lag deler én ingestion-tabel og ét dagligt job | | **3,0 d** |

**Rækkefølgen er ikke tilfældig:** P1.5a giver tal fra dag ét uden nogen godkendelse, P1.5b kræver kun et
token, og P1.5c er den eneste med en ekstern afhængighed — og kun hvis appen skal ud over ejerens konto.

## 6. Hvad der stadig er ejerens beslutning

1. **Action Scheduler:** adapter på 0,5 d oven på Woo's kopi (anbefalet), eller den fulde 2,0 d-migration.
2. **P1.5:** skal alle tre slices bygges, eller kun P1.5a+P1.5b (1,5 d) indtil Instagram-tokenet er prøvet?
3. **Historik:** Pinterest kan kun gå 90 dage tilbage, så en beslutning nu afgør om første måned bliver
   backfilled eller ej.

## 7. Kilder

- Action Scheduler: [FAQ](https://actionscheduler.org/faq) · [usage](https://actionscheduler.org/usage) ·
  [API](https://github.com/woocommerce/action-scheduler/blob/trunk/docs/api.md) ·
  [4.0.0-ændringer](https://developer.woocommerce.com/2026/06/17/changes-to-action-scheduler)
- Pinterest: [pin analytics](https://developers.pinterest.com/docs/api/v5/pins-analytics) ·
  [top pins](https://developers.pinterest.com/docs/api/v5/user_account-analytics-top_pins) ·
  [rate limits](https://developer.pinterest.com/docs/reference/rate-limits) ·
  [access tiers](https://developers.pinterest.com/docs/key-concepts/access-tiers/)
- Meta: [Instagram Platform overview](https://developers.facebook.com/docs/instagram-platform/overview) ·
  [App Review-krav](https://developers.facebook.com/documentation/instagram-platform/app-review) ·
  [Account Insights](https://developers.facebook.com/docs/instagram-api/reference/ig-user/insights) ·
  [changelog](https://developers.facebook.com/documentation/instagram-platform/changelog)
- Bluesky: [getPosts](https://docs.bsky.app/docs/api/app-bsky-feed-get-posts) ·
  [rate limits](https://docs.bsky.app/docs/advanced-guides/rate-limits)
- Mastodon: [Status](https://docs.joinmastodon.org/entities/Status/) ·
  [statuses](https://docs.joinmastodon.org/methods/statuses)
