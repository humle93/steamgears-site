# Steamgears Autopilot — audit, roadmap and effort estimates

_Last audited: 2026-11-01 · plugin **7.15.0** (`ab676db` on `main`), shipped as **7.15.1** with this document · update this file in the same commit as any release._

This is the working roadmap for `plugins/steamgears-autopilot`. It answers three questions: does every
function in the plugin actually do something, what is missing before the plugin can truly run unattended,
and how long each missing piece takes.

---

## 0. Short version

- **26 classes, 15 PHP files, ~6.3k lines.** Every subsystem is reachable from a real entry point (cron,
  REST, admin page, ability or AJAX) with **one** exception: `SGAP_Admin::get_setting_help_link()` is dead.
- **17 settings are registered but never read.** Six of them are visible on the Settings screen (the five
  Etsy keys and `labor_rate`), which makes the screen promise an Etsy API connection that does not exist.
- **The plugin publishes well when everything is healthy, and tells nobody when it isn't.** There is no
  failure email, no "queue is running dry" alert, no retry backoff, and no publish audit trail. Those are
  the four things standing between "works" and "set and forget".
- **Estimated effort to a credible set-and-forget v1: 8–9 developer-days** (P0 + the cheap half of P1 +
  test hygiene). Full parity with hosted schedulers, including analytics ingestion and evergreen
  recycling, is **19–22 developer-days**, before external API approvals that take calendar weeks.

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
| Click tracking + UTM | `core.php` `SGAP_Tracking`, `SGAP_UTM` | `/go/<id>/` rewrite | Live, covered |
| ROI / learning / winner-loser | `core.php` `SGAP_ROI`, `SGAP_Learning` | daily cron, `/stats`, **Campaigns** | Live, covered |
| Rescue (image backfill) | `automation.php` `SGAP_Rescue` | cron `sgap_rescue` | Live, covered |
| Promote new posts | `automation.php` `SGAP_Auto` | cron `sgap_promote_new` | Live, covered |
| Weekly article + weekly mail | `automation.php` | cron `sgap_weekly_article`, `sgap_weekly_email` | Live; mail only to `admin_email` |
| Content Studio (articles, images, SEO) | `studio.php` | admin page, abilities | Live; page render now smoke-tested |
| Chat assistant | `chat.php` | admin page, AJAX | Live; page render now smoke-tested |
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

### 3.1 Dead code (1 method)

- `SGAP_Admin::get_setting_help_link()` (`includes/core.php:1609`) — zero call sites and zero string
  references. It returns a docs link that no template uses. **Delete it** (10 minutes) or wire it into the
  settings rows.

### 3.2 Registered but unused settings (17)

None of these are read or written anywhere in the plugin:

| Setting | Visible to the user? | Why it exists |
| --- | --- | --- |
| `etsy_shop_id`, `etsy_oauth_token`, `etsy_refresh_token`, `etsy_shipping_profile_id`, `etsy_return_policy_id` | **Yes** — Settings tab + setup guide | Planned Etsy OAuth import that was never built. `/etsy/orders` is a manual import. |
| `labor_rate` | **Yes** — Settings | Planned cost-of-time in ROI; ROI uses `monthly_budget` and `target_roi` instead. |
| `pinterest_token`, `instagram_token`, `tiktok_token`, `youtube_token`, `youtube_channel_id` | No (constants only) | Left over from direct-posting plans; all publishing goes through Publer. |
| `telegram_bot_token`, `telegram_chat_id` | No | Notification channel that never shipped — and is exactly what P0.1 below needs. |
| `reddit_username`, `reddit_subreddit` | No | Reddit posting that never shipped. |
| `ai_mode` | No | Superseded by `ai_provider`. |
| `email_test_recipient` | No | Would be useful for P0.1. |

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
| P0.1 | Failure alerting: mail the configured address on `failed`, plus a daily digest with the error text and a link to the queue row; reuse `email_test_recipient` and the existing `wp_mail` path | Today a broken token or a Publer outage is invisible | 0.5 d | — |
| P0.2 | "Running dry" alert: if a channel has fewer than N queued items for the next 7 days, say so in the digest | Silent gaps are the classic autopilot failure | 0.5 d | P0.1 |
| P0.3 | Retry with backoff: `next_attempt_at` on the queue, 5 min → 30 min → 2 h, then a terminal `failed` list viewable and re-queuable in wp-admin | Three immediate retries can all fail during one outage | 1.0 d | — |
| P0.4 | Cron honesty: detect `DISABLE_WP_CRON`, surface a Site Health test + the exact `wp-cron.php` line for real cron, document it in the README | On a cached site WP-Cron may not fire for hours; quiet hours then release posts late | 0.5 d (detect + docs) | — |
| P0.5 | Publish audit log: one row per attempt (time, channel, HTTP status, response excerpt, actor) behind a **Log** tab | You cannot debug "why did this not post" from `error TEXT` alone | 1.0 d | — |
| P0.6 | Cross-system duplicate guard: refuse a new item whose `channel`+`destination_url`+`title` fingerprint exists within N days, and expose the fingerprint in `/snapshot` | The Command Center bridge can hand over the same post twice | 0.5 d | CC side |
| **P0 total** | | | **4.0 d** | |

