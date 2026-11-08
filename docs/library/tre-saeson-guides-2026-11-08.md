# Tre sæson-guides publiceret — SEO-indhold 3 af 3, 8/11

De tre kladder, der blev valgt efter årstiden, er nu publiceret med billeder, alt-tekst og et «Shop the Look»-afsnit
med rigtige produktlinks. Dermed er SEO-indholdsmodulet **3 af 3** og projektet **100 %**.

## Valget (november–februar)

| ID | Guide | Hvorfor nu |
| --- | --- | --- |
| 1357 | DIY Steampunk Wall Decor: Gear Clocks and Copper Pipe Shelves | indendørs-DIY og gavesæson |
| 1361 | Steampunk Mad Scientist Costume: 10 Inventor Ideas to Build | nytårsaften og karneval i februar |
| 1359 | Steampunk Utility Belt: Build a Rig with Holsters & Pouches | accessory som gave og «sidste brik» til cosplay |

**1410** (børne-Halloween) blev bevidst parkeret: dens sæson toppede i september–oktober og skal ud igen før 1. september.

## Hvad der blev tilføjet pr. guide

Hver guide fik to billeder med beskrivende alt-tekst (fra produktbiblioteket, samme visuelle sprog som resten af
sitet) og et `Shop the Look`-afsnit med tre links i det format de øvrige guides bruger: `<a href="/go/<produkt-id>/">`.

| Guide | Billeder (alt-tekst) | `/go/`-links |
| --- | --- | --- |
| 1357 wall decor | 958 «Brass Luna Moth Herbarium art print, framed…», 959 «Raven Chronometer…» | 966, 965, 967 (tre Herbarium-prints) |
| 1361 mad scientist | 476 «Copper Pilot Goggles — Vintage Windproof Riding…», 857 «Time Traveler Top Hat with Goggles…» | 477, 483, 940 (goggles, hat, lommeur) |
| 1359 utility belt | 947 «Brass skeleton pocket watch…», 946 «Brass pocket watch pendant…» | 477, 941, 940 (goggles og to ure på kæde) |

Indholdet voksede fra 11,2–11,5k til **12,6–12,9k tegn**, og ingen eksisterende tekst blev ændret — kun tilføjet.

## Målt live

| URL | Status | Bytes | `/go/`-links | Billeder m. alt |
| --- | --- | --- | --- | --- |
| `/steampunk-wall-decor-diy/` | **200** | 104 007 | 965, 966, 967 | 2 + øvrige |
| `/steampunk-mad-scientist-costume/` | **200** | 104 337 | 477, 483, 940 | 2 + øvrige |
| `/steampunk-utility-belt/` | **200** | 104 485 | 477, 940, 941 | 2 + øvrige |

Redirect-kæden er kontrolleret: `/go/966/` → **302** → `/product/brass-luna-moth-print/` (**200**),
`/go/477/` → **302** → `/product/copper-pilot-goggles/` (**200**), `/go/941/` → **302** →
`/product/the-aeronaut-pocket-watch/` (**200**). Billedfilerne svarer **200**.

## Sitemap-fælden

Efter publiceringen listede `post-sitemap.xml` stadig kun 31 af 33 publicerede indlæg, og de tre nye manglede —
selv om `rank_math_robots` var tom (altså *ikke* noindex). Årsagen var Rank Maths **fil-baserede sitemap-cache** i
`wp-content/uploads/rank-math/`, som hverken `w3tc_flush_all()` eller en transient-sletning rører. Efter en
re-save af ét indlæg plus sletning af `uploads/rank-math/*.xml` målte sitemappen **34 lokationer**, og alle tre
slugs står nu i den.

## Status efter passet

- **SEO-indholdsproduktion: 100 %** — batch 3/3 guides, loop 1/1, copy 4/4, corset 1/1.
- **Site-optimering: 88,6 %** — de syv S4-punkter der kræver ejeren (CVR/adresse/telefon og dermed GPSR, valuta og
  priser, betalingsaftaler, forsendelsesvalg, Printful-billeder, EU-samtykkemodel i Complianz).
- **Plugin: 98,0 %** — P1.5's sidste 0,60 d er live-verifikation og kræver ejerens tokens.
- **Dark academia-rebrand: 63 %** — batch 4-mockups kræver Printful-adgang.
