# Niche-butikken — ét felt, én butik (8/11 2026)

_Plugin 7.64.3. Modulet er målt både lokalt (36 smoke-checks, 1084 grønne checks i alt) og på
steamgears.eu mod rigtig WooCommerce: ét niche-navn blev til 5 kollektioner, 8 prissatte
produktkladder, en sideskits og en navigation — og rollback gav præcis udgangstilstanden tilbage.
P4 er dermed 1,0 af 1,0 d._

## 1. Hvad der blev bygget

Spørgsmålet ejeren stillede var: »gør så jeg bare skal skrive niche-navn i pluginnet, også blir alt
sat op automatisk — fuld udrulning af webshop«. Svaret er `includes/niche-shop.php` med ét felt under
**Steamgears → Shop → Niche shop**.

| Lag | Hvad det gør | Hvor |
| --- | --- | --- |
| Plan | niche → brandforslag, 5 kollektioner, prissatte produkter, 5 sideskitser, menu, schema, ejer-liste | `SGAP_Niche_Shop::plan()` (ren PHP) |
| Udrulning | opretter `product_cat`-termer, produkt-**kladder**, sidekladder og navigation med pris, SKU og `_sgap_niche` | `SGAP_Niche_Shop::apply()` |
| Rollback | sletter kun den kørsels egne kladder, tomme termer og egen navigation; publiceret indhold beholdes og rapporteres | `SGAP_Niche_Shop::rollback()` |
| Flader | admin-side, `POST /sgap/v1/niche`, `/niche/rollback`, `GET /niche/runs`, fire evner | `page()`, `routes()`, `SGAP_Abilities` |

## 2. Sådan bliver et niche-navn til en butik

1. **Normalisér** niche-navnet (2–60 tegn) og del det i ord. Alt for korte input afvises med en
   fejl i stedet for at gætte.
2. **Vælg arketypen** ved at matche nøgleord som delstrenge, vægtet efter længde, så »dark academia
   library« slår den løsere »academia«. Ti arketyper — steampunk, dark academia, gothic, cottagecore,
   nautical, botanical, cyberpunk, celestial, retro og en neutral fallback — hver med egne
   kollektioner, motiver, brands og tone.
3. **Byg kataloget**: seks POD-familier (tee, hoodie, art print, tote, mug, phone case) krydset med
   arketypens motiver og niche-ordene, fordelt round-robin over kollektionerne. Titler, slugs, SKU
   (`SG-XXXXXX`), kort og lang beskrivelse følger med.
4. **Pris** = den højeste af `cost × 2,6` og `SGAP_MCP_Woo::floor_price($cost, $margin)`, rundet til
   `…,95`. Margin-gulvet kommer fra *Settings → Shop & feeds* (`woo_min_margin`), så planen kan ikke
   underbyde butikkens egen regel. Kostpris er markeret `estimate`, indtil Printful er koblet på.
5. **Sider**: start-here, about, shipping & returns, size guide og contact. Hver ejer-fakta står som
   `[EJER: …]` — CVR, adresse, telefon, zoner, fragtpris, størrelsestal — fordi de ikke må gættes.
   Findes siden i forvejen, springes den over og meldes som `skipped`.
6. **Menu**: planens otte punkter bliver rigtige links — forsiden, butikken, de tre første
   kollektionsarkiver og de sider, der faktisk findes. På en bloktema-side skrives de som en
   `wp_navigation`-post; på et klassisk tema som en nav-menu. Navigationen peges **ikke** på headeren
   automatisk: at skifte forsidens menu er ejerens beslutning, men den ligger klar til at blive valgt.
7. **Ejer-listen** (8 punkter) siger hvad der ikke kan automatiseres: Printful-adgang, publicering,
   valuta og priser, betalingsaftaler, CVR/navn/adresse/telefon, EU-samtykke, forsendelseszoner og
   godkendelse af butikkens navn.

