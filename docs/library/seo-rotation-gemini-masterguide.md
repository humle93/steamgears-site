# Den Komplette Optimeringsguide: Fra Klassisk SEO til AI, Svar- og Visuel Søgning
*(Gemini-leveret master-guide, lagt i Drive af Henning 27/10 — implementeret i Steamgears' ugerotation)*

Overblik: De 11 Discipliner

| # | Disiplin | Primære Kanaler | Kerneformål |
|---|---|---|---|
| 1 | SEO | Google, Bing (organiske links) | Klik og organisk trafik via side 1 |
| 2 | AEO | Featured Snippets, PAA, Siri/Assistant | Det præcise svar direkte i resultatet (position 0) |
| 3 | GEO | ChatGPT, Perplexity, Gemini, AI Overviews | Blive citeret som autoritativ kilde i AI-svar |
| 4 | SXO | Sitet (UX + søgehensigt) | SEO + brugeroplevelse, eliminere pogo-sticking |
| 5 | AIO | AI-crawlere, bots, API'er | Teknisk letlæselig for AI (JSON-LD, llms.txt, clean HTML) |
| 6 | LLMO | Træningsdata, Reddit, fora | Brand-niche-associering i sprogmodeller |
| 7 | VSO | Siri, Alexa, assistenter | Talt sprog + long-tail spørgsmål |
| 8 | VEO | Google Lens, Pinterest Lens, Billeder | Filnavne, alt-tags, Product-schema |
| 9 | CRO | Produktsider, checkout | Besøgende → køb |
| 10 | SMO | Pinterest, TikTok, YouTube, IG | Søgning inde i sociale apps |
| 11 | ASO | App Store, Play, WP Directory | Rangering i lukkede markedspladser (n/a for Steamgears) |

## Kondenserede tjeklister pr. disiplin

**SEO:** 1 søgehensigt pr. side; ét H1 med primært keyword; meta-title <60 tegn med keyword forrest; meta-desc 140-155 tegn + CTA; intern linkbuilding med beskrivende ankertekster (topic clusters).

**AEO (answer-first / omvendt pyramide):** H2/H3 formuleret som spørgsmål; direkte faktuelt svar på 40-60 ord før dybden; lister/tabeller til spec- og størrelsesindhold (snippet-materiale); FAQPage JSON-LD på kategorier + guides.

**GEO:** Fakta-tæthed — målbare specifikationer ("250g syrefrit museumspapir", "30×40 og 50×70 cm") frem for salgssnak; entitets-optimering (Victorian Industrial, Bespoke Horology, Dark Academia, Skeleton Pocket Watch); citérbare "Key Takeaways"-bokse øverst i lange artikler (RAG-venligt).

**AIO:** Komplet JSON-LD @graph (Organization, WebSite, Product, Offer, BreadcrumbList, Article); /llms.txt i domænerod; semantisk HTML5; vigtig tekst ikke gemt i JS.

**LLMO:** Co-occurrence — brandnavn konsekvent med kerneprodukter på tværs af nettet; sameAs-schema der samler alle officielle profiler; dybdegående niche-guides som reference-materiale.

**SXO:** LCP < 2,5 s (WebP/AVIF, let CSS); hovedindhold og tydelig H1 + produktbillede over folden; korte afsnit (3-4 linjer), faktabokse, luft.

**VEO:** Beskrivende bindestregs-filnavne før upload; præcise alt-tekster ("motiv + produkttype + anvendelse"); close-up OG interiør-mockup af samme produkt; billeder koblet til Product JSON-LD (Google Billeder "Produkt"-badge).

**VSO:** Konversations-long-tails ("hvordan finder jeg størrelse på victoriansk brokade-vest"); mundrette svar uden indskudte sætninger.

**SMO:** Boards navngivet efter søgeord; fyldige board-beskrivelser; 3 unikke pins pr. produkt (1 produkt, 2 livsstils-mockup, 3 lore/story 1000×1500); Open Graph + product:price-metadata til Rich Pins.

**CRO:** 4 købsbarrierer besvaret ved Læg i kurv (størrelse, materiale, levering, betaling); CTA i kontrastfarve (kobber/messing); friktionsfri checkout (få felter, gæst-checkout, hurtige betalinger).

**Praktisk side-skabelon (SEO+AEO+GEO+VEO):** kort ren URL → H1 + 40-60 ords intro → spec-tabel/bullets med mål+materialer+levering → FAQ med FAQPage JSON-LD (3-5 spørgsmål, ~50 ords svar).
