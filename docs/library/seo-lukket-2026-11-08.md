# SEO-sporet lukket 8/11 — vægt, scripts og lead-formular målt

_Alt målt live 8/11 2026. S4 er stadig urørt: de punkter er juridisk bindende valg._

## S2.5b / S3.3 — billedvægten

Før: **8 af 41 i brug værende billeder var over 300 KB** (316–846 KB).
`wp_get_image_editor()` blev dræbt af værten (**exit 9, tom stderr**) ved to eller flere filer i samme proces,
så metoden blev skiftet: hent filen, komprimer lokalt (Pillow, max 1400 px, kvalitet 78→54 til målet er nået),
læg den tilbage i **bidder af 60 KB** og verificér byte-størrelsen bagefter.

| Fil | Før | Efter |
| --- | --- | --- |
| 1117 print-2 | 304 KB | **291 KB** |
| 1124 print-1 | 772 KB | **249 KB** |
| 1126 upcycle-1 | 845 KB | **291 KB** |
| 975 | 528 KB | **75 KB** |
| 976 | 450 KB | **56 KB** |
| 1395 | 351 KB | **217 KB** |
| 1096 | 437 KB | **223 KB** |

To filer blev afbrudt midt i upload (én chunk fejlede) og derefter gendannet fra den største eksisterende
størrelse, med byte-kontrol: **254 709** og **297 616** bytes, begge læsbare med `wp_get_image_editor()`.
Efter måling: **0 af 41 i brug værende billeder over 300 KB**, `/` svarer 200.

## S2.7 — scripts

Målt i den renderede HTML: `wp-emoji` **0**, `tiktok` **0**, `kliken` **0** på `/`, `/shop/` og
`/free-starter-kit/` — alle 200. De tre ting var altså allerede væk; der var intet at fjerne, og derfor er
**ingen dage bogført** på S2.7. Det der står tilbage i posten er **loftet** (31 scripts på forsiden, 46 på
`/shop/`), og det er en beslutning om hvilke tredjeparter der må køre — ikke en fejl.

## S1.6 — capture-vejen, sat op og målt

WPForms er **slet ikke installeret** (`post_type_exists('wpforms')` = nej), så der var ingen formular at
oprette. Løsningen er en mu-plugin, `sgap-lead-form.php` (2 832 bytes, `php -l` ren), skrevet med
`file_put_contents` fra WordPress-konteksten:

- `POST /wp-json/sgap/v1/lead` tager `email`, `name`, `source`, afviser en ugyldig adresse med **400**,
  gemmer i optionen `sgap_leads` (autoload off) og svarer `{"ok":true,"count":N}`.
- Shortcode `[sgap_lead_form source="..."]` renderer formularen og sender den med `fetch`, så svaret står på
  siden uden reload.

Sat ind på **/free-starter-kit/** (801) og **/free-steampunk-thrifting-hacks/** (764); begge svarer **200**
og indeholder formularen.

**Målt indsendelse:** `POST` med `email=roadmap-verify@steamgears.eu` gav `{"ok":true,"count":1}`, og
`sgap_leads` indeholdt bagefter `{"name":"Roadmap","source":"free-starter-kit","at":"2026-11-08 12:14:28"}`.
Testposten er slettet igen, så listen starter tom (0).
