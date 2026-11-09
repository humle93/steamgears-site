# Priser i den levende butik — målt 9/11 2026

Grundlaget for hver pris i `docs/library/guides-*.md`. Tallene er læst i butikken selv gennem Store API
9/11 2026, ikke overført fra en tidligere pristabel:

```bash
curl -s 'https://steamgears.eu/wp-json/wc/store/v1/products?per_page=100' \
  | jq -r '.[] | "\(.id) \(.name) \(.prices.price) \(.prices.currency_code)"'
```

Butikken prissætter i **DKK** (`currency_code: DKK`, `currency_minor_unit: 2`), og `/shop/` indeholder
**0** `$` og **66** `kr.`. Kataloget har **20 publicerede produkter** (`x-wp-total: 20`). En pris der ikke
står her, findes ikke i butikken — og en guide må ikke bruge et `/go/<id>/`, som butikken svarer 404 på.

| ID | Produkt | Pris |
| ---: | --- | ---: |
| 413 | Airship Storm — Vintage Engraving Hoodie | 335,00 |
| 403 | Mechanical Elephant — Curiosity Hoodie | 335,00 |
| 401 | Whale Airship — Vintage Voyage Hoodie | 335,00 |
| 399 | Clockwork Octopus — Curiosity Hoodie | 335,00 |
| 557 | Airship Armada — Vintage Engraving Tee | 180,00 |
| 954 | Brass Luna Moth — Clockwork Herbarium Tee | 530,00 |
| 1255 | Brass Fox Automaton — Steampunk Tee | 530,00 |
| 1282 | Astrolabe Planisphere — Steampunk Tee | 530,00 |
| 477 | Copper Pilot Goggles — Vintage Windproof | 165,00 |
| 483 | Time Traveler Top Hat with Goggles | 335,00 |
| 489 | Steampunk Overbust Corset — Leather Lace-Up | 400,00 |
| 491 | Gothic Overbust Corset — Faux Leather, Silver | 435,00 |
| 493 | Wine Red Velvet Tailcoat — Victorian Frock Coat | 435,00 |
| 495 | Victorian Vampire Tailcoat — Brocade | 265,00 |
| 965 | Clockwork Rose — Herbarium Art Print | 395,00 |
| 966 | Brass Luna Moth — Herbarium Art Print | 395,00 |
| 967 | The Raven Chronometer — Herbarium Art Print | 395,00 |
| 970 | Brass Luna Moth — Herbarium Phone Case | 330,00 |
| 940 | Clockwork Seer — Brass Pocket Watch Pendant | 865,00 |
| 941 | The Aeronaut — Brass Skeleton Pocket Watch | 1.000,00 |

## Hvad der ændrede sig siden 2.–3/11

- **Valutaen:** butikken kørte USD, da copyen blev skrevet (`$24.95` osv.). Den kører nu DKK, så hvert
  beløb i de gamle filer var forkert for kunden — ikke fordi priserne var gået op, men fordi de var i en
  anden valuta.
- **Kataloget:** 39 publicerede produkter er blevet 20. Tolv af de atten `/go/`-id'er som guide-copyen
  brugte, svarer nu `woocommerce_rest_product_invalid_id` (404). De findes ikke længere, og et link til
  dem kan ikke repareres — kun erstattes.
- **Derfor** læser `tools/guide_check.py` denne fil og ikke de gamle prislister, og den slår hvert
  `/go/<id>/` op i butikken. Målingen 9/11 står i
  [`go-links-og-guide-copy-2026-11-09.md`](go-links-og-guide-copy-2026-11-09.md).
