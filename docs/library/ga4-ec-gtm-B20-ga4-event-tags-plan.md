# B20-21 — GA4 e-commerce Event-tags i GTM-54M3JCPM + shim-fjernelse

## Fakta (verificeret 27/10)
- Shim ligger i WP header template-part (`wp_template_part` slug "header", wp_id 12, Extendable), som `wp:html`-block markeret `GA4 e-commerce fallback (Juno) — FJERNES når GA4 Event-tag er published i GTM-54M3JCPM`.
- Shim: poller `window.dataLayer` hvert 400 ms, matcher EC-events (view_item, add_to_cart, purchase m.fl.), buffer max 14, videresender via `window.gtag('event', …, {send_to:'G-3MYLFKY8MY'})` kun ved consent `statistics` (Complianz).
- Målinger: G-3MYLFKY8MY (property 559297232). OBS: 559271833 samler stadig dobbelt — Site Kit-skrivning kræver Henning-OAuth (separat punkt).
- Gammel Ads-conversion "Kurv" i GTM skal pauses/slettes samme runde.

## GTM-trin (Henning, klik-punkter)
1. tagmanager.google.com → container **GTM-54M3JCPM** → Workspace "Default".
2. **Variables** → Built-in Variables → Configure → aktivér **Event**.
3. Variables → New → type **Constant**, navn `GA4 - Measurement ID`, værdi `G-3MYLFKY8MY`. Save.
4. **Triggers** → New (3 stk, type **Custom Event**, "All Custom Events" → radioknappen "Some Custom Events" → præcis navn):
   - `view_item` · navn: "CE – view_item"
   - `add_to_cart` · navn: "CE – add_to_cart"
   - `purchase` · navn: "CE – purchase"
5. **Tags** → New → type **Google Analytics: GA4 Event** (pr. event):
   - Measurement ID: `{{GA4 - Measurement ID}}`
   - Event Name: `view_item` / `add_to_cart` / `purchase`
   - Flueben **Send Ecommerce data** → Data source: **Data Layer**
   - Trigger: tilsvarende CE-trigger. Navne: "GA4 EC – view_item" osv.
6. (Rensning) Tags → find gammel Ads-conversion "Kurv" → Pause/Delete.
7. **Submit** → versionsnavn `GA4 EC events v1` → **Publish**.
8. **Verificering:** Preview (Tag Assistant) → åbn steamgears.eu produktside → check view_item-tag fired m. ecommerce.items → add_to_cart (AJAX-event) → add_to_cart fired. Purchase verificeres ved næste live-ordre (B21, DebugView).

## Fjernelses-rækkefølge (undgå dobbelt-tælling)
1. Publish GTM-tags (trin 7) — samme dag som fjernelse.
2. Verificér i Preview + GA4 Realtime/DebugView at tags fyres.
3. Juno fjerner shim-block fra header template-part via WP REST (revision gemmes automatisk).
4. Juno pauser/sletter Ads-"Kurv"-tag er allerede sket i trin 6; ellers gøres det her.
5. Cache: W3TC flush + CDN TTL-bust (script 11).
6. Verificér: view-source på forside/produktside → "GA4 e-commerce fallback" kommentar væk.
7. 24-48 t efter: GA4 Events-rapport sanity-check (ingen spike fra overlap).
8. B21: DebugView-verifikation af view_item → purchase ved næste live-ordre.

Overlapvindue mellem publish og shim-fjernelse bør holdes < timer — begge sender til samme property, så alle EC-events tælles dobbelt i overlapet.
