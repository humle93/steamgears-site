# Agenternes feedback-loop — fra fast prompt til målte tal

**Målt 10/11 2026 · Autopilot 7.70.0 · P8 i `projektstatus.json`**

## Sammenfatning

Alle agenterne i projektet skrev indtil nu ud fra en **fast** prompt og ét snapshot af den side de arbejdede
for. De blev aldrig klogere af det siden målte: en vinkel med 120 klik og nul ordrer blev valgt igen, en
kanal der var gået i stå fik flere poster, og den ugentlige SEO-plan talte stadig om **39 produkter i USD**,
selv om butikken havde **20 i DKK**.

P8 vender det om. `SGAP_Coach` læser sidens egne tal og lægger et kort, målt brief i prompten hos **den
daglige forfatter, kampagne-agenten, Studio-artiklen og Studio-SEO-redaktøren**. En daglig cron omsætter de
samme tal til højst 12 daterede regler, som vises på dashboardet og kan hentes af en agent gennem abilityen
`agent-brief`. Repo-siden fik samme princip: `tools/agent_brief.py` måler det levende katalog og skriver
`docs/agent-brief.md`, som `seo_agent.py` nu læser før den planlægger.

## Problemet, konkret

| Flade | Hvad den læste | Hvad den ikke kunne se |
| --- | --- | --- |
| Daglig forfatter (`SGAP_AI::prompt`) | Sidens titel, type og 120-ords resumé | At kanalen havde 120 klik og 0 ordrer, at vinklen var prøvet, at feedet var tomt |
| Kampagne-agenten (P6) | Samme prompt + kampagnemålet | Samme som ovenfor; målet er en retning, ikke en måling |
| Content Studio (`SGAP_Studio::prompt`) | Emne, søgeord, længde og en fast SEO-regelliste | At kataloget var skrumpet, at valutaen var skiftet, hvad der sælger |
| Studio-SEO-redaktøren (`optimize`) | Sidens tekst og nuværende meta | Samme som ovenfor |
| Ugentlig SEO-agent (`seo_agent.py`) | `docs/seo.md`, `knowledge_base/` og et 20-siders live-snapshot | At butikken havde 20 produkter i DKK og et andet prisinterval |

Fællesnævneren er ikke en manglende model eller et manglende kald: det er en **manglende hukommelse**. Hver
kørsel startede fra den antagelse prompten blev skrevet med.

## Hvad der er bygget (PHP)

`includes/coach.php` er ét modul med fem læsende funktioner og én skrivning:

| Funktion | Læser | Giver |
| --- | --- | --- |
| `facts()` | `wp_count_posts`, WooCommerce-valutaen, `get_posts`, `sgap_ai_drafts` | Produkter, kladder, indlæg, valuta, alder på nyeste side, ventende kladder |
| `outcomes($days = 30)` | **én** grupperet forespørgsel på `sgap_events` | Pr. kanal og pr. vinkel: publiceringer, klik, ordrer, omsætning, seneste publicering og dage siden |
| `lessons()` | Optionen `sgap_agent_lessons` | De gemte regler med bevis og første dato |
| `learn()` | `outcomes()` + `facts()` | Genberegner reglerne, beholder første dato, skriver kun når noget ændrede sig |
| `brief()` | Det hele, cachet i en time | Promptblokken; filteret `sgap_agent_brief` kan erstatte den |
| `summary()` / `card()` | Det hele | Ability-svaret og dashboardkortet |

**Reglerne er bevidst konservative.** En klik-regel kræver mindst **25 klik** på kanalen eller vinklen, så en
stille uge ikke lærer agenten noget, tallene ikke understøtter. En kanal kaldes først stille efter **14 dage**
inden for vinduet, og en kladde-baglog bliver først en regel ved **20** ventende kladder. Der er højst **12**
regler, salgsregler før advarsler, og hver regel bærer sit eget bevis (`120 clicks, 0 orders`).

**Briefen er fakta, ikke pynt.** Den består af fire dele — hvad siden er, hvad kanalerne gjorde, hvad
vinklerne gjorde, og hvilke lærerige regler der gælder — og slutter med: *»use only these numbers. Do not
invent metrics, prices, discounts or stock«*. En måling der ikke findes, udelades; en tom butik får
»no clicks or orders measured in the window« i stedet for et gæt.

