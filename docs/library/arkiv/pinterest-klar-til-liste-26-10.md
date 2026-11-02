# Pinterest klar-til-listen — Henning's 2-min-opgaver (26/10 2026)

Kontekst: Pinterest API-token er stadig 401 ("consumer type not supported") — **Buffer dækker pin-udgivelse** indtil app'en er godkendt. Boards er det manglende led: pins i Buffer-køen kan først målrettes boards, når de findes. Alt herunder er copy-paste-klar, hvert trin ≈ 2 min.

---

## Trin 1 — Verificér steamgears.eu på pinterest.com/cpaleadsis (≈ 2 min)

1. Log ind på pinterest.com/cpaleadsis → **Business Hub → Indstillinger → Claimed accounts / Verificér website**.
2. Vælg **Claim → HTML-meta-tag** og kopiér linjen:
   `<meta name="p:domain_verify" content="XXXX"/>`
3. Indsæt i WordPress: **WPCode → Header** (som for Warpstorm/AdSense) eller Rank Math → General → Webmaster/Pinterest-feltet. Gem.
4. Klik **Verify** på Pinterest. Grønt flueben = claimed. Deraf: rich pins muligt + site-URL på profilen.

*Alternativ: verificering via AdSense-plugin/Site Kit er ikke nødvendig — meta-tag er hurtigst.*

---

## Trin 2 — Opret de 4 boards (≈ 2 min/stykke)

EN-navne (en-US SEO-strategien) + kopieret beskrivelse pr. board:

**1. Steampunk Workshop Guides** (slug: `steampunk-workshop-guides`)
> DIY steampunk costume guides from the Steamgears workshop: build your own goggles, corsets and full outfits. Step-by-step projects with difficulty, time and budget — every pin links to a full guide on steamgears.eu.

Pinner: /start-here/, /build/, DIY Goggles Guide, "How to Make a Steampunk Costume", Budget-guide, Beginners-guide, Casual Outfits, DIY vs. Buy.

**2. Steampunk Posters & Wall Art** (slug: `steampunk-posters-prints`)
> Steampunk posters and wall art printed to order in 30x40 and 50x70 cm. Airship, clockwork and pirate designs — text-free art for workshop, office and living room. Ships worldwide from steamgears.eu.

⚠️ Tjek FØRST: boardet `pinterest.com/steamngears/steampunk-posters-prints` (rank #1 for "steampunk poster") — er det din alt-konto → genbrug/omdøb det, ellers opret nyt som ovenfor.

**3. Steampunk Complete Looks** (slug: `steampunk-complete-looks`)
> Head-to-toe steampunk outfit ideas: complete looks with goggles, corsets, jackets and accessories. Airship Captain, Victorian Inventor, Dark Clockwork and more — each look shoppable on steamgears.eu.

Pinner: 5 Complete Looks-samlinger (Airship Captain, Victorian Inventor, Dark Clockwork, Mechanical Explorer, Beginner Steampunk) + corset-pins fra 25/10 hører hertil.

**4. Steampunk Halloween & Costumes** (slug: `steampunk-halloween-costumes`)
> Steampunk Halloween costume ideas and last-minute looks: plague doctor, airship pirate, clockwork skull and more. Full guides and shoppable pieces on steamgears.eu.

Genbrug/omdøb eksisterende "Steampunk + goth" → dette board. **Pin Pack #3 (27–31/10) targets allerede her** — ingen ændring i Buffer-køen ud over board-navnet.

Flagship-boardet "Steampunk" forbliver uændret.

---

## Trin 3 — Pin-forslag fra poster-mockups (Buffer udfylder / Juno refiller køen)

**Live nu (Pirat Piger V1, produktsider klar, image-mockups i /workspace/library/pirat-piger/):**

| # | Pin-titel | Link | Billede |
|---|-----------|------|---------|
| 1 | Sky Corsair — Steampunk Pirate Poster | /product/sg-pirat-001* (produkt 719) | 01-sky-corsair.jpg |
| 2 | The Kraken Widow — Steampunk Wall Art | produkt 721 | 02-kraken-widow.jpg |
| 3 | Clockwork Figurehead — Pirate Girl Poster | produkt 724 | 03-clockwork-figurehead.jpg |
| 4 | The Compass Duchess — Steampunk Poster | produkt 726 | 04-compass-duchess.jpg |
| 5 | First Mate's Oath — Wall Art 30x40/50x70 | produkt 728 | 05-first-mates-oath.jpg |

Description-skabelon (en-US): "Sky Corsair — steampunk pirate girl poster, printed to order in 30x40 and 50x70 cm. Text-free steampunk wall art from Steamgears. #steampunk #wallart #posterart #steampunkpirate" (Link → produktside eller /product-category/steampunk-pirate-posters/.)

**Fra 75-poster-design-puljen (mockups til import — pin-forslag klar):**
- "Time Is a Machine" — layered gears, clock face (SG-POSTER-GEAR-001)
- "Keep Time. Keep Moving." — gunmetal + brass (…-002)
- "Precision Under Pressure" (…-003)
- "Every Tooth Counts" (…-004)
- "Built to Keep Moving" (…-005)
- "Chart the Impossible" / "Ride Beyond the Horizon" (…-024/026 — luftskibs-vinkel → pair med Complete Looks-board som cross-pin)
- "Wound by Hand" / "Steel. Brass. Steam." (…-014/017)

Fordeling-forslag: 5 × Pirat Piger → **Posters & Wall Art** + Pirat Piger-serienavnet i beskrivelsen; gear-slogan-pins → **Posters & Wall Art**; 1-2 cross-pins (poster + komplet outfit) → **Complete Looks**; halloween-posters når linjen vokser → **Halloween & Costumes**.

NB: Pirat Piger-mockups er tekstfri (standing order ✓). Gear-CSV-designs har typografi — brug dem som planlagt fra CSV'en.

---

## Trin 4 — Efter boards findes (Juno klarer)

- Om-target Buffer-køen: Pin Pack #2 (28/10) → Complete Looks / Pirate-serie-board; Pin Pack #3 (27–31/10) → Halloween & Costumes.
- Poster-pinen (26/10 18:30 UTC, board "Steampunk") kan flyttes til Posters & Wall Art.
- Verificering åbner rich pins — tag Juno, så aktiveres de.

---

## Opdatering 26/10 — Billeder genereret via aimlapi ✅

9 pins (848×1264, 2:3, alle tekstfri-verificerede via vision) i canvas-artifacten "Board Covers & Pirat Piger Pins" (/library/Pinterest Pins/):

**Board covers (sæt som cover-billede når boardet oprettes):**
- cover-workshop-guides.png → Steampunk Workshop Guides
- cover-posters-wall-art.png → Steampunk Posters & Wall Art
- cover-complete-looks.png → Steampunk Complete Looks
- cover-halloween-costumes.png → Steampunk Halloween & Costumes

**Pirat Piger pin-format (nye variationer, brug som pin-billeder i Buffer):**
- pin-sky-corsair.png · pin-kraken-widow.png (tekst "THE SEA SERPENT" fjernet via image edit) · pin-clockwork-figurehead.png · pin-compass-duchess.png · pin-first-mates-oath.png

Note til Senere: gatewayen laver landskab (1408×768) medmindre prompten starter med "Vertical portrait 2:3 composition" — brug det prefix ved fremtidige generationskald.
