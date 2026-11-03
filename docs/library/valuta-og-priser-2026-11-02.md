# Valuta og priser — forslag til S4.3

Butikken kører i **USD**. For en dansk butik med EU-levering betyder det, at kunden skal regne om i
hovedet, og at momsvisning bliver meningsløs. S4.3 kræver både et valutaskift **og** nye priser —
valutakoden alene ville gøre $21.90 til 21,90 kr.

## Kurs brugt her

Hentet 2/11 2026 fra `api.frankfurter.app` (ECB-referencer, kurs dateret 30/10 2026):

- 1 USD = **0,89509 EUR**
- 1 USD = **6,6895 DKK**

Kursen skal genhentes på skiftedagen — tabellen er et forslag, ikke en facitliste.

## Forslag

**Primær valuta: DKK.** Kunden er dansk, fragt og retur foregår i Danmark, og MobilePay (S4.5) er
DKK-only i praksis. **EUR vises som sekundær** via currency switcher, ikke som base.

Afrunding: EUR til nærmeste `x,95`, DKK til nærmeste 5 kr. Det holder prisbilledet ens på tværs af
produkter og undgår, at en tee koster 179,32 kr.

## Prisforslag pr. produkt

38 produkter med pris (den gratis workshop-checkliste på $0.00 er udeladt).

| Produkt | USD nu | EUR-forslag | DKK-forslag |
|---|---|---|---|
| Clockwork Figurehead — Vintage Maritime Print | $21.90 | €19.95 | 145 kr |
| First Mate's Oath — Vintage Maritime Print | $21.90 | €19.95 | 145 kr |
| Sky Corsair — Vintage Maritime Print | $21.90 | €19.95 | 145 kr |
| The Compass Duchess — Vintage Maritime Print | $21.90 | €19.95 | 145 kr |
| The Kraken Widow — Vintage Maritime Print | $21.90 | €19.95 | 145 kr |
| Copper Pilot Goggles - Vintage Windproof Riding | $24.95 | €22.95 | 165 kr |
| Steampunk Aviator Goggles - Retro Motorcycle Windproof | $24.95 | €22.95 | 165 kr |
| Airship Armada — Vintage Engraving Tee | $26.95 | €24.95 | 180 kr |
| Cabinet of Curiosities — Dark Academia Tee | $26.95 | €24.95 | 180 kr |
| Chrono-Wing Dragonfly — Natural History Tee | $26.95 | €24.95 | 180 kr |
| Clockwork Fox — Natural History Tee | $26.95 | €24.95 | 180 kr |
| Clockwork Tea Robots — Whimsigoth Tee | $26.95 | €24.95 | 180 kr |
| Hummingbird Automaton — Natural History Tee | $26.95 | €24.95 | 180 kr |
| Ink and Steam Muse — Dark Academia Tee | $26.95 | €24.95 | 180 kr |
| Nautilus Voyage — Dark Academia Tee | $26.95 | €24.95 | 180 kr |
| The Clockwork Seer — Tarot Witchy Tee | $26.95 | €24.95 | 180 kr |
| Vintage Driver Goggles - Adult and Kids Cosplay | $29.95 | €26.95 | 200 kr |
| Steampunk Top Hat with Removable Goggles - Black | $34.95 | €31.95 | 235 kr |
| Gothic Bowler Top Hat with Goggles | $39.95 | €35.95 | 265 kr |
| Medieval Assassin Coat — Gothic Split Jacket | $39.95 | €35.95 | 265 kr |
| Victorian Vampire Tailcoat — Brocade Gothic Jacket | $39.95 | €35.95 | 265 kr |
| Victorian Lace-Up Corset Top — Brown | $44.95 | €40.95 | 300 kr |
| Airship Storm — Vintage Engraving Hoodie | $49.95 | €44.95 | 335 kr |
| Clockwork Heart — Gothic Romance Hoodie | $49.95 | €44.95 | 335 kr |
| Clockwork Octopus — Curiosity Hoodie | $49.95 | €44.95 | 335 kr |
| Clockwork Owl — Dark Academia Hoodie | $49.95 | €44.95 | 335 kr |
| Clockwork Skull — Dark Academia Hoodie | $49.95 | €44.95 | 335 kr |
| Compass Mandala — Dark Academia Hoodie | $49.95 | €44.95 | 335 kr |
| Gear Corset Lady — Victorian Gothic Hoodie | $49.95 | €44.95 | 335 kr |
| Gear Filigree — Art Nouveau Mandala Hoodie | $49.95 | €44.95 | 335 kr |
| Keyhole Nouveau — Art Nouveau Hoodie | $49.95 | €44.95 | 335 kr |
| Mechanical Elephant — Curiosity Hoodie | $49.95 | €44.95 | 335 kr |
| Sun Compass — Alchemical Hoodie | $49.95 | €44.95 | 335 kr |
| Time Traveler Top Hat with Goggles | $49.95 | €44.95 | 335 kr |
| Whale Airship — Vintage Voyage Hoodie | $49.95 | €44.95 | 335 kr |
| Steampunk Overbust Corset - Leather Lace-Up Burlesque | $59.95 | €53.95 | 400 kr |
| Gothic PU Leather Overbust Corset — Silver | $64.95 | €58.95 | 435 kr |
| Wine Red Velvet Tailcoat — Victorian Frock Coat | $64.95 | €58.95 | 435 kr |
## Før du skifter

1. **Moms.** Kontrollér, om de nuværende priser er sat inkl. eller ekskl. moms i WooCommerce →
   Indstillinger → Moms. Skiftet må ikke ændre, hvad kunden reelt betaler, med mere end afrundingen.
2. **Fragt.** Printify-priser er i USD; en DKK-butik skal have fragttabellerne genberegnet, ellers
   æder kursudsving margenen (S4.6).
3. **Betalingsgebyrer.** Stripe tager et procentgebyr plus et fast beløb pr. valuta. MobilePay og
   PayPal har egne satser — regn dem ind, før priserne låses.
4. **Annoncer og feeds.** Hvis produkter er eller har været i Meta/Google-feeds, skal feedvalutaen
   skiftes samtidig, ellers afvises produkterne.
5. **Rækkefølge.** Skift valuta og priser i samme handling som S4.5 (betalingsmetoder), så kunden
   møder en færdig butik og ikke en halv.

## Hvad der ikke er gjort

Intet er ændret i butikken. Et valutaskift er en økonomisk beslutning med moms-, fragt- og
gebyrkonsekvenser, og priserne skal ejeren godkende. Tabellen er klar til at blive ført ind, når
beslutningen er taget.
