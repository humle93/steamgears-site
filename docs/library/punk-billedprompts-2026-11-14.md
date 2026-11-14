# Alle 19 plakatmotiver genereret lokalt og kontrolleret (14/11 2026)

**Kort:** Beskrivelserne ligger i **`Steamgears – ChatGPT-prompter (19 motiver)`** i billedmappen — ikke i
Gemini-mappen. Ud fra dem er **alle 19 motiver** (R1–R5, K1–K7, D1–D3, C1–C3) genereret lokalt med SD-Turbo
på CPU og gemt som `sg-<kode>-local-a.png` i `posters/punk-2026-11-14/`. Hvert motiv er åbnet og tjekket mod
sin beskrivelse, og bundkanten er renset for signatur/vandmærke. **Opløsning:** 512×768 native, opskaleret
2× til 1024×1536 — se »Opløsning« nedenfor.

## Kilden: hvad der faktisk ligger i Drive (målt 14/11)

Søgt på tværs af hele drevet: `name contains 'Gemini'` (inkl. skraldespand), `fullText contains 'Gemini'`,
alle billedfiler ændret siden 1/11, og alt med Clockwork/Dieselpunk/CLK/DSL i navnet.

| Fil | ID | Type | Størrelse | Indhold |
| --- | --- | --- | --- | --- |
| Steamgears – ChatGPT-prompter (19 motiver) | `12OrcA_LaeU3FCmUSIsQz0ig1jmxGGjI_V5EEMyr6nwA` | Google Doc | 8 091 B | **Autoritativ.** 19 motiver med fuld prompt pr. motiv |
| LÆS MIG – sådan skal billederne ligge | `1Go_JkhRSgDlGtY-AIwLWvTU3fnRWPYzfg0IBw_UHl9Y` | Google Doc | 4 360 B | Filnavne `sg-[kode]-[kilde]-[bogstav].png`, 2:3/3:4, ≥2 000 px for udvælgelse, ≥4 100 px trykklar, ≥8 300 px ideelt, ingen tekst/vandmærke, rolig nederste femtedel |
| Gemini (mappe) | `13xXtakV63vzCboeQ08_gttFadHzFAKkx` | Folder | — | **Ét** dokument: `Steamgears_Automations_og_Pipeline_Dokumentation.md` (`1kkHrUox5J1JYUWm0JbfFL3v2NGkmAfiIvFvXcG5dGM0`) — Content/SEO-, Pinterest- og Command Center-pipeline. **Ingen billedbeskrivelser** |
| Gemini  (mappe, med mellemrum) | `1yjVw-1UNreGlXm4jjW13c9jRJLH0uSv8` | Folder | — | ZIP-arkiver og PHP/plan-dokumenter. Ingen motiver |
| Arbejdsseddel til Gemini (28/10) | `1h3jbfq1fa2Td0FKUSKgK3T0imgwLWqEom8OnMs1qExw` | Google Doc | 30 302 B | Gammel arbejdsseddel, ikke de nye motiver |
| Geminibilleder – Steamgears Plan (25/10) | `19bgAtvtToWCTbWO95b-3tstfMufRlYdO` | Markdown | 10 897 B | Ældre 50-plakat-plan |

Skraldespanden indeholder kun en tom dublet af 25/10-planen (`1Xm_ZUaQYWmptlMTK26Dp94vV6smV6CB_`, 0 B).
Ingen sidecar-filer og ingen billedmetadata med beskrivelser.

## Vejen: lokal SD-Turbo på CPU

| Vej | Resultat | Præcis fejl |
| --- | --- | --- |
| Freebuff eget API | ✗ | 280 chat-modeller, **0 billedmodeller**; `/api/v1/paid/images/generations`, `/api/v1/images/generations`, `/api/v1/free/models`, `/api/v1/paid/media` → **404** |
| WaveSpeedAI | ✗ | Kræver `WAVESPEED_API_KEY`, som ikke findes i miljø, `.env` eller `.dev.vars` |
| Pollinations | ✗ | Første kald 200, derefter **402 Payment Required**; kun modellen `sana`, output 627×940 **med vandmærke** |
| Sana Sprint 0.6B (lokal) | ✗ | Loader på 3,4 s, men 512×768 med 2 steps blev **ikke færdig inden for 280 s** (bf16 og fp32) |
| **SD-Turbo (lokal, CPU)** | ✓ | 1 step, 512×768, **40–46 s pr. billede**, ingen vandmærke |

