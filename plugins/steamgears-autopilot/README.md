# Steamgears Autopilot (WordPress plugin)

Source, roadmap and releases: [github.com/humle93/steamgears-site](https://github.com/humle93/steamgears-site)
— the repository is private, so the link opens for the GitHub accounts the owner has given access. The plugin
itself reads its updates from the same repository (`plugins/steamgears-autopilot` on `main`).

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

Install: Plugins → Add New → Upload → `../steamgears-autopilot-v7.72.0.zip` → Activate. On an existing
install the same zip arrives through the self-updater (see *Updates*), so an upload is only needed for the
first install or a recovery.

Patch notes: [`CHANGELOG.md`](CHANGELOG.md) says what was added, changed or fixed in every
release from 7.65.0 onwards, newest first. The top entry always matches the version in the plugin
header and the zip name, and `python3 tools/patch_notes.py --check` fails the check when a version
ships without notes.

## Keys in one place (7.47)

Every credential the plugin uses sits on **one** screen, *Settings → Keys*, one card per service:
AI text, AI images, Publer, Printful, SerpApi, Bluesky, Mastodon, Telegram, the product-feed secret
and the MCP token. Each card says what the key is for, where to get it (a link to the service or to
your own Mastodon instance), whether it is saved, and whether the last test passed. Nothing else in
the plugin renders a key field: the AI, Publishing, Shop and MCP tabs carry settings only, and the
settings page strips any secret that is listed outside the Keys tab, so a key added to the wrong tab
later still appears in exactly one place.

A saved key is never printed back. The field is always empty with the placeholder *saved – leave empty
to keep*; typing a new value replaces it and ticking *Remove saved key* deletes it. A key that lives in
`wp-config.php` as `SGAP_<KEY>` replaces the field with a note naming the constant. *Test the saved keys*
runs the same check as the setup guide and shows the answer next to the button. The setup guide no longer
asks for the AI key once one is saved — it points at *Settings → Keys* instead.

The screen is built the way WordPress 7.x builds *Settings → Connectors*: a grid of cards, each with an
icon, a purpose, its source and a status pill; the tab bar and the save bar are sticky, focus rings follow
`:focus-visible`, and the layout collapses to one column below 782 px. The plugin still ships its own
stylesheet — no build step, no `@wordpress/ui` — and picks up the WordPress design tokens where they exist.

## A backup provider, so one dead account cannot stop everything (7.67.1)

The primary provider is whatever *AI provider* says — AIMLAPI by default. This is the second one:
*Settings → Keys → **OpenRouter (backup)*** takes an OpenRouter key **and** a model, and both are
needed before the backup exists. An empty model means off, on purpose: OpenRouter has no default,
and a guessed slug only fails on the day it is needed.

It is called **only when the primary provider fails**: a transport error, `401`/`403` (dead or
revoked key), `402` (no credit), `429` (rate limit) or a `5xx`. A `2xx` answer never starts it, even
an empty or cut-off one — the provider *did* answer, and a second call would be billed twice. A
`400`/`404`/`422` is our own bad request and is reported, not retried elsewhere. A model name the
primary rejects is still retried once on the primary's own default first.

The backup request is the same body on `https://openrouter.ai/api/v1/chat/completions` with the
backup key, the backup model, and `reasoning_effort` removed (that parameter is AIMLAPI's; OpenRouter
may refuse a request carrying it). Every surface that speaks `/chat/completions` uses the same path —
the generator, the Content Studio and the AI Chat — so a failover in one is a failover in all three.
It is **not** used while OpenRouter is the provider itself: that would be a backup to the same
service. The Anthropic wire format and AI images are out of scope.

Nothing is silent. A failover writes one row to the error log (`Fejlsøgning`, source `ai-fallback`)
with the primary's status and the model that answered — never the key and never the request — and the
AI Chat answer ends with a sentence saying the primary failed and the backup answered. Neither the
provider nor the model is changed: the next call tries the primary again. *Test connections* tests the
backup **pair** (that key, that model), because testing it with the primary's model would be testing a
combination the plugin never sends. As with every key here, it can live in `wp-config.php` as
`SGAP_OPENROUTER_API_KEY`.

Since 7.69.1 the test also says *which* of the two problems it found. OpenRouter keys start with
`sk-or-v1-`; a saved value that does not is named as **not an OpenRouter key** (with its length) instead of
being sent and answered with a 401 about a missing header — that is the answer an AIMLAPI key, a pasted
`curl` command or a masked value gets, and on 10/11 it read as “the key was rejected” when the truth was
“that is not the key”. If the value does start with `sk-or-` and OpenRouter still reports no Authorization
header, the message says so and points at a line break in the field or a host/proxy that strips the header.
Every key is also trimmed before it becomes a header, so a trailing newline from a paste can no longer turn
`Bearer sk-or-…` into something no gateway parses.

**7.70.1 sends the header from one place.** The test and the failover each built their own `Authorization`
header, so a test could prove a header the real call never sent. `SGAP_AI::openrouter_headers()` is now the
only builder and `SGAP_AI::openrouter_post()` the only request, used by the key test, the failover and the
generator. The token is cleaned by `SGAP_AI::bearer()`: whitespace, line breaks and invisible characters
(U+200B, U+00A0, U+FEFF) are removed, because a header value holding one is dropped by the transport and the
gateway then answers *Missing Authentication header* — the same answer a wrong key gets. And when OpenRouter
still reports no header, `SGAP_AI::stripped_by()` runs the site's own `http_request_args` filter on a copy of
the request: if a plugin removes `Authorization` from outgoing calls, the message names that filter instead of
sending you to openrouter.ai/keys. Eleven new smoke checks observe the outgoing request itself — the exact
`Bearer` header, one line, no key in any message — and four of them fail if the cleaning is removed.

## A module you can switch on: Cirla (7.71)

Cirla is the first part of the plugin that is **a module of its own**. It is off by default, and while it is
off its file is not even loaded from `steamgears-autopilot.php` — so a module that is off cannot register an
ability or a cron job. Switching it on under *Settings & keys → Cirla* takes effect on the next request.
It also gets a page of its own under **Steamgears → Create → Cirla**: the module's state, the four actions
as buttons, and its log — the same code the abilities call, so the page and MCP cannot drift apart.

It exposes four actions over the same MCP connection as everything else, all guarded by its own capability
`sgap_cirla_run` (administrators have it through `manage_options`):

| Action | What it does |
|---|---|
| `cirla-status` | The module's state: on/off, allowed actions, next daily job, AI calls spent today, drafts it wrote, last log rows |
| `cirla-draft` | One social post for a topic or a page, through the same AI path as the daily writer, saved as `pending` under AI Drafts with `angle = cirla` |
| `cirla-seo-check` | One page against the plugin's own meta limits (title 60, description 70–155) — reads only |
| `cirla-social-suggestions` | Up to five suggestions, one per channel, saved nowhere |

Nothing it does queues, publishes or deletes: a draft waits for approval exactly like an AI draft, and it
passes the quality gate first — a dead or unpublished link stops it. Three settings keep it small: which
actions are allowed, a **daily AI budget** (5 calls by default, `0` = none), and what its log keeps. The
daily job records that it ran and spends nothing; writing posts stays a called action, because a module that
is on must not spend the owner's credits on its own. The AI call sits behind the `sgap_cirla_generate`
filter, so a human editor or another model can replace it — the same seam as `sgap_evergreen_variation`.

## AI profiles (7.41.2)

The chat's persona and what it may touch are one setting — *Settings → AI content → AI profile* — and one
tool, `set-ai-profile`, so the assistant can switch when it is asked to:

| Profile | Who it is | Tools |
|---|---|---|
| **Autopilot assistant** (default) | The social autopilot's own assistant | Reads everything; the site-changing tools are withheld |
| **Site administration** | WordPress and WooCommerce administrator | Every tool the site has — and **access all**: AI Engine's Dynamic REST + Abilities are switched on the moment the profile is chosen, so the whole REST surface is reachable after the MCP client reconnects |
| **WordPress SEO expert** | Technical and on-page SEO | Everything read-only, plus content, SEO fields, products and media; plugin and settings work is refused |

`enable-full-access` itself stays out of the chat in every profile: the profile is the switch, so the assistant
cannot quietly change its own access level. Switching away from *Site administration* leaves full REST access as
it is — turn it off on the same page if you want it off.

## Agent feedback loop (7.70)

Every writer used to start from the same fixed prompt and one page's snapshot. A channel that had burned
120 clicks without an order was still picked the next day, and a channel that had gone quiet stayed quiet.
`SGAP_Coach` closes that gap: it reads what the site has already measured and puts it in the prompt.

- **One measured brief.** `SGAP_Coach::brief()` builds a short block from `sgap_events` (clicks, orders,
  revenue and the last publish per channel and angle, 30 days), the pending drafts and WordPress' own post
  counts. It is appended to the daily writer, the campaign agent (same prompt), the Studio article writer
  and the Studio SEO editor. The block ends with the rule that only its numbers may be cited.
- **Lessons, rebuilt daily.** A cron (`sgap_coach_learn`) turns the numbers into at most 12 dated rules: an
  angle with 25+ clicks and no order, a channel that sells, a channel silent past 14 days, a draft backlog
  of 20+. They are stored in one non-autoloaded option. A rule is only drawn when the data supports it, and
  the day it was first drawn is kept.
- **Readable from outside.** The read-only ability `steamgears-autopilot/agent-brief` returns the facts, the
  outcomes, the lessons and the brief itself, and the dashboard card *What the agents have learned* shows
  the same list with its evidence.
- **Nothing is published, and nothing is guessed.** No new table, no setting and no publishing call; a
  measurement that could not be read is left out, and the brief says so.

The repo's weekly SEO agent gets the same idea from the other side: `tools/agent_brief.py` measures the live
catalogue (products, currency, price range, categories, posts, pages, newest post) and writes
`docs/agent-brief.md`, which `seo_agent.py` reads as its own source. That is why the plan no longer describes
39 products in USD.

## Campaign agent (7.68)

The autopilot promotes whatever is next in line. The campaign agent starts from something you chose: a
published post, page or product, and a written goal — *"sell the brass goggles to cosplayers before
Halloween"*. It writes one post per configured channel for that page, runs each one through the channel
pre-flight and the quality gate, and saves them **as drafts under AI Drafts**. That is the whole of it:

- **It never queues and never publishes.** Full Auto does not change that. The agent calls neither
  `SGAP_Queue` nor `SGAP_Publer`; approving a draft is still a person pressing *Approve & Queue*.
- **The findings stay with the draft.** Whatever the channel rules and the quality gate said is stored on
  the row (`gate_report`) and printed on AI Drafts, so the warning does not vanish between writing and
  approving.
- **The campaign name travels.** The name you type (or the page slug, if you leave it empty) is stored on
  every draft and carried into the queue as `utm_campaign`, so GA4 can tell this campaign from the
  evergreen stream.
- **The goal shapes the angle, not the facts.** It goes into the prompt as a direction, with an explicit
  instruction not to state it as news or promise what the page does not say.

On the dashboard, *Campaign agent* asks for the page, the goal, the campaign name and the channels. The
same run is available as the ability `steamgears-autopilot/campaign-drafts` (so Claude can write the
drafts), and a channel that already has a pending draft for the same campaign is skipped — one run writes
at most 10 drafts.

## Feed health (7.69)

In RSS mode the plugin sends no token — Publer polls one feed per channel and posts what it finds — so the
question "can this channel send?" has a different answer than in API mode. *Feed health* is that answer:
for every configured channel it reads the same rows the feed renders and reports one state.

| State | What it means |
| --- | --- |
| `ok` | The feed carries posts with a title, text and (where the channel demands it) a photo |
| `empty` | Nothing to post — Publer sees no new item, and the channel goes quiet |
| `stale` | The newest post is older than the limit (7 days, filter `sgap_feed_stale_days`) |
| `invalid` | A post the feed carries cannot be posted as it stands: no title, no text, or no photo on Instagram/Pinterest |
| `off` | API mode is on, so no feed is in use and there is nothing to check |

Each row also carries the post count, the age of the newest post and the feed address Publer is given, so the
same answer says *why*. A summary counts the states and names the channels that need attention. It is part of
`steamgears-autopilot/channel-health` and `GET /sgap/v1/channel-health` (`feeds` and `feeds_summary`), so one
read-only call answers both halves: whether the channel *can* send, and whether its feed has anything usable
to send.

## Run it from Claude

The plugin registers WordPress *abilities* (WordPress 6.9+), exposed to MCP. A
Claude connected to the site's MCP server (the "Steam" connector) gets these tools,
all admin-only, none of them deleting anything:

| Tool | What it does |
|---|---|
| `steamgears-autopilot/status` | Switches, channels, setup state, why sending is paused, queue counts, pending drafts, recent errors, 30-day results |
| `steamgears-autopilot/channel-health` | Per channel: is a token stored, what is the next step, and which platform numbers actually landed in `sgap_events` (default 90 days). Telegram reports `no_metric` — the Bot API has no per-post numbers — and a channel that is `ready` but `empty` is a token that worked and returned nothing. Read-only; `tools/live_channels.py` uses it to verify P1.5 from outside the site |
| `steamgears-autopilot/list-drafts` | AI drafts (default: waiting for approval) with the page they promote |
| `steamgears-autopilot/review-draft` | `queue` (approve + queue), `approve` or `reject` one draft |
| `steamgears-autopilot/list-queue` | Queue items with status, attempts, errors, tracking links |
| `steamgears-autopilot/queue-post` | Queue a post Claude (or you) wrote. Takes `angle`, `content_type`, `format` and `campaign`, so a hand-written campaign post lands in the queue with the same dimensions the AI posts get (7.25) |
| `steamgears-autopilot/generate` | Run the AI writer now |
| `steamgears-autopilot/campaign-drafts` | One page + a written goal → one drafted post per channel, gated and **saved for review** (never queued or published). Takes `post_id`, `goal`, `channels` and an optional `campaign` (7.68) |
| `steamgears-autopilot/publish` | Run the Publer publisher now |
| `steamgears-autopilot/set-mode` | Autopilot / Full Auto on or off |
| `steamgears-autopilot/update` | Install the newest version from GitHub |
| `steamgears-autopilot/check-keys` | Test the Publer and AI keys (see below) |
| `steamgears-autopilot/check-publer` | Workspaces, accounts, channels without an account |
| `steamgears-autopilot/list-evergreen` | Evergreen pages with budget, expiry, last share and whether they are ready — read-only |
| `steamgears-autopilot/mark-evergreen` | Mark or unmark a post/product, with an optional budget and expiry date |
| `steamgears-autopilot/recycle-evergreen` | Write and queue the next variation for the due pages now |
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
| `steamgears-tools/mcp-status` | State of this plugin's own MCP credential: client ID and refresh token saved, expiry, verified tool count, why the last renewal failed |
| `steamgears-tools/mcp-refresh` | Renew the MCP access token now, verify it with `initialize` + `tools/list` and store the rotated refresh token |

The last two are the point: `list-abilities` shows what exists — WordPress core,
WooCommerce, Rank Math, AI Engine, this plugin — and `call-ability` runs it. New
plugins that register abilities become reachable without another code change.

### MCP access that renews itself (7.44)

The site's MCP credential is a Novamira OAuth pair: the access token lives 3600 seconds and the refresh token
is **rotated** on every renewal, so the old one is dead afterwards. 7.44 moves the renewal onto the site, so
access no longer depends on repository secrets or on a loop running in a sandbox:

- `sgap_mcp_refresh` runs every 30 minutes: it renews, proves the new token with `initialize` +
  `Mcp-Session-Id` + `tools/list`, and writes the rotated refresh token back before anything else.
- *Settings → MCP access* holds the public client ID and the refresh token. The token is a secret (autoload
  off) and is never shown again; an empty field keeps the stored value.
- A token that is issued but does not verify is **not** reported as renewed. The API's own reason
  (`invalid_grant`, `unsupported_grant_type`, an empty `tools/list`) is stored, shown by `mcp-status` and
  raised as a problem in the daily self-check — so the alarm comes from the site.
- A refresh token set in `wp-config.php` is refused with an explanation: the server rotates it, and a
  constant cannot be written back.

Approval is still one owner click: `python3 tools/mcp_oauth.py --start` prints the link, `--finish '<code>'`
saves the pair outside the repo, and the two values are pasted under *Settings → MCP access*.

### Shop tools that cannot underprice themselves (7.45, cost import 7.46)

The WooCommerce/Printful side is no longer a second plugin. Six tools live in the same
`steamgears-tools` category as everything else, so the site's MCP server exposes them without
another credential:

| Tool | What it does |
| --- | --- |
| `woo-catalog` | Products with price, cost, **where that cost came from**, the margin, and the lowest price your setting allows |
| `woo-propose` | Builds a proposal (title, description, price) and queues it — the shop is not touched |
| `woo-queue` | The proposals waiting for approval, with before and after |
| `woo-apply` | Writes one proposal; dry run unless `confirm` is true |
| `woo-discard` | Drops a proposal without touching the product |
| `woo-cost-import` | Writes each product's cost from its Printful sync variant — dry run unless `confirm` is true |

A cost is **never invented**. It is the product's `_sgap_cost` field, or the Printful sync
variant's own price (matched on the WooCommerce id or SKU, never on the product name); without
either, the call is refused and names both fixes. The floor is
`floor(cost × (1 + margin)) + 0.95`, raised by one when that would land *under* the margin, and
it is recomputed at apply time — so a cost that rose while a proposal was waiting raises the
price instead of quietly breaking the promise. The tools do not write the copy themselves: the MCP
client is an assistant already, and *Content Studio* writes product texts for the owner — so
`woo-propose` takes the text you send and carries the rest over unchanged.

`woo-cost-import` is how that cost field gets filled. It reads the Printful sync variants
(paginated, cached for six hours, `fresh` bypasses the cache) and writes each variant's **own
price** as the product's cost, matched on the WooCommerce id first and the SKU second — never on
the name. It is a dry run unless `confirm` is true, so the first call only shows the plan. A cost
you already typed wins: without `overwrite` it is reported as `kept`, and a second run reports
`unchanged` rather than churning the same number. A variant without its own price is listed as
`no_price` and left alone; a product Printful does not know is `unmatched` and left alone. A
variable product gets each variation's cost, and the parent gets the lowest of them. The source is
stored next to the number (`_sgap_cost_source`, e.g. *Printful import: sync variant 56 (matched on
Woo id)*), so `woo-catalog` can say where a cost came from instead of pretending it is the owner's.

*Settings → Shop & feeds* holds the Printful token (a secret, autoload off), the store id and
the minimum margin (45 % by default: a cost of 100 refuses anything under 145.95).

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
ROAS against the budget, attributed orders), **Evergreen** (mark a page, see its
budget and next share, recycle now), Etsy orders and profit, Full Auto (suppressed
combinations, reposts) and an XML product feed for Google Merchant Center and
Pinterest Catalogs (*Google Feed*, needs a feed secret and WooCommerce).

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

