# Dieselpunkt + clockwork punk: de seks motiver er lavet lokalt og verificeret (14/11 2026)

**Kort:** Beskrivelserne lå ikke i Gemini-mappen, men i **`Steamgears – ChatGPT-prompter (19 motiver)`**
i billedmappen. De er den autoritative kilde og erstatter de tidligere prompts. Ud fra dem er de seks
punk-motiver — **D1, D2, D3, C1, C2, C3** — genereret lokalt med SD-Turbo på CPU, fordi hverken Freebuff,
WaveSpeed, Pollinations eller Sana kunne levere. Alle seks er åbnet og kontrolleret: rigtigt motiv,
ingen tekst/signatur/vandmærke. Opløsningen er **1024×1536** — nok til udvælgelse, **ikke** til tryk
(se »Hvad der stadig mangler«).

## Kilden: hvad der faktisk ligger i Drive (målt 14/11)

Søgt på tværs af hele drevet: `name contains 'Gemini'` (inkl. skraldespand), `fullText contains 'Gemini'`,
alle billedfiler ændret siden 1/11, og alt med Clockwork/Dieselpunk/CLK/DSL i navnet.

| Fil | ID | Type | Størrelse | Indhold |
| --- | --- | --- | --- | --- |
| Steamgears – ChatGPT-prompter (19 motiver) | `12OrcA_LaeU3FCmUSIsQz0ig1jmxGGjI_V5EEMyr6nwA` | Google Doc | 8 091 B | **Autoritativ.** 19 motiver: R1–R5, K1–K7, D1–D3, C1–C3 med fuld prompt pr. motiv |
| LÆS MIG – sådan skal billederne ligge | `1Go_JkhRSgDlGtY-AIwLWvTU3fnRWPYzfg0IBw_UHl9Y` | Google Doc | 4 360 B | Filnavne `sg-[kode]-[kilde]-[bogstav].png`, 2:3/3:4, ≥2 000 px for udvælgelse, ≥4 100 px trykklar, ≥8 300 px ideelt, ingen tekst/vandmærke, rolig nederste femtedel |
| Gemini (mappe) | `13xXtakV63vzCboeQ08_gttFadHzFAKkx` | Folder | — | **Ét** dokument: `Steamgears_Automations_og_Pipeline_Dokumentation.md` (`1kkHrUox5J1JYUWm0JbfFL3v2NGkmAfiIvFvXcG5dGM0`) — Content/SEO-, Pinterest- og Command Center-pipeline. **Ingen billedbeskrivelser** |
| Gemini  (mappe, med mellemrum) | `1yjVw-1UNreGlXm4jjW13c9jRJLH0uSv8` | Folder | — | ZIP-arkiver og PHP/plan-dokumenter. Ingen motiver |
| Arbejdsseddel til Gemini (28/10) | `1h3jbfq1fa2Td0FKUSKgK3T0imgwLWqEom8OnMs1qExw` | Google Doc | 30 302 B | Gammel arbejdsseddel, ikke de nye motiver |
| Geminibilleder – Steamgears Plan (25/10) | `19bgAtvtToWCTbWO95b-3tstfMufRlYdO` | Markdown | 10 897 B | Ældre 50-plakat-plan |

Skraldespanden indeholder kun en tom dublet af 25/10-planen (`1Xm_ZUaQYWmptlMTK26Dp94vV6smV6CB_`, 0 B).
Ingen sidecar-filer, ingen billedmetadata med beskrivelser, ingen CLK/DSL-filer ud over de kendte køreplaner.

## De seks endelige prompts (brugt ordret)

SD-Turbo læser maks. 77 CLIP-tokens, så de fulde dokumentprompts blev komprimeret til motivets kerne
uden at ændre indholdet. Rækkefølgen er den i dokumentet.

- **D1 – Zeppelin Metropolis:** Art deco metropolis at night, huge riveted diesel zeppelin docked at a skyscraper mooring mast, searchlight beams, rain-slick streets, vintage cars, smoke, gold and black palette, cinematic digital painting.
- **D2 – The Diesel Aviatrix:** woman aviator in brown leather jacket, flying helmet and goggles standing beside a silver propeller airplane on an airfield at dawn, hangars behind, heroic, oil painting, brush strokes, no text.
- **D3 – Iron Titan:** Colossal riveted diesel walking machine striding through an industrial harbour at sunset, black smoke, cranes, cargo ships, tiny workers, gunmetal rust and brass, dramatic low angle, cinematic digital painting.
- **C1 – The Clockmaker's Workshop:** clockwork automaton figure of carved walnut wood and polished brass standing on a workbench in a Renaissance inventor workshop, visible gears and springs, parchment drawings, astrolabes, candlelight, cinematic digital painting, no text.
- **C2 – The Ornithopter:** Wooden clockwork ornithopter with canvas wings and brass gears flying over a Renaissance hilltop city, terracotta roofs, bell towers and cypress trees, golden hour, cinematic digital painting.
- **C3 – The Automaton Lady:** Elegant female clockwork automaton, porcelain face, brass joints, deep green velvet Renaissance gown, standing in a cabinet of curiosities among globes and astrolabes, candlelight, cinematic digital painting.

