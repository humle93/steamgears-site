# WooCommerce-opdatering på steamgears.eu — tjekliste før og efter

3/11 2026. Skrevet fordi Woo-updates er den eneste ændring på sitet der både kan **fjerne værktøjer fra
MCP-listen** (se [driftsnoten om de ni spejle](woo-11-3-vaerktoejsliste-2026-11-02.md)), **røre
produkt-URL'er og sitemap** og **kræve en databasebackup**. Tjeklisten er lavet så samme kommando køres før
og efter, og så forskellen kan forklares med tal i stedet for med en fornemmelse.

**Hvem gør hvad:** sitet ligger hos Simply, og ingen i dette workspace kan opdatere det — opdateringen køres i
wp-admin (eller over MCP når der er et credential, hvilket der ikke er). Tjeklisten her er det arbejde der
ligger **før** og **efter** selve klikket, og det kan køres herfra for de kolonner der ikke kræver login.

## 1. Før opdateringen — uden credential (kan køres nu)

| # | Måling | Kommando | Hvorfor | Baseline 3/11 2026 |
| --- | --- | --- | --- | --- |
| F1 | Antal udgivne produkter | `curl -sSI 'https://steamgears.eu/wp-json/wc/store/v1/products?per_page=1' \| grep -i x-wp-total` | Woo-ændringer i produktstatus og lager viser sig først her | `x-wp-total: 56` |
| F2 | Antal produkt-URL'er i sitemap | `curl -sS https://steamgears.eu/product-sitemap.xml \| grep -c '<loc>'` | `seo-engine` og Woo deler sitemap; en Woo-opdatering kan ændre hvad der medtages | 57 `loc` |
| F3 | Butikssiden svarer og er hurtig nok | `curl -sS -o /dev/null -w '%{http_code} %{time_total}s\n' https://steamgears.eu/shop/` | Woo er den tungeste plugin på forsiden af butikken | `200` · 2,51 s · 462 KB |
| F4 | Store API'et svarer | `curl -sS -o /dev/null -w '%{http_code}\n' https://steamgears.eu/wp-json/wc/store/v1/cart` | Kurven er den rute en kunde rammer først; offentlig, kræver intet login | `200` |
| F5 | REST-namespaces | `curl -sS https://steamgears.eu/wp-json/ \| python3 -c "import sys,json;print(sorted(json.load(sys.stdin)['namespaces']))"` | Beviser at `wc/v3`, `wc/store/v1`, `sgap/v1`, `mcp/v1` og `wp-abilities/v1` stadig registreres | 46 namespaces, alle fem til stede |
| F6 | `wc/v3` afviser anonymt | `curl -sS -o /dev/null -w '%{http_code}\n' 'https://steamgears.eu/wp-json/wc/v3/products?per_page=1'` | Skal blive `401 woocommerce_rest_cannot_view` — bliver den `200`, er der åbnet en dør | `401` |
| F7 | Nyeste Woo-version og krav | `curl -gsS 'https://api.wordpress.org/plugins/info/1.2/?action=plugin_information&request[slug]=woocommerce'` | Versionen der opgraderes **til**, `requires_php` og `tested` | 11.2.1 · PHP 7.4+ · testet til WP 7.1.4 |

## 2. Før opdateringen — med credential (kan ikke køres herfra)

| # | Måling | Hvorfor |
| --- | --- | --- |
| F8 | Installeret Woo-version og om den er **aktiv** | Triage-dokumentet har en uløst modstrid: snapshot 23/10 siger inaktiv 11.2.1, men Store API og `printify-for-woocommerce` svarer som om butikken kører. Afklar i wp-admin → Plugins, før noget opdateres. |
| F9 | MCP `tools/list`: total og `--split` | `python3 tools/mcp_rest.py --split` — gem `total` og `counts`. 206 er den dokumenterede baseline; falder den med op til ni, er de udfasede spejle væk. |
| F10 | HPOS-status (High-Performance Order Storage) | Skifter Woo dette under opdateringen, flyttes ordredata. Det skal være en **beslutning**, ikke en bivirkning af et klik. |
| F11 | Antal ordrer og ordrestatus-fordeling | Den eneste måde at se om ordredata kom helskindet igennem. |
| F12 | Aktive betalings- og forsendelsesmetoder | Woo-opdateringer kan deaktivere gateways der ikke er kompatible; opdages først ved næste køb. |
| F13 | Woo-indstillinger der styrer adfærd | Valuta, moms, lager, gæste-checkout, sidevalg (shop/cart/checkout/my-account). Gem et screenshot — det er billigere end at rekonstruere. |

