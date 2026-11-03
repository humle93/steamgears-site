# Steamgears site — optimisation roadmap

_Last audited: 2026-11-02 · production: WordPress **7.1.4**, PHP **8.5.12**, MySQL 8.4.11, WooCommerce 11.2.1, 33 active plugins · companion to [`plugins/steamgears-autopilot/ROADMAP.md`](../plugins/steamgears-autopilot/ROADMAP.md), which is imported in §5._

This is the roadmap for **steamgears.eu itself** — structure, content, store and hygiene. The plugin has its
own roadmap; this document tracks what the site needs, and §5 pulls every plugin item in with its site-side
dependency so there is one place to read the whole plan.

---

## 0. Short version

- **The 25/10 hub structure is still missing.** `/start-here/`, `/build/`, `/inspiration/`, `/shipping/` and
  `/returns/` all return **404** today, seven days after `mangler-og-plan-25-10.md` flagged them. This is the
  single biggest structural gap on the site.
- **AI Engine is now the agent's only way into the site.** Production runs **3.9.0, active**, and it serves
  the MCP endpoint `/wp-json/mcp/v1/http` (OAuth approved 2/11 2026, 206 tools). The earlier note — "3.8.9,
  inactive, MCP surface removed" — described 1/11 and is superseded (measured 2/11 2026 with
  `wp_list_plugins` and `tools/list`). The CVE history in `docs/library/sikkerhed-ai-engine-2026-11-01.md`
  still stands; deleting the plugin would remove agent access (see §2b).
- **The plugin's CI matrix is behind production.** The site runs PHP **8.5.12** and WP **7.1.4**; CI lints
  8.0–8.4 and tests WP 6.4 + latest. Imported item P3.3 is therefore not hygiene any more — it is the one
  change that verifies the code we actually ship to the runtime we actually run.
- **The store is behind the content pipeline:** 23 products in draft (including a complete 12-SKU Herbarium
  line) and 8 draft posts against 39 published products. See `docs/library/indholdsaudit-2026-11-01.md`.
- **The page cache should be re-measured.** The homepage and `/faq/` were serving `mwai-popup-container` and
  `ai-engine/themes/glass.css` while AI Engine was reported inactive. AI Engine is active again, so that
  markup may now be legitimate: check a cache-busted request before purging (S0.8), or the purge hides a
  real difference.
- **Effort: S0 2.6 d · S1 4.5 d · S2 6.0 d · S3 3.0 d = 16.1 developer-days**, before external approvals.

---

## 1. Method and what is verified

| What | How |
| --- | --- |
| Runtime and stack | `wp_get_site_status` / `wp_get_site_info` / `wp_get_plugins` over the MCP connection, 2026-11-01 |
| Content and store state | `wp_count_posts`, `wp_get_posts`, `wp_get_pages`, `wc_get_products`, `wc_get_product` — see the content audit |
| URL status | `curl` against production, one request per path, no auth, no cache busting |
| Page identity | `wp_get_page` for ids 35, 41, 42, 550, 713, 22 to confirm real slugs |
| Plugin CI | `.github/workflows/plugin-ci.yml` read in full |

**Not verified:** no Lighthouse/Core Web Vitals run, no GA4 data, no order or revenue data. GSC gives aggregate
clicks and impressions for 25–31/10, but **Crawl stats → By response has not been read** (S0.6), and the 24
published posts have not been crawled link by link. Statements below are limited to what the table above covers.

---

## 2. Stack snapshot (live)

| Item | Value |
| --- | --- |
| WordPress | 7.1.4 |
| PHP | 8.5.12 (memory 2048M, max execution 300 s, upload 1 GB) |
| MySQL | 8.4.11 |
| Theme | Extendable 2.1.9 |
| WooCommerce | 11.2.1 (39 published products) |
| Active plugins | 33 of 38 installed |
| Caching | W3 Total Cache 2.11.0 + `advanced-cache.php` + `object-cache.php` |
| SEO | Rank Math 1.0.280, Redirection 5.10.1 |
| Mail | WP Mail SMTP 4.10.3 (relevant for imported P0.1) |
| Agent access | **New MCP server** at `/wp-json/mcp/v1/http` (AI Engine) since 2/11: `401 rest_forbidden` without a credential, OAuth + PKCE published, dynamic client registration works. The connection waits for one owner click on the approval link — [`mcp-auth.md`](mcp-auth.md) |

### 2b. Deployment status (2026-11-02)

The repo ships two things to the site, and they are not always at the same version. The "live" column is read
from the site itself (`wp_get_plugins`), never inferred from this repo, so it can be re-measured in one call.

| What | In this repo | Live on steamgears.eu | Gap |
| --- | --- | --- | --- |
| Steamgears Autopilot | **7.25.0** (`plugins/steamgears-autopilot-v7.25.0.zip`, byte-identical to source) | **7.22.0**, active (measured 2/11 2026) | **One version** — 7.25.0 waits for the update; the plugin's own `steamgears-autopilot/update` ability can run it from MCP; nothing else is missing |
| MCP server | AI Engine 3.9.0 (site-side, not in this repo) | `/wp-json/mcp/v1/http`, OAuth approved 2/11 2026, 206 tools | **Read access only** — AI Engine's *Drafts Only* is on, so `queue-post` is refused |

**Measured 2/11 2026, after the owner approved the OAuth client:** `wp_list_plugins` returns
`{"Name": "Steamgears Autopilot", "Version": "7.22.0"}` and `steamgears-autopilot/status` reports
`version: 7.22.0` with `latest_on_github: 7.22.0`. The release that sat blocked on one click is therefore on the
site, and step 1 below passes. **7.25.0** is built, tested and zipped but not installed yet; it carries 7.24.0's
campaign name (the setting plus a per-post field) and 7.25.0's `angle`/`content_type`/`format` on `queue-post`.
Until it is installed the site keeps emitting the old hardcoded campaign value, and a hand-written post can only
enter the queue as `angle_a`/`post`.

**Measured 2/11 2026 with the new OAuth token:** `status` reports `version: 7.22.0`, `latest_on_github: 7.22.0`
and a queue of **35 queued / 10 published**, `publish_mode: rss`, Publer configured. `POST
/wp-json/sgap/v1/queue` with the same Bearer token answers **401** (`Sorry, you are not allowed to do that`):
the token authenticates AI Engine's MCP surface, not the plugin's own REST namespace. The plugin's 31 abilities
are exposed over MCP, but **AI Engine's *Drafts Only* mode refuses every write ability**: both
`steamgears-autopilot_update` and a deliberately invalid `queue-post` answer *"This site lets assistants work on
drafts only, so this was not done"*. Read-only abilities (`status`, `check-post`, `list-queue`, `list-plugins`)
answer normally. So the write path that `tools/load_campaign.py --post` now uses is correct but cannot be used
until the owner switches *Drafts Only* off — and the plugin cannot be updated from here either, for the same
reason, so 7.25.0 still needs the owner's *Plugins → Update* click.

**The quality gate is live but could only be half-proven here.** `status` reports `quality_gate: {mode: block,
banned_words: 0}`, and `check-post` refuses a post that links to a placeholder (`placeholder_link`, severity
`block`, `ok: false`) — the block path works. What could not be exercised is the *write* half: `queue-post` never
reaches the gate, because AI Engine answers *"This site lets assistants work on drafts only, so this was not
done"*. Two owner-side settings therefore stand between the gate and its real test:

