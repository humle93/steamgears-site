# MCP-værktøjer på steamgears.eu — AI Engine og mcp-adapter

Målt og kortlagt 2/11 2026. To kilder: **live-måling** af sitets REST-flade uden credential, og
**kildelæsning** af de plugins der svarer. Formålet er at kunne svare på hvad en MCP-klient faktisk kan
gøre mod sitet — og hvad den ikke kan — uden at gætte.

## Metode og forbehold

| Kilde | Version | Hvordan |
| --- | --- | --- |
| AI Engine (Meow Apps) | **3.9.0** fra wordpress.org | Hentet som `ai-engine.3.9.0.zip` og læst i `labs/mcp*.php` |
| mcp-adapter (WordPress) | **0.7.1** | Samme release-zip som ejeren installerede (`releases/latest/download/mcp-adapter.zip`) |
| Sitet | ukendt plugin-version | `wp-content/plugins/ai-engine/readme.txt` svarer **455** (Simply WAF), så versionen kan ikke læses udefra |

Forbehold: værktøjslisten er fra **3.9.0**. Kører sitet en ældre eller nyere udgave, kan listen afvige.
Det tal der gælder for sitet er `tools/list` på endpointet, og det kræver et credential — se §4.

## 1. Den levende MCP-flade (målt uden credential)

| Rute | Svar | Betydning |
| --- | --- | --- |
| `POST /wp-json/mcp/v1/http` | `401` + `WWW-Authenticate: Bearer realm="MCP", resource_metadata="…/?rest_route=/mcp/v1/.well-known/oauth-protected-resource"` | AI Engines MCP-endpoint; lukket, men korrekt annonceret |
| `GET /wp-json/mcp/v1/.well-known/oauth-protected-resource` | `200` | `resource: …/wp-json/mcp/v1/http`, `scopes_supported: ["mcp"]` |
| `GET /wp-json/mcp/v1/.well-known/oauth-authorization-server` | `200` | authorize/token/register under `/wp-json/mcp/v1/oauth/*` |
| `GET /wp-json/mwai/v1/mcp/status` · `…/mcp/functions` | `401` | AI Engines admin-ruter for MCP; kræver credential |
| `POST /wp-json/mcp/mcp-adapter-default-server` | `401` · `rest_forbidden` | Adapterens ene rute |
| `POST /mcp` · `POST /wp-json/royal-mcp/v1/mcp` | `404` | Royal MCP svarer ikke længere |
| `GET /.well-known/oauth-protected-resource` og `…/oauth-authorization-server` | `200` med gyldig JSON | rod-discovery virker nu; under aktiveringen svarede de `301`/`500` — se §1-noten |

**Transient, ikke en tilstand:** de to rod-stier svarede `301` til forsiden og den dybe sti `500` i minutterne
efter `wp plugin install … --activate`, fordi aktiveringen flusher rewrite-regler. Senere samme aften svarer alle
tre `200`. Et probe lige efter en plugin-aktivering siger derfor intet om, hvordan discovery opfører sig bagefter.

## 2. AI Engine — 74 værktøjer

Fordeling på adgangsniveau: **29 læsende**, **31 skrivende**, **14 admin**.
Niveauet er pluginets eget `accessLevel`, og det styrer hvad en klient må kalde — ikke hvad den kan se.

### 2.1 Læsende (29)

