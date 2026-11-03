# 17 kladde-produkter udgivet — 2/11 2026

Log for den session hvor Steamgears blev færdiggjort som butik: adgang via Royal MCP, kladderne færdige, udgivet og verificeret på det live site.

## Adgang

Login kom ind via Royal MCP's OAuth-flow (PKCE, offentlig klient, callback til en lokal server i workspacet). Klienten skulle godkendes af ejeren i wp-admin under **Royal MCP → Pending Clients**, hvorefter `/authorize` gav en kode, som blev vekslet til et token med 24 timers levetid og `mcp:full`. Token og klientdata ligger i `.secrets/` (gitignoreret) — intet er skrevet til repoet eller chatten.

270 MCP-værktøjer var tilgængelige, heraf `wc_*` til WooCommerce.

## Hvad der faktisk var galt

Kladderne var ikke tomme. Teksterne var skrevet (14 af 17 havde 1.000–2.400 tegn), men:

- **Ingen af de 17 havde et featured image.** Billederne lå allerede i mediebiblioteket fra oktober (949–964 til Herbarium, 946–948 til Atelier, 771 og 935 til DIY) uden at være knyttet til varerne.
- **Ingen havde kort beskrivelse**, så alle ville stå tomme i katalog, relaterede varer og søgeresultater.
- **Atelier (940, 941, 942)** havde danske pladsholdere: `[PLACEHOLDER — afventer leverandørvalg og fotos]`.
- **Print-teksterne (965–968)** angav ingen mål, selvom butikken sælger 30 × 40 og 50 × 70 cm.
- **Cover-teksterne (969–972)** lovede et modelvalg ("the phone model selected above"), som produktet ikke har — der findes ingen globale vareattributter på siten.
- **Alle 12 Herbarium-varer** linkede til Dark Academia og Natural History, uanset at de ligger i Gaslight, T-Shirts, Posters & Print og Phone Cases.
- **De seks ældre ID'er** i indholdsauditten (27, 28, 29, 75, 183, 765) findes ikke længere. Sættet er 17, ikke 23.

## Hvad der blev skrevet

| Varer | Ændring |
|---|---|
| 940, 941, 942 | Nye engelske tekster i husets struktur: hook, why, details, dispatch & delivery, care, GPSR, kollektionslinks. Materiele oplyst som antik-messing-finish; producent Steamgears (ikke "printed on demand", som er forkert for smykker) |
| 766 | Omskrevet til husets struktur; materiale, justerbar vidde 71–112 cm og pleje bevaret fra den oprindelige tekst |
| 767 | Omskrevet som digitalt produkt: hvad du får, levering, udskrivning. Ingen GPSR-blok, som på det udgivne gratis-PDF |
| 953–956, 965–968, 969–972 | Brødteksten bevaret. Print fik mål og rør; covers fik ordrenote-instruks i stedet for et modelvalg der ikke findes; alle fik kollektionslinks der matcher deres faktiske kategorier |
| Alle 17 | Kort beskrivelse i husets stil (sætning, mål/model, "EU 14-day returns") og alt-tekst på billedet |

Trykte facts er genbrugt fra udgivne varer, ikke gættet: tee-blokke og måletabel fra 563, leverings- og GPSR-formulering fra 563/728/809, print-mål fra de udgivne prints og `docs/library/punk-lancering-2026-11-02.md`.

## Udgivet

- 17 produkter skiftet til `publish`. Butikken gik fra 39 til **56 udgivne produkter**.
- `/inspiration/` oprettet som side 1243 og publiceret: 803 ord, native Gutenberg-blokke, Rank Math title/description/focus keyword sat.
- Novelle 973 fik et "Wear the engravings"-afsnit med links til alle tolv Herbarium-varer, og "arrives in November 2026" blev erstattet.
- `/start-here/` fik et femte punkt i "Start with what you want to do" der peger på hubben.
- `redirects.csv`/`.json`: rækkerne for `/inspiration/` og for utility belt-produktet er fjernet — begge er nu live sider, og en 301 ville have ødelagt dem.

## Kontrolleret

- Store API: 56 produkter, alle 17 nye med præcis ét billede og en permalink.
- Fire produktsider hentet med cache-buster: 200, ingen soft-404, `og:image` sat, add-to-cart til stede.
- `/inspiration/`: 200, ingen soft-404, rigtig `<title>`, én `<h1>`, meta description.
- Ti kategorilinks brugt i de nye tekster: alle 200 uden soft-404.
- `/product-category/gaslight/` viser de nye varer.
- `_thumbnail_id` = det tilsigtede medie-ID på alle 17.
- Fire billeder set efter: tee, print, pendant og bælte matcher varen.

## Ikke kontrolleret eller stadig åbent

- **Sitemappen er cachet i Rank Math.** `/product-sitemap.xml` viser stadig de gamle 39 produkter og `/page-sitemap.xml` mangler `/inspiration/`, selvom den er regenereret efter ændringerne. Der findes intet MCP-værktøj til at rydde Rank Math-cachen. Den falder formentlig selv ud inden for 24 timer; ellers skal "Regenerate sitemap" køres i wp-admin.
- **Print-målene 30 × 40 og 50 × 70 cm** er butikkens dokumenterede standard, men de er ikke bekræftet pr. SKU hos leverandøren.
- **Telefonmodeller** er ikke en vareattribut nogen steder i butikken. Cover-teksterne beder nu kunden skrive model i ordrenoten. Skal butikken sælge covers i volumen, bør der laves en global `pa_model`-attribut med variationer.
- **Atelier-varerne** har stadig kun ét billede hver og ingen bekræftet leverandør bag GPSR-producentlinjen.
- **P1.1 gælder stadig:** ingen produkter har 3+ billeder. Det er stadig den største enkeltforbedring.
- **P0-blokeringerne står urørt:** CVR, firmanavn, adresse og telefon mangler; valuta er stadig USD; MobilePay/PayPal mangler.
- Sitemap-tællingen betyder også at de nye varer ikke er meldt til Google endnu.

## Gentag

```bash
python3 tools/deploy/finish_drafts.py           # plan, intet skrives
python3 tools/deploy/finish_drafts.py --apply   # billede, kort tekst, brødtekst
python3 tools/deploy/finish_drafts.py --apply --publish
```

Scriptet læser `.secrets/drafts-full.json` (snapshot af kladderne) og kræver et gyldigt token i `.secrets/royal-token.json`. Er tokenet udløbet, skal OAuth-flowet køres igen.
