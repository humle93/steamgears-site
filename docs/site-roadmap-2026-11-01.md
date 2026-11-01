# Steamgears site — optimisation roadmap

_Last audited: 2026-11-01 · production: WordPress **7.1.4**, PHP **8.5.12**, MySQL 8.4.11, WooCommerce 11.2.1, 33 active plugins · companion to [`docs/plugin-roadmap.md`](plugin-roadmap.md), which is imported in §5._

This is the roadmap for **steamgears.eu itself** — structure, content, store and hygiene. The plugin has its
own roadmap; this document tracks what the site needs, and §5 pulls every plugin item in with its site-side
dependency so there is one place to read the whole plan.

---

## 0. Short version

- **The 25/10 hub structure is still missing.** `/start-here/`, `/build/`, `/inspiration/`, `/shipping/` and
  `/returns/` all return **404** today, seven days after `mangler-og-plan-25-10.md` flagged them. This is the
  single biggest structural gap on the site.
- **The AI Engine security case can be closed.** Production now runs **AI Engine 3.8.9** (fix version for
  CVE-2026-110732 is 3.8.7) and the plugin is **inactive**. The open case in
  `docs/library/sikkerhed-ai-engine-2026-11-01.md` no longer describes the site.
- **The plugin's CI matrix is behind production.** The site runs PHP **8.5.12** and WP **7.1.4**; CI lints
  8.0–8.4 and tests WP 6.4 + latest. Imported item P3.3 is therefore not hygiene any more — it is the one
  change that verifies the code we actually ship to the runtime we actually run.
- **The store is behind the content pipeline:** 23 products in draft (including a complete 12-SKU Herbarium
  line) and 8 draft posts against 39 published products. See `docs/library/indholdsaudit-2026-11-01.md`.
- **The page cache is stale.** The homepage and `/faq/` still serve `mwai-popup-container` and
  `ai-engine/themes/glass.css` for a plugin that is inactive — a cache-busted request has neither. Purge needed.
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

**Not verified:** no Lighthouse/Core Web Vitals run, no GSC or Analytics data pulled, no crawl of the 24
published posts, no order or revenue data. Statements below are limited to what the table above covers.

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
| Agent access | Royal MCP 1.5.13 (this connection), Steamgears Autopilot 7.15.1 |

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

- **AI Engine 3.8.9, inactive** — chatbot off, MCP surface removed (`/wp-json/mwai/v1/*` and the AI Engine
  OAuth routes all answer 404). Its markup still comes out of the page cache; see S0.8. `changelog.txt` still
  reports 3.8.1, so it is no longer a reliable way to read the installed version.
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

Effort is developer-days for one person who knows this site. Nothing here requires a code release except
S0.3, which is a repo change.

### S0 — broken or urgent (2.6 d)

| # | Item | Why | Effort |
| --- | --- | --- | --- |
| S0.1 | ✅ **Done 1/11** — AI Engine case closed in `sikkerhed-ai-engine-2026-11-01.md` (new §0) and in `README.md`; confirmed 3.8.9, inactive, `/wp-json/mwai/v1/*` 404 | A security doc that describes an old state trains people to ignore it | 0.25 d |
| S0.2 | Resolve the two `User-agent: *` groups in robots.txt and record which plugin owns the file | Conflicting robots directives can silently deindex paths | 0.5 d |
| S0.3 | ✅ **Done 1/11** — CI matrix now lints PHP 8.0–8.5 and tests WP 6.4 / 7.1 / latest (imported P3.3) | Production runs 8.5.12 / 7.1.4, CI stopped at 8.4 | 0.25 d |
| S0.4 | Stop the 404 bleed: 301 map for `/start-here/`, `/build/`, `/inspiration/`, `/shipping/`, `/returns/` to the closest live page until S2.1 lands them. ⚠ **Blocked 1/11** — targets chosen (`/steampunk-cosplay-for-beginners/`, `/how-to-make-a-steampunk-costume/`, `/steampunk-cosplay-gallery/`, `/delivery/`, `/refund_returns/`), but Redirection has **no groups** (`wp_redirection_groups` is empty → `Red_Item::create` fails with "Invalid group") and the MCP surface cannot create one. **Unblock: create one group in Tools → Redirection (group dropdown → "Add Group")**, or add the five rows by hand. Note: merely opening the screen does *not* help — Redirection 5.10.1 is active but its installer only seeds groups on activation/upgrade, so `wp_redirection_groups` stayed empty and `group_id` 1/2 are rejected too. Once a group exists the five redirects take one call | Any nav, pin or ad pointing there loses the visitor entirely | 0.5 d |
| S0.5 | Remove the redundant Health Check plugin | Two troubleshooting plugins can fight over the same filters | 0.25 d |
| S0.6 | Confirm "Coming Soon" cannot serve a holding page to logged-out crawlers | One mis-set toggle hides the whole shop from Google | 0.25 d |
| S0.7 | Verify Rank Math sitemap includes the published set and no 404s | Cheap sanity check on the entry point crawlers use | 0.5 d |
| S0.8 | ✅ **Done 1/11** — W3TC purged (`{"purged": true, "scope": "all"}`); homepage and `/faq/` verified free of `mwai-`/`ai-engine/` markup, both still 200 | Cached HTML referenced a plugin that is not running | 0.1 d |
| **S0 total** | | | **2.6 d** |

