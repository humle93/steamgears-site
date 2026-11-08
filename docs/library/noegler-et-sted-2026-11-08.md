# Alle nøgler på én side — med ejerskab og ændringshistorik

**Målt og bygget 8/11 2026 · Steamgears Autopilot 7.62.0**

Keys-siden (P1.18, 7.47.0) samlede allerede alle 15 hemmeligheder på én fane, så en nøgle ikke længere
lå på den fane der tilfældigvis nævnte den. To ting manglede stadig, og begge blev målt før noget blev
ændret:

1. **Hvem ejer kontoen, og hvorfor findes nøglen?** Kortet sagde hvad tjenesten gør, men ikke hvis konto
   det er, og hvad der stopper med at virke uden nøglen. Det er den slags der først bliver dyrt, når en
   nøgle skal roteres et halvt år senere.
2. **Hvad skete der ved en udskiftning?** Der var ingen spor: en ny værdi overskrev den gamle, og en
   gammel nøgletest kunne blive stående som «✓» på en nøgle der ikke længere var den samme.

## Hvad der blev bygget

### Ejerskab og begrundelse pr. kort

Hver af de 13 udbydere i `SGAP_Keys::PROVIDERS` har fået to felter:

| Felt | Betydning | Vises som |
| --- | --- | --- |
| `owner` | Hvilken konto nøglen tilhører — altid ejerens egen, aldrig en delt eller lånt konto | **Belongs to** |
| `why` | Hvad der stopper med at virke uden nøglen, og om den er valgfri | **Why it is here** |

Teksten står på kortet og gentages i en sammenklappelig tabel nederst på siden, én række pr. nøgle
(15 rækker for 13 kort, fordi Pinterest-kortet ejer tre felter). `SGAP_Keys::documentation()` udstiller
præcis samme tekst som data, så dokumentation og skærm ikke kan glide fra hinanden:

```php
SGAP_Keys::documentation();
// [['key'=>'ai_api_key','label'=>'AI API key','provider'=>'ai','title'=>'AI text',
//   'owner'=>'You — your own AIMLAPI or Anthropic account, paid by you.',
//   'why'=>'Without it nothing writes: …','optional'=>false,
//   'state'=>'saved','source'=>'database'], …]
```

`source` er `database`, `wp-config.php` eller `not set` — den samme sandhed som kortets status-pille.

### Ændringshistorik uden nøglen

`SGAP_Keys::record($key, $old, $new)` skriver én række pr. ændring til optionen `sgap_key_changes`
(ikke autoloadet, loft 120 rækker). Rækken indeholder:

| Felt | Eksempel | Hvorfor |
| --- | --- | --- |
| `key` | `publer_api_key` | hvilken nøgle |
| `action` | `set` / `changed` / `cleared` | hvad der skete |
| `chars` | `68` | længden, nok til at se at det er en anden slags nøgle |
| `fingerprint` | `4ca64038` | første 8 tegn af sha256 — sammenlignelig, ikke reversibel |
| `at` | `2026-11-08 14:02:11` | hvornår |
| `by` | brugernavn, `cron` eller `system` | hvem |

**Værdien gemmes aldrig.** Det er ikke en antagelse: både testen og den live målte kørsel sammenligner
`serialize()` af hele loggen mod den værdi der blev skrevet, og kræver nul forekomster.

To skriveveje skal dækkes, og det er fælden:

- **Indstillingsformularen** gemmer gennem `options.php`, som kalder `update_option()` direkte. Derfor
  lytter `SGAP_Keys::init()` på `update_option_sgap_<key>` og `add_option_sgap_<key>`.
- **Abilities, chat, token-fornyelser og wizard** går gennem `SGAP_Settings::set()`, som ikke findes i
  formularens sti. Den kalder `record()` selv.

En statisk spærre pr. nøgle (`$wrote`) gør at den samme skrivning ikke bliver logget to gange, når begge
veje ser den. En nøgle der er sat i `wp-config.php` logges bevidst **ikke**: databasen bliver ganske vist
skrevet, men den konstante værdi vinder, så der er ingen rotation at vise.

### Cachen følger med nøglen

En roteret nøgle med en gammel cache ligner en død nøgle: konto-listen eller modelkataloget blev hentet med
den forrige værdi og svarer 401 indtil den udløber. `SGAP_Keys::flush()` rydder derfor pr. nøgle:

| Nøgle | Rydder |
| --- | --- |
| `ai_api_key` | `sgap_ai_catalog_aimlapi`, `sgap_ai_catalog_anthropic`, `sgap_ai_catalog_openai_compatible` |
| `image_api_key` | `sgap_ai_catalog_aimlapi` |
| `publer_api_key`, `publer_workspace_id` | `sgap_publer_accounts` |
| `printful_api_key`, `printful_store_id` | `sgap_printful_costs` |
| `bluesky_app_password` | `sgap_bluesky_session` |
| alle | `sgap_dashboard_stats` og den gemte nøgletest (`sgap_key_status`) |

