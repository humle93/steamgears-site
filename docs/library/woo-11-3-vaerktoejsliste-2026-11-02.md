# Woo 11.3 og de ni REST-spejle — hvad der forsvinder fra værktøjslisten

Driftsnote 2/11 2026. Kort version: **ni Woo-værktøjer kan forsvinde fra MCP-listen ved en
WooCommerce-opdatering, og vi skal ikke gøre noget — men vi skal kende faldet i tal, før vi kalder det en fejl.**

## Hvad der er på vej ud

`src/Internal/Abilities/AbilitiesRestBridge.php:35-89` registrerer ni abilities som spejle af de gamle
`/wc/v3`-ruter:

| Ability | Rute |
| --- | --- |
| `woocommerce/products-list`, `-get`, `-create`, `-update`, `-delete` | `/wc/v3/products` |
| `woocommerce/orders-list`, `-get`, `-create`, `-update` | `/wc/v3/orders` |

De sætter `'meta' => [ 'show_in_rest' => true, 'expose_in_deprecated_woocommerce_mcp' => true ]`
(`REST/RestAbilityFactory.php:75`). Woo kalder dem selv *deprecated* i koden — metadata-nøglen og
`MCPAdapterProvider::should_include_ability_by_default()` findes netop for at holde dem ude af Wos egen
adapter, så de kan fjernes uden at røre de syv rigtige værktøjer.

## Hvorfor det rammer vores liste

AI Engines bro eksponerer alt med `meta.mcp.public === true` **eller** ikke-tomt `meta.show_in_rest`
(`labs/mcp-abilities.php:42-48`). Spejlene har ikke `mcp.public`, men de har `show_in_rest` — så de går
gennem broen alligevel og optræder i dag som `woocommerce_products-list`, `woocommerce_orders-get` osv.

Ved opdateringen forsvinder de fra `tools/list`. Det er **op til ni værktøjer**, altså falder 206-baselinen
til omkring **197**, og `ability_bridge`-spanden falder tilsvarende. Vores 32 og AI Engines 74 rører sig ikke.

## Hvad der er sikkert, og hvad der ikke er

- **Sikkert:** spejlene er markeret som udfasede i kilden, og de syv kanoniske abilities
  (`product-create`, `product-update`, `product-delete`, `products-query`, `orders-query`,
  `order-update-status`, `order-add-note`) med `mcp.public = true` bliver, hvor de er.
- **Ikke sikkert:** *hvornår*. Regnearket og den tidligere research skrev »fjernet i 11.3«, men der står
  intet `11.3` og ingen `@deprecated`-tag i 11.2.1's abilities- eller MCP-kode, og 11.3 er ikke udgivet:
  wordpress.orgs plugin-API svarede **11.2.1** den 2/11 2026 — samme version som sitet kører. Behandl
  versionstallet som en forventning, ikke som en dato vi har målt.

## Hvad vi gør ved næste Woo-opdatering

1. Før opdateringen: `python3 tools/mcp_rest.py --split` og gem `counts` + `ability_bridge`.
2. Efter opdateringen: kør den igen. Falder `ability_bridge` med op til ni, er spejlene væk som ventet —
   det er ikke en fejl, og der skal ikke bygges erstatninger.
3. Ret 206-baselinen i [`mcp-vaerktoejer-2026-11-02.md`](mcp-vaerktoejer-2026-11-02.md) og notér den nye
   total med dato, så næste sammenligning ikke ser et udsving der ikke er der.
4. Rækkerne i [`mcp-adapter-abilities.csv`](mcp-adapter-abilities.csv) bliver stående med nytte 1 — de er
   allerede markeret som udfasede, så ingen bruger dem ved en fejl.

Der er intet at rette i koden: vi kalder ingen af spejlene, og vores egne 32 abilities er upåvirkede.
Kontrollen kræver det samme credential som resten af målingerne — `/wp-json/wp-abilities/v1/abilities`
svarer stadig 401 anonymt, så listen kan ikke tjekkes uden login.
