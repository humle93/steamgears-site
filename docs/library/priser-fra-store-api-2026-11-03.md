# Priser målt i den levende butik — 3/11 2026

Link-planen for de ældre guides havde fyrre rækker med et kortlagt produkt-ID, men otte af dem kunne ikke få en
pris: produkterne blev udgivet 2/11 2026 og stod ikke i
[`valuta-og-priser-2026-11-02.md`](valuta-og-priser-2026-11-02.md). De otte rækker stod derfor med `—` og status
`pris mangler`. Det var ikke en reel mangel — prisen findes, den stod bare ikke i repoet.

**Den 3/11 2026 blev alle priser målt i den levende butik** gennem Store API, som er offentlig læsning uden
credential. Ingen pris i planen er nu gættet, og ingen række er uden pris.

## 1. Kilden

```bash
curl -sS -A 'Mozilla/5.0' \
  'https://steamgears.eu/wp-json/wc/store/v1/products?per_page=100&page=1'
```

- **Endpoint:** `/wp-json/wc/store/v1/products` (WooCommerce Store API, `wc/store/v1`) — svarer **200** uden auth.
- **Målt:** 3/11 2026, `http=200`, 440.164 bytes, **67 produkter** i butikken (én side, `per_page=100`).
- **Prisfelt:** `prices.price` er et heltal i `currency_minor_unit` (2) og `prices.currency_code` er `USD`.
  `2995` betyder altså **$29.95**. Det er den eneste fælde i formatet: divider med 100, aldrig med 1.
- **Dækning:** alle **31 unikke produkter** i link-planen findes i svaret — **0 manglende**.

Det er samme butik kunden ser. API'et viser den aktuelle pris, ikke en kampagnepris, og butikken kører stadig
USD (S4.3 er ikke gennemført).

## 2. Driftkontrol — de 25 kendte priser

De 25 rækker der allerede havde en pris fra pristabellen, blev holdt op mod butikken: **25 af 25 stemmer,
0 afvigelser.** Pristabellen fra 2/11 kan altså bruges som reference, og de otte nye priser er målt med samme
metode som den.

## 3. De otte rækker der manglede en pris

| Guide | Link | Produkt | Pris | Status |
| --- | --- | --- | --- | --- |
| `/3d-print-steampunk-parts/` | /go/766/ | Steampunk Leather Utility Belt & Holster | $24.90 | `klar` |
| `/build/weathering/` | /go/767/ | Digital Blueprint: Victorian Corset & Vest PDF Pattern | $6.90 | `klar` |
| `/build/tools-and-materials/` | /go/767/ | Digital Blueprint: Victorian Corset & Vest PDF Pattern | $6.90 | `klar` |
| `/sustainable-steampunk/` | /go/767/ | Digital Blueprint: Victorian Corset & Vest PDF Pattern | $6.90 | `klar` |
| `/steampunk-gift-guide-2026/` | /go/940/ | Clockwork Seer — Brass Pocket Watch Pendant | $89.00 | `klar` |
| `/steampunk-gift-guide-2026/` | /go/941/ | The Aeronaut — Brass Skeleton Pocket Watch | $149.00 | `klar` |
| `/steampunk-gift-guide-2026/` | /go/942/ | Ink and Steam — Clockwork Charm Bracelet | $69.00 | `klar` |
| `/steampunk-gift-guide-2026/` | /go/968/ | Nightshade Apothecary – Herbarium Art Print | $29.95 | `klar` |

To af dem er digitale leverancer og koster derfor noget andet end tøj og tilbehør: blueprint-PDF'en (767) er
**$6.90**, og det justerbare bælte med hylster (766) er **$24.90**. Gaveguiden har de fire dyreste stykker i
planen — $29.95 til $149.00 — hvilket er værd at huske, når linkteksten skal love noget om pris.

## 4. Hele planens 31 produkter, målt

