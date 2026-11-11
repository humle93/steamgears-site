# OpenRouter som standard og log ind i Command Center med ét klik (Autopilot 7.76.0)

**P10 — ejeren bad om to ting 11/11: »flyt til OpenRouter« og »lav en log ind i Command Center-knap i
Steamgears-pluginnet«.** Dette dokument er målingen af det, der blev bygget, og af den fejl, der blev fundet
undervejs.

## Fundet: det gamle engangslink kunne aldrig nå frem

Command Center (v7.17.5) havde en knap, *Send login-link til WordPress*, der lavede en engangskode og sendte
den til `POST /sgap/v1/login-link` i pluginnet. **Den rute har aldrig eksisteret.** Målt 11/11:

| Kontrol | Resultat |
| --- | --- |
| `register_rest_route` i `includes/*.php` (7.71.0) | 25 ruter, ingen `/login-link` |
| `git log -S login-link -- plugins` | ingen commits |
| Command Centers kald | `sgap_api("POST", "/login-link", …)` i `app/main.py` |

Flowet krævede desuden to klik på to sider: først i Command Center, så i WordPress inden for 10 minutter.
Pluginnets egen knap *Log ind i Command Center* åbnede kun adressen fra indstillingerne, så man skulle logge
ind igen derovre.

## Hvad der blev bygget

| Del | Placering | Virkning |
| --- | --- | --- |
| OpenRouter som standard | `includes/generator.php` | `SGAP_AI::provider()` giver `openrouter`, når intet er gemt, og ved en ukendt værdi. Et gemt AIMLAPI-valg bevares. |
| AIMLAPI markeres | `includes/core.php`, `setup.php`, `keys.php` | OpenRouter står først og anbefales; AIMLAPI hedder *being phased out*. |
| Flytte-knappen | `includes/openrouter.php` (ny) | Advarsel på pluginets sider, mens AIMLAPI er valgt. Har OpenRouter-reserven nøgle og model, gør *Switch to OpenRouter now* den til hovedudbyder med præcis den nøgle og model. |
| Billeder via OpenRouter | `includes/studio.php` | `SGAP_Images::backend()`: OpenRouter, når der er en OpenRouter-nøgle og `openrouter_image_model`; ellers AIMLAPI som før; ellers ingen. Kaldet er `chat/completions` med `modalities: ["image","text"]` og `image_config.aspect_ratio`; billedet læses som data-URL fra `message.images[]`. |
| Login-ruten | `includes/command-center.php` | `POST /sgap/v1/cc-link` (kræver `manage_options`) gemmer Command Centers adresse og en fælles nøgle i `sgap_cc_link`, uden autoload. Tom nøgle fjerner linket. |
| Login-billetten | `includes/command-center.php` | `admin_post_sgap_cc_login` laver en billet: `base64url(json) . "." . base64url(HMAC-SHA256)`. JSON indeholder e-mail (små bogstaver), brugernavn, site, `iat`, `exp = iat + 60` og en tilfældig 128-bit `jti`. Browseren sendes til `{cc}/cc-sso?t=…`. |
| Command Center | repoet `command-center`, v7.27.0 | Laver nøglen, sender den ved hver sync, og `GET /cc-sso` tjekker signatur, udløb og engangsbrug og logger kun ind som en eksisterende bruger med samme e-mail. |

## Sikkerhedsregler, og hvorfor

- **Nøglen forlader aldrig `command-center.php`.** `SGAP_CC::status()` går ud i `/snapshot` og i MCP-evnerne,
  så den har kun `sso: true/false`. Testene tjekker, at nøglen hverken står i status, snapshot, knappens HTML
  eller billetten.
- **Billetten laves først ved klikket.** Knappen er en formular til `admin-post.php` med nonce; HTML'en
  indeholder ingen billet, der kunne kopieres fra siden.
- **60 sekunder og én gang.** En billet, der havner i en log eller en browserhistorik, er død, før den kan
  genbruges, og Command Center afviser samme `jti` to gange.
- **E-mailen skal findes i Command Center.** En WordPress-administrator kan ikke oprette en ny bruger derovre
  med et klik — kun logge ind som den bruger, der allerede har samme adresse.
- **https.** Adressen afvises, hvis den er `http://`, undtagen for localhost under udvikling.

## Bevidst ikke

- **AIMLAPI er ikke fjernet.** Sitet må ikke miste tekst eller billeder, før en OpenRouter-nøgle er sat.
  Notatet i `projektstatus.json` (9/11) viser desuden, at teksten i dag kører via `openai_compatible` mod Gemini,
  ikke AIMLAPI — det er billederne, der stadig bruger AIMLAPI.
- **`aimlapi-tool-schema-sanitizer.php` beholder navnet.** Den retter skemaer, som Claude afviser, også via
  OpenRouter, og `sgap-mcp-schema-fix.php` indlæser den efter filnavn på det levende site.
- **Ingen standard-billedmodel.** OpenRouters katalog skifter; et gættet modelnavn ville kun fejle på den dag,
  det skulle bruges. Samme regel som for tekstmodellen siden 7.67.0.

## Verifikation

| Kontrol | Resultat |
| --- | --- |
| `php plugins/tests/smoke.php` | 1 505 → 1 550 PASS, 0 FAIL (45 nye i `plugins/tests/cc-login.php`) |
| Mutationstjek | En ændret HMAC giver `FAIL p10 cc: the ticket is signed with HMAC-SHA256 over the payload` og exit 1 |
| `php -l` på alle PHP-filer | ingen fejl |
| Zip mod kilde (CI-trinnet *Zip matches source*) | `Zip is up to date (v7.76.0)`, 74 filer |
| `bash tools/natlige-vaern.sh` | 40 af 42 grønne; de to røde er miljøet: `fpdf` manglede, og S5-målingen kan ikke nå steamgears.eu fra sandkassen |

**Ikke verificeret:** et rigtigt billedkald til OpenRouter, et login mod en kørende Command Center på sitet, og
installationen på steamgears.eu. 7.76.0 ligger i repoet, ikke på sitet.

## Sådan tages det i brug

1. Installér 7.76.0 (zip'en i `plugins/`).
2. Opret en nøgle på openrouter.ai/keys. Læg den som OpenRouter-reserve under *Settings → Keys* og vælg en
   model, og tryk *Switch to OpenRouter now* — eller vælg OpenRouter direkte under *AI content*.
3. Vælg en billedmodel under *AI content → OpenRouter image model*.
4. Opdatér Command Center til 7.27.0, sæt `CC_PUBLIC_URL`, og vent på næste sync (højst 30 min.) eller tryk
   *Forbind login-knappen* under Social. Derefter logger *Log ind i Command Center* ind med ét klik.