Ingen nøgler fandtes (`WAVESPEED_API_KEY`, `POLLINATIONS_TOKEN`, `OPENAI_API_KEY`, `REPLICATE_API_TOKEN`,
`GEMINI_API_KEY`, `GOOGLE_API_KEY`, `HF_TOKEN`, `FAL_KEY`, `STABILITY_API_KEY`). Derfor lokal generering i
`.venv` (gitignoreret): `torch 2.15.0+cpu`, `diffusers 0.43.0`, `stabilityai/sd-turbo`.
Kørslen er reproducerbar med [`tools/punk_poster_local.py`](../../tools/punk_poster_local.py):

```bash
.venv/bin/python tools/punk_poster_local.py r1:201 k2:202 …   # KODE:SEED
```

## De 19 filer

To seeds pr. motiv (201/202, og 203–208 hvor motivet ikke ramte), bedste valgt. Alle er 2:3 i **1024×1536 px**.

| Kode | Motiv | Seed | Bemærkning |
| --- | --- | --- | --- |
| R1 | Luftskibspiraten ved roret | 201 | Kaptajn ved rattet, mørkerød frakke, tricorn med briller ✓ |
| R2 | Kaptajnen i kortrummet | 201 | Kaptajn ved kortbord i messingkabine ✓ |
| R3 | Entringen | 205 | Figur på tov mellem luftskibe; 201/202/203/204 ramte ikke og blev kasseret |
| R3B | The Storm Plank | 201 | Spring over plank mellem luftskibe ✓ |
| R4 | Mekanikeren i maskinrummet | 202 | 201 gav en dubletfigur og blev kasseret |
| R5 | Havnen i skyerne | 201 | Flyvende havn med fortøjede luftskibe ✓ |
| K1 | Luftskibet over byen | 201 | Kobberluftskib over victoriansk by ✓ |
| K2 | Strix mechanica | 201 | Mekanisk ugle med rav-øjne og urværk ✓ |
| K3 | Cor Horologium | 201 | Kobberhjerte med urværk på cremefarvet papir ✓ |
| K4 | Luftskibskaptajnen – portræt | 204 | 201/202/203 gav en mand; 204 er kvinden i kamérammen ✓ |
| K5 | Skyskraberen – Neodeco | 207 | 201–206 manglede zeppeliner eller havde skiltetekst; 207 rammer ✓ |
| K6 | Rosa mechanica | 201 | Kobberrose, botanisk plade ✓ |
| K7 | Det mekaniske cirkus | 201 | Stribet telt, akrobat i trapezen, stjernehimmel ✓ |
| D1 | Zeppelin Metropolis | 101 | Art deco-by, zeppeliner ved masten, våde gader ✓ |
| D2 | The Diesel Aviatrix | 103 | Aviator ved sølvfly; **bund 5 % beskåret** (signatur) |
| D3 | Iron Titan | 102 | Kolossal dieselmaskine i havn ved solnedgang ✓ |
| C1 | The Clockmaker's Workshop | 104 | Messingautomat på værkstedet ✓ |
| C2 | The Ornithopter | 101 | Tandhjuls-ornithoptere over renæssanceby ✓ |
| C3 | The Automaton Lady | 101 | Porcelænsautomat i grøn fløjl blandt globusser ✓ |

**Kontrol:** hvert motiv er åbnet og sammenholdt med sin beskrivelse, og de nederste 8 % af hver fil er
inspiceret særskilt. De 13 nye fik **4 %** skåret af bunden som sikkerhed mod signatur; D2 fik 5 %.
Resultat: **0** forekomster af tekst, tal, logo, signatur eller vandmærke.
Kontaktarket `posters/punk-2026-11-14/kontaktark.png` viser alle 19 i rækkefølge R1→C3.

## Opløsning — og hvorfor sættet er ensartet

SD-Turbo kan godt køre 768×1152, men den er trænet på 512 og begynder at gentage anatomi ved større
native størrelser; samtidig ville det tage ca. **100 s pr. billede** i stedet for 44 s. De seks første
motiver (D1–D3, C1–C3) var allerede lavet i 512×768, og opgaven var at holde sættet ensartet frem for at
genkøre dem. Derfor er **alle 19** 512×768 native og opskaleret 2× med Lanczos til 1024×1536.

Det er over grænsen for udvælgelse (2 000 px), men **under** trykklar (4 100 px) og langt under 50×70-kravet
(5 906×8 268 px). En dedikeret opskalering eller et større diffusermodel-kør skal til, før filerne kan sendes
til Gelato. Det er den eneste åbne post.

## Filer

- `posters/punk-2026-11-14/sg-<kode>-local-a.png` — de 19 færdige filer (1024×1536)
- `posters/punk-2026-11-14/raw/<kode>-<seed>.png` — alle rå kørsler, inkl. de kasserede varianter
- `posters/punk-2026-11-14/manifest-local.json` — motiv, seed, redigering og størrelse pr. fil
- `posters/punk-2026-11-14/kontaktark.png` — alle 19 samlet
