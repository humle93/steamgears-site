# Integrationstesten mod et rigtigt WordPress efter OpenRouter-only (13/11 2026)

**Formål:** efter P12 (7.85.0) var `plugins/tests/integration.php` skrevet om til OpenRouter-only, men
aldrig kørt. Denne kørsel startede fra en worktree uden root, installerede WordPress 7.1.4, MariaDB 10.11
og PHP 8.3 i `/tmp`, kørte `plugins/tests/integration.sh` og rettede det testen fandt. Facit: **254 PASS /
0 FAIL** og ingen plugin-notitser i `debug.log`.

## Opskriften uden root

Ubuntu 24.04-worktreen har `pdo_mysql` i den statiske PHP, men **ikke `mysqli`**, og WordPress kræver
`mysqli`. Der er ingen `sudo` (»no new privileges«), så alt blev hentet som pakker og pakket ud i `/tmp`:

```bash
mkdir -p /tmp/apt/lists/partial /tmp/apt/cache/archives/partial
apt-get -o Dir::State::lists=/tmp/apt/lists -o Dir::Cache=/tmp/apt/cache update
apt-get -o Dir::State::lists=/tmp/apt/lists -o Dir::Cache=/tmp/apt/cache download \
    php8.3-cli php8.3-common php8.3-mysql php8.3-mbstring php8.3-curl php8.3-xml php8.3-zip \
    mariadb-server-core mariadb-client-core mariadb-common liburing2 libnuma1 libaio1t64
for f in *.deb; do dpkg-deb -x "$f" /tmp/rootfs; done
```

- PHP'en kører med `-c /tmp/php-run/php.ini`, hvor `extension_dir` peger på `/tmp/rootfs/usr/lib/php/20230831`
  og rækkefølgen er **`mysqlnd` før `mysqli`** (ellers `undefined symbol: mysqlnd_global_stats`), derefter
  `pdo`, `pdo_mysql`, `mbstring`, `curl`, `xml`, `dom`, `simplexml`, `zip`, `tokenizer`, `phar`.
  `tokenizer` og `phar` er ikke valgfrie: uden dem dør `wp config set` og hele wp-cli.
- **Fælden:** wp-cli kalder sin egen PHP via `PHP_BINARY` uden `-c`. Sæt derfor `PHPRC=/tmp/php-run` i
  miljøet, ellers starter den anden proces uden `phar` og svarer `Class "Phar" not found`.
- MariaDB initialiseres med `mariadb-install-db --no-defaults --basedir=/tmp/rootfs/usr --datadir=/tmp/mysql/data
  --auth-root-authentication-method=normal`, startes på `127.0.0.1:3306`, og `root@127.0.0.1` får en
  adgangskode, så scriptets standarder (`DB_USER=root`, `DB_PASS=root`) passer uden miljøvariabler.
- **Fælden:** wp-cli sender selv `--no-defaults` til `mysql`. En wrapper, der lægger `--no-defaults` oveni,
  giver `unknown option '--no-defaults'` og får `wp db create` til at fejle — så scriptet fortsætter uden
  database. Wrapperen skal kun sætte `LD_LIBRARY_PATH` og videresende argumenterne.
- wp-cli hentes som phar og køres med den samme PHP. `php`, `wp`, `mysql` og `mariadb` lå som shims i
  `/tmp/bin`, som blev sat først i `PATH`.

## Hvad testen fandt, og hvad der blev rettet

1. **Integrationstesten var duplikeret.** 7.85.0-passet indsatte den nye keycheck-blok, men slettede ikke
   den gamle: et `s.index(end)` uden start-offset ramte en tidligere `remove_all_filters()` og kopierede
   ~17 KB af filen. Konsekvensen var, at den gamle AIMLAPI/Anthropic-blok stadig kørte — med 7 checks der
   fejlede — og at `SGAP_AI::ANTHROPIC_URL` gav en fatal senere i filen. Dubletten er fjernet, så filen nu
   er 766 linjer mod 997.
2. **Evergreen-afsnittet brugte stadig Anthropic-formatet** (`type: message`, `output_config`). Det kører nu
   gennem `/chat/completions` med `choices[].message.content`, `response_format: json_object`, `max_tokens`
   1200 og `messages[1]` som prompt — og checken kræver, at `output_config` og `reasoning_effort` *ikke*
   sendes.
3. **Mocks matchede ikke katalogets URL.** `SGAP_AI::fetch_catalog()` kalder `/models` med query-parametre;
   en eksakt URL-sammenligning faldt igennem til det rigtige OpenRouter. Alle tre mocks bruger nu
   `str_starts_with($url, 'https://openrouter.ai/api/v1/models')`. (At netværket virker, blev bevist ved
   et uønsket kald, der returnerede 470 rigtige model-id'er.)
4. **Nøglens form.** Testen brugte `good-or-key`, men pluginnet afviser alt uden `sk-or-` før requesten.
   Nøglen heder nu `sk-or-good`, og der sættes en god nøgle tilbage, før »key without model«-checken, så
   den måler den rigtige fejl.
5. **Dropdownen tæller også det udgåede navn.** Med to udbudte modeller og én gemt, udgået model er der
   fire `<option>`-elementer, ikke tre. Checken kræver nu 4 og at det udgåede navn står i listen.
6. **Automationstesten skrev 4 kladder i stedet for 2.** AI-redaktøren var tændt og svarede ikke med JSON,
   så alle opslag blev holdt; samtidig blev alle indlæg, testen selv havde publiceret, promoveret. Redaktøren
   slås fra i dette forløb (den har sin egen suite), og `sgap_new_content` ryddes før testindlægget.
7. **To fejlbeskeder pegede på den gamle fane.** `json_call()` og `generate()` sagde *Settings → AI & images*;
   de siger nu *Settings → AI* (7.85.1).

## Facit

| Del | Checks |
| --- | --- |
| In-process (`integration.php`) | 155 PASS |
| Admin-værktøjer (`admin-tools.php`) | 45 PASS |
| Automation (`automation.php`) | 27 PASS |
| HTTP (`/go/`, feeds, REST) | 18 PASS |
| Uninstall | 9 PASS |
| **I alt** | **254 PASS / 0 FAIL** |

`debug.log` indeholdt ingen linjer fra pluginnet, og smoke kørte sideløbende 1 570 PASS / 0 FAIL.
WordPress 7.1.4 (»latest«), MariaDB 10.11.14, PHP 8.3.6.

## Ikke målt

Intet kald er gået til den rigtige OpenRouter: alle AI-svar er mocket, og der findes ingen nøgle i
worktreen. Det, der er bevist, er at pluginnet installerer, aktiverer, registrerer 42 abilities, skriver
igennem OpenRouter-formatet, publicerer, serverer feeds og `/go/`, og rydder op ved uninstall — ikke at
OpenRouter svarer som forventet med en rigtig nøgle.