## Channel status tells a dead token from a missing one (7.61, tightened 7.64.4 and 7.64.8)

`GET /sgap/v1/channel-health` answers, per channel, which of six states it is in — `ready`,
`needs_refresh`, `missing_token`, `refused`, `needs_scope` or `no_class` — plus the sentence that says
what to do next. `no_class` means the channel's own class did not load: that is a code regression, not
an empty field, and the summary has its own `broken` counter for it so the screen never asks the owner
to paste a token to fix a missing file. Since
7.64.4 a stored token that the provider refuses is **`refused`, not `ready`**: Pinterest records the
401 with a date (`sgap_pinterest_refused`), the report shows `refused_at`, and the summary counts it on
its own line. Saving a new access token forgets the old verdict, and the next successful call clears
it. Before 7.64.4 the one channel the screen called ready answered 401 on every call.

**7.64.6** adds two things. The token is read from **Pinterest for WooCommerce** when that
integration is connected — through its own `get_access_token()`, because the value stored in
`pinterest_for_woocommerce_data` is encrypted and answers 401 as-is; the integration also renews
it, so it is preferred over a pasted token. And a **scope** answer is no longer reported as a
dead token: Pinterest's *insufficient permissions … Missing: ['boards:read']* is recorded as
`sgap_pinterest_scope` and shown as `needs_scope` with the missing scope named.