- **AI Engine → MCP → Drafts Only** is on. With it on, the agent can read the site and nothing else, so
  `queue-post`, `generate` and `publish` are refused by AI Engine before the plugin sees them. **Re-measured
  2/11 2026 after the owner reported switching it off:** still refused, and `wp_get_option
  {"key":"mwai_options","raw":true}` shows `mcp_drafts_only: true` in the database itself (object cache
  bypassed), with `mcp_role: admin`, `mcp_approvals: off` and `mcp_dynamic_rest: true`. So the token is strong
  enough and nothing else is in the way — the switch simply is not saved yet. Recipe in
  [`mcp-auth.md`](mcp-auth.md#sådan-tjekkes-spærren-uden-at-skrive).

  **Re-measured 3/11 2026:** `mcp_drafts_only` is still `true` in `mwai_options`, and
  `steamgears-autopilot_update` still answers *"This site lets assistants work on drafts only, so this was not
  done. It would change the live site; ask the owner to do it."* Nothing is different from 2/11 — the switch has
  not been saved, so 7.25.0 stays one version behind and the campaign fields it carries stay unused. The gate was
  then read in AI Engine 3.9.0's own source: it refuses every tool outside its 10 draft-safe and 21 post tools,
  **including `wp_update_option`**, so no agent-side route can lift it — and an unsaved toggle looks saved until
  the page is reloaded. Recipe and verification in
  [`library/mcp-drafts-only-gaten-2026-11-03.md`](library/mcp-drafts-only-gaten-2026-11-03.md). The 7.25.0 zip is
  reachable at the URL the plugin's updater uses (HTTP 200, 161 KB), and `status` already reports
  `latest_on_github: 7.25.0`, so the owner's *Plugins → Update* click is enough.
- **Settings → General → Banned words is empty** (`banned_words: 0`), so the headline check of 7.22 cannot fire
  on anything until the owner adds at least one word. The other three checks (dead links, missing alt text,
  duplicate captions) work without it.

**Why the gap exists:** a plugin folder can only be replaced by WordPress itself. The MCP surface reads plugins
but cannot install or activate them, so the last step of every release belongs to the owner — *Plugins → Update*
or *Dashboard → Updates*, which the plugin's own self-updater feeds from the zip on `main`. Nothing has to be
uploaded anywhere for 7.22.0; the update is already offered.

**Verifying a release after the click** — in this order, because each step can hide the one before it:

1. `wp_get_plugins` → the `steamgears-autopilot` row reports the new version and `active: true`. This site's
   AI Engine does expose the plugin list (`wp_list_plugins`), measured 2/11 2026 — the Pro-only tool set is not
   what is installed here, so a missing tool means something else.
2. A feature *from the release* answers from the site, not just "no error": for 7.22 a post that fails a
   `block` check must be refused with the reason named. `check-post` proves the decision without writing; a
   `queue-post` refusal additionally proves the gate sits in the queue's own path, but needs *Drafts Only* off.
3. `steamgears-autopilot/status` → `quality_gate.mode` reports `block` (the default).

A release that fails step 1 is a failed upload, not a failed build: the zip is compared with `diff -r` locally
and in CI, so the difference is always on the WordPress side. The procedure itself, with the build commands,
is in [`plugins/steamgears-autopilot/README.md`](../plugins/steamgears-autopilot/README.md) §Updates.

---

## 3. Findings

### 3.1 URL status (2026-11-01)

| Path | Status | Note |
| --- | --- | --- |
| `/`, `/shop/`, `/faq/`, `/steampunk-costumes/`, `/steampunk-halloween/` | 200 | Core funnel intact |
| `/start-here/`, `/build/`, `/inspiration/`, `/shipping/`, `/returns/` | **404** | Planned hubs, never built |
| `/sitemap_index.xml`, `/robots.txt` | 200 | — |
| `/about/`, `/journal/` | 200 | Real slugs; `/workshop-journal/` is 404 |
| `/the-clockwork-herbarium-short-story/` | 200 | Published story whose products are drafts |

### 3.2 Homepage

Title: "Steampunk Costumes & Gear: Goggles, Corsets, Posters | Steamgears". H1: "Steampunk Gear & Costumes –
Shop the Looks" (page 42 is the front page). The H1 and title agree with each other but neither carries the
"Build Your Own Steampunk Story" framing from the workshop plan — that mismatch is now a deliberate choice to
make, not a bug.

### 3.3 Plugins worth attention

- **AI Engine 3.9.0, active** — it *is* the MCP server: `/wp-json/mcp/v1/http` answers `401` with
  `WWW-Authenticate: Bearer realm="MCP"` until OAuth (working since 2/11 2026), and `/wp-json/mwai/v1/*`
  answers `401 rest_forbidden`, not `404`. The chatbot is off. `changelog.txt` still reports an old number, so
  it is not a reliable way to read the installed version — use `wp_list_plugins`.
- **Two Health Check entries** active ("Health Check & Troubleshooting" 1.7.1 and "Health Check
  Troubleshooting Mode" 1.9.2) — one is redundant.
- **Coming Soon / Maintenance 6.20.10 active** — confirm it never serves a holding page to crawlers.
- **TikTok 1.4.3 + Smash Balloon TikTok Feed 1.9.0** — overlapping scope.
- **WP Pusher 3.0.20 active** — a deploy path outside the GitHub-Actions + zip flow this repo documents.
- **robots.txt contains two `User-agent: *` groups**; source not confirmed (host mu-plugin
  `managed-wordpress-robots.php` vs Rank Math vs W3TC).

### 3.4 Store and content state

From `docs/library/indholdsaudit-2026-11-01.md`: 24 published posts / 8 drafts, 21 published pages, 39
published products / 23 drafts / 43 trashed, 245 media files. All published products are `instock`. Draft
post 750 duplicates live post 699 and has waited since 25/10.

---

## 4. Roadmap

Owner-facing PDF of this section, with the manual click list, time-to-100 % estimates and every roadmap in one document: [`reports/roadmap-og-dokumentation-2026-11-03.pdf`](../reports/roadmap-og-dokumentation-2026-11-03.pdf). Generated by `tools/roadmap_pdf.py` — the older five-page `docs/pdf/manual-roadmap-2026-11-02.pdf` was a duplicate of it and is removed.


Effort is developer-days for one person who knows this site. Nothing here requires a code release except
S0.3, which is a repo change.

### S0 — broken or urgent (2.6 d)

| # | Item | Why | Effort |
| --- | --- | --- | --- |
| S0.1 | ✅ **Done 1/11** — AI Engine case closed in `sikkerhed-ai-engine-2026-11-01.md` (new §0) and in `README.md`; confirmed 3.8.9 at the time — **superseded 2/11 2026: 3.9.0, active, serving MCP over OAuth** | A security doc that describes an old state trains people to ignore it | 0.25 d |
| S0.2 | Resolve the two `User-agent: *` groups in robots.txt and record which plugin owns the file | Conflicting robots directives can silently deindex paths | 0.5 d |
| S0.3 | ✅ **Done 1/11** — CI matrix now lints PHP 8.0–8.5 and tests WP 6.4 / 7.1 / latest (imported P3.3) | Production runs 8.5.12 / 7.1.4, CI stopped at 8.4 | 0.25 d |
| S0.4 | Stop the 404 bleed: 301 map for `/start-here/`, `/build/`, `/inspiration/`, `/shipping/`, `/returns/` to the closest live page until S2.1 lands them. ⚠ **Blocked 1/11** — targets chosen (`/steampunk-cosplay-for-beginners/`, `/how-to-make-a-steampunk-costume/`, `/steampunk-cosplay-gallery/`, `/delivery/`, `/refund_returns/`), but Redirection has **no groups** (`wp_redirection_groups` is empty → `Red_Item::create` fails with "Invalid group") and the MCP surface cannot create one. **Unblock: create one group in Tools → Redirection (group dropdown → "Add Group")**, or add the five rows by hand. Note: merely opening the screen does *not* help — Redirection 5.10.1 is active but its installer only seeds groups on activation/upgrade, so `wp_redirection_groups` stayed empty and `group_id` 1/2 are rejected too. Once a group exists the five redirects take one call | Any nav, pin or ad pointing there loses the visitor entirely | 0.5 d |
| S0.5 | Remove Health Check **and its own drop-in** (`health-check-troubleshooting-mode.php`). **Corrected 2/11:** the second entry is not a redundant second plugin — it is the drop-in Health Check installs itself. Deleting the plugin removes both | A troubleshooting tool left active on a live shop is failure surface with no benefit | 0.25 d |
| S0.6 | ⚠ **Omskrevet 2/11 — præmissen var forkert.** 455 «Security Incident Detected» er Simplys **spoof-værn**, ikke en Google-blokering: samme URL svarer **200** til neutral curl-UA og **455** til Googlebot-/bingbot-UA fra denne maskines datacenter-IP, mens Google beviseligt indekserer sitet (`site:` 2/11 viser forside, guides med dato 16/10 og de kommercielle sider) og GSC viser sitemap hentet **31/10** (25–31/10: 3 klik, ~110 visninger). Simply hvidlister allerede Googlebot, bingbot, OpenAI og Anthropic **på IP**, og en ny tjeneste kræver en offentlig IP-liste, som denne container ikke har — »bed Simply hvidliste søgemaskine-bots« var derfor aldrig rettelsen, og at fjerne WAF'en ville være en sikkerhedsregression. **Tilbage:** læs **GSC → Crawl stats → By response** for de sidste 7 dage og bekræft at **Googlebot har 0 × 455/5xx**; kun hvis det ikke holder, åbnes en sag hos Simply med webserver-loggen fra Control Panel ([waf-diagnose §6](library/waf-diagnose-2026-11-02.md)) | Indekseringen er forudsætningen for hvert andet SEO-punkt; det eneste der stadig ikke er målt, er Googles eget svar pr. crawl | 0.25 d |
| S0.7 | ✅ **Done 2/11** — all 121 sitemap URLs crawled (post 31, page 24, product 40, product_cat 20, category 5), **121 × 200**, no 404 or redirect in the sitemap | Cheap sanity check on the entry point crawlers use | 0.5 d |
| S0.8 | ✅ **Done 1/11** — W3TC purged (`{"purged": true, "scope": "all"}`); homepage and `/faq/` verified free of `mwai-`/`ai-engine/` markup, both still 200 | Cached HTML referenced a plugin that is not running | 0.1 d |
| **S0 total** | | | **2.6 d** |

### S1 — store and content that earns (4.5 d)

| # | Item | Why | Effort |
| --- | --- | --- | --- |
| S1.1 | Launch the Herbarium line: publish the 12 SKUs (953–956, 965–968, 969–972) and link them from story 973 | The story is live and sells nothing; the merch is finished and invisible | 0.5 d |
| S1.2 | Decide and execute on Atelier jewellery (940–942) and DIY/digital (765–767) | Six finished products with SKUs and prices, all drafts | 0.5 d |
| S1.3 | ✅ **Done 1/11** — 897/898/789/988/989 published, 750 merged into 699 (five-piece rule + reading note) and 938 merged into 974 (materials, dieselpunk conversion, trenchpunk hybrid); both merged drafts trashed | 8 drafts carrying duplicate intent against live posts | 1.0 d |
| S1.4 | Empty the product trash (43) and page trash (3) after a link check | Trash inflates admin, sitemaps and any naive count | 0.5 d |
| S1.5 | Product-link coverage: every published guide links at least one product, through the `/go/<id>/` tracking path | Traffic without a path to the shop is the leak this site can least afford | 1.0 d |
| S1.6 | Two lead-magnet pages (801, 764) get a real capture path and a thank-you state | They exist but the funnel behind them is unverified | 1.0 d |
| **S1 total** | | | **4.5 d** |

### S2 — structure and SEO (6.0 d)

| # | Item | Why | Effort |
| --- | --- | --- | --- |
| S2.1 | Build the hubs for real: `/start-here/` + 5 children, `/build/` + 6 children, `/inspiration/` — **partial 2/11:** both hubs (1183, 1185) and five children (`/start-here/glossary/`, `/characters/`, `/occasions/`, `/build/tools-and-materials/`, `/build/weathering/`) are live with 0 bad links. The other six children would duplicate existing posts, so they are deliberately not built; `/inspiration/` is covered by the gallery and should 301 instead. 1,6 of 3,0 d | The plan's spine; 404 today | 3.0 d |
| S2.2 | `/shipping/` and `/returns/` pages (or 301 to the existing policy pages) | Commercial trust pages, currently absent | 0.5 d |
| S2.3 | Navigation restructure: Start Here · Build · Shop · Inspiration · Info | Nothing can link to hubs that do not exist | 0.5 d |
| S2.4 | Homepage H1/title decision (shop framing vs workshop framing) and apply consistently | The two currently disagree with the plan, agree with each other | 0.25 d |
| S2.5 | Rank Math meta pass over the 24 published posts: title length, description, focus keyword, OG image | 24 posts, no evidence of a per-post pass | 1.0 d |
| S2.6 | Internal linking: stories and guides → relevant products and hubs | Fixes orphan-ish pages without new content | 0.75 d |
| **S2 total** | | | **6.0 d** |

### S3 — hygiene (3.0 d)

| # | Item | Why | Effort |
| --- | --- | --- | --- |
| S3.1 | Delete the 3 private design pages (692, 870, 871) once the theme direction is locked | Staging leftovers with public URLs | 0.25 d |
| S3.2 | Remove draft page 72 (duplicate Privacy Policy) | Duplicate legal page, one accidental publish from a mess | 0.1 d |
| S3.3 | Media pass: 245 files, Imagify/webp coverage, alt text on the images the guides use | Weight and accessibility in one sweep | 0.75 d |
| S3.4 | Plugin rationalisation: 33 active → remove the duplicate Health Check, decide TikTok vs Smash Balloon, review WP Pusher against the documented deploy flow. **Audited 2/11** in `library/plugin-audit-2026-11-02.md`: 36 plugins + 4 drop-ins, 33 active, **5 behind** on WordPress.org (not 16 — that number came from premium channels and cannot be reproduced), **5 overlap pairs** (HTTPS ×2, snippets ×2, TikTok ×2, Google tags ×2, Health Check + its own drop-in), 3 inactive plugins to decide on. Biggest find: **Royal Links** (installed, inactive) is link cloaking and can deliver the `/go/<id>/` path that S1.5 needs — the blocked Redirection group may not be needed at all. Click list is 1,75 h total, not 1,0 d. **0,35 d done — removals still need the owner** | Every plugin is update surface and failure surface | 1.0 d |
| S3.5 | Weekly maintenance log entries continue in `docs/library/vedligehold-ugentlig-log.md` with the numbers from this roadmap's checks | The log only helps if it is fed | 0.5 d |
| **S3 total** | | | **3.0 d** |

### S4 — butik og salgsklar (5.0 d)

Full evidence in [library/butiksaudit-2026-11-01.md](library/butiksaudit-2026-11-01.md). This band is what stands between
"a store that exists" and "a store that can take money from strangers": both orders ever placed were a zero-value
completed order and one refund. S4.1–S4.5 are blocking before any ad spend.

| # | Item | Why | Effort |
| --- | --- | --- | --- |
| S4.1 | **Complianz to EU opt-in** — currently `regions: [us]`, `consent_types: [optout]`, so a US opt-out banner sits in front of EU customers and non-essential cookies can fire first; only US legal docs are generated | 2026 Danish enforcement priority; wrong consent model = unusable consent | 0.25 d |
| S4.2 | **Legal identity** — CVR, registered name, address and **phone** do not appear anywhere; Complianz imprint is `mode: none`; contact is a gmail address | Forbrugerombudsmanden requires them one click from the front page | 0.25 d |
| S4.3 | **Currency and price model** — store runs in **USD**; switch to EUR or DKK *and* recalculate all 39 prices (changing the currency code alone would turn $21.90 into 21.90 kr) | EU shoppers cannot price-compare a dollar store; VAT display becomes meaningless | 0.5 d |
| S4.4 | **Withdrawal function** — page with procedure + EU model form shipped 1/11 as `/right-of-withdrawal/`; the one-click digital function from 19/6 2026 still needs a form plugin (WPForms Lite cannot create forms via MCP) | Statutory since 19 June 2026; without it the 14-day clock may not start | 0.45 d (0.15 d done) |
| S4.5 | **Payment methods** — Stripe only; add MobilePay, PayPal, optionally Klarna | Payment apps overtook cards in Danish e-commerce in 2024 and shoppers abandon stores that lack them | 0.4 d + acquirer wait |
| S4.6 | **Shipping** — verify Printify live rates in a real cart, exclude cart/checkout from W3TC, set DK/EU/UK/RoW zones and tee/hoodie/poster/mug classes, free-shipping threshold above blended AOV | Mixed carts bill two first-item rates; cached checkout silently loses rates | 0.4 d |
| S4.7 | **Product images** — all 39 products have fewer than 3 images (max 2) | Printify mockups alone underperform; 5–8 images is the POD norm | 1.0 d (top 10 first) |
| S4.8 | **Size charts** on every apparel product, in cm and inches, plus the flat-measurement tip | Wrong size is the single biggest apparel-return driver, and POD production is non-refundable | 0.5 d |
| S4.9 | **Product copy** — fit note, production time, delivery window and the GPSR responsible-operator block on each listing | GPSR has required this since 13/12 2024; none of it is present | 0.5 d |
| S4.10 | **Reviews** — 0 reviews site-wide and no collection tool; pick a platform, automate the request, show how reviews are verified | Cheapest trust available to an unknown store, and the Omnibus rules are enforced | 0.4 d |
| S4.11 | **`/shop/` performance** — measured 5.8 s / 470 KB against 2.1 s on the homepage | 17% of Baymard abandonments are slow or failing pages; shop is the expensive page | 0.4 d |
| S4.12 | **Order confirmation email** carries the withdrawal information itself, not just a link (durable-medium requirement) | A link on the website does not satisfy the duty | 0.2 d |
| **S4 total** | | | **5.0 d** |

---

## 5. Imported from `plugins/steamgears-autopilot/ROADMAP.md`

The plugin roadmap is the authority for plugin work; this table is the site-side view of it, so a reader of
this document knows what is blocked, by what, and who fixes it. Effort stays as estimated there.

| Plugin item | Effort | Site-side dependency | Status on the site |
| --- | --- | --- | --- |
| P0.1 Failure alerting | 0.5 d | None — WP Mail SMTP 4.10.3 is installed and active | **Unblocked** |
| P0.2 "Running dry" alert | 0.5 d | None | Unblocked |
| P0.3 Retry with backoff | 1.0 d | None | Unblocked |
| P0.4 Cron honesty | 0.5 d | **W3TC + `advanced-cache.php` are active**, so WP-Cron is at real risk of firing late; needs the host's cron or Action Scheduler | ⚠ Raise priority |
| P0.5 Publish audit log | 1.0 d | None | Unblocked |
| P0.6 Cross-system duplicate guard | 0.5 d | Command Center side | Blocked on CC |
| P1.1 Pre-flight channel validation | 1.0 d | None | **Done 2/11 in 7.20.0** — `includes/preflight.php` refuses an invalid post before the first Publer call (Instagram JPEG/size/aspect, Pinterest board, caption and hashtag ceilings) and repairs what is mechanical |
| P1.3 Blackout calendar | 0.5 d | None | **Done 2/11 in 7.21.0** — `includes/blackout.php` holder poster ude i angivne datointervaller og flytter dem til næste frie dag samme klokkeslæt |
| P1.4 Evergreen recycling | 2.5 d | P0.6 | Blocked on P0.6 |
| P1.5 Platform analytics ingestion | 4.0 d | **Pinterest API token still 401** (see `mangler-og-plan-25-10.md`) + Meta App Review | Blocked externally |
| P2.1–P2.3 Bluesky / Mastodon / Telegram | 2.0 d | Telegram needs a BotFather token; WP Mail SMTP already covers P0.1 | Needs tokens |
| P2.4 Etsy import or hide the settings | 2.5 d / 0.25 d | Owner decision (§6.2) | **Awaiting decision** |
| P3.3 PHP 8.5 + WP 7.1 in CI | 0.25 d | None — and production already runs both | **Done as S0.3 in this change** |
| P1.6 Quality gate before queueing | 1.0 d | None | **Done 2/11 in 7.22.0 — live** (§2b) — `includes/quality.php` stopper bandeord, døde links, manglende alt-tekst og næsten-dubletter, før posten når køen |
| P1.7 Whole-site SEO/GEO/AEO audit | 1.0 d | None | **Done 2/11 in 7.19.0** — `includes/site-seo.php` scans every published URL in one pass and fixes the weakest meta |
| P1.8 `utm_campaign` som parameter | 0.25 d | None | **Done 2/11 in 7.24.0 — venter på Update-klikket** (§2b) — kampagnenavnet er en indstilling (`utm_campaign`) plus et felt pr. post i `/queue` og `queue-post`, så en navngivet kampagne kan måles i GA4 ved siden af evergreen-strømmen |

**Net effect of importing:** the plugin's P0 block (4.0 d) is not blocked by anything on the site, except
P0.4, which this audit shows is *more* urgent than the plugin document assumed because page caching is
confirmed active. P1.5 remains the one genuinely external blocker.

---

## 6. Decisions needed from the owner

1. **Hubs** — build `/start-here/`, `/build/`, `/inspiration/` as planned (S2.1, 4.0 d), or formally drop them
   and rewrite navigation around the existing pages (0.5 d)? Everything in S2 depends on this.
2. **Etsy** — build the OAuth import or hide the five settings (plugin P2.4).
3. **Herbarium launch** — publish the 12 drafts and link from story 973, or hold the line until a campaign date.
4. **Draft 750** — merge into live 699, or rewrite as a distinct angle.
5. **Homepage framing** — keep "Steampunk Gear & Costumes – Shop the Looks" or move to the workshop-plan H1.
6. **Cron** — accept the Action Scheduler migration (2.0 d, plugin optional item) or configure host cron for
   `wp-cron.php` (0.25 d on Simply).

---

## 7. Ordering

```
S0.1 S0.3 S0.5  (independent, no risk)  ──► same day
S0.2            (config, needs a decision on robots ownership)
S0.6 S0.7       ──► GSC-læsning og genmåling, ingen ændring på sitet
S0.4            ──► immediately, buys time until S2.1
S1.1 S1.2       ──► after decision 3; revenue first
S1.3 S1.4       ──► after decision 4
S2.1 S2.2 S2.3  ──► after decision 1
plugin P0.1–P0.3, P0.5 ──► parallel, different repo, nothing blocks them
```

---

## 8. Change log for this document

| Date | Change |
|---|---|
| 2026-11-03 (plugin P2.5 — multi-sprog) | **Én kilde-post giver nu én caption pr. sprog.** 7.31.0 tilføjer `includes/locales.php` (`SGAP_Locales`): *Source language* (standard `en`), *Languages* (`da,sv,de` — højst seks, aldrig kildesproget, skrald og dubletter fjernes ved gem) og et opslag i **WPML eller Polylang** (`pll_get_post()` først, derefter WPML's `wpml_object_id`-filter), så varianten linker til den oversatte side i stedet for den engelske. URL'en gættes aldrig: findes der ingen oversættelse, laves der ingen variant — det rapporteres. Selve captionen oversættes af den konfigurerede model gennem `SGAP_AI::json_call()`, altså WordPress' HTTP-API, så testene kan stubbe transporten; filteret `sgap_locale_translator` kan sætte DeepL eller et menneske i stedet. `SGAP_Queue::add()` lægger varianterne i køen som helt almindelige rækker — samme kanal, vinkel, format, billede, quality gate og blackout — og de bærer `locale` og `source_id` (to nye kolonner via `dbDelta`), så en variant aldrig avler nye varianter. **Intet forsvinder i stilhed:** manglende oversat side, manglende AI-nøgle, API-fejl, ikke-JSON og tom caption kommer tilbage i `locale_errors`, mens kildeposten bliver i køen. 35 nye smoke-checks (364 → 399 PASS, 0 FAIL) dækker normalisering af listen, opslaget af den oversatte side, én variant pr. sprog, alle fejlveje, AI-kaldet uden nøgle og med afvist/ugyldigt svar, kø-integrationen og reglen om ingen videre ekspansion. Plugin-projektet 45,4 % → **55,6 %** (13,55 af 24,35 d; P2 2,0 → 4,5 af 7,0 d). Etsy-beslutningen (P2.4) er stadig urørt og venter på ejeren. |
| 2026-11-03 (plugin P2.2 + P2.3 — Mastodon og Telegram) | **To kanaler mere uden om Publer.** 7.30.0 tilføjer `includes/mastodon.php` (`SGAP_Mastodon`) og `includes/telegram.php` (`SGAP_Telegram`), begge gennem `wp_remote_post` og begge routet i `SGAP_Publer::tick()` med samme backoff, alert og publish-log-række som Bluesky. Mastodon er ét kald til `POST {instance}/api/v1/statuses` med Bearer-token og en `Idempotency-Key` pr. kø-post, så et gentaget tick ikke poster dobbelt; instansen er en del af legitimationsoplysningerne, fordi et Mastodon-token kun virker på serveren der udstedte det, og API'ets egen fejltekst bliver beskeden i køen og loggen. Telegram er ét kald til `bot<token>/sendMessage`, og her er fælden at API'et svarer **HTTP 200 med `ok: false`** når det afviser — det tjekkes før alt andet, så en afvisning aldrig ser ud som et publiceret opslag. Grænserne er 500 tegn for Mastodon (en `mastodon_limit`-indstilling, for instanser kan tillade mere) og 4096 for Telegram; `preflight.php`'s Telegram-loft er rettet fra 1024 til 4096, så en gyldig caption ikke længere klippes uden grund. Fælles teksthåndtering ligger i `includes/text.php` (`SGAP_Text`), så reglen står ét sted. 28 nye smoke-checks (336 → 364, 0 FAIL) dækker succes, manglende legitimationsoplysninger, afvist token, API-afvisning, netværksfejl, `ok:false`, ikke-JSON, trimning og routing pr. kanal. Plugin-projektet 41,3 % → **45,4 %** (11,05 af 24,35 d; P2 1,0 → 2,0 af 7,0 d). Etsy-beslutningen (P2.4) og multi-sprog (P2.5) er urørt og venter på ejeren. |
| 2026-11-03 (plugin P2.1 — Bluesky) | **Bluesky er nu en rigtig kanal i pluginnet, uden Publer.** 7.29.0 tilføjer `includes/bluesky.php` (`SGAP_Bluesky`): `com.atproto.server.createSession` (handle + app password) og `com.atproto.repo.createRecord`, begge gennem WordPress' HTTP-API — ingen SDK, ingen Composer-afhængighed, og derfor stubbare i tests. Kanalen tændes ved at sætte `bluesky` i kanallisten og udfylde handle + app password under Settings → Publer; app-passwordet er et secret som Publer-nøglen, så et tomt felt beholder den gemte værdi, og fjerner man `bluesky` fra listen eller tømmer passwordet, går kanalen tilbage til Publer. `SGAP_Publer::tick()` sender den gennem **samme** løkke: en fejl får samme backoff, samme alert og samme publish-log-række, og en succes markeres `published` med det samme med AT-URI'en som eksternt id, fordi der ikke er noget Publer-job at følge op på. Sessionen caches i 90 minutter, og et 401 fornyer den én gang og prøver posten igen. Teksten respekterer Blueskys **300 grafer** (`\X`, så en emoji med modifier ikke deles), tracking-linket reserveres før kroppen trimmes og sendes som et richtext-facet, så det er klikbart, og hashtags bliver tag-facets. Alle fejl er `WP_Error` med årsagen — manglende legitimationsoplysninger, `invalid_grant` ved afvist login, API'ets egen besked ved afvist post og transportfejl — aldrig en tavs no-op. 28 nye smoke-checks med stubpet `wp_remote_post` (308 → 336 PASS, 0 FAIL). Plugin-projektet 37,2 % → **41,3 %** (10,05 af 24,35 d; P2 0 → 1,0 af 7,0 d). |
| 2026-11-03 (plugin P3.2 + P3.5) | **De to REST-ruter uden assertions er dækket, og uninstall er tjekket mod DDL'en.** Plugin 7.28.0: `SGAP_REST::merchant_feed()` returnerer XML'en som et `WP_HTTP_Response` i stedet for at `echo` og kalde `exit` — før kunne feedet overhovedet ikke testes, og `header()` faldt efter output var begyndt. 12 nye smoke-checks dækker hemmeligheds-gaten (mangler secret, forkert nøgle), 501 uden WooCommerce, Google-namespacet, SKU der falder tilbage til produkt-id, pris med butikkens valuta, XML-escapede titler, strippet HTML, spring over uden pris/billede, begge lagerstatusser og content-typen. Fem checks dækker `/reposts` (liste, 400 uden id'er, nummerering, 422 ved loftet, insert) og fem dækker uninstall: testen udtrækker hver tabel `install()` opretter, kræver at `uninstall.php` nævner alle ni, og kører filen med stubbet `$wpdb` og kræver en `DROP TABLE IF EXISTS` for hver. **Fund undervejs:** `* Version:` stod på samme linje som `* Description:` i plugin-headeren, så WordPress ikke kunne læse versionen — rettet til to linjer. Suiten er nu **308 PASS, 0 FAIL** (README's »229 checks« var forældet). Zip 7.28.0 er genbygget og `diff -r`-verificeret mod kilden. Plugin-projektet 34,1 % → **37,2 %** (9,05 af 24,35 d; P3 0,25 → 1,0 af 2,0 d). |
| 2026-11-03 (Pinterest-token fornyes selv) | **Den natlige niche-scanning fornyer nu sit eget Pinterest-token.** Access-tokenet holder 30 dage, og fornyelse var indtil nu en manuel opgave hver måned. `tools/niche_scanner.py` har fået `refresh_pinterest_token()` (POST `/v5/oauth/token` med Basic `client_id:client_secret`, `grant_type=refresh_token`) og `pinterest_refresher()`, som kun aktiveres når både klient-id, klient-secret, refresh-token og en `--token-out`-fil er givet — uden det sidste returnerer den `None` med vilje, for Pinterest roterer refresh-tokenet og bruger det gamle op, så en fornyelse uden gemning ville efterlade næste nat uden adgang. Det nye par skrives atomisk med mode 600 **før** access-tokenet bruges. Kun 401 fornyes og kaldet gentages én gang: en 403 er manglende adgang, ikke et udløbet token, og en 400 fra token-endpointet er et dødt refresh-token. Mangler access-tokenet helt, henter scanneren et med refresh-tokenet. Workflowet har fået et trin der lægger begge tokens tilbage i secrets med `gh secret set` (maskeret med `::add-mask::`, filen ligger i `RUNNER_TEMP` og slettes) og kører med `always()`, så et roteret token ikke mistes når scanningen fejler bagefter; mangler PAT'en `PINTEREST_SECRETS_TOKEN`, fejler jobbet rødt med en annotation. 48 tests (var 40), og secret-trinnet er afprøvet mod en falsk `gh` i tre tilfælde. Dertil `docs/library/pinterest-token-fornyelse-2026-11-03.md` + PDF. Ingen procenter flyttet: værktøj uden estimatlinje i de fem projekter. |
| 2026-11-03 (natlig niche-scanning) | **Tre-dages-gaten passer nu sig selv.** `.github/workflows/niche-scan.yml` scanner kl. 02:30 dansk tid (en halv time efter den natlige push) de 11 søgeord fra `tools/niche_seeds.txt` mod Pinterest Trends, skriver `reports/niche-scan-<dato>.md`/`.json` og committer måledagen i `reports/niche-scan-history.json` med tre genforsøg ved afvist push. For at gaten ikke skulle kunne snyde sig selv, blev to fejl rettet i `tools/niche_scanner.py`: en kørsel uden data skrev dagens rapport og overskrev dermed den sidste gode, og historikken blev skrevet med alle seeds via `… or terms`, så tre forgæves nætter ville have givet «Anbefalet» uden en måling. Nu er exit-koden 2, intet skrives, og kun målte søgeord får en dato. Mangler `PINTEREST_ACCESS_TOKEN`, springes scanningen over med en `::notice` — er den udløbet (401), fejler jobbet rødt. Dertil: `docs/library/niche-scan-ci-2026-11-03.md` + PDF, 40 tests (var 38), og AGENTS §4's automatiseringsliste er opdateret. Ingen procenter flyttet: værktøj uden estimatlinje i de fem projekter. |
| 2026-11-03 (niche-scanneren færdig) | De tre §7-krav fra [library/niche-scan-procedure-2026-11-02.md](library/niche-scan-procedure-2026-11-02.md) er nu kodet i `tools/niche_scanner.py`: `momentum` læses fra Pinterests eget `pct_growth_mom` i stedet for at blive udregnet af den min-max-normaliserede serie; rapporten viser `Lavsæson` (gennemsnit af nederste kvartil) og `Top` (måned); nuller tælles som censur, holdes ude af gennemsnittene, og en serie der kun består af nuller rangeres ikke. Dertil en tre-dages-gate: `reports/niche-scan-history.json` husker hvilke dage et søgeord er målt, og `Status` skriver først «Anbefalet» ved 3 måledage. TikTok-eksporten har ingen serie og bliver derfor hverken kaldt censureret eller tildelt en lavsæson — det skel var den første version af koden selv ude om at sløjfe. 38 tests, alle offline. Undervejs blev `tools/md_to_pdf.py` rettet: en tabelkolonne med et enkelt-tegns-hoved (`#`) kunne blive smallere end et tegn og kaste `FPDFException`. Ingen procenter flyttet: værktøjet har ingen estimatlinje i de fem projekter. |
| 2026-11-02 (round 18 — alle værn) | Alle repoets værn er samlet i **`tools/natlige-vaern.sh`**: `php -l` på alle plugin-filer, `projektstatus.py --selftest`, `projektstatus.py --check`, `load_campaign.py --selftest`, `plugins/tests/smoke.php` (229 checks), `test_mcp_rest.py`, `test_mcp_oauth.py` og `test_niche_scanner.py` — otte værn, der alle kører offline. Dommen falder på **exit-koden**, ikke på teksten, fordi projektstatus' selftest med vilje skriver en `[FEJL]`-linje fra et negativt fixture og afslutter 0. Fejler noget, skrives en tabel plus de fejlende linjer til job-summaryen, hvert fejlende værn får sin egen `::error`-annotation, og scriptet afslutter 1. Den natlige kørsel kalder nu bare scriptet, så listen står ét sted og kan køres lokalt før et push. Begge veje er afprøvet: 8/8 grønne → exit 0, og en konstrueret `Parse error` i en midlertidig fil → exit 1, annotation og `Parse error`-linjen i summaryen. Ingen procenter flyttet: fortsat repo-infrastruktur uden estimatlinje. |
| 2026-11-02 (round 17 — natlig smoke) | Den natlige kørsel kører nu også **plugin-smoke**: PHP 8.5 via `shivammathur/setup-php`, derefter `php plugins/tests/smoke.php` — samme 229 checks som CI's lint+smoke-job. En rød test giver `::error`-annotation, skriver de fejlende `FAIL`-linjer i job-summaryen og fejler jobbet. Testen ligger **efter** commit/push med vilje: den natlige push skal ikke holdes tilbage af en testfejl, men en rød test skal kunne ses i Actions og i mailen. Både den grønne og den røde vej er afprøvet lokalt (229/0 → exit 0; en konstrueret FAIL → annotation + exit 1). Ingen procenter flyttet: stadig repo-infrastruktur uden estimatlinje. |
| 2026-11-02 (round 16 — natlig push) | **Ny regel: alt arbejde skal ende på `main`, og senest af en natlig kørsel kl. 02 dansk tid.** `.github/workflows/natlig-push.yml` kører værnene (`--selftest`, `--check`), opdaterer daglig status + brief, committer **alt** i træet (`git add -A`) og pusher til `main` med tre genforsøg og `pull --rebase --autostash` ved afvist push. Fordi GitHub cron er UTC og dansk tid skifter mellem UTC+1 og UTC+2, ligger kørslen på begge de timer der kan være kl. 02 lokalt og springer selv over hvis den lokale time ikke er 2 — ingen sommertids-rettelse nødvendig. **Grænsen står i AGENTS.md §4:** kørslen kan kun pushe det der ligger i repoet, så ucommittet arbejde i en sandkasse ser den ikke; push i samme tur er stadig reglen, nattens kørsel er sikkerhedsnettet. Ingen procenter flyttet: reglen er repo-infrastruktur og har ingen estimatlinje i de fem projekter. |
| 2026-11-03 (round 27 — /go-ruten virker) | **`/go/<id>/` sendte læseren til forsiden.** Målt 3/11: `/go/477/`, `/go/767/`, `/go/940/` og `/go/809/` svarede alle **302 → `https://steamgears.eu/`** med `x-redirect-by: Steamgears`. Årsagen stod i kilden: `SGAP_Tracking::redirect()` slog kun op i `sgap_queue`, og produkt-ID'er findes ikke der. Rettet i plugin **7.30.0**: mangler kø-posten, forsøges `wc_get_product($id)` og redirect til permalinken; ukendt ID går stadig til forsiden, kø-posten vinder stadig, og opslaget er guardet mod sites uden WooCommerce. 5 nye smoke-checks (**341 PASS**), zip bygget og byte-identisk med kilden, ingen ny `wp_mail`, ingen ændring i klik-attributionen. Målt grundlag: 67 af 67 produkt-permalinks svarer 200, og de publicerede guides har i dag **nul** `/go/`-links — så intet live-link pegede forkert. Diagnosen: [`library/go-ruten-produktlinks-2026-11-03.md`](library/go-ruten-produktlinks-2026-11-03.md). **Tilbage til ejeren:** *Plugins → Update* til 7.30.0 og indsættelse af de 20 `klar`-links |
| 2026-11-03 (round 26 — priserne målt) **Den sidste datamangel i S2.6 er væk.** De otte rækker der stod som `pris mangler` (766, 767, 940, 941, 942, 968) har fået deres pris målt i den levende butik gennem Store API (`/wp-json/wc/store/v1/products`, 200 uden credential, **67 produkter**): $24.90, $6.90, $89.00, $149.00, $69.00 og $29.95. Alle **31** produkter i planen findes i butikken, og de **25** priser der allerede stod i pristabellen stemte **25 af 25** — målingen bekræftede altså også det skrevne. `—` er ikke længere en gyldig pris og `pris mangler` ikke længere en gyldig status i `link_plan_check` (13 fixtures), og `guide_check` læser priser fra bade pristabellen og målingen. Planen er nu **20 `indsat` + 20 `klar`**, alle med ID og pris. Målingen og hele produkttabellen: [`library/priser-fra-store-api-2026-11-03.md`](library/priser-fra-store-api-2026-11-03.md). **S2.6 står stadig 0,7 af 0,75 d** — blokeret arbejde tæller ikke som fremgang: de 20 `klar`-links kræver WordPress-adgang, og `/go/`-ruten kræver en Redirection-gruppe eller Royal Links. Site uændret **57,9 %** (12,6 af 21,75 d) |
| 2026-11-03 (round 25 — S2.6-dækning) **De to sidste stier er redegjort, og guide-tallet stemmer.** Planen har fået en dækningstabel pr. guide: **ti guides · fyrre rækker** — de ni fra 2/11-planen plus outfit-guiden, som er den ene der findes som fil i repoet. `/build/weathering/` (767) og `/steampunk-gift-guide-2026/` (940, 941, 942, 968) står nu som **navngivet blokering med årsag**: ID'erne er sporet til appendix i mappingen, men produkterne blev udgivet 2/11 2026 og har **ingen prisrække** i `valuta-og-priser-2026-11-02.md`, så både en kontrolleret pris og selve indsættelsen kræver et ejer-opslag og WordPress-adgang. Ingen række er uden ID. **S2.6 står fortsat 0,7 af 0,75 d (ikke 100 %)** — de 20 indsatte links er de 0,7, de 12 `klar` og 8 `pris mangler` ligger stadig i planen. Site-projektet uændret **57,9 %** (12,6 af 21,75 d) |
| 2026-11-03 (round 24 — link-planen anvendt) | **S2.6 er nu faktisk gennemført i repoet, ikke kun planlagt.** Den ene ældre guide der findes som fil — `/steampunk-outfit-ideas-for-women/` — har fået **20 `/go/<id>/`-links** på de linjer hvor produkterne allerede stod, plus en “Read Next”-sektion med de fire nye guides og hubben; `link_plan_check.py` læser filen og afviser en række der påstår `indsat` uden at linket står der. Produkt-ID-mappingen har fået et **appendix med seks kilder** (766, 767, 940, 941, 942, 968), så alle ni guides fra 2/11-planen nu har mindst ét kortlagt produkt — `pris mangler` hvor pristabellen ikke har en række, aldrig en gættet pris. 40 plan-rækker i alt: **20 indsat · 12 klar til adgang · 8 uden pris**. **S2.6 rettes fra 0,75 til 0,7 af 0,75 d** (scope-korrektion: de 12 links på WordPress-only-guides er ikke anvendt), S2 4,8 → **4,7 af 6 d**, site-projektet 58,4 % → **57,9 %** (12,6 af 21,75 d; S3 steg til 2,45 i samme pas fra den natlige kørsel) |
| 2026-11-03 (round 23 — link-plan, S2.6 lukket) | **Link-planen for de ældre publicerede guides** ([library/guide-links-aeldre-2026-11-03.md](library/guide-links-aeldre-2026-11-03.md), PDF `reports/guide-links-aeldre-2026-11-03.pdf`): **tolv** `/go/<id>/`-links på **syv** guides, med den samme struktur som de fire nye (link ved første omtale, ét pr. produkt, hub i bunden, ingen produktboks på `/build/*`). Nye **`tools/link_plan_check.py`** kontrollerer hver række mod ID-mappingen og pristabellen og har 8 selftest-fixtures; den er værn nr. 12 og 13 i den natlige kørsel (plan + selftest). Seks ID'er fra 2/11-planen (766, 767, 940, 941, 942, 968) står ikke i mappingen og er derfor **navngivet som blokerede** i stedet for gættet — det rammer `/build/weathering/` og `/steampunk-gift-guide-2026/` helt. **S2.6: 0,7 → 0,75 af 0,75 d = 100 %** (planen er den del der kan verificeres i repoet; indsættelsen kræver adgang og er noteret som blokeret). S2: 4,75 → **4,8 af 6 d**. Site-projektet 56,8 % → **57,0 %** (12,4 af 21,75 d) |
| 2026-11-03 (round 22 — hub-copy) | **Hub-copy til `/steampunk-costumes/` (side 550)** ([library/hub-steampunk-costumes-2026-11-03.md](library/hub-steampunk-costumes-2026-11-03.md), PDF `reports/hub-steampunk-costumes-2026-11-03.pdf`): titel 56 tegn, meta 151, H1, 955 ord, quick answer, fem sektioner, tre figurer med udregnede totaler (99,85 · 126,80 · 149,80 · 151,80), otte FAQ og **15** `/go/`-links. Siden routes til de fire guides i stedet for at gentage deres budgettabeller — hubben står i GSC som *Discovered — currently not indexed* siden 1/11, og det er copyen, ikke linkmængden, der var problemet. Verificeret med samme værn som guidene (titel/meta/H1/ordtal, ID-mapping, pristabel, produktnavne) plus guide-klyngens fire links. **Modul S2: 4,65 → 4,75 af 6 d (S2.6 0,6 → 0,7 af 0,75)**, fordi hub-siden er hub-delen af den samme linkstruktur. Site-projektet 56,3 % → **56,8 %** (12,35 af 21,75 d) |
| 2026-11-03 (round 21 — guide-klynge, S2.6) | **De fire guides er nu én linket klynge** ([library/guide-hub-2026-11-03.md](library/guide-hub-2026-11-03.md), PDF `reports/guide-hub-2026-11-03.pdf`): hver guide har en “Read next — the four-guide cluster”-sektion med de tre søsterguides og hubben `/steampunk-costumes/`, så ingen af dem er en død ende, og `guides-2026-11-03.md`/`-corset.md` er re-renderet (ordtal 884–1.271). `tools/guide_check.py` håndhæver det nu med tre nye værn — hub-link, fuld klynge (alle `Slug`-felter er sandheden, også på tværs af filer) og **produktnavn** på hvert `/go/<id>/` (linktekstens ord skal gå igen i ID-mappingens titel, så et kopieret link med gammelt id afvises). Selftest **7 → 11 fixtures**, alle grønne; 4/4 guides grønne. Værnet fandt straks to korte linktekster (475 “goggles”, 567 “tee”) og tvang reglen “kort navn skal gå igen i titlen” i stedet for “mindst to fælles ord” — ellers havde det afvist korrekt copy. **Modul S2: 4,45 → 4,65 af 6 d (S2.6 0,4 → 0,6 af 0,75)**, fordi de sidste 0,15 d er de samme links på de ældre publicerede guides. Site-projektet 55,4 % → **56,3 %** (12,25 af 21,75 d) |
| 2026-11-03 (round 20 — corset-guide) | **Corset-guiden skrevet** ([library/guides-2026-11-03-corset.md](library/guides-2026-11-03-corset.md), PDF `reports/guides-2026-11-03-corset.pdf`, 4 sider): "Steampunk Corset: How to Choose, Style and Wear One", 1.167 ord, titel 51 tegn, meta 155 tegn (præcis på grænsen), fem sektioner med "In one line", fire Complete-the-look-outfits, budgettabel med ti stykker ($451.50) og otte FAQ. Alle ti produkter og priser kommer fra `om-maerkning-dark-academia-2026-11-01.md` og `valuta-og-priser-2026-11-02.md`; `tools/guide_check.py` regnede fire totaler efter (451,50 · 109,85 · 159,85 · 154,85) og afviste to beløb ($71,90 og $96,85), der manglede i total-linjen — tilføjet, ikke fjernet. **4/4 guides grønne, 7/7 selftest-fixtures.** Guiden er det sidste brief-definerede mål kataloget kan bære: briefene 24.–26/10s vest-guide og 31/10s boots/spats-guide (id 293) har ingen produkter og kan derfor ikke få et `/go/`-link, og 31/10s "under $100" er dækket af guide 2. **Modul: guide-copy 3 → 4 af 4; publicerede guides står stadig 0 af 3 (+ den nye corset-side)**, fordi fotos, schema og publish kræver WordPress-adgang. Projektet 50,0 % → **57,1 %** (4 af 7: fire copy-guides, nul publicerede, loop tælles ikke) |
| 2026-11-03 (round 19 — guide-copy) | SEO-indholdsproduktion: **de tre guides fra briefen 1/11 er skrevet færdige** ([library/guides-2026-11-03.md](library/guides-2026-11-03.md), PDF i `reports/guides-2026-11-03.pdf`): men's essentials (292), tre budgetter (296) og women's essentials (295) med Rank Math-felter, H1, brødtekst (884–1.160 ord), FAQ, `/go/`-produktlinks og hub-links. Nye **`tools/guide_check.py`** kontrollerer titel- og metagrænser, ordtal, FAQ, pladsholdere, og at hvert `/go/<id>/` og hvert `$beløb` findes i ID-mappingen og pristabellen. 7 selftest-fixtures og 3/3 guides grønne, og værn **`guide-copy`** er tilføjet til `tools/natlige-vaern.sh`. Regnestykkerne fangede tre fejl undervejs (kaptajn-totalen, wardrobe-totalen og minimumsoutfittet var 1–30 USD forkerte) — rettet før commit. **Modul: guide-copy 0 → 3 af 3; publicerede guides står stadig 0 af 3**, fordi fotos, HowTo/FAQPage-schema og publish kræver WordPress-adgang. Projektet 25,0 % → 50,0 % (3 af 6 guides: de 3 er copy, de 0 er publicerede; den daglige loop tælles ikke med, fordi den er recurring) |
| 2026-11-02 (round 15 — kampagnenavn) | Plugin 7.24.0: **`utm_campaign` er blevet en parameter** (P1.8). `SGAP_UTM::build()` tager `campaign`, falder tilbage til den nye indstilling *Campaign name* (`sgap_utm_campaign`, standard `steamgears_premium_evergreen`, så gamle links forbliver sammenlignelige), og `SGAP_Queue::add()` sender postens eget `campaign` videre fra REST `/queue` og `queue-post`-abilliteten. Værdien `sanitize_key`-es ved indgangen, så et kampagnenavn ikke kan smugle en query-streng ind i linket, og den er aldrig tom; den fryses ind i linket ved kø-tid, så poster der allerede står i køen beholder det navn de fik. 10 nye smoke-checks (219 → 229) og 3 integrationstjek. §2b's tabel siger nu 7.24.0 i repo, 7.22.0 live — forskellen er ét klik. **Versionsnummeret kolliderede:** 7.23.0 var allerede taget af `meta-audit`-abilliteten (committet samme aften, med zip men uden changelog-række og med `* Version:` på samme linje som `* Description:`, hvilket CI's zip-job afviser). Rækkefølgen er nu: 7.23.0 = meta-audit, 7.24.0 = kampagnenavnet, headeren er rettet til to linjer igen, og begge releases har fået deres changelog-rækker. |
| 2026-11-02 (round 14c — MCP-serveren slukket) | `/mcp` svarer nu `404 rest_no_route`, altså er MCP-serveren deaktiveret, ikke bare nøglen ugyldig (den gav `401` tidligere på aftenen). Det betyder at **al** site-side læsning og skrivning er blokeret, også den verifikation af 7.22.0 der var aftalt: `wp_get_plugins`, `queue-post` og `status` kan ikke kaldes, og der findes ingen offentlig flade der viser plugin-versionen. §2's agent-linje og §2b siger nu det, og procenterne kan ikke flytte sig mens MCP er slukket. OAuth er samtidig målt død fra cloud-worktreen: `.well-known/oauth-authorization-server`, `/register` og `/token` svarer `454` fra Simplys WAF. |
| 2026-11-02 (round 14b — MCP-nøglen døde) | Ejeren klikkede *Plugins → Update*, og verifikationen blev **blokeret**: MCP-nøglen i `.env` svarer nu `401 Invalid or expired access token` på hvert kald, også med browser-UA og på frisk session (den virkede om formiddagen med 270 værktøjer). Ingen påstand om live-version er derfor opdateret: §2's snapshot og §2b's tabel står nu som *sidste måling før klikket*, ikke som gældende status, og de tre efterkontroltrin er markeret som afventende. Nøglen skal roteres i wp-admin og lægges i `.env` som `MCP_ACCESS_TOKEN`, så kan trinnene køres. `docs/mcp-auth.md` har fået samme rettelse, inkl. at sitets `454` på forskiden er Simplys bot-regel og ikke en nøglefejl. |
| 2026-11-02 (S0.6 omskrevet) | Rettede S0.6, som påstod at søgemaskiner ikke kan crawle sitet. Målingen i [library/waf-diagnose-2026-11-02.md](library/waf-diagnose-2026-11-02.md) viser det modsatte: 455 rammer spoofede bot-UA'er fra ikke-Google-IP'er, neutral UA får 200 på samme URL, Google indekserer sitet (`site:` 2/11) og GSC har sitemap hentet 31/10. Rækken kræver nu kun én læsning af GSC → Crawl stats → By response (0 × 455/5xx for Googlebot); »bed Simply hvidliste søgemaskine-bots« er fjernet som handling, fordi Simply allerede hvidlister på IP og kræver en offentlig IP-liste — connectoren kan ikke røre WAF-regler. §1's »no GSC data pulled« og §7's rækkefølge er rettet samme sted. **Ingen ændring i procenter:** S0.6 er stadig 0,25 d og åben, så S0-båndet står på 1,9 af 2,6 d. Runde 8's konklusion nedenfor står uændret som historik, men er erstattet af denne række. || 2026-11-02 (round 14 — dokumentation og deployment) | Dokumentationspas, ingen kode og ingen procenter flyttet. **§2b er ny:** hvad der ligger i repoet mod hvad der kører på sitet, målt med `wp_get_plugins` — Autopilot **7.22.0 i repo, 7.21.0 live**, altså er quality-gaten fra P1.6 bygget og CI-verificeret men ikke uploadet, fordi et plugin-folder kun kan skiftes af WordPress selv. Sektionen skriver den ene klik-vej (*Plugins → Update* via selv-opdateren) og de tre trin der afgør om en release faktisk virker: version + `active` fra `wp_get_plugins`, et feature-svar fra sitet (en blokeret `queue-post` og samme post med `force`), og `quality_gate.mode` i `status`. Rettet tre forældede steder: §2's snapshot sagde 7.18.0, P1.6-rækken sagde "4/11" (en dato der ikke var indtruffet), og plugin-README'ens installationslinje pegede på v7.18.0-zip'en. Release-proceduren ligger nu i plugin-README §Updates og er refereret herfra. |
| 2026-11-02 (autonomous round 13) | Plugin 7.22.0: **quality gate før køning** (P1.6). `includes/quality.php` (`SGAP_Quality`) ligger inde i `SGAP_Queue::add()`, som er den eneste vej ind i køen, så Studio, AI-drafts, REST-API'et og Claude-værktøjerne alle rammer samme kontrol. Fire tjek: **bandeord** fra ejerens egen liste (hele ord, og et hashtag tæller som ordet); **døde links** — interne links slås op i WordPress selv, så et slug der er væk eller en post der stadig er kladde fanges, mens arkiver, feeds og søgning genkendes og ikke meldes; **manglende alt-tekst** på fremhævet billede; **næsten-dubletter** mod de sidste 40 poster på samme kanal (85 % ord-overlap, og posten der gentages navngives). Tre tilstande (block/warn/off, standard block) og en `force`-udvej der køer posten med fundene som advarsler; captions under otte ord kaldes aldrig dubletter. Ingen udgående HTTP — eksterne værter probles ikke, og README forklarer hvorfor. 36 nye smoke-checks (172 → 208). Plugin 24,8 % → 29,0 %. |
| 2026-11-02 (autonomous round 12) | Plugin 7.21.0: **blackout-kalender** (P1.3). `includes/blackout.php` (`SGAP_Blackout`) tager ét datointerval pr. linje i Settings → Schedule & limits (`2026-12-24..2026-12-26 Christmas`). Intet frigives i intervallet, og en post der allerede står i køen flyttes til **næste frie dag samme klokkeslæt** — ikke slettet, og ikke opsparet til ét samlet læs når perioden slutter. Flytningen kører i toppen af publish-tick hvert 15. minut, rører aldrig `published`/`sending`-rækker og er idempotent (en flyttet post flyttes ikke igen). `blocked_reason()` melder `blackout` med intervallets label og hvor posten havner, Queue-siden forklarer det, og en post der stadig er blokeret efter 60 dage placeres alligevel, så en tastefejl ikke kan parkere den for evigt. 21 nye smoke-checks (151 → 172). Plugin 22,7 % → 24,8 %. |
| 2026-11-02 (autonomous round 11) | Plugin 7.20.0: **pre-flight-validering pr. kanal** (P1.1). `includes/preflight.php` (`SGAP_Preflight`) tørkører hver kø-post mod nettets egne grænser *før* det første Publer-kald: Instagram kræver foto, JPEG, ≥ 320 px og 4:5–1,91:1; Pinterest kræver foto og board; captions og titler tjekkes mod 2 200/500/500/280/300/500/1 024/1 500/3 000/63 206 tegn og mod hashtag-loftet (Threads: præcis én). Det mekaniske **repareres** — ordgrænse-afkortning og overskydende hashtags fjernes — i stedet for at Publer afviser posten og brænder et af de tre forsøg; resten afvises med en besked der navngiver rettelsen. Billedtype og -størrelse læses fra attachment-metadata eller filendelsen, aldrig fra en download, så et billede den ikke kan inspicere blokeres ikke på et gæt. `SGAP_Queue::add()` returnerer de samme tjek som `warnings`, så Studio og en agent ser dem ved oprettelsen, og ability `check-post` tørkører det hele. 23 smoke-checks tilføjet (128 → 151). Plugin 18,5 % → 22,7 %. |
| 2026-11-02 (autonomous round 10) | Plugin 7.19.0: **whole-site SEO/GEO/AEO audit** replaces the per-page loop. `includes/site-seo.php` (`SGAP_Site_SEO`) scores every published post, page and product in one pass against 20 rules — SEO (title/meta length, missing meta, one H1, text length per type, internal links, alt text, featured image, slug, duplicate title/description), AEO (question headings, FAQ + `FAQPage` schema, answer-first paragraph, lists/tables) and GEO (named entity, quotable numbers, structured data, outbound citations, freshness) — plus four whole-domain checks (llms.txt, sitemap, robots.txt, product feed). The second button writes the missing Rank Math/Yoast meta for the weakest URLs and never touches body text. Admin card in Content Studio, ability `optimize-site-seo`, lazy weekly refresh because WP-Cron is not dependable on this host. 16 smoke checks added (112 → 128, all pass), zip rebuilt byte-identical, CI version+zip check satisfied. Plugin 15 % → 18,5 %. |
| 2026-11-02 (autonomous round 9) | Plugin-audit og owner-PDF i samme pas. **Audit** (`library/plugin-audit-2026-11-02.md`): 36 plugins + 4 drop-ins, 33 aktive, 5 reelt bagud på WordPress.org, 3 der ikke kan tjekkes udefra, 5 overlap-par og 3 inaktive plugins. To tal rettet: Health Check er ét plugin plus dets egen drop-in, og de «16 bagud» kommer fra et 23/10-snapshot i `plugin-opdateringer-triage-2026-11-02.md`, ikke fra det live site. Fund: det inaktive **Royal Links** kan levere den `/go/<id>/`-sti som S1.5 har været blokeret på. **PDF**: `docs/pdf/manual-roadmap-2026-11-02.pdf`, fem sider med status, manuel roadmap i fire faser og tidsestimat. S3 42 % → 53 %. |
| 2026-11-02 (autonomous round 8) | Fortsatte S2/S3 med bot-tjek. **Ny finding (S0.6):** Simply.com svarer **455 «Security Incident Detected»** til Googlebot og bingbot på alle sider, inkl. robots.txt og sitemap, mens browsere får 200. Coming Soon (SeedProd) er ikke årsagen. Derfor trukket S0.7 tilbage (S0 2,4→1,9, site 50,7 %→48,3 %, `scope_change` logget). S2.6 målt på alle 30 publicerede indlæg: **30/30** linker til shop eller produkt, men kun **10/30** har et kontekstuelt link til en hub (`/start-here/` eller `/build/`); 20 indlæg mangler det. S2.6 er derfor stadig åben. S3.1–S3.3 var allerede lukket i runde 5. Ejer skal bede Simply om at hvidliste søgemaskine-bots; derefter genmåles S0.6/S0.7. Log: `docs/library/vedligehold-ugentlig-log.md`. |
| 2026-11-02 (autonomous round 7) | Skriveadgang via MCP OAuth (PKCE, ingen nøgle i repo), og fire punkter lukket i samme pas: **S2.3** — `Start Here` og `Build` er nu i den site-wide header (de to hubs havde nul site-wide links), og `/right-of-withdrawal/` + cookie-indstillinger kom i footeren; **S0.7** — alle 121 sitemap-URL'er crawlet, 121 × 200; **S2.5** — 15 indekserbare sider og indlæg fik titel, description og fokusnøgleord, 0 huller tilbage af 59 undersøgte; **S2.4** — forsidens dobbelte H1 rettet på live-sitet. Stadig blokeret: S0.4/S2.2 (Redirection har ingen gruppe), S1.1/S1.2 (varianter og placeholder-tekster), S0.5 (ingen plugin-toggle i MCP), S0.2 (robots-ejerskab). Detaljer i [library/navigation-og-meta-2026-11-02.md](library/navigation-og-meta-2026-11-02.md). Moduler nu S0 92 %, S1 38 %, S2 66 %, S3 42 %, S4 27 % — 10,3 of 21,1 days. |
| 2026-11-02 (autonomous round 6) | Tried to create the missing size variations on the nine tees. **Blocked at the MCP surface:** WooCommerce refuses variations on a `simple` product and no tool can change the product type (`wc_update_product` has no `type` parameter, `wp_add_post_terms` is append-only, nothing removes a term). Delivered instead: `library/tee-varianter-spec-2026-11-02.md` with all 45 rows, the exact admin steps and the blokerings-bevis. Secondary finding: all five hoodie variations share one SKU (`SG-HOOD-23`), which makes pick lists ambiguous. S1 unchanged at 38 % — blocked work is not progress. |
| 2026-11-02 (autonomous round 5) | Shop links finished: all 30 published posts and the commercial pages now link the shop (28 unique product targets, 0 bad). `/curiosities-vol-3-live/` lists all eight Vol. 3 tees, the three stories link their collections, `/steampunk-costumes/` gained six products plus five character categories, and the size guide, both new build children and the two lead magnets got a shop path. Hygiene: private design pages 692/870/871 and duplicate page 72 trashed, empty `digital-downloads` category set to noindex. Alt text verified complete (210 images, 0 missing). **New finding, documented in `library/butik-fund-2026-11-02.md`:** the nine published tees are `simple` products with no size selection while hoodies are `variable`; fixing it needs a Printify variant mapping first, so it was not touched. Currency proposal for S4.3 in `library/valuta-og-priser-2026-11-02.md`. Modules now S0 73 %, S1 38 %, S2 50 %, S3 42 %, S4 27 % — 9,25 of 21,1 days. |
| 2026-11-02 (autonomous round 4) | Five hub children built and wired to their parents: glossary (1205), characters (1208), occasions (1211) under `/start-here/`; tools-and-materials (1214) and weathering (1217) under `/build/`. 56 unique link targets across the seven hub pages, 0 bad (checked on title, not just status). Six of the eleven planned children were skipped as thin duplicates of live posts and `/inspiration/` was left to the gallery; the reasoning is in `seo.md` §5e. Modules now S0 73 %, S1 31 %, S2 50 %, S3 20 %, S4 27 % — 8,3 of 21,1 days. |
| 2026-11-01 (autonomous round 3) | Draft triage finished: 750 merged into 699, 938 merged into 974, both drafts trashed, and the six superseded draft products (27, 28, 29, 75, 183, 765) moved to trash so their URLs answer 404 instead of a 200 soft-404. Two hubs built — `/start-here/` (1183, 31 links) and `/build/` (1185, 15 links), both with Rank Math meta and both linked from ten guides; the eleven child pages and `/inspiration/` remain. All twelve Herbarium products (953–956, 965–968, 969–972) now have full descriptions in the live-product structure with zero placeholders left, but cannot publish: they are simple products without sizes or phone models, and MCP has no tool to set product categories. Modules now S0 73 %, S1 31 %, S2 40 %, S3 20 %, S4 27 % — 7,7 of 21,1 days. |
| 2026-11-01 (autonomous round 2) | Six finished article drafts published (989 gaslamp fantasy, 988 steampunk books, 974 dieselpunk, 897 outfit ideas for men, 898 couple costumes, 789 accessories for men) with SEO meta and collection links; 750 and 938 left as merge candidates against live 699 and 974. Fixed the remaining dead product links in the two newly published posts and re-verified by page title, not status code — unknown URLs on this site return 404, but the six superseded draft products return **200 with a "Page Not Found" title**, a soft-404 that made status-based link checks blind. The 12 Herbarium products stay draft: they are simple products with no variants, so a tee has no sizes and a phone case has no models, and they still contain `[PLACEHOLDER]` text. Modules now S0 73 %, S1 27 %, S2 32 %, S3 20 %, S4 27 % — 7,0 of 21,1 days. |

| Date | Change |
|---|---|
| 2026-11-01 (SEO round) | Autonomous SEO pass executed and measured across all 108 sitemap URLs: 18 pages got new SEO titles/descriptions, 5 category terms got descriptions, `/opt-out-preferences/` set to noindex, all 24 dead link targets rewritten in content (42 edits in 15 posts), and 39 product images got alt text matching the new product names. Dead internal links: 39 → 0. Titles over 62 chars: 19 → 0. Missing meta descriptions: 3 → 0. Performance diagnosed but not fixed: 458 KB product HTML, 151 KB inline CSS, 105 script tags, W3TC minify/lazyload/db-cache off and no MCP write path. Module status: S0 73 %, S1 9 %, S2 29 %, S3 20 %, S4 27 % — 6,0 of 21,1 days done. Full detail in [seo.md](seo.md). |

| Date | Change |
| --- | --- |
| 2026-11-01 | First version. Created from the live MCP audit plus the plugin roadmap import. Actioned in the same change: **S0.1** (security case closed), **S0.3** (CI to PHP 8.5 / WP 7.1), **S0.8** (W3TC purge, verified). **S0.4 attempted and blocked** — Redirection has no group to file into, and no MCP path can create one (§4 S0.4). A full internal-link crawl then found **39 dead links across 15 live pages**, catalogued in [library/dode-links-2026-11-01.md](library/dode-links-2026-11-01.md) — this is the bigger half of S0.4 and does not need Redirection. Remaining in S0: **1.5 d** of work plus the S0.4 unblock.
| 2026-11-01 (later) | Added band **S4 — butik og salgsklar (5.0 d)** from the store audit in [library/butiksaudit-2026-11-01.md](library/butiksaudit-2026-11-01.md). Two structural findings: the store prices in **USD**, and Complianz runs a **US opt-out** consent model in front of EU customers. Shipped in the same change: **`/right-of-withdrawal/`** (procedure + EU model form, linked from the refund policy) and a corrected refund-timing clause. Blocking on the owner for: CVR, registered name, address, phone, VAT/OSS status, and the currency decision — see the audit’s closing list.
| 2026-11-01 (later) | Added [library/niche-vurdering-2026-11-01.md](library/niche-vurdering-2026-11-01.md): the recommendation is **not to change niche**, because with 2 orders ever and no instrumentation (Royal Links inactive, W3TC stats off, no analytics access) the niche has never been tested; instead broaden the *search* surface (dark academia: 18.1k searches vs 7.4k listings) and keep steampunk as the design language and Halloween capsule. Adds a 90-day test with ATC-based decision rules.
| 2026-11-01 (later) | **Executed rebrand batch 3:** all 39 products given new descriptions and short descriptions — motif hook, family bullets, fit and size table, production 3–7 business days, delivery 6–10 (DK) / 7–12 (EU) days, care, GPSR block and collection link. Fixed 475's short description, which claimed 2–3 weeks shipping against the site's own 3–7 days. GPSR block still lacks a postal address (owner data pending). See [library/produkttekster-2026-11-01.md](library/produkttekster-2026-11-01.md). |
| 2026-11-01 (later) | Added [library/mockup-brief-2026-11-01.md](library/mockup-brief-2026-11-01.md): a Printify checklist for all 39 products (38 physical + 1 PDF) in five phases, ~130 new images, ~6.5 h. Current state confirmed via Store API: every product has one image, the five posters two. |
| 2026-11-01 (later) | **Executed rebrand batches 1–2:** 32 of 39 products renamed with dark academia / gothic / vintage-maritime titles plus SEO title, meta description and focus keyword, and six customer-facing collections created (Dark Academia 24, Gothic & Victorian 6, Natural History 6, Vintage Maritime Prints 7, Witchy & Tarot 3, Steampunk Capsule 7) with existing categories preserved. All six archive pages return 200, W3TC purged. Remaining risk: Printify may overwrite the titles on its next sync, and batches 3–4 (copy, mockups) are untouched. |
| 2026-11-01 (later) | Added [library/om-maerkning-dark-academia-2026-11-01.md](library/om-maerkning-dark-academia-2026-11-01.md): per-product mapping of all 39 designs — 21 rebrand to dark academia, 6 relabel to gothic/victorian, 7 stay as the steampunk capsule — plus five customer-facing collections and a 5-image mockup plan per family. 11–16 h, of which the mockups (4–6 h) need Printify access. Two constraints recorded: slugs must not change (39 inbound links, 24 dead targets already), and titles/descriptions must be edited **in Printify** or the next sync overwrites them. |
