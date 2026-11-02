# MCP Adapter — abilities, værktøjer og brugbarhed

Research 2/11 2026 af hvad WordPress' MCP Adapter reelt kan stille op med, samlet i regnearket
[`mcp-adapter-abilities.csv`](mcp-adapter-abilities.csv) med én række pr. ability og en
brugbarhedsscore fra 1 til 5 for netop dette sites roadmap.

## 1. Sådan eksponeres abilities

Adapteren oversætter WordPress' Abilities API til MCP. To ting styrer hvad der dukker op:

- **Abilities er private som standard.** En ability skal have `meta.mcp.public = true` (eller
  `meta.public = true`) for at blive eksponeret. Derfor kan et site med adapteren aktiv stadig svare
  med meget få værktøjer.
- **Adapteren har kun tre meta-værktøjer.** Den registrerer ikke ét MCP-værktøj pr. ability, men
  lader dig opdage, læse og køre abilities gennem:

| Værktøj | Rolle |
| --- | --- |
| `mcp-adapter/discover-abilities` | find alle offentliggjorte abilities |
| `mcp-adapter/get-ability-info` | læs input/output-skema for én |
| `mcp-adapter/execute-ability` | kør den, med input |

Det betyder at alt skrive-arbejde går gennem **`execute-ability`** — der findes ingen
`update-page`-knap at trykke på. Det er også derfor adapteren kan se fattig ud, selv når sitet har
mange evner.

## 2. Målt på sitet, ikke antaget

| Rute | Svar 2/11 | Betydning |
| --- | --- | --- |
| `POST /wp-json/mcp/mcp-adapter-default-server` | `401 rest_forbidden` | Adapteren er aktiv; den afviser kun uden credential |
| `GET /wp-json/wp-abilities/v1/abilities` | `401 rest_forbidden` | Abilities-API'et findes, men kræver login |
| `GET /wp-json/mcp/v1/servers` | `404 rest_no_route` | Findes ikke |
| `.well-known/mcp/server-card.json` | `404` | Væk — de filer tilhørte Royal MCP |

Der er ingen vej til at liste abilities anonymt. Alt nedenstående om *hvilke* abilities der findes,
kommer derfor fra WooCommerce' og WordPress' egen dokumentation, og er markeret som
`dokumenteret` i regnearket — ikke som verificeret på sitet. Kun ruterne er verificeret.

## 3. Hvad der faktisk kan skrive

**WooCommerce har syv kanoniske abilities** (fra 10.9, default-aktiveret fra 11.3). Sitet kører
11.2.1, så de kan være til stede, men er ikke garanteret slået til:

| Ability | Type | Rammer |
| --- | --- | --- |
| `woocommerce/products-query` | læse | produkter |
| `woocommerce/product-create` | skrive | produkter |
| `woocommerce/product-update` | skrive | produkter |
| `woocommerce/product-delete` | skrive (soft delete som standard) | produkter |
| `woocommerce/orders-query` | læse | ordrer |
| `woocommerce/order-update-status` | skrive | ordrer |
| `woocommerce/order-add-note` | skrive | ordrer |

Dertil ni **udfasede** REST-spejle (`woocommerce/products-list`, `-get`, `-create`, `-update`,
`-delete`, `orders-list`, `-get`, `-create`, `-update`), som fjernes i 11.3. De står i regnearket
med nytte 1, så de ikke bliver brugt ved en fejl.

**Sider og indlæg har ingen ability.** WordPress registrerer ikke page/post-abilities som
offentlige, så adapteren kan ikke redigere en side eller et indlæg. Det er den vigtigste
begrænsning, fordi S2.2 (`/shipping/` + `/returns/`), S2.3 (navigation) og S2.5 (meta på 24
indlæg) alle er side- og indlægsarbejde.

**Men app-passwordet åbner mere end adapteren.** Samme credential virker mod WordPress' egen REST:
`wp-json/wp/v2/pages`, `wp-json/wp/v2/posts`, `wp-json/wp/v2/product` og
`wp-json/wp-abilities/v1/abilities/{navn}/run`. Det er de rækker i regnearket der er markeret
`(REST)` med nytte 5, og de er den realistiske vej til S2-arbejdet. En ting skal testes før vi
regner med det: om Rank Math eksponerer `rank_math_title` og `rank_math_description` i REST-meta
for en bruger med `edit_posts`. Gør den ikke, skal meta sættes et andet sted, og så er Royal MCP
igen den eneste vej.

## 4. Brugbarhedsscoren

Kolonnen `nytte_1_5` er ikke generel kvalitet, men hvor meget abilityen flytter netop dette sites
roadmap:

| Score | Betydning | Rækker |
| --- | --- | --- |
| 5 | Løser en åben roadmap-post direkte | 3 adapter-værktøjer, `products-query`, `product-update`, `wp/v2/pages`, `wp/v2/posts` |
| 4 | Nyttig vej eller fallback | `wp/v2/product`, `wp-abilities/.../run` |
| 3 | Drift og verifikation | `product-delete`, `orders-query` |
| 2 | Sjældent relevant | `product-create`, `order-update-status` |
| 1 | Brug ikke | de ni udfasede REST-spejle, `order-add-note` |

Syv rækker har nytte 5. Det er dem der afgør om adapter-vejen er noget værd: kan `product-update`
og `wp/v2/pages` skrive, er 8 af S2's poster gennemførlige uden Royal MCP.

## 5. Sådan fyldes regnearket med rigtige tal

Regnearket er kurateret viden. Den levende liste laves med to kommandoer, når app-passwordet er sat:

```bash
python3 tools/mcp_rest.py --ask                                  # getpass, skriver .env mode 600
python3 tools/mcp_rest.py --abilities --csv /tmp/abilities-live.csv
```

Den anden kommando skriver hvert værktøj med `navn, skriver, rammer, parametre, beskrivelse`, så
den kan sammenlignes med den kuraterede fil række for række. Kommandoen findes allerede som
`--abilities` (JSON) og nu også med `--csv`.

## 6. Kilder

- [WordPress/mcp-adapter](https://github.com/WordPress/mcp-adapter) — de tre meta-værktøjer og
  `meta.public`-modellen.
- [Abilities API i WordPress 6.9](https://make.wordpress.org/core/2025/11/10/abilities-api-in-wordpress-6-9/)
  — registrering, REST-ruter og app-password som auth.
- [WooCommerce 10.9: kanoniske abilities](https://developer.woocommerce.com/2026/05/12/mcp-abilities-api-10-9/)
  — de syv ability-navne, soft delete og `mcp.public`.
- [WooCommerce 11.3: MCP-adapter-opdatering](https://developer.woocommerce.com/2026/10/21/woocommerce-11-3-mcp/)
  — de udfasede REST-spejle og hvad der fjernes.