| ID | Produkt | Pris | Plan-status |
| --- | --- | ---: | --- |
| 487 | Victorian Lace-Up Corset Top — Brown | $44.95 | `indsat` |
| 497 | Medieval Assassin Coat — Gothic Split Jacket | $39.95 | `indsat` |
| 481 | Steampunk Top Hat with Removable Goggles – Black | $34.95 | `indsat` |
| 489 | Steampunk Overbust Corset – Leather Lace-Up Burlesque | $59.95 | `indsat` |
| 493 | Wine Red Velvet Tailcoat — Victorian Frock Coat | $64.95 | `indsat` |
| 479 | Vintage Driver Goggles – Adult and Kids Cosplay | $29.95 | `indsat` |
| 491 | Gothic PU Leather Overbust Corset — Silver | $64.95 | `indsat` |
| 485 | Gothic Bowler Top Hat with Goggles | $39.95 | `indsat` |
| 477 | Copper Pilot Goggles – Vintage Windproof Riding | $24.95 | `indsat` |
| 559 | Clockwork Fox — Natural History Tee | $26.95 | `indsat` |
| 555 | Nautilus Voyage — Dark Academia Tee | $26.95 | `indsat` |
| 475 | Steampunk Aviator Goggles – Retro Motorcycle Windproof | $24.95 | `indsat` |
| 409 | Gear Corset Lady — Victorian Gothic Hoodie | $49.95 | `indsat` |
| 495 | Victorian Vampire Tailcoat — Brocade Gothic Jacket | $39.95 | `indsat` |
| 483 | Time Traveler Top Hat with Goggles | $49.95 | `indsat` |
| 719 | Sky Corsair — Vintage Maritime Print | $21.90 | `indsat` |
| 567 | The Clockwork Seer — Tarot Witchy Tee | $26.95 | `indsat` |
| 561 | Cabinet of Curiosities — Dark Academia Tee | $26.95 | `indsat` |
| 403 | Mechanical Elephant — Curiosity Hoodie | $49.95 | `indsat` |
| 399 | Clockwork Octopus — Curiosity Hoodie | $49.95 | `indsat` |
| 809 | Steampunk Costume Checklist — Free Workshop PDF | $0.00 | `klar` |
| 728 | First Mate's Oath — Vintage Maritime Print | $21.90 | `klar` |
| 721 | The Kraken Widow — Vintage Maritime Print | $21.90 | `klar` |
| 563 | Ink and Steam Muse — Dark Academia Tee | $26.95 | `klar` |
| 405 | Clockwork Owl — Dark Academia Hoodie | $49.95 | `klar` |
| 766 | Steampunk Leather Utility Belt & Holster | $24.90 | `klar` |
| 767 | Digital Blueprint: Victorian Corset & Vest PDF Pattern | $6.90 | `klar` |
| 940 | Clockwork Seer — Brass Pocket Watch Pendant | $89.00 | `klar` |
| 941 | The Aeronaut — Brass Skeleton Pocket Watch | $149.00 | `klar` |
| 942 | Ink and Steam — Clockwork Charm Bracelet | $69.00 | `klar` |
| 968 | Nightshade Apothecary – Herbarium Art Print | $29.95 | `klar` |

31 unikke produkter · 40 plan-rækker · alle med ID og pris · målt 3/11 2026.

## 5. Hvad det ændrede

- `tools/link_plan_check.py` tillader ikke længere `—` som pris: `NO_PRICE_IDS` er tom, og status `pris mangler`
  er fjernet fra de gyldige statusser. En række uden pris kan ikke længere slippe igennem som «kortlagt».
- `tools/guide_check.py` læser priser fra **både** pristabellen og denne fil, så et `/go/<id>/` med en målt pris
  valideres mod butikken og ikke kun mod 2/11-tabellen.
- Link-planen har nu **20 rækker `indsat`** (outfit-guiden, i repoet) og **20 rækker `klar`** (WordPress-only).
  Ingen række er længere uden pris, og ingen række er uden ID.

**Det der stadig blokerer** er ikke data men adgang: de 20 `klar`-rækker skal indsættes på sider der kun findes
i WordPress, og `/go/`-ruten kræver en Redirection-gruppe eller Royal Links. Begge dele er ejerens.
