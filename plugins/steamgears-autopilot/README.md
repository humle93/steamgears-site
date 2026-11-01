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

Install: Plugins → Add New → Upload → `../steamgears-autopilot-v7.15.1.zip` → Activate.

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
| `steamgears-autopilot/queue-post` | Queue a post Claude (or you) wrote |
| `steamgears-autopilot/generate` | Run the AI writer now |
| `steamgears-autopilot/publish` | Run the Publer publisher now |
| `steamgears-autopilot/set-mode` | Autopilot / Full Auto on or off |
| `steamgears-autopilot/update` | Install the newest version from GitHub |
| `steamgears-autopilot/check-keys` | Test the Publer and AI keys (see below) |
| `steamgears-autopilot/check-publer` | Workspaces, accounts, channels without an account |

Example: "Write today's posts and show them to me" → `generate`, then
`list-drafts`, then `review-draft` for the ones you like.

## Also in the admin (from v7.4)

Queue page with Re-queue / Delete, *Approve & Queue* / *Approve only* / *Reject*
on drafts, Campaigns (learning matrix, winners and losers), Reports (30-day ROI and
ROAS against the budget, attributed orders), Etsy orders and profit, Full Auto
(suppressed combinations, reposts) and an XML product feed for Google Merchant
Center and Pinterest Catalogs (*Google Feed*, needs a feed secret and WooCommerce).

## Updates

From 7.6 the plugin updates itself from GitHub (`humle93/steamgears-site`, branch `main`):
a newer version shows up under *Plugins* and *Dashboard → Updates* like any plugin (auto-updates
can be switched on there), and the Steamgears dashboard has an **Update now** button. Claude can
run it too (`steamgears-autopilot/update`). The installed zip is
`plugins/steamgears-autopilot-v<version>.zip` from `main`, so a release is: bump the version, rebuild
the zip, merge to `main`.

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

WP-Cron only runs when the site gets visits. For exact timing, add a real cron
job on the host that calls `https://steamgears.eu/wp-cron.php` every 5 minutes.

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
in the post instead of the raw URL. A visit:

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
| POST | `/queue` | Add a post. Required: `channel`, `title`, `destination_url`. Optional: `body`, `image_url`, `angle`, `content_type`, `format`, `post_id`, `scheduled_at`, `status` (`queued`/`draft`). Returns `id`, `tracking_url`, `utm_url`. Same channel + URL + title again → `409`. |
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
[docs/plugin-roadmap.md](../../docs/plugin-roadmap.md). Two things worth knowing before you rely on this
unattended:

- A post that fails is retried up to three times the same day and then stays `failed` in the queue.
  Nothing emails you - the weekly report is currently the only outgoing mail.
- The Etsy API settings and the direct Instagram/Pinterest/TikTok/YouTube/Reddit/Telegram keys are
  registered but unused. Publishing goes through Publer (RSS feeds or API), and `/etsy/orders` takes a
  manual import.
