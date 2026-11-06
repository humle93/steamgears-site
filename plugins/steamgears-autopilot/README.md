# Steamgears Autopilot (WordPress plugin)

The whole social autopilot runs inside WordPress (Command Center is retired):

1. **Write** – once a day an AI model (Claude by default) writes one post per
   channel for 1–2 of the site's posts/products that have not been promoted in
   the last `repost_cooldown_days`.
2. **Approve** – posts wait under *Steamgears → AI Drafts* (Approve / Reject), or go
   straight to the queue when **Full auto** is on.
3. **Publish** – every 15 minutes the next due posts (quiet hours, daily cap and
   batch size apply) go to **Publer**, which posts to the connected Facebook,
   Instagram, Pinterest, LinkedIn, X, Threads, Bluesky, Mastodon, Telegram and
   Google Business accounts. Two modes (*Settings → Publer → Publish mode*):
   - **RSS feeds** (default, works on Publer **Professional**): posts are released
     into one feed per channel, `https://steamgears.eu/?sgap_feed=<channel>`. In
     Publer add each feed once (*Explore → RSS Feeds*), open its *Settings*, choose
     **Auto-post**, tick "use link title/description as caption", pick *photo* for
     Instagram/Pinterest/Google and *link* for the others, and select the account.
     Pinterest needs a default board in Publer. The dashboard lists the feed URLs.
   - **API** (Publer **Business**): posts are sent straight to Publer and the
     plugin records whether each one was published; failures are retried up to 3
     times with the reason shown on the Queue page.
4. **Measure** – every post links via `/go/<id>/`, so clicks (and WooCommerce
   orders) are counted per channel.

Install: Plugins → Add New → Upload → `../steamgears-autopilot-v7.33.2.zip` → Activate. On an existing
install the same zip arrives through the self-updater (see *Updates*), so an upload is only needed for the
first install or a recovery.

## Run it from Claude

The plugin registers WordPress *abilities* (WordPress 6.9+), exposed to MCP. A
Claude connected to the site's MCP server (the "Steam" connector) gets these tools,
all admin-only, none of them deleting anything:

| Tool | What it does |
|---|---|
| `steamgears-autopilot/status` | Switches, channels, setup state, why sending is paused, queue counts, pending drafts, recent errors, 30-day results |
| `steamgears-autopilot/list-drafts` | AI drafts (default: waiting for approval) with the page they promote |
| `steamgears-autopilot/review-draft` | `queue` (approve + queue), `approve` or `reject` one draft |
| `steamgears-autopilot/list-queue` | Queue items with status, attempts, errors, tracking links |
| `steamgears-autopilot/queue-post` | Queue a post Claude (or you) wrote. Takes `angle`, `content_type`, `format` and `campaign`, so a hand-written campaign post lands in the queue with the same dimensions the AI posts get (7.25) |
| `steamgears-autopilot/generate` | Run the AI writer now |
| `steamgears-autopilot/publish` | Run the Publer publisher now |
| `steamgears-autopilot/set-mode` | Autopilot / Full Auto on or off |
| `steamgears-autopilot/update` | Install the newest version from GitHub |
| `steamgears-autopilot/check-keys` | Test the Publer and AI keys (see below) |
| `steamgears-autopilot/check-publer` | Workspaces, accounts, channels without an account |
| `steamgears-autopilot/meta-audit` | Title and meta-description audit on published posts: missing, too long, too short — read-only |
| `steamgears-autopilot/optimize-site-seo` | Whole-site SEO/AEO/GEO scan in one pass; `fix=true` also writes the missing meta |

Example: "Write today's posts and show them to me" → `generate`, then
`list-drafts`, then `review-draft` for the ones you like.

## General WordPress tools (from v7.26)

Twenty-six more abilities in the `steamgears-tools` category, so the site can be
run end to end from an MCP client instead of a browser tab. All administrator-only.