## 3. Selve opdateringen

1. **Databasebackup først.** Woo migrationer kan ikke rulles tilbage med filer alene.
2. **Én plugin ad gangen, Woo først** — og derefter satellitterne (`printify-for-woocommerce`,
   `pinterest-for-woocommerce`, `woocommerce-services`), så en fejl kan henføres. Rækkefølgen for de øvrige
   16 plugins står i [triage-dokumentet](plugin-opdateringer-triage-2026-11-02.md) §6.
3. **Ikke midt i en kampagne.** Køen for Clockwork Punk kører 3.–14/11
   ([kampagneplanen](clockwork-punk-kampagne-2026-11-02.md)); en Woo-opdatering der tager butikken ned i
   ti minutter, eller som ændrer produkt-URL'er, rammer de planlagte poster.
4. **Forvent faldet i værktøjslisten.** Forsvinder de ni spejle, er det den ventede konsekvens — ikke en fejl,
   og der skal ikke bygges erstatninger.
5. **Kør ikke bulk-rename eller medieoprydning i samme vindue.** `media-file-renamer` flytter filer og dermed
   URL'er, og det arbejde skal kunne henføres.

## 4. Efter opdateringen — samme syv målinger

Kør F1–F6 igen og sammenlign med kolonnen fra 3/11. Acceptkriterierne:

| Måling | Godkendt hvis | Hvis ikke |
| --- | --- | --- |
| F1 produkter | 56 ± 0, medmindre sortimentet blev ændret med vilje | Produkter forsvundet eller nyheder fra Woo → undersøg produktstatus og synlighed |
| F2 sitemap | 57 ± 0, og ingen `loc` peger på en 404 | Kør crawlen i [`../roadmap.md`](../roadmap.md) S0 og se hvilke URL'er der døde |
| F3 `/shop/` | `200` og under ~6 s | Cache og Woo-CSS; en tom butiksside er en blokerende fejl |
| F4 kurv | `200` | Store API nede = butikken kan ikke sælge. Rul tilbage |
| F5 namespaces | Alle fem stadig til stede | Mangler `wc/v3` eller `wc/store/v1`, er registreringen brudt |
| F6 `wc/v3` anonymt | Stadig `401` | `200` betyder åben adgang — luk med det samme |
| F9 MCP-liste | `total` 206 → ca. 197, `ability_bridge` faldet med op til ni, **vores 32 og AI Engines 74 uændrede** | Falder vores egne eller AI Engines tal, er det ikke spejlene men noget andet |

Derudover, med credential: F10–F12 sammenlignet med før, plus én gennemført testordre i testtilstand hvis
betalingsmetoderne har ændret sig.

## 5. Hvornår der rulles tilbage

- Butikssiden eller kurven svarer ikke `200`.
- Produkter eller ordrer er væk (F1 eller F11 afviger uden forklaring).
- Betalingsmetoder er deaktiveret og kan ikke genaktiveres.
- `wc/v3` eller `wc/store/v1` er afregistreret.

Alt andet — herunder de ni spejle der forsvinder — er **forventet adfærd** og rettes i dokumentationen, ikke
med en rollback.

## 6. Hvad der ikke kan måles herfra

1. Installeret version og aktiv/inaktiv-status (F8) — kræver wp-admin eller credential.
2. MCP-værktøjslisten (F9) — `/wp-json/wp-abilities/v1/abilities` svarer `401` anonymt, og `tools/mcp_rest.py`
   stoppede med »ingen credential« den 3/11.
3. HPOS, ordrer, betalingsmetoder og Woo-indstillinger (F10–F13).
4. Om `printify-for-woocommerce` og Woo-opdateringen faktisk er kompatible — det står i deres changelogs, og
   den kontrol hører i opdateringsvinduet, ikke i denne fil.

**Om F1-tallet:** Store API svarede 56 udgivne produkter den 3/11, mens produktdokumenterne taler om 39
designs. Forskellen er ikke forklaret her. Tallet duer som **anker** for før/efter — det er den samme måling
hver gang — men det er ikke en facitliste over sortimentet.