**7.64.7** closes the three holes that came with the token change. A 401 on the integration's token is
**never renewed with this plugin's own app credentials** — those would rotate a token that is not the one
being sent — and a scope answer is not retried at all. A refusal is stamped with the credential it is
about (source plus an 8-character sha256 prefix, never the token), so an integration that rotates its
token does not inherit last week's `refused`. And a scope verdict belongs to **the endpoint that was
denied**: `/user_account` answering 200 proves the token, not that `/pins` may be read, so only a
successful `/pins` clears the `boards:read` verdict.

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

## Duplicate guard across systems (7.40.1)

The queue has more than one writer: the AI generator, the Studio, the REST API, an MCP client and
Command Center can all add a post, and two of them can hand over the same one. Command Center syncs
every 30 minutes, so a dashboard that retries a hand-over would post twice.

`includes/duplicate.php` (`SGAP_Duplicate`) keys on the fingerprint the database already indexes —
`channel|destination_url|title`, sha256 — and refuses the second one with **409** and a message that
names the row it collided with:

> Already queued as #412 (queued, 2026-11-08 10:00). The same channel, destination and title went in
> in the last 30 days — change the title, or delete the old row if this repeat is deliberate.

- **Still queued always blocks**, however old the row is: it is about to go out.
- **A published post blocks for `dupe_days`** (Settings → Publishing, default **30**, `0` = the whole
  history), so an evergreen campaign can legitimately repeat a title next quarter.
