# Steamgears Autopilot — audit, roadmap and effort estimates

_Last audited: 2026-11-12 (7.83.0) · the audit further down was of **7.15.0** (`ab676db` on `main`); the site runs
**7.65.5** (measured 9/11 2026 through Novamira: the zip was installed with `Plugin_Upgrader` and the installed
version reads back as 7.65.5). The repo, and the zip in `plugins/`, is **7.76.1** (7.76.0 made OpenRouter the
standard provider and added one-click Command Center login; 7.76.1 fixes the REST date/permission edges and the
uninstall cleanup), so the next *Plugins → Update* is a real upgrade and not a no-op · update this file in the
same commit as any release._

**The 7.64.9 upgrade has no agent path, measured 8/11 2026:** there is no MCP credential anywhere — 0
repository secrets, no token file in the sandbox, and the public routes that exist answer `401` — so the
install is the owner's ***Plugins → Update*** click, not something a push can do. With
`NOVAMIRA_REFRESH_TOKEN` + `NOVAMIRA_SECRETS_TOKEN` in place, the nightly keepalive proves the installed
`autopilot_version` and writes the channel measurement by itself. Evidence, rejected paths and the
rollback gap: [`docs/library/live-7-64-9-2026-11-08.md`](../../docs/library/live-7-64-9-2026-11-08.md).

**Opdateringsvejen er prøvet 8/11 2026, så klikket ikke kan fejle på et manglende pakke-link:** `remote_version()` læser `Version: 7.64.9` fra `main` (HTTP 200), og det link `package_url()` bygger svarer 200 med et arkiv der er byte-identisk med zip'en i repoet — størrelse og SHA-256 står i [`reports/release-preflight-2026-11-08.md`](../../reports/release-preflight-2026-11-08.md), målt på den publicerede fil. Zip'en har én rodmappe (`steamgears-autopilot/`), 49 poster, `Version: 7.64.9` i plugin-headeren og hverken `tests/` eller stier ud af mappen. Det var netop den fælde 7.58.2 lukkede: et *Update*-klik uden noget at installere.

**History, kept because it explains the rituals:** 7.58.0 took the whole REST API down —
`includes/diagnostics.php` registered its route with `SGAP_Admin::NS`, a constant that has never existed, so
`rest_api_init` died with a fatal on every `/wp-json/` request (measured 7/11 2026, see
`docs/library/rest-nedbrud-7-58-0-2026-11-07.md`). 7.58.1 fixed that with `SGAP_REST::NS`, and 7.58.2 fixed the
second half of the same trap — the dashboard could say “Up to date” for six hours after a release, so *Update*
had nothing to install. The release then sat on disk **inactive** while WooCommerce was inactive too, and
`mu-plugins/sga-payload-trim.php:134` called `is_woocommerce()` unguarded, so the homepage, `/shop/` and
`/?nocache=1` answered **HTTP 500**. All of it was resolved 7/11: WooCommerce 11.2.2 and the plugin active,
18 unused plugins deleted, and the cached fatal page flushed with `w3tc_flush_all()`.

This is the working roadmap for `plugins/steamgears-autopilot`. It answers three questions: does every
function in the plugin actually do something, what is missing before the plugin can truly run unattended,
and how long each missing piece takes.

---

## 0. Short version

**P12 (7.85.0, 13/11 2026) — kun OpenRouter, ingen billed-AI, ryddet admin-UI, 4,0 d (plan 4,0 d).** Ejeren bad om tre ting: fjern AIMLAPI, gør OpenRouter til den eneste udbyder, og ryd hele pluginets opsætning. Resultatet er ét AI-trin i guiden, ét nøglekort, én model-dropdown uden standardvalg og ingen billedgenerering: Instagram og Pinterest bruger sidens featured image. Reserve-udbyderen og gratis-model-forsøget er også væk, så en fejl rapporteres med OpenRouters egne ord i stedet for at blive forsøgt igen.

| Fil | Ændring | Andel |
| --- | --- | --- |
| `includes/generator.php` | omskrevet til OpenRouter-only: `provider()` fast, ingen fallback/free-retry/Anthropic, én vej gennem `chat_completions()` | 25 % |
| `includes/studio.php` | `SGAP_Images` og hele billed-stien slettet; kun *Write* tilbage | 15 % |
| `includes/core.php`, `setup.php`, `keys.php`, `keycheck.php`, `chat.php` | ét udbyderkort, ét wizard-trin, `.env`-agtig nøglekontrol, ingen provider-valg eller backup | 15 % |
| `includes/automation.php`, `rss.php`, `preflight.php`, `editor.php`, `abilities.php` m.fl. | featured image i stedet for genereret foto; selvtjek og tekster uden AI-fotos/backup | 10 % |
| `assets/pages/studio.js`, `assets/admin.css` | ingen billed-trin eller `.sgap-studio-img` | 5 % |
| `plugins/tests/smoke.php` + `cc-login.php`, `keys.php`, `automation.php`, `integration.php`, `admin-tools.php` | OpenRouter-mocks, featured-image-forløb, 42 abilities, ingen free-retry/backup-checks | 15 % |
| `README.md`, `ROADMAP.md`, `CHANGELOG.md`, `docs/plan-openrouter-only-ui-2026-11-11.md` | udbyderafsnittet erstattet, 7.85.0-note, planen med faser og estimat | 10 % |
| `plugins/steamgears-autopilot-v7.85.0.zip`, `projektstatus.json` | én zip der matcher kilden, P12-række | 5 % |

**Behov, ikke gjort:** intet kald er gået til den rigtige OpenRouter (ingen nøgle i sandkassen), og intet WordPress er kørt her — `integration.php` kræver MySQL + wp-cli. Målt: 1 570 PASS / 0 FAIL i smoke.

**P11 (7.84.0, plan 12/11 2026) — Publer udfases, 0,6 d.** Facebook, Instagram og Pinterest poster direkte, kanal for kanal, mens resten stadig går via RSS til Publer. Publer opsiges først, når hver kanal er målt live (abonnementet fornyes næste måned).

| Fil | Ændring | Andel |
| --- | --- | --- |
| `includes/direct.php` (ny) | `SGAP_Direct`: hvilken kanal postes direkte, ét publiceringskald, fælles bogføring | 15 % |
| `includes/rss.php` | RSS-tilstand poster direkte-kanaler selv; feed-sundhed springer dem over | 15 % |
| `includes/publer.php` | API-tilstand bruger `SGAP_Direct`; kører uden Publer-nøgle når kanalerne er direkte | 10 % |
| `includes/instagram.php` | `publish()`: container → status → `media_publish` (Instagram Login) | 15 % |
| `includes/pinterest.php` | `publish()`: `POST /v5/pins` med board, titel, tekst, link, alt og billede | 15 % |
| `includes/preflight.php`, `core.php`, `setup.php` | `instagram_direct`, `pinterest_direct`, `pinterest_board_id`; board-tjek kender den direkte board | 5 % |
| `plugins/tests/smoke.php` | checks for opdeling, de tre kanaler, fejl og RSS-blanding | 15 % |
| `docs/library/publer-udfasning-2026-11-12.md` (+ PDF) | ejerens guide: Meta-app, Instagram-udgivelse, Pinterest-app, rækkefølge, opsigelse | 10 % |

**P9.9 (7.83.0, 12/11 2026) — AI-redaktør, 0,3 d.** `includes/editor.php` (ny, 50 %), `generator.php::write_for` (dom før kø, 20 %), `automation.php` (selvtjek, 5 %), smoke (12 checks, 25 %). Gratis modeller; intet svar = venter på ejeren.

**P9.8 (7.82.0, 12/11 2026) — prioriteter fra Command Center, 0,4 d.** CC sender sider på plads 4-20 + søgeord (`POST /priorities`); `pick()` tager dem først og prompten bruger søgeordet. Fil: `includes/priorities.php` (ny, 45 %), `includes/generator.php` (pick/source/prompt, 25 %), `includes/core.php` (snapshot, 5 %), `plugins/tests/smoke.php` (13 checks, 25 %). Arbejdsdelingen er uændret: pluginnet ejer opslagene, CC leverer målingerne.

**P9.7 (7.81.0, 12/11 2026) — gratis reserve-model, 0,15 d.** Ved kreditfejl hos OpenRouter prøves `google/gemma-4-31b-it:free` → `openrouter/free` (målt: cost 0). Ændringer: `includes/generator.php` (`free_models`, `wants_free_retry`, `free_retry`, optælling i `write_for`) 60 %, `includes/automation.php` (selvtjek) 15 %, `plugins/tests/smoke.php` (9 checks) 25 %.

**P9.6 (7.80.0, 12/11 2026) — output-værn, 0,25 d, 0,2 done.** Målt live 12/11 på 7.79.0: 22 opslag stod som
udgivet på 7 kanaler der er slukket, fordi `claim()` frigav hele køen; OpenRouter-kreditten blev opbrugt
og genereringen fejlede uden at selvtjekket sagde noget; `max_tokens` var fast 8 000/16 000.

| Fil | Ændring | Andel |
| --- | --- | --- |
| `includes/core.php` | `claim()` frigiver kun tændte kanaler (+ påmindelser); `parked()` tæller resten | 35 % |
| `includes/generator.php` | `token_budget()` = 1 500 + 700 pr. kanal (2 500-8 000) for begge udbydere | 20 % |
| `includes/automation.php` | `output_problems()` i `health()`: tom AI-kørsel og ventende opslag | 25 % |
| `plugins/tests/smoke.php` | 10 checks (1 578 PASS) | 20 % |

Mangler til 100 %: installation på sitet og en live-måling af selvtjekket.

**Grænsen mod Command Center (besluttet 12/11):** pluginnet ejer sociale opslag (kø, feeds, kanaler,
evergreen og klik). Command Center ejer analyse, SEO og artikler. Artikler når kun WordPress som kladder,
og CC læser `/snapshot` uden at skrive i pluginnets kø. Sådan undgår man to motorer, der skriver opslag til
de samme kanaler.

- **58 classes. 45 PHP files. 20.424 lines** (measured 8/11 2026 on 7.64.9). Every subsystem is reachable
  from a real entry point (cron, REST, admin page, ability or AJAX); the last dead method,
  `SGAP_Admin::get_setting_help_link()`, was deleted in 7.33.3.
- **0 settings are registered but never read** (down from 17). Nine invisible leftovers were removed in
  7.33.3 (`pinterest_token`, `instagram_token`, `tiktok_token`, `youtube_token`, `youtube_channel_id`,
  `reddit_username`, `reddit_subreddit`, `email_test_recipient`, `ai_mode`), the five Etsy keys were hidden in
  7.33.4, and the last one — `labor_rate` — has been read by the ROI card since **7.59.0** (P3.1).
- **It tells you when something is wrong.** Failure mail, a "queue is running dry" alert per channel, retry
  backoff (5 → 15 → 45 min), a publish audit log, the cross-system duplicate guard, Action Scheduler when
  WooCommerce supplies it, and analytics ingestion for all six channels are built and covered by tests (P0.1–P0.7c,
  P1.5a–c). What still stands between "works" and "set and forget" is **live verification of the analytics**:
  the code runs, but the numbers only arrive when each channel has a token the owner owns.
- **Effort: 31,20 developer-days of agent work, all of it done and verified** (`projektstatus.json` books 33,75,
  fordi P4 og P3.7–P3.9 er egne moduler der; P4 er projektet `niche`), plus
  **0,6 d of live verification that is owner-blocked** and therefore sits *outside* the estimate — P1.5's last
  step. Full parity with hosted schedulers is **18–21 developer-days**, before external API approvals that take
  calendar weeks. Within that scope only two things are left: **P1.5's live verification**, which needs the
  owner's update click, a Pinterest scope and five channel tokens, and the **Command Center direction**
  (one-way CC reading `/snapshot`), which is the owner's decision in §8. P2.1–P2.3 are built.

---

---

## Historical snapshot — the audit of 7.15.0 (written 2026-11-01)

> **This is history, not the plan.** Sections 1–4 below are the audit exactly as it stood on 1 November 2026,
> measured on **7.15.0** (`ab676db`): the call graph, the file and smoke counts, "what we do not have", and every
> finding. Several of the gaps they describe have since shipped — P0.1–P0.7c, P1.1–P1.22, P1.5a–c, P2.1–P2.6,
> P3.1–P3.6 and P4.1 — so read "we do not have" and the numbers as *the state then*, not as open work.
> **The current plan is §5**, whose status is measured on **7.64.9** (8/11 2026); §6 is the 1/11 estimate, kept
> to show how the scope grew, and §8 says who owns each remaining decision. §5 and §8 are current; §1–§4 are not.

## 1. Method (1/11 2026 — historical)

| What | How |
| --- | --- |
| Call graph | Static inventory of every `function` definition, then counted call sites and string references across all 15 files (hook callbacks show up as strings). |
| Settings | Cross-referenced every key in `DEFAULTS` / `*_KEYS` / `SECTIONS` / `TABS` against every `SGAP_Settings::get/enabled/set()` and `get_option()` read. |
| Syntax | `php -l` on all 21 PHP files (plugin + tests), PHP 8.3.14. |
| Behaviour | `plugins/tests/smoke.php` (stubbed WordPress) locally; `plugins/tests/integration.php` (real WordPress + MySQL, queue/drafts/tracking/AI/Publer/rescue/maintenance/evergreen) in CI. |
| Compatibility | Manual scan for PHP 8.4 implicit-nullable signatures; CI lint matrix 8.0–8.5. |

