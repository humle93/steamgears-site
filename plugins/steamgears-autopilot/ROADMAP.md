# Steamgears Autopilot — audit, roadmap and effort estimates

_Last audited: 2026-11-01 · audit was of **7.15.0** (`ab676db` on `main`); current release **7.38.1**, and
**7.22.0 is what steamgears.eu runs** (measured 2026-11-02 after the update click, with `wp_list_plugins`
and `steamgears-autopilot/status`; see `docs/roadmap.md` §2b) — the zip that waits for that click is
**7.38.1** · update this file in the same commit as any release._

This is the working roadmap for `plugins/steamgears-autopilot`. It answers three questions: does every
function in the plugin actually do something, what is missing before the plugin can truly run unattended,
and how long each missing piece takes.

---

## 0. Short version

- **42 classes, 30 PHP files, 10.754 lines** (measured 6/11 2026 on 7.33.2). Every subsystem is reachable
  from a real entry point (cron, REST, admin page, ability or AJAX); the last dead method,
  `SGAP_Admin::get_setting_help_link()`, was deleted in 7.33.3.
- **1 setting is registered but never read** (down from 17). Nine invisible leftovers were removed in
  7.33.3 (`pinterest_token`, `instagram_token`, `tiktok_token`, `youtube_token`, `youtube_channel_id`,
  `reddit_username`, `reddit_subreddit`, `email_test_recipient`, `ai_mode`) and the five Etsy keys were
  hidden in 7.33.4, so the Settings screen no longer promises an Etsy connection that does not exist. Only
  `labor_rate` is left, and it is a pricing question rather than a code question (section 8).
- **The plugin publishes well when everything is healthy, and tells nobody when it isn't.** There is no
  failure email, no "queue is running dry" alert, no retry backoff, and no publish audit trail. Those are
  the four things standing between "works" and "set and forget".
- **Estimated effort to a credible set-and-forget v1: 11 developer-days** (all of P0, P1 without analytics,
  and the two test-hygiene items). Full parity with hosted schedulers, including analytics ingestion and
  evergreen recycling, is **18–21 developer-days**, before external API approvals that take calendar weeks.

---

## 1. Method

| What | How |
| --- | --- |
| Call graph | Static inventory of every `function` definition, then counted call sites and string references across all 15 files (hook callbacks show up as strings). |
| Settings | Cross-referenced every key in `DEFAULTS` / `*_KEYS` / `SECTIONS` / `TABS` against every `SGAP_Settings::get/enabled/set()` and `get_option()` read. |
| Syntax | `php -l` on all 21 PHP files (plugin + tests), PHP 8.3.14. |
| Behaviour | `plugins/tests/smoke.php` (stubbed WordPress) locally; `plugins/tests/integration.php` (real WordPress + MySQL, queue/drafts/tracking/AI/Publer/rescue/maintenance) in CI. |
| Compatibility | Manual scan for PHP 8.4 implicit-nullable signatures; CI lint matrix 8.0–8.4. |

**Not verified in this workspace:** there is no WordPress or MySQL here, so `integration.php` only runs in
CI, and nothing has been exercised against the live steamgears.eu database. Any statement about production
behaviour below comes from reading code and docs, not from a live run.

---

## 2. Is every subsystem live?

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
| Admin tools (29 ops) | `admin-tools.php` | abilities | Live, covered |
| Self-updater | `updater.php` | `Update URI` + `update_plugins_github.com` | Live; parsing and package URL covered |
| Maintenance + health | `core.php` `SGAP_Maintenance`, `admin-tools.php` | cron `sgap_daily_cleanup`, `sgap_daily_health` | Live, covered |
| Merchant feed (`/feed` products) | `core.php` `SGAP_REST::merchant_feed` | REST `/feed` | Live, **no test** |
| Suppression list | `core.php` | REST `/suppression` | Live, thin test |
| Reposts | `core.php` | REST `/reposts` | Live, **no test** |
| Etsy reporting | `core.php` `SGAP_REST::import_etsy_order` | REST `/etsy/orders`, admin **Etsy** | Manual/REST import only — no API client |
| Snapshot for outside dashboards | `core.php` `SGAP_REST::snapshot` | REST `/snapshot` | Live (7.15.0), smoke-tested |

---

## 3. Findings from this audit

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

