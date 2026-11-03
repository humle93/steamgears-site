# Drafts Only-gaten: hvorfor knappen kan se slukket ud uden at være det (3/11 2026)

Målt 3/11 2026 · læst i kildekoden til **AI Engine 3.9.0** (`downloads.wordpress.org/plugin/ai-engine.3.9.0.zip`) ·
suppleret med to læsende MCP-kald og ét afvist skrivekald mod steamgears.eu

## TL;DR

- Gaten er **én boolean**: `mwai_options['mcp_drafts_only']`. Der findes ingen anden spærring — `mcp_role: admin`,
  `mcp_approvals: off`, `mcp_default_readonly: false` og `mcp_dynamic_rest: true` er alle som de skal være.
- **3/11 2026 stod den stadig `true`** efter ejeren havde slået den fra og gemt. `steamgears-autopilot_update`
  svarer derfor fortsat «This site lets assistants work on drafts only, so this was not done. It would change
  the live site; ask the owner to do it.» — og sitet kører stadig **7.22.0**.
- For at gemme skal man ramme **Save** nederst i AI Engine → Settings → **MCP**. Indstillingen er en React-kontakt
  (`name:"mcp_drafts_only"`), og en side der har stået åben længe kan gemme med et udløbet nonce uden at sige det:
  kontakten bliver stående i den stilling man satte den i, men `mwai_options` ændres ikke.
- **Kontrol der ikke kræver kode:** genindlæs AI Engine-siden. Springer kontakten tilbage til «til», blev den
  aldrig gemt.

## 1. Hvad gaten afviser — læst i labs/mcp-approvals.php

`drafts_refusal()` kaldes i `execute_tool()` **før** værktøjet kører, og afviser alt undtagen tre grupper:

| Gruppe | Indhold | Resultat |
| --- | --- | --- |
| `DRAFT_SAFE` (10) | `wp_create_post`, `wc_create_product`, `wp_upload_media`, `wp_upload_request`, `wp_create_term`, `wp_create_note`, `wp_update_note`, `wp_delete_note`, `mwai_image`, `wp_preview_link` | Tillades, hvis status er `draft`/`pending`/`auto-draft` |
| `POST_TOOLS` (21) | post-, meta-, term-, Elementor- og Woo-felter, fx `wp_update_post`, `wp_alter_post`, `wp_write_blocks`, `wc_update_product`, `wp_update_seo` | Tillades kun hvis posten **ikke** er live, og status ikke ændres til publiceret |
| Alt andet | herunder `steamgears-autopilot_update` **og `wp_update_option`** | Afvises med «It would change the live site; ask the owner to do it.» |

To konsekvenser der er værd at kende:

1. **Agenten kan ikke slå gaten fra selv.** `wp_update_option` er ikke i nogen af grupperne, så den er omfattet af
   den gate den skulle ændre. Det er med vilje, og det er derfor der ikke findes en vej udefra.
2. **Vores eget plugins skrive-abilities er ikke «draft-safe».** `steamgears-autopilot_update` er ikke en
   post-redigering, så den rammer «alt andet». Det gælder også `queue-post`, som ellers kunne have kørt som kladde.

`mcp_approvals` er en separat guard med niveauerne `off`/`deletes`/`site`. Den står på `off` og er ikke i vejen.

## 2. Hvorfor opdateringen ikke kan køres fra agenten — og hvad der så kan

Opdateringsvejen i `includes/updater.php` henter pakken fra en fast URL:

```
https://raw.githubusercontent.com/humle93/steamgears-site/main/plugins/steamgears-autopilot-v<version>.zip
```

Den fil findes: `steamgears-autopilot-v7.25.0.zip` svarer **200, 161 KB** på `main` 3/11 2026, og sitets
`status`-kald rapporterer allerede `latest_on_github: 7.25.0` (så transient-cachen på 6 timer er ikke i vejen).
Tre veje til at få 7.25.0 på sitet:

| Vej | Hvem | Status |
| --- | --- | --- |
| `steamgears-autopilot_update` over MCP | agenten | **Blokeret** af Drafts Only |
| Plugins → Update i wp-admin (WordPress viser 7.25.0 via `inject_update`) | ejeren | Virker, ét klik |
| `wp-admin/admin-post.php?action=sgap_self_update` | ejeren, logget ind | Virker, samme pakke |

Der findes ingen tredje indgang: pluginnet registrerer `/wp-json/sgap/v1/*` til kø, kladder, statistik og feed —
**ikke** en opdateringsrute — og `.env` indeholder ingen application password, så Basic-auth er heller ikke en vej.

## 3. Opskrift til ejeren

1. AI Engine → **Settings** → fanen **MCP**.
2. Slå **Drafts Only** fra.
3. Rul ned og tryk **Save** (ikke kun kontakten).
4. Genindlæs siden. Står den nu som slået fra, er den gemt — og agenten kan køre opdateringen med det samme.
5. Springer den tilbage til «til», er gemmet ikke gået igennem: genindlæs **først** siden (nyt nonce), slå fra, gem.

Indtil da står sitet på 7.22.0, og de felter 7.24.0/7.25.0 indfører (`campaign` som indstilling og pr. indlæg,
samt `angle`/`content_type`/`format` på `queue-post`) er stadig ubrugte.

## 4. Sådan aflæses flaget uden at skrive

`wp_get_option {"key":"mwai_options","raw":true}` returnerer hele optionen; de relevante nøgler er:

```json
{"mcp_drafts_only": true, "mcp_role": "admin", "mcp_approvals": "off", "mcp_default_readonly": false}
```

`raw` og ikke-`raw` gav samme svar 3/11, så en objekt-cache var **ikke** forklaringen her. Er de to forskellige,
er der en stale cache mellem dem — og så er det cache-purge, ikke endnu et gem, der mangler.