### S1 — store and content that earns (4.5 d)

| # | Item | Why | Effort |
| --- | --- | --- | --- |
| S1.1 | Launch the Herbarium line: publish the 12 SKUs (953–956, 965–968, 969–972) and link them from story 973 | The story is live and sells nothing; the merch is finished and invisible | 0.5 d |
| S1.2 | Decide and execute on Atelier jewellery (940–942) and DIY/digital (765–767) | Six finished products with SKUs and prices, all drafts | 0.5 d |
| S1.3 | Draft-post triage: resolve 750 (duplicate of 699), merge 974+938, publish 897/898/789/988/989 | 8 drafts carrying duplicate intent against live posts | 1.0 d |
| S1.4 | Empty the product trash (43) and page trash (3) after a link check | Trash inflates admin, sitemaps and any naive count | 0.5 d |
| S1.5 | Product-link coverage: every published guide links at least one product, through the `/go/<id>/` tracking path | Traffic without a path to the shop is the leak this site can least afford | 1.0 d |
| S1.6 | Two lead-magnet pages (801, 764) get a real capture path and a thank-you state | They exist but the funnel behind them is unverified | 1.0 d |
| **S1 total** | | | **4.5 d** |

### S2 — structure and SEO (6.0 d)

| # | Item | Why | Effort |
| --- | --- | --- | --- |
| S2.1 | Build the hubs for real: `/start-here/` + 5 children, `/build/` + 6 children, `/inspiration/` | The plan's spine; 404 today | 3.0 d |
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
| S3.4 | Plugin rationalisation: 33 active → remove the duplicate Health Check, decide TikTok vs Smash Balloon, review WP Pusher against the documented deploy flow | Every plugin is update surface and failure surface | 1.0 d |
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

## 5. Imported from `docs/plugin-roadmap.md`

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
| P1.1 Pre-flight channel validation | 1.0 d | None | Unblocked |
| P1.3 Blackout calendar | 0.5 d | None | Unblocked |
| P1.4 Evergreen recycling | 2.5 d | P0.6 | Blocked on P0.6 |
| P1.5 Platform analytics ingestion | 4.0 d | **Pinterest API token still 401** (see `mangler-og-plan-25-10.md`) + Meta App Review | Blocked externally |
| P2.1–P2.3 Bluesky / Mastodon / Telegram | 2.0 d | Telegram needs a BotFather token; WP Mail SMTP already covers P0.1 | Needs tokens |
| P2.4 Etsy import or hide the settings | 2.5 d / 0.25 d | Owner decision (§6.2) | **Awaiting decision** |
| P3.3 PHP 8.5 + WP 7.1 in CI | 0.25 d | None — and production already runs both | **Done as S0.3 in this change** |
| P1.6 Quality gate before queueing | 1.0 d | Needs an alt-text/redirect inventory (S3.3 helps) | Unblocked, better after S3.3 |

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
S0.2 S0.6 S0.7  (config, needs a decision on robots ownership)
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
| 2026-11-01 (SEO round) | Autonomous SEO pass executed and measured across all 108 sitemap URLs: 18 pages got new SEO titles/descriptions, 5 category terms got descriptions, `/opt-out-preferences/` set to noindex, all 24 dead link targets rewritten in content (42 edits in 15 posts), and 39 product images got alt text matching the new product names. Dead internal links: 39 → 0. Titles over 62 chars: 19 → 0. Missing meta descriptions: 3 → 0. Performance diagnosed but not fixed: 458 KB product HTML, 151 KB inline CSS, 105 script tags, W3TC minify/lazyload/db-cache off and no MCP write path. Module status: S0 73 %, S1 9 %, S2 29 %, S3 20 %, S4 27 % — 6,0 of 21,1 days done. Full detail in [library/seo-runde-og-status-2026-11-01.md](library/seo-runde-og-status-2026-11-01.md). |

