# Instagram insights i pluginnet — P1.5c (7.54.0, 7/11 2026)

**Instagram er den tredje kanal hvor platformen ved noget vi ikke selv kan måle.** Bluesky og Mastodon
offentliggør likes/reposts/replies som et frit opslag (P1.5a), Pinterest rapporterer impression/saves/outbound
clicks for de pins kontoen ejer (P1.5b) — og Instagram rapporterer, hvor mange konti der så et opslag, og hvad
de gjorde ved det. Det er de tal der svarer på «blev opslaget set», i modsætning til vores eget `/go/`-klik
(`sgap_cro`) og ordrerne i WooCommerce. Modulet er `includes/instagram.php` (`SGAP_Instagram`), og tallene
gemmes som **Instagram-egne event-typer**, så ingen af de tre kilder kan blandes sammen i en sum.

Målt: **820 smoke-checks, 0 FAIL** (var 770) · **24 af 24 værn grønne** · zip byte-identisk med kilden ·
plugin **91,0 % → 96,2 %** (27,8 af 28,9 d) · P1.5c er 1,5 af 1,5 d.

---

## 1. Hvad Instagram faktisk lover

Læst i den officielle Instagram Platform-dokumentation 7/11 2026, fordi API'et har ændret sig to gange inden
for de sidste to år, og hukommelse er den dyreste kilde her:

| Fakta | Konsekvens i koden |
| --- | --- |
| `GET /<IG_MEDIA_ID>/insights?metric=views,likes,comments,shares,saved` giver **lifetime-tal** i `data[].values[0].value`; der findes ikke et datointerval | Vinduet på 90 dage afgør **hvilke opslag** vi spørger om, aldrig hvad svaret dækker. Kortet hedder derfor «posts from the last 90 days», ikke «last 90 days» |
| `impressions` er **deprecated for medier oprettet efter 2/7 2024** og afløst af `views` | Vi spørger om `views`. Havde vi spurgt om `impressions`, ville hvert nyt opslag rapportere nul — og det ville se ud som en måling |
| **En metric der ikke findes, udelades af svaret i stedet for at være `0`**; data kan være op til 48 timer forsinket | Et tomt svar gemmes **ikke**. Opslaget tælles som *waiting* og prøves igen i næste kørsel. At skrive 0 ville være en påstand Meta ikke har fremsat, og hvert gennemsnit nedstrøms ville arve den |
| `saved` staves med d, `likes`/`comments`/`shares` uden | Én fejlstavet metric = hele kaldet afvises, ikke ét manglende tal |
| Medie-listen er `GET /<IG_USER_ID>/media?fields=id,caption,permalink,timestamp,media_type,media_product_type`, cursor-pagineret | Pagineringen gemmes som en cursor der kan genoptages, så en stor konto walkes over flere kørsler |
| Et medie har **intet link-felt**: vores `/go/<id>/` står kun i captionen, og `permalink` er `/p/<shortcode>/` | Matchningen sker på captionen — og kun når værten er vores egen |
| Den lange Instagram-bruger-token lever **60 dage** og fornyes **på stedet** med `GET graph.instagram.com/refresh_access_token?grant_type=ig_refresh_token` (mindst 24 timer gammel, ikke udløbet) | Der findes ingen separat refresh-token: den nye token skrives tilbage over den gemte. Derfor afvises en token i `wp-config.php` helt — den kan ikke erstattes der |
| To login-typer: Instagram Login (`graph.instagram.com`, `instagram_business_basic` + `instagram_business_manage_insights`) og Facebook Login (`graph.facebook.com`, `instagram_basic` + `instagram_manage_insights`) | Værten er en **indstilling** med to valg, ikke et gæt ud fra tokenens form. Gætter man forkert, bliver en fungerende token til en 400 |

## 2. Fire beslutninger der er værd at kende

1. **Delvise svar er normale, ikke en fejl.** `analytics()` returnerer kun de metric der faktisk kom tilbage.
   Et tomt array betyder «ikke rapporteret endnu» og tælles som *waiting*; enkelte metric der mangler, gemmes
   ikke som nul. Det er den eneste måde at undgå at et opslag to dage gammelt ser ud som et opslag ingen så.
