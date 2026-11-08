# Butiksfund 2/11 2026 — tees uden størrelsesvalg

**Kort:** ni af de publicerede tees kan ikke sælges med størrelse. De er `simple` produkter uden
variationer, mens deres produkttekster lover S–2XL. Hoodierne er `variable` og virker. Det er den
største enkeltfejl i butikken lige nu, og den kan ikke rettes sikkert uden Printful-mapping.

## Bevis

Frontend, hentet 2/11 2026:

| Produkt | `variations_form` | Størrelsesværdier i HTML | Produkttype |
| --- | --- | --- | --- |
| `/product/the-clockwork-seer-tarot-steampunk-unisex-t-shirt/` | nej | ingen | `simple` |
| `/product/hummingbird-automaton-steampunk-unisex-t-shirt/` | nej | ingen | `simple` |
| `/product/airship-storm-engraving-steampunk-unisex-hoodie/` | **ja** | S, M, L, XL, 2XL | `variable` |

Alle 9 tees (553, 555, 557, 559, 561, 563, 565, 567, 619) er `simple` til $26.95. Alle 12 hoodies
(391–413) er `variable` til $49.95 og har fungerende størrelsesvalg. Hats og goggles er `simple`
med rette — de er one size.

Samtidig lover tee-teksterne størrelser: måletabellen i beskrivelsen går fra S til 2XL, og
`/size-guide/` beskriver de samme mål. En kunde, der lægger en tee i kurven, får ingen størrelse at
vælge og ved ikke, hvad der bliver sendt.

## Hvorfor den ikke er rettet automatisk

Værktøjerne findes (`wc_set_product_attributes`, `wc_create_variation`, `wc_batch_update_variations`),
men **de virker ikke på et `simple` produkt**: WooCommerce afviser med `Product 953 is not a variable
product`, og MCP kan ikke skifte produkttypen. Se `tee-varianter-spec-2026-11-02.md` for de fem
forsøg og for den færdige spec. Uanset det tekniske er den egentlige blokering:


1. **Ingen POD-connector er aktiv.** `wp_get_plugins` viser 38 plugins; den eneste Printful-relaterede
   er *Printify Shipping Method 3.2*. Der er ingen Printful-fulfilment-plugin, som kan mappe en
   variation til en Printful-variant.
2. **Uden mapping kan ordren ikke routes.** En variation med SKU `SG-V3-10-S` findes ikke i Printful.
   Kunden kan betale, og ordren strander som manuel behandling — værre end en tee uden størrelse,
   fordi pengene er trukket.
3. **Det kræver ejerens Printful-konto** at hente de rigtige variant-ID'er og priser pr. størrelse.
   Det er den samme blokering som Herbarium-linjen (12 kladder, alle `simple`).

## Anbefalet rækkefølge

1. Beslut fulfilment: Printful-appen (mapping pr. variation) eller manuel ordrebehandling.
2. Med appen: opret S–2XL som variationer på de 9 tees **efter** mapping, ikke før.
3. Uden appen: skriv i tee-teksten, at størrelse aftales efter køb — det er ærligt, men koster
   konvertering og giver flere returer.
4. Publikér Herbarium (953–956, 965–968, 969–972) i samme arbejdsgang; de har samme problem.

## Rettelse til tidligere dokumentation

`../seo.md` §5c hævdede, at art prints og telefoncovers lå under "T-Shirts".
Det er **forkert**. Kontrolleret 2/11:

- 953–956 (tees) → `gaslight`, `t-shirts`
- 965–968 (art prints) → `gaslight`, `posters-prints`, `vintage-botanical-prints`
- 969–972 (covers) → `gaslight`, `gothic-steampunk-phone-cases`

Kategorierne er altså korrekte. Det, der reelt mangler på de 12, er varianter og Printful-mapping.
`gaslight` er en tom kategori uden produkter; den kan ryddes op i admin, men gør ingen skade.

## Ryddet op i samme runde

| Handling | Resultat |
| --- | --- |
| Private designsider 692, 870, 871 → skraldespand | Staging-rester væk fra URL-rummet |
| Kladde-side 72 (duplikat Privacy Policy) → skraldespand | Én legal side mindre at tage fejl af |
| `digital-downloads` (tom kategori) → `noindex` via Rank Math term-meta | Verificeret: `follow, noindex` på frontend |

Alt er reversibelt: skraldespanden er ikke tømt, og term-meta kan fjernes igen.