Plan-nøglen er `sha1(engine | niche | count | margin)` — samme input giver samme nøgle, så en plan kan
vises, deles og godkendes, før en eneste række skrives. Køres samme nøgle igen, skrives intet nyt, og
admin-siden siger det med datoen i stedet for at påstå at der blev oprettet noget.

## 3. Sikkerhedsreglerne i udrulningen

- **Kun kladder.** Intet publiceres automatisk, og ingen eksisterende vare røres: findes slugen,
  springes den over og rapporteres som `skipped`.
- **Uden WooCommerce skrives intet.** `post_type_exists('product')` tjekkes før første skrivning, så
  en halv udrulning ikke kan opstå. Målt i smoke: afvisningen giver `sgap_niche_no_woo` og efterlader
  ingen kørselsbog.
- **Run-bogen** (`sgap_niche_runs`, de ti nyeste) gemmer hvert oprettet id — inkl. navigationens — så
  en kørsel kan rulles tilbage i sin helhed.
- **Rollback er konservativ.** Den sletter kun kladder med `_sgap_niche` lig kørslens nøgle, kun termer
  uden indhold og kun den navigation, kørslen selv skrev. Publicerede varer og varer fra andre kørsler
  står i `kept` med årsag.
- **Adgang** kræver `manage_options` plus nonce på alle flader; evnerne er erklæret read-only
  (`niche-plan`, `niche-runs`) eller skrivende (`niche-apply`, `niche-rollback`).

## 4. Kørslen på steamgears.eu (målt 8/11 2026)

Sitet: WordPress 7.1.4, PHP 8.5.12, WooCommerce 11.2.2, temaet Extendable (bloktema med
`wp_navigation`-post 4 som site-navigation). Plugin 7.63.0 blev opdateret til **7.64.3** via
`Plugin_Upgrader` fra release-zippen, og filerne på serveren er SHA-1-sammenlignet med kilden:
`steamgears-autopilot.php` `ef62636f…`, `includes/niche-shop.php` `d1004ad3…`, `includes/abilities.php`
`8ebd3f5f…` og `uninstall.php` `0dfe67eb…` — alle identiske — og `SGAP_VERSION` svarer `7.64.3` i et
frisk request.

**Før** (2026-11-08 16:19:17 UTC, plugin 7.64.2 → samme tilstand som 7.63.0):

| Måling | Værdi |
| --- | --- |
| Produkter | 116 i alt: 4 kladder · 20 publiceret · 92 i papirkurv |
| `product_cat` | 32 termer (24:uncategorized … 184:atelier), samme id'er som ved sessionens start |
| Sider | 32 (22:shop … 1397:returns) |
| Navigation | 1 `wp_navigation` (id 4, »Navigation«); 0 klassiske menuer |
| `sgap_niche_runs` | findes ikke |

**Kørslen**: `POST /sgap/v1/niche` med `{"niche":"cottagecore mushroom","count":8,"apply":1}`,
plan-nøgle `26f9ebdd1c39`, arketype `cottagecore` (75 % match), brandforslag »Fern & Fen«,
margin-gulv 45. Svar 200, `errors: []`, `skipped`: de fire sider der fandtes i forvejen
(start-here, about, size-guide, contact). Oprettet 2026-11-08T15:21:39Z:

| Slags | Id'er |
| --- | --- |
| Kollektioner | **210** meadow · **211** foraging · **212** kitchen-garden · **213** woodland · **214** pressed-flowers |
| Produkter (kladde) | **1517** mushroom-forager-tee · **1518** honey-and-clover-hoodie · **1519** pressed-fern-art-print · **1520** meadow-hare-tote · **1521** jam-jar-mug · **1522** woodland-snail-phone-case · **1523** cottagecore-tee · **1524** mushroom-hoodie |
| Side (kladde) | **1525** shipping-and-returns (444 tegn, `_sgap_niche` = kørslen) |
| Navigation | **1526** »Cottagecore Mushroom menu« (`wp_navigation`, publish, 8 links) |