Optional, larger: migrate the publish tick to **Action Scheduler** (per-job status, retries, real WP-CLI
runner). That is 2.0 d on top and is the "correct" long-term answer to P0.4 on low-traffic sites.

### P1 — parity with what shop owners get from hosted tools

| # | Item | Why | Effort | Depends on |
| --- | --- | --- | --- | --- |
| P1.1 | Pre-flight validation per channel (IG: JPEG + 4:5–1.91:1 + min width; Threads: 500 chars, 1 hashtag; Pinterest: board configured; FB: length) with a clear error before it reaches Publer | Turns silent failures into actionable messages | 1.0 d | — |
| P1.2 | "Remind me instead of posting" flag on a queue item for formats the APIs reject (API mode only; RSS mode cannot) | Stories/carousels currently just fail | 1.0 d | P1.1 |
| P1.3 | Blackout calendar: date ranges where nothing is released, with posts shifted to the next free day instead of dropped | Holidays and sale blackouts are the #1 manual intervention | 0.5 d | — |
| P1.4 | Evergreen recycling: mark posts/products as evergreen, recycle with a variation prompt, expire after N shares or a date | The plugin already has reposts and cooldowns; this is the missing engine | 2.5 d | P0.6 |
| P1.5 | Platform analytics ingestion (reach, saves, outbound clicks from IG/Pinterest) into `sgap_events`, shown per channel | Closes the loop from "we posted" to "it worked" | 4.0 d | Pinterest 401 fixed, Meta review |
| P1.6 | Quality gate before queueing: near-duplicate captions, dead links, missing alt text, banned-words list | Cheap insurance for unattended posting | 1.0 d | — |
| **P1 total** | | | **10.0 d** (6.0 d without P1.5) | |

### P2 — channels and scope

| # | Item | Why | Effort | Depends on |
| --- | --- | --- | --- | --- |
| P2.1 | Bluesky direct posting (app password, `createSession` + `createRecord`) | Free, no review, one afternoon — the cheapest new channel available | 1.0 d | — |
| P2.2 | Mastodon direct posting | Free, self-hostable, same shape as Bluesky | 0.5 d | — |
| P2.3 | Telegram channel posting | Token settings already exist; also gives P0.1 a free second alert channel | 0.5 d | — |
| P2.4 | Etsy API import (OAuth + receipt sync) **or** remove the five Etsy settings and label the page "manual import" | The screen currently promises an integration that is not there | 2.5 d to build / 0.25 d to hide | Etsy app review |
| P2.5 | Multi-language variants per post (one source post → per-locale captions) | Nobody in the market does this properly; WPML/Polylang make it feasible | 2.5 d | — |
| **P2 total** | | | **4.5 d build / 2.25 d if Etsy is hidden** | |

Not recommended: direct X posting ($0.20 per post with a URL makes link posts economically pointless),
direct LinkedIn (vetted tier, closed permission), direct Google Business Profile (quota 0 until approved).
Threads and LinkedIn stay behind Publer.

### P3 — hygiene and proof

| # | Item | Effort |
| --- | --- | --- |
| P3.1 | Delete `get_setting_help_link()`; remove or wire the 17 unused settings | 0.5 d |
| P3.2 | Tests for `/feed` (merchant) and `/reposts`; assert `/snapshot` auth in `integration.php` | 0.5 d |
| P3.3 | Add PHP 8.5 + WordPress 7.1 to the CI matrix (code already nullable-clean) | 0.25 d |
| P3.4 | Release notes in the update modal (changelog from the zip's `readme.txt`/release body) | 0.5 d |
| P3.5 | Verify `uninstall.php` removes every option/table it created, including the new ones | 0.25 d |
| **P3 total** | | **2.0 d** |

---

## 6. Completion estimates

| Milestone | Scope | Effort | Calendar at ~half-time |
| --- | --- | --- | --- |
| **Set-and-forget v1** | P0 (4.0 d) + P1.1–P1.4, P1.6 (5.0 d) + P3.1–P3.2 (1.0 d) | **10 d** | ~3 weeks |
| Set-and-forget v1 without backoff/audit log | P0.1, P0.2, P0.4 + P1.1, P1.3 | 3.5 d | ~1 week |
| **Full parity with hosted schedulers** | v1 + P1.5 + P2.1–P2.3 + P3 | 19–21 d | ~6–8 weeks |
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

1. **Etsy** — build the OAuth import (2.5 d + review) or hide the five settings and label the page
   "manual import" (15 min)?
2. **Direct network tokens** — remove `pinterest_token`, `instagram_token`, `tiktok_token`, `youtube_*`,
   `reddit_*`, `ai_mode` entirely, or keep them for a future direct-posting mode?
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
| 2026-11-01 | 7.15.1 | Docs-only release: this roadmap, a README "Roadmap and known gaps" section, and smoke coverage for `sgap-setup`, `sgap-studio`, `sgap-chat`. |
| 2026-11-01 | 7.15.0 | First audit: call-graph and settings inventory, smoke coverage extended to all 12 admin pages, research into set-and-forget gaps and platform limits, effort estimates added. |