- **Failed rows never block** — a post that gave up is the owner's to retry.
- `/snapshot` carries `fingerprint` on every queue row, so an outside dashboard can recognise its own
  hand-over instead of learning from a 409.

## Evergreen recycling (7.41)

Some pages keep earning long after their first post: a fitting guide, the goggles everyone searches for,
the corset explainer. Mark one as **evergreen** — in the editor sidebar, on the **Evergreen** page, or with
the `mark-evergreen` ability — and the plugin brings it back on a schedule with a **freshly written
caption**, not the same one again.

- **Settings → Evergreen** holds the four numbers: the switch, the days between two shares (default 60),
the default share budget per page (default 3) and how many pages may be recycled per day (default 1).
- A page can override the budget and set an **expiry date** in the editor; `0` shares and an empty date mean
"use the setting" and "never expires".
- Each share rotates to the next channel (and the next angle) and goes through the normal queue, so the
  quality gate, pre-flight, blackout calendar and duplicate guard all apply. A share is counted when the
  post reaches the queue; a page with a post still queued is skipped, not doubled.
- The variation prompt gets the page, the channel, the angle and the last captions for that page, and is
  told what not to repeat. The `sgap_evergreen_variation` filter replaces the AI call with a human editor or
  another model.
- An expired page or one that has used its budget stays marked but stops; the Evergreen page says which
  of the two it is, in words.