| Tool | What it does |
|---|---|
| `steamgears-tools/site-info` | WordPress, PHP, theme, permalinks, timezone, plugin versions, sitemap URLs, content and ability counts |
| `steamgears-tools/get-option` | Read one option — refused for any name that looks like a credential |
| `steamgears-tools/update-option` | Write one option from an allowlist; dry run by default, needs `confirm`, returns a 72-hour undo token |
| `steamgears-tools/undo` | Put back the previous value using that token |
| `steamgears-tools/list-plugins` | Installed plugins with version, active state and available updates |
| `steamgears-tools/set-plugin-state` | Activate or deactivate one plugin (nothing is ever deleted) |
| `steamgears-tools/list-themes` | Installed themes and which one is active |
| `steamgears-tools/flush-rewrites` | Rebuild permalink rules |
| `steamgears-tools/flush-caches` | Object cache, Rank Math sitemap cache, LiteSpeed/WP Rocket/W3TC/Autoptimize |
| `steamgears-tools/list-cron` | Scheduled events with next run and recurrence |
| `steamgears-tools/run-cron` | Run one scheduled hook now |
| `steamgears-tools/list-posts` | Any post type with status, date, URL, and price/stock for products |
| `steamgears-tools/get-post` | One post with content, SEO meta and product fields |
| `steamgears-tools/create-post` | Create a post, page or product (draft by default) with SEO meta |
| `steamgears-tools/update-post` | Change content, status, slug, SEO meta or featured image |
| `steamgears-tools/trash-post` | Move to trash; permanent deletion only for something already trashed |
| `steamgears-tools/list-media` | Media library with id, MIME, URL, alt text |
| `steamgears-tools/upload-media` | Upload from a URL or base64, set alt text and featured image |
| `steamgears-tools/list-products` | WooCommerce products with price and stock |
| `steamgears-tools/get-product` | One product with SEO, categories, gallery |
| `steamgears-tools/update-product` | Price, sale price, stock, name, description, status |
| `steamgears-tools/indexnow-submit` | Ping IndexNow with up to 100 URLs |
| `steamgears-tools/indexnow-status` | The key file URL, whether it currently serves the key, and Rank Math's log tail |
| `steamgears-tools/sitemap-audit` | Reads `sitemap_index.xml`, follows every child sitemap, counts URLs, checks one URL |
| `steamgears-tools/list-abilities` | **Every** ability on the site, from every plugin, with its input schema |
| `steamgears-tools/call-ability` | Run any ability by name, after its own permission check |

The last two are the point: `list-abilities` shows what exists — WordPress core,
WooCommerce, Rank Math, AI Engine, this plugin — and `call-ability` runs it. New
plugins that register abilities become reachable without another code change.

### IndexNow key

`indexnow-submit` serves its own key rather than borrowing Rank Math's (that key
lives in an option no MCP client may read). The key is generated on first use and
served at `/?sgap_indexnow_key=<key>` and `/sgap-indexnow-<key>.txt`. If the host
firewall blocks the IndexNow crawler, `indexnow-status` reports the probe result
so the failure is visible instead of silent. A 202 from the API only means
"accepted, validation pending"; 200 means the key was found.

## Also in the admin (from v7.4)

Queue page with Re-queue / Delete, *Approve & Queue* / *Approve only* / *Reject*
on drafts, Campaigns (learning matrix, winners and losers), Reports (30-day ROI and
ROAS against the budget, attributed orders), Etsy orders and profit, Full Auto
(suppressed combinations, reposts) and an XML product feed for Google Merchant
Center and Pinterest Catalogs (*Google Feed*, needs a feed secret and WooCommerce).

## Whole-site SEO, GEO and AEO (7.19)

*Content Studio → **Optimise the whole site*** replaces clicking through pages one at a time:

| Button | What it does | Cost |
|---|---|---|
| **Scan the whole site** | Scores every published post, page and product in one pass against 20 rules, plus four whole-domain checks (llms.txt, sitemap, robots.txt, product feed) | Free — database and HTTP only |
| **Scan and fix meta** | Runs the same scan, then writes Rank Math/Yoast title, description and focus keyword for the weakest URLs | One AI call per page, default 8 per round |