**Fladerne er de eksisterende.** Ingen ny tabel, ingen indstilling, ingen side: briefen går ind i de tre
prompts der allerede findes, reglerne ligger i én option (ikke autoloadet), og de kan læses gennem den
read-only ability `agent-brief` og dashboardkortet *What the agents have learned*.

## Hvad der er bygget (repo-siden)

`tools/agent_brief.py` måler det offentlige katalog og skriver `docs/agent-brief.md`. Målt live 10/11 2026:

| Faktum | Målt værdi |
| --- | --- |
| Produkter i kataloget | 20 |
| Valuta | DKK |
| Prisinterval | 165–1 000 DKK |
| Produktkategorier | 9 |
| Publicerede indlæg | 33 |
| Publicerede sider | 32 |
| Nyeste indlæg | 2026-11-08 |

`seo_agent.py` læser filen som en selvstændig kilde efter `docs/seo.md`, og workflowen
`.github/workflows/seo_audit.yml` måler butikken **før** auditten og committer briefen sammen med planen.
Mangler filen, skriver agenten en `::warning` og kører videre på vidensbasen alene — den gætter ikke.

## Målt

- `php plugins/tests/smoke.php`: **1 432 PASS / 0 FAIL** efter rebase over 7.69.1 (1 383 før P8, altså **44
  nye** i `plugins/tests/coach.php`; 7.69.1 lagde 5 til).
- `python3 tools/test_agent_brief.py`: **13 tests, OK** (pris-enheder, kilder der ikke kan læses, dansk
  talformat, `--check` mod både en rigtig og en forkert fil).
- `php -l` rent på alle plugin-filer; `tools/fejlscan.py` uden fund; `projektstatus.py --check` uden drift.
- Briefen er genereret **mod det levende site**, ikke mod en fixture: 20 produkter, DKK, 9 kategorier.

## Ikke verificeret

- **Ingen rigtig AI-kørsel fra sandkassen.** Prompten er bevist at indeholde briefen, og briefen er bevist at
  indeholde de målte tal; om modellen bruger dem bedre, kan først ses når genereringen kører på sitet.
- **7.70.0 er ikke installeret på det levende site.** Zip'en er bygget og byte-identisk, men kræver ejerens
  *Plugins → Tilføj nyt* (repoet er privat, så selvopdateringen kan ikke hente den).
- **Lesson-storen er tom på sitet indtil første cron-kørsel** efter installationen; kortet siger det med
  vilje (»Nothing measured yet«) i stedet for at vise en tom tabel.
- **Tærsklerne (25 klik, 14 dage, 20 kladder) er vurderinger**, ikke målinger. De er valgt så de ikke fyrer på
  en enkelt dag, og de kan ændres i koden uden en migration.

## Fælder undervejs

1. **`number_format_i18n()` findes ikke i smoke-stubben.** Første kørsel døde midt i `learn()`; stubben fik
   funktionen, så plugin'et kan bruge WordPress' egen formatering uden at testene får en falsk fejl.
2. **`get_permalink()` i stubben tager et id, ikke et objekt.** `optimize_prompt()` sender nu `(int) $post->ID`
   — hvilket også er den rigtige signatur at bruge i WordPress.
3. **`SGAP_Admin::table_exists()` svarer gennem samme `get_var`-stub som alt andet.** Testene sætter derfor
   tabelnavnene og `COUNT(*)`-svaret i den rækkefølge funktionerne læser dem; ellers læser `outcomes()` en tom
   tabel og en regel forsvinder.
4. **En `learn()`-kørsel forbruger sin egen stub-kø.** Den anden kørsel i samme test skal sætte køen igen,
   ellers er «uændrede tal» testet mod en tom tabel i stedet for mod de samme tal.
5. **`.github/workflows/seo_audit.yml` må ikke kræve nye secrets.** Briefen læser kun offentlige endpoints, så
   den kører i samme job som auditten uden nøgler.

## Næste skridt

1. Installér `plugins/steamgears-autopilot-v7.70.0.zip` på sitet, og lad `sgap_coach_learn` køre én gang.
2. Læs dashboardkortet: står der regler, og stemmer deres tal med *Last 30 days per channel*?
3. Kør én generering og se, om briefen ændrer vinkelvalget — det er den eneste måde at afgøre, om agenten
   faktisk udvikler sig, og det er ejerens kald at vurdere kvaliteten.
4. Genkør `python3 tools/agent_brief.py` efter hver katalogændring; den ugentlige workflow gør det selv.
