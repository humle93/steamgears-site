# Butikken har nul anmeldelser, fordi intet nogensinde har spurgt

**Dato:** 7/11 2026 · **Roadmap:** S4.10 · **Fil:** `wordpress/mu-plugins/sgap-review-request.php`

S4.10 var formuleret som et valg og en automatisering: »0 reviews site-wide and no collection tool;
pick a platform, automate the request, show how reviews are verified«. Dette pas tager stilling til
platformen, bygger anmodningen og gør verifikationen forklarlig — alt sammen i repoet, fordi det
eneste der mangler bagefter er at lægge filen ind.

Det er værd at sige højt, hvad problemet egentlig er. Nul anmeldelser er ikke et plugin-problem:
WooCommerce har haft anmeldelser hele tiden, formularen findes på hver produktside, og
verified-buyer-mærkningen ligger i kernen. Der er nul, fordi **ingen nogensinde har spurgt** kunden.
En platform ville ikke løse det; den ville bare flytte formularen.

## 1. WooCommerces egne anmeldelser, ikke en platform

| Krav | WooCommerce native | Tredjepartsplatform |
| --- | --- | --- |
| Konto hos en leverandør | nej | ja, og en ny databehandler |
| Script på produktsiden | nej | typisk ja — S2.7 kæmper allerede med 24 eksterne scripts på `/shop/` |
| Verified buyer | ja, når mailen matcher ordren | afhænger af integrationen |
| Anmeldelser i produkt-schema | ja, kernen udsender `Review`/`AggregateRating` | kræver typisk egen widget |
| Ejerens data | i egen database | hos leverandøren |

Valget er derfor WooCommerces egne anmeldelser. Det er også det valg der gør S4.10 færdig nu i
stedet for »når der er oprettet en konto«: der er ingen nøgle, ingen App Review og intet script at
indlæse.

## 2. Fjorten dage, ikke samme dag

Mailen sendes **14 dage efter** at ordren er markeret gennemført (`sgap_review_delay_days()`,
filterbart, men standarden er 14). En anmeldelse skrevet den dag pakken lander handler om posten, ikke
om varen — og en vare der skal bruges et par gange, før den kan vurderes, bliver aldrig vurderet hvis
spørgsmålet kommer for tidligt. Fjorten dage er også langt inde i returvinduet, så en kunde der er
utilfreds har haft tid til at opdage det.

Planlægningen sker på `woocommerce_order_status_completed` (prioritet 20) og bruger **Action
Scheduler** når den findes — den er allerede i WooCommerce 11.x — ellers `wp_schedule_single_event`.
Det er den samme adapter-tilgang som P1.5-researchen konkluderede, ikke en ny afhængighed.

## 3. Status læses igen ved afsendelse

Det er her en naiv version fejler. En ordre kan blive refunderet, annulleret eller returneret i de
fjorten dage, og en mail der spørger »hvordan gik det?« til en kunde midt i en retursag er værre end
ingen mail.

Derfor re-læses alt ved afsendelsen: ordrestatus skal stadig være `completed`, der skal stadig være
mindst ét anmeldbart produkt, og der skal stadig være en faktureringsadresse. Er statussen en anden,
noteres den i `_sgap_review_skipped` (så det kan ses hvorfor der ikke blev spurgt) og der sendes
intet. Der genplanlægges **ikke**: en ordre får én chance, ikke en kø der følger statussen rundt.

## 4. Dubletter og dobbeltklik

To metadata-nøgler bærer hele værn'et:

- `_sgap_review_scheduled` — sat når opgaven lægges i kø. En ordre der skifter frem og tilbage mellem
  `completed` og `processing` planlægger derfor ikke to gange.
- `_sgap_review_requested` — sat **først når mailen faktisk er sendt** (`wp_mail()` returnerede sandt).
  Fejler afsendelsen, står ordren uden markering og kan forsøges igen; lykkes den, kan den ikke sendes
  igen.

At markeringen sættes på succes og ikke på forsøg er en afvejning: en mail der fejlede skal kunne
forsøges igen, og prisen for det er at et svar fra `wp_mail()` der er sandt uden at mailen er leveret
(fx en SMTP-kø) tælles som sendt. Det er den rigtige side at fejle på — alternativet er kunder der får
den samme anmodning hver nat.

## 5. Ét link pr. produkt, og ingen mail uden en formular

Mailen linker til `<permalink>#reviews`, ikke til forsiden eller butikken: kunden skal lande på den
formular der hører til varen, ikke lede efter den.

- Er anmeldelser lukket for et produkt (`comments_open()`), **udelades produktet**. At bede om en
  anmeldelse der ikke kan afgives er værre end ikke at spørge.