The daily cron runs the recycler after the normal generator (20 minutes past the hour), and the **Evergreen**
page has a *Recycle now* button plus `recycle-evergreen` for an MCP client. Nothing is published by the
recycler itself: it only queues, exactly like the AI writer.

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

The **Queue** page shows that on the row itself: a *Language* column names the language and marks the row
*original* or *translation*, and a *Source* column links a translation to the queued post it came from — one
click jumps to that row. A source that has since been deleted still shows its id instead of a guess.

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
- The **Command Center URL** setting (General) adds a "Log ind i Command Center" button to the dashboard. It opens
  the dashboard in a new tab. Leave it empty to hide the button. The plugin does not call Command Center; the link is only a shortcut.

## One niche, one shop (7.64)

*Steamgears → Shop → Niche shop* answers one question: **what is the shop about?** Type a niche
— `steampunk airship`, `cottagecore mushroom`, `celestial zodiac` — and the plugin turns it into a
shop skeleton you can publish from:

1. **A plan, before anything is written.** Ten archetypes (steampunk, dark academia, gothic,
   cottagecore, nautical, botanical, cyberpunk, celestial, retro, and a neutral fallback) decide the
   five collections, the motifs, the voice and the brand suggestions. The plan holds priced products
   across the print-on-demand families (tee, hoodie, art print, tote, mug, phone case), five page
   drafts, a menu and the schema types the shop needs. Same niche and settings give the same plan
   key, so a plan can be reviewed and shared before it is created.