904 lines of tests across 5 files. As of this audit the smoke suite renders **all 12 admin pages**
(`sgap-setup`, `sgap-studio` and `sgap-chat` were previously never rendered — a fatal in any of them would
have reached production unnoticed; adding them immediately surfaced one missing test stub).

Still untested:

- `GET /feed` (merchant product feed) — zero assertions.
- `GET/POST /reposts` — zero assertions.
- `SGAP_Updater::run()` — only header parsing and the package URL are covered, not the install path.
- `SGAP_KeyCheck` for the non-AIMLAPI providers.
- The chat conversation loop (only the page render).
- `/snapshot` is smoke-tested but not asserted in `integration.php` (its auth path is the same
  `manage_options` callback that `/stats` already covers anonymously).

### 3.4 Compatibility

- All 21 files lint clean on PHP 8.3.14. Every nullable parameter is already written `?type $x = null`, so
  there are **no PHP 8.4 implicit-nullable deprecations** — the most common 8.4 breakage does not apply.
- CI lints 8.0–8.4 and runs WordPress 6.4 + latest. PHP 8.5 and WordPress 7.x are not in the matrix yet.
- `Requires at least: 6.4` / `Requires PHP: 8.0` in the header still match the code.

---

## 4. Research: what "set and forget" actually requires

Sources are listed in section 7. The comparison set is Buffer, Publer, Metricool, Later, SocialBee,
SmarterQueue and Hootsuite — the tools a shop owner would otherwise buy.

### 4.1 Table stakes we do not have

1. **Publish-failure notifications.** Buffer emails and pushes on every failed post; Publer offers
   per-failure, hourly or daily-digest mail and keeps a *Posts → Failed* view with the reason; Metricool
   shows a "With Errors" status. The plugin writes `status=failed` plus an `error` string into the queue and
   stops there — you only find out by opening wp-admin.
2. **Connection / token expiry alerts.** Buffer notifies when a channel disconnects or permissions expire;
   Publer badges the account and links to re-auth. We hold no tokens in RSS mode (Publer does), so the
   equivalent for us is *feed health* — and there is none.
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

### P0 — reliability (nothing else matters until this is done)

| # | Item | Why | Effort | Depends on |
| --- | --- | --- | --- | --- |
| P0.1 | ✅ **Done 2/11** — `includes/alerts.php` (`SGAP_Alerts`): `SGAP_Queue::mark()` fires `sgap_queue_failed`, the alert mails the error text and a link to the queue row. Deduplicated per item for 6 h and capped at 5 mails/hour so a dead channel cannot storm the inbox. Modes `immediate` / `daily` / `off` under Settings → Alerts; recipient has its own setting (empty = admin address) rather than the unused `email_test_recipient` | Today a broken token or a Publer outage is invisible | 0.5 d | ✅ |
| P0.2 | ✅ **Done 2/11** — runway in days (`queued / daily_cap`) is checked in the daily self-check and warns below `alert_dry_days` (default 2, `0` = off); in `daily` mode it lands in the digest. Measured over the whole queue, not per channel: a per-channel alarm needs a per-channel cap, which does not exist yet | Silent gaps are the classic autopilot failure | 0.5 d | ✅ |
| P0.3 | ✅ **Done 2/11** — backoff 5 → 15 → 45 minutes, written into the existing `scheduled_at` instead of a new `next_attempt_at` column: no schema change, and `claim()` already refuses items whose `scheduled_at` is in the future. The third attempt is still terminal `failed` and now also fires the P0.1 alert | Three immediate retries can all fail during one outage | 1.0 d | ✅ |
| P0.4 | Cron honesty: detect `DISABLE_WP_CRON`, surface a Site Health test + the exact `wp-cron.php` line for real cron, document it in the README | On a cached site WP-Cron may not fire for hours; quiet hours then release posts late | 0.5 d (detect + docs) | Kode, test og README i 7.18.0. CI: zip + lint/smoke (PHP 8.0–8.5) grønne; `WordPress 6.4/7.1/latest` fejler i `integration.sh` efter 6–7 s — samme fejl på `3d9725e` før dette arbejde, så ikke forårsaget af P0.4. Logger kræver GitHub-login. |
| P0.5 | ✅ **Done 2/11** — `includes/audit.php` (`SGAP_Audit`): every attempt writes one row to `sgap_publish_log` — time, queue id, channel, outcome (`sent`/`rss`/`published`/`requeued`/`failed`), HTTP status carried by the Publer `WP_Error`, a 500-character response excerpt, and the actor (cron, admin, REST, WP-CLI). Own **Publish log** page with outcome filters, paging and a clear button. `SGAP_Maintenance::daily()` prunes rows older than `audit_keep_days` (default 90, `0` = keep everything). Retention rather than a cap, so a busy week cannot push out the evidence | You cannot debug "why did this not post" from `error TEXT` alone | 1.0 d | ✅ |
| P0.6 | Cross-system duplicate guard: refuse a new item whose `channel`+`destination_url`+`title` fingerprint exists within N days, and expose the fingerprint in `/snapshot` | The Command Center bridge can hand over the same post twice | 0.5 d | CC side |
| **P0 total** | | | **4.0 d** | |

