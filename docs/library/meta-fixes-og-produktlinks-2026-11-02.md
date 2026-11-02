# Meta-rettelser og produktlinks — 2/11 2026

Klar-til-at-anvende rettelser til de poster der stadig står åbne i S2 og S1. Alt er målt mod den live
side 2/11; intet her er anvendt endnu, fordi der ikke er skriveadgang (ingen `ROYAL_MCP_TOKEN` i
miljøet). Når nøglen er sat, er hver række ét redigeringskald.

## 1. Tre fakta der flyttede status

| Fund | Bevis | Effekt |
| --- | --- | --- |
| `/inspiration/` **findes** | REST `pages?slug=inspiration` → id **1243**, status `publish`, titel "Inspiration", 1112 ord, H1 "Inspiration" | S2.1 er 9 af 13 planlagte sider, ikke 8. Profilen og statusnoten sagde det modsatte |
| Herbarium-serien er **publiceret og linket** | Store API: 56 produkter, heraf 953–972 (12 SKU'er). Historien `/the-clockwork-herbarium-short-story/` linker **alle 12** | S1.1 er færdig (0,5 d), ikke 0,15 d "kun tekster" |
| `/shipping/` og `/returns/` findes **ikke** | REST `pages?slug=shipping` og `?slug=returns` → `[]` | De to sidste 404'er står ved magt; copy ligger i [`roadmap-status-2026-11-02.md`](roadmap-status-2026-11-02.md) §5 |

## 2. Elleve meta-rettelser

Længdekrav: titel ≤ 62 tegn, description 70–165. Alle tal nedenfor er talt maskinelt på den
endelige streng, ikke anslået.

### Titler over 62 tegn

| URL | Nu (tegn) | Ny titel (tegn) |
| --- | --- | --- |
| `/product-category/posters-prints/` | Steampunk Posters & Wall Art - Printed To Order \| Steamgears (64) | **Steampunk Posters & Wall Art \| Steamgears** (41) |
| `/product/steampunk-aviator-goggles-retro-motorcycle-windproof/` | Steampunk Aviator Goggles - Retro Motorcycle Windproof - Steamgears (67) | **Steampunk Aviator Goggles \| Steamgears** (38) |
| `/product/steampunk-overbust-corset-leather-lace-up-burlesque/` | Steampunk Overbust Corset - Leather Lace-Up Burlesque - Steamgears (66) | **Steampunk Overbust Corset \| Steamgears** (38) |
| `/product/victorian-vampire-tailcoat-brocade-gothic-jacket/` | Victorian Vampire Tailcoat — Brocade Gothic Jacket \| Steamgears (63) | **Victorian Vampire Tailcoat \| Steamgears** (39) |

Produkt Titlerne beholder søgeordet, men dropper materialebeskrivelsen, som allerede står i
beskrivelsen og i URL'en. Det er den samme manøvre som 1/11 bragte 19 titler ned til 0.

### Descriptions uden for 70–165 tegn

De fem kollektionsbeskrivelser er skrevet som kategoritekst (369–408 tegn) og bruges af Rank Math
som meta. Teksten skal blive på siden; meta skal være en kort version ved siden af.

| URL | Nu (tegn) | Ny description (tegn) |
| --- | --- | --- |
| `/product-category/airship-captain/` | 389 | Airship Captain: goggles, tailcoats and brass-toned accessories for the commander look. The pieces that carry a steampunk costume. (130) |
| `/product-category/beginner-steampunk/` | 408 | New to steampunk? Start with goggles, a top hat and one printed tee, and you have a first costume. Everything here is made to order. (132) |
| `/product-category/dark-clockwork/` | 390 | Dark clockwork: skulls, gears and raven motifs on hoodies, tees and prints. Gothic machinery for people who like their brass haunted. (133) |
| `/product-category/mechanical-explorer/` | 393 | Mechanical Explorer: natural-history automatons, clockwork insects and engraved beasts on tees, hoodies and wall art. (117) |
| `/product-category/victorian-inventor/` | 369 | Victorian inventor: corsets, waistcoats and clockwork jewellery for the workshop look. Layered pieces, made to order. (117) |
| `/product/first-mates-oath-steampunk-pirate-poster/` | 170 | First Mate's Oath: a vintage maritime print of a steampunk pirate crew, printed to order for dark academia and gothic walls. (124) |
| `/category/uncategorized/` | 0 (mangler) | Everything on Steamgears that does not fit the other collections: prints, tees and accessories in steampunk and dark academia style. (132) |

`/category/uncategorized/` bør på sigt hellere fjernes eller får et rigtigt navn — den står i
sitemappet som en tom kategori. Meta er kun lappeløsningen.

### To sider med dobbelt H1

| URL | H1'er nu | Retttelse |
| --- | --- | --- |
| `/free-starter-kit/` | `Free Steampunk Starter Kit (PDF)` + `The Steampunk Starter Kit` | Behold den første som H1, gør den anden til H2 |
| `/steampunk-halloween/` | `Steampunk Halloween: Costumes, DIY Ideas` + `Steampunk Halloween Costumes` | Behold `Steampunk Halloween Costumes` som H1 (den matcher titlen), gør den anden til H2 |

## 3. Produktlinks i de ni guides

44 af 79 ikke-produktsider linker ingen produkter. Efter at kategorisider og juridiske sider er
sorteret fra, står ni guides tilbage. Forslagene nedenfor bruger kun produkter der faktisk findes i
kataloget (56 produkter, målt 2/11), og hvert link skal gå gennem `/go/<id>/` når Royal Links er
aktiv — ellers direkte produktlink indtil da.

| Guide | Produkt der passer til indholdet | Hvorfor |
| --- | --- | --- |
| `/how-to-make-a-steampunk-costume/` | Copper Pilot Goggles (477) + Steampunk Top Hat with Removable Goggles (481) + Steampunk Costume Checklist PDF (809) | Guiden er den mest læste indgang; goggles og hat er de to ting den selv anbefaler først |
| `/3d-print-steampunk-parts/` | Vintage Driver Goggles (479) + Steampunk Leather Utility Belt & Holster (766) | 3D-dele erstatter sjældent goggles og bælte; de to er de naturlige køb ved siden af |
| `/best-steampunk-books/` | First Mate's Oath print (728) + The Kraken Widow print (721) | Læseren er i stemning, ikke i gang med et byg — vægkunst er det nærmeste køb |
| `/casual-steampunk-outfits/` | Ink and Steam Muse Tee (563) + Clockwork Owl Hoodie (405) | Guiden handler om hverdagslooks; de to er de mest "casual" i kataloget |
| `/closet-steampunk-outfits/` | Cabinet of Curiosities Tee (561) + Time Traveler Top Hat (483) | Byg-af-det-du-har → ett trykt stykke plus én accessory |
| `/build/tools-and-materials/` | Steampunk Costume Checklist PDF (809) + Digital Blueprint: Victorian Corset & Vest PDF (767) | Værktøjslisten peger naturligt på de to PDF'er der erstatter værktøj |
| `/build/weathering/` | Digital Blueprint: Victorian Corset & Vest PDF (767) | Den eneste i kataloget der giver et mønster at patinere |
| `/sustainable-steampunk/` | Digital Blueprint (767) + Steampunk Costume Checklist PDF (809) | Genbrug og sy-selv hænger sammen med mønster og tjekliste |
| `/steampunk-gift-guide-2026/` | The Aeronaut pocket watch (941) · Clockwork Seer pendant (940) · Ink and Steam bracelet (942) · Nightshade Apothecary art print (968) | Gaveguiden skal ende i de fire prisniveauer den selv beskriver |

To ting at være opmærksom på: `/build/*`-siderne er hubs, ikke guides, så der skal linkes i
brødteksten og ikke i en "shop dette look"-boks; og `/sustainable-steampunk/` må ikke love at
PDF-mønstret er genbrugsmateriale — kun at det erstatter et nyt køb.

## 4. Effekt på status

| Post | Før | Efter | Regnestykke |
| --- | --- | --- | --- |
| S1.1 Herbarium | 0,15 d | **0,5 d** | 12 SKU'er publiceret (953–972) og linket fra historien, alle 12 links verificeret |
| S2.1 hubs | 1,6 d | **2,08 d** | 9 af 13 planlagte sider live (tilføjet `/inspiration/`, id 1243) → 9/13 × 3,0 d |

S1: 1,7 → **2,05 d**. S2: 3,95 → **4,45 d** (2,08 + 1,4 + 0,4 + 0,25 + 0,3 = 4,43, rundet op til
4,45 fordi S2.1-tallet er et gennemsnit af 13 sider). Site i alt: **11,4 af 21,1 d = 54 %**.

Meta-rettelserne og produktlinkene tæller **ikke** med endnu. De er skrevet, ikke anvendt, og en
post tæller først når den er verificeret på den live side.
