# Størrelsesvarianter på de ni tees — spec og blokering

**Konklusion først:** varianterne kan **ikke** oprettes via MCP. WooCommerce nægter at lave variationer
på et `simple` produkt, og MCP-fladen har intet værktøj, der skifter produkttypen. Spec'en nedenfor er
derfor lavet klar til at blive ført ind i WP-admin eller importeret som CSV.

## Hvorfor det blokerer

| Forsøg | Resultat |
| --- | --- |
| `wc_set_product_attributes` med `size` S–2XL, `variation: true` på en tee | Lykkes, men produktet bliver **stadig `simple`** |
| `wc_create_variation` på samme produkt | `invalid_args: Product 953 is not a variable product (type: simple)` |
| `wc_update_product` med `type: variable` | Afvises: `No update fields provided. Pass at least one of: name, description, ...` — `type` er ikke en parameter |
| `wp_add_post_terms` med `taxonomy: product_type, terms: [variable]` | Tilføjer termen **ved siden af** `simple` (append-only), og `get_type()` læser stadig `simple`, fordi den tager den første term alfabetisk |
| Fjern termen igen | Der findes intet fjern-term-værktøj; `wp_delete_term` sletter termen globalt og ville ramme alle `simple` produkter |

`wc_create_product` **har** en `type`-parameter, så et nyt variabelt produkt kan oprettes — men et nyt
produkt får nyt ID og ny slug, og de ni tees er linket fra 30 indlæg. At udskifte dem ville betyde
døde links og tabte ordrehistorik. Det er ikke gjort.

**Bivirkning, som skal nævnes:** testen på kladden 953 (`Clockwork Rose — The Clockwork Herbarium Tee`)
efterlod termen `variable` ved siden af `simple`. Den er inert nu (typen læses som `simple`, produktet er
ikke publiceret), og den forsvinder af sig selv, første gang 953 gemmes i WP-admin, fordi WooCommerce
dér skriver produkttypen med replace.

## Hvad varianterne skal indeholde

Formen er kopieret fra hoodierne (391–413), som er de eneste vareprodukter der virker i dag:
et **custom** attribut kaldet `size` (ikke en global `pa_size` — der findes ingen globale attributter
på sitet), med værdierne `S`, `M`, `L`, `XL`, `2XL`, og én variation pr. værdi.

| Parent | Design | SKU-base | Størrelser | Pris |
| --- | --- | --- | --- | --- |
| 553 | Hummingbird Automaton — Natural History Tee | `SG-V3-01` | S, M, L, XL, 2XL | 26.95 |
| 555 | Nautilus Voyage — Dark Academia Tee | `SG-V3-02` | S, M, L, XL, 2XL | 26.95 |
| 557 | Airship Armada — Vintage Engraving Tee | `SG-V3-03` | S, M, L, XL, 2XL | 26.95 |
| 559 | Clockwork Fox — Natural History Tee | `SG-V3-04` | S, M, L, XL, 2XL | 26.95 |
| 561 | Cabinet of Curiosities — Dark Academia Tee | `SG-V3-05` | S, M, L, XL, 2XL | 26.95 |
| 563 | Ink and Steam Muse — Dark Academia Tee | `SG-V3-07` | S, M, L, XL, 2XL | 26.95 |
| 565 | Chrono-Wing Dragonfly — Natural History Tee | `SG-V3-08` | S, M, L, XL, 2XL | 26.95 |
| 619 | Clockwork Tea Robots — Whimsigoth Tee | `SG-V3-09` | S, M, L, XL, 2XL | 26.95 |
| 567 | The Clockwork Seer — Tarot Witchy Tee | `SG-V3-10` | S, M, L, XL, 2XL | 26.95 |

SKU pr. variation: `<base>-<størrelse>`, altså `SG-V3-01-S`, `SG-V3-01-M` … `SG-V3-01-2XL`. 45 rækker i alt.
Størrelserne er skrevet som hoodierne gør det (`2XL`, ikke `XXL`), så filters og `/size-guide/` stemmer.

## Sådan gøres det i WP-admin

Pr. produkt, cirka 3 minutter:

1. **Produkter** → åbn teen → **Produktdata** → vælg **Variable product** (øverst i dropdown).
2. Fanen **Attributter** → tilføj attributten `Size` (custom attribute) med værdierne
   `S | M | L | XL | 2XL` → sæt flueben i **Used for variations** → **Save attributes**.
3. Fanen **Variationer** → **Generate variations** (alle kombinationer).
4. Vælg alle rækker → **Bulk actions → Set regular price → 26.95** → Apply.
5. Sæt SKU pr. række efter tabellen ovenfor → **Save changes**.

Alternativt: WooCommerce → **Produkter → Import** med en CSV bygget på tabellen (kolonnerne `Parent`,
`SKU`, `Attribute 1 name`, `Attribute 1 value(s)`, `Regular price`, `Published`). CSV'en er ikke lagt i
repoet, fordi importformatet ikke kan verificeres herfra, og en forkert header giver en tavs fejlimport.

## Rækkefølgen afhænger af routing

- **Printful-appen på sikt:** appen opretter og mapper selv varianterne, når den synkroniserer produktet.
  Så skal der **ikke** oprettes varianter manuelt først — de ville blive dubletter.
- **Manuel ordrebehandling (Printful uden app):** varianterne skal oprettes som ovenfor, ellers ved
  hverken du eller kunden, hvilken størrelse der er bestilt. Det er den vej spec'en er lavet til.

## Sekundært fund: hoodiernes SKU'er er ikke unikke

Alle fem variationer på et hoodie har **samme** SKU (`SG-HOOD-23` for S, M, L, XL og 2XL på produkt 391).
Det er ikke fatalt for Printful, som mapper på variations-ID, men det gør pluklister, lager og
fejlsøgning på ordrer upræcise — en ordre på `SG-HOOD-23` siger ikke hvilken størrelse der skal trykkes.
`wc_update_variation` understøtter `sku`, så det kan rettes til `SG-HOOD-23-S` … `-2XL` uden risiko.
Det er ikke gjort her, fordi det ikke var en del af opgaven.