Optional, larger: migrate the publish tick to **Action Scheduler** (per-job status, retries, real WP-CLI
runner). That is 2.0 d on top and is the "correct" long-term answer to P0.4 on low-traffic sites.

### P1 — parity with what shop owners get from hosted tools

| # | Item | Why | Effort | Depends on |
| --- | --- | --- | --- | --- |
| P1.1 | ✅ **Done 2/11 (7.20.0)** — `includes/preflight.php` (`SGAP_Preflight`) runs the channel's own rules in `SGAP_Publer::publish()` **before the first API call**: Instagram (photo present, JPEG only, ≥ 320 px wide, 4:5–1.91:1), Pinterest (photo + board configured), and the caption/title/hashtag ceilings per network — Threads 500 characters incl. the tracking link and exactly one hashtag, X 280 with any link counted as 23, Facebook 63 206, LinkedIn 3 000, Instagram 2 200 / 30 hashtags, Pinterest 100 + 500, Bluesky 300, Mastodon 500, Telegram 1 024, Google 1 500. What is mechanical is **repaired** (word-boundary truncation, surplus hashtags dropped) instead of refused; what is not (no photo, wrong image format, wrong aspect, no board) is refused with a message naming the fix. Image type and size come from the attachment metadata or the file extension — the image is never downloaded to check it, and an uninspectable image is never blocked on a guess. `SGAP_Queue::add()` returns the same checks as `warnings` so the Studio and an agent see them at creation, and the ability `check-post` dry-runs the whole thing. 23 smoke checks added (128 → 151) | Turns silent failures into actionable messages | 1.0 d | — |
| P1.2 | ✅ **Done 3/11 (7.27.0)** — `remind` on a queue row (`remind TINYINT(1)`, added by `dbDelta` on upgrade), set from the *Remind me* button on the Queue page, `POST /queue` or the `queue-post` ability. `includes/reminder.php` (`SGAP_Reminder`) mails the owner the channel, format, due time, caption, image URL, tracked link and the queue link, then sets status `reminded` — terminal, so no API call, no retry, no backoff and no failure mail. Both publishers honour it: API mode skips Publer and RSS mode holds the item out of the feed, because a feed would post it anyway. The mail uses the alert recipient (`alert_email`, falling back to the admin) but is sent even when Alerts is `off`, since it is the post and not an alert; the same item is never mailed twice inside 6 h. Own publish-log outcome (*Reminded by mail*) and a `reminder` badge on the row. 20 smoke checks (265 → 285), including the empty-address fallback and the terminal-status guarantees. RSS and API share one code path so they cannot drift | Stories/carousels currently just fail | 1.0 d | ✅ |
| P1.3 | ✅ **Done 2/11 (7.21.0)** — `includes/blackout.php` (`SGAP_Blackout`): Settings → *Schedule & limits* → **Blackout days** takes one range per line (`2026-12-24..2026-12-26 Christmas`), with an optional label. Nothing is released inside a range, and a queued post that lands there is **moved to the next free day at the same time of day** — not dropped, and not saved up for one heap at the end of the range. `reschedule()` runs at the top of the publish tick, so a range added this morning is honoured before the next post goes out, and a shifted post is not shifted twice. Only `queued` rows move; `published` and in-flight `sending` rows are never touched. Queueing inside a range schedules the next free day immediately and returns the new time, `blocked_reason()` reports `blackout` with the range label and where posts go, the Queue page explains it, and a post still blocked after 60 days is placed anyway so a typo cannot park it forever. 3 smoke checks for the field and the notice, 21 in total for the calendar (151 → 172) | Holidays and sale blackouts are the #1 manual intervention | 0.5 d | — |
| P1.4 | Evergreen recycling: mark posts/products as evergreen, recycle with a variation prompt, expire after N shares or a date | The plugin already has reposts and cooldowns; this is the missing engine | 2.5 d | P0.6 |
| P1.5 | Platform analytics ingestion (reach, saves, outbound clicks from IG/Pinterest) into `sgap_events`, shown per channel | Closes the loop from "we posted" to "it worked" | 4.0 d | Pinterest 401 fixed, Meta review |
| P1.6 | ✅ **Done 2/11 (7.22.0) — live and measured on the site** (`check-post` refuses a placeholder link with severity `block`; a `queue-post` refusal is still untested because AI Engine's *Drafts Only* stops writes) — `includes/quality.php` (`SGAP_Quality`) sits inside `SGAP_Queue::add()`, the only way into the queue, so the Studio, AI drafts, the REST API and the Claude tools are covered by one check. Four checks: **banned words** from the owner's own list (whole words, hashtags count); **dead links** — internal links resolved against WordPress itself, so a missing slug or a not-yet-published post is caught, while archives/feeds/search are recognised and left alone; **missing alt text** on the featured image; **near-duplicate captions** against the last 40 posts on the same channel, 85 % word overlap, with the colliding post named. Three modes (block/warn/off, default block) and a `force` escape hatch that queues the post with the findings as warnings. Captions under eight words are never called duplicates. No outbound HTTP: external hosts are not probed (documented in the README, with the reason). 36 smoke checks (172 → 208) | Cheap insurance for unattended posting | 1.0 d | — |
| P1.8 | ✅ **Done 2/11 (7.24.0)** — `utm_campaign` is a parameter instead of a constant. `SGAP_UTM::build()` takes `campaign`; empty falls back to the new setting *Campaign name* (`utm_campaign`, default `steamgears_premium_evergreen`, so existing links stay comparable), and `SGAP_Queue::add()` passes a post's own `campaign` through, from REST `/queue` or the `queue-post` ability. The name is `sanitize_key`-ed on the way in, so a campaign cannot smuggle a query string into the link, and it is never empty. A finite series can now be measured as one campaign in GA4 (*Session campaign*) while the evergreen posts keep running on the setting; posts queued earlier keep the value they were queued with. 10 smoke checks (219 → 229) + 3 integration checks | A named campaign could not be told apart from the everyday stream in GA4 | 0.25 d | — |
| P1.12 | ✅ **Done 6/11 (7.38.0)** — there was no way to see what a site actually exposes without reading code. Two MCP tools and one admin screen close that: **ability-index** groups every registered ability by category with a count, how many only read, and the names; **search-abilities** matches name, label, description and category, optionally inside one category, and reports how many matched so a caller knows whether to narrow. Both are pure helpers (`index_abilities()`, `search_abilities()`) behind thin tool wrappers, both marked read-only, and they become chat tools automatically like every other read-only ability. The new **Tools** screen (`sgap-tools`, next to AI Chat) renders the same catalogue with a sticky search bar (`/` focuses, `Esc` clears), category chips with counts, live *n of m* counter and cards carrying read/write, bound and blocked-in-chat badges — 445 checks cover the grouping, the search, the limit, both tool wrappers and the page | You could not tell what the site exposes, or in which category, without grepping | 0.75 d | — |
| P1.11 | ✅ **Done 6/11 (7.37.0)** — the chat only saw this plugin's two categories, so every ability WordPress core, WooCommerce or another plugin registers was invisible to it. `SGAP_Chat::available()` now normalises **every** registered ability (name, short name, category, description, readonly flag from its own `annotations`), `tools()` binds its own plus the read-only MCP tools plus every other read-only ability up to `MAX_BOUND = 80`, and three meta tools cover the rest: **find-tools** (keyword search across the whole registry, returns `total_available`), **describe-tool** (schema by full or short name) and **run-tool** (runs any ability by name, still refusing the chat's exclusions). A 300-tool site therefore stays within a sane request size while nothing is hidden. Name collisions keep both entries (full name wins), and the chat header now reads *N of M tools* with M the real site total, so the count is visible instead of assumed. Nine smoke checks: foreign read-only abilities bound, foreign writers not bound, meta tools always present, find-tools reaches the catalogue and hides exclusions, describe-tool resolves a short name, run-tool runs an unbound ability and refuses an excluded one, and the catalogue counts per category | The assistant could not see the abilities the rest of the site exposes | 0.5 d | — |
| P1.10 | ✅ **Done 6/11 (7.36.0)** — the chat's tool list was fixed for the life of a page and there was no supported way to add a tool from outside the plugin. Three things now cover that: **(1)** `add_filter('sgap_chat_tools', …)` lets another plugin add or rename tools, and every entry is normalised (name, description, an object schema; anything malformed is dropped) so the model is never handed something it cannot call; **(2)** `tools_signature()` (md5 of the sorted names) and `tool_catalog()` report count, names and signature, and `wp_ajax_sgap_chat_tools` serves them behind the same capability and nonce as the chat; **(3)** the chat header has a *Refresh tools* button that re-reads the catalogue and says how many tools were added, so a plugin update that registers new abilities shows up without a reload. A tool without a runner now answers with a clear error instead of a fatal. Four smoke checks: the filter adds a tool, the signature follows the catalogue, the catalogue reports all three fields, and a runner-less tool fails cleanly | New abilities would only reach the chat after a page reload, and nothing outside the plugin could add one | 0.25 d | — |
| P1.9 | ✅ **Done 6/11 (7.35.0)** — the admin chat could only use the plugin's own abilities, so it could queue and publish but could not *read* the site: no posts, products, plugins, cron or options. `SGAP_MCP_Tools::readonly_names()` exposes the read-only half of the MCP catalogue (13 tools: `site-info`, `get-option`, `list-plugins`, `list-themes`, `list-cron`, `list-posts`, `get-post`, `list-media`, `list-products`, `get-product`, `indexnow-status`, `sitemap-audit`, `list-abilities`) and `SGAP_Chat::tools()` merges them with its own after the exclusion list. Everything that writes (`create-post`, `update-*`, `trash-post`, `set-plugin-state`, `run-cron`, `upload-media`, `indexnow-submit`, `undo`, `call-ability`) stays on the MCP surface, where dry_run/confirm and the undo token live, and the assistant is told to describe a change and let the owner apply it. Name collisions between the two catalogues are skipped, and six smoke checks pin the boundary (read set complete, no writer in it, no writer in the chat, every schema an object) | The chat was blind to the half of the plugin an MCP client can already see | 0.25 d | — |
| P1.7 | ✅ **Done 2/11 (7.19.0)** — whole-site SEO/GEO/AEO audit, `includes/site-seo.php` (`SGAP_Site_SEO`). One pass scores every published post, page and product against 20 rules in three groups — **SEO** (title/meta length, missing meta, focus keyword, one H1, text length per type, internal links, alt text, featured image, slug, duplicate title/description), **AEO** (question headings, FAQ + `FAQPage` schema, answer-first paragraph, lists/tables) and **GEO** (named entity, quotable numbers, structured data, outbound citations, freshness) — plus four whole-domain checks (llms.txt, sitemap, robots.txt, product feed). `fix()` writes the missing Rank Math/Yoast meta for the weakest URLs, 8 calls per round, and never touches body text. Result lives in one option and is refreshed lazily weekly because WP-Cron cannot be trusted on this host. Admin card in Content Studio, ability `optimize-site-seo`, 16 smoke checks (112 → 128). Replaces the per-page loop that made a site-wide pass 40 clicks | The plugin could only optimise one page at a time, so in practice nobody did it | 1.0 d | — |
| **P1 total** | | | **11.25 d** (7.25 d without P1.5) | |