2. **Drafts, never live products.** *Create drafts* writes `product_cat` terms, WooCommerce product
   drafts (price, SKU, `_sgap_niche` marker), page drafts and the menu — as a `wp_navigation` post on
   a block theme, as a nav menu elsewhere, with real links to the shop, the collection archives and
   the pages that exist. Nothing is published, no existing product is touched, and every id is
   stored under the run key. The menu is not pointed at the header automatically: swapping the live
   navigation is the owner's decision, but the navigation sits ready to be picked.
3. **Roll back in one click.** The Runs table keeps the ten newest runs. A rollback deletes the
   drafts, empty terms and menu *that run* created and keeps anything already published — the report
   says which is which. Running the same plan twice writes nothing new; the page says so with the
   date of the first run.
4. **Honest about the rest.** Every plan ends with the steps only the owner can take: Printful access
   (real costs and 5–8 mockups), publishing approval, currency and prices, payment methods,
   CVR/name/address/phone, the EU consent model, shipping zones, and confirming the shop name. The
   page copy marks each owner-only fact with `[EJER: …]` instead of inventing it.

Prices always clear the margin floor in *Settings → Shop & feeds* (`woo_min_margin`); the price is
the higher of `cost × 2.6` and `SGAP_MCP_Woo::floor_price()`, ending in `,95`. Costs are marked
`estimate` until Printful is connected, so no number in the plan pretends to be measured.