Rules by group:

- **SEO** — title and meta length, missing meta, focus keyword, keyword in title, one H1, enough text for the type (post 500 / page 400 / product 120 words), internal links, image alt text, featured image, slug, duplicate title and duplicate description across the site.
- **AEO** (answer engines) — question-shaped H2/H3, an FAQ block with `FAQPage` schema, a direct answer in the first paragraph, lists or tables.
- **GEO** (generative engines) — the brand named in the opening, quotable numbers, structured data, outbound citations, freshness.

The scan is stored in one option (`sgap_seo_site`) and refreshed lazily once a week when an
administrator opens wp-admin, because WP-Cron is not dependable on this cached host. Body text is
never rewritten by this screen; content suggestions stay in *Optimise SEO of a page*.

## Pre-flight per channel (7.20)

Every post is checked against the destination network's own limits **before** anything is sent to
Publer (`includes/preflight.php`, `SGAP_Preflight`). Publer's rejection arrives as a vague error after a
failed upload and also spends one of the three attempts; this check runs on the site, first, and costs
nothing.

| Channel | Refused | Repaired automatically |
|---|---|---|
| Instagram | no photo; PNG/WebP/GIF instead of JPEG; width under 320 px; photo outside 4:5–1.91:1 | caption over 2 200 characters |
| Pinterest | no photo; no board set in Settings | title over 100, description over 500 |
| Threads | — | caption over 500 (the tracking link is counted in), more than one hashtag |
| Facebook / LinkedIn | — | caption over 63 206 / 3 000 |
| X | — | 280 characters, with any link counted as 23 |
| Bluesky / Mastodon / Telegram / Google | — | caption over 300 / 500 / 1 024 / 1 500 including the link |

Two safety valves. The checker never downloads the photo, so type and size are only judged when the image
is in the media library (mime type and dimensions come from the attachment metadata) or the file extension
is known — an image it cannot inspect is never blocked on a guess. And queueing a post reports the same
problems as **warnings** in the API response (`warnings`), because a photo can still arrive later: the
featured image, or the AI photo `SGAP_RSS` adds at release. The refusal happens at publish time, which is
the moment before Publer.

The ability `steamgears-autopilot/check-post` runs the same rules without queueing anything and returns
`problems`, `repairs` and the text that would actually be sent.

## Blackout calendar (7.21)

Settings → *Schedule & limits* → **Blackout days** takes one range per line:

```
2026-12-24..2026-12-26 Christmas
2026-12-31 New Year's Eve
```

Nothing is released on those days, and a post that is already queued inside a range is **moved to the
next free day at the same time of day** — not dropped, and not saved up so the whole backlog lands at once
when the range ends. The move runs from the publish tick every 15 minutes, so a range you add this morning
is honoured before the next post goes out, and a post that was shifted is not shifted again on the next
tick. The Queue page explains why nothing is going out, and the range label is carried into the message.

Two details worth knowing. Only queued posts move: `published` and in-flight `sending` rows are never
touched, so a blackout cannot rewrite history. And the calendar is a day filter, not a pause: the daily cap,
quiet hours and the pre-flight checks still apply on the free day the post lands on. A post placed more
than 60 days ahead of everything blocked is placed anyway, so a badly typed never-ending range cannot keep
a post out of the queue forever.

## Quality gate before queueing (7.22)

Every post enters the queue through one function, so the gate sits there: a post that a person would have
stopped gets stopped while it is still a draft, not after Instagram has it. Settings → *Publishing* →
**Quality gate** and **Banned words**.

It checks four things:

- **Banned words.** Your own list, one per line or comma-separated. Whole words only, so `sale` does not fire
  on `wholesale` — but a hashtag counts, because `#sale` is the same word in a different dress.
- **Dead links.** Internal links are resolved against WordPress itself, so a link to a slug that no longer
  exists, or to a post that is still a draft, is caught before it ships. Archives, feeds, search and the front
  page are recognised and left alone. External hosts are **not** fetched — see the limitation below.
