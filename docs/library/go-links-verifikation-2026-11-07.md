# /go/-verifikation 7/11 2026 — ruten virker, men 21 produktlinks er døde

Opfølgningen på S1.5 måler alle trackinglinks efter ejerens rewrite-flush. Den er read-only og
ændrer intet på sitet.

**Kort svar:** `/go/`-ruten virker nu. Alle **31 unikke** links svarer **302**. Men kun **10** ender på
en live produktside; **21** ender på `?post_type=product&p=<id>` med **HTTP 404**, fordi produkt-ID'et
ikke længere findes. Det er en katalogfejl, ikke en routingfejl.

## Metode

- `GET /wp-json/wp/v2/posts?per_page=100&_fields=id,slug,link,title,content` med neutral
  `steamgears-link-audit/1.0`-UA gav **30 publicerede indlæg** og **31 unikke `/go/<id>/`** i
  **53 forekomster**.
- Hvert `/go/<id>/` blev kaldt **uden** redirect-følgning. Ved 302 blev `Location` fulgt manuelt, indtil
  svaret ikke længere var en redirect; først derefter blev status, sidetitel og canonical læst.
- Butikkens aktuelle katalog blev læst gennem `GET /wp-json/wc/store/v1/products?per_page=100`:
  **20 publicerede produkter**.
- Målt **7/11 2026**. Alle tal er fra denne ene kørsel; intet er overtaget fra 6/11-målingen.

## Resultat

| Udfald | Unikke IDs | Forekomster |
| --- | ---: | ---: |
| 302 → live produktside 200 | **10** | **18** |
| 302 → `?post_type=product&p=<id>` 404 | **21** | **35** |
| 200 → `/journal/` (den gamle fejl) | **0** | **0** |
| **I alt** | **31** | **53** |

Rewrite-fixet er dermed bekræftet: **0 links** ender i journalen, og **31/31** har en `Location`.
Tilbage er 21 døde produkt-ID'er. De står i den næste tabel.

## De 21 døde IDs

| ID | Linktekst på siden | Indlæg | Forekomster |
| ---: | --- | ---: | ---: |
| 393 | Compass Mandala Tee | 699 | 1 |
| 395 | Sun Compass Tee | 699 | 1 |
| 405 | Clockwork Owl Hoodie | 699, 615 | 2 |
| 409 | Gear Corset Lady Hoodie | 699 | 1 |
| 411 | Clockwork Heart Tee | 699 | 1 |
| 475 | Steampunk Aviator Goggles | 699 | 3 |
| 479 | Vintage Driver Goggles | 699, 805 | 5 |
| 481 | Steampunk Top Hat with Removable Goggles | 699, 8 | 3 |
| 485 | Gothic Bowler Top Hat with Goggles | 699 | 1 |
| 487 | Victorian Corset Top – Brown | 699 | 3 |
| 497 | Medieval Assassin Coat | 699 | 2 |
| 555 | Nautilus Voyage Tee | 699 | 1 |
| 561 | Cabinet of Curiosities tee | 803 | 1 |
| 563 | Ink and Steam Muse Tee | 699, 615 | 2 |
| 721 | Kraken Widow print | 988 | 1 |
| 728 | First Mate's Oath print | 988 | 1 |
| 766 | utility belt and holster | 805 | 1 |
| 767 | Victorian corset blueprint PDF | 806 | 1 |
| 809 | Steampunk costume checklist | 806, 8 | 2 |
| 942 | Ink and Steam bracelet | 804 | 1 |
| 968 | Nightshade Apothecary print | 804 | 1 |

**Post 699** (`steampunk-outfit-ideas-for-women`) står for 17 af de 21 døde IDs og langt de fleste
forekomster. Det er den vigtigste enkeltrettelse.

## De 10 links der virker

| ID | Linktekst | Endelig produktside | Bemærkning |
| ---: | --- | --- | --- |
| 401 | Whale Airship Tee | `/product/whale-airship-hoodie/` | **Forkert produkttype:** siden er nu en hoodie, ikke en tee |
| 477 | Copper Pilot Goggles | `/product/copper-pilot-goggles/` | OK |
| 483 | Time Traveler Top Hat | `/product/time-traveler-top-hat/` | OK |
| 489 | Steampunk Overbust Corset — Leather Lace-Up | `/product/leather-overbust-corset/` | OK |
| 491 | Gothic Overbust Corset — Silver | `/product/silver-overbust-corset/` | OK |
| 493 | Wine Red Velvet Tailcoat | `/product/wine-velvet-frock-coat/` | OK |
| 495 | Victorian Vampire Tailcoat — Brocade | `/product/brocade-tailcoat/` | OK |
| 557 | Airship Captain Tee / Airship Armada Engraved Tee | `/product/airship-armada-tee/` | Samme produktfamilie; linkteksten varierer |
| 940 | Clockwork Seer pendant | `/product/clockwork-seer-pendant/` | OK |
| 941 | Aeronaut pocket watch | `/product/the-aeronaut-pocket-watch/` | OK |

Ni af de ti rammer den produkttype, linkteksten lover. **401** gør ikke: teksten siger “Tee”, men
produktet er en hoodie. Det skal rettes som tekst eller link, ikke bare omdirigeres.

## Hvad det betyder

- **Ruten er ikke længere problemet.** `SGAP_Tracking::redirect()` slår op og sender videre; den gamle
  200/journal-fejl er væk efter rewrite-flushet.
- **Produktkataloget er problemet.** De 21 ID'er findes ikke i det aktuelle katalog på 20 produkter.
  Enten er produkterne slettet/trashed under oprydningen, eller ID'erne er fra en ældre katalog.
- **En 404 er ikke en tom omdirigering.** Kunden ser en fejlside, ikke et produkt. Det er derfor
  vigtigere end den tidligere 200-side: fejlen er nu synlig og målbar.
- **Post 699 bærer hovedparten.** Selv om alle 31 links teknisk set redirecter, er 17 af dem døde.

## Næste skridt

1. **Afgør om de 21 produkter skal gendannes eller erstattes.** Ligger de i WooCommerce-trash, kan en
   gendannelse være nok. Er de bevidst fjernet, skal linkene erstattes.
2. **Ret post 699 først.** 17 døde IDs i én guide er den største enkeltgevinst; brug linkteksten i
   tabellen som facit for, hvilket produkt der var ment.
3. **Erstat kun med et produkt, der faktisk findes i kataloget.** Der er 20 publicerede produkter;
   mange af de gamle navne (Kraken Widow, Nightshade Apothecary, Ink and Steam bracelet) har ingen
   direkte afløser. Så skal linket fjernes eller teksten omskrives — ikke pege på et tilfældigt produkt.
4. **Ret 401's tekst** fra “Whale Airship Tee” til hoodie, eller peg på en tee.
5. **Mål igen** med samme metode: 31/31 302 er ikke nok; målet er 31/31 **200 på en produktside**.

## Filer

- S1.5-dækningsmålingen 6/11:
  [`produktlink-daekning-2026-11-06.md`](produktlink-daekning-2026-11-06.md).
- Roadmap-punktet: [`../roadmap.md`](../roadmap.md) §S1.5.
- PDF: `reports/go-links-verifikation-2026-11-07.pdf`.