**Verified 6/11:** `integration.php` runs in CI and was also run here against WordPress 7.1.4 + MySQL 8.0.36
(PHP 8.2.32, CI's kombination, wp-cli): **249 PASS / 0 FAIL**, and against WordPress 6.4:
**184 PASS / 0 FAIL** (the ability groups are skipped because the Abilities API starts at 6.9). Nothing has
been exercised against the live steamgears.eu database, so any statement about production behaviour below
comes from reading code and docs, not from a live run.

---

## 2. Is every subsystem live? (1/11 2026 — historical)

| Subsystem | Where | Entry point | State |
| --- | --- | --- | --- |
| Content generator | `generator.php` | cron `sgap_generate`, abilities, admin **AI** | Live, mocked end-to-end in integration |
| Queue + daily cap + quiet hours | `core.php` `SGAP_Queue` | REST `/queue`, `/queue/due`, admin | Live, covered |
| RSS feeds to Publer | `rss.php` | `/wp-json/sgap/v1/feed/<channel>` | Live, covered |
| Publer API publishing | `publer.php` | settings choice `publish_mode=api` | Live, covered with mocked HTTP |
| Click tracking + UTM | `core.php` `SGAP_Tracking`, `SGAP_UTM` | `/go/<id>/` rewrite | Live, covered; `utm_campaign` is a setting plus a per-post field (7.23) |
| ROI / learning / winner-loser | `core.php` `SGAP_ROI`, `SGAP_Learning` | daily cron, `/stats`, **Campaigns** | Live, covered |
| Rescue (image backfill) | `automation.php` `SGAP_Rescue` | cron `sgap_rescue` | Live, covered |
| Promote new posts | `automation.php` `SGAP_Auto` | cron `sgap_promote_new` | Live, covered |
| Weekly article + weekly mail | `automation.php` | cron `sgap_weekly_article`, `sgap_weekly_email` | Live; mail only to `admin_email` |
| Content Studio (articles, images, SEO) | `studio.php` | admin page, abilities | Live; page render now smoke-tested |
| Chat assistant | `chat.php` | admin page, AJAX | Live; render smoke-tested, and since 7.35.0 it also reads the site through the 13 read-only MCP tools |
| Setup wizard | `setup.php` | admin page `sgap-setup` | Live; page render now smoke-tested |
| Abilities (agent tools) | `abilities.php`, `studio.php` | `wp-abilities/v1` | Live when WP 6.9+ |
| Admin tools (35 ops) | `admin-tools.php` | abilities | Live, covered |
| Self-updater | `updater.php` | `Update URI` + `update_plugins_github.com` | Live; parsing, package URL and the install path are covered |
| Maintenance + health | `core.php` `SGAP_Maintenance`, `admin-tools.php` | cron `sgap_daily_cleanup`, `sgap_daily_health` | Live, covered |
| Merchant feed (`/feed` products) | `core.php` `SGAP_REST::merchant_feed` | REST `/feed` | Live, covered (P3.2) |
| Suppression list | `core.php` | REST `/suppression` | Live, thin test |
| Reposts | `core.php` | REST `/reposts` | Live, covered (P3.2) |
| Etsy reporting | `core.php` `SGAP_REST::import_etsy_order` | REST `/etsy/orders`, admin **Etsy** | Manual/REST import only — no API client |
| Snapshot for outside dashboards | `core.php` `SGAP_REST::snapshot` | REST `/snapshot` | Live (7.15.0), smoke- and auth-tested |

---

## 3. Findings from this audit (1/11 2026 — historical)

### 3.1 Dead code (0 methods)

- ✅ **Deleted in 7.33.3** — `SGAP_Admin::get_setting_help_link()` had zero call sites and zero string
  references; it was removed together with its link table, which also carried the last references to the
  removed network tokens.

### 3.2 Registered but unused settings (1 after 7.33.4)

The five Etsy keys were hidden in 7.33.4 and nine invisible leftovers were removed in 7.33.3 (section 0).
One is left, and it is the only one whose removal is a pricing decision rather than a code change:

| Setting | Visible to the user? | Why it exists |
| --- | --- | --- |
| `labor_rate` | **Yes** — Settings → Economics | Planned cost-of-time in ROI; ROI uses `monthly_budget` and `target_roi` instead. Hiding it is the last 0.05 d of P3.1. |

Two decisions are needed (section 8): **build or hide** the Etsy block, and **remove or repurpose** the
direct-network tokens. Leaving them visible is the worst option — the screen implies an integration that
does not exist.

### 3.3 Test coverage

2.341 lines of tests across 5 files. As of this audit the smoke suite renders **all 12 admin pages**
(`sgap-setup`, `sgap-studio` and `sgap-chat` were previously never rendered — a fatal in any of them would
have reached production unnoticed; adding them immediately surfaced one missing test stub).

**Nothing from this audit remains untested.** The chat conversation loop was the last gap; six smoke checks
now run it in both wire formats. The OpenAI-compatible path must send the tool schema, execute `analyse`, and
return the result under the same `tool_call_id` before the model gives its final answer. The Anthropic path
must do the same with `x-api-key`, `anthropic-version`, an assistant turn carrying the `tool_use` block and a
user turn carrying the matching `tool_result`. Both tests assert exactly two rounds, so a loop that silently
stops after the tool call — or keeps calling — fails. 524 → **530 PASS**.

Every audit gap is closed: P3.2 asserts `/feed` and `/reposts`, `integration.php` installs a mocked GitHub
release through `SGAP_Updater::run()`, `/snapshot` is tested both signed in and signed out, and the Anthropic
and custom OpenAI-compatible key checks assert the exact endpoint, header and result.

### 3.4 Compatibility

- All 21 files lint clean on PHP 8.3.14. Every nullable parameter is already written `?type $x = null`, so
  there are **no PHP 8.4 implicit-nullable deprecations** — the most common 8.4 breakage does not apply.
- CI lints 8.0–8.5 and runs WordPress 6.4 / 7.1 / latest on PHP 8.2 + MySQL 8.0.
- `Requires at least: 6.4` / `Requires PHP: 8.0` in the header still match the code.

---

## 4. Research: what "set and forget" actually requires (1/11 2026 — historical)

Sources are listed in section 7. The comparison set is Buffer, Publer, Metricool, Later, SocialBee,
SmarterQueue and Hootsuite — the tools a shop owner would otherwise buy.

### 4.1 Table stakes we do not have

1. **Publish-failure notifications.** Buffer emails and pushes on every failed post; Publer offers
   per-failure, hourly or daily-digest mail and keeps a *Posts → Failed* view with the reason; Metricool
   shows a "With Errors" status. The plugin writes `status=failed` plus an `error` string into the queue and
   stops there — you only find out by opening wp-admin.
2. **Connection / token expiry alerts.** Buffer notifies when a channel disconnects or permissions expire;
   Publer badges the account and links to re-auth. We hold no tokens in RSS mode (Publer does), so the
   equivalent for us is *feed health* — **closed in 7.69.0 (P7)**: empty, stale and unusable feeds are reported
   per channel next to the token status.
3. **Retry with backoff.** Publer's "Auto Reschedule" pushes a failed post to the next free slot. Ours
   re-queues immediately and gives up after 3 attempts inside the same day, so a 20-minute API hiccup at
   09:00 can burn all three attempts before 09:05.
4. **Per-network payload rules.** SocialBee customises per network; Metricool applies format-specific rules.
   We send one caption and one image to every channel and let Publer sort it out.
5. **"Send as a reminder instead of posting"** for formats the APIs cannot publish (Instagram Stories,
   interactive stickers). Metricool calls it "Sent", Later "notification publishing". We have no such mode.
6. **Analytics ingestion.** Metricool turns published posts into reports automatically and mails them on a
   schedule. We measure our own clicks and revenue, but not platform-side reach/engagement.
7. **Evergreen recycling.** SocialBee/Metricool autolists/SmarterQueue re-queue proven content with
   variations and expiry rules. We have a one-shot `repost` endpoint and a cooldown, not a recycle engine.
8. **Approval workflow.** Buffer Team/SocialBee/Hootsuite gate posting behind approval, and a missed
   approval deadline lands in the failure pipeline (Publer). We have `publish_mode` but no per-post gate.

### 4.2 Where a self-hosted plugin can beat the hosted tools

- **Automatic retry with exponential backoff and a dead-letter list.** No vendor in the research set
  documents it. The queue table already has `attempts`, `fingerprint`, `external_id` and `sent_at`, so this
  is a small, high-leverage change.
- **Queue runway forecasting** ("5 days of Pinterest content left, projected gap on 14 Nov"). Buffer's
  empty-queue alert is the ceiling in this market.
- **Blackout / holiday calendar that shifts posts instead of dropping them.** Hootsuite's "suspend content"
  is a global pause; nobody documents date-range rules.
- **Cross-system dedupe** (plugin ↔ Command Center) using a content fingerprint. Even Buffer has shipped a
  duplicate-post incident; a hash of caption + media + channel inside a window beats the market.

### 4.3 Platform limits that shape the design

Numbers that matter when choosing what to build (see sources):

| Platform | Posting without a partner tier | Hard limits that bite |
| --- | --- | --- |
| Instagram | Yes — free, Meta App Review + Business/Creator account | 100 posts/24 h rolling, 400 containers/24 h, **JPEG only**, 8 MB, aspect 4:5–1.91:1; container expires in 24 h |
| Threads | Yes — same Meta app | 250 posts/24 h, **500 characters**, **1 hashtag** |
| Facebook Page | Yes — App Review, `pages_manage_posts` | ~25 posts/Page/24 h convention; call budget 4 800 × engaged users, error 32 when exceeded |
| Pinterest | Yes — but *Trial* pins are invisible; Standard needs a demo video | `board_id` mandatory, no default board; refresh token 60-day continuous; 100 req/min per user on Standard |
| LinkedIn | Effectively no — Community Management API is vetted, `r_member_social` closed | Unpublished limits, 500 req/app/day on Development tier |
| Bluesky | **Yes — free, app password, two API calls** | 5 000 write points/hour (~1 666 posts), `createSession` 300/day |
| Mastodon | **Yes — free, per-instance token** | 300 req/5 min per account and per IP; media 30 per 30 min |
| Telegram | **Yes — free, BotFather token** | 1 msg/sec per chat, 20/min per group |
| Google Business Profile | Gated — quota is 0 until Google approves the project | 300 QPM, 10 edits/min/profile, posts expire in ~7 days |
| X / Twitter | **No free tier** — pay-per-use credits | $0.015/post, **$0.20/post when it contains a URL** |

Two conclusions. First: **Bluesky, Mastodon and Telegram are the only channels we can add in days rather
than weeks**, and two of the three need no review at all. Second: in RSS mode Publer applies most per-network
rules for us, so our job is to stop feeding it invalid payloads rather than to re-implement the rules.

---

## 5. Roadmap

Effort is developer-days for one developer who knows this codebase, excluding waiting on external reviews.
"Live path" means the code change only — every item assumes the existing smoke + integration suites pass.

**Where the plan stands (målt 9/11 2026; live og repo kører nu begge 7.65.5 (7.65.5 er en bogføringsrelease: kun denne fil er ændret)).** Til og med 9/11 formiddag kørte sitet **7.65.0**, mens repoet var nået til 7.65.4 — P3.7 og P3.8 var altså bygget og grønne i CI uden at være i drift. Det er lukket: zip'en blev uploadet gennem MCP i base64-dele og installeret med `Plugin_Upgrader::install(…, overwrite_package)`, og live måler nu `SGAP_Admin::dashboard()` **17 566 bytes HTML med 0 inline `<script>` og 0 inline `<style>`**, syv side-scripts i `assets/pages/` og to CSS-filer. Every row below is
built, covered by the smoke suite and CI-verified; the only open work is **P1.5's live verification**, which
needs the owner. Målt i samme pas: **1286 smoke-checks PASS / 0 FAIL**, 65 PHP-filer uden syntaksfejl, og
`SGAP_Channel_Health::summary()` giver `ready 0 · needs_scope 1 · missing 5` mens `analytics_summary()` står på
`measured 0 · empty 5 · no_metric 1` — altså nul platformstal i `sgap_events`, præcis som P1.5 siger. `projektstatus.json`
books the rows in the modules below, and the sum is the number the percentage is computed from:

| `projektstatus.json`-modul | dage | Rækker her |
| --- | --- | --- |
| `P0` | 4,65 | P0.1–P0.6 |
| `P0.7` | 1,00 | P0.7 |
| `P0.7c` | 0,25 | P0.7c |
| `P1` | 14,45 | P1.1–P1.18 (15,05 d i rækkerne − 0,6 d ejer-blokeret live-verifikation) |
| `P1.19`–`P1.25` | 1,95 | P1.19 0,25 · P1.20 0,25 · P1.21 0,25 · P1.22 0,50 · P1.23 0,25 · P1.24 0,35 · P1.25 0,10 |
| `P2` | 4,75 | P2.1–P2.5 |
| `P2.6` | 1,50 | P2.6 (eget modul, ikke inde i `P2`) |
| `P3` | 2,00 | P3.1–P3.6 |
| `P4` | 1,00 | P4.1 |
| **i alt** | **30,45** | = **100 %** i `projektstatus.json` (`verified: true`), der bogfører 31,60: P4 er projektet `niche`, og P3.7–P3.9 står der som egne moduler (1,15) uden en række her |

### P0 — reliability (nothing else matters until this is done)

| # | Item | Why | Effort | Depends on |
| --- | --- | --- | --- | --- |
| P0.1 | ✅ **Done 2/11** — `includes/alerts.php` (`SGAP_Alerts`): `SGAP_Queue::mark()` fires `sgap_queue_failed`, the alert mails the error text and a link to the queue row. Deduplicated per item for 6 h and capped at 5 mails/hour so a dead channel cannot storm the inbox. Modes `immediate` / `daily` / `off` under Settings → Alerts; recipient has its own setting (empty = admin address) rather than the unused `email_test_recipient` | Today a broken token or a Publer outage is invisible | 0.5 d | ✅ |
| P0.2 | ✅ **Done 2/11** — runway in days (`queued / daily_cap`) is checked in the daily self-check and warns below `alert_dry_days` (default 2, `0` = off); in `daily` mode it lands in the digest. Measured over the whole queue, not per channel — **closed 7/11 (7.42.0)**: `channel_caps` gives each channel its own cap and its own runway, so one channel running dry is no longer hidden by a healthy total; 7.43.0 makes the cap a real ceiling in the publisher | Silent gaps are the classic autopilot failure | 0.5 d | ✅ |
| P0.3 | ✅ **Done 2/11** — backoff 5 → 15 → 45 minutes, written into the existing `scheduled_at` instead of a new `next_attempt_at` column: no schema change, and `claim()` already refuses items whose `scheduled_at` is in the future. The third attempt is still terminal `failed` and now also fires the P0.1 alert | Three immediate retries can all fail during one outage | 1.0 d | ✅ |
| P0.4 | ✅ Cron honesty: detect `DISABLE_WP_CRON`, surface a Site Health test + the exact `wp-cron.php` line for real cron, document it in the README | On a cached site WP-Cron may not fire for hours; quiet hours then release posts late | 0.5 d (detect + docs) **Done 0,5 af 0,5 d (7.18.0 + 7.33.0, ejerbekræftet 7/11 2026).** Site Health-test (`SGAP_Auto::site_status_tests()`), `DISABLE_WP_CRON`-detektion, README "Real cron"-afsnit, smoke-checks og CC-kaldsregistrering. Host-cron er sat op i Simply (URL-cron mod `wp-cron.php?doing_wp_cron` hver 5. min); Websundhed viser Good. | ✅ |
| P0.5 | ✅ **Done 2/11** — `includes/audit.php` (`SGAP_Audit`): every attempt writes one row to `sgap_publish_log` — time, queue id, channel, outcome (`sent`/`rss`/`published`/`requeued`/`failed`), HTTP status carried by the Publer `WP_Error`, a 500-character response excerpt, and the actor (cron, admin, REST, WP-CLI). Own **Publish log** page with outcome filters, paging and a clear button. `SGAP_Maintenance::daily()` prunes rows older than `audit_keep_days` (default 90, `0` = keep everything). Retention rather than a cap, so a busy week cannot push out the evidence | You cannot debug "why did this not post" from `error TEXT` alone | 1.0 d | ✅ |
| P0.6 | ✅ **Done 6/11 (7.40.1)** — `includes/duplicate.php` (`SGAP_Duplicate`): the queue refuses a second hand-over of the same `channel`+`destination_url`+`title` from *any* writer, not just through the old all-time exact match. The key is the sha256 the database already indexed; **a row that is still queued blocks whatever its age** (it is about to go out), a published one only inside `dupe_days` (Settings → Publishing, default 30, `0` = the whole history), so an evergreen title may legitimately return next quarter, and `failed` rows never block. The refusal names the row it collided with — `Already queued as #412 (queued, 2026-11-08 10:00) …` — and carries `id` + `fingerprint` in the error data, and `/snapshot` exposes `fingerprint` on every queue row so an outside dashboard can recognise its own hand-over. 15 smoke checks (479 → 494) | A dashboard that retries a hand-over would post twice | 0.5 d | ✅ |
| P0.7 | ✅ **Done 7/11 (7.49.0 + 7.50.0)** — `includes/scheduler.php` (`SGAP_Scheduler`) lægger hvert job på Action Scheduler når WooCommerce leverer den (gruppe `sgap`, så `wp action-scheduler run --group=sgap` er et rent håndtag) og falder tilbage til WP-Cron ellers. `recurring()`/`single()`/`next()`/`unschedule()` er idempotente, første kørsel beholder den forsinkelse aktiveringen bad om, og `unschedule()` rydder **begge** backends, så et backend-skift ikke efterlader en tvilling. Aktivering, publicerings-tick, self-check, ugentlig artikel/mail, rescue-kø og MCP-fornyelse går nu gennem adapteren; `cron_lag()` og Site Health læser næste kørsel samme vej, så »15 minutter bagud« måles rigtigt uanset backend. Nødudgang hvis AS-køen står stille: `define('SGAP_SCHEDULER_WP_CRON', true)` eller filteret `sgap_scheduler_action_scheduler`. 20 smoke-checks (679 → 700 PASS) | Et cron-job der kun findes i WP-Cron kan ikke inspiceres, gentages eller køres manuelt — og et job der findes i begge backends kører dobbelt | 1.0 d | ✅ |
| P0.7c | ✅ **Done 7/11 (7.51.0)** — dashboardkortet **Scheduler** viser backend, Action Scheduler-version, næste publicering, forsinkelse og manglende tick; **Engagement · last 30 days** viser Bluesky/Mastodon-likes, reposts og replies eller en tydelig tom tilstand. Begge er read-only; seks smoke-checks dækker backends, lateness, manglende job og snapshots | Operators should be able to tell a healthy scheduler from a merely installed one, and read collected engagement without WP-CLI | 0.25 d | P0.7 |
| **P0 total** | | | **5.90 d** = P0.1–P0.6 **4.65 d** + P0.7 1.0 + P0.7c 0.25. **Afstemt 7/11 linje for linje** (se `docs/library/roadmap-afstemning-2026-11-07.md`): 4,00 d rækker + P0.2's kanal-loft 0,20 + P0.4's cron-checks 0,30 + `/go/`-fallback 0,15 = **4,65**. Den gamle total 4,70 talte CC-linkets 0,20 med to gange (det ligger i P0.4's 0,50, som rækken selv skriver) og udelod `/go/`-rettelsen. Intet P0-punkt var åbent — P0.4's række manglede blot ✅ | |

Done 7/11 as P0.7 — **adapter, not migration**. `SGAP_Scheduler` uses WooCommerce's copy of Action Scheduler
when it is loaded (per-job status, retries, a real WP-CLI runner) and stays on WP-Cron when it is not, so the
2.0 d migration was unnecessary: the library was already on the site. `SGAP_SCHEDULER_WP_CRON` forces WP-Cron
if the AS queue ever stalls.

### P1 — parity with what shop owners get from hosted tools

| # | Item | Why | Effort | Depends on |
| --- | --- | --- | --- | --- |
| P1.1 | ✅ **Done 2/11 (7.20.0)** — `includes/preflight.php` (`SGAP_Preflight`) runs the channel's own rules in `SGAP_Publer::publish()` **before the first API call**: Instagram (photo present, JPEG only, ≥ 320 px wide, 4:5–1.91:1), Pinterest (photo + board configured), and the caption/title/hashtag ceilings per network — Threads 500 characters incl. the tracking link and exactly one hashtag, X 280 with any link counted as 23, Facebook 63 206, LinkedIn 3 000, Instagram 2 200 / 30 hashtags, Pinterest 100 + 500, Bluesky 300, Mastodon 500, Telegram 1 024, Google 1 500. What is mechanical is **repaired** (word-boundary truncation, surplus hashtags dropped) instead of refused; what is not (no photo, wrong image format, wrong aspect, no board) is refused with a message naming the fix. Image type and size come from the attachment metadata or the file extension — the image is never downloaded to check it, and an uninspectable image is never blocked on a guess. `SGAP_Queue::add()` returns the same checks as `warnings` so the Studio and an agent see them at creation, and the ability `check-post` dry-runs the whole thing. 23 smoke checks added (128 → 151) | Turns silent failures into actionable messages | 1.0 d | — |
| P1.2 | ✅ **Done 3/11 (7.27.0)** — `remind` on a queue row (`remind TINYINT(1)`, added by `dbDelta` on upgrade), set from the *Remind me* button on the Queue page, `POST /queue` or the `queue-post` ability. `includes/reminder.php` (`SGAP_Reminder`) mails the owner the channel, format, due time, caption, image URL, tracked link and the queue link, then sets status `reminded` — terminal, so no API call, no retry, no backoff and no failure mail. Both publishers honour it: API mode skips Publer and RSS mode holds the item out of the feed, because a feed would post it anyway. The mail uses the alert recipient (`alert_email`, falling back to the admin) but is sent even when Alerts is `off`, since it is the post and not an alert; the same item is never mailed twice inside 6 h. Own publish-log outcome (*Reminded by mail*) and a `reminder` badge on the row. 20 smoke checks (265 → 285), including the empty-address fallback and the terminal-status guarantees. RSS and API share one code path so they cannot drift | Stories/carousels currently just fail | 1.0 d | ✅ |
| P1.3 | ✅ **Done 2/11 (7.21.0)** — `includes/blackout.php` (`SGAP_Blackout`): Settings → *Schedule & limits* → **Blackout days** takes one range per line (`2026-12-24..2026-12-26 Christmas`), with an optional label. Nothing is released inside a range, and a queued post that lands there is **moved to the next free day at the same time of day** — not dropped, and not saved up for one heap at the end of the range. `reschedule()` runs at the top of the publish tick, so a range added this morning is honoured before the next post goes out, and a shifted post is not shifted twice. Only `queued` rows move; `published` and in-flight `sending` rows are never touched. Queueing inside a range schedules the next free day immediately and returns the new time, `blocked_reason()` reports `blackout` with the range label and where posts go, the Queue page explains it, and a post still blocked after 60 days is placed anyway so a typo cannot park it forever. 3 smoke checks for the field and the notice, 21 in total for the calendar (151 → 172) | Holidays and sale blackouts are the #1 manual intervention | 0.5 d | — |
| P1.4 | ✅ **Done 6/11 (7.41.0)** — `includes/evergreen.php` (`SGAP_Evergreen`): a post or product can be marked evergreen, with its own share budget and expiry date, and is recycled after `evergreen_after_days` (default 60) with a freshly written variation for the next channel and angle. The variation prompt gets the page, the channel and the last captions, and is told not to repeat a hook or invent an offer; `sgap_evergreen_variation` replaces the AI call with a human or another model. Each share goes through the normal queue, so quality gate, pre-flight, blackout and duplicate guard apply; a page with a post still queued is skipped, and the share is counted only when the post reaches the queue. Own daily cron, editor metabox, **Evergreen** admin page (mark, stop, recycle now) and three abilities (`list-evergreen`, `mark-evergreen`, `recycle-evergreen`). 29 smoke checks (494 → 523). | The plugin already had reposts and cooldowns; this is the missing engine | 2.5 d | ✅ P0.6 |
| P1.5 | ⛔ **Ejer-blokeret — koden er bygget, testet og CI-verificeret, men ikke live-verificeret.** Alle tre delmoduler er færdige og grønne i smoke: **P1.5a** Bluesky/Mastodon (7.48.0), **P1.5b** Pinterest Pin Analytics (7.53.0) og **P1.5c** Instagram-insights, plus **kanalstatus** (7.61.0), der gør tilstanden læsbar pr. kanal med `ready`/`needs_refresh`/`missing_token`/`refused`/`needs_scope`. *Ikke live-verificeret* betyder at der ikke er hentet ét platformstal ind i `sgap_events` endnu: det kræver et token pr. kanal. **Målt 8/11 2026:** Pinterest-tokenet ligger i **Pinterest for WooCommerce** og virker — `/user_account` 200 som `cpaleadsis` (id 688980580401955331), læst gennem integrationens egen dekryptering — men appen har kun ads/billing/catalogs/pins/user_accounts og **mangler `boards:read`**, så `/pins` afvises og kanalstatus står `needs_scope` med scopet navngivet. **Ejeren skal gøre to ting, i denne rækkefølge:** (1) **Pinterest-scope `boards:read`** — genforbind Pinterest med board-adgang, eller indsæt et developer-token med `boards:read` + `pins:read`; (2) **tokens til de fem andre kanaler** på **Settings → Keys**: Instagram (langlivet token + professional account-id + Graph-host), Bluesky (handle + app password), Mastodon (instans + token med write:statuses), Telegram (BotFather-token + chat-id) og Facebook (page-id + page token med pages_read_engagement/pages_manage_posts). Intet af det må indsættes i chat eller repo. **Opdateringsskridtet er udgået:** sitet kører nu **7.65.0** gennem `Plugin_Upgrader`, og `steamgears-autopilot/update` svarer »Already up to date (7.65.0)« — den tidligere »(1) Update til 7.64.9« var derfor et skridt der allerede var taget. **Målt igen 8/11 kl. 21:24:** `SGAP_Channel_Health::summary()` = `ready 0 · needs_scope 1 · missing 5 · refused 0 · broken 0` af 6, og `analytics_summary(analytics())` = `measured 0 · empty 5 · no_metric 1` med **0 rækker** i `sgap_events`. Når (1)–(2) er på plads, er selve målingen **én kommando** — `python3 tools/live_channels.py` — eller den natlige kørsel, der gør det af sig selv: abilityen `channel-health` svarer pr. kanal med hvad der er landet i `sgap_events`. Indtil da melder den `blokeret`, fordi intet token findes — de 0,6 d. Platform analytics ingestion into `sgap_events`, shown per channel | Closes the loop from "we posted" to "it worked" | 3,85 d — **3,0 d bygget** (P1.5a 0,5 + P1.5b 1,0 + P1.5c 1,5); de sidste **0,6 d** er live-verifikation og er ejer-blokeret | **P1.5a ✓ 7/11** (Bluesky+Mastodon, 0,5 d) · **P1.5b ✓ 7.53.0** — Pinterest Pin Analytics · **P1.5c ✓ 7/11** — Instagram-insights · **kanalstatus ✓ 7.61.0**. Kode og tests: `plugins/tests/pinterest.php`, `plugins/tests/channel-health.php`; måling og token-diagnose: `docs/library/pinterest-tokenkilde-2026-11-08.md` |
| P1.6 | ✅ **Done 2/11 (7.22.0) — live and measured on the site** (`check-post` refuses a placeholder link with severity `block`; a `queue-post` refusal is still untested because AI Engine's *Drafts Only* stops writes) — `includes/quality.php` (`SGAP_Quality`) sits inside `SGAP_Queue::add()`, the only way into the queue, so the Studio, AI drafts, the REST API and the Claude tools are covered by one check. Four checks: **banned words** from the owner's own list (whole words, hashtags count); **dead links** — internal links resolved against WordPress itself, so a missing slug or a not-yet-published post is caught, while archives/feeds/search are recognised and left alone; **missing alt text** on the featured image; **near-duplicate captions** against the last 40 posts on the same channel, 85 % word overlap, with the colliding post named. Three modes (block/warn/off, default block) and a `force` escape hatch that queues the post with the findings as warnings. Captions under eight words are never called duplicates. No outbound HTTP: external hosts are not probed (documented in the README, with the reason). 36 smoke checks (172 → 208) | Cheap insurance for unattended posting | 1.0 d | — |
| P1.8 | ✅ **Done 2/11 (7.24.0)** — `utm_campaign` is a parameter instead of a constant. `SGAP_UTM::build()` takes `campaign`; empty falls back to the new setting *Campaign name* (`utm_campaign`, default `steamgears_premium_evergreen`, so existing links stay comparable), and `SGAP_Queue::add()` passes a post's own `campaign` through, from REST `/queue` or the `queue-post` ability. The name is `sanitize_key`-ed on the way in, so a campaign cannot smuggle a query string into the link, and it is never empty. A finite series can now be measured as one campaign in GA4 (*Session campaign*) while the evergreen posts keep running on the setting; posts queued earlier keep the value they were queued with. 10 smoke checks (219 → 229) + 3 integration checks | A named campaign could not be told apart from the everyday stream in GA4 | 0.25 d | — |
| P1.14 | ✅ **Done 6/11 (7.39.0)** — the model was a free-text field, so a guessed or retired name could be saved and every AI call afterwards failed. It is now a **dropdown of what the provider actually lists**: `SGAP_AI::catalog()` reads `/models` (AIMLAPI needs no key, Anthropic and a custom OpenAI-compatible endpoint use the saved one), `catalog_ids()` dedupes and sorts the ids (AIMLAPI repeats them), and the list is cached 12 hours — a failure only 30 minutes — so the settings page costs at most one request per half-day. `SGAP_Setup::model_field()` renders the ids in per-vendor `optgroup`s with *Provider default* first, keeps a stored model that is no longer offered visible as *(not offered any more)* instead of silently changing it, and falls back to a plain text field when no list can be read, so an unreachable provider never blocks the rest of the form. `SGAP_Settings::sanitize_model()` checks again on save: a name the provider does not list is refused, the stored value stays and the owner gets a settings error — so neither the form nor a crafted POST can store an unavailable model. A site behind a proxy can replace the list with the `sgap_ai_model_catalog` filter. Nine smoke checks: dedupe/sort/grouping, the dropdown with the default first, the retired value stays visible, a listed model saves, an unlisted one is refused with an error and the old value kept, empty still means the default, the settings page renders the select, and without a catalogue it degrades to free text | A saved model the provider does not offer broke every AI call, and nothing in the UI could tell you which names exist | 0.3 d | — |
| P1.12 | ✅ **Done 6/11 (7.38.0)** — there was no way to see what a site actually exposes without reading code. Two MCP tools and one admin screen close that: **ability-index** groups every registered ability by category with a count, how many only read, and the names; **search-abilities** matches name, label, description and category, optionally inside one category, and reports how many matched so a caller knows whether to narrow. Both are pure helpers (`index_abilities()`, `search_abilities()`) behind thin tool wrappers, both marked read-only, and they become chat tools automatically like every other read-only ability. The new **Tools** screen (`sgap-tools`, next to AI Chat) renders the same catalogue with a sticky search bar (`/` focuses, `Esc` clears), category chips with counts, live *n of m* counter and cards carrying read/write, bound and blocked-in-chat badges — 445 checks cover the grouping, the search, the limit, both tool wrappers and the page | You could not tell what the site exposes, or in which category, without grepping | 0.75 d | — |
| P1.11 | ✅ **Done 6/11 (7.37.0)** — the chat only saw this plugin's two categories, so every ability WordPress core, WooCommerce or another plugin registers was invisible to it. `SGAP_Chat::available()` now normalises **every** registered ability (name, short name, category, description, readonly flag from its own `annotations`), `tools()` binds its own plus the read-only MCP tools plus every other read-only ability up to `MAX_BOUND = 80`, and three meta tools cover the rest: **find-tools** (keyword search across the whole registry, returns `total_available`), **describe-tool** (schema by full or short name) and **run-tool** (runs any ability by name, still refusing the chat's exclusions). A 300-tool site therefore stays within a sane request size while nothing is hidden. Name collisions keep both entries (full name wins), and the chat header now reads *N of M tools* with M the real site total, so the count is visible instead of assumed. Nine smoke checks: foreign read-only abilities bound, foreign writers not bound, meta tools always present, find-tools reaches the catalogue and hides exclusions, describe-tool resolves a short name, run-tool runs an unbound ability and refuses an excluded one, and the catalogue counts per category | The assistant could not see the abilities the rest of the site exposes | 0.5 d | — |
| P1.10 | ✅ **Done 6/11 (7.36.0)** — the chat's tool list was fixed for the life of a page and there was no supported way to add a tool from outside the plugin. Three things now cover that: **(1)** `add_filter('sgap_chat_tools', …)` lets another plugin add or rename tools, and every entry is normalised (name, description, an object schema; anything malformed is dropped) so the model is never handed something it cannot call; **(2)** `tools_signature()` (md5 of the sorted names) and `tool_catalog()` report count, names and signature, and `wp_ajax_sgap_chat_tools` serves them behind the same capability and nonce as the chat; **(3)** the chat header has a *Refresh tools* button that re-reads the catalogue and says how many tools were added, so a plugin update that registers new abilities shows up without a reload. A tool without a runner now answers with a clear error instead of a fatal. Four smoke checks: the filter adds a tool, the signature follows the catalogue, the catalogue reports all three fields, and a runner-less tool fails cleanly | New abilities would only reach the chat after a page reload, and nothing outside the plugin could add one | 0.25 d | — |
| P1.9 | ✅ **Done 6/11 (7.35.0)** — the admin chat could only use the plugin's own abilities, so it could queue and publish but could not *read* the site: no posts, products, plugins, cron or options. `SGAP_MCP_Tools::readonly_names()` exposes the read-only half of the MCP catalogue (13 tools: `site-info`, `get-option`, `list-plugins`, `list-themes`, `list-cron`, `list-posts`, `get-post`, `list-media`, `list-products`, `get-product`, `indexnow-status`, `sitemap-audit`, `list-abilities`) and `SGAP_Chat::tools()` merges them with its own after the exclusion list. Everything that writes (`create-post`, `update-*`, `trash-post`, `set-plugin-state`, `run-cron`, `upload-media`, `indexnow-submit`, `undo`, `call-ability`) stays on the MCP surface, where dry_run/confirm and the undo token live, and the assistant is told to describe a change and let the owner apply it. Name collisions between the two catalogues are skipped, and six smoke checks pin the boundary (read set complete, no writer in it, no writer in the chat, every schema an object) | The chat was blind to the half of the plugin an MCP client can already see | 0.25 d | — |
| P1.7 | ✅ **Done 2/11 (7.19.0)** — whole-site SEO/GEO/AEO audit, `includes/site-seo.php` (`SGAP_Site_SEO`). One pass scores every published post, page and product against 20 rules in three groups — **SEO** (title/meta length, missing meta, focus keyword, one H1, text length per type, internal links, alt text, featured image, slug, duplicate title/description), **AEO** (question headings, FAQ + `FAQPage` schema, answer-first paragraph, lists/tables) and **GEO** (named entity, quotable numbers, structured data, outbound citations, freshness) — plus four whole-domain checks (llms.txt, sitemap, robots.txt, product feed). `fix()` writes the missing Rank Math/Yoast meta for the weakest URLs, 8 calls per round, and never touches body text. Result lives in one option and is refreshed lazily weekly because WP-Cron cannot be trusted on this host. Admin card in Content Studio, ability `optimize-site-seo`, 16 smoke checks (112 → 128). Replaces the per-page loop that made a site-wide pass 40 clicks | The plugin could only optimise one page at a time, so in practice nobody did it | 1.0 d | — |
| P1.15 | ✅ **Done 7/11 (7.44.0)** — the Novamira access token lives 3600 s and its refresh token is **rotated** on every renewal, so keeping the MCP surface alive needed either repository secrets or a loop in a sandbox that dies when it is archived. `includes/mcp-auth.php` (`SGAP_MCP_Auth`) moves it onto the site: `sgap_mcp_refresh` runs every 30 minutes, renews with the saved refresh token, proves the new token with `initialize` + `Mcp-Session-Id` + `tools/list`, and writes the rotated refresh token back before anything else. The client ID is an ordinary setting and the refresh token is a secret (autoload off); a token set in `wp-config.php` is refused because it cannot be rotated. A failed renewal keeps the API's own reason (`invalid_grant`, `unsupported_grant_type`, an empty `tools/list`), shows it through the read-only `mcp-status` tool and raises it in the daily self-check, and `mcp-refresh` renews on demand. 20 smoke checks (564 → 584 PASS) | 30 days of MCP access without a secret or a running sandbox | 0.5 d | — |
| P1.16 | ✅ **Done 7/11 (7.45.0)** — the WooCommerce/Printful bridge existed only as a separate, unfinished file: a `/tools` REST route that was not MCP, a token renewal against an endpoint that answers **404**, a default model AIMLAPI no longer offers, and a "margin floor" computed as 40 % of the very price it was meant to protect. The useful parts are consolidated into **one module inside this plugin**, `includes/mcp-woo.php`, in the `steamgears-tools` category so the site's MCP server exposes them like every other tool: `woo-catalog`, `woo-propose`, `woo-queue`, `woo-apply` and `woo-discard`. The cost is never invented — `_sgap_cost` first, then the Printful sync variant's own price matched on the Woo id or SKU, otherwise the call is refused and names both fixes. The floor is `floor(cost × (1 + margin)) + 0.95`, raised by one when it would land under the margin, and it is recomputed at apply time. No token layer: the site's MCP authentication already guards the tools, and the Printful token is a secret with autoload off. 43 smoke checks (584 → 627 PASS) | The shop can be optimised through MCP without a second plugin, a dead credential or a margin that cannot fail | 0.5 d | — |
| P1.17 | ✅ **Done 7/11 (7.46.0)** — `_sgap_cost` is the only cost the shop tools trust, and it started empty, so every proposal was refused by design. `woo-cost-import` reads the Printful sync variants (paginated, cached six hours, `fresh` bypasses the cache) and writes the variant's own price as the product's cost, matched on the WooCommerce id or the SKU — never on the name. It is a dry run unless `confirm` is true, keeps a cost you already set unless `overwrite` is true, reports a variant without its own price instead of guessing, and gives a variable product the lowest of its variations. The written source is stored next to it, so `woo-catalog` can say where a cost came from. | The shop tools are useless without a cost, and typing 200 costs by hand is how a wrong one survives | 0.3 d | — |
| P1.18 | ✅ **Done 7/11 (7.47.0)** — a key belonged to whichever tab happened to list it: the AI key under *AI & images*, the Publer key under *Publishing*, the feed secret under *Shop & feeds*, the MCP token under *MCP access*, and three service credentials (`bluesky_handle`, `mastodon_instance`, `telegram_chat_id`) were on no tab at all. Now **one** screen owns every secret: `includes/keys.php` (`SGAP_Keys`) registers each service once — title, icon, purpose, where to get it, optional test — and renders it as a card, the pattern WordPress 7.x uses for *Settings → Connectors*. The Keys tab is first, and `settings_page()` strips anything in `SGAP_Settings::SECRET_KEYS` from every other panel, so a key listed on the wrong tab later still shows up in exactly one place. The setup guide asks for the AI key only while none is saved, and otherwise links to Keys. The look is tightened in the same pass: sticky capsule tab bar, sticky save bar with an *Unsaved changes* pill tracked by JS, softer cards, 40 px fields with a visible focus ring, and a one-column layout under 782 px — the plugin's own stylesheet, no build step, no `@wordpress/ui`. 19 smoke checks (643 → **662 PASS**, 0 FAIL): card coverage equals `SECRET_KEYS` both ways, one field (or one wp-config note) per key, no stored value in the HTML, the Mastodon card links to the saved instance, the guide stops asking, and the CSS carries the new parts. | Keys scattered over five tabs is how a plugin ends up with a credential nobody can find; one screen, one card per service, and never a value echoed back | 0.5 d | — |
| P1.19 | ✅ **Done 7/11 (7.52.0)** — dashboardet læses nu oppefra i tre sektioner: **Now** (Run now, AI Chat, Self-check, Scheduler), **Channels** (tal pr. kanal, engagement, RSS-feeds, API-nøgler) og **System** (plugin-opdatering, Command Center, billed-rescue). Før lå to kort i venstre kolonne og otte i højre, så den ene side var tre skærmhøjder lang og den anden næsten tom. Øverst står **én** linje — *Needs you* — med kun det et menneske skal gøre: en AI-nøgle der dumpede sin test, problemer fra self-check, blokeret publicering eller kladder der venter. Er der intet, står der *All clear* med hvad der blev tjekket, i stedet for grønne pills spredt over hele siden. Engagement-kortet viser likes/reposts/replies som tre tal over den per-kanal-tabel, og to helpers (`SGAP_UI::section()`, `SGAP_UI::metric()`) bærer mønstret videre til de næste sider. Stylesheet'et fik sektions-overskrifter med en linje der toner ud, metrik-blokke og `forced-colors`-støtte (Windows high contrast) til kort, pills, tabs og progress-bars — stadig uden build-step. Otte nye smoke-checks holder rækkefølgen fast (706 → **714 PASS**) | Et dashboard hvor man ikke kan se hvad der er galt, eller hvor man skal trykke, bliver læst som en liste af bokse | 0,25 d | — |
| P1.20 | ✅ **Done 7/11 (7.54.0)** — mønstret fra dashboardet er nu på de tre sider der stadig var en stak felter. **Settings:** hver fane åbner med en overskrift og én linje om hvad den afgør (fx »Hvad autopiloten må gøre af sig selv, hvor den poster, og hvor mange sider den fremmer om dagen«), så ingen fane starter med et navnløst felt; `SGAP_Setup::TAB_HINTS` dækker præcis de ni faner, og Keys har sin egen overskrift. **Queue:** én tabel med en statuskolonne blev to spørgsmål — *Waiting* (queued, sending, reminded, pending, approved) og *Done* (published, failed) — hver med sin tabel og en optælling i overskriften, plus en *Held*-linje øverst der oversætter quiet hours, blackout, dagligt loft og slukket autopilot til ord. **Content Studio:** *Write* (artikel, guide, AI-billede) og *Fix* (meta for én side, hele sitet i ét gennemløb, og hvad scoren måler), med resultatboksene under hver sektion. 11 nye smoke-checks (770 → **781 PASS**) | En indstilling man ikke kan finde, og en kø man ikke kan se forskellen på »venter« og »sendt« i, bliver læst som om der ikke er noget at gøre | 0,25 d | — |
| P1.21 | ✅ **Done 7/11 (7.55.0)** — de sidste to sider var stadig én lang liste. **AI Drafts:** kladderne er nu grupperet efter den **side** de er skrevet til (det er den beslutning man tager — »er denne sides posts gode nok?«), med en optælling pr. gruppe og tre tal øverst: kladder, sider, kanaler. En kladde uden kilde-side får sin egen gruppe i stedet for at forsvinde, og de tre knapper pr. kladde er uændrede. **Publish log:** filteret står først som sin egen sektion med levetiden i forklaringen, derefter *Attempts* med **Sent · Failed · Requeued or held** talt op fra de rækker der faktisk vises (»on this page«), så tallene altid beskriver tabellen under dem. Retentionsnoten er flyttet ind i sektions-forklaringen i stedet for at være en løs bjælke. Syv nye smoke-checks (781 → **788 PASS**) | En liste man skal læse fra top til bund for at finde ud af hvad der kræver en beslutning, bliver læst som om der ikke er noget at beslutte | 0,25 d | — |
| P1.22 | ✅ **Done 7/11 (7.57.0)** — **fejlsøgning inde i pluginnet.** Steamgears → Fejlsøgning har ni tjek der læses ud af den tilstand modulerne allerede holder (PHP, tabeller, planlagte job, fejlede posts, AI-nøgle, Pinterest, Instagram, MCP, modulfejl), hver med en sætning om hvad der blev fundet og hvad man gør — også når det gik godt. En kanal der aldrig er forbundet er `off`, ikke `bad`. Ved siden af ligger `sgap_error_log`, en ringbuffer på 40 rækker: modulerne holder kun **én** fejltekst og rydder den ved næste kørsel, så et natligt afslag var væk inden nogen læste det — loggen beholder det, tæller gentagelser i stedet for at duplikere, og skiller visnings-`at` fra regne-`ts`. **Forbeholdet følger tallene:** seks tekster siger hvad tallene ikke indeholder, og de står både under modulets dashboardkort og samlet på siden. 67 nye smoke-checks (838 → **905**). | Et tal uden sin begrænsning bliver læst som en påstand, og en fejl der blev rettet af næste kørsel er usynlig | 0,5 d | — |
| P1.23 | ✅ **Done 9/11 (7.67.0)** — **OpenRouter som egen tekst-provider.** Pluginnet kunne allerede tale OpenRouter gennem *Other OpenAI-compatible*, men kun hvis ejeren selv skrev `https://openrouter.ai/api/v1` og et modelnavn — og nøgletesten spurgte `/models`, som OpenRouter svarer **uden** nøgle, så enhver nøgle blev kaldt gyldig. Nu er `openrouter` et valg for sig: adressen er fast (`SGAP_AI::OPENROUTER_URL`) ligesom AIMLAPI's, så en tastefejl i *API base URL* ikke kan sende nøglen til en anden tjeneste; `SGAP_KeyCheck::check_openrouter()` beviser nøglen med **ét token** mod den valgte model og skelner afvist nøgle (401/403), tom konto (402), ukendt model (404/400) og rate limit (429); og der er **bevidst ingen standardmodel**, fordi OpenRouters katalog ændrer sig — modellen vælges fra deres live `/models`, og et tomt felt svarer med providerens navn i stedet for at gætte. Nøglen ligger samme sted som de andre (Settings → Keys), kataloget caches under sin egen nøgle og ryddes ved rotation. 9 nye smoke-checks (1277 → **1286 PASS**, 0 FAIL). **Forbehold:** ingen nøgle findes i sandkassen, så hvert kald i testene er mocket — den rigtige tjeneste er ikke prøvet herfra, og en model der afviser JSON-mode (`response_format`) vil svare 400 indtil en anden model vælges. | AIMLAPI-kontoen løb tør for kredit 7/11, og den eneste alternativ-vej krævede håndskreven URL og gav et falsk »nøglen virker« | 0,25 d | — |
| P1.24 | ✅ **Done 10/11 (7.67.1)** — **OpenRouter som automatisk backup for AIMLAPI.** P1.23 gjorde OpenRouter til et *valg*; dette er det andet: når den valgte udbyder fejler, skal autopiloten ikke stoppe, indtil nogen skifter provider i hånden — og AIMLAPI-kontoen løb tør for kredit 7/11. Backuppen har **sin egen nøgle** (`openrouter_api_key`) og **sit eget model-felt** (`openrouter_model`, valgt fra OpenRouter's live liste), fordi `ai_api_key` er »én nøgle ad gangen« for den valgte udbyder. Kun **udbyderfejl** starter den — transport, 401/403, 402, 429, 5xx — mens et 2xx-svar (også tomt eller afkortet) aldrig gør, så et svar der kom ikke koster et ekstra kald; 400/404/422 er vores egen fejl og rapporteres. Et modelnavn primæren afviser prøves stadig først på primærens standardmodel. `SGAP_AI::chat_completions()` er nu den **eneste** vej til `/chat/completions`: generatoren, Content Studio og AI Chat går alle gennem den, backup-bodyen er den samme minus `reasoning_effort` (som kun AIMLAPI kender), og nøgletesten prøver backup-**parret**, ikke primærens model. Hver failover skriver én række i fejlloggen (`ai-fallback`) med status og model — aldrig nøgle eller request — og chat-svaret slutter med en sætning om at primæren fejlede. Backuppen er slået fra når primæren *er* OpenRouter. 30 nye smoke-checks (1286 → **1316 PASS**, 0 FAIL). **Forbehold:** intet kald er gået til den rigtige OpenRouter — der findes ingen nøgle i sandkassen — så et live-failover er ikke målt. | En tom AIMLAPI-konto standsede hver generering, artikel og chat-svar indtil nogen skiftede udbyder i hånden | 0,35 d | — |
| P1.25 | ✅ **Done 10/11 (7.69.1)** — **Nøgletesten siger hvad der er galt med nøglen.** Målt 10/11 svarede OpenRouter `401 Missing Authentication header` på nøgletesten, og beskeden blev vist som »OpenRouter refused the key« — altså som om nøglen var afvist, når sandheden var at den gemte værdi slet ikke var en OpenRouter-nøgle. Nu tjekkes **formen før der bruges en request**: OpenRouter-nøgler starter med `sk-or-v1-`, og en værdi der ikke gør, navngives med sin længde og en henvisning til openrouter.ai/keys. Ser nøglen rigtig ud og svaret stadig handler om en manglende header, siger beskeden det og peger på en linjeskift i feltet eller en vært/proxy der fjerner headeren, i stedet for at påstå at nøglen blev afvist. Alle nøgler **trimmes** nu før de bliver en header (`SGAP_AI::api_key()`), så et linjeskift fra en indsættelse ikke længere kan gøre `Bearer sk-or-…` ulæseligt — det ramte generatoren, Studio, AI Chat og nøgletesten. 5 nye smoke-checks (1353 → **1358 PASS**, 0 FAIL). **7.70.1** går et skridt videre og retter requesten selv: headeren bygges nu ét sted (`SGAP_AI::openrouter_headers()`/`openrouter_post()`) for både nøgletest, failover og generator, og tokenet renses for mellemrum, linjeskift og usynlige tegn (`SGAP_AI::bearer()`), som transporten ellers kasserer headeren på. Svarer OpenRouter stadig »missing header«, kører `SGAP_AI::stripped_by()` sitets eget `http_request_args`-filter på en kopi af requesten og navngiver den plugin-funktion der fjerner headeren. 11 nye checks observerer den udgående request (Bearer-header, én linje, ingen nøgle i beskeden); 4 af dem fejler hvis rensningen fjernes. | En 401 om en manglende header blev læst som en afvist nøgle og sendte ejeren på den forkerte jagt | 0,15 d | — |
| **P1 total** | | | **16.30 d** = rækkerne 16,20 + queue-post 0,10 (7.25.0). **Afstemt 7/11:** P1.19–P1.22 (1,25 d) bogføres som egne moduler i `projektstatus.json`, så P1's egen nævner er 14,95 + 0,10 = **15,05 d**, og 15,05 − 14,45 = **0,6 d = P1.5's live-verifikation**. Den er **ejer-blokeret** (målt 8/11: Pinterest-tokenet virker, men appen mangler `boards:read`; 5 af 6 kanaler har intet token) og er derfor flyttet ud af agent-estimatet — P1 står som **14,45 af 14,45 d**. Den gamle total 15,20 talte P1.19's 0,25 med to gange og udelod queue-post-arbejdet | |

### P2 — channels and scope

| # | Item | Why | Effort | Depends on |
| --- | --- | --- | --- | --- |
| P2.1 | ✅ **Done 3/11 (7.29.0)** — `includes/bluesky.php` (`SGAP_Bluesky`) posts to Bluesky directly, without Publer: `com.atproto.server.createSession` (handle + app password) and `com.atproto.repo.createRecord`, both through WordPress' HTTP API, so no SDK and no Composer dependency. Credentials live where the others do (Settings → Publer, the app password as a secret with the same empty-field-keeps-the-old rule), and `bluesky` in the channel list is the on/off switch — remove it or clear the password and the channel falls back to Publer. `SGAP_Publer::tick()` routes the channel through the **same** loop, so a failure gets the same backoff, the same P0.1 alert and the same publish-log row; a success has no Publer job to poll, so the item is marked `published` at once with the AT URI as its external id. The session is cached for 90 minutes and a 401 renews it once and retries the post. Text respects Bluesky's **300 graphemes** (`\X` matches, so an emoji with a modifier is never split in half), the tracking link is reserved before the body is trimmed and sent as a richtext facet so it is clickable, and hashtags become tag facets. Every failure is a WP_Error that names the cause — missing credentials, `invalid_grant` from a refused login, the API's own message on a refused record, or the transport error — never a silent no-op and never a PHP warning. 28 smoke checks with a stubbed `wp_remote_post` (336 PASS in total) | Free, no review, one afternoon — the cheapest new channel available | 1.0 d | — |
| P2.2 | ✅ **Done 3/11 (7.30.0)** — `includes/mastodon.php` (`SGAP_Mastodon`): one call to `POST {instance}/api/v1/statuses` with the token as a Bearer header (an `Idempotency-Key` per queue item, so a retried tick cannot post twice). The instance is part of the credential because a Mastodon token only works on the server that issued it; `https://` is added and trailing slashes dropped. Limit **500 characters** by default (`mastodon_limit` overrides, clamped 100–5000), trimmed grapheme-aware with the tracking link reserved. The API's own `error` text — `The access token is invalid`, a validation message — becomes the WP_Error message and the publish-log row; a 401 is reported, never swallowed | Free, self-hostable, same shape as Bluesky | 0.5 d | — |
| P2.3 | ✅ **Done 3/11 (7.30.0)** — `includes/telegram.php` (`SGAP_Telegram`): one call to `https://api.telegram.org/bot<token>/sendMessage`. Telegram refuses a message with **HTTP 200 and `{"ok": false}`**, so `ok` is checked before anything else and `description` becomes the error — a refused post can never look published. 4096-character limit, grapheme-aware trimming with the link reserved; `preflight.php` now allows the real 4096 instead of 1024, so a valid caption is no longer cut without reason. The bot token stays in the URL and never in an error message. Settings → Publer: bot token (a secret), chat id, and `telegram` in the channel list | Token settings already exist; also gives P0.1 a free second alert channel | 0.5 d | — |
| P2.4 | ✅ **Decided 6/11: hidden, not built (7.33.4)** — the five Etsy keys are unregistered, the section is gone from Settings, and the Etsy page labels itself a manual import with `POST /etsy/orders` as the way in. The 0.25 d is booked **here** (it also sat in P3.1's note, which counted the same afternoon twice), and the 2.5 d OAuth client is out of scope until Etsy volume asks for it. Four smoke checks guard the hidden state | The screen promised an integration that is not there | 0.25 d ✅ (2.5 d build cancelled) | Etsy app review — not needed |
| P2.5 | ✅ **Done 3/11 (7.31.0)** — `includes/locales.php` (`SGAP_Locales`): one source post queues one translated caption per locale. *Source language* (default `en`) and *Languages* (`da,sv,de`, at most six, never the source, junk and duplicates stripped on save) sit under **Settings → AI content**; when the list is non-empty, `SGAP_Queue::add()` also queues a variant per language. The translated page comes from **WPML or Polylang** — `pll_get_post()` first, then WPML's `wpml_object_id` filter — and the variant links to that page; the plugin never guesses a URL, so a post without a translation simply gets no variant. The caption itself is translated by the configured model through `SGAP_AI::json_call()` (WordPress' HTTP API, so the tests stub it), and the `sgap_locale_translator` filter can hand the job to DeepL or a human. Variants are ordinary queue rows — same channel, angle, format, image, quality gate and blackout handling — and carry `locale` plus `source_id`, so a variant never spawns variants of its own. **No failure is silent:** a language with no translated page, no AI key, an API error, a non-JSON answer or an empty caption is reported in the queue result's `locale_errors` while the source post stays queued. 35 smoke checks with stubbed HTTP (364 → 399 PASS) | Nobody in the market does this properly; WPML/Polylang make it feasible | 2.5 d | — |
| P2.6 | ✅ **Done 7/11 (7.58.0)** — `includes/facebook.php` (`SGAP_Facebook`): the page as a channel — publish, insights and moderation. Publishing goes to `/{page}/feed` with the tracked link as `link` (so Facebook builds the preview) or to `/{page}/photos` with the link in the caption, because `link` is ignored on a photo post. **Direct publishing is opt-in** (`facebook_direct`, default `no`): a page connected only to be read must not move publishing out of Publer on its own, so `handles()` stays false until the owner says otherwise. One Page access token writes as the page; a user token cannot, and error 190 says so instead of forwarding Meta's wording. Insights read `post_impressions`, `post_impressions_unique`, `post_reactions_by_type_total` (an object, summed) and `post_clicks`; a metric Meta has not reported is left out rather than stored as 0 and the post is asked again next run, and a refused metric set is retried with the two every post has. Comments are read, answered and hidden — never deleted — with `can_hide` from Meta deciding whether the button is offered. Stores are scoped `channel = 'facebook'` so `likes`/`shares` from other channels survive; Graph version is a setting, not a constant. Own admin screen, dashboard card, REST routes, cron in the diagnostics list and its own caveat. 82 smoke checks, 905 → 987 PASS | A page the plugin cannot reach is a page somebody moderates by hand at midnight | 1.5 d | — |
| **P2 total** | | | **4,75 d** = P2.1 1,0 + P2.2 0,5 + P2.3 0,5 + P2.4 0,25 + P2.5 2,5. P2.6 (1,5 d) bogføres som **eget modul** i `projektstatus.json`, så rækkerne summer til 6,25 d, men `P2`-modulet er 4,75. Totalen her sagde 6,25 og talte P2.6 med to gange (rettet 8/11 2026) | |

Not recommended: direct X posting ($0.20 per post with a URL makes link posts economically pointless),
direct LinkedIn (vetted tier, closed permission), direct Google Business Profile (quota 0 until approved).
Threads and LinkedIn stay behind Publer.

### P3 — hygiene and proof

| # | Item | Effort |
| --- | --- | --- |
| P3.1 | ✅ **Done (7.33.3 + 7.59.0)** — the dead method `get_setting_help_link()` is deleted and nine invisible leftovers are gone from `SECRET_KEYS`/`TEXT_KEYS`. The Etsy half (7.33.4) is booked under **P2.4**, where the roadmap estimated it, so the same afternoon is not counted twice. **`labor_rate` (the last 0.05 d) is now read (7.59.0):** section 8's third ending — “rename it to what it is today” — is the one that needed no invented number, so the ROI card computes `SGAP_ROI::hours()` and shows *Labour equivalent: X h at N/h (budget ≈ Y h)*. Five smoke checks in `plugins/tests/roi.php` prove the division, the null on a zero rate, and that the card actually reads the setting back. No scope removed, no pricing promise added. | 0.25 d ✅ |
| P3.2 | ✅ **Done 3/11 (7.28.0)** — tests for `/feed` and `/reposts`. `merchant_feed()` printed the XML and called `exit`, so the feed could not be tested at all; it now returns a `WP_HTTP_Response` with `Content-Type: application/xml` (and stops calling `header()` after output). 12 checks cover the secret gate (missing secret, wrong key), the 501 without WooCommerce, the Google namespace, SKU falling back to the product id, price with the store currency, XML-escaped titles, stripped HTML in the description, skipped products without price or image, both availability states and the content type; 5 cover the reposts (list, 400 without ids, numbering, 422 at the cap, the insert); 5 cover the uninstall (table list, drops, options, post meta). The `/snapshot` auth gap is closed too: `integration.php` signs out and asserts **401**, and that the refused call is not counted as a Command Center call. | 0.5 d |
| P3.3 | ✅ **Done as S0.3** — PHP 8.0–8.5 and WordPress 6.4 / 7.1 / latest are in the CI matrix | 0.25 d |
| P3.4 | ✅ **Done 6/11 (7.34.0)** — the “View details”/update modal now shows the three newest rows from this file's changelog instead of a bare GitHub link. `SGAP_Updater::changelog_notes()` is a pure parser (regex on the `\| date \| version \| change \|` table, newest first, `**bold**` and backticked code converted) and `remote_changelog()` fetches `ROADMAP.md` from `main` behind a 6-hour site transient; when GitHub is unreachable the modal falls back to a link rather than an empty tab. Note text is escaped, so nothing in the repo can inject markup. Six smoke-checks cover the parser, the escaping, the limit, the empty case and the modal contract | 0.5 d |
| P3.5 | ✅ **Done 3/11 (7.28.0)** — `uninstall.php` is now checked against the `dbDelta` DDL instead of memory: the test extracts every table `SGAP_Install::install()` creates, asserts the uninstall file declares all nine, then includes `uninstall.php` with a stubbed `$wpdb` and requires a `DROP TABLE IF EXISTS` for each, the option/transient delete and the post-meta cleanup. The file was correct; the check is what was missing | 0.25 d |
| P3.6 | ✅ **Done 7/11 (7.53.1)** — **fejlsøgning er nu en fast kørsel, ikke en lejlighed.** `tools/fejlscan.py` scanner på tværs af filer efter netop de fejl der ikke giver en fejlkode: version tre steder (konstant, header, zip-navn), zip der ikke er byte-identisk med kilden, `require` der peger på en fil der ikke findes eller en fil i `includes/` som ingen kræver, dokumenter i `docs/library/` som indekset ikke nævner (kun nye, jf. AGENTS.md — ældre noter er gæld, ikke fejl), tokens i sporede tekstfiler, `TODO`/`FIXME` i PHP og tests der ikke kan fejle. 13 fixtures i `--selftest` beviser både at hver fejlklasse fanges, og at en ren kasse er grøn, så værnet ikke bare er en mur der altid er rød; det er **værn nr. 23 og 24** i `tools/natlige-vaern.sh`. Første kørsel gav fem reelle fund, alle rettet her: (1) et Pinterest-token i `wp-config.php` blev brugt til at hente data, men fornyelsen skrev til en option som konstanten altid overskyggede — nu afvises fornyelsen med konstantens navn, og `token()` bruger konstanten som den er; (2) et indklistret token uden registreret udløb blev rapporteret som *ugyldigt* i `status`, så dashboardet bad om en ny nøgle til en nøgle der virkede; (3) et afslag på pin-listen (manglende `boards:read`, 429, nedbrud) stoppede hele kørslen, selv om de allerede kendte pins kunne måles — nu måles de, og fejlen rapporteres ved siden af; (4) `gmdate()` blev brugt som vindue mod rækker der skrives med `current_time('mysql')`, dvs. to ure med op til 14 timers forskel — nu `wp_date()` som resten af pluginnet; (5) engagement-checken `summary()==[] \|\| true` kunne aldrig fejle og læste samtidig den forrige tests kø-rækker som snapshot-kolonner — den er nu en rigtig assertion på et tømt datasæt. Samme pas rettede P0- og P1-totalerne i §5, som var 0,70 d og 1,90 d bagud i forhold til `projektstatus.json` | 0.25 d |
| P3.7 | ✅ **Done 8/11 (7.65.2) — kode og UI: 41,8 KB mindre HTML pr. admin-side, og intet CSS i `<head>`.** Stylesheetet blev foer printet inline paa hver plugin-side (**40 452 tegn**) plus menu-CSS/JS paa hver admin-side og et submit-script pr. side. Nu ligger fire filer i `assets/` (`admin.css`, `admin.js`, `menu.css`, `menu.js`) og enqueues af `SGAP_Assets`: menu-assets overalt (menuen er synlig overalt), side-assets kun paa pluginets egne skaerme (`is_plugin_screen()`, ren funktion), begge kun for `manage_options`. Versionen er filens mtime, saa en CSS-rettelse ikke kan serveres fra cachen. UI-rettelser i samme fil: synligt `:focus-visible` paa alle kontroller, tabeller der kan scrolles under 782 px, ét-kolonne-grid og reduceret padding paa mobil, `prefers-reduced-motion` slukker animationer, og tal i stat-tiles/feed staar i tabular-nums. **36 nye smoke-checks** i `plugins/tests/assets.php` (filer, indhold, skaermgenkendelse, enqueue-kontrakt, loft paa 12 inline side-scripts) — suiten gaar **1172 PASS / 0 FAIL**. | 0,60 d |
| P3.8 | ✅ **Done 9/11 (7.65.4) — de sidste inline side-scripts er filer, og loftet på 12 er væk.** Efter P3.7 stod der stadig femten `<script>`-blokke i ni includes: hver side byggede sin egen JS med `wp_json_encode($cfg)` indeni, så HTML'en voksede pr. request og koden hverken kunne caches eller syntakstjekkes. Nu ligger syv filer i `assets/pages/` (`dashboard.js`, `studio.js`, `seo.js`, `chat.js`, `tools.js`, `niche.js`, `setup.js`), enqueuet af `SGAP_Assets::page_script()` med mtime som version, og den konfiguration der skal beregnes pr. request (nonces, chat-historikken, værktøjskataloget) sendes med `wp_localize_script` som ét objekt. JS-teksten er udtrukket mekanisk fra PHP-literalene og er byte-identisk med det der blev printet før (kontrolleret mod HEAD for alle syv filer) og `node --check`-ren. Det ene `<script>` tilbage i `includes/` er JSON-LD, som er data. 20 nye smoke-checks (**1192 PASS / 0 FAIL**). | 0.30 d |
| P3.9 | ✅ **Done 9/11 (live, ikke kun i repoet) — 0,25 d.** Sitet kørte 7.65.0 mens repoet var 7.65.4, så P3.7's og P3.8's besparelser var bygget men ikke i drift. Zip 398 668 bytes uploadet i to base64-dele gennem MCP, installeret med `Plugin_Upgrader::install($zip, ['overwrite_package'=>true])`: **7.65.0 → 7.65.4 → 7.65.5**, stadig aktiv, `opcache_reset()` og `w3tc_flush_all()` kørt. Målt live: dashboard **17 566 bytes / 0 inline `<script>` / 0 inline `<style>`**, 7 side-scripts, 2 CSS-filer; `/` 75 108 B, `/shop/` 269 496 B, produktside 282 822 B og `/cart/` 378 701 B svarer alle **200**; `/wp-json/sgap/v1/tick` **200** | 0.25 d |
| **P3 total** | | **2.90 d** — P3.1 0,25 + P3.2 0,50 + P3.3 0,25 + P3.4 0,50 + P3.5 0,25 + P3.6 0,25 + P3.7 0,60 + P3.8 0,30 + P3.9 0,25 (eget modul `p3live` i `projektstatus.json`). **Afstemt 7/11:** totalen sagde 2,75, men rækkerne summer til 2,00; de 0,75 d blev aldrig fjernet da P3.1's Etsy-halvdel flyttede til P2.4 og P3.6 blev sat ned til 0,25, og der har aldrig været en P3-række bag dem (`git log -S "P3.7"` er tom). Både `done` og `total` er 2,60 i `projektstatus.json` (P3.7 lagt til 8/11) |

### P4 — niche shop (7.64.3)

| # | Item | Effort |
| --- | --- | --- |
| P4.1 | ✅ **Done 8/11 (7.64.3) — one field, one shop, measured on the live site.** `includes/niche-shop.php` (plan motor, apply, rollback, admin page) plus four abilities and three REST routes. The planner is pure PHP: ten archetypes with keywords, collections, motifs and tone; six POD families with default costs; prices through `SGAP_MCP_Woo::floor_price()` so the margin floor in *Shop & feeds* is obeyed; five page drafts where every owner-only fact is written as `[EJER: …]`; and a menu that becomes real links (a `wp_navigation` post on a block theme, a nav menu elsewhere). Apply creates **drafts only** (product categories, products with price/SKU/`_sgap_niche`, pages, navigation), records every id under a plan key in `sgap_niche_runs`, and refuses without WooCommerce instead of half-writing. Rollback deletes only that run's drafts, empty terms and menu and keeps published items, reported line by line. **Live run on steamgears.eu** (WordPress 7.1.4, WooCommerce 11.2.2, plugin 7.64.3, files SHA-1-identical to source): `POST /sgap/v1/niche` with "cottagecore mushroom", 8 products → plan key `26f9ebdd1c39`, collections 210–214, product drafts 1517–1524 with `_price`/`_regular_price`/SKU/`_sgap_niche` (32.95, 70.95, 20.95, 23.95, 22.95, 24.95, 32.95, 70.95), page 1525 and navigation 1526 with 8 links; the shop's 20 published products and its 32 categories/32 pages were untouched, drafts stayed 404. Rollback through the admin button deleted all 15 rows (`kept: []`) and left a state identical to the pre-run one field by field. The admin page was verified in a browser (plan built over `admin-ajax.php` 200, already-applied notice, rollback, no JS errors from our code); that pass found and fixed the missing menu (7.64.1), a "1 pages" plural (7.64.2) and a `TypeError` on an already-applied plan that left the button stuck (7.64.3). 36 smoke checks in `plugins/tests/niche-shop.php`; the suite is at **1 128 PASS / 0 FAIL** on 7.64.9, `php -l` clean, zip byte-identical. See `docs/library/niche-butik-2026-11-08.md`. | 1.00 d |
| **P4 total** | | **1.00 d** — done and measured |

### P5 — forum og Reddit som trafikaccelerator (7.66.0)

**Formålet er ikke "et forum".** Et tomt forum er en tom side. Modulet bygges som en trafikmaskine med tre
led, og hvert led kan måles: (1) **forummet** giver hver diskussion en indekserbar side med `DiscussionForumPosting`,
som søgemaskiner og AI-svar kan citere; (2) **Reddit** er den eneste kanal hvor en lille butik kan låne andres
publikum uden annoncebudget — hver publiceret tråd kan sendes som et **link post** til et emne-relevant subreddit
med UTM, så besøget kan føres tilbage til den tråd der sendte det; (3) **radaren** finder de tråde hvor nogen
allerede spørger om noget vi sælger, så ejeren kan svare som menneske i stedet for at spamme.

Beslutninger, der styrer koden:

- **Emner er en CPT (`sgap_topic`), svar er WordPress-kommentarer.** Native kommentarer giver moderation,
  spamfilter, trådning og `comment`-arrays i JSON-LD uden en egen svar-tabel. Tavler (`sgap_board`) er en
  taksonomi, så en tavle kan mappes til et subreddit uden at røre koden.
- **Kun link posts, aldrig automatiske kommentarer på Reddit.** Automatisk selvpromovering i andres tråde er
  spam efter Reddits egne regler og koster kontoen. Radaren **læser** og foreslår; den skriver intet.
- **Reddit-køen er egen tabel (`sgap_reddit_posts`)** og ikke `sgap_queue`: Publer kender ikke Reddit, og et
  link post har andre felter (subreddit, reddit-id, permalink) end en social post. Køen har backoff 5 → 15 → 45
  min, dagligt loft, minimumsinterval og dublet-værn på emne-id.
- **Attribution er UTM + `/go/`.** Linket der sendes til Reddit er `?utm_source=reddit&utm_medium=forum&utm_campaign=<tavle>`,
  og trådens produkttips peger på butikkens `/go/<id>/`-links, så en Reddit-læser lander på et produkt og ikke
  på en blindgyde. Uden det er "trafik" et tal uden kilde.
- **Tokenet er en script-app (refresh token).** Læsning (radaren) kan køre med `client_credentials` uden
  brugerkontekst; publicering kræver en bruger og dermed app-id, secret, brugernavn, adgangskode og det
  refresh token Reddit roterer. Alle fem er secrets og vises aldrig igen.
- **Forummet er ikke åbent for gæster.** Læsning er offentlig; skrivning kræver login, nonce, honeypot og
  3 emner/time pr. bruger, og alt fra ikke-moderatorer lander som `pending`. Et åbent forum på et cachet site
  bliver en spam-magnet inden for en uge.

| Trin | Indhold | Estimat | Akkumuleret |
| --- | --- | --- | --- |
| F1 | Forum-kerne: CPT + tavle-taksonomi, `[steamgears_forum]`, trådside, nyt-emne-formular med værn, kommentar-svar, moderation, `DiscussionForumPosting`, produkttips | 1,20 d | **40,0 %** |
| F2 | Reddit-klient: OAuth (refresh/password/client_credentials), token-cache, `/api/submit`, kø med backoff, loft og minimumsinterval, status og fejltekst | 0,80 d | **66,7 %** |
| F3 | Radar: hot/new pr. subreddit, nøgleord, set-ringbuffer, scoring, cache; UTM på delte links | 0,30 d | **76,7 %** |
| F4 | Admin-siden *Forum & Reddit*, indstillinger (ny fane), REST-ruter, menupunkt | 0,30 d | **86,7 %** |
| F5 | Tests: smoke-fixtures for forum, kø, token, radar og værn; zip; dokumentation + PDF | 0,30 d | **96,7 %** |
| F6 | Live: zip installeret, Reddit-app oprettet, første link post målt | 0,10 d | **100 %** — ejer-blokeret |

**Filstruktur (komplet):**

```
plugins/steamgears-autopilot/
  includes/forum.php          NY   SGAP_Forum — CPT, tavler, shortcode, formular, JSON-LD, admin-side
  includes/reddit.php         NY   SGAP_Reddit — OAuth, submit, kø, radar, status, REST
  assets/forum.css            NY   frontend-styling, kun på forum-sider
  includes/core.php           ÆND table-DDL + settings (DEFAULTS, INT/BOOL/TEXT/SECRET, SECTIONS, HELP)
  includes/setup.php          ÆND fane "Forum & Reddit", labels og tab-hint
  includes/admin-menu.php     ÆND siden i Publish-gruppen
  uninstall.php               ÆND tabel, options, post meta, cron
  steamgears-autopilot.php    ÆND requires, init-kald, version 7.66.0
plugins/tests/smoke.php       ÆND stubs + require af forum-testen
plugins/tests/forum.php       NY   40+ checks
```

**Hvad der ikke bygges, og hvorfor:** automatiske Reddit-kommentarer (spam, kontodød), egen login-udbyder
(WordPress har brugerne), tråd-afstemning og badges (ingen trafikværdi før der er brugere), og Reddit-login på
sitet (kræver en Reddit-app til OAuth og giver en konto vi ikke kan moderere).

---

### P6 — kampagne-agent med review-gate (7.68.0)

**Formålet er en flertrins-agent, ikke en ekstra chatprofil.** Ejeren vælger et publiceret produkt/indlæg, skriver kampagnens mål og vælger kanaler. Agenten læser kildesiden, laver kanaltilpassede opslag, kører hvert forslag gennem kvalitetstjek + kanal-preflight og gemmer dem **altid som `pending` AI-kl adder**. `Full Auto` ignoreres med vilje. Agenten skriver aldrig direkte i publiceringskøen og kalder aldrig publisheren; et menneske kan læse hvert forslag på *Steamgears → AI Drafts* og vælge *Approve & Queue*, *Approve Only* eller *Reject*. Gate-advarsler gemmes med kladden, vises ved review og kvalitetsgaten køres igen ved godkendelse.

| Trin | Indhold | Estimat | Akkumuleret |
| --- | --- | --- | --- |
| A | Kampagnebrief i AI-prompten; kildens fakta forbliver eneste faktakilde | 0,10 d | **11,1 %** |
| B | Agent: valider kilde/brief/kanaler, stop gentagne pending drafts, generér, gate-test pr. kanal, gem kun pending; tests for stopklodser | 0,35 d | **50,0 %** |
| C | Admin-handler + dashboard-formular, nonce/capability, tydelig review-status; GitHub-link på dashboardet | 0,15 d | **66,7 %** |
| D | Admin-only WordPress ability til MCP/AI Chat; returnerer draft-id'er og gate-advarsler, aldrig queue-id | 0,10 d | **77,8 %** |
| E | README/changelog, 30+ smoke-checks, PHP lint og installationszip byte-identisk med kildefilerne | 0,20 d | **100 %** |

**Filstruktur (komplet):** `includes/campaign-agent.php` ny (kørsel, validering, pending-save, per-channel gates, admin form/handler); `includes/generator.php` ændr (valgfrit kampagnebrief); `includes/core.php` ændr (DB-kolonne til gate-advarsler, dashboardkort, review-visning); `includes/abilities.php` ændr (MCP ability); `includes/updater.php` ændr (repository-link); `steamgears-autopilot.php` ændr (require/init, version 7.68.0); plugin-README ændr (scope, review gate, GitHub); ROADMAP ændr (P6/changelog); `plugins/tests/campaign-agent.php` ny + `plugins/tests/smoke.php` ændr (stubs/load); erstat den ene zip `plugins/steamgears-autopilot-v7.67.0.zip`; opdater `projektstatus.json` og `docs/samtalepunkter.md`.

**Afgrænsning:** ingen auto-publicering, schedule/cron-agent, live-produktredigering, ny provider/credential eller gæt uden publiceret kilde; eksisterende Full Auto ændres ikke. Én agent, ét reviewbart kampagne-run.

---

### P7 — feed health: RSS-tilstanden kan måles (7.69.0)

**Hullet står i §4.1 pkt. 2, skrevet 1/11 og aldrig lukket:** »We hold no tokens in RSS mode (Publer does), so
the equivalent for us is *feed health* — and there is none.« I API-tilstand findes token-status i
`SGAP_Channel_Health`; i RSS-tilstand, som er standarden, findes intet tilsvarende. Et tomt feed betyder at
Publer ikke har noget at poste, og et feed hvis poster mangler titel, tekst eller billede bliver afvist eller
postet halvfærdigt — og i dag kan ingen af delene ses uden at åbne feedet i en browser.

**Målet er at gøre tilstanden læsbar, ikke at bygge endnu en måling udenfor.** Feed-sundheden lægges ved siden
af kanalstatus i de to flader der allerede findes — abilityen `channel-health` og `/sgap/v1/channel-health` — så
et MCP-kald og den natlige kørsel kan svare på begge spørgsmål: *kan kanalen sende?* og *har dens feed noget at
sende, og kan Publer bruge det?*

| Trin | Indhold | Estimat | Akkumuleret |
| --- | --- | --- | --- |
| A | `SGAP_RSS::health()` + `health_summary()`: pr. konfigureret kanal antal poster, alder på nyeste, og de fire fejlklasser `empty`/`stale`/`invalid`/`no_photo`; `off` i API-tilstand | 0,12 d | **40,0 %** |
| B | Fladerne: `feeds` + `feeds_summary` i abilityen `channel-health` og i REST-ruten, så én kørsel dækker begge tilstande | 0,08 d | **66,7 %** |
| C | Tests for alle fem tilstande og grænsen for `stale`; README, changelog, zip | 0,10 d | **100 %** |

**Filstruktur (komplet):** `includes/rss.php` ændr (`health()`, `health_summary()`, `STALE_DAYS`); `includes/abilities.php` ændr (to felter i `channel-health`); `includes/channel-health.php` ændr (REST-svaret får `feeds`); `plugins/tests/feed-health.php` ny + `plugins/tests/smoke.php` ændr (load); plugin-README ændr (feed health-afsnit); `steamgears-autopilot.php` ændr (version 7.69.0); zip erstattes; `projektstatus.json`, `docs/samtalepunkter.md` og et biblioteksdokument opdateres.

**Afgrænsning:** ingen nye indstillinger, ingen ny tabel og ingen egen side. Der måles kun på det feed
pluginnet selv udstiller (samme `items()` som `xml()` bruger), ikke på Publers kvittering — den kan først ses når
en kanal har en konto, og det er F6/P1.5.

---

### P8 — agenternes feedback-loop: de læser sidens målte tilstand (7.70.0)

**Problemet er ikke manglende agenter men manglende hukommelse.** Hver skribent får en fast prompt
(`SGAP_AI::SYSTEM`, `CHANNEL_RULES`, `SGAP_Studio::prompt`) og ét snapshot af den side den skriver for.
Intet i prompten afspejler det siden har målt siden: hvilken kanal der konverterer, hvilken vinkel der har
brændt 120 klik af uden en ordre, hvornår en kanal sidst udgav noget, eller hvor mange kladder der venter.
Resultatet er at den samme svage vinkel vælges igen, og den ugentlige SEO-agent planlægger stadig ud fra
`docs/seo.md` plus et 20-siders snapshot, selv om kataloget er gået fra 39 til 20 produkter og valutaen fra
USD til DKK. Agenten udvikler sig altså ikke sammen med siden — den gentager sin første antagelse.

**Målet er én målt brief, som alle skrivende agenter læser, plus en lesson-store der genberegnes dagligt.**
Briefen bygges af `SGAP_Coach` udelukkende fra `sgap_events`/`sgap_learning`, `sgap_ai_drafts` og WordPress'
egen tælling — ingen nye tabeller, ingen indstillinger — og den lægges i prompten som *fakta agenten må
bruge*, med en regel om at tal uden for briefen ikke må citeres. Lesson-storen er de samme tal oversat til
korte regler (»angle_c: 120 klik, 0 ordrer — skriv en anden hook«), så en ny kørsel starter klogere end den
forrige. Repo-siden får samme princip: `tools/agent_brief.py` måler den levende butik og skriver
`docs/agent-brief.md`, som `seo_agent.py` læser før den planlægger.

| Trin | Indhold | Estimat | Akkumuleret |
| --- | --- | --- | --- |
| A | `includes/coach.php` ny: `facts()`, `outcomes()`, `learn()`, `lessons()`, `brief()`, `summary()`, `card()`; filtrene `sgap_agent_brief` og `sgap_agent_facts`; briefen caches i en time | 0,25 d | **31,3 %** |
| B | Briefen ind i de tre skrivende prompts: `SGAP_AI::prompt()` (daglig forfatter + P6-kampagnen), `SGAP_Studio::prompt()` (artikel/guide) og `SGAP_Studio::optimize()` (SEO-redaktøren) | 0,20 d | **56,3 %** |
| C | Fladerne: daglig cron `sgap_coach_learn` via `SGAP_Scheduler`, read-only ability `agent-brief` og et dashboardkort der viser de lærte regler med deres tal | 0,20 d | **81,3 %** |
| D | Repo-siden: `tools/agent_brief.py` + `tools/test_agent_brief.py`, `seo_agent.py` læser briefen, workflow-trin, 25+ smoke-checks, README/changelog, zip | 0,15 d | **100 %** |

**Filstruktur (komplet):** `includes/coach.php` ny (facts, outcomes, lessons, brief, kort);
`includes/generator.php` ændr (brief i prompten); `includes/studio.php` ændr (brief i artikel- og
optimeringsprompten); `includes/abilities.php` ændr (ability `agent-brief`); `includes/core.php` ændr
(dashboardkort); `steamgears-autopilot.php` ændr (require/init, version 7.70.0); `plugins/tests/coach.php` ny +
`plugins/tests/smoke.php` ændr (load); `tools/agent_brief.py` ny, `tools/test_agent_brief.py` ny,
`seo_agent.py` ændr, `.github/workflows/seo_audit.yml` ændr (brief før audit), `tools/natlige-vaern.sh` ændr
(Python-testen); plugin-README ændr (feedback-loop-afsnit); ROADMAP ændr (P8/changelog); biblioteksdokument +
PDF; erstat den ene zip `plugins/steamgears-autopilot-v7.69.0.zip`; opdater `projektstatus.json` og
`docs/samtalepunkter.md`.

**Afgrænsning:** ingen ny tabel (lesson-storen er én option), ingen ny indstilling, ingen publicering og ingen
ny provider. Briefen citerer kun tal den selv har regnet ud; mangler der data, siger den det i stedet for at
gætte. Niche-scanneren er urørt, fordi den allerede læser det levende katalog ved hver kørsel, og
`seo_harvester.py` er urørt, fordi dens kilder er eksterne guider — ingen af dem kan blive klogere af sidens
egne tal. Chatten er urørt: den læser allerede sitet live gennem MCP-værktøjerne.

---

### P9 — Cirla som til/fra-modul (7.71.0)

Cirla er nu et modul, der kan slås til og fra, og som er **slukket som standard** — efter
[`../docs/library/cirla-teknisk-dokumentation-2026-11-06.md`](../docs/library/cirla-teknisk-dokumentation-2026-11-06.md)
§9.4. Filer: `modules/cirla/class-cirla.php` (ny), `steamgears-autopilot.php` (betinget require + init, version),
`includes/core.php` (fyra indstillinger, sektion, hjælpetekster), `plugins/tests/cirla.php` (ny) og
`plugins/tests/smoke.php` (require). Fire handlinger bag capabilityen `sgap_cirla_run`: status, én kladde, SEO-tjek
og forslag. En kladde gemmes som `pending` med `angle = cirla` og køes eller publiceres aldrig; kvalitetsgaten
stopper et dødt link; samme opgave to gange giver ingen dublet; dagligt AI-loft på 5 og filteret
`sgap_cirla_generate`. **28 nye checks (1 443 → 1 471 PASS, 0 FAIL)**, inklusive at modulfilen ikke indlæses
mens modulet er slukket, at alle fire handlinger forsvinder når det slås fra, og at kladderne bliver liggende.
Bevis: [`../docs/library/cirla-modul-2026-11-10.md`](../docs/library/cirla-modul-2026-11-10.md). 0,35 d.

### P10 — OpenRouter som standard + log ind i Command Center med ét klik (7.76.0)

**Hvorfor:** ejeren vil væk fra AIMLAPI og over på OpenRouter, og knappen *Log ind i Command Center* skal logge
ind — ikke bare åbne login-siden. Fundet undervejs (11/11): Command Centers engangslink (`POST /autopilot/login-link`
i CC v7.17.5) sendte til `POST /sgap/v1/login-link`, en rute pluginnet **aldrig** har haft (`git log -S login-link --
plugins` er tom), så det flow har aldrig kunnet virke.

**Filstruktur:**

| Fil | Ændring |
| --- | --- |
| `includes/openrouter.php` (ny) | `SGAP_OpenRouter_Move`: advarsel når udbyderen er AIMLAPI, og *Skift til OpenRouter nu* (admin-post, nonce, `manage_options`), der flytter reserve-nøglen og -modellen over som hovedudbyder |
| `includes/generator.php` | standardudbyder `openrouter` (kun når intet er gemt), `SGAP_AI::openrouter_key()` |
| `includes/core.php` | OpenRouter først og anbefalet, AIMLAPI markeret *udfases*, ny tekstindstilling `openrouter_image_model`, hjælpetekster |
| `includes/setup.php`, `includes/keys.php` | guiden og nøglekortene anbefaler OpenRouter |
| `includes/studio.php` | `SGAP_Images::backend()`: billeder via OpenRouter (`modalities: image`, data-URL) når nøgle + billedmodel findes, ellers AIMLAPI som før |
| `includes/command-center.php` | `POST /sgap/v1/cc-link` (CC lægger adresse + hemmelig nøgle), `admin_post_sgap_cc_login` laver en HMAC-signeret billet (60 s, `jti`) og sender browseren til `{cc}/cc-sso` |
| `plugins/tests/cc-login.php` (ny), `plugins/tests/smoke.php` | checks for billet, signatur, udløb, rettigheder, ruten, billed-backend og flytte-knappen |
| `steamgears-autopilot.php`, `README.md`, zip | version 7.76.0 |

Command Center-siden (repoet `command-center`, v7.27.0): nøglen genereres og sendes ved hver sync, `GET /cc-sso`
verificerer signatur, udløb og engangsbrug og kræver en CC-bruger med samme e-mail; det døde `/cc-login`-flow fjernes.

**Status 11/11: færdig i repoet (0,5 af 0,5 d), 1 505 → 1 550 PASS.** Ikke installeret på sitet endnu.

**Estimat 0,5 d, fordelt:** OpenRouter som standard + flytte-knap 0 → 30 % (0,15 d) · billeder via OpenRouter
30 → 50 % (0,1 d) · login-billet og `cc-link`-ruten 50 → 70 % (0,1 d) · tests, `php -l`, zip og dokumentation
70 → 100 % (0,15 d).

**Bevidst ikke:** AIMLAPI fjernes ikke helt endnu — sitet må ikke miste billeder eller tekst før en OpenRouter-nøgle er
sat. `aimlapi-tool-schema-sanitizer.php` beholder navnet: den retter skemaer som Claude afviser, også via OpenRouter,
og `sgap-mcp-schema-fix.php` indlæser den efter filnavn på det levende site.

## 6. Completion estimates (1/11 2026 — historical)

**Kept to show how the scope grew, not as a forecast.** The milestones were computed against the 7.15.0
audit's scope. Actuals measured 8/11 2026: **29,85 d of agent work, all of it built and covered**
(`projektstatus.json`, `verified: true`), plus **0,6 d of live verification that is owner-blocked** (P1.5).
External approvals are still the calendar cost; they are not agent days.

| Milestone | Scope | Effort | Calendar at ~half-time |
| --- | --- | --- | --- |
| **Set-and-forget v1** | P0 (4.0 d) + P1.1–P1.4 and P1.6 (6.0 d) + P3.1–P3.2 (1.0 d) | **11 d** | ~3 weeks |
| Set-and-forget v1 without backoff/audit log | P0.1, P0.2, P0.4 + P1.1, P1.3 | 3.0 d | ~1 week |
| **Full parity with hosted schedulers** | v1 + P1.5 + P2.1–P2.3 + remaining P3 (+ P2.5 if wanted) | 18–21 d | ~6–8 weeks |
| Channel expansion alone (Bluesky, Mastodon, Telegram) | P2.1–P2.3 | 2.0 d | ~3 days |
| Command Center consumption of `/snapshot` (different repo) | read endpoint, show status, alert on stall | 1–2 d | — |

Calendar time is dominated by external approvals, not code: Meta App Review is roughly 5 business days per
permission set, Pinterest Standard access needs a recorded demo and has no published timeline, Google
Business Profile access is ~14 days, and an Etsy app review is similar. None of those block P0.

---

## 7. Sources

Hosted-tool behaviour: [Buffer notifications](https://support.buffer.com/articles/enabling-email-notifications-in-buffer-kuKhKBU7c3) ·
[Publer failed-post notifications](https://publer.com/help/en/article/how-to-get-notified-about-my-failed-posts-acjw20/) ·
[Publer auto-reschedule](https://publer.com/help/en/article/how-to-reschedule-or-auto-reschedule-posts-mypcso/) ·
[Metricool tutorial](https://metricool.com/metricool-mega-tutorial/) ·
[SocialBee features](https://socialbee.com/features/) ·
[SmarterQueue evergreen](https://smarterqueue.com/features/evergreen_recycling) ·
[Hootsuite approval workflow](https://blog.hootsuite.com/social-media-approval-workflow/).

WordPress engineering: [PHP compatibility and WP versions](https://make.wordpress.org/core/handbook/references/php-compatibility-and-wordpress-versions/) ·
[PHP 8.4 code changes](https://make.wordpress.org/core/2024/11/07/getting-ready-for-php-8-4-some-code-changes-landing-in-6-7/) ·
[Abilities API in 6.9](https://make.wordpress.org/core/2025/11/10/abilities-api-in-wordpress-6-9/) ·
[Action Scheduler WP-CLI](https://actionscheduler.org/wp-cli/) ·
[WP-Cron replacement](https://crontap.com/blog/replace-wordpress-wp-cron-with-real-scheduler) ·
[Plugin Update Checker](https://github.com/YahnisElsts/plugin-update-checker/blob/master/README.md).

Platform limits: [Instagram content publishing](https://developers.facebook.com/documentation/instagram-platform/content-publishing) ·
[Threads API](https://developers.facebook.com/documentation/threads/overview) ·
[Graph API rate limits](https://developers.facebook.com/docs/graph-api/overview/rate-limiting/) ·
[Pinterest rate limits](https://developers.pinterest.com/docs/reference/rate-limits/) ·
[LinkedIn rate limits](https://learn.microsoft.com/en-us/linkedin/shared/api-guide/concepts/rate-limits) ·
[Bluesky rate limits](https://bsky.network/docs/rate-limits/) ·
[Mastodon rate limits](https://docs.joinmastodon.org/api/rate-limits/) ·
[Telegram Bots FAQ](https://core.telegram.org/bots/faq) ·
[Google Business Profile limits](https://developers.google.com/my-business/content/limits) ·
[X API pricing](https://docs.x.com/x-api/getting-started/pricing).

---

## 8. Decisions and who owns them (status 8/11 2026)

1. **Etsy** — ✅ **decided 2026-11-06: hidden, not built** (7.33.4). The five fields are gone from Settings and
   the Etsy page labels itself a manual import; `POST /wp-json/sgap/v1/etsy/orders` is still the way in. Revisit
   only if Etsy volume justifies the 2.5 d OAuth import.
2. **Direct network tokens** — ✅ **decided 2026-11-06: removed** (7.33.3). All publishing goes through Publer,
   so `pinterest_token`, `instagram_token`, `tiktok_token`, `youtube_*`, `reddit_*` and `ai_mode` are gone.
3. ~~**Action Scheduler**~~ — **afgjort 7/11: adapter, ikke migration** (7.49.0 + 7.50.0; statuskort 7.51.0). `includes/scheduler.php`
   bruger Woo's kopi når den findes og falder tilbage til WP-Cron ellers; samtlige jobs går gennem den, og
   `SGAP_SCHEDULER_WP_CRON` er nødudgangen. Den oprindelige formulering lød: accept the 2 d migration, or stay on
   WP-Cron with detection and documentation
   (P0.4)? **Research 7/11:** WooCommerce 11.x leverer Action Scheduler (GPL-3.0, L-2-politik), så en adapter på ~0,5 d
   (`function_exists('as_schedule_recurring_action')` → AS med `sgap`-gruppe, ellers WP-Cron) slår den fulde 2,0 d-migration — se
   [docs/library/plugin-funktioner-research-2026-11-07.md](../../docs/library/plugin-funktioner-research-2026-11-07.md).
4. ✅ **afgjort i praksis 7/11 — P1.5 blev delt og bygget.** Spørgsmålet om ejerens tokens står i P1.5's
   række i §5, ikke her. Den oprindelige formulering lød: **Analytics** — is platform-side reach/engagement worth 4 d plus API review, or are click and revenue
   attribution (already built) enough? **Research 7/11:** review-kravet gælder kun *andre folks* konti — Pinterest-analytics er med i Trial,
   IG-insights på egen konto kræver intet App Review, og Bluesky/Mastodon er offentlige reads. P1.5 kan derfor deles i
   **0,5 + 1,0 + 1,5 d** i stedet for 4,0 d — se
   [docs/library/plugin-funktioner-research-2026-11-07.md](../../docs/library/plugin-funktioner-research-2026-11-07.md).
5. ⛔ **Åbent valg — kun ejeren kan tage det.** **Command Center direction:** skal CC være **énvejs**
   (CC læser `/snapshot` og lægger kladder ind via `POST /wp-json/sgap/v1/drafts`, mens WordPress aldrig kalder CC)?
   Det er den anbefalede retning og den eneste der ikke kræver en ny tokenvej. **Svar ja/nej.** Ved ja starter
   CC-arbejdet på den kontrakt; ved nej skal den alternative retning (tovejs eller ingen integration) vælges
   først, fordi den ændrer både auth og datamodel.
6. **`labor_rate`** — ✅ **decided and shipped 7/11 (7.59.0), P3.1 closed.** Of the three endings, the third was the
   only one that needs no number the plugin cannot measure: the field stays visible under Economics and now does
   what its label honestly says — it turns money into **hours**. The ROI card divides the 30-day attributed
   revenue and the monthly budget by the rate and prints *Labour equivalent: X h at N/h (budget ≈ Y h)*; a rate of
   0 prints “off” instead of dividing by zero. ROI, ROAS and the target are untouched, so no economics changed —
   a registered-but-unread setting stopped being unread. Removing the field or costing minutes-per-post remain
   available later if the owner wants them, but they are no longer what P3.1 waits for.
7. **Activate WooCommerce and the plugin** — ✅ **done 7/11 by the owner's nearest pass (no owner decision was needed).**
   Both are active: WooCommerce 11.2.2, Autopilot 7.59.0 (7.58.2 at the time of the activation). Measured after: `/`,
   `/shop/` and `/wp-json/sgap/v1` all return **200**, the REST namespace lists **19 routes** including the three
   `/facebook/*`, and the `is_woocommerce()` fatal is gone. The 500 that survived the activation was W3 Total Cache
   serving the cached fatal page; `w3tc_flush_all()` cleared it. The mu-plugin fix 1.3.1 is in the repo but **not on
   the site** — rettet 8/11: Novamira kan godt skrive til `wp-content/mu-plugins`, og alle fem færdige
   mu-plugins (`sga-design-system`, `sgap-product-schema`, `sgap-review-request`, `sgap-withdrawal-email`,
   `sgap-og-images`) plus `sgap-shop-h1` er nu lagt ind og verificeret på det levende site (7.63.0 dengang; sitet kører 7.65.5 nu).

8. ✅ **Lukket 9/11 — formuleringen var forkert, ikke funktionen.** Posten lød: »`alert_email` er tom,
   så fejl registreres i diagnostikken men ingen mail sendes«. Målt 9/11: `SGAP_Alerts::recipient()` falder
   tilbage til `get_option('admin_email')` når feltet er tomt eller ugyldigt, og hjælpeteksten i Settings siger
   det samme (*Where alerts go. Empty = the site admin address*). På live returnerer `recipient()`
   admin-adressen, `mode` er `immediate`, `wp_mail()` findes og **WP Mail SMTP er aktiv** — så P0.1's mailgren
   har virket hele tiden. Adressen er alligevel skrevet ind i **Settings → Alerts**, så den står synligt i
   stedet for at være en usynlig fallback. Læren er generel: en tom indstilling er ikke det samme som en
   manglende evne — læs koden der læser feltet, før et punkt erklæres ejer-blokeret.
9. ⛔ **Åbent — men ikke-blokerende.** **Plugin'ets opdateringskanal** efter at repoet blev privat: A) bliv privat og
   læg zip'en ind manuelt, B) flyt kun release-zip'en til et offentligt sted, C) gør repoet offentligt igen.
   `SGAP_Updater` henter anonymt fra `raw.githubusercontent.com`, og målt 8/11 svarer både versionsfil og pakke
   **404** fra sitet. Beslutningen holder ikke andre punkter tilbage; den står i
   [docs/library/repo-privat-2026-11-08.md](../../docs/library/repo-privat-2026-11-08.md).
---

## 9. Change log for this document

- **7.85.0 (13/11) — P12: kun OpenRouter, ingen AI-billedgenerering, ryddet admin-UI.** AIMLAPI, Anthropic, *Other OpenAI-compatible*, reserve-udbyderen og gratis-model-forsøget er fjernet. `SGAP_AI::provider()` svarer altid `openrouter`, adressen er fast, og der findes ingen standardmodel. Content Studio skriver tekst, og Instagram/Pinterest genbruger sidens featured image; `SGAP_Images`, `generate-image`, billednøgler/-modeller og `includes/openrouter.php` er væk. Fanen heder nu *Settings → AI*, nøglesiden har ét kort, og guiden ét AI-trin. 1 570 PASS / 0 FAIL i smoke; `cc-login`, `keys`, `automation`, `integration` og `admin-tools` er opdateret. Ikke målt: et rigtigt OpenRouter-kald og en kørsel mod WordPress.
- **7.76.0 (11/11) — P10: OpenRouter som standard, billeder via OpenRouter og log ind i Command Center med ét klik.** OpenRouter er standardudbyder (kun når intet er gemt) og står først i guiden og på nøglesiden; AIMLAPI er markeret *udfases*, men et gemt AIMLAPI-valg virker stadig. Ny fil `includes/openrouter.php`: advarsel mens AIMLAPI er valgt, og *Switch to OpenRouter now*, når reservenøgle og -model findes. `SGAP_Images::backend()` laver billeder via OpenRouter (`modalities: image`, data-URL) når nøgle + `openrouter_image_model` findes, ellers AIMLAPI som før. `POST /sgap/v1/cc-link` gemmer Command Centers adresse og fælles nøgle (ikke autoload); knappen laver en HMAC-billet (60 s, `jti`) til `{cc}/cc-sso`. Fund: CC's gamle engangslink sendte til `POST /sgap/v1/login-link`, en rute der aldrig har fandtes. **45 nye checks (1 505 → 1 550 PASS, 0 FAIL)** i `plugins/tests/cc-login.php`. Ikke verificeret: et rigtigt OpenRouter-billedkald og et login mod den kørende Command Center.

- **7.75.0 (11/11) — log-rækkerne siger hvad målet hedder.** `SGAP_Cirla::pending_draft($id)` giver både eksistens og titel i én query, og `draft_exists()` bygger på den; `row_target()` leverer url og titel samlet, så en draft-række læses »Draft #7 · Brass goggles« og seo/social-rækker »SEO check · sidens titel«. Mål der er væk giver hverken link eller titel. Smoke: 1 505 PASS (1 504 → 1 505).
- **7.74.0 (11/11) — log-rækkerne peger på det de handler om.** `SGAP_Cirla::log($action, $result, $meta)` gemmer målets id; draft-rækker linker til kladden på AI Drafts (`#sgap-draft-<id>`, anker tilføjet i `core.php`) og seo/social-rækker til sidens editor. Der linkes kun når målet stadig findes — ellers ren tekst, så ingen døde links. 8 nye checks (1 496 → 1 504 PASS).
- **7.73.0 (11/11) — loggen på Cirla-siden kan filtreres pr. handling.** `SGAP_Cirla::entries($limit, $action)` læser én handling; siden har faner med antal for All/status/draft/seo/social, viser kun den valgte handlings rækker og beholder to tomme tilstande (tom log, tomt filter med »Show all N rows«). 6 nye checks (1 490 → 1 496 PASS).
- **7.72.0 (11/11) — Cirla fik sin egen side i Steamgears-menuen.** `modules/cirla/class-cirla-admin.php` indlæses sammen med modulet og kun i wp-admin: tilstand, de fire handlinger som knapper gennem `admin-post.php` med nonce og `manage_options`, resultatet som tabel og modulets log. 15 nye checks (1 475 → 1 490 PASS).
- **7.71.1 (11/11) — patch notes er nu en fast del af hver udgivelse.** `CHANGELOG.md` i pluginnet med noter fra 7.65.0 og frem, `tools/patch_notes.py --check` som værn i `tools/natlige-vaern.sh` og i release-gaten, og reglen i AGENTS.md §7.4 om at noten også skal med i svaret til ejeren. Ingen PHP-adfærd ændret.
- **7.71.0 (10/11) — P9: Cirla er et modul, der kan slås til og fra, og som er slukket som standard.** Modulfilen `modules/cirla/class-cirla.php` indlæses kun, når `sgap_cirla_enabled` er `yes`, så et modul der er slået fra ikke kan registrere én ability eller ét cron-job. Fire handlinger bag capabilityen `sgap_cirla_run` (status, én kladde, SEO-tjek, forslag); kladden gemmes som `pending` med `angle = cirla` og køes eller publiceres aldrig, kvalitetsgaten stopper et dødt link, samme opgave to gange giver ingen dublet, og et dagligt loft på 5 AI-kald plus filteret `sgap_cirla_generate` holder omkostningen nede. 28 nye checks (1 443 → 1 471 PASS). Bevis: `docs/library/cirla-modul-2026-11-10.md`.

- **7.70.0 (10/11) — P8: agenterne læser sidens målte tilstand i stedet for en fast antagelse.** Hver skribent
  startede fra den samme prompt og ét snapshot af den side den skrev for, så en vinkel med 120 klik og nul
  ordrer blev valgt igen, og en kanal der var gået i stå blev ved med at få flere poster. `SGAP_Coach` læser nu
  `sgap_events` (klik, ordrer, omsætning og seneste publicering pr. kanal og vinkel, 30 dage),
  `sgap_ai_drafts` og WordPress' egne tællinger og lægger et kort, målt brief i prompten hos den daglige
  forfatter, kampagne-agenten (samme prompt), Studio-artiklen og Studio-SEO-redaktøren — med en regel om at
  kun briefens tal må citeres. En daglig cron omsætter tallene til højst 12 daterede regler (fx »angle_c: 120
  klik, 0 ordrer« eller »bluesky har ikke udgivet noget i 20 dage«), og de vises på dashboardet under *What the
  agents have learned* og kan læses af en agent gennem den read-only ability `agent-brief`. Ingen ny tabel,
  indstilling eller publiceringsvej; en måling der ikke findes, udelades og siges højt. Repo-siden fik samme
  princip: `tools/agent_brief.py` måler det levende katalog (20 produkter, DKK, 165–1 000, 9 kategorier, 33
  indlæg, 32 sider) og skriver `docs/agent-brief.md`, som `seo_agent.py` nu læser — derfor gentager den
  ugentlige plan ikke længere 39 produkter i USD. 44 nye coach-checks (**1 383 → 1 427 PASS / 0 FAIL**; efter
  rebase over 7.69.1 er totalen **1 432 PASS / 0 FAIL**) og 13
  Python-tests.
- **7.69.0 (10/11) — P7: feed health lukker §4.1 pkt. 2, som har stået åbent siden 1/11.** I RSS-tilstand — som
  er standarden — findes der ingen token at teste, så svaret på »kan kanalen sende?« er feedet selv.
  `SGAP_RSS::health()` læser præcis de rækker `xml()` renderer og giver hver konfigureret kanal én tilstand:
  `ok`, `empty` (Publer ser intet nyt), `stale` (nyeste post ældre end 7 dage, kan hæves/sænkes med filteret
  `sgap_feed_stale_days`), `invalid` (en post uden titel, uden tekst, eller uden foto hvor Instagram/Pinterest
  kræver det) eller `off` (API-tilstand). Rækken bærer antal poster, alder og feed-adressen, så svaret også
  siger hvorfor, og `health_summary()` tæller tilstandene og navngiver de kanaler der kræver handling. Det hele
  går ind i de to flader der allerede findes — abilityen `channel-health` og `/sgap/v1/channel-health` — så ét
  read-only kald dækker begge halvdele: kan kanalen sende, og har dens feed noget brugbart. Ingen ny tabel,
  indstilling eller side. 25 nye smoke-checks (**1 358 → 1 383 PASS / 0 FAIL**).
- **7.68.0 (10/11) — P6: kampagne-agenten skriver kladder, men publicerer aldrig.** Efter P5 var der stadig ingen
  arbejdsgang mellem »skriv noget for den her side« og »kør den i dag«. `SGAP_Campaign_Agent` tager nu en
  publiceret side, et skrevet mål på 8–160 tegn og de kanaler der er sat op, og skriver ét kladdeudkast pr.
  kanal gennem det samme AI-kald som den daglige forfatter — men gemmer **altid** `pending` i `sgap_ai_drafts`,
  også når Full Auto er slået til. Den rører hverken `SGAP_Queue` eller `SGAP_Publer`; godkendelsen er stadig et
  menneske på *AI Drafts*. Hver kladde får kanalens og kvalitetsgaten's bemærkninger med (`gate_report`), fordi
  en advarsel ellers forsvinder mellem generering og godkendelse, og kampagnenavnet (`campaign`) følger kladden
  hele vejen gennem `SGAP_Queue::from_draft()` til `utm_campaign`, så GA4 kan skelne kampagnen fra det
  sædvanlige flow. To nye kolonner i DDL'en (`campaign`, `gate_report`), en ny ability `campaign-drafts`, et
  kort på dashboardet og et kampagnemål i prompten (kun når agenten sætter det; den daglige forfatter er
  uændret). Agenten springer en kanal over hvis der allerede venter en kladde for samme kampagne, og skriver
  højst 10. 42 nye smoke-checks (**1 316 → 1 358 PASS / 0 FAIL**).
- **7.65.5 (9/11) — 7.65.4 er nu faktisk på sitet, og filens øverste påstand er rettet.** Sitet kørte 7.65.0, mens repoet var 7.65.4, så P3.7's og P3.8's besparelser var bygget uden at være i drift; zip'en blev derfor uploadet gennem MCP i to base64-dele og installeret med `Plugin_Upgrader::install(…, overwrite_package)`. Målt live efter: version **7.65.5**, dashboard **17 566 bytes HTML med 0 inline `<script>` og 0 inline `<style>`**, syv side-scripts og to CSS-filer; `/`, `/shop/`, produktside og `/cart/` svarer 200. Filens indledning sagde stadig »7.64.6 is what steamgears.eu runs« — den er rettet til det målte. Denne patch ændrer kun dokumentation, men versionen hæves fordi denne fil er en del af zip'en.
- **7.65.4 (9/11) — de sidste inline side-scripts er blevet filer.** P3.7 flyttede CSS'en, men der laa stadig femten `<script>`-blokke i ni includes — hver side byggede sin egen JS som PHP-strenge med `wp_json_encode($cfg)` indeni. Nu ligger syv filer i `assets/pages/`: `dashboard.js` (generering, kopiér-knapper, redningskortet), `studio.js`, `seo.js` (hele-sitet-kortet var en nowdoc i `site-seo.php`), `chat.js`, `tools.js`, `niche.js` og `setup.js` (indstillinger, noegletest og wizard). `SGAP_Assets::page_script()` enqueuer filen i footeren med filens mtime som version og sender konfigurationen med `wp_localize_script` — nonces, chat-historikken og vaerktoejskataloget bliver stadig beregnet pr. request, men som ét objekt i stedet for indeni JS'en. Teksten er udtrukket mekanisk fra PHP-literalene og er byte-identisk med det der blev printet foer (sammenlignet mod HEAD for alle syv filer) og `node --check`-ren; det ene `<script>` tilbage i `includes/` er JSON-LD. Loftet paa 12 inline side-scripts er fjernet og erstattet af et krav om nul. 20 nye smoke-checks (1172 -> **1192 PASS / 0 FAIL**).
- **7.65.3 (9/11) — §8 pkt. 8 lukket, og hardningen ligger nu på sitet.** `SGAP_Alerts::recipient()` falder
  tilbage til admin-adressen, så »alarm-e-mailen er tom« var en fejlformulering, ikke en mangel; adressen er
  alligevel skrevet ind i Settings → Alerts. Samme pas lagde `wp-content/mu-plugins/sgap-hardening.php` på
  det levende site (12 558 bytes, md5 lig repoets), hvilket tidligere stod som umuligt — Novamira-tokenet
  ligger i hjemmemappen. Målt udefra: `s5_measure.py` gik fra **8 opfyldt · 11 mangler** til
  **19 opfyldt · 0 mangler**, og tre cache-fund kom med: W3TC svarede uden om PHP (headere manglede på
  cachede sider), en slettet `readme.html` blev afspillet med 200 fra cachen, og `pgcache.cache.404 = true`
  fik enhver ukendt URL til at svare 200 ved andet kald. Værnet er koblet på i `tools/natlige-vaern.sh`.
  Bevis: [docs/library/bredt-layout-og-hardning-2026-11-09.md](../../docs/library/bredt-layout-og-hardning-2026-11-09.md)
- **7.65.2 (8/11) — admin-siderne baerer ikke laengere deres eget CSS.** Stylesheetet laa inline i `<head>` (40 452 tegn) og blev hentet igen ved hvert sidevisning; menu-CSS/JS og et submit-script laa oveni. Nu er der fire asset-filer, en `SGAP_Assets`-loader med `is_plugin_screen()`, mtime som version og enqueue kun for `manage_options` — 41,8 KB mindre HTML pr. side og cachebare filer. Samtidig er UI'et rettet paa fire punkter: fokus-ring, mobil-tabeller, mobil-grid og reduceret bevaegelse. 36 nye smoke-checks; 1172 PASS / 0 FAIL.
- **7.65.1 (8/11 aften) — status målt på ny: to påstande var forældede, og ejerlisten er nu præcis.** §5 målte 7.64.9; live er **7.65.0** og repoet **7.65.1** (denne patch ændrer kun dokumentationen), og
suiten giver **1136 PASS / 0 FAIL**. P1.5 bad ejeren trykke *Update* til 7.64.9 — det var allerede kørt (`update` svarer »Already up to date (7.65.0)«), så skridtet er fjernet og listen er nu to skridt. Kanalmålingen står med friske tal fra 21:24: `summary()` = `ready 0 · needs_scope 1 · missing 5`, `analytics_summary()` = `measured 0 · empty 5 · no_metric 1`. §8 har fået to punkter: alarm-e-mailen er tom, og opdateringskanalen A/B/C efter at repoet blev privat; CC-spørgsmålet er gjort til et ja/nej med konsekvens. Bevis: [docs/library/roadmap-status-2026-11-08.md](../../docs/library/roadmap-status-2026-11-08.md)
- **7.64.9 (8/11) — P1.5 kan nu måles, ikke kun forklares.** Kanalstatus svarede på »kan næste refresh køre?«
  (`ready`/`needs_refresh`/`missing_token`/`refused`/`needs_scope`), men ikke på det P1.5 faktisk bedømmes på:
  **landede platformstallene i `sgap_events`?** Der fandtes ingen læseflade for det — hverken en ability eller
  en samlet rapport — så målingen ville ende som et menneskes påstand om et dashboard. `SGAP_Channel_Health`
  har nu `PLATFORM_TYPES` (de event-typer der er *platformens* tal, pr. kanal), `analytics($days)` og
  `analytics_summary()`, og en ny read-only ability **`channel-health`** returnerer begge halvdele: kanalenes
  tilstand og de landede tal. `/sgap/v1/channel-health` får samme `analytics`-blok. Tre ting er holdt ude af
  summen med vilje: vores egne `publish`/`click`/`order`-rækker tælles ikke som platformstal; `likes` tælles
  **pr. kanal** (fire platforme skriver samme type); og Telegram er `no_metric` — Bot API har ingen tal pr.
  opslag, så en nulstilling der ville ligne en manglende måling. **`tools/live_channels.py`** kalder abilityen
  gennem Novamira-fladen og svarer med en dom: `bestået` (alle kanaler med en metric har landet tal),
  `blokeret` (en kanal kan ikke måles endnu — exit 3) og `fejlet` (**et virkende token landede intet** — exit 1,
  den række der er pointen). Den kører nu i `novamira-keepalive.yml` med det fornyede token og skriver
  `reports/live-kanaler-<dato>.md`. 10 nye smoke-checks (**1 118 → 1 128 PASS / 0 FAIL**), 11 nye Python-tests
  og et nyt værn (**29 → 30**). **Ikke live-verificeret endnu:** sitet kører 7.64.6 indtil ejerens
  *Plugins → Update*, Pinterest mangler `boards:read`, og de fem andre kanaler har intet token — målingen
  melder derfor `blokeret`, ikke `bestået`.
- **7.64.8 (8/11) — udgivelsen: kanalstatus tæller nu en manglende klasse for sig selv.** `summary()` slog
  `no_class` sammen med `missing_token`, så en kanal hvis klasse ikke var loaded blev vist som »indsæt et
  token« — altså en kodefejl der lignede et tomt felt, med en ejerhandling der ikke kunne løse den.
  `SGAP_Channel_Health::STATES` mapper nu hver af de seks tilstande til sin egen tæller (`broken` for
  `no_class`), og `plugins/tests/channel-health.php` kræver at hver tæller matcher sin tilstand i `report()`,
  ikke bare at summen er 6. To nye checks: **1 118 PASS / 0 FAIL**. README siger nu seks tilstande i stedet
  for fire.
- **7.64.8 (8/11)** — **roadmappet er gjort entydigt: §1–§4 er historik, §5 er planen, og hvert åbent punkt har
  sin ejerblokering.** §1–§4 (metode, subsystem-tabel, fund, research) blev skrevet 1/11 mod **7.15.0** og blev
  læst som gældende plan, selv om de kalder ting »manglende« der er bygget for længde siden. De er nu mærket som
  **historisk snapshot** i både overskrift og banner, med indholdet bevaret; §6 er mærket som det historiske
  skøn, den var, og §8 hedder nu »Decisions and who owns them«, fordi 6 af 7 punkter var afgjort (kun
  Command Center-retningen er åben). **Afstemt mod `projektstatus.json`:** en ny tabel i §5 binder hver
  P-række til det modul den bogføres i, og summer til **29,85 d**; P2-totalen sagde 6,25 d og talte P2.6 med to
  gange (P2.6 er eget modul, `P2` er 4,75 d); P1.5's række siger nu eksplicit **kode-, test- og CI-verificeret,
  ikke live-verificeret**, med ejerens tre skridt i rækkefølge (***Plugins → Update*** til 7.64.8, Pinterest
  `boards:read`, tokens til de fem andre kanaler); P4's smoke-tal er suite'ts 1 118, ikke det gamle 1 084.
  Ingen procent er flyttet: `0,6 d` er fortsat ejer-blokeret og står uden for agent-estimatet.
- **7.64.7 (8/11)** — **tokenkilden bestemmer nu, hvem der må forny, og hvad et 401 betyder.** Tre fejl fra
  7.64.6 er rettet i `includes/pinterest.php`: (1) et 401 blev fornyet med pluginnets **egne** app-credentials,
  også når det sendte token kom fra Pinterest for WooCommerce — den fornyelse roterer et andet token end det der
  fejlede — og et scope-svar (`Missing: ['boards:read']`) blev fornyet forgæves. Fornyelse kræver nu egne
  credentials **og** at kilden er vores egen, og et scope-svar fornyes aldrig. (2) `refreshable()` betød både
  »vi kan forny« og »der findes et token«; den er delt i `own_refreshable()` (vores app-credentials +
  refresh-token) og `refreshable()` (det ene eller det andet), så `status()` ikke længere beder om
  app-credentials på et site der kører på integrationens token. (3) En afvisning blev ryddet af **ethvert**
  200-svar, så et 200 fra `/user_account` skjulte at `/pins` mangler `boards:read`; verdictet husker nu sit
  endpoint og ryddes kun af det endpoint der blev afvist. Afvisninger stemples desuden med kilde **og** et
  8-tegns sha256-prefix af det token de gælder (aldrig tokenet), så integrationens rotation ikke arver en gammel
  `refused`. `status()` læser integrationens udløbstid (kun `token_data.refresh_time`/`expires_in` — aldrig
  ciphertext) og siger »reconnect that integration« i stedet for at bede om et nyt token. 11 nye smoke-checks
  (1105 → **1116 PASS**, 0 FAIL), og `plugins/tests/channel-health.php` tæller nu alle fem tilstande i sin sum
  og sender scope-verdictet med i sin egen konsistenskontrol.
- **7.63.0 (8/11)** — **bogføringsrelease: de færdige mu-plugins er nu også på sitet, og P1.5 står som
  ejer-blokeret med navnet på hver token.** Ingen plugin-kode er ændret; versionen hæves fordi ROADMAP.md er en
  del af zippen, og `fejlscan` kræver at zip og kilde følger hinanden. Målt live 8/11: de seks mu-plugins er
  lagt ind i `wp-content/mu-plugins` og siden svarer 200 på `/`, `/shop/`, en guide og en produktside;
  `tools/schema_check.py` giver **20/20** produkter med brand, returret og leveringstid; OG-billedet på indlæg
  615 er nu **768 × 1366** (var 576 × 1024, under 600 px-grænsen), 751 og 806 bruger 768 px-varianter, og
  Pinterest-plugin'ets dublerede `og:image` er væk. Smoke stadig **1049 PASS / 0 FAIL**.
- **7.62.0 (8/11)** — **alle nøgler på én side, med ejerskab og en ændringshistorik.** Keys-siden har
  allerede samlet alle 15 hemmeligheder, men den sagde ikke hvem kontoen tilhører eller hvorfor nøglen findes,
  og en udskiftning efterlod intet spor. Hvert kort har nu `Belongs to` og `Why it is here`, og en
  sammenklappelig tabel nederst gentager det for alle 15 nøgler; `SGAP_Keys::documentation()` udstiller samme
  tekst, så dokumentation og skærm ikke kan glide fra hinanden. `SGAP_Keys::record()` skriver hver ændring
  (sat/skiftet/fjernet) med **længde, 8-tegns sha256-fingerprint, tidspunkt og aktør — aldrig værdien** — til
  optionen `sgap_key_changes` (ikke autoloadet, loft 120 rækker), og kortet viser den seneste linje. En ændring
  rydder samtidig det der blev hentet med den gamle nøgle (modelkatalog, Publer-konto-liste, Printful-priser,
  Bluesky-session) og den gamle nøgletest, så en rotation ikke ligner en død nøgle. Skrivevejen bag abilities og
  tokens (`SGAP_Settings::set()`) logger selv, fordi `options.php`-hooket ikke ser den. 22 nye smoke-checks
  (1027 → **1049 PASS**, 0 FAIL) dækker dokumentationen, at hver nøgle har præcis én række, at en gentagen
  værdi ikke tæller som rotation, at en konstant i `wp-config.php` ikke logges, og at hverken side eller log
  indeholder værdien.
- **7.60.1 (8/11)** — **et token fra det gamle felt er stadig et gyldigt token.** 7.33.3 afregistrerede
  `pinterest_token`, men et token indtastet før da ligger stadig i options-tabellen under `sgap_pinterest_token` —
  og intet læste det, så dashboardet sagde “ikke forbundet” mens en `pina_`-nøgle på 101 tegn lå én option væk,
  uden et felt at se den i. `SGAP_Pinterest::saved_token()` læser nu det gamle navn som fallback, og
  `migrate_legacy()` (kørt på `init`) kopierer det ind i `pinterest_access_token`, som Keys-kortet viser, så
  nøglen kan ses, udskiftes og roteres. En gemt ny nøgle vinder altid over den gamle, og det gamle felt lades
  urørt som tilbagerulningsvej. Seks smoke-checks (1010 → 1016 PASS, 0 FAIL) dækker fallbacken, at anmodningen
  sender det gamle token, kopien til det synlige felt, at en ny nøgle ikke overskrives, og at ingen af felterne
  stadig betyder intet token.
  **Målt live 8/11:** 7.60.1 aktiv på sitet, `access_len = 101`, `migrated_equal = true`, `configured = true` — og
  Pinterest afviser det gamle token med **HTTP 401 `Authentication failed.`** (0 pins, 0 events), mens Instagram
  fortsat ikke har noget token (`configured = false`). P1.5's 0,85 d er derfor **ikke** bogført; målingen står i
  `docs/library/pinterest-noegle-gammelt-felt-2026-11-08.md`.
- **Afstemning 7/11 (7.59.0)** — **roadmappet er afstemt linje for linje, og der var intet skjult punkt.** Tre totaler
  var regnet forkert: P0 sagde 4,70 (CC-linkets 0,20 talt to gange, `/go/`'s 0,15 udeladt) → **4,65**; P1 sagde 15,20
  (P1.19's 0,25 talt to gange, queue-post 0,10 udeladt) → **15,05**; P3 sagde 2,75 (0,75 d der aldrig blev fjernet da
  Etsy-halvdelen flyttede til P2.4) → **2,00**. Alle rækker er gennemgået med kilde-anker, dage, status og verifikation i
  `docs/library/roadmap-afstemning-2026-11-07.md`; summen er 29,60 af 30,45 d, og **åbent = 0,85 d = P1.5's
  live-verifikation** — det eneste punkt der ikke kan lukkes uden ejerens Pinterest- og Instagram-tokens. Intet punkt blev
  erklæret færdigt i passet, og P0.4's manglende ✅ er tilføjet. §8 punkt 7 er lukket: WooCommerce og pluginnet kører.
- **7.59.0 (7/11)** — **`labor_rate` læses nu: P3.1 lukket.** Indstillingen har været registreret siden 7.15.0 og
  læst af intet (den sidste af slagsen, §8 punkt 6). Den er ikke fjernet og der er ikke gættet et minutforbrug:
  §8's tredje udgang — “omdøb den til det den er i dag”, en budget-til-timer-reference — er gennemført i stedet.
  `SGAP_ROI::hours($amount, $rate)` dividerer med timeprisen, og ROI-kortet viser nu
  *Labour equivalent: 4 h at 250.00/h (budget ≈ 4 h)* ved siden af omsætning, budget, ROI og ROAS; en pris på 0
  giver “off” i stedet for en division med nul, så kortet aldrig viser uendeligt. ROI/ROAS/target er urørte, så
  ingen økonomi er ændret — kun at feltet faktisk bliver læst. Fem smoke-checks i `plugins/tests/roi.php`
  (990 → 995 PASS, 0 FAIL) dækker divisionen, nul-prisen, afrundingen og at kortet læser indstillingen tilbage.
- **7.58.2 (7/11)** — **opdateringsfælden: kortet kunne sige “Up to date” i seks timer.** Ejeren trykkede
  *Plugins → Update*, og der skete intet — ikke fordi knappen fejlede, men fordi der ikke var noget at
  installere: `SGAP_Updater::remote_version()` cacher GitHubs versionsnummer i et site-transient i **6 timer**,
  og WordPress' egen `update_plugins`-transient kan hænge op til **12 timer** bagefter, så release 7.58.1 var
  usynlig for begge flader. Dashboard-kortet bruger nu **`refresh_for_admin()`**: den læser versionen **friskt**
  (højst ét GitHub-kald pr. minut via `sgap_remote_version_admin`), og når GitHub har noget nyere end den
  installerede version, ryddes `update_plugins`, så Plugins-listen tilbyder samme version med det samme.
  Beviset ligger i `plugins/tests/updater_cache.php`, som kører uden WordPress: en cachet læsning på den gamle
  version, en frisk læsning på den nye, og en kontrol af at `update_plugins` bliver ryddet — og ikke ryddet når
  vi er ajour. Ingen nye dage.
- **7.58.1 (7/11)** — **REST-nedbruddet i 7.58.0 rettet.** `includes/diagnostics.php` og
  `includes/facebook.php` registrerede deres ruter med `SGAP_Admin::NS`; klassen `SGAP_Admin` har ingen
  `NS`-konstant (den ligger på `SGAP_REST`), så `rest_api_init` kastede en `Error` på **hver**
  `/wp-json/`-forespørgsel — også `/wp/v2`, Store API (kurv og kasse) og begge MCP-flader — mens forsiden
  og de statiske sider svarede 200. Fire forekomster rettet til `SGAP_REST::NS`, og `tools/fejlscan.py`
  har fået kontrollen **`class-konstanter`**, der afviser en `SGAP_X::KONSTANT` som klassen ikke
  definerer, med to selftest-fixtures. Ingen nye dage.
- **7.58.0 (7/11)** — **Facebook-siden som en kanal pluginnet kan styre (P2.6).** `includes/facebook.php` (`SGAP_Facebook`) publicerer, måler og modererer: tekstindlæg går til `/{page}/feed` med det sporede link som `link`, fotoindlæg til `/{page}/photos` med linket i captionen (et `link` på et fotoindlæg ignoreres af Facebook, og så står posten uden klikbar vej). **Direkte publicering er opt-in** — `facebook_direct` står på `no`, fordi en side der kun er forbundet for at blive læst ikke må flytte publiceringen ud af Publer af sig selv; `handles()` svarer først ja, når ejeren har slået den til. Nøglen er et Page access token, ikke et bruger-token, og fejl 190 siger det i stedet for at videresende Metas tekst. Insights læser `post_impressions`, `post_impressions_unique`, `post_reactions_by_type_total` (et objekt, summeret) og `post_clicks`; en metrik Meta ikke har rapporteret endnu udelades frem for at gemmes som 0, og posten tælles som `waiting` og spørges igen — mens et afvist metriksæt (kode 100 på medietyper uden klik) genforsøges med de to metrikker alle indlæg har. Kommentarer læses, besvares og skjules, aldrig slettes, og `can_hide` fra Meta afgør om knappen tilbydes. Gem sletter kun kanalens egne typer (`channel = 'facebook'`), så `likes`/`shares` fra Bluesky, Mastodon og Instagram overlever; Graph-versionen er en indstilling med fallback til `v21.0`, ikke en konstant. Egen side, dashboardkort, REST-ruter, cron i Fejlsøgnings listen og eget forbehold. 82 nye smoke-checks (905 → 987 PASS, 0 FAIL) — alle gennem `$transport`-sømmen, så intet kald er gået til Meta, og siden er ikke set i en rigtig wp-admin.
- **7.57.0 (7/11)** — **fejlsøgning i pluginnet (P1.22), og forbehold som en del af svaret.** Ny side **Steamgears → Fejlsøgning** (`includes/diagnostics.php`) med ni tjek, der hver har en tilstand, en sætning om hvad der blev fundet og en om hvad man gør — også når rækken er grøn. Tilstanden læses af det modulerne allerede ved (cron, tabeller, token, sidste kørsel, fejlede posts), så siden ikke selv kan blive en ny fejlkilde; **en kanal der aldrig er forbundet er `off`, ikke `bad`**. Ved siden af ligger `sgap_error_log`: en ringbuffer på 40 rækker, fordi et modul kun holder én fejltekst og rydder den ved næste kørsel — et natligt afslag kl. 03 var ellers væk kl. 09. Den samme fejl tælles op i stedet for at duplikere, og rækken har både `at` (sitets ur, til visning) og `ts` (`time()`, til regnestykker), så 24-timers-vinduet ikke arver den gamle `gmdate`-fejl. **Forbeholdet står under hvert moduls tal** (`SGAP_UI::caveat()`): Instagrams metric der ikke er rapporteret endnu er *udeladt*, ikke 0; Pinterest afgrænses til sin kanal; ROI er last-click uden ordrer uden klik; et opslag der aldrig kom afsted har ingen tal. Dashboardkortet vises kun når der er noget at sige, og rapporten kan hentes som `GET /sgap/v1/diagnostics`. 67 smoke-checks (838 → **905**, 0 FAIL).
- **7.56.0 (7/11)** — **Instagram-insights (P1.5c): den tredje kanal hvor platformen ved noget vi ikke selv kan
  måle.** `includes/instagram.php` (`SGAP_Instagram`) læser `GET /<media id>/insights` for kontoens medier og
  gemmer `views`, `likes`, `comments`, `shares` og `saved` som **Instagram-egne event-typer**, adskilt fra vores
  `/go/`-klik, ordrerne og de to andre platformes snapshots. Tre ting i API'et former koden: **`impressions` er
  deprecated** for medier efter 2/7 2024 og afløst af `views`, så et nyt opslag ellers ville rapportere nul; **en
  metric Instagram ikke har endnu udelades af svaret i stedet for at være 0** (op til 48 timers forsinkelse), så
  opslaget tælles som *waiting* og gemmes ikke som et opslag uden visninger; og et medie har **intet link-felt**,
  så matchningen sker på captionen mod `/go/<id>/` — et opslag uden vores link er *unmapped*, et `/go/`-id uden
  en publiceret Instagram-række er *orphaned*, og begge står på dashboardkortet. De to login-typer taler med hver
  sin Graph-vært, så værten er en indstilling i stedet for et gæt; den 60-dages token fornyes **på stedet** med
  `grant_type=ig_refresh_token` (der findes ingen separat refresh-token) og gemmes tilbage, og en token i
  `wp-config.php` afvises. Medie-listen pagineres med en cursor der kan genoptages, hver kørsel bruger højst 20
  insights-kald, og en medie-type der afviser en metric får ét smallere forsøg med de tre alle typer har. 50 nye
  smoke-checks (770 → **838 PASS**, 0 FAIL). Undervejs blev tre fejl fanget og rettet: test-doublen manglede
  `WP_Error::get_error_code()`, det smallere metric-forsøg slugte token-fejl, og token-fejlen blev returneret før
  fornyelsen blev forsøgt. **Ikke kørt live** — intet Instagram-token findes i repoet.
- **7.55.0 (7/11)** — **de sidste to lister (P1.21):** AI Drafts er grupperet efter den side kladderne er skrevet til, med kladder/sider/kanaler som tal og en gruppe for kladder uden kilde; Publish log har filteret som egen sektion og *Attempts* med Sent/Failed/Requeued talt op fra rækkerne på skærmen. Syv smoke-checks (781 → **788 PASS**).
- **7.54.0 (7/11)** — **sektionsmønstret på de øvrige sider (P1.20):** Settings-fanerne åbner med overskrift og én linje om hvad de afgør, Queue er delt i *Waiting* og *Done* med en *Held*-linje der oversætter blokeringen til ord, og Content Studio er delt i *Write* og *Fix*. Undervejs kom en skjult gren frem: smoke-harnessen manglede `mysql2date()`, fordi hver kø-række hidtil havde `null` som tid. 11 smoke-checks (770 → **781 PASS**).
- **7.53.1 (7/11)** — **fejlsøgning som fast punkt (P3.6), og fem fejl den fandt.** `tools/fejlscan.py` er
  værn nr. 23–24 og kontrollerer version/zip-paritet, døde og forældreløse includes, biblioteksindekset, tokens i
  sporede filer, TODO-markører og tests der ikke kan fejle — med 13 fixtures der beviser at hver klasse fanges.
  Fundene den gav er rettet: en `wp-config.php`-konstant for access-tokenet blev overskygget af det roterede
  token; et indklistret token uden udløbsdato blev kaldt ugyldigt i `status`; et afslag på pin-listen stoppede
  målingen af de pins der allerede var kendt; `gmdate()` blev sammenlignet med rækker skrevet på sitets ur; og
  engagement-testen endte i en altid-sand klausul. Desuden er §5's P0- og P1-totaler rettet til 5,95 d og
  15,20 d, så de summer som `projektstatus.json`. 770 smoke-checks, 0 FAIL.
- **7.52.0 (7/11)** — **dashboardet læses oppefra (P1.19):** tre sektioner — *Now*, *Channels*, *System* — med hvert kort under det spørgsmål det svarer på, i stedet for to kort i venstre kolonne og otte i højre. Én *Needs you*-linje øverst nævner kun det et menneske skal gøre (dumpet AI-test, self-check-problemer, blokeret publicering, kladder der venter), og siger *All clear* når der intet er. Engagement-kortet får tre tal over tabellen, og `SGAP_UI::section()`/`metric()` plus `forced-colors`-regler gør mønstret genbrugeligt. Otte smoke-checks (706 → **714 PASS**).
- **7.51.0 (7/11)** — **P0.7c dashboard-observability:** Autopilot-dashboardet har nu et read-only scheduler-kort med backend, AS-version, næste publicering og målt forsinkelse (et manglende publiceringsjob vises som fejl); WP-Cron-nødudgangen nævnes, når `DISABLE_WP_CRON` er aktiv. Engagement-kortet summerer de eksisterende Bluesky-/Mastodon-snapshots for 30 dage og viser en forklarende tom tilstand før første refresh. Seks smoke-checks dækker begge views (700 → **706 PASS**).
- **7.50.0 (7/11)** — **P0.7b alle jobs gennem adapteren:** aktiveringens fem jobs, publicerings-tick'et,
  den daglige self-check, ugentlig artikel/mail, rescue-køen og MCP-fornyelsen planlægges nu med
  `SGAP_Scheduler::recurring()`/`single()` i stedet for direkte WP-Cron-kald. `cron_lag()` og Site Health læser
  næste kørsel gennem adapteren, så »15 minutter bagud« måles rigtigt uanset backend, og rådgivningen skelner
  mellem de to. `unschedule()` rydder begge backends, så et skift ikke efterlader en tvilling, og `uninstall.php`
  fjerner vores AS-actions. Nødudgang: `define('SGAP_SCHEDULER_WP_CRON', true)` eller filteret
  `sgap_scheduler_action_scheduler`. 10 nye smoke-checks (690 → **700 PASS**).
- **7.49.0 (7/11)** — **P0.7 scheduler-adapter:** `includes/scheduler.php` vælger Action Scheduler når WooCommerce
  leverer den (`as_schedule_recurring_action` m.fl., gruppe `sgap`) og falder ellers tilbage til WP-Cron.
  `recurring()`/`single()`/`next()`/`unschedule()` er idempotente, og `health()` rapporterer backend uden
  side effects. Engagement-cron'en kører nu gennem adapteren. 10 nye smoke-checks (689 → 699 PASS).
- **7.48.0 (7/11)** — **P1.5a:** Bluesky- og Mastodon-engagement læses ind i `sgap_events` (`likeCount`/`repostCount`/`replyCount`, `favourites_count`/`reblogs_count`/`replies_count`), dagligt via `sgap_engagement_refresh` og `POST /sgap/v1/engagement/refresh`. Snapshottet erstattes pr. queue-række, og reach står som `null`, fordi protokollerne ikke har tallet.
| Date | Plugin | Change |
| --- | --- | --- |
| 2026-11-10 | 7.70.1 | **OpenRouter-headeren sendes nu fra én bygger (P1.25).** Nøgletesten og failoveren byggede hver deres `Authorization`-header, og nøglen blev kun trimmet — et linjeskift eller et usynligt tegn inde i værdien får transporten til at kaste headeren, og OpenRouter svarer `401 Missing Authentication header`, præcis som ved en forkert nøgle. Nu er `SGAP_AI::openrouter_headers()`/`openrouter_post()` den eneste vej for test, failover og generator, `SGAP_AI::bearer()` fjerner mellemrum, linjeskift og usynlige tegn, og `SGAP_AI::stripped_by()` opdager og navngiver et `http_request_args`-filter der fjerner headeren. 11 nye smoke-checks ser på den udgående request; 4 af dem fejler uden rensningen. 1 443 PASS / 0 FAIL. |
| 2026-11-10 | 7.69.1 | **Nøgletestens diagnose (P1.25).** OpenRouters svar `401 Missing Authentication header` blev vist som »OpenRouter refused the key«, selv om den gemte værdi ikke var en OpenRouter-nøgle. Nu tjekkes formen først: nøgler starter med `sk-or-v1-`, og alt andet navngives med sin længde og en henvisning til openrouter.ai/keys uden at bruge en request. Ser nøglen rigtig ud og svaret stadig mangler en header, siges det, med en linjeskift- eller proxy-forklaring. Alle nøgler trimmes desuden før de bliver en `Authorization`-header. 5 nye smoke-checks (1353 → **1358 PASS**, 0 FAIL). |
| 2026-11-10 | 7.67.1 | **OpenRouter som automatisk backup (P1.24).** AIMLAPI-kontoen løb tør for kredit 7/11, og indtil nu standsede det hver generering, artikel og chat-svar indtil nogen skiftede provider i hånden. Backuppen har sin egen nøgle (`openrouter_api_key`) og sit eget model-felt (`openrouter_model`, valgt fra OpenRouter's live liste — intet gættet standard-slug), fordi `ai_api_key` hører til den valgte udbyder. Den kaldes **kun** ved udbyderfejl: transport, 401/403, 402, 429 og 5xx; et 2xx-svar — også tomt eller afkortet — starter den aldrig, og 400/404/422 rapporteres som vores egen fejl. `SGAP_AI::chat_completions()` er nu den eneste vej til `/chat/completions`, så generatoren, Content Studio og AI Chat deler samme failover; backup-bodyen er identisk minus `reasoning_effort`, som kun AIMLAPI kender. Hver failover giver én række i fejlloggen (`ai-fallback`) og en sætning i chat-svaret — aldrig nøglen — og nøgletesten prøver backup-parret (den nøgle, den model) i stedet for primærens model. Mens OpenRouter selv er provideren, er backuppen slået fra. 30 nye smoke-checks (1286 → **1316 PASS**, 0 FAIL). **Ikke målt live:** ingen OpenRouter-nøgle findes i sandkassen. |
| 2026-11-09 | 7.67.0 | **OpenRouter som egen tekst-provider (P1.23).** AIMLAPI-kontoen løb tør for kredit 7/11, og den eneste anden vej var *Other OpenAI-compatible* med håndskreven base-URL og modelnavn — hvor nøgletesten spurgte `/models`, som OpenRouter svarer uden nøgle, så en død nøgle blev rapporteret som virkende. `openrouter` er nu et eget valg: `SGAP_AI::OPENROUTER_URL` er fast, `SGAP_AI::base_url()` ignorerer det håndskrevne felt for den provider, `SGAP_AI::provider_name()` siger OpenRouter i dashboard, chat og nøgletest, og `SGAP_KeyCheck::check_openrouter()` bruger ét token på den valgte model og oversætter 401/403, 402, 404/400 og 429 til hver sin sætning med den rigtige rettelse. Der er ingen standardmodel: kataloget hentes fra `/models` (cachet 12 timer under `sgap_ai_catalog_openrouter` og ryddet når nøglen roteres), og et tomt modelfelt fejler med providerens navn frem for at gætte et slug. 9 nye smoke-checks (1277 → 1286 PASS, 0 FAIL). |
| 2026-11-08 | 7.65.0 | P2.5 made visible: **the Queue page now says what is a translation.** The rows already carried `locale` and `source_id`; the page did not read them, so a translated caption looked like any other post. The table (now `queue_table()`, after P1.20 split the page into *Waiting* and *Done*) has a *Language* column — the language in a `<code>` tag plus *original* or *translation* — and a *Source* column that links a translation to the queued post it came from, with that post's title beside it; every row has an `id`, so the link jumps straight to the original. One query with a self-join on `source_id`; a source that has since been deleted still shows its id rather than a guess, and titles are escaped like every other field. 8 new smoke checks in `plugins/tests/queue-page.php` (1128 → 1136 PASS, 0 FAIL) render the page with a source row, a translation, a deleted source and a title containing a script tag. |
| 2026-11-07 | 7.56.0 | **Instagram-insights ind i pluginnet (P1.5c).** `includes/instagram.php` (`SGAP_Instagram`) læser `GET /<media id>/insights` og gemmer `views`, `likes`, `comments`, `shares` og `saved` som Instagram-egne event-typer, adskilt fra vores `/go/`-klik, ordrerne og de to andre platformes snapshots. `views` afløser `impressions`, som er deprecated for medier efter 2/7 2024 — at spørge om `impressions` ville have rapporteret nul for hvert nyt opslag. En metric Instagram endnu ikke har rapporteret **udelades af svaret i stedet for at være 0** (op til 48 timers forsinkelse), så opslaget tælles som *waiting*; et medie har intet link-felt, så matchningen sker på captionen mod `/go/<id>/`, og *unmapped*/*orphaned* rapporteres i stedet for at blive gættet. De to login-typer taler med hver sin Graph-vært, så værten er en indstilling; den 60-dages token fornyes på stedet med `grant_type=ig_refresh_token` og gemmes tilbage, og en token i `wp-config.php` afvises. Cursor-pagineret medie-liste, højst 20 insights-kald pr. kørsel, og ét smallere metric-forsøg hvis en medie-type afviser en metric. 50 nye smoke-checks (770 → **838 PASS**, 0 FAIL). **Ikke kørt live** — intet Instagram-token findes i repoet. |
| 2026-11-07 | 7.55.0 | **AI Drafts og Publish log i sektioner (P1.21).** Kladderne lå i ét grid sorteret efter id, så man skulle læse hver kladde for at se hvilken side den hørte til — nu er de grupperet efter siden, med antal pr. gruppe og tre tal øverst (kladder, sider, kanaler), og en kladde uden kilde får sin egen gruppe i stedet for at forsvinde. Log-siden havde filter, tabel og paginering som tre løse bjælker; nu står filteret som en sektion med levetiden i forklaringen, og *Attempts* tæller Sent/Failed/Requeued op fra de rækker der faktisk vises. Syv nye smoke-checks (781 → **788 PASS**, 0 FAIL) — bl.a. at log-siden nu kræver at harnessen svarer på `SHOW TABLES` før den svarer på antallet. |
| 2026-11-07 | 7.54.0 | **Sektionsmønstret er nu på alle fire hovedsider (P1.20).** Settings-fanerne åbnede direkte på felt-rækker uden at sige hvad de afgjorde; nu har hver fane en overskrift og én linje (`TAB_HINTS`), og Keys beholder sin egen. Kø-siden var én tabel hvor »venter« og »sendt« stod blandet sammen — nu to sektioner, *Waiting* og *Done*, med optælling i overskriften og en *Held*-linje der oversætter quiet hours, blackout, dagligt loft og slukket autopilot til ord. Content Studio var fire kort i to gentagne grids — nu *Write* og *Fix* med resultatet under hver. Undervejs kom en rigtig fejl frem: smoke-harnessen havde ingen `mysql2date()`, fordi kø-tabellen aldrig var blevet renderet med en dato på; den er stubbet nu, og de nye checks bruger en kø-række med både planlagt og publiceret tid. 770 → **781 PASS**, 0 FAIL. |
| 2026-11-07 | 7.53.1 | **Fejlsøgning som fast punkt (P3.6) — og de fem fejl den fandt.** `tools/fejlscan.py` scanner på tværs af filer efter det `php -l` og smoke-suiten ikke kan se: version tre steder (konstant, header, zip-navn), en zip der ikke er byte-identisk med kilden, `require` til en fil der ikke findes eller en fil i `includes/` som ingen kræver, nye dokumenter uden for `docs/library/README.md`, tokens i sporede filer, `TODO`/`FIXME` og tests der ikke kan fejle. 13 fixtures i `--selftest` beviser at hver klasse fanges og at en ren kasse er grøn, og værnet kører som nr. 23–24 i den natlige kørsel. Fundene er rettet: et access-token i `wp-config.php` blev brugt til at hente data, men fornyelsen skrev til en option som konstanten altid overskyggede (nu afvises fornyelsen med konstantens navn); et indklistret token uden registreret udløb blev rapporteret som ugyldigt i `status`; et afslag på pin-listen stoppede hele kørseln i stedet for at måle de pins der allerede var kendt; vinduerne blev målt med `gmdate()` mod rækker skrevet med `current_time('mysql')` (op til 14 timers forskel, nu `wp_date()`); og engagement-checken `summary()==[] \|\| true` kunne aldrig fejle og læste den forrige tests kø-rækker som snapshot-kolonner. Samme pas: §5's P0-total 5,25 → **5,95 d** og P1-total 13,30 → **15,20 d**, så de summer som `projektstatus.json`. 770 smoke-checks, 0 FAIL. |
| 2026-11-07 | 7.53.0 | **Pinterest-analytics ind i pluginnet (P1.5b).** `includes/pinterest.php` (`SGAP_Pinterest`) læser `GET /v5/pins/{id}/analytics` for de pins kontoen ejer og gemmer `IMPRESSION`, `SAVE` og `OUTBOUND_CLICK` i `sgap_events` som **tre egne event-typer** — adskilt fra Bluesky/Mastodons likes/reposts/replies og fra vores egne klik (`sgap_cro`) og ordrer, så ingen af tallene blandes sammen i en sum. Pin→kø-matchningen sker på pin'ets `link` mod `/go/<id>/` og aldrig på titel: et fremmed link tælles som *unmapped*, og et `/go/`-id uden en publiceret Pinterest-post i vinduet som *orphaned*, og begge tal står på det nye dashboardkort **Pinterest · last 90 days** i stedet for at blive gættet. 90 dage er Pinterests eget loft, så `start_date` hæves til dagens dato minus 89 dage for en ældre post; pin-listen følger `bookmark` over flere kørsler, og hver kørsel bruger højst 25 analytics-kald, så en backlog drænes fair (ældste snapshot først). Fornyelsen er en form-encoded POST til `/v5/oauth/token` med HTTP Basic, og fordi Pinterest **roterer refresh-tokenet ved hver fornyelse**, gemmes det nye par før noget andet kan fejle; en konstant i `wp-config.php` afvises med en forklaring, og et afvist token når det daglige selvtjek. Et indklistret Trial-token virker uden app-credentials, men `status` siger højt at det ikke kan forny sig selv. 52 nye smoke-checks (714 → **766 PASS**, 0 FAIL) dækker rotationen, 401-genforsøget, 90-dages-gulvet, bookmark-pageringen, budgettet, de tre fejlklasser (401/403/429) og at platformstal ikke blander sig med vores egne. **Ikke kørt live** — intet Pinterest-token findes i repoet eller i sandkassen. |
| 2026-11-07 | 7.52.0 | **Dashboardet læses oppefra (P1.19).** Det gamle layout lagde to kort i venstre kolonne og otte i højre — den ene side tre skærmhøjder lang, den anden næsten tom — så spørgsmålet »går det?« krævede rul ned ad en tilfældig rækkefølge. Nu tre sektioner: **Now** (Run now, AI Chat, Self-check, Scheduler), **Channels** (tal pr. kanal, engagement, RSS-feeds, API-nøgler) og **System** (opdatering, Command Center, billed-rescue). Øverst én *Needs you*-linje med kun det et menneske skal gøre — AI-nøgle der dumpede sin test, self-check-problemer, blokeret publicering, kladder der venter — og *All clear* når der intet er. Engagement-kortet viser likes/reposts/replies som tre tal over per-kanal-tabellen. Nye helpers `SGAP_UI::section()` og `SGAP_UI::metric()`, sektions-overskrifter med udtonet linje, metrik-blokke og `forced-colors`-regler til kort/pills/tabs/progress-bars. Otte nye smoke-checks (706 → **714 PASS**, 0 FAIL). |
| 2026-11-07 | 7.46.0 | **P0.4 er nu verificeret, ikke bare påstået.** Rækken lovede »smoke-checks«, men der var ingen: `smoke.php` stubber `wp_next_scheduled()` til altid at svare false, så `cron_lag()` var 0 i hver eneste test, og hverken den grønne eller den kritiske gren af Site Health-testen blev nogensinde kørt. Stubben læser nu `$GLOBALS['next_scheduled']` (default 0, som før), og fire checks dækker registreringen, »good« ved 5 minutters forsinkelse, »critical« ved 45 og at rådgivningen bærer den præcise `wp-cron.php`-linje med 5-minutters-intervallet. **P0 er dermed 4,65 af 4,7 d** — de sidste 0,3 d af P0.4's 0,5 d var netop testen og dokumentationen. 662 → **666 PASS**, 0 FAIL. |
| 2026-11-07 | 7.46.1 | **CI var rød på WordPress 7.1 og nyeste — rækkefølgen i evergreen-rotationen var ikke deterministisk.** `SGAP_Evergreen::candidates()` sorterede udelukkende på tidspunktet for seneste share. To sider der **aldrig** er delt har samme tid (0), og så afgjorde databasen rækkefølgen: på WordPress 6.4 kom den ene side først, på 7.1 den anden. Det er ikke kun en test der blev træt — det er rotationen på sitet, der skiftede med MySQL-versionen, så »ældste share først« kunne blive til »hvad tabellen nu returnerede«. Rettelsen er et sekundært sorteringsled: `[last_share, id]`, så den ældste share kommer først og id bryder tie'en; nu er rækkefølgen den samme overalt. Desuden talte to CI-checks stadig 35 abilities efter `woo-cost-import` gjorde dem til 36 (`plugins/tests/admin-tools.php`). Målt lokalt med rigtig MariaDB og WordPress: 7.1 og 7.1.4 giver begge **249 PASS, 0 FAIL** (før rettelsen: 147 PASS, 1 FAIL på 7.1). |
| 2026-11-07 | 7.47.0 | **Alle nøgler står nu på én skærm (P1.18).** En nøgle lå på den fane der tilfældigvis nævnte den — AI-nøglen under *AI & images*, Publer-nøglen under *Publishing*, feed-hemmeligheden under *Shop & feeds*, MCP-tokenet under *MCP access* — og Bluesky-handle, Mastodon-instans og Telegram-chat-id stod slet ingen steder. `includes/keys.php` (`SGAP_Keys`) ejer nu alle ti hemmeligheder: ét kort pr. tjeneste med ikon, formål, hvor nøglen hentes (link til tjenesten, eller til ejerens egen Mastodon-instans), status-pill og den sidste test. Keys-fanen ligger først, og `settings_page()` filtrerer `SECRET_KEYS` væk fra alle andre paneler, så en nøgle der senere skrives på den forkerte fane stadig kun vises ét sted. Guiden spørger kun efter AI-nøglen så længe ingen er gemt, og peger ellers på Keys. Selve udseendet er strammet op i samme pas: kapsel-formet tab-bar og gem-linje der hænger fast, en *Unsaved changes*-pill drevet af JS, blødere kort, 40 px felter med synlig fokus-ring og én kolonne under 782 px — stadig pluginnets egen stylesheet, uden build-step og uden `@wordpress/ui`. 19 nye smoke-checks (643 → **662 PASS**, 0 FAIL). |
| 2026-11-07 | 7.46.0 | **Kostprisen kan nu importeres fra Printful (P1.17).** `_sgap_cost` var det eneste kostpris-felt værktøjerne ville tro på, og det var tomt — så hver eneste prisprofil blev afvist med rette. `woo-cost-import` læser Printfuls synkroniserede varianter (pagineret, seks timers cache, `fresh` rydder den) og skriver **variantens egen pris** ind som varens kostpris, matchet på Woo-id først og SKU derefter, aldrig på navnet. Første kald er en tørkørsel; intet skrives uden `confirm`. En kostpris du selv har skrevet vinder: uden `overwrite` meldes den som `kept`, og et genkørsel melder `unchanged` i stedet for at skrive samme tal igen. En variant uden egen pris meldes `no_price` og røres ikke — en kostpris der kun ligner en kostpris er værre end ingen — og en vare Printful ikke kender er `unmatched`. Et variabelt produkt får hver variants pris, og forælderen den laveste. Kilden gemmes ved siden af tallet (`_sgap_cost_source`), så `woo-catalog` kan sige hvor prisen kom fra. 16 nye smoke-checks (627 → **643 PASS**, 0 FAIL). |
| 2026-11-07 | 7.45.1 | **Shop-værktøjerne skriver ikke længere selv teksten (P1.16).** `woo-propose` havde en `generate`-gren med egen prompt og eget JSON-skema — et tredje tekstlag oven på Content Studio og den MCP-klient der allerede er en assistent, med en ekstra regning til AIMLAPI for hvert forslag. Grenen er væk: værktøjet tager den tekst der sendes, bærer resten uændret videre og bruger kræfterne på det der ikke fandtes i forvejen — kostprisen, gulvet og køen. Samtidig blev registreringen lagt ind i det eksisterende katalog: `SGAP_MCP_Tools::all_definitions()` lægger shop-rækkerne sammen med resten, så der er **én** registrering, **én** capability-callback og **ét** sæt ability-metadata i stedet for to. Fire smoke-checks fjernet, én tilføjet: intet i noten påstår at have skrevet teksten, og `write_copy` findes ikke (627 PASS, 0 FAIL). |
| 2026-11-07 | 7.45.0 | **WooCommerce/Printful er nu ét modul i pluginnet (P1.16).** Den separate bro kunne ikke virke: `/tools` var en REST-rute uden JSON-RPC, tokenfornyelsen ramte `api.aimlapi.com/v1/oauth/token` som svarer **404**, standardmodellen `anthropic/claude-3-5-sonnet` findes ikke længere i AIMLAPI's katalog (målt: 951 poster, nul 3.5-id'er), og »margin-gulvet« blev regnet som 40 % af den pris det skulle beskytte — en kontrol der aldrig kunne fejle. `includes/mcp-woo.php` samler det brugbare i kategorien `steamgears-tools`: `woo-catalog`, `woo-propose` (dry-run), `woo-queue`, `woo-apply` og `woo-discard`. Kostprisen opfindes aldrig: `_sgap_cost` først, derefter Printful-variantens egen pris matchet på Woo-id eller SKU, ellers **afvises** kaldet med begge rettelser navngivet. Gulvet er `floor(kostpris × (1 + margin)) + 0,95`, hævet med én når det ville lande under marginen, og det genberegnes ved apply — så en kostpris der er steget mens forslaget ventede, hæver prisen i stedet for at bryde løftet. Intet token-lag: sitets MCP-auth beskytter værktøjerne, og Printful-tokenet er en secret med autoload off. 43 nye smoke-checks (584 → **627 PASS**, 0 FAIL). |
| 2026-11-07 | 7.44.0 | **MCP-fornyelsen kører nu på sitet selv (P1.15).** Novamira-tokenet lever 3600 s, og refresh-tokenet roteres ved hver fornyelse — indtil nu blev det holdt i live af et worktree-loop eller et GitHub-workflow med secrets, og begge dele holder op. `includes/mcp-auth.php` (`SGAP_MCP_Auth`) fornyer nu hvert 30. minut på sitets egen WP-Cron, verificerer med `initialize` + `Mcp-Session-Id` + `tools/list` og gemmer det roterede token **før** noget andet, så et afbrudt kald ikke koster en ny godkendelse. Client ID er en almindelig indstilling, refresh-tokenet er en secret (autoload off), og et token i `wp-config.php` afvises fordi serveren ikke kan skrive rotationen tilbage. Et token der ikke virker bliver aldrig meldt som fornyet: API'ets egen grund gemmes, vises af det nye læsende værktøj `mcp-status` og rejses i det daglige selvtjek, og `mcp-refresh` fornyer på kommando. Indstillingerne ligger under *Settings → MCP access*. 20 nye smoke-checks (564 → **584 PASS**, 0 FAIL). |
| 2026-11-07 | 7.43.0 | **Kanal-cap er nu et reelt loft i publisheren.** `SGAP_Queue::full_channels()` finder de kanaler med cap der har publiceret eller sender deres cap i dag (`published_at` eller `sending` siden midnat), og `claim()` springer dem over i udtrækket. De øvrige kanaler i køen går stadig ud, og den fulde kanals poster bliver i køen til i morgen. Uden `channel_caps` ændrer intet sig. 3 nye smoke-checks (559 PASS, 0 FAIL). |
| 2026-11-07 | 7.42.0 | **Hver kanal har nu sin egen runway — det hul P0.2 selv pegede på.** Runway blev målt på hele køen, så 40 Facebook-poster kunne skjule at Pinterest stod tom: totalen sagde fem dage, mens én kanal stoppede i morgen. Ny indstilling *Kanal-cap* (Settings → Alerts, `channel_caps`, fx `pinterest=2, facebook=3`): `SGAP_Alerts::caps()` læser den og ignorerer ukendte kanaler, tomme navne og tal uden for 1–50, så en tastefejl ikke giver en evig alarm; `channel_runway()` grupperer køen pr. kanal og måler hver kanal mod sin egen cap — en kanal uden cap mod `daily_cap`. `dry_channels()` rapporterer dem under advarselsniveauet, og en kanal **med** cap tæller altid (en cap er et løfte, så en tom kanal er et hul), mens en kanal uden cap kun tæller så længe den har noget i køen. I *immediate* får de tørre kanaler deres egen mail (»Steamgears: pinterest is running dry«) med kø, cap og dage, dedupliceret pr. kanal-sæt; i *daily* har digesten en kanal-linje og hele tabellen, og den springes ikke længere over når kun én kanal er tør. 11 nye smoke-checks, alle på stubbede kø-rækker (544 → **555 PASS**, 0 FAIL). |
| 2026-11-06 | 7.41.1 | **Fatal fejl i den ugentlige site-scanning rettet.** `SGAP_Site_SEO::site_checks()` byggede fire URL'er med `home . '…'` i stedet for `$home . '…'`; i PHP 8 er en ukendt konstant en `Error`, så `admin_init`-hooket dræbte hele wp-admin-requesten. Det ramte **én admin-request i døgnet** (guarden sættes før scanningen), og det skete på live-sitet 6/11 kl. 19:55:00 — to minutter før butikkens fire plugins blev deaktiveret. Rettet på fire linjer; ny smoke-check `site seo: the domain checks run without a fatal error` dør med samme fejl hvis den genindføres. Zip 7.41.0 genopbygget. |
| 2026-11-06 | 7.41.1 | **Nøgletest for Anthropic og egne OpenAI-kompatible endpoints — og en cachefejl fundet af testen.** Fem nye integrationstests: Anthropic-kaldet skal gå til Models API med `x-api-key` og `anthropic-version`, og en custom provider skal gemme et modelnavn fra sit eget katalog, bestå med `Bearer`-nøglen mod `/models`, og afvise en forkert nøgle. Testen afslørede at `ai_base_url` skiftede endpoint, mens modelkataloget blev liggende i 12-timers cachen under provideren; et gyldigt modelnavn blev derfor afvist mod den gamle listes id'er. `SGAP_Settings::clear_model_catalog()` rydder nu de tre katalog-transients når base-URL'en ændres. API-nøglen rydder bevidst ikke: en ugyldig nøgle giver en tom liste, og så må modelvalideringen ikke begynde at acceptere hvad som helst. 5 nye checks (244 → **249 PASS**; 6.4: 179 → **184 PASS**), 0 FAIL. **Chat-loopet er også dækket:** seks nye smoke-checks kører både det OpenAI-kompatible og det Anthropic wire-format med et rigtigt værktøjskald, kræver resultatet tilbage under samme call-id og præcis to runder. Smoke 524 → **530 PASS**. |
| 2026-11-06 | 7.41.0 | **WordPress 6.4 kører også grønt.** Integrationstesten er kørt på ny mod 6.4 og 7.1 efter P1.4: 6.4 giver **179 PASS / 0 FAIL**, 7.1 og latest (7.1.4) giver **244 PASS / 0 FAIL**. De 65 checks der ikke kører på 6.4, er præcis ability-grupperne: 51 i `admin-tools.php`, 9 i `integration.php`'s abilities-gruppe, 4 ability-gated checks i `check-keys`/`update`/evergreen og 1 i `automation.php`; de springes over fordi Abilities API først findes fra 6.9. Ingen plugin- eller scriptrettelse var nødvendig. Kørt med CI's PHP 8.2.32 + MySQL 8.0.36; 6.4 er også kørt på PHP 8.5.8 med samme resultat, men med deprecations fra kernen og wp-cli, mens `PASS no plugin notices in debug.log` bestod. Matrixdokumentet er opdateret. |
| 2026-11-06 | 7.41.0 | **Evergreen-kørslen er nu dækket i integrationstesten mod rigtigt WordPress.** 20 nye checks i `plugins/tests/integration.php` markerer to rigtige indlæg, kører `SGAP_Evergreen::run()` med en mocked Anthropic-model og beviser at begge shares rammer køen på facebook/angle_a med caption og hashtags; at share-tallet og datoen gemmes; at en anden kørsel samme dag ikke gør noget; at næste share efter 61 dage roterer til instagram/angle_b og får den nye AI-tekst; at prompten bærer de tidligere captions som *do-not-repeat*; at budgettet lukker siden med en grund; at opsummeringen og `sgap_last_evergreen` stemmer; og at unmark rydder mark, budget og tal. `integration.sh` tjekker også at deaktivering fjerner `sgap_evergreen`-cron'en. Kørt lokalt mod WordPress 7.1.4 + MySQL 8.0.36 (PHP 8.5.8): **244 PASS / 0 FAIL**. Testen fandt to ting undervejs: den brugte `SGAP_Generator::ANTHROPIC_URL` (konstanten ligger på `SGAP_AI`), og `admin-tools.php` forventede stadig 32 abilities efter P1.4's tre nye — nu 35. Smoke stadig 523 PASS. |
| 2026-11-06 | 7.41.0 | **Evergreen-sider kommer tilbage med en ny vinkel (P1.4).** `includes/evergreen.php` (`SGAP_Evergreen`) markerer et indlæg eller produkt som evergreen — i editor-sidebaren, på den nye **Evergreen**-side eller med `mark-evergreen` — og genbruger det efter `evergreen_after_days` (standard 60) med en frisk variation til næste kanal og vinkel. Budgettet er pr. side (standard 3) og kan udløbe på en dato; begge kan overstyres i editoren. Variationsprompten får sidens titel, kanal, vinkel og de sidste captions og bliver bedt om ikke at gentage kroge eller opfinde tilbud; filtret `sgap_evergreen_variation` kan sætte et menneske eller en anden model i stedet. Hver share går gennem den almindelige kø, så quality gate, pre-flight, blackout og dublet-værn gælder, og en side med en post i køen springes over i stedet for at blive dobbeltpostet. Egen daglig cron, metabox, admin-side med *Recycle now* og tre abilities. 29 nye smoke-checks (494 → **523 PASS**, 0 FAIL). |
| 2026-11-06 | 7.40.1 | **Den anden skribent kan ikke længere køre den samme post ind to gange (P0.6).** Køen har flere skribenter — AI-generatoren, Studio, REST-API'et, en MCP-klient og Command Center — og Command Center synkroniserer hvert 30. minut, så et dashboard der gentager en overdragelse kunne poste dobbelt. `includes/duplicate.php` (`SGAP_Duplicate`) bruger det fingerprint databasen allerede indekserer (`channel\|destination_url\|title`, sha256) og afviser nr. to med **409**, nu med en besked der navngiver rækken (*Already queued as #412 (queued, 2026-11-08 10:00)*) i stedet for det tidligere *Already queued.*, plus `id` og `fingerprint` i error-data. Tidsvinduet er nyt: en post der **stadig står i køen blokerer uanset alder**, en publiceret blokerer kun inden for `dupe_days` (Settings → Publishing, standard **30**, `0` = hele historikken), så en evergreen-titel må gentages næste kvartal, og `failed` blokerer aldrig — en post der gav op er ejerens at prøve igen. `/snapshot` bærer `fingerprint` på hver kø-række, så en ekstern dashboard kan genkende sin egen overdragelse. 15 nye smoke-checks (479 → **494 PASS**, 0 FAIL). |
| 2026-11-06 | 7.40.0 | **Log ind-knap til Command Center.** Ny indstilling *Command Center URL* (General). Når den er sat, vises knappen »Log ind i Command Center« på dashboardet efter AI Chat-kortet og åbner dashboardet i en ny fane. Tom værdi skjuler knappen. Pluginet kalder ikke Command Center; linket er kun en genvej. Ikke verificeret i en rigtig WordPress-installation endnu. |
| 2026-11-06 | 7.39.2 | **Integrationstesten er nu faktisk kørt mod et rigtigt WordPress — og den fandt en fejl i modelvalget.** `plugins/tests/integration.sh` havde aldrig været afviklet, så «grøn i CI» var en antagelse. Kørslen her (MariaDB 11.4.5 + WordPress 7.1.4 + wp-cli, PHP 8.3 med `mysqli`) startede med 1 fatal og 12 røde checks. Det egentlige fund: **et providerskift efterlod den gamle providers konkrete model i `ai_model`**, fordi 7.39.1 gemmer den model *Provider default* resolver til — en side der skiftede fra Anthropic til AIMLAPI blev derefter kaldt med `claude-opus-5-5`, som AIMLAPI ikke udbyder, og hvert første kald fejlede indtil fallback'en havde repareret det. `SGAP_Settings::keep_provider_model()` hører nu på `update_option_sgap_ai_provider` og erstatter en gemt model den nye provider ikke udbyder med dennes standard; kan kataloget ikke læses, røres intet. Resten var testens opsætning: HTTP-mocken kendte ikke det `/v1/models`-kald som 7.39.0 indførte (den svarede på et GET som om det var en chat-POST), `hello-dolly` fra en tidligere kørsel fik «allerede installeret» til at fejle, WordPress' egen gættede URL gav `/wordpress` i alle absolutte links, den gemte model blev afvist af den nye gemme-kontrol uden at testen skrev den som en gammel side har den, og to forventninger i `admin-tools.php` var forældede (`update-settings` er med vilje ikke et chat-tool siden 7.38.3). Testen kan nu køres to gange i træk: databasen droppes, `wp-content/plugins` ryddes, `home`/`siteurl` pinnes til test-URL'en, og et `FAIL` inde i eval-filerne fejler kørslen i stedet for kun at stå i loggen. Fire nye checks beviser de fire modelpunkter i rigtigt WordPress: udgået navn markeres *(not offered any more)*, dropdownen er grupperet med standarden øverst og kun udbudte id'er, et afvist navn gensendes på standarden med en sætning om hvad der blev skiftet væk fra, og *Provider default* gemmes som den model der bruges. **222 PASS / 0 FAIL** i integration, smoke stadig 478 PASS. |
| 2026-11-06 | 7.39.1 | **"Provider default" gemmer nu den model der faktisk bruges, og reparationen gør det samme.** 7.39.0 gemte det tomme felt, når ejeren valgte *Provider default* — så stod feltet tomt og modellen var kun implicit, afhængig af en senere ændring af provider eller standard. `SGAP_Settings::sanitize_model()` gemmer nu den model som valget resolver til (`anthropic/claude-opus-5.5` for AIMLAPI, `claude-opus-5-5` for Anthropic), og `SGAP_AI::repair_model()` skriver samme konkrete id i stedet for at tømme feltet, så både indstillingen og chat-svaret siger hvad der reelt kører. Bevis i stedet for påstand: smoke-suiten kører nu fire sammenhængende trin på den registrerede sanitize-callback — udgået navn vises som *(not offered any more)*, valget *Provider default* gemmer standardmodellen (ikke tomt), feltet viser den som valgt, og det udgåede navn kan ikke gemmes tilbage. Med `SGAP_LIVE_CATALOG=<fil eller 1>` hentes AIMLAPI's eget katalog og **hver eneste `<option>` sammenlignes med de id'er provideren udbyder** (målt 6/11: 776 unikke modeller af 951 poster, ingen afvigelse, standarden øverst). 473 → 478 PASS deterministisk, 482 med live-katalog. |
| 2026-11-06 | 7.39.0 | **Modellen er nu en dropdown over det provideren faktisk udbyder (P1.14).** Feltet var fri tekst, så et gættet eller udgået modelnavn kunne gemmes og lammede derefter hvert AI-kald. `SGAP_AI::catalog()` læser providerens `/models` (AIMLAPI uden nøgle, Anthropic og en egen OpenAI-kompatibel endpoint med den gemte nøgle), `catalog_ids()` fjerner dubletter og sorterer (AIMLAPI gentager id'er), og listen caches i 12 timer — en fejl kun i 30 minutter — så indstillingssiden koster højst ét opslag pr. halve døgn. `SGAP_Setup::model_field()` viser id'erne i `optgroup` pr. udbyder med *Provider default* øverst, lader en gemt model der er udgået stå synligt som *(not offered any more)* i stedet for at skifte den i stilhed, og falder tilbage til et tekstfelt når listen ikke kan læses, så en utilgængelig provider aldrig blokerer resten af formularen. `SGAP_Settings::sanitize_model()` tjekker igen ved gem: et navn provideren ikke udbyder afvises, den gemte værdi bliver stående, og ejeren får en settings-fejl — hverken formularen eller et håndlavet POST kan gemme en utilgængelig model. Et site bag en proxy kan erstatte listen med filtret `sgap_ai_model_catalog`. Ni nye smoke-checks (464 → 473 PASS). |
| 2026-11-06 | 7.38.4 | **Et gemt, ugyldigt modelnavn lammede chatten — nu helbreder kaldet sig selv.** 7.38.3 lukkede vejen til at *skrive* `ai_model`, men en værdi der allerede stod i databasen (fx `claude-3-5-sonnet`) blev ved med at få hvert kald til at fejle indtil ejeren tømte feltet i hånden. `SGAP_AI::chat_call()` er nu den ene vej til `/chat/completions`: bliver modellen afvist som ukendt eller ikke udbudt (`model_rejected()` matcher kun modelfejl — aldrig auth, netværk, rate limit eller kredit), prøves **præcis én gang** på providerens standardmodel, og ved succes tømmes den gemte indstilling, så standarden gælder fra nu af (`repair_model()` rører ikke en værdi ejeren har ændret i mellemtiden). Chat-loopet læser modellen hver runde, så resten af samtalen også kører på standarden, og svaret slutter med en sætning om hvilken model der blev skiftet væk fra. Fejler også andet forsøg, returneres den oprindelige fejl. Fem nye smoke-checks: kun modelfejl tæller som modelfejl, et afvist navn svarer alligevel på standarden (to kald, ikke flere), indstillingen er repareret, ejeren får besked, og auth, netværk og 429 fejler stadig uden fallback. 456 → 464 PASS. |
| 2026-11-06 | 7.38.3 | **Chatten kunne skrive pluginets indstillinger og dermed skifte AI-modellen.** `steamgears-autopilot/update-settings` er en skrivende ability i pluginets egen kategori, og chatten bandt sin egen kategori uden at se på readonly-flaget — så assistenten kunne kalde den og sætte fx `ai_model` til et modelnavn den gættede fra sin træning (`claude-3-5-sonnet`). AIMLAPI har ingen `claude-3`-modeller i sit katalog (målt 6/11: 951 modeller, 132 Claude-id'er, alle 4.x/5.x), så næste kald svarede med en fejl, og indstillingen blev ved med at stå forkert indtil ejeren rettede den manuelt — hvorefter den skiftede igen ved næste kommando. `update-settings` står nu i `SGAP_Chat::EXCLUDED`, så den hverken bindes eller kan køres gennem `run-tool`, og systemprompten siger eksplicit at assistenten ikke må gætte et modelnavn, men skal navngive indstillingen og lade ejeren skrive den. `get-settings` er stadig bundet, så den kan se hvad der står. Tre nye smoke-checks (453 → 456 PASS). |
| 2026-11-06 | 7.38.2 | **Provider-kald logges, så et afvist værktøjsnavn kan diagnosticeres uden gæt.** Hver runde husker provider, model, antal værktøjer og selve navnene; ved en fejl gemmes også fejlteksten, og `culprit()` oversætter providerens indeks (`tools[0].function_declarations[30]`) til det konkrete værktøj, som også tilføjes til fejlbeskeden i chatten. De tre nyeste kald ligger i 24 timer og vises nederst på Tools-siden med tid, provider, antal, rødt kort ved fejl, afvist værktøj og en sammenklappelig liste over navnene der blev sendt. Der logges **kun navne, aldrig nøgler**, og en fejl uden indeks (`quota exceeded`) giver ingen falsk culprit. Fire nye smoke-checks (449 → 453 PASS). |
| 2026-11-06 | 7.38.1 | **Værktøjsnavne var ugyldige for AIMLAPI (Gemini): "Invalid function name".** Målt live efter 7.38.0: AIMLAPI afviste tre funktionsdeklarationer, fordi et navn skal begynde med et bogstav eller underscore og kun må indeholde a-z A-Z 0-9 _ . : -. Kataloget kunne sende navne med skråstreg (kollisioner blev gemt som `kategori/navn`), mellemrum og navne der starter med et cifre — det gik gennem OpenAI, men ikke gennem Gemini. `SGAP_Chat::safe_name()` reparerer nu hvert navn (ulovlige tegn → `_`, ledende cifre → `t_`-præfiks, maks 64 tegn), `unique_name()` giver kollisioner `__2` i stedet for en skråstreg, og *både* bundne og filter-tilføjede værktøjer normaliseres. Fire nye smoke-checks kræver at **hvert** bundet navn matcher det provider-sikre mønster. Chat-headeren siger nu "N of M bound" i stedet for "tools", og `sgap_chat_max_bound` kan hæve de 80. 445 → 449 PASS. |
| 2026-11-06 | 7.38.0 | **Indeks og kategorisøgning over alle abilities, plus en moderne værktøjsoversigt (P1.12).** Der var ingen måde at se hvad sitet faktisk udstiller uden at læse kode. To MCP-værktøjer og én admin-side lukker det: **ability-index** grupperer hver registreret ability pr. kategori med antal, hvor mange der kun læser og navnene, og **search-abilities** matcher navn, label, beskrivelse og kategori — også inden for én kategori — og rapporterer hvor mange der matchede. Begge er rene helper-funktioner bag tynde værktøjs-wrappere, begge markeret read-only, og de bliver automatisk chat-værktøjer som alle andre læsende abilities. Den nye **Tools**-side (`sgap-tools`, ved siden af AI Chat) viser samme katalog med en fast søgelinje (`/` fokuserer, `Esc` rydder), kategori-chips med antal, levende *n af m*-tæller og kort med read/write-, bound- og blokeret-i-chat-badges. Otte nye smoke-checks (437 → 445 PASS), og MCP-værnene (test_mcp_rest, test_mcp_oauth, test_niche_scanner) kører grønt. |
| 2026-11-06 | 7.37.0 | **Botten ser alt, hvad sitet registrerer af abilities og MCP-værktøjer (P1.11).** Før så chatten kun pluginets egne to kategorier, så alt hvad WordPress, WooCommerce eller et tredje plugin registrerer, var usynligt for den. `SGAP_Chat::available()` normaliserer nu **hver** registreret ability (navn, kortnavn, kategori, beskrivelse og readonly-flag fra dens egne `annotations`). `tools()` binder pluginets egne, de læsende MCP-værktøjer og alle andre læsende abilities op til 80, og tre metaværktøjer dækker resten: **find-tools** (nøgleordssøgning i hele registret, svarer med `total_available`), **describe-tool** (skema på fuldt eller kort navn) og **run-tool** (kører enhver ability på navn, men afviser stadig chatttens udelukkelser). Dermed kan et site med 300+ værktøjer rummes uden at hver request vokser, og intet er skjult. Navnekollisioner beholder begge poster, og chat-headeren viser nu *N of M tools*, hvor M er det reelle antal på sitet. Ni nye smoke-checks (428 → 437 PASS). |
| 2026-11-06 | 7.36.0 | **Bottens værktøjer kan opdateres, og flere kan komme til (P1.10).** Værktøjslisten var låst for sidens levetid, og der var ingen understøttet måde at tilføje et værktøj udefra. Nu kan andre plugins tilføje eller omdøbe værktøjer via `add_filter('sgap_chat_tools', …)`, og hver post normaliseres (navn, beskrivelse, objekt-skema — det forkert formede smides væk), så modellen aldrig får noget den ikke kan kalde. `tools_signature()` og `tool_catalog()` rapporterer antal, navne og signatur gennem `wp_ajax_sgap_chat_tools` med samme capability og nonce som chatten, og en *Refresh tools*-knap i chat-headeren henter kataloget igen og siger hvor mange værktøjer der kom til — så nye abilities fra en plugin-opdatering slår igennem uden genindlæsning. Et værktøj uden runner svarer nu med en klar fejl i stedet for en fatal. Fire nye smoke-checks (424 → 428 PASS). |
| 2026-11-06 | 7.35.0 | **Botten kan læse sitet, men ikke skrive i det (P1.9).** Chat-assistenten kunne kun bruge pluginets egne abilities — den kunne sætte i kø og publicere, men ikke se posts, produkter, plugins, cron eller options. `SGAP_MCP_Tools::readonly_names()` udstiller nu den læsende halvdel af MCP-kataloget (13 værktøjer), og `SGAP_Chat::tools()` fletter dem ind efter udelukkelseslisten. Alt der skriver (`create-post`, `update-*`, `trash-post`, `set-plugin-state`, `run-cron`, `upload-media`, `indexnow-submit`, `undo`, `call-ability`) bliver på MCP-fladen, hvor dry_run/confirm og undo-tokenet er, og assistenten får besked på at beskrive ændringen i stedet for at udføre den. Navnekollisioner mellem de to kataloger springes over, og seks nye smoke-checks holder grænsen (418 → 424 PASS). |
| 2026-11-06 | 7.34.0 | **Release notes i update-modalen (P3.4).** Modalen viste kun et link til GitHub. Nu viser den de tre nyeste rækker fra ROADMAP'ens changelog: `changelog_notes()` er en ren parser (nyeste først, `**fed**` og backtick-kode oversat), og `remote_changelog()` henter `ROADMAP.md` fra `main` med 6 timers cache; kan GitHub ikke nås, står der et link i stedet for en tom fane. Al tekst escapes, så indhold fra repoet ikke kan injicere markup. Seks nye smoke-checks (412 → 418 PASS). Hermed er P3 færdig bortset fra `labor_rate`. |
| 2026-11-06 | 7.33.4 | **Etsy skjult: indstillingerne lover ikke længere en integration der ikke findes.** De fem Etsy-nøgler er afregistreret (`SECRET_KEYS`/`TEXT_KEYS`), Etsy-sektionen er fjernet fra indstillingerne, og Shop-fanen viser nu kun feed-hemmeligheden. Etsy-siden har fået en ærlig tekst: ordrer kommer ind manuelt via `POST /wp-json/sgap/v1/etsy/orders`, pluginnet taler aldrig med Etsy og holder ingen Etsy-credentials. Fire nye smoke-checks (408 → 412 PASS) holder skjulningen fast, så felterne ikke kan snige sig tilbage. Ejerbeslutning 1 i §8 er hermed taget: skjul, ikke byg. Tilbage af P3.1 er kun `labor_rate`, som er en prismæssig beslutning. |
| 2026-11-06 | 7.33.3 | **P3.1, first half: død kode og usynlige rester væk.** `SGAP_Admin::get_setting_help_link()` er slettet (nul kald, nul strenge-referencer), og ni indstillinger der aldrig blev læst, er fjernet fra `SECRET_KEYS`/`TEXT_KEYS`: `pinterest_token`, `instagram_token`, `tiktok_token`, `youtube_token`, `youtube_channel_id`, `reddit_username`, `reddit_subreddit`, `email_test_recipient`, `ai_mode`. Målingen er lavet forfra 6/11, ikke kopieret fra 7.15-auditten: `telegram_bot_token`/`telegram_chat_id` er live siden P2.3 og tæller derfor ikke længere som ubrugte, så tallet er 15 → 6 i stedet for 17 → 6. De seks tilbageværende er de synlige (fem Etsy-felter + `labor_rate`) og kræver ejerens byg-eller-skjul-beslutning. 408 smoke-checks, 0 FAIL. |
| 2026-11-06 | 7.33.2 | **Nøgler autoloades ikke længere, og et tomt felt sletter dem ikke.** `SGAP_Settings::set()` skrev alle optioner med standard-autoload, også `ai_api_key` og `image_api_key`; skrivevejen bag abilities, chat og wizard (`SGAP_AdminTools::update_settings()`) skrev nøgler direkte med `update_option()`, så en tom værdi slettede den gemte nøgle, en ny option blev autoloadet, og en nøgle fra `wp-config.php` blev "gemt" uden effekt. Begge følger nu formularens regler, og en `wp-config`-nøgle rapporteres i stedet for at blive overskrevet. 4 nye smoke-checks (404 → 408 PASS). Rettelsen gjorde den udgivne zip stale i forhold til kilden — CI's zip-værn ville fejle — så denne release genopbygger zippen. |
| 2026-11-05 | 7.33.1 | **Abilities uden input crashede på WordPress 7.1.** Målt live efter opdateringen 7.6.0 → 7.33.0: `self-check` kastede *Too few arguments*, fordi WordPress 7.1 kalder en ability uden input-schema med **nul** argumenter, mens closurerne krævede ét. Testene så det ikke, fordi de altid sendte `null`. Alle fem registreringssteder (abilities, admin-tools, mcp-tools, automation, studio + site-seo) pakker nu callbacken ind, så den altid får `$input` (null uden input). Nyt tjek i `admin-tools.php` kræver, at hver `steamgears-*`-callback kan kaldes uden argumenter (fejlede for 8+ abilities før rettelsen), og at self-check kører kaldt uden argument. |
| 2026-11-05 | 7.33.0 | **Command Center-linket (envejs) gjort færdigt.** `includes/command-center.php` (`SGAP_CC`) noterer kald med `X-Command-Center` på `sgap/v1`-ruterne (kun administratorer, højst én skrivning i minuttet), viser forbindelsen i Self-check-kortet, i `status` og i `/snapshot` (`command_center`), og selvtjekket melder et problem, når et CC der plejede at kalde har været tavst i over 2 timer. Modstykket er Command Center 7.19.0 (læser hele `/snapshot`, viser selvtjek/cron/pause og kalder `wp-cron.php` hvert 5. min). Samtidig er **CI på `main` repareret**: seks gamle zips fjernet (jobbet kræver én), quality gaten blokerer ikke længere sitets **egne** links når sitet kører på localhost/127.0.0.1 (CI og dev — et link til sitet selv er aldrig en placeholder), og testdata er fulgt med koden (JPEG-testbillede til Instagram-preflight, backoff før genforsøg, 32 abilities). Lokalt: integration 109, admin-tools 49, automation 27, smoke 404 PASS. |
| 2026-11-03 | 7.32.0 | **`/go/<id>/` virker nu også som produktkortlink.** Ruten blev målt 3/11 og sendte læseren til forsiden for produkt-ID'er, fordi `SGAP_Tracking::redirect()` kun slog op i `sgap_queue`: `/go/477/`, `/go/767/`, `/go/940/` og `/go/809/` gav alle **302 → `https://steamgears.eu/`** med `x-redirect-by: Steamgears`. Mangler kø-posten, forsøger ruten nu `wc_get_product($id)` og redirecter til permalinken; først når ID'et hverken er en kø-post eller et produkt, lander det på forsiden som før. Opslaget er guardet med `function_exists('wc_get_product')`, så ruten ikke kan fatale på et site uden WooCommerce, og produktklik tælles ikke i kanal-statistikken (der er ingen kø-post at attribute til). 5 nye smoke-checks dækker produktopslaget, ukendt ID, id 0 og de to kildekrav. Målt grundlag: 67 af 67 produkt-permalinks svarer 200. |
| 2026-11-03 | 7.31.0 | P2.5 shipped: **multi-language variants — one source post, one translated caption per locale.** `includes/locales.php` (`SGAP_Locales`) reads *Source language* and *Languages* from Settings → AI content (`da,sv,de`, at most six, never the source, junk and duplicates removed on save) and `SGAP_Queue::add()` queues one variant per language next to the source post. The URL is never guessed: the translated page is looked up through Polylang (`pll_get_post()`) or WPML (`wpml_object_id`), and a post with no translation gets no variant — that is reported, not invented. The caption is translated by the configured model through `SGAP_AI::json_call()`, i.e. WordPress' HTTP API, so the tests stub the transport; the `sgap_locale_translator` filter can replace the model with DeepL or a person. A variant is an ordinary queue row with the same channel, angle, format, image, quality gate and blackout path, and it carries `locale` and `source_id` (new columns, added by `dbDelta`), so it cannot expand again. Every failure — no translated page, no AI key, API error, non-JSON answer, empty caption — comes back in `locale_errors` while the source post stays queued; nothing is dropped quietly. 35 new smoke checks (364 → 399 PASS, 0 FAIL) cover the list normalisation, the translated-page lookup, one variant per language, the error paths, the AI call without a key and with a refused/invalid answer, the queue integration and the no-expansion rule. |
| 2026-11-03 | 7.30.0 | P2.2 and P2.3 shipped: **Mastodon and Telegram post directly too**, through the same loop and the same failure path as Bluesky. `includes/mastodon.php` does `POST {instance}/api/v1/statuses` with a Bearer token and an `Idempotency-Key` per queue item; `includes/telegram.php` does `POST bot<token>/sendMessage` and treats **HTTP 200 with `ok:false`** as the failure it is, reporting `description` instead of counting a refusal as a post. Both keep their limits in the open — 500 for Mastodon (a `mastodon_limit` setting, because an instance may allow more) and 4096 for Telegram — and trim grapheme-aware with the tracking link reserved; `preflight.php`'s Telegram ceiling is corrected from 1024 to 4096. Shared string handling lives in `includes/text.php` (`SGAP_Text`), so the length rule is written once. 28 new smoke checks (336 → 364) cover success, missing credentials, a refused token, an API rejection, a network failure, `ok:false`, non-JSON, trimming and the routing for each channel. |
| 2026-11-03 | 7.29.0 | P2.1 shipped: **Bluesky posts directly, without Publer.** `includes/bluesky.php` (`SGAP_Bluesky`) does the two XRPC calls the AT Protocol needs — `createSession` with handle + app password, then `createRecord` — through `wp_remote_post`, so no SDK is involved and the tests can stub the transport. The channel is switched on by putting `bluesky` in the channel list and filling in the handle and app password in Settings → Publer; the password is a secret like the Publer key, so an empty field keeps the stored value. `SGAP_Publer::tick()` routes it through the existing loop rather than a side path: failures take the same backoff, alert and publish-log route, and success is marked `published` immediately because there is no Publer job to follow up. Session cached 90 minutes; a 401 renews once and retries. Text is cut by graphemes (300), the link is reserved and turned into a richtext facet, hashtags into tag facets. 28 new smoke checks (308 → 336) cover success, missing credentials, a refused login, a refused record, a network failure, the token retry, grapheme-safe trimming and the routing. |
| 2026-11-03 | 7.28.0 | P3.2 and P3.5 shipped, plus a header bug found on the way: **`* Version:` sat on the same line as `* Description:`**, so `get_plugin_data()` had no version to compare and the update screen depended on a header WordPress could not parse. Split into two lines, which is what the zip check in CI had already warned about once. `SGAP_REST::merchant_feed()` returns the XML instead of echoing it and calling `exit`, so the merchant feed can be tested; the content type travels with the response instead of a `header()` that came after the output had started. 22 new smoke checks cover both REST routes that had zero assertions and the uninstall path; the suite prints **308 PASS, 0 FAIL** (the README's «229 checks» was stale and is corrected). No behaviour change in the feed itself. |
| 2026-11-02 | 7.23.0 | `meta-audit` ability shipped: `includes/meta-audit.php` (`SGAP_Meta_Audit`) audits titles and meta descriptions on published posts and pages — how many have none, how many are outside 60 / 70–155 characters, and which posts are worst — and writes nothing. The description is read from Rank Math and, if empty, Yoast, and the answer says which plugin answered. Exposed as the `steamgears-autopilot/meta-audit` ability; this release was bumped and zipped but not written into the changelog at the time, so it is recorded here. 11 smoke checks (208 → 219). |
| 2026-11-02 | 7.25.0 | `queue-post` takes `angle`, `content_type` and `format` (defaults `angle_a`, `custom`, `post`, so older callers are unaffected). Needed because the OAuth token AI Engine issues authenticates the MCP surface but not the plugin's own REST namespace — `POST /sgap/v1/queue` answers 401 with it — so a hand-written campaign has to go through the ability, and without these three fields every campaign post would have landed as `angle_a`/`post` and flattened the angle experiment the learning table depends on. 4 smoke checks (229 → 233). |
| 2026-11-02 | 7.24.0 | P1.8 shipped: **`utm_campaign` is a parameter**. `SGAP_UTM::build()` accepts `campaign`, falls back to the new setting *Campaign name* (`sgap_utm_campaign`, default `steamgears_premium_evergreen`), and `SGAP_Queue::add()` forwards a post's own campaign from REST `/queue` and the `queue-post` ability. `sanitize_key` on the way in keeps a campaign from injecting a query string, an empty value always means the default, and the value is frozen into each link at queue time — so old posts keep the name they were queued with. 10 smoke checks (219 → 229) and 3 integration checks. 7.23.0 had already taken the version number with the `meta-audit` ability, so this release is 7.24.0. |
| 2026-11-02 | 7.22.0 | P1.6 shipped: `includes/quality.php` (`SGAP_Quality`) adds a **quality gate before queueing**, wired into `SGAP_Queue::add()` so every path into the queue passes through it. It checks banned words from the owner's list, dead links (internal links resolved against WordPress, so a vanished slug or a draft post is caught; archives and search are not), missing alt text on the featured image, and near-duplicate captions against the last 40 posts on the same channel (85 % word overlap, names the post it repeats). Block/warn/off modes, `force` queues anyway with the findings as warnings, and captions under eight words are never called duplicates. No outbound HTTP — external hosts are not probed, and the README says why. 36 smoke checks (172 → 208). |
| 2026-11-02 | 7.21.0 | P1.3 shipped: `includes/blackout.php` (`SGAP_Blackout`) adds a **blackout calendar** — date ranges where nothing is released, one range per line in Settings → Schedule & limits. A queued post inside a range moves to the next free day at the same time of day instead of being dropped or saved up for one heap; the shift runs from the publish tick, never touches `published`/`sending` rows, and is idempotent. `blocked_reason()` reports `blackout` with the range label and the next free day, the Queue page says so, and a post still blocked after 60 days is placed anyway. 21 smoke checks (151 → 172). |
| 2026-11-02 | 7.20.0 | P1.1 shipped: `includes/preflight.php` (`SGAP_Preflight`) validates every queue item against the destination channel's own limits **before** Publer is called — Instagram JPEG/320 px/4:5–1.91:1, a Pinterest board, and the caption, title and hashtag ceilings for all ten channels — and repairs what is mechanical (word-boundary truncation, surplus hashtags) instead of letting Publer fail the post and burn one of the three attempts. Image type and size are read from the attachment metadata or the file extension, never from a download, so an image it cannot inspect is not blocked on a guess. `SGAP_Queue::add()` returns the checks as `warnings`; ability `check-post` dry-runs them. 23 smoke checks (128 → 151). |
| 2026-11-02 | 7.19.0 | P1.7 shipped: `includes/site-seo.php` (`SGAP_Site_SEO`) scans the **whole site in one pass** — 20 rules across SEO, AEO and GEO plus four domain checks — and fixes the missing meta for the weakest URLs instead of one page per click. Admin card *Optimise the whole site* in Content Studio, ability `optimize-site-seo`, lazy weekly refresh (WP-Cron is not dependable here), 16 smoke checks (112 → 128). |
| 2026-11-02 | 7.18.0 | P0.4 shipped: Site Health check *Autopilot publishing cron* (`SGAP_Auto::site_status_tests()`): critical when the publisher is 30+ min late, recommended when `DISABLE_WP_CRON` is set, otherwise good. README section *Real cron* with the crontab line. 4 smoke checks added in `plugins/tests/automation.php`. |
| 2026-11-02 | 7.17.0 | P0.5 shipped: `includes/audit.php` (`SGAP_Audit`) writes one row per publish attempt to `sgap_publish_log` — channel, outcome, HTTP status, a 500-character response excerpt and the actor — behind a **Publish log** page with outcome filters, paging and a clear button. `SGAP_Queue::mark()` still owns the state; the log keeps the history the `error` column overwrites. Retention `audit_keep_days` (default 90, `0` = forever) pruned by the daily maintenance job. 18 smoke checks added (94 → 112). |
| 2026-11-02 | 7.16.0 | P0.1–P0.3 shipped: `includes/alerts.php` (`SGAP_Alerts`) mails a failed post with its error and queue link, warns when the queue has fewer days left than `alert_dry_days`, and deduplicates both (6 h per item, 5 mails/hour, `immediate`/`daily`/`off`). `SGAP_Queue::mark()` now backs off 5 → 15 → 45 minutes via `scheduled_at` instead of spending all three attempts in one 15-minute window. New Settings → Alerts section; 16 smoke checks added (78 → 94). |
| 2026-11-01 | 7.15.1 | Docs-only release: this roadmap, a README "Roadmap and known gaps" section, and smoke coverage for `sgap-setup`, `sgap-studio`, `sgap-chat`. |
| 2026-11-01 | 7.15.0 | First audit: call-graph and settings inventory, smoke coverage extended to all 12 admin pages, research into set-and-forget gaps and platform limits, effort estimates added. |
