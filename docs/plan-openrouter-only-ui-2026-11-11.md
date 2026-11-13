# OpenRouter-only, ingen billedgenerering, strømlinet admin-UI

Dato: 13. november 2026  
Status: Godkendt scope; tilpasset den aktuelle `main`-base 7.84.1 før udførelse.

## Scope og grænser

1. OpenRouter er den eneste tekst-/AI-provider for generator, Content Studio, SEO, chat, editor, campaign-agent, Cirla og øvrige AI-kald. Fast endpoint `https://openrouter.ai/api/v1`; ingen AIMLAPI, Anthropic eller kompatible provider-valg, ingen anden provider og ingen gratis-model-/backup-failover. Modellen vælges fra OpenRouters katalog; der gættes ikke standardmodel.
2. Fjern alt AI-billedgenerering: `SGAP_Images`, generationens endpoints/abilities/AJAX/UI, artikel-promptens billedfelter og billednøgler/-modeller/-indstillinger. Bevar uploadede medier, post-/produktbilleder og genbrug af eksisterende featured image til sociale opslag. Ingen eksisterende medier, indlæg eller site-data slettes eller omskrives.
3. Strømlin hele plugin-adminfladen med et fælles, roligere designsystem; fjern døde provider-/billedkontroller i wizard, Settings, Keys, dashboard og Content Studio, samt rette tilhørende tekster.
4. Opdatér relevante mocks/assertions, README, plugin-ROADMAP/change log og mu-plugin schema-helper til generisk navngivning. Historiske change-log-noter bevares som historik, ikke som aktive valgmuligheder.
5. Urelateret `ai-chat/` og `css/landing-page.html` forbliver urørt. Ingen site-data, legitimationsoplysninger eller `.mcp.json` ændres.

## Aktuel base, version og estimat

GitHub `main` fast-forwardet til `f0836ef` (13/11), plugin-header/zip **7.84.1**. Den lokale 7.67-patch blev bevaret separat og genbruges kun som reference; 7.84-funktioner overskrives ikke. Målrelease: **7.85.0**.  
Estimat: **4,0 udviklerdage / 32 timer**.

| Fase | Timer | Andel | Leverance |
|---|---:|---:|---|
| A. Provider-konsolidering | 8,0 | 25 % | OpenRouter fast og eneste; fjern alle provider-, backup-, gratis-model- og model-healingsveje |
| B. Billedgenerering ud | 6,4 | 20 % | Fjern generator/indstillinger/keys/ability/AJAX/UI; behold almindelige medie- og featured-image-referencer |
| C. Admin-oprydning | 9,6 | 30 % | Fælles typografi/spacing/cards, wizard, Keys/Settings, dashboard/Studio og døde kontroller |
| D. Test, pakning og release | 8,0 | 25 % | Relevante lokale checks, docs/mu-plugin, status, 7.85.0 zip, dansk commit/push |

## Berørte områder (7.84.1-strukturen)

**Plugin-kode:** `steamgears-autopilot.php`; `includes/generator.php`, `openrouter.php`, `keycheck.php`, `core.php`, `keys.php`, `setup.php`, `chat.php`, `studio.php`, `automation.php`, `rss.php`, `preflight.php`, `abilities.php`, `mcp-tools.php`, `campaign-agent.php`, `coach.php`, `editor.php`, `priorities.php`, `direct.php` og andre AI-kaldere fundet ved søgning. Billedrelaterede changes rører ikke upload, mediebibliotek, billed-URL'er eller social media payloads.

**Admin-flader/assets:** `includes/admin-ui.php`, `admin-menu.php`, relevante side-renderere, `assets/admin.css`, `admin.js` og berørte `assets/pages/{setup,dashboard,chat,studio,tools,niche,seo}.js`.

**Tests:** `plugins/tests/{smoke,integration,admin-tools,automation,keys,cc-login,campaign-agent,coach,direct}.php` samt tests der direkte forudsætter Anthropic/AIMLAPI/failover/image-generator.

**Docs/helpers/pakning:** plugin-`README.md`, `ROADMAP.md`, `CHANGELOG.md`, denne plan; `wordpress/mu-plugins/aimlapi-tool-schema-sanitizer.php` og `sgap-mcp-schema-fix.php` **beholder deres filnavne** (de loades efter navn) og røres ikke; kun pluginets egne filer ændres. `projektstatus.json`; slet zip 7.84.1 og byg præcis én zip 7.85.0 i `plugins/`.

## Implementerings- og verificeringsregler

- Legacy provider-optioner normaliseres til OpenRouter; de bruges ikke, men gammel indstilling eller credential bliver ikke automatisk slettet fra en live database.
- OpenRouter `/models` katalog + én nøgle/model; ingen `fallbacks`, gratis-model retry, anden nøgle, provider-switch eller model auto-repair.
- Postens `featured_image`/media URL kan stadig gå til eksisterende kanaler. Manglende foto bliver en tydelig preflight-afvisning; intet genereres.
- `./.tools/php -l` efter PHP-ændringer; målrettede smoke/mock tests på OpenRouter og uden billedgenerering. Ingen live OpenRouter-/WordPress-påstand uden ejerens credentials/login.
- Søgning skal bekræfte ingen aktiv AIMLAPI-provider/endpoint, billedgenerations-endpoint/klasse/ability/kontroller, eller de fjernede felter i plugin/test/mu-plugin. Historiske changelog-rækker må kun omtale tidligere funktioner.
- Versionsheader, zip-navn, ZIP-indhold byte-match; præcis én zip i `plugins/`.
- Opdater status via `projektstatus.py`; 100 % kræver grønne lokale checks/zip og `verified: true`. Commit besked på dansk med Modul/Før/Efter/Δ/Grundlag; push til `main` via `cloud_git` i samme pas.

## Fremdrift

Plugin-status havde 33,75/33,75 d før dette udvidede scope. Tilføj P12 med 4,0 d; done krediteres kun fase-leverancer, og live-verifikation forbliver uden for denne status hvis den kræver ejeradgang.