- **Missing alt text.** A featured image with an empty *Alternative text* field is reported, so screen readers
  and image search are not left with nothing.
- **Near-duplicate captions.** The caption is compared with the last 40 posts on the same channel; 85 % word
  overlap or more is the same caption in other words, and the message names the post it repeats.

Three modes: **Block** (default) refuses the post and says why, **Warn** queues it and reports the problems,
**Off** does not look. A refusal is not a dead end: pass `force`, or press *queue anyway* where the plugin
shows the refusal, and the post is queued with its findings returned as warnings — a deliberate repost is
your call, not the plugin's. Captions shorter than eight words are never called duplicates: "New drop" and
"New drop!" are similar without being a repeat worth blocking.

**The limitation, stated plainly:** external links are not probed. A HEAD request per link would put an
outbound HTTP dependency in the queueing path — slow, rate-limited and wrong the one time the far site is
briefly down. So the gate catches what it can prove without the network: placeholders (`example.com`),
links with no domain, `http` instead of `https`, and internal addresses that do not resolve. A Pinterest pin
that 404s is still only found by opening it.

## Post meta audit (7.23)

The ability `steamgears-autopilot/meta-audit` lets Claude run the meta round itself: it reads the published
posts and pages and reports, per post: title length, meta-description
length and what is outside the limits — **title 60 characters, description 70–155 characters**. Posts with no
description at all are counted separately, because that is a different fix from shortening one.

The description is read from Rank Math (`rank_math_description`) and, if that is empty, Yoast
(`_yoast_wpseo_metadesc`); the report says which plugin answered, so a "0 missing" can be trusted. Nothing is
written: the audit only reads, and the answer names the worst posts first (most problems, then the longest
title) so the next meta round starts with the posts that matter.

`meta-audit` takes two optional arguments: `scan` (how many of the newest posts to check, default 100, max
500) and `limit` (how many of the worst to return, default 20, max 100).

## Naming a campaign (7.24)

Every link already carried `utm_campaign=steamgears_premium_evergreen`, hardcoded, so a named campaign could
not be separated from the everyday stream in GA4. Now the name has two levels:

- **Settings → General → Campaign name** (`utm_campaign`) sets the name for everything queued from now on.
  Set it once at the start of a campaign and change it back afterwards — the old posts keep the name they were
  queued with, because the value is baked into each link when the post joins the queue. Letters, digits, `-`
  and `_` only; empty means `steamgears_premium_evergreen`, so nothing ever goes out with a blank campaign.
- **Per post:** pass `campaign` to `POST /queue` (or the `queue-post` ability, which since 7.25 also takes
  `angle`, `content_type` and `format`) for a single post, e.g.
  `campaign=clockwork_punk_2026_11`. The post's own name wins over the setting, which is what a finite series
  needs: twelve posts can be measured as one campaign while the evergreen posts run on the setting.

In GA4 the campaign shows up under *Traffic acquisition → Session campaign* and in *Explore* as the dimension
`Session campaign`. The plugin's own `/stats` still counts clicks and orders per channel and angle, so a
campaign can be read from two independent sources instead of one.

## Remind me instead of posting (7.27)

Some formats only exist inside the network's own app: a story, a carousel, a native upload. The API refuses
them, so the post spent three attempts, landed in *failed* and the owner had to read an error to find out what
the caption said anyway. A queue item can now be flagged **remind me**: it is neither sent to Publer nor
released into the RSS feed — it is mailed to you with everything needed to post it by hand, and its status
becomes `reminded`.

- **Where:** the *Remind me* button on a row in **Queue**, or `remind: true` on `POST /queue` and on the
  `queue-post` ability. *Re-queue* clears the flag again, because re-queueing means "let the autopilot try".
- **The mail** carries the channel, format, title, due time, the full caption, the image URL (or a note to pick
  one from the media library), the tracked link and a link to the queue row. It goes to the address under
  **Settings → Alerts** (`alert_email`, falling back to the admin address).
