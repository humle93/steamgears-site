# Steamgears Autopilot (WordPress plugin)

The WordPress side of the social autopilot. **Command Center** (Ultra.cc) generates
and publishes the posts; this plugin holds the queue, the approval flow, click
tracking and the ROI/learning numbers.

Install: Plugins → Add New → Upload → `../steamgears-autopilot-v7.2.0.zip` → Activate.

## API keys

Tokens are never shown again in Settings once saved (leave the field empty to keep
the value, tick *Clear* to remove it). They are not autoloaded on page views.

You can also keep them out of the database entirely by putting them in
`wp-config.php`. The setting is then locked in the admin:

```php
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

| Method | Path | Purpose |
|---|---|---|
| POST | `/queue` | Add a post. Required: `channel`, `title`, `destination_url`. Optional: `body`, `image_url`, `angle`, `content_type`, `format`, `post_id`, `scheduled_at`, `status` (`queued`/`draft`). Returns `id`, `tracking_url`, `utm_url`. Same channel + URL + title again → `409`. |
| GET | `/queue/due` | Next posts to publish (optional `?channel=`). Respects Autopilot on/off, quiet hours, daily cap and batch size. Returned items are marked `sending`. |
| POST | `/queue/<id>/status` | `status=published`, or `status=failed&error=...` (retried up to 3 times, then `failed`). |
| POST | `/drafts` | Add an AI draft for approval in WP (fields as the `sgap_ai_drafts` table). |
| GET | `/drafts?status=approved` | Drafts approved in WP, ready to queue. |
| POST | `/drafts/<id>/status` | `pending` / `approved` / `rejected` / `used`. |
| GET | `/stats?days=30` | Events per channel, learning table, queue status counts. |

Example:

```bash
curl -u "juno:xxxx xxxx xxxx xxxx" -X POST https://steamgears.eu/wp-json/sgap/v1/queue \
  -d channel=pinterest -d title="Steampunk goggles" \
  -d destination_url=https://steamgears.eu/steampunk-goggles/
```

## Maintenance

A daily WP-Cron job (`sgap_daily_cleanup`) puts posts stuck in `sending` back in
the queue and deletes raw events and unconverted clicks older than 180 days
(`sgap_event_retention_days` filter). Totals are kept in `sgap_learning`.

Deleting the plugin (not just deactivating) removes all its tables and settings.
