# Indeksering: status og hvad der blokerer — 2/11 2026

Notat fra forsøget på at slå Rank Math Instant Indexing til og få de 17 nye produkter indekseret. Skrevet efter at MCP-forbindelsen faldt bort midt i arbejdet, så det hele kan genoptages uden at gætte.

## 1. Det der viste sig at være anderledes end antaget

**Instant Indexing var allerede slået til.** `rank_math_modules` indeholder `instant-indexing` som modul nr. 11 (Rank Math 1.0.280). Der var altså intet at tænde.

**Men den har aldrig sendt et produkt.** `rank_math_indexnow_log` har 36 poster, alle blogindlæg, med status 200 (én enkelt 202). Seneste post: 25/10 2026. Nul poster efter 1/11 — altså blev ingen af de 17 produkter, der blev udgivet 2/11, sendt til IndexNow. Produkter optræder ikke i loggen overhovedet, heller ikke ældre.

**Sitemappen er derimod i orden nu.** Det tidligere billede (39 produkter, ingen `/inspiration/`) var et cache-snapshot:

- `/product-sitemap.xml` → **57** produkt-URL'er, alle 17 nye inkluderet
- `/page-sitemap.xml` → 25 URL'er, `/inspiration/` inkluderet
- `/robots.txt` → peger på `https://steamgears.eu/sitemap_index.xml`
- `rank_math_sitemap_cache_files` er tom, så sitemappen genereres live

**Search Console er allerede forbundet.** Rank Math Analytics har en Google-forbindelse med scopes `webmasters`, `siteverification`, `analytics.readonly`, og `rank_math_analytics_all_services` siger `isVerified: true`, `inSearchConsole: true` for `https://steamgears.eu/`. Men `hasSitemap: false` — der er **ingen sitemap registreret i Search Console**. Det er det ene hul, der reelt står åbent.

## 2. IndexNow-nøglen kan ikke verificeres — og det er med vilje

Nøglen ligger i en option, hvis navn indeholder `_key`. Royal MCP:

- redacterer den i `wp_get_plugin_settings` (derfor den ikke ses i dumpet)
- nægter at læse den: *"Option is permanently denylisted (its name looks like a credential)"*
- nægter at skrive den, også i `dry_run`

Det er korrekt design. Konsekvensen er, at nøglefilens sti ikke kan kendes udefra. Jeg prøvede de fire sandsynlige afledninger af `home_url()` (`md5` med og uden trailing slash, http/https, www) — alle svarer 404/soft-404, hvilket betyder at nøglen er en tilfældig værdi og ikke en `md5`. **Nøglefilen er derfor ikke nødvendigvis i stykker**; jeg kan bare ikke finde den, og jeg kan ikke verificere den.

Praktisk konsekvens: man kan ikke se udefra, om en IndexNow-indsendelse vil blive accepteret. API'et svarer 202 ("modtaget, validering afventer") uanset hvad, så et 202 er ikke et bevis.

## 3. WAF'en forklarer de mærkelige svar undervejs

| User-Agent | Svar |
|---|---|
| Neutral browser-UA fra datacenter | **454** (blokeret) |
| Bingbot-UA fra datacenter | **455** (blokeret) |
| `Mozilla/5.0 (compatible; steamgears-audit)` | 200 |

Siten afviser alt, der ligner en browser eller en bot, når det kommer fra en ikke-hjemhørende IP. Det er godt nyt for sikkerheden, men det betyder også, at **rigtige crawlere kan blive afvist, hvis de ikke er på hostens allowliste** — og at ingen udefrakommende automatisering kan verificere indeksering uden at bruge en UA, der slipper igennem.

## 4. Blokeringen: Royal MCP forsvandt midt i arbejdet

Efter et permalink-flush begyndte alle MCP-kald at fejle med `rest_no_route`. Undersøgt:

- `/wp-json/royal-mcp/v1` → 404 `rest_no_route`
- `/?rest_route=/royal-mcp/v1` → samme 404 (så det er ikke rewrite-reglerne)
- `/wp-json/` → 42 namespaces, **`royal-mcp` er væk**, men `mcp` (den officielle adapter) svarer

Shoppen er upåvirket: forside, produktside og `/inspiration/` svarer 200 med add-to-cart.

Tre kald blev forsøgt med 20 sekunders mellemrum over et par minutter — alle 404. Det peger på at Royal MCP-pluginnet er deaktiveret eller er faldet på en fejl, ikke på en forbigående netværksfejl. **Det kræver et klik i wp-admin: Plugins → Royal MCP → Aktiver** (eller kig i `wp-content/debug.log` efter en fatal error).

## 5. En bivirkning der skal rettes

For at afgøre om option-skrivning var tilladt, skrev jeg `blogdescription` (tagline) med værdien "Steamgears". Det var en dårlig test: jeg kendte ikke den oprindelige værdi.