## Vejen der virkede — og de fem der ikke gjorde

| Vej | Resultat | Præcis fejl |
| --- | --- | --- |
| Freebuff eget API | ✗ | `GET /api/v1/paid/models` = **280 chat-modeller, 0 billedmodeller**; `/api/v1/paid/images/generations`, `/api/v1/images/generations`, `/api/v1/free/models`, `/api/v1/paid/media` → **404** |
| WaveSpeedAI | ✗ | Kræver `WAVESPEED_API_KEY`, som ikke findes i miljø, `.env` eller `.dev.vars` |
| Pollinations (nøglefri) | ✗ | Første kald **200**, derefter **402 Payment Required** på alle varianter; eneste model er `sana`, output **627×940** med **vandmærke** `pollinations.ai` |
| Sana Sprint 0.6B (lokal) | ✗ | Loader fint (3,4 s), men 512×768 med 2 steps blev **ikke færdig inden for 280 s** (bf16 og fp32) på 2 CPU-kerner |
| SD-Turbo (lokal, CPU) | ✓ | 1 step, 512×768, **43–46 s pr. billede**, ingen vandmærke |

Ingen nøgler fandtes: `WAVESPEED_API_KEY`, `POLLINATIONS_TOKEN`, `OPENAI_API_KEY`, `REPLICATE_API_TOKEN`,
`GEMINI_API_KEY`, `GOOGLE_API_KEY`, `HF_TOKEN`, `FAL_KEY`, `STABILITY_API_KEY` — alle manglede.
Derfor lokal generering i en venv (`.venv`, gitignoreret): `torch 2.15.0+cpu`, `diffusers 0.43.0`,
`stabilityai/sd-turbo`.

## Filer og verifikation

 Tolv rå billeder blev kørt (to seeds pr. motiv) og derefter bedømt ét ad gangen. Seks valgt:

| Fil | Motiv | Seed | Kontrol |
| --- | --- | --- | --- |
| `sg-d1-local-a.png` | Zeppelin Metropolis | 101 | Zeppeliner ved skyskrabermast, våde gader, guld/sort ✓ |
| `sg-d2-local-a.png` | The Diesel Aviatrix | 103 | Kvinde i læderjakke ved sølvfly ✓ · **bund 5 % beskåret** for at fjerne signatur |
| `sg-d3-local-a.png` | Iron Titan | 102 | Kolossal dieselmaskine i havn ved solnedgang ✓ |
| `sg-c1-local-a.png` | Clockmaker's Workshop | 104 | Messingautomat på værksted med ure og astrolabier ✓ |
| `sg-c2-local-a.png` | The Ornithopter | 101 | Tandhjuls-ornithoptere over renæssanceby ✓ |
| `sg-c3-local-a.png` | The Automaton Lady | 101 | Porcelænsautomat i grøn fløjl blandt globusser ✓ |

Kontrolleret på alle seks: åbnet visuelt, og de nederste 8 % af hver fil er inspiceret separat —
**0** forekomster af tekst, tal, logo, signatur eller vandmærke. Format 2:3, **1024×1536 px**, ca. 1,8–2,1 MB.
Råfiler og seeds ligger i `posters/punk-2026-11-14/raw/`, valgene i `manifest-local.json`.

## Hvad der stadig mangler

- **Trykopløsning.** Originalerne er 512×768 og er opskaleret 2× til 1024×1536. Det er over grænsen for
  udvælgelse i butikken, men **under** kravet til 50×70 (5906×8268 px). En dedikeret opskaleringsmodel
  (eller et større diffusermodel-kør) skal til, før filerne kan sendes til Gelato.
- De øvrige 13 motiver i dokumentet (R1–R5, K1–K7 og resten) er ikke genereret i dette pas.
- Push af commit'en er blokeret af tre urelaterede, untracked filer i worktreen (se næste pas).