2. **Match på caption, ikke på permalink.** Samme regel som Pinterest: et opslag hvis caption indeholder vores
   `/go/<queue id>/` har en præcis ejer. Uden den tælles opslaget som *unmapped*; et `/go/`-id uden en
   publiceret Instagram-række i vinduet tælles som *orphaned*. Begge står på dashboardet i stedet for at blive
   gættet — en fremmed konto kunne ellers puste vores tal op.
3. **Et afvist metric giver et smallere kald, ikke et tabt opslag.** En medie-type der ikke bærer `saved`
   afviser hele kaldet med HTTP 400; så spørges der én gang til med `views,likes,comments`, som alle
   understøttede typer har. Tre rigtige tal slår fem der aldrig kommer.
4. **Sletningen er afgrænset til kanalen.** `likes` er også Bluesky/Mastodons snapshot-type, så en sletning
   pr. kø-række alene kunne viske en anden kanals tal ud på samme række. Instagram sletter med
   `channel = 'instagram'` — og **samme latente fejl blev rettet i Pinterest-modulet i samme pas**, hvor `saves`
   deles med Instagram.

## 3. Tre fejl fundet undervejs

| Fejl | Hvordan den blev fanget | Rettelse |
| --- | --- | --- |
| Test-doublen `WP_Error` manglede `get_error_code()`, som rigtig WordPress har | Kaldet døde med «Call to undefined method» midt i suiten | Metoden tilføjet til doublen; modulet bruger den nu rigtigt |
| Det smallere metric-forsøg slugte **token-fejl**: en udløbet token blev «retried» med færre metrics i stedet for at blive fornyet | Testen «a code-190 answer refreshes the token» fejlede | Det smalle forsøg kræver nu eksplicit `sgap_instagram_400`; token-fejl går til fornyelsen |
| Token-fejlen blev returneret **før** fornyelsen blev forsøgt, fordi Instagram svarer 400/code 190 og fejlen allerede var pakket som `WP_Error` | Samme test | Forældelses-tjekket ligger nu før retur, og ser både på HTTP-status og på fejlkoden |

## 4. Hvad der er bygget

- `includes/instagram.php`: token og fornyelse, medie-liste med cursor, caption-match, insights med smal
  fallback, lagring pr. kanal, daglig kørsel gennem `SGAP_Scheduler` (`sgap_instagram_refresh`), selvtjek,
  dashboardkort og `POST /sgap/v1/instagram/refresh`.
- Indstillinger: **Instagram account ID** og **Graph host** under Publishing, **token** under Settings → Keys
  (eget kort, som de øvrige tjenester). Tokenen er en secret med autoload slået fra.
- 50 nye smoke-checks: ukonfigureret tilstand, caption-match (med og uden scheme, `www.`, fremmed vært), de to
  værter, fornyelse (URL, lagring, throttle, afvist fornyelse), paginering, fem metric, delvist svar, tomt
  svar, smal fallback, code-190-fornyelse, budget, kanal-afgrænset sletning, opsummering og kortet.

## 5. Verifikation — og hvad der stadig ikke er målt

| Kontrol | Resultat |
| --- | --- |
| `php -l` på alle ændrede filer | rent |
| `plugins/tests/smoke.php` | **820 PASS / 0 FAIL**, ingen PHP-advarsler |
| `tools/fejlscan.py` | ingen fund (version, zip, includes, bibliotek, tokens, tests) |
| `bash tools/natlige-vaern.sh` | **24 af 24 grønne** |
| zip mod kilde | byte-identisk (7.54.0) |

**Ikke gjort:** der findes ingen Instagram-token i repoet eller i sandkassen, så modulet har aldrig talt med
Meta. Første rigtige kørsel skal bekræfte tre ting: at `views` faktisk kommer tilbage (og ikke kun `likes`), at
caption-matchningen finder `/go/`-linket som skrevet i det rigtige opslag, og at fornyelsen svarer med en token
der stadig virker. Indtil da er P1.5c **kode-færdig, ikke verificeret** — og de 0,85 d der står åbne i P1.5 er
netop den live-verifikation, som hverken Pinterest eller Instagram kan få uden en token.