Den gamle nøgletest bliver slettet, fordi et «✓» fra den forrige nøgle er værre end ingen test.

## Målinger

**Før passet (målt i repoet):** `plugins/steamgears-autopilot/includes/keys.php` havde 13 kort og
`covered/status/tally/last_test/card/panel`, men intet `owner`, intet `why`, ingen log og ingen cache-
rydning; `plugins/tests/keys.php` havde 15 checks. Smoke stod på **1027 PASS / 0 FAIL** (7.61.0).

**Efter passet:**

| Kontrol | Resultat |
| --- | --- |
| `php -l` på de ændrede filer | ren |
| Smoke | **1049 PASS / 0 FAIL** (1027 → 1049, **+22**) |
| `python3 tools/fejlscan.py` | **0 fund** (version, zip, includes, bibliotek, hemmeligheder) |
| Zip | `steamgears-autopilot-v7.62.0.zip` **354 369 bytes / 46 filer**, md5 `fc1eb680e649d4168618b422bfcfd74d` |
| Live-installation | `plugin get --field=status` → **active**, `--field=version` → **7.62.0** |
| Live `documentation()` | **15 rækker**, 15 med `owner`, 15 med `why` |
| Live `record()` | `changed`, 21 tegn, fingerprint `4ca64038` = `substr(sha256('live-new-value-123456'),0,8)`, **0 forekomster af værdien** i `serialize()` |
| Live side-render | **13 kort**, 13 × `Belongs to`, 13 × `Why it is here`, **15** rækker i ejer-tabellen, tally **3 af 15** |
| Live historik-linje | med en indskudt testrække: 2 forekomster af `sgap-key-history` (1 i CSS + 1 på kortet), pille «Key history», tid, «32 characters» og fingerprint vist — testrækken slettet igen |
| Live log efter test | **0 rækker** (ingen rigtig nøgle blev rørt) |

De 22 nye checks dækker: ejerskab og begrundelse pr. kort, at `documentation()` har præcis én række pr.
nøgle i kortrækkefølge, at `source` er en kendt værdi, at siden viser begge felter og ejer-tabellen, at
hverken side eller log indeholder en gemt værdi, at en nøgle gemmes som `set` med længde og fingerprint,
at samme værdi igen **ikke** tæller som rotation, at en ny værdi bliver `changed` med et nyt fingerprint,
at tømning bliver `cleared` uden hash, at en `wp-config.php`-konstant ikke logges, at den gamle nøgletest
slettes, og at de rigtige cachenavne bliver ryddet.

## Fælder undervejs

- **`novamira` 1.3.6 kræver `@` foran filnavnet:** `--input <fil>` giver «Ability input must be valid
  JSON» for et fuldt gyldigt JSON-dokument; `--input @<fil>` virker. Det kostede en runde på otte chunks.
- **`do_action('page_sgap-settings')` giver tomt HTML i WP-CLI.** Siden hænges på `admin_menu`, som ikke
  kører i den kontekst. Kald `SGAP_Setup::settings_page()` direkte når siden skal måles uden browser.
- **`sgap-key-history` står også i den inlinede CSS**, så en optælling af markup skal trække CSS-baseline
  fra (1) før den siger noget om hvor mange kort der har en historik.
- **`delete_site_transient()` findes ikke i smoke-stubben.** `flush()` tjekker `function_exists()` og kalder
  også `delete_transient()`, så navnene kan testes uden at testfilen skal ændres.
- **Historik-linjen vises først efter den første rigtige rotation.** En tom log er korrekt adfærd, ikke en
  manglende funktion — derfor blev render-stien målt med en midlertidig række der blev slettet igen.

## Filer

- `plugins/steamgears-autopilot/includes/keys.php` — `owner`/`why` på alle 13 udbydere,
  `documentation()`, `init()`, `record()`, `changes()`, `last_change()`, `fingerprint()`, `flush()`,
  `flushed()`, ejer-tabel og historik-linje på kortet.
- `plugins/steamgears-autopilot/includes/core.php` — `SGAP_Settings::set()` logger nu også ændringer.
- `plugins/steamgears-autopilot/includes/admin-ui.php` — styling af `.sgap-key-owner`, `.sgap-key-history`
  og `.sgap-key-owners`.
- `plugins/tests/keys.php` — 15 → **37** checks i filen.
- `plugins/steamgears-autopilot/steamgears-autopilot.php` — 7.62.0 (header og `SGAP_VERSION`).

## Procent

| Modul | Før | Efter | Δ | Grundlag |
| --- | --- | --- | --- | --- |
| Steamgears Autopilot — plugin (P1.18 allerede lukket) | 98,0 % | 98,0 % | 0 | 29,85 af 30,45 d — ingen ændring; tilføjelsen er en forbedring af et lukket punkt og flytter ikke dagtallet |