- **Not an alert:** a reminder is the post itself, so it is mailed even when Alerts is set to `off`. A queue item
  is never mailed twice inside six hours, and a broken mail setup cannot leave it spinning: the item is marked
  `reminded` either way, with the reason in the publish log.
- **Not a failure:** `reminded` is terminal. No retry, no backoff, no failure mail, and the publish log gets an
  outcome of its own (*Reminded by mail*) so the history still says what happened.

RSS mode uses the same path: a flagged item is held back from the feed (leaving it in would be the opposite of
"do not post this one for me") and mailed instead.

## Bluesky (direct posting)

Bluesky needs no app review and no partner tier, so the plugin posts there itself instead of going through
Publer: `includes/bluesky.php` logs in with `com.atproto.server.createSession` and posts with
`com.atproto.repo.createRecord`, both through WordPress' HTTP API. Configure it under **Settings → Publer**:

1. **Handle** — e.g. `steamgears.bsky.social`. The `@` is optional.
2. **App password** — Bluesky → Settings → Privacy and security → App passwords. Not the account password.
   It is stored like the Publer key: never shown again, and an empty field keeps the saved value.
3. **Channels** — add `bluesky` to the list. That is also the switch: drop it again, or clear the password,
   and posts for that channel go back to Publer.

The login is cached for 90 minutes, and a refused token (401) is renewed once before the post is retried.
Text follows Bluesky's 300 **graphemes**, not bytes: the tracking link is reserved first and never cut, the
body is trimmed at a grapheme boundary with an ellipsis, and the link is sent as a richtext facet so it is
clickable. Hashtags become tag facets. Direct posting runs in API mode only — in RSS mode the feed still owns
every channel.

Every failure is the ordinary path: missing credentials, a refused login, a refused post and a network error
all put the item back in the queue with the same backoff, fire the alert after the last attempt and write a
publish-log row. Nothing is swallowed, and no SDK is needed.

## Mastodon (direct posting)

One call to the instance's own API: `POST {instance}/api/v1/statuses` with the access token as a Bearer header
and an `Idempotency-Key` per queue item, so a retried run cannot post twice. Settings → Publer:

1. **Instance** — the server the account lives on, e.g. `mastodon.social`. `https://` is added when missing.
2. **Access token** — Preferences → Development → New application, scope `write:statuses`.
3. **Channels** — add `mastodon`. Removing it, or clearing the token, hands the channel back to Publer.

The limit is **500 characters** by default because every instance accepts that; raise *Mastodon limit* only when
the instance documents more. Trimming is grapheme-aware and keeps the tracking link whole. The API's own error
text — `The access token is invalid`, a validation message — is what lands in the queue and the publish log,
so a refused post says why.

## Telegram (direct posting)

One call to `https://api.telegram.org/bot<token>/sendMessage`. Settings → Publer:

1. **Bot token** — from BotFather (`/newbot`, or `/token` for an existing bot).
2. **Chat id** — `@channelname` for a public channel, or the numeric id; a group id is negative. The bot has to
   be an administrator of the channel, otherwise the API refuses the message.
3. **Channels** — add `telegram`.

Telegram answers **HTTP 200 with `{"ok": false}`** when it refuses a message, so the plugin checks `ok` before
anything else and reports `description` as the error — a refusal never looks like a published post. The limit is
4096 characters, trimmed the same way as the others, and the bot token never appears in an error message.

## Multi-language variants

One source post can carry a caption per language. Under **Settings → AI content**, set the language the posts
are written in (*Source language*, default `en`) and the languages you want (*Languages*, e.g. `da,sv,de`).
When the list is not empty, every post that enters the queue also queues one translated caption per language.

The translated page comes from **WPML or Polylang** — the plugin never guesses a URL. A language only gets a
variant when the post has a translation in that language, and the variant points at that translation instead
of the original. The caption is translated by the configured model through the same HTTP API as the rest of
the plugin, and the `sgap_locale_translator` filter can hand that job to DeepL or to a human instead.