### P2 — channels and scope

| # | Item | Why | Effort | Depends on |
| --- | --- | --- | --- | --- |
| P2.1 | ✅ **Done 3/11 (7.29.0)** — `includes/bluesky.php` (`SGAP_Bluesky`) posts to Bluesky directly, without Publer: `com.atproto.server.createSession` (handle + app password) and `com.atproto.repo.createRecord`, both through WordPress' HTTP API, so no SDK and no Composer dependency. Credentials live where the others do (Settings → Publer, the app password as a secret with the same empty-field-keeps-the-old rule), and `bluesky` in the channel list is the on/off switch — remove it or clear the password and the channel falls back to Publer. `SGAP_Publer::tick()` routes the channel through the **same** loop, so a failure gets the same backoff, the same P0.1 alert and the same publish-log row; a success has no Publer job to poll, so the item is marked `published` at once with the AT URI as its external id. The session is cached for 90 minutes and a 401 renews it once and retries the post. Text respects Bluesky's **300 graphemes** (`\X` matches, so an emoji with a modifier is never split in half), the tracking link is reserved before the body is trimmed and sent as a richtext facet so it is clickable, and hashtags become tag facets. Every failure is a WP_Error that names the cause — missing credentials, `invalid_grant` from a refused login, the API's own message on a refused record, or the transport error — never a silent no-op and never a PHP warning. 28 smoke checks with a stubbed `wp_remote_post` (336 PASS in total) | Free, no review, one afternoon — the cheapest new channel available | 1.0 d | — |
| P2.2 | ✅ **Done 3/11 (7.30.0)** — `includes/mastodon.php` (`SGAP_Mastodon`): one call to `POST {instance}/api/v1/statuses` with the token as a Bearer header (an `Idempotency-Key` per queue item, so a retried tick cannot post twice). The instance is part of the credential because a Mastodon token only works on the server that issued it; `https://` is added and trailing slashes dropped. Limit **500 characters** by default (`mastodon_limit` overrides, clamped 100–5000), trimmed grapheme-aware with the tracking link reserved. The API's own `error` text — `The access token is invalid`, a validation message — becomes the WP_Error message and the publish-log row; a 401 is reported, never swallowed | Free, self-hostable, same shape as Bluesky | 0.5 d | — |
| P2.3 | ✅ **Done 3/11 (7.30.0)** — `includes/telegram.php` (`SGAP_Telegram`): one call to `https://api.telegram.org/bot<token>/sendMessage`. Telegram refuses a message with **HTTP 200 and `{"ok": false}`**, so `ok` is checked before anything else and `description` becomes the error — a refused post can never look published. 4096-character limit, grapheme-aware trimming with the link reserved; `preflight.php` now allows the real 4096 instead of 1024, so a valid caption is no longer cut without reason. The bot token stays in the URL and never in an error message. Settings → Publer: bot token (a secret), chat id, and `telegram` in the channel list | Token settings already exist; also gives P0.1 a free second alert channel | 0.5 d | — |
| P2.4 | Etsy API import (OAuth + receipt sync) **or** remove the five Etsy settings and label the page "manual import" | The screen currently promises an integration that is not there | 2.5 d to build / 0.25 d to hide | Etsy app review |
| P2.5 | ✅ **Done 3/11 (7.31.0)** — `includes/locales.php` (`SGAP_Locales`): one source post queues one translated caption per locale. *Source language* (default `en`) and *Languages* (`da,sv,de`, at most six, never the source, junk and duplicates stripped on save) sit under **Settings → AI content**; when the list is non-empty, `SGAP_Queue::add()` also queues a variant per language. The translated page comes from **WPML or Polylang** — `pll_get_post()` first, then WPML's `wpml_object_id` filter — and the variant links to that page; the plugin never guesses a URL, so a post without a translation simply gets no variant. The caption itself is translated by the configured model through `SGAP_AI::json_call()` (WordPress' HTTP API, so the tests stub it), and the `sgap_locale_translator` filter can hand the job to DeepL or a human. Variants are ordinary queue rows — same channel, angle, format, image, quality gate and blackout handling — and carry `locale` plus `source_id`, so a variant never spawns variants of its own. **No failure is silent:** a language with no translated page, no AI key, an API error, a non-JSON answer or an empty caption is reported in the queue result's `locale_errors` while the source post stays queued. 35 smoke checks with stubbed HTTP (364 → 399 PASS) | Nobody in the market does this properly; WPML/Polylang make it feasible | 2.5 d | — |
| **P2 total** | | | **7.0 d build / 4.75 d if Etsy is hidden** | |

