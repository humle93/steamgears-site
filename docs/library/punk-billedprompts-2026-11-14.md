# Clockwork + Dieselpunk: ti billedprompts klar, selve billederne mangler en nøgle (14/11 2026)

**Kort:** Der ligger **ingen** separat prompt- eller beskrivelsesfil for de ti nye punk-plakater i Drive.
Det nærmeste er de to gældende køreplaner, som beskriver motiverne i produktteksten, samt
50-plakatkataloget fra 13/11, som kun dækker AIR/ENG/RAIL/NAT/ARC. De ti beskrivelser er derfor
omsat til ti eksplicitte, engelske billedprompts og lagt i de rigtige tema-mapper. Selve
billedgenereringen kunne ikke køres: **der findes ingen billed-API-nøgle i miljøet**.

## Hvad der blev søgt efter (målt 14/11)

- Drive (begge tilsluttede konti): søgning på motivtitlerne («Valkyrie of the Oil Fields»,
  «Cathedral of the Eternal Pendulum»), på `SG-CLK`/`SG-DSL` sammen med «poster» og på nye
  promptdokumenter. Resultat: **kun** de kendte køreplaner (gældende 13/11-udgave, forkastet
  kladde og 27/10-originalen) samt CSV-importen fra 27/10. Ingen promptfil, ingen nye
  beskrivelsesdokumenter.
- `Steamgears_Clockwork_Punk_Design.json` (Drive-ID `1Hop0UPP4fn8BU2KM689g43vfWjkrU85C`) er hentet og
  læst: det er et **webtema** (farver, typografi, knap- og kortkomponenter), ikke billedprompts.
- Repoet: AI-billedgenerering blev fjernet fra Autopilot i v7.85.0, og der er ingen
  billedgenerator at kalde. `PIXAZO_API_KEY`, `GEMINI_API_KEY` og `OPENROUTER_API_KEY` er **ikke sat**.
- 50-plakatkataloget (13/11) har 50 prompts, men til serierne AIR/ENG/RAIL/NAT/ARC — **0** for CLK/DSL.

## Leveret i Drive (nye Google Docs, ikke slettet eller ændret andet)

| Dokument | Mappe | ID |
| --- | --- | --- |
| Clockwork Fantasy & Gotik - billedprompts til de 5 plakater (14-11-2026) | Clockwork Fantasy & Gotik | `1QxAYh5Fl9c3JwcWLEj2ynpZnzAc3qwbljg-Jksy3Rco` |
| Dieselpunk - billedprompts til de 5 plakater (14-11-2026) | Dieselpunk | `1D36IZw-FT_AZvF5j5Le8B2lAOWtUFB8Yw5mjDr9Y8V8` |

Begge dokumenter indeholder fælles tryk-krav (portræt 5:7, 5906×8268 px ved 300 dpi, rolig mørk
nederste femtedel, ingen tekst/vandmærke/ramme) og én prompt pr. SKU.

## De ti prompts (sammendrag)

**Clockwork** — palet: midnight black, antique brass, teal-skygger med svage arcane blue accenter.

1. **SG-CLK-01 Cathedral of the Eternal Pendulum** — gotisk kirkeskib om natten, kolossal messing-astronomiur
   og kæmpependul over midtergangen, måneskin gennem et rose-vindue af tandhjul, stearinlys på forgyldte escapementer.
2. **SG-CLK-02 The Alchemist's Astral Orrery** — stearinlyst gothisk lærdeværelse med roterende messing-orrery,
   graverede stjernekort i guld, sølv og lapis, glødende aether-flasker og lædergrimoirer.
3. **SG-CLK-03 Porcelain Automaton Violinist** — porcelæns- og messingautomat med violinen i et månebelyst
   konservatorium, revnet elfenbensansigt, synlige guld-fjedre og sølv-fingertandhjul.
4. **SG-CLK-04 Arch-Mage of the Brass Sanctum** — hættet lærdomagiker med runegraveret tandhjulsstav,
   svævende astrolabier, blå runer og en mekanisk messingravn på arbejdsbordet.
5. **SG-CLK-05 Gargoyle of the Belltower Gearworks** — bevinget sten- og bronze-gargoyle på et gothisk
   klokketårn over tågede victorianske tage, glødende ravøjne og det oplyste urskive bagved.

**Dieselpunk** — palet: gunmetal, patineret stål, sodsort og glødende rav med kølige teal-skygger.

1. **SG-DSL-01 Iron Sky Squadron** — sværm af tunge to-motorers dieselfly med nittet panser over en
   industrimetropol i storm, ildudstødning og søgelys gennem røg.
2. **SG-DSL-02 Trench Walker MK-IV** — kolossal tobenet dieselmekanik med valsestål, dobbelte udstødningsstabler
   og ravglødende cockpit-visirer i mudret, granatramt landskab.
3. **SG-DSL-03 Metropolis Noir Detective** — trenchcoat-detektiv i regn under Art Deco-skyskrabere,
   stålveteran-biler og en fortøjet diesel-zeppelin, neonreflekser i våd asfalt.
4. **SG-DSL-04 Heavy Foundry Titan** — monumentalt støberi med flydende jern, kæmpe dieselturbiner,
   nittede portalkraner og arbejdere i silhuet på gangbroerne.
5. **SG-DSL-05 Valkyrie of the Oil Fields** — kvindelig pilot i læderflyverdragt og svejsebriller ved en
   bæltekørende dieselrig med krom-V12-udstødninger, olierigge og sort røg bagved.

## Hvorfor billederne ikke blev genereret

- Ingen billedmodel er tilgængelig: ingen nøgle i miljøet, og repoets tidligere OpenRouter-billedvej
  blev fjernet i v7.85.0. Der er intet sted at sende prompten hen.
- En nøgle kræver konto og betaling — det er en beslutning, kun ejeren kan tage. Anbefalet vej:
  **WaveSpeedAI** (én nøgle, REST, mange modeller, `WAVESPEED_API_KEY`). Alternativer: Pixazo.ai og ImagineArt.
- De rå billeder, der allerede ligger i Drive, er 848–896 × 1200–1264 px. Uanset generator skal
  output opskaleres **5,6–6,8×** og efterbearbejdes, før 50×70 kan trykkes — og AI-linjen må først
  sættes pr. design, når det er gjort.

**Næste skridt:** læg `WAVESPEED_API_KEY` i projektets secrets, så køres de ti prompts som et batch,
filnavne `sg-clk-0X-…png` / `sg-dsl-0X-…png`, og originalerne lægges i tema-mapperne i Drive —
ikke i Mediebiblioteket og ikke på produkterne, før opskalering og efterbearbejdning er verificeret.
