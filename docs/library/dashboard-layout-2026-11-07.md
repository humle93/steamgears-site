# Dashboardet læses oppefra — tre sektioner i stedet for to ulige kolonner

**7.52.0 (P1.19) og 7.54.0 (P1.20) · 7. november 2026 · Steamgears Autopilot**

Dashboardet i wp-admin var ikke grimt, men det var **lige dele**: to kort i venstre kolonne
(`Run now` og kanaltabellen) og otte i højre (AI Chat, Self-check, Scheduler, Engagement,
RSS-feeds, API-nøgler, Command Center, plugin-opdatering). Siden blev tre skærmhøjder lang på
den ene side og næsten tom på den anden, og de kort der svarer på »går det?« lå spredt i den
rækkefølge de var blevet tilføjet — scheduler-status under AI Chat, engagement under Self-check.

Spørgsmålet en ejer stiller først er ikke »hvilke kort findes der«, men »**er der noget jeg skal
gøre?**«. Det spørgsmål havde ingen plads i layoutet.

## Beslutningen: ét spørgsmål pr. sektion

| Sektion | Spørgsmålet den svarer på | Kort |
| --- | --- | --- |
| **Now** | Kører det, og hvad kan jeg trykke på? | Run now · AI Chat · Self-check · Scheduler |
| **Channels** | Hvor poster det, og hvad kom der ud af det? | Tal pr. kanal · Engagement · RSS-feeds · API-nøgler |
| **System** | Hvad holder det i live? | Plugin-opdatering · Command Center · billed-rescue |

Hvert kort står nu under det spørgsmål det besvarer, og rækkefølgen er den rækkefølge man
læser i. Sektionerne er rene overskrifter (`SGAP_UI::section()`) — ikke kort, ikke bokse —
så de kan genbruges på de øvrige sider uden at ændre deres markup.

## `Needs you`-linjen: kun det et menneske kan handle på

Øverst, lige under status-pills, står **én** linje. Den nævner kun fire slags ting:

1. AI-nøglen **dumpede** sin sidste test (`ok === false`).
2. Antal problemer fra det daglige self-check (`sgap_health.problems`).
3. Blokeret publicering, oversat til ord: autopilot slået fra, quiet hours (med det klokkeslæt
   postene går ud), dagligt loft nået, blackout.
4. Kladder der venter på godkendelse.

Er der intet, står der **All clear** — ikke en tom plads. Linjen er en påstand med et regnestykke
bag, ikke en stemning.

Tre ting er bevidst **ikke** med:

- **»Queue er tom«** — det kan være helt rigtigt (og er ikke noget man handler på).
- **»AI-nøgle ikke testet endnu«** — det står allerede i *Get started*-tjeklisten. Med det i
  linjen ville *Needs you* være permanent på en frisk installation, og så læser man den ikke.
- **Grønne pills for ting der kører** — de står stadig i status-pills over linjen, hvor de hører til.

## Engagement: tre tal før detaljen

`SGAP_Engagement::dashboard_card()` viser nu likes, reposts og replies som tre tal over
per-kanal-tabellen (`SGAP_UI::metric()`). Tallene er summen af de snapshots der findes;
reach er stadig `null`, fordi hverken Bluesky eller Mastodon har tallet.

## Mønstret, så det kan gentages

- `SGAP_UI::section(string $title, string $hint = '')` — overskrift i 12 px versaler med en
  linje der toner ud mod højre. Ingen wrapper, intet kort.
- `SGAP_UI::metric(string $label, $value, string $hint = '')` — ét tal med label og en valgfri
  tredje linje. Grid'en (`minmax(110px, 1fr)`) giver 2–4 tal pr. række afhængigt af bredden,
  og to pr. række under 600 px.
- `forced-colors: active` (Windows high contrast) får nu kant på kort, metrik-blokke, pills,
  badges, tabs og kategorichips, og `Highlight` som farve på bjælker og aktive tabs. Uden det
  forsvandt både kortgrænser og den markerede fane i high contrast-tilstand.
- Ingen build-step, ingen `@wordpress/ui`: det hele er pluginnets egen stylesheet.

## Fælder undervejs