Nothing is dropped silently: a language without a translated page, without an AI key, with an API error or
with an empty answer is reported back in `locale_errors` on the queue result, while the source post stays
queued. Variants are ordinary queue rows — same channel, angle, format, image, quality gate and blackout
rules — carrying their language and the id of the post they came from, and a variant never spawns variants
of its own.

## Command Center link (7.33)

Command Center (the self-hosted dashboard) talks to this plugin **one way**: it reads `GET /wp-json/sgap/v1/snapshot`
every 30 minutes and sends posts as drafts with `POST /drafts`, using an administrator application password.
Nothing on the site calls Command Center.

- Every Command Center call carries `X-Command-Center: <version>`. The plugin notes it (option `sgap_cc_seen`,
  at most once a minute, only for administrators on the `sgap/v1` routes).
- The Self-check card shows **"Command Center … connected · last sync N min ago"**; `status` and `/snapshot`
  report it as `command_center` (`connected` / `stale` / `never`).
- If a Command Center that used to call has been silent for **more than 2 hours**, the daily self-check lists it
  as a problem (and so the alerts fire). A site that never had Command Center is not nagged.
- Command Center 7.19+ also calls `wp-cron.php` every 5 minutes, so the publishing cron runs on time even on a
  quiet site. A real host cron (below) is still the better fix.

## Updates

From 7.6 the plugin updates itself from GitHub (`humle93/steamgears-site`, branch `main`):
a newer version shows up under *Plugins* and *Dashboard → Updates* like any plugin (auto-updates
can be switched on there), and the Steamgears dashboard has an **Update now** button. Claude can
run it too (`steamgears-autopilot/update`). The installed zip is
`plugins/steamgears-autopilot-v<version>.zip` from `main`, so a release is: bump the version, rebuild
the zip, merge to `main` — and then click update. The click is the deploy; nothing changes on the site
before it, because the MCP surface can read plugins but cannot install or activate them.

### Shipping a release

1. Bump `Version:` in `steamgears-autopilot.php` and `SGAP_VERSION` — they must agree, CI fails otherwise.
2. Rebuild the zip from the folder so the name and the contents match:
   `cd plugins && rm -f steamgears-autopilot-*.zip && zip -qr steamgears-autopilot-v<version>.zip steamgears-autopilot`
3. `php plugins/tests/smoke.php` (408 checks) and `diff -r` the zipped copy against the source. CI repeats
   both, plus `php -l` on PHP 8.0–8.5 and WordPress 6.4 / 7.1 / latest.
4. Commit code, zip, README and `ROADMAP.md` together and push to `main`.
5. **Click *Plugins → Update*** (or *Dashboard → Updates*). Auto-updates can be switched on in the same
   screen, which makes step 5 happen on its own after the next WP-Cron run.

Check that the release landed, in this order — each step can hide the previous one:

- `wp_get_plugins` reports the new version with `active: true`.
- A feature from the release answers from the site. For 7.22: a post that fails a `block` check is refused
  and the reason is named. `check-post` proves the decision without writing; a `queue-post` refusal also
  proves the gate sits inside the queue, but needs the site's MCP access to allow writes.
- A release with an ability answers too: `meta-audit` (7.23) returns the worst posts with their title and
description lengths and writes nothing.
- `steamgears-autopilot/status` shows the new setting — `quality_gate: {mode, banned_words}` for 7.22,
  `utm_campaign` under *General* for 7.24 (and `check-post` still writes nothing).

> **7.22.0 was the live release when this was written; 7.24.0 ships the campaign name (7.23.0 shipped the
> `meta-audit` ability).** The proof steps below are unchanged, and the update click is still the deploy.
>
> **7.22.0 measured live.** Measured 2026-11-02 after the update click: both `wp_list_plugins` and
> `steamgears-autopilot/status` report 7.22.0. Two owner-side settings limit what can be proven: *Banned words*
> is empty (so that check cannot fire) and AI Engine's *Drafts Only* refuses `queue-post` before the gate sees it.
> Site-side status and the measurements are in [`docs/roadmap.md`](../../docs/roadmap.md) §2b.

