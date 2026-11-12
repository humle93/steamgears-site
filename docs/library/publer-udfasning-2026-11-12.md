# Publer udfases — sådan poster pluginnet selv til Facebook, Instagram og Pinterest

**Dato:** 12/11 2026 · **Plugin:** Steamgears Autopilot 7.84.0 · **Mål:** opsige Publer, før abonnementet
fornyes næste måned, uden et eneste døgn hvor intet bliver postet.

## 1. Udgangspunkt (målt 12/11)

- Sitet kører `publish_mode = rss`. Publer læser tre feeds og poster selv til **Facebook, Instagram og
  Pinterest**. De sidste 60 dage gik 40 opslag ud den vej (16 Facebook, 12 Instagram, 12 Pinterest).
- Pluginnet kunne allerede poste direkte til Facebook, Bluesky, Mastodon og Telegram, men kun i API-tilstand.
  API-tilstanden krævede en Publer-nøgle, så den vej var lukket.
- Instagram og Pinterest kunne kun *læse* statistik.

## 2. Hvad 7.84.0 ændrer

- **Én kanal ad gangen.** I både RSS- og API-tilstand spørger pluginnet først `SGAP_Direct`. En kanal, der er
  slået til som direkte, postes af pluginnet. Alle andre går til Publer som før. Feed-sundheden viser en
  direkte kanal som `direct` og ikke som et tomt feed.
- **Instagram:** opslaget laves i tre skridt: `POST /{konto}/media` (billede og tekst), statustjek til
  `FINISHED`, derefter `POST /{konto}/media_publish`. Teksten har intet link, fordi Instagram ikke gør links
  klikbare. Billedet skal være en offentlig **JPEG** (tjekket før kaldet).
- **Pinterest:** `POST /v5/pins` med board, titel (100 tegn), tekst (500 tegn), alt-tekst, billede og
  sporingslinket, så klik tælles som på de andre kanaler.
- **Samme sikkerhed som før:** kø, dagsloft, stille timer, blackout, kvalitetsgate, AI-redaktør og backoff
  ved fejl gælder også de direkte kanaler.
- Alle tre er **slået fra som standard**. At forbinde en konto for at læse tal flytter ikke udgivelsen af sig
  selv.

## 3. Ejerens opgaver

### 3.1 Facebook-siden (koden findes allerede)

1. Opret eller genbrug en app på developers.facebook.com (type *Business*).
2. Hent et **Page access token**, der lever længe, med `pages_manage_posts` og `pages_read_engagement`
   (Graph API Explorer → vælg siden → generér → forlæng).
3. Gem det i WordPress under *Settings → Keys* (Facebook access token) og sidens tal-id under
   *Settings → Publer* (`facebook_page_id`).
4. Sæt `facebook_direct = yes`.

### 3.2 Instagram

*(Om TikTok: på sitet ligger kun *TikTok for WooCommerce*-nøgler (`tt4b_*`). De er til annoncer, pixel og
katalog og ikke til at poste opslag. TikTok-udgivelse kræver et token fra jeres godkendte TikTok-app med
udgivelses-scope. Det er næste trin efter Publer.)*


1. Kontoen skal være en **professionel konto** (Business eller Creator).
2. Tilføj *Instagram API with Instagram Login* til samme Meta-app. Giv tokenet scopes
   `instagram_business_basic` og `instagram_business_content_publish`. `..._manage_insights` beholdes, så
   statistikken fortsat kan læses.
3. Gem token og konto-id (`instagram_user_id`, begynder med 1784…). Sæt `instagram_direct = yes`.
4. **Ikke verificeret herfra:** om Meta kræver app-gennemgang for at poste til din *egen* konto, mens appen
   er i udviklingstilstand. Efter vores viden er det nok, at kontoen har en rolle på appen. Første opslag
   viser det.

### 3.3 Pinterest

1. **Tokenet fra *Pinterest for WooCommerce* kan ikke poste** (målt 12/11). Det har `pins:write`, men
   Pinterest svarer 401 `Missing: ['boards:read', 'boards:write']` på `POST /v5/pins`, og WooCommerce-appen
   beder ikke om board-scopes. Til at poste skal der bruges et token fra **din egen** Pinterest-app med
   `boards:read`, `boards:write`, `pins:read` og `pins:write` (`tools/pinterest_connect.py` beder om dem
   fra 7.84.1).
   - På sitet ligger en client secret på 40 tegn fra din app, men **App ID (client id) mangler**. Indsæt
     det under *Settings → Keys*, og kør `--authorize` / `--finish` som i
     [`pinterest-genforbindelse-2026-11-09.md`](pinterest-genforbindelse-2026-11-09.md).
   - Det gamle eget token på sitet er udløbet (401) og kan ikke fornyes uden App ID.
   - Pluginnet bruger WooCommerce-tokenet før sit eget. Derfor skal integrationens token enten afbrydes,
     eller også skal det egne token sættes som konstant. Det står i samme dokument.
2. Find boardets tal-id (`GET /v5/boards`) og gem det som `pinterest_board_id`. På sitet ligger et 18-cifret
   id fra en ældre opsætning (`sg_pinterest_board`). Det er sandsynligvis boardet, men det kan ikke
   bekræftes uden `boards:read`. Sæt derefter `pinterest_direct = yes`.
3. **Ikke verificeret herfra:** nye Pinterest-apps starter på *Trial access*. Efter vores viden kan pins, der
   oprettes på trial, være begrænsede, indtil appen får *Standard access*. Søg om Standard samtidig, så
   ventetiden løber.

## 4. Rækkefølge og opsigelse

| Uge | Skridt | Bevis før næste skridt |
| --- | --- | --- |
| 1 | Facebook direkte (`facebook_direct = yes`) | Et opslag på siden, `external_id` i køen, og Publer poster ikke det samme |
| 1-2 | Instagram direkte | Et opslag på kontoen og media-id i køen |
| 2-3 | Pinterest direkte | En pin på boardet og et pin-id i køen |
| Før fornyelse | `SGAP_Direct::publer_free()` er sand: alle tændte kanaler er direkte | Feed-sundheden viser `direct` for alle tre |
| Derefter | Fjern feedsene i Publer og **opsig abonnementet** | Et døgns opslag går ud uden Publer |

Kanaler, der ikke kan postes direkte, skal **slås fra** under *Settings → Publer → Channels*, inden Publer
opsiges. Ellers kommer deres opslag i et feed, som ingen læser. Pluginnet (7.80.0+) holder opslag til slukkede
kanaler tilbage.

## 5. Verifikation

- `plugins/tests/direct.php`: 19 checks for opdeling, Instagram-forløbet (container → status → publish, fejl,
  manglende billede, manglende rettighed), Pinterest (payload, scope-fejl, manglende board), preflightens board-tjek,
  fælles bogføring og RSS-blandingen. 1 613 → 1 632 PASS, 0 FAIL.
- **Ikke målt:** et rigtigt opslag til Instagram eller Pinterest. Det kræver ejerens tokens fra §3.