From Claude or any MCP client: `niche-plan` (read-only), `niche-apply`, `niche-runs` and
`niche-rollback`. Over REST: `POST /wp-json/sgap/v1/niche` (`niche`, `count`, `margin`, `apply`),
`POST /sgap/v1/niche/rollback` (`plan_key`, `dry`) and `GET /sgap/v1/niche/runs`. All of them need
`manage_options`.

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
3. `php plugins/tests/smoke.php` (1443 checks) and `diff -r` the zipped copy against the source. CI repeats
   both, plus `php -l` on PHP 8.0–8.5 and WordPress 6.4 / 7.1 / latest. The plugin is also exercised inside
   a real WordPress: `bash plugins/tests/integration.sh` (needs `php` with `mysqli`, `wp-cli`, `curl` and a
   reachable MySQL; 249 checks, including the four model rules, the Anthropic/custom-provider key checks and evergreen recycling) — it installs
   WordPress, activates the plugin, runs the in-process, admin-tool and automation suites, then hits `/go/`,
   the feeds, the REST routes and uninstall over HTTP. On WordPress 6.4 the same run passes 184 checks;
   the ability groups are skipped because the Abilities API starts at 6.9. The model dropdown is proven
   against the real catalogue with `curl -s -o /tmp/models.json https://api.aimlapi.com/v1/models` and
   `SGAP_LIVE_CATALOG=/tmp/models.json php plugins/tests/smoke.php` — every `<option>` must match an id AIMLAPI
   actually offers.
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
   `claude-opus-5-5`), **AIMLAPI** (Claude and 300+ models behind one key), **OpenRouter**
   (one key in front of hundreds of models from many providers — the address is fixed to
   `https://openrouter.ai/api/v1` and there is no default model, so pick one from the live list),
   or **Other OpenAI-compatible** with your own address, a key and a model name.
5. *General*: switch **Autopilot** on. Leave **Full auto** off at first and approve
   the drafts by hand until you like the tone.
6. Use **Generate posts now** / **Publish queue now** on the dashboard to try it
   without waiting for the cron.

**Key test.** Saving a Publer or AI key (or the workspace, channels, provider or
model) tests it straight away — OpenRouter with a single-token chat call to the chosen model,
because its `/models` answers without a key and would call every key valid; the result appears at the top of the page and on
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
  both into one digest after the self-check. Give a channel its own cap (`channel_caps`, e.g.
  `pinterest=2, facebook=3`) and it gets its own runway, so a quiet channel is named even when the total
  looks healthy. The cap is also a ceiling: once a channel has published or started sending its cap for the
  day, the publisher skips that channel and the rest of the queue still goes out.
- Every attempt is also written to the **Publish log**: channel, outcome, HTTP status, a response excerpt and
  the actor (cron, admin, REST, WP-CLI). The queue shows the current state; the log keeps the history the
  `error` column overwrites. Rows are kept for `audit_keep_days` (default 90, `0` = forever) and pruned by
  the daily maintenance job.
- Etsy is a manual import: there is no Etsy API client, and the five Etsy fields were removed from Settings
  in 7.33.4. Publishing goes through Publer (RSS feeds or API), and `/etsy/orders` takes the import.