Not recommended: direct X posting ($0.20 per post with a URL makes link posts economically pointless),
direct LinkedIn (vetted tier, closed permission), direct Google Business Profile (quota 0 until approved).
Threads and LinkedIn stay behind Publer.

### P3 — hygiene and proof

| # | Item | Effort |
| --- | --- | --- |
| P3.1 | 🟢 **Done 6/11 except 0.05 d (7.33.3 + 7.33.4)** — the dead method `get_setting_help_link()` is deleted, nine invisible leftovers are gone from `SECRET_KEYS`/`TEXT_KEYS`, and the five visible Etsy keys are hidden: they are no longer registered, the Etsy tab section is gone, and the Etsy page says *manual import* with the `/etsy/orders` route instead of promising an API. Four smoke-checks guard it (no Etsy fields in the Settings HTML, shop tab still renders the feed secret, manual-import text present, dead keys rejected by `update_settings`). **Only `labor_rate` is left (0.05 d):** it is visible under Economics, and hiding it changes what ROI promises, so it waits for the owner (section 8). | 0.5 d (0.45 done) |
| P3.2 | ✅ **Done 3/11 (7.28.0)** — tests for `/feed` and `/reposts`. `merchant_feed()` printed the XML and called `exit`, so the feed could not be tested at all; it now returns a `WP_HTTP_Response` with `Content-Type: application/xml` (and stops calling `header()` after output). 12 checks cover the secret gate (missing secret, wrong key), the 501 without WooCommerce, the Google namespace, SKU falling back to the product id, price with the store currency, XML-escaped titles, stripped HTML in the description, skipped products without price or image, both availability states and the content type; 5 cover the reposts (list, 400 without ids, numbering, 422 at the cap, the insert); 5 cover the uninstall (table list, drops, options, post meta). `assert /snapshot` auth is still open — it needs a WordPress user in `integration.php` | 0.5 d |
| P3.3 | ✅ **Done as S0.3** — PHP 8.0–8.5 and WordPress 6.4 / 7.1 / latest are in the CI matrix | 0.25 d |
| P3.4 | ✅ **Done 6/11 (7.34.0)** — the “View details”/update modal now shows the three newest rows from this file's changelog instead of a bare GitHub link. `SGAP_Updater::changelog_notes()` is a pure parser (regex on the `| date | version | change |` table, newest first, `**bold**` and backticked code converted) and `remote_changelog()` fetches `ROADMAP.md` from `main` behind a 6-hour site transient; when GitHub is unreachable the modal falls back to a link rather than an empty tab. Note text is escaped, so nothing in the repo can inject markup. Six smoke-checks cover the parser, the escaping, the limit, the empty case and the modal contract | 0.5 d |
| P3.5 | ✅ **Done 3/11 (7.28.0)** — `uninstall.php` is now checked against the `dbDelta` DDL instead of memory: the test extracts every table `SGAP_Install::install()` creates, asserts the uninstall file declares all nine, then includes `uninstall.php` with a stubbed `$wpdb` and requires a `DROP TABLE IF EXISTS` for each, the option/transient delete and the post-meta cleanup. The file was correct; the check is what was missing | 0.25 d |
| **P3 total** | | **2.0 d** |

---

## 6. Completion estimates

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

## 8. Open decisions for the owner

1. **Etsy** — ✅ **decided 2026-11-06: hidden, not built** (7.33.4). The five fields are gone from Settings and
   the Etsy page labels itself a manual import; `POST /wp-json/sgap/v1/etsy/orders` is still the way in. Revisit
   only if Etsy volume justifies the 2.5 d OAuth import.
2. **Direct network tokens** — ✅ **decided 2026-11-06: removed** (7.33.3). All publishing goes through Publer,
   so `pinterest_token`, `instagram_token`, `tiktok_token`, `youtube_*`, `reddit_*` and `ai_mode` are gone.
3. **Action Scheduler** — accept the 2 d migration, or stay on WP-Cron with detection and documentation
   (P0.4)?
4. **Analytics** — is platform-side reach/engagement worth 4 d plus API review, or are click and revenue
   attribution (already built) enough?
5. **Command Center direction** — one-way (CC reads `/snapshot` and hands drafts to
   `POST /wp-json/sgap/v1/drafts`) remains the recommendation; confirm before CC work starts.

---

## 9. Change log for this document

| Date | Plugin | Change |
| --- | --- | --- |
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