| Date | Change |
| --- | --- |
| 2026-11-01 | First version. Created from the live MCP audit plus the plugin roadmap import. Actioned in the same change: **S0.1** (security case closed), **S0.3** (CI to PHP 8.5 / WP 7.1), **S0.8** (W3TC purge, verified). **S0.4 attempted and blocked** — Redirection has no group to file into, and no MCP path can create one (§4 S0.4). A full internal-link crawl then found **39 dead links across 15 live pages**, catalogued in [library/dode-links-2026-11-01.md](library/dode-links-2026-11-01.md) — this is the bigger half of S0.4 and does not need Redirection. Remaining in S0: **1.5 d** of work plus the S0.4 unblock.
| 2026-11-01 (later) | Added band **S4 — butik og salgsklar (5.0 d)** from the store audit in [library/butiksaudit-2026-11-01.md](library/butiksaudit-2026-11-01.md). Two structural findings: the store prices in **USD**, and Complianz runs a **US opt-out** consent model in front of EU customers. Shipped in the same change: **`/right-of-withdrawal/`** (procedure + EU model form, linked from the refund policy) and a corrected refund-timing clause. Blocking on the owner for: CVR, registered name, address, phone, VAT/OSS status, and the currency decision — see the audit’s closing list.
| 2026-11-01 (later) | Added [library/niche-vurdering-2026-11-01.md](library/niche-vurdering-2026-11-01.md): the recommendation is **not to change niche**, because with 2 orders ever and no instrumentation (Royal Links inactive, W3TC stats off, no analytics access) the niche has never been tested; instead broaden the *search* surface (dark academia: 18.1k searches vs 7.4k listings) and keep steampunk as the design language and Halloween capsule. Adds a 90-day test with ATC-based decision rules.
| 2026-11-01 (later) | **Executed rebrand batch 3:** all 39 products given new descriptions and short descriptions — motif hook, family bullets, fit and size table, production 3–7 business days, delivery 6–10 (DK) / 7–12 (EU) days, care, GPSR block and collection link. Fixed 475's short description, which claimed 2–3 weeks shipping against the site's own 3–7 days. GPSR block still lacks a postal address (owner data pending). See [library/produkttekster-2026-11-01.md](library/produkttekster-2026-11-01.md). |
| 2026-11-01 (later) | Added [library/mockup-brief-2026-11-01.md](library/mockup-brief-2026-11-01.md): a Printify checklist for all 39 products (38 physical + 1 PDF) in five phases, ~130 new images, ~6.5 h. Current state confirmed via Store API: every product has one image, the five posters two. |
| 2026-11-01 (later) | **Executed rebrand batches 1–2:** 32 of 39 products renamed with dark academia / gothic / vintage-maritime titles plus SEO title, meta description and focus keyword, and six customer-facing collections created (Dark Academia 24, Gothic & Victorian 6, Natural History 6, Vintage Maritime Prints 7, Witchy & Tarot 3, Steampunk Capsule 7) with existing categories preserved. All six archive pages return 200, W3TC purged. Remaining risk: Printify may overwrite the titles on its next sync, and batches 3–4 (copy, mockups) are untouched. |
| 2026-11-01 (later) | Added [library/om-maerkning-dark-academia-2026-11-01.md](library/om-maerkning-dark-academia-2026-11-01.md): per-product mapping of all 39 designs — 21 rebrand to dark academia, 6 relabel to gothic/victorian, 7 stay as the steampunk capsule — plus five customer-facing collections and a 5-image mockup plan per family. 11–16 h, of which the mockups (4–6 h) need Printify access. Two constraints recorded: slugs must not change (39 inbound links, 24 dead targets already), and titles/descriptions must be edited **in Printify** or the next sync overwrites them. |