Hvert produkt er målt i WooCommerce, ikke kun i databasen:

| Id | Pris | `_regular_price` | `_price` | SKU | Kollektion | Kostpris (estimate) |
| --- | --- | --- | --- | --- | --- | --- |
| 1517 | 32.95 | 32.95 | 32.95 | SG-0CCCC3 | meadow | 12.5 |
| 1518 | 70.95 | 70.95 | 70.95 | SG-0AF281 | foraging | 27 |
| 1519 | 20.95 | 20.95 | 20.95 | SG-07B7DD | kitchen-garden | 8 |
| 1520 | 23.95 | 23.95 | 23.95 | SG-A7CF0B | woodland | 9 |
| 1521 | 22.95 | 22.95 | 22.95 | SG-78E1F6 | pressed-flowers | 8.5 |
| 1522 | 24.95 | 24.95 | 24.95 | SG-3A1EC0 | meadow | 9.5 |
| 1523 | 32.95 | 32.95 | 32.95 | SG-2257DC | foraging | 12.5 |
| 1524 | 70.95 | 70.95 | 70.95 | SG-B2C301 | kitchen-garden | 27 |

Alle otte har `post_status = draft`, `_sgap_niche = 26f9ebdd1c39`, `_sgap_cost_source = estimate`,
beskrivelse på 443–545 tegn og 0 billeder (Printful er ikke koblet på). Kollektionernes arkiver
(`/product-category/meadow/`, `/product-category/woodland/`, …) svarede **200**, butikken havde
fortsat **20 publicerede varer** før og efter, og en kladde var ikke offentligt tilgængelig
(`?p=1496` → **404** i den tilsvarende kørsel). Navigationens otte links:

```
Start here -> https://steamgears.eu/start-here/
Shop       -> https://steamgears.eu/shop/
Meadow     -> https://steamgears.eu/product-category/meadow/
Foraging   -> https://steamgears.eu/product-category/foraging/
Kitchen Garden -> https://steamgears.eu/product-category/kitchen-garden/
Guides     -> https://steamgears.eu/journal/
About      -> https://steamgears.eu/about/
Contact    -> https://steamgears.eu/contact/
```

**Rollback** blev kørt med admin-sidens »Roll back«-knap (samme vej som ejeren bruger): 15 rækker
slettet (5 kollektioner, 8 produkter, 1 side, 1 navigation), `kept: []`. Eftertilstanden blev
sammenlignet felt for felt med før-tilstanden og er **identisk**: 4 kladder · 20 publiceret · 92 i
papirkurv, de samme 32 kategori-id'er, de samme 32 side-id'er, kun `wp_navigation` id 4 tilbage, ingen
klassiske menuer, og `sgap_niche_runs` er tom igen.

### Det kørslen afslørede, og som blev rettet

| Version | Fejl | Rettelse |
| --- | --- | --- |
| 7.64.0 | Planen lovede en menu, men `apply()` skrev ingen: `created` havde ingen `menus`, og `wp_get_nav_menus()` var stadig tom efter kørslen | `menu_links()` + `create_menu()`: rigtige URL'er, `wp_navigation` på bloktema, klassisk nav-menu ellers; id'et gemmes i run-bogen og slettes af rollback (7.64.1) |
| 7.64.1 | Runs-tabellen skrev »1 pages« | Ental/flertal i både PHP-tabellen og JS-renderingen (7.64.2) |
| 7.64.2 | »Create drafts« på en plan, der allerede var kørt, gav et JS-`TypeError` (`result` har `already_applied` men ingen `counts`): knappen blev hængende på »Creating drafts…« og der blev ikke skrevet noget som helst | Egen informationsboks med kørslens dato og teksten »nothing new was written«, plus `.catch` på begge fetch-kald så et netværkssvar aldrig efterlader knappen død (7.64.3) |