## Setup

1. **Publer**: on Professional keep *Publish mode = RSS feeds* and add the feeds
   (see above); steps 2–3 are only for the API mode. On Business: Settings →
   Access & Login → API Keys → create a key with the scopes `workspaces`,
   `accounts`, `posts`, `media`.
2. In WordPress: *Steamgears → Settings → Publer*: paste the API key, save, then
   click **Test Publer** on the dashboard. It lists your workspaces (copy the id
   into *Publer workspace id*, save) and then your connected accounts.
3. *Channels*: e.g. `facebook, instagram, pinterest`. For Pinterest also set the
   board id. Instagram and Pinterest need a featured image on the post.
4. *AI content*: provider **Anthropic** with a Claude API key (model defaults to
   `claude-opus-5-5`), or **OpenAI-compatible** (AIMLAPI by default) with a key
   and a model name.
5. *General*: switch **Autopilot** on. Leave **Full auto** off at first and approve
   the drafts by hand until you like the tone.
6. Use **Generate posts now** / **Publish queue now** on the dashboard to try it
   without waiting for the cron.

**Key test.** Saving a Publer or AI key (or the workspace, channels, provider or
model) tests it straight away; the result appears at the top of the page and on
the dashboard (✓/✗), and *Test API keys* repeats it. Publer: key valid, scopes,
workspace (filled in automatically when the key has only one), an account for
every channel, Pinterest board set. Claude: key valid and model available via the
Models API (no tokens used). OpenAI-compatible: key valid and model offered by
`/models`.

## Real cron (publishing on time)

WP-Cron only runs when the site gets visits, so a quiet site or a full page cache
can delay the 15-minute publisher. Add a real cron job on the host (or an external
service such as cron-job.org) that calls the site every 5 minutes:

```
*/5 * * * * curl -s https://steamgears.eu/wp-cron.php?doing_wp_cron > /dev/null 2>&1
```

If `DISABLE_WP_CRON` is set in `wp-config.php`, WordPress never runs cron on page
loads, so the external job is required.

Tools → Site Health shows the check *Autopilot publishing cron*:

- **Critical** when the publisher is 30 minutes or more overdue (the same warning
  appears on the dashboard).
- **Recommended** when `DISABLE_WP_CRON` is set, as a reminder to keep the external job running.
- **Good** when the publisher is on time.

## API keys

Tokens are never shown again in Settings once saved (leave the field empty to keep
the value, tick *Clear* to remove it). They are not autoloaded on page views.

You can also keep them out of the database entirely by putting them in
`wp-config.php`. The setting is then locked in the admin:

```php
define('SGAP_PUBLER_API_KEY', '...');
define('SGAP_AI_API_KEY', '...');
define('SGAP_TELEGRAM_BOT_TOKEN', '...');
// SGAP_PINTEREST_TOKEN, SGAP_INSTAGRAM_TOKEN, SGAP_TIKTOK_TOKEN, SGAP_YOUTUBE_TOKEN,
// SGAP_ETSY_OAUTH_TOKEN, SGAP_ETSY_REFRESH_TOKEN, SGAP_MERCHANT_FEED_SECRET
```

## Click tracking

Every queue item gets a short link `https://steamgears.eu/go/<id>/`. Use that link
in the post instead of the raw URL. **A `/go/<id>/` with no queue item is treated as a product short
link** and resolves to that product's permalink (7.32.0), so the same route can be used in guides,
newsletters and print; only an id that is neither a queue item nor a product falls back to the front
page. Product links are not counted in the per-channel click stats, because they have no queue item
to attribute to. A visit:

1. logs a `click` event (bots and link-preview crawlers are ignored),
2. sets a first-party cookie `sgap_click` (30 days, HttpOnly),
3. redirects to the UTM-tagged destination (only steamgears.eu and etsy.com;
   add hosts with the `sgap_redirect_hosts` filter).