1. **Kort der kan returnere tomt.** `SGAP_Setup::checklist_card()`, `SGAP_Auto::health_card()`
   og `SGAP_Rescue::card()` renderer sig selv og springer over når der ikke er noget at vise.
   En sektion må derfor ikke antage et fast antal kort — kolonnerne skal kunne være tomme
   uden at layoutet falder sammen. Det er også grunden til at Command Center-kortet selv
   forsvinder helt, når der hverken er en adresse eller en statuslinje.
2. **`SGAP_UI::css()` printer kun én gang pr. request** (`self::$printed`). En test der leder
   efter nye CSS-regler i sidens HTML fejler, hvis en tidligere side i samme kørsel allerede
   har printet stylesheet'et. Læs `SGAP_UI::stylesheet()` i stedet — den findes netop til det.
3. **Assert på kortets titel, ikke på en tekstlinje.** »Next publish tick« afhænger af om
   cron-stubben svarer med en tid; `Scheduler</h2>` gør ikke. Rækkefølge-checks skal hænge på
   noget der altid findes.
4. **`›Needs you‹` skal ikke kunne opstå af ingenting.** Efter at »ikke testet endnu« blev
   flyttet ud, er den ene vej til at få en permanent advarsel væk, og *All clear* blev en
   tilstand der faktisk kan nås.

## Bredt ud til de øvrige sider (7.54.0)

Mønstret holdt, fordi det er forbudt at være et kort: `SGAP_UI::section()` er en overskrift og en
linje, ikke en boks. De tre sider der stadig var en stak felter, fik hver deres spørgsmål:

| Side | Før | Nu |
| --- | --- | --- |
| **Settings** | en fane åbnede direkte på felt-rækker | hver fane åbner med overskrift og én linje om hvad den afgør (`SGAP_Setup::TAB_HINTS`); Keys har sin egen overskrift |
| **Queue** | én tabel med en statuskolonne | *Waiting* (queued, sending, reminded, pending, approved) og *Done* (published, failed), hver med optælling i overskriften — plus en *Held*-linje øverst |
| **Content Studio** | fire kort i to ens grids, resultatet imellem | *Write* (artikel, guide, AI-billede) og *Fix* (meta for én side, hele sitet, hvad scoren måler), med resultatboksene under hver |

To ting er værd at huske:

- **En statuskolonne er ikke et svar.** Kø-siden havde allerede al information, men »venter« og
  »sendt« stod i samme liste sorteret efter id, så det man skulle handle på lå blandet sammen med
  historikken. Opdelingen krævede ingen SQL-ændring — kun et filter i PHP på de samme 50 rækker.
- **Blokeringen skal stå som ord, ikke som en kode.** `blocked_reason()` returnerer
  `quiet_hours`, `blackout`, `daily_cap_reached` eller `autopilot_disabled`;
  `blocked_message()` har allerede den pæne sætning, så *Held*-linjen genbruger den i stedet for at
  opfinde en anden formulering.

Fælden dukkede op her: **smoke-harnessen havde ingen `mysql2date()`**. Den blev først nødt til at
findes, da en kø-række fik en rigtig `scheduled_at` — indtil da var hver test-række `null`, og den
gren af tabellen var aldrig blevet kørt. Stubben er tilføjet, og de nye checks bruger en række med
både planlagt og publiceret tid, så datogrenen faktisk bliver renderet.

## Hvad er verificeret — og hvad er ikke

- `plugins/tests/smoke.php`: **781 PASS, 0 FAIL** (770 før dette pas, 706 før de to første). Otte checks holder
  dashboardets sektionsrækkefølge, `Needs you` før tal-rækken, hvert korts sektion, opdateringskortet
  under *System*, *All clear*, og at stylesheet'et bærer `.sgap-section-head`, `.sgap-metrics` og
  `forced-colors`. Elleve mere dækker 7.54: hver Settings-fane har overskrift og hint (og hints
  dækker præcis de ni faner), køens *Waiting*/*Done*-opdeling med en sending-række i *Waiting*, og
  Studioets *Write*/*Fix* med resultatet efter hver sektion.
- **Ikke set i en browser.** Der er ingen wp-admin-adgang fra denne worktree, og 7.54.0 er ikke
  installeret på sitet — det kræver ejerens *Plugins → Update*-klik. Renderingen er kontrolleret
  gennem smoke-suitens `ob_start(); do_action('page_sgap-…')`, altså markup og rækkefølge, ikke
  pixels. Farver, luft og hvordan de to kolonner faktisk bryder ved 782 px er **uverificeret**.