- Er der intet anmeldbart produkt tilbage, sendes der hverken mail eller opgave.
- Et produkt købt to gange spørges der om én gang (deduplikering på `product_id`).
- Er `woocommerce_enable_reviews` ikke `yes`, er hele modulet slået fra — der er ingen formular at
  linke til.

## 6. Copy: ingen incitamenter, og negative anmeldelser bliver stående

Der er to ting mailen **ikke** må indeholde, og det er ikke en stilistisk præference:

1. **Et incitament.** Rabat, gave eller refundering for en anmeldelse er unfair handelspraksis efter
   de Omnibus-regler roadmap'en selv citerer — og det gør anmeldelsen værdiløs for den næste kunde.
2. **En anmodning om at fjerne eller ændre en negativ anmeldelse.**

I stedet siger mailen hvad verifikationen er: en anmeldelse skrevet med den mail ordren blev lagt med,
markeres af WooCommerce som **verified purchase**, så andre kunder kan se at den kommer fra en rigtig
ordre. Den siger også, med vilje tørt, at en ærlig negativ anmeldelse bliver liggende, og at kunden
skal skrive hvad der var galt med pasform, print eller levering, så butikken kan svare. Det er den
sætning der gør mailen til noget andet end en rating-maskine.

## 7. Én tekst, to formater

Afsnittene skrives **én gang** i `sgap_review_sections()` og renderes to steder: HTML med linkede
produktnavne og ren tekst med `navn: url`. En tekst-only-klient får dermed det samme indhold i stedet
for en tom mail eller et enkelt link. Produktnavne escapes i HTML (`esc_html`), URL'er via `esc_url`,
og testen bekræfter eksplicit at der ikke ligger rå HTML i tekstversionen.

Ren tekst lægges på som `AltBody` gennem `phpmailer_init` — det er den eneste måde PHPMailer får en
tekstversion, når `wp_mail()` kaldes med HTML. Handlingen fjernes igen efter afsendelsen, så den ikke
hænger på en senere mail i samme request.

## 8. Hvad testen beviser — og hvad den ikke beviser

`wordpress/tests/test-review-request.php` kører **26 påstande / 0 FAIL** uden WordPress, med stubbe
for de funktioner mu-pluginnet rører. Dækket:

- fristen er 14 dage, og filteret kan flytte den;
- én gennemført ordre giver præcis én planlagt opgave, 14 dage ude, på pluginets eget hook;
- en anden gennemførelse planlægger intet nyt;
- mailen går til ordrens adresse, nævner ordrenummeret, forklarer verifikationen, og begge produkter
  har et `#reviews`-link;
- ordren markeres som spurgt, og en anden kørsel sender intet og svarer `false`;
- en refunderet ordre får ingen mail, og springet noteres med status;
- er anmeldelser slået fra, sker der hverken planlægning eller afsendelse;
- et produkt med lukkede anmeldelser udelades, og ingen anmeldbare produkter giver hverken mail eller
  opgave;
- Action Scheduler foretrækkes frem for wp-cron når den findes;
- HTML og tekst nævner det samme antal produkter, HTML escapes, tekst har ingen rå HTML, og copy
  afviser incitamenter i stedet for at tilbyde dem;
- kill-switchen (`sgap_review_request_enabled`) slår alt fra.

Det testen **ikke** beviser: at mailen kan leveres. Der er ikke sendt en mail, filen er ikke set i en
rigtig WordPress-installation, og `#reviews`-ankeret er ikke klikket efter på det levende site — fordi
sitet svarer PHP-parsefejl på alle dynamiske kald. Det er præcis de sidste 0,05 d af rækken.

## 9. Hvad der stadig mangler

| Del | Status | Kræver |
| --- | --- | --- |
| Kode, test, værn | færdig (0,35 af 0,4 d) | — |
| Filen i `wp-content/mu-plugins/` | mangler (0,05 d) | File Manager eller SFTP |
| En rigtig mail set i begge formater | mangler | samme, plus en testordre |
| `#reviews`-linket klikket efter | mangler | samme |

Filen er et **mu-plugin**, ikke et almindeligt plugin: den skal ikke aktiveres, den skal bare ligge i
`wp-content/mu-plugins/`. Det er bevidst — en anmeldelses-anmodning der kan deaktiveres ved et uheld
fra plugins-skærmen er en anmodning der holder op med at virke uden at nogen opdager det. Slås den fra,
sker det med vilje gennem filteret `sgap_review_request_enabled`, som testen også bruger.

## 10. Filer

- `wordpress/mu-plugins/sgap-review-request.php` — modulet (256 linjer).
- `wordpress/tests/test-review-request.php` — 26 påstande, ingen WordPress nødvendig.
- `tools/natlige-vaern.sh` — testen er **værn nr. 26** i den natlige kørsel (25 → 26), med den
  begrundelse der står i filen: en regression her koster kunder, ikke en fejlkode.