## 5. Admin-siden i browseren

Siden `wp-admin/admin.php?page=sgap-niche` blev åbnet i en rigtig browser som midlertidig
administrator (bruger 4, oprettet til lejligheden og **slettet igen**; tilbage er kun 1:Humle91,
2:juno, 3:mcpwp_bot). Målt:

- Siden rendrede begge kort: »What is the shop about?« med niche-felt, antal, margin-gulv og de to
  knapper, og »Runs« med kørslens række: `5 collections · 8 products · 1 page · 1 menu`, nøglen
  `26f9ebdd1c39` og »Roll back«.
- »Build the plan« kaldte `admin-ajax.php` (svar **200**) og rendrede planen: brand, arketype,
  nøgle, `8 products · 5 collections · 5 pages · 8 owner steps · 51 fields marked [EJER] · ~2.95 h`,
  produkttabel, sider, menu og »What only the owner can do«.
- »Create drafts« på samme plan gav informationsboksen »This plan was already applied on
  2026-11-08T15:21:39+00:00 — nothing new was written…« (7.64.3's rettelse) og genaktiverede knappen.
- »Roll back« gennemførte sletningen og genindlæste siden til »No run yet«.
- Konsollen havde **ingen fejl fra vores kode**: kun to blokerede twemoji-billeder fra
  `cdn.jsdelivr.net` (`ERR_BLOCKED_BY_ORB`), som en anden plugins notice indlæser.

Skærmbillede: `docs/assets/niche-admin-2026-11-08.png` (planen med informationsboksen og
produkttabellen). Siden er et smalt admin-layout (ca. 630 px), så skærmbilledet er taget scrollet til
planens begyndelse.

## 6. Reparation fundet undervejs: Novamira-sandkassen stod i safe mode

Admin-siden viste ved første besøg »Novamira Sandbox: safe mode is active«. Årsagen lå i
`wp-content/novamira-sandbox/.crashed`: `sga-design-system.php` i sandkassen erklærede
`sga_design_enabled()`, `sga_design_candidates()` og `sga_design_css()` igen — de findes allerede i
`wp-content/mu-plugins/sga-design-system.php` — og det fatale kald satte **alle** sandkassefiler på
pause. Seks sandkassefiler var ældre kopier af mu-plugin-filer (fem byte-identiske, `sgap-og-images.php`
en ældre udgave på 2371 b mod mu-pluginnets 3086 b). De blev omdøbt til `*.php.disabled`, `.crashed`
blev fjernet, og `/`, `/shop/` og en produktside svarer fortsat **200** uden fatal. Tilbage som
indlæsbar sandkassefil er kun `steamgears-site-events.php` (2 funktioner, ingen navnekollision, parser
rent).

## 7. Plugin CI-checken »model: a name the provider does not offer is refused at save«

Checken ligger i `plugins/tests/integration.php` og kører mod rigtig WordPress i CI. Den var rød på
alle tre WordPress-versioner med `-> "claude-made-up"`, altså den gemte værdi.

Årsagen blev målt på det levende site (WordPress 7.1.4, `wp eval`-lignende kontekst via
`novamira/execute-php`), ikke gættet:

| Måling | Resultat |
| --- | --- |
| Er sanitizeren overhovedet koblet på af `SGAP_Settings::register()`? | `has_filter('sanitize_option_sgap_ai_model')` → **true** |
| Katalog tom (fx ingen nøgle, provider utilgængelig) | `update_option('sgap_ai_model','claude-made-up')` gemmer **`claude-made-up`** — den dokumenterede fallback: en utilgængelig provider må ikke blokere resten af formularen |
| Katalog læsbar og uden navnet | navnet **afvises**, den gamle værdi beholdes |
| Hvilken URL læser kataloget? | `https://api.anthropic.com/v1/models?limit=100` |
| Hvad svarede integrationstestens stub? | kun `https://api.anthropic.com/v1/models/` (enkelmodel-proben); `str_starts_with($url, '…/v1/models/')` er **false** for katalog-URL'en |

Checken målte altså fallbacken (»katalog kunne ikke læses«) og ikke afvisningen, fordi stubbens
præfiks ikke matcher den URL koden faktisk kalder. Rettelsen ligger i vores egen testfil:
stubben svarer nu `…/v1/models?limit=100` med modellisten, og katalog-transienten ryddes lige før
checken, så det er stubben — ikke en cachet liste fra en tidligere fase — der afgør resultatet.
Produktadfærden er uændret.

## 8. Målt i alt

| Måling | Resultat |
| --- | --- |
| Smoke-checks i alt efter 7.64.3 | **1084 PASS, 0 FAIL** (`php plugins/tests/smoke.php`) |
| Nye checks for modulet | **36** (`plugins/tests/niche-shop.php`) |
| `php -l` | ren på alle PHP-filer i pluginnet |
| `python3 tools/fejlscan.py` | ingen fund (version, zip, includes, bibliotek, tomme tests) |
| Zip | `plugins/steamgears-autopilot-v7.64.3.zip` byte-identisk med kilden |
| Live-kørsel | 5 kollektioner, 8 produkter, 1 side, 1 navigation — id'er i §4 |
| Rollback | 15 rækker slettet, `kept: []`, tilstand identisk med før |
| Admin-side | rendret i browser, AJAX 200, ingen JS-fejl fra vores kode |
| Plugin CI-checken | årsag målt og rettet i teststubben (§7) |

## 9. Det der stadig kræver ejeren

1. **Printful-adgang** for rigtige kostpriser og 5–8 mockups pr. produkt; indtil da er kostpriser
   estimater og hvert produkt har 0–1 billede.
2. **Publicering**: kladderne skal godkendes og publiceres — og den nye navigation skal vælges i
   headerens Navigation-blok, hvis den skal vises.
3. Alt det en butik ikke kan undvære, og som ikke må gættes: CVR/navn/adresse/telefon, valuta og
   priser, betalingsaftaler, forsendelseszoner, EU-samtykkemodel — de samme punkter som
   `docs/roadmap.md` §4c.
4. **AI-nøglen**: admin viste »AIMLAPI refused the key: You've run out of funds«. Det rammer
   tekstgenerering, ikke niche-butikken, men skal fyldes op (eller provideren skiftes) før AI-delen
   kan bruges.
5. **Rank Math-notits** efter en prøvekørsel: når en tom kollektion rulles tilbage, mindes Rank Math
   den tidligere publicerede term og foreslår en redirect. Det er kosmetisk støj fra en slettet
   test-term, ikke en fejl i udrulningen.

## 10. Ændrede filer

| Fil | Ændring |
| --- | --- |
| `plugins/steamgears-autopilot/includes/niche-shop.php` | ny: plan, apply (inkl. menu), rollback, admin-side, REST |
| `plugins/steamgears-autopilot/includes/abilities.php` | fire evner + callbacks |
| `plugins/steamgears-autopilot/includes/admin-menu.php` | »Niche shop« i Shop-gruppen |
| `plugins/steamgears-autopilot/steamgears-autopilot.php` | 7.64.3, require + init |
| `plugins/tests/niche-shop.php` | ny: 36 checks (plan, menu-links, rollback af menu) |
| `plugins/tests/smoke.php` | kræver den nye testfil |
| `plugins/tests/integration.php` | katalog-URL'en stubbes, transient ryddes før model-checken |
| `plugins/steamgears-autopilot-v7.64.3.zip` | ny release (7.63.0/7.64.0/7.64.1/7.64.2-zip fjernet) |
| `plugins/steamgears-autopilot/README.md`, `ROADMAP.md` | afsnit »One niche, one shop« og P4 som færdig |
| `docs/assets/niche-admin-2026-11-08.png` | admin-siden set rendret i browser |