When WooCommerce is active, a paid order (processing/completed) placed with that
cookie is credited to the post: `sgap_cro` gets the order id and revenue and an
`order` event is logged. Register `sgap_click` in Complianz as a statistics cookie.

## REST API — `/wp-json/sgap/v1`

Auth: an administrator's **application password** (Users → Profile → Application
Passwords), sent as HTTP Basic auth.

The publisher above uses the same queue. The API is for other tools that want to
add posts or read the numbers.

| Method | Path | Purpose |
|---|---|---|
| POST | `/queue` | Add a post. Required: `channel`, `title`, `destination_url`. Optional: `body`, `image_url`, `angle`, `content_type`, `format`, `post_id`, `scheduled_at`, `campaign` (utm_campaign for this post; empty = the setting), `status` (`queued`/`draft`). Returns `id`, `tracking_url`, `utm_url`. Same channel + URL + title again → `409`. |
| GET | `/queue/due` | Next posts to publish (optional `?channel=`). Respects Autopilot on/off, quiet hours, daily cap and batch size. Returned items are marked `sending`. |
| POST | `/queue/<id>/status` | `status=published`, or `status=failed&error=...` (retried up to 3 times, then `failed`). |
| POST | `/drafts` | Add an AI draft for approval in WP (fields as the `sgap_ai_drafts` table). |
| GET | `/drafts?status=approved` | Drafts approved in WP, ready to queue. |
| POST | `/drafts/<id>/status` | `pending` / `approved` / `rejected` / `used`. |
| GET | `/stats?days=30` | Events per channel, learning table, queue status counts. |
| GET | `/snapshot?days=30&limit=100` | Everything in one read-only call, for an outside dashboard (Command Center): queue, drafts, results per channel and per day, conversions, learning, suppression, reposts, Etsy orders, settings (secrets only as set/not set) and health. |
| GET/POST | `/suppression` | List, or block/reduce a channel+angle+type+format combination (Full Auto skips blocked ones). |
| GET/POST | `/reposts` | List, or record a repost of a winner (max `repost_max`). |
| POST | `/etsy/orders` | Import an Etsy order (`etsy_order_id`, `revenue`, `fees`, `shipping`, ...). |
| GET | `/feed?key=<secret>` | XML product feed (public, protected by the feed secret). |

Example:

```bash
curl -u "juno:xxxx xxxx xxxx xxxx" -X POST https://steamgears.eu/wp-json/sgap/v1/queue \
  -d channel=pinterest -d title="Steampunk goggles" \
  -d destination_url=https://steamgears.eu/steampunk-goggles/
```

## Maintenance

A daily WP-Cron job (`sgap_daily_cleanup`) puts posts claimed through the REST API
but never reported back in the queue and deletes raw events and unconverted clicks older than 180 days
(`sgap_event_retention_days` filter). Totals are kept in `sgap_learning`.

Deleting the plugin (not just deactivating) removes all its tables and settings.

## Roadmap and known gaps

The audit behind this release and the prioritised roadmap with effort estimates live in
[ROADMAP.md](ROADMAP.md). Two things worth knowing before you rely on this
unattended:

- A post that fails is retried with backoff (5 → 15 → 45 minutes) and then stays `failed` in the queue.
  Settings → Alerts mails you when that happens and when the queue is running dry; `daily` mode collects
  both into one digest after the self-check.
- Every attempt is also written to the **Publish log**: channel, outcome, HTTP status, a response excerpt and
  the actor (cron, admin, REST, WP-CLI). The queue shows the current state; the log keeps the history the
  `error` column overwrites. Rows are kept for `audit_keep_days` (default 90, `0` = forever) and pruned by
  the daily maintenance job.
- The Etsy API settings and the direct Instagram/Pinterest/TikTok/YouTube/Reddit/Telegram keys are
  registered but unused. Publishing goes through Publer (RSS feeds or API), and `/etsy/orders` takes a
  manual import.