| Værktøj | Beskrivelse |
| --- | --- |
| `acf_get_fields` | List the Advanced Custom Fields (ACF) of a post or term: every field its field groups give it, even empty ones, with name, label, type, current val… |
| `mcp_list_changes` | List the recent changes made on this site through MCP (by any assistant, newest first), with their ID, what they changed, and whether they can be u… |
| `mcp_ping` | Simple connectivity check. Returns the current GMT time and the WordPress site name. Whenever a tool call fails (error or timeout), immediately inv… |
| `mwai_vision` | Analyze an image via AI Engine Vision. |
| `wp_count_media` | Return number of attachments (optionally after/before date). |
| `wp_count_posts` | Return counts of posts by status. Optional post_type (default post). |
| `wp_count_terms` | Return total number of terms in a taxonomy. |
| `wp_get_blocks` | Read a post or page as an outline of its Gutenberg blocks: each block's path ( |
| `wp_get_comments` | List comments (comment_ID, comment_post_ID, comment_type, comment_author, comment_content, comment_date, comment_approved), 10 by default. Filter b… |
| `wp_get_media` | List media items. Filter by uploader with `author` (user ID) or `author_name` (user slug). |
| `wp_get_menus` | List the site's menus: classic menus with their items (ID, title, URL, what they link to, parent) and the theme locations they fill, and, on block… |
| `wp_get_post` | Get a post by ID: title, content, status, dates, permalink (fresh right after the write tools). content_format  |
| `wp_get_post_meta` | Get specific post meta field(s). Provide  |
| `wp_get_post_snapshot` | Get complete post data in ONE call: all post fields, all meta, all terms/taxonomies, featured image, and author. Use this for WooCommerce products,… |
| `wp_get_post_terms` | Get terms attached to a post. |
| `wp_get_post_types` | List public post types (key, label). |
| `wp_get_posts` | Retrieve posts (fields: ID, post_title, post_status, post_excerpt, permalink). No full content. **If no limit is supplied it returns 10 posts by de… |
| `wp_get_redirects` | List the redirects of the Redirection plugin: source path, target, HTTP code (301, 302, 307, 410...), hits and whether each is enabled. Pass search… |
| `wp_get_rendered` | See what a page really shows, to check your work: for a published post or any URL of this site, the HTTP status, <title>, meta description, robots,… |
| `wp_get_revisions` | List the saved versions (revisions) of a post or page: who, when, and how big the change was. Pass revision_id to see exactly what changed in that… |
| `wp_get_seo` | Read the SEO fields of posts or terms from the SEO plugin the site uses (Yoast SEO, Rank Math, All in One SEO, SEOPress, The SEO Framework, Slim SE… |
| `wp_get_shortcodes` | Read a page built with a shortcode page builder ( |
| `wp_get_site_style` | Read how the active theme is styled: the Additional CSS, the theme mods (Customizer settings such as colors, logo or header options), and on block… |
| `wp_get_taxonomies` | List taxonomies for a post type. |
| `wp_get_templates` | Block themes only: list the templates (home, single, page, archive, 404...) and template parts (header, footer...) of the active theme, with whethe… |
| `wp_get_terms` | List terms of a taxonomy. |
| `wp_get_widgets` | Classic themes only: list the widget areas (sidebars, footers) and the widgets in each, with their ID (e.g.  |
| `wp_list_block_patterns` | List the block patterns of this site (core, theme, plugins): ready-made layouts (heroes, pricing tables, testimonials, galleries, calls to action)… |
| `wp_list_plugins` | List installed plugins (returns array of {Name, Version}). |

### 2.2 Skrivende (31)

| Værktøj | Beskrivelse |
| --- | --- |
| `acf_update_fields` | Set ACF field values of a post or term through ACF, by field name:  |
| `mcp_undo_change` | Undo a change made through MCP: puts back what it replaced (post fields, meta, terms, options, plugins...). Pass  |
| `mwai_image` | Generate an image with AI Engine and store it in the Media Library. Uses the site's default image model unless envId/model are given. Optional: tit… |
| `wp_add_post_terms` | Add, replace or remove terms on a post (categories, tags, WooCommerce attributes like pa_color). By default  |
| `wp_alter_post` | Search and replace in one field of a post (post_content, post_excerpt or post_title) without resending the rest. With regex: true,  |
| `wp_create_comment` | Insert a comment. Requires post_id and comment_content. Optional author, author_email, author_url, and comment_parent to reply inside an existing c… |
| `wp_create_note` | Add an editor Note (WordPress 6.9+ block-level feedback, shown in the editor's Notes panel) to a post, attached to one block. Find the block with a… |
| `wp_create_post` | Create a post, a page or any post type (post_type, default  |
| `wp_create_term` | Create a term. |
| `wp_delete_note` | Delete a note permanently. Deleting a top-level note also deletes its replies and detaches it from its block. |
| `wp_edit_blocks` | Change blocks of a post without rewriting it, using the paths from wp_get_blocks.  |
| `wp_edit_shortcodes` | Change a shortcode page builder page without rewriting it, using the paths from wp_get_shortcodes.  |
| `wp_edit_widgets` | Change the widgets of a classic theme, using the IDs from wp_get_widgets.  |
| `wp_insert_block_pattern` | Insert a block pattern (its name from wp_list_block_patterns) into a post or page. mode  |
| `wp_moderate_comments` | Moderate comments in bulk: set  |
| `wp_open_template` | Get an editable copy of a block theme template or template part (e.g. slug  |
| `wp_preview_link` | Get a link to preview a draft. By default the WordPress preview, which needs the person to be logged in. With share: true, a link anyone can open w… |
| `wp_restore_revision` | Bring a post back to an earlier revision (title, content and excerpt), as the Restore button of WordPress does. The current version stays in the re… |
| `wp_save_redirect` | Create a redirect in the Redirection plugin ( |
| `wp_set_featured_image` | Attach or remove a featured image (thumbnail) for a post/page. Provide media_id to attach, omit or null to remove. |
| `wp_site_maintenance` | Common fixes, one  |
| `wp_update_comment` | Update a comment – pass comment_ID plus fields (comment_content, comment_approved). |
| `wp_update_media` | Update attachment meta. |
| `wp_update_note` | Edit a note's text and/or mark a note thread resolved or open again, exactly like the editor does (the thread shows  |
| `wp_update_post` | Update a post's fields and/or custom fields in one call: ID, plus  |
| `wp_update_post_meta` | Update post meta efficiently. Use  |
| `wp_update_seo` | Set SEO fields of a post or term in the site SEO plugin (see wp_get_seo).  |
| `wp_update_term` | Update a term. |
| `wp_upload_media` | Upload a file to the WordPress Media Library. Provide either a url (WordPress will download it) or base64-encoded content with a filename. Base64 m… |
| `wp_upload_request` | Upload a local file (not a URL) to the Media Library without passing it through the conversation: this returns a one-time upload URL, valid 5 minut… |
| `wp_write_blocks` | Lay out an existing post or page as real Gutenberg blocks that open cleanly in the editor (create the post first).  |

### 2.3 Admin (14)

| Værktøj | Beskrivelse |
| --- | --- |
| `wp_create_user` | Create a user. Requires user_login and user_email. Optional: user_pass (random if omitted), display_name, role. |
| `wp_delete_comment` | Move a comment to the trash. With force: true, delete it permanently (irreversible). |
| `wp_delete_media` | Move an attachment to the trash. Most sites have no media trash (MEDIA_TRASH is off by default), so without force this is usually refused: then ask… |
| `wp_delete_post` | Move a post, page, product or any post type to the trash (refused when it is already there or the site has no trash). force: true deletes it for go… |
| `wp_delete_post_meta` | Delete custom field(s) from a post. Provide value to remove a single row; omit value to delete all rows for the key. |
| `wp_delete_term` | Delete a term. |
| `wp_edit_menu` | Change a classic menu ( |
| `wp_get_diagnostics` | Check the site's health when something looks broken or slow: versions and limits (PHP, WordPress, memory, debug settings), the latest PHP errors fr… |
| `wp_get_form_entries` | Read what visitors sent through the site's forms ( |
| `wp_get_option` | Get a single WordPress option value (scalar or array) by key. Set raw to true to read the stored value straight from the database, bypassing the ob… |
| `wp_get_users` | Retrieve users (fields: ID, user_login, display_name, roles). If no limit supplied, returns 10. `paged` ignored if `offset` is used. |
| `wp_update_option` | Create or update a WordPress option. Arrays and objects are stored natively (a JSON string is decoded first). Page caches (Varnish, WP Rocket, Clou… |
| `wp_update_site_style` | Change how the active theme looks, with any of:  |
| `wp_update_user` | Update a user – pass ID plus a “fields” object (user_email, display_name, user_pass, role). |

### 2.4 Ud over værktøjerne

- **Prompts (4):** `tour_my_site`, `write_a_draft`, `audit_my_content`, `improve_a_post` — defineret i
  `labs/mcp.php`. De er skabeloner, ikke værktøjer.
- **Resources: ingen.** `get_resources_list()` returnerer `[]`.
- **Abilities-bro (dynamisk):** `labs/mcp-abilities.php` registrerer **ét værktøj pr. WordPress-ability**
  (navn og beskrivelse kommer fra abilityen), styret af filteret `mwai_mcp_abilities`. Antallet på sitet er
  derfor ikke fast og ikke målt.
- **Sporbarhed og fortrydelse:** `mcp_list_changes` og `mcp_undo_change` plus ruterne `mcp/changes`,
  `mcp/approvals`, `mcp/undo`, `mcp/usage`, `mcp/self_test`, `mcp/auth_echo`.
  `read_only_refusal()` og `role_has_access()` i `labs/mcp.php` kan afvise skrivende kald pr. rolle.
- **Udvidelsespunkter:** `mwai_mcp_tools`, `mwai_mcp_instructions`, `mwai_mcp_needs_approval`,
  `mwai_mcp_callback` — andre plugins kan tilføje værktøjer, så listen på sitet kan være større end de 74.

## 3. mcp-adapter 0.7.1 — 3 meta-værktøjer, ingen egne

Adapteren har ingen indbyggede WordPress-værktøjer. Den oversætter Abilities API til MCP og eksponerer
præcis tre meta-værktøjer på `/wp-json/mcp/mcp-adapter-default-server`:

| Værktøj | Gør |
| --- | --- |
| `mcp-adapter/discover-abilities` | Lister de abilities klienten må se |
| `mcp-adapter/get-ability-info` | Viser én abilitys skema |
| `mcp-adapter/execute-ability` | Kører en ability |

Kun abilities med `meta.public` eller `meta.mcp.public` er synlige; resten er private som standard. Adapteren
har **hverken OAuth eller `.well-known`-ruter** — den arver WordPress' REST-auth (application password over
Basic eller cookie + nonce). Det gør den til et supplement til AI Engine, ikke en afløser: samme site, to
forskellige værktøjsflader, to forskellige credential-modeller.

## 4. Det der først kan siges med et credential

- **Hvor mange værktøjer sitet reelt annoncerer** — `tools/list` mod `/wp-json/mcp/v1/http` eller
  `GET /wp-json/mwai/v1/mcp/status` + `…/mcp/functions`.
- **Om abilities-broen er slået til**, og hvor mange abilities den i så fald lægger oveni de 74.
- **Hvilke niveauer der er låst** for den rolle tokenet tilhører (`read_only_refusal`, rollefilter).
- **Om adapterens default-server har nogen public abilities** — `discover-abilities` på dens rute.

Tallene herfra: **74 kerneværktøjer i AI Engine 3.9.0-kilden**, **3 i mcp-adapter 0.7.1**, og **206 annonceret på
sitet** — det sidste er målt med et OAuth-token, se [`../mcp-auth.md`](../mcp-auth.md). Forskellen på 132 er
Autopilot-familien (31), bridgede abilities og resten af Pro-sættet; fordelingen er ikke målt.

## 5. Sikkerhed

- **45 af de 74 værktøjer kan ændre sitet** — heraf `wp_delete_post`, `wp_delete_media`,
  `wp_update_option`, `wp_create_user`, `wp_update_user`, `wp_site_maintenance`, `wp_update_site_style`,
  `wp_save_redirect` og `mcp_undo_change`. Et OAuth-token til MCP arver den WP-rolle der godkendte det, så det skal behandles som admin-adgang, ikke som
  læsenøgle. AI Engine har desuden *Drafts Only* slået til på sitet, så skrivende kald afvises før plugin'et ser
  dem — den switch, ikke værktøjslisten, afgør om de 45 værktøjer kan bruges.
- **To flader betyder to angrebsflader.** Adapterens `execute-ability` kan kalde de samme abilities som
  AI Engine kan, hvis de er gjort public — så en ability skal kun gøres public bevidst.
- **Dynamisk klientregistrering er åben:** `POST /wp-json/mcp/v1/oauth/register` svarer `201` uden credential.
  Hvem som helst kan altså registrere en klient — det er adgangskontrollen ved `authorize` (WP-login +
  samtykke) og de rollebundne værktøjer der holder, ikke registreringen.

## Referencer

- [`docs/mcp-auth.md`](../mcp-auth.md) — credential-matrix, fejlkoder og den målte udvikling 2/11 2026
- [`docs/agent-profil.md`](../agent-profil.md) — projektprofil og adgangsmatrix
- AI Engine: <https://meowapps.com/ai-engine/>
- mcp-adapter: <https://github.com/WordPress/mcp-adapter>