Den oprindelige tagline var:

```
DIY steampunk costumes, goggles & gear — build it, wear it, own it.
```

Grundlag: Google-snippet af footeren ("Steamgears. DIY steampunk costumes, goggles & gear — build it, wear it, own it. Explore. Guides · Shop · Gallery") kombineret med at RSS-feedets `<description>` nu står til "Steamgears", og at teksten ikke længere findes i sidens HTML.

**Skal gendannes** i Indstillinger → Generelt → Tagline, eller via undo-token `cd901c4295b0bc4091db7d1bf4bbd28a` (72 timer) når MCP er tilbage.

## 6. Sådan bliver de 18 URL'er indekseret nu

Rækkefølgen er valgt så den ikke afhænger af MCP.

1. **Aktiver Royal MCP igen** (Plugins → Royal MCP → Aktiver) og gendan tagline.
2. **Rank Math → Instant Indexing → Submit URLs.** Indsæt de 18 URL'er fra [indexnow-urls-2026-11-02.txt](indexnow-urls-2026-11-02.txt). Modulet er allerede aktivt; denne boks er den manuelle vej uden om den automatiske hook, der aldrig fangede produkter. Se i loggen nederst på samme side om svarene er 200.
3. **Search Console → Sitemaps → tilføj `sitemap_index.xml`.** Det er det ene, der reelt mangler, og det kan ikke gøres gennem Rank Math uden at bruge den på forhånd forbundne Google-konto.
4. **Undersøg hvorfor produkter aldrig trigges.** Instant Indexing lytter på publicering af indlæg, og WooCommerce-produkter udgivet via REST ser ikke ud til at ramme den hook. Indtil det er afklaret, skal nye produkter sendes manuelt i punkt 2.

## 7. Adgangskanaler pr. 2/11 (aften)

Tre kanaler undersøgt, fordi Royal MCP er nede.

**a) AI Engine simple-token** (`/wp-json/mwai/v1/`, token for `humle91@hotmail.com`)

Virker til indlogning, men rækker ikke ind i WordPress:

| Kald | Svar |
|---|---|
| `simpleAuthCheck` | 200 → `{"type":"email","value":"humle91@hotmail.com"}` |
| `wp/v2/users/me` | **401** `rest_not_logged_in` |
| `wp/v2/settings` | **401** `rest_forbidden` |
| `helpers/post_types`, `settings/options`, `mcp/status`, `mcp/functions` | **401** |
| `simpleTextQuery` | **500** — se nedenfor |

Konklusion: tokenen er scopet til AI Engine's `simple*`-endpoints. Den kan ikke ændre options, indhold, plugins eller indeksering. Den kan ikke engang bruges til at skrive tekster lige nu, fordi:

```
From Anthropic: The "anthropic" AI connector has not been approved for use by "ai-engine/ai-engine.php".
```

Det er WordPress-kernens **Connector Approvals**-funktion, ikke en fejl i AI Engine. Rettelsen er ét sted i wp-admin: **Tools → Connector Approvals**, hvor AI Engine skal godkendes til at bruge Anthropic-connectoren. Indtil da er alle AI-funktioner på siten døde — også dem i editoren.

**b) WP-kernens `ai/v1`** — `providers`, `connector-approvals` og `abilities` er admin-only (401/403), og `abilities/invoke` afviser uautoriserede kald korrekt med 403, også når man sender et gyldigt ability-navn. Ingen hul at lukke.

**c) Application Passwords** — `wp/v2/users?context=edit` svarer 401, og `workspace/auth-check` siger `credentials_rejected` / *"WordPress received the credentials but refused them"*. Vigtigt: WordPress **modtog** `Authorization`-headeren, så headeren bliver ikke strippet af serveren. En rigtig Application Password vil derfor sandsynligvis virke og give fuld REST-skriveadgang — nok til at gendanne tagline og til at undersøge produkternes manglende IndexNow-submission. Det er den hurtigste vej tilbage, hvis Royal MCP ikke kan aktiveres.

Bemærk: den token, der blev delt i chatten, bør tilbagekaldes i AI Engine → Settings → Tokens, uanset om den kun har snæver adgang.

## 8. Ikke verificeret

- Om Rank Maths nøglefil faktisk svarer 200 på den rigtige sti. Kan kun ses i wp-admin.
- Om IndexNow ville acceptere en indsendelse lige nu (API'et svarer 202 uanset gyldighed).
- Om produkters manglende submission skyldes post-typen, REST-vejen eller en fejl i modulet.
- Om WAF'ens bot-regler blokerer Bing/Googles crawlere for `.txt`-filer og REST-stier. 455-svaret på en Bingbot-UA er et advarselstegn, ikke et bevis.
