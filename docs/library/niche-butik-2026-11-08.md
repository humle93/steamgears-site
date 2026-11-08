# Niche-butikken — ét felt, én butik (8/11 2026)

_Plugin 7.64.0. Plan-motoren er målt med 32 smoke-checks og 1081 grønne checks i alt; selve
WooCommerce-kørslen er **ikke** målt endnu og står som modulets sidste 0,15 d._

## 1. Hvad der blev bygget

Spørgsmålet ejeren stillede var: »gør så jeg bare skal skrive niche-navn i pluginnet, også blir alt
sat op automatisk — fuld udrulning af webshop«. Svaret er `includes/niche-shop.php` med ét felt under
**Steamgears → Shop → Niche shop**.

| Lag | Hvad det gør | Hvor |
| --- | --- | --- |
| Plan | niche → brandforslag, 5 kollektioner, prissatte produkter, 5 sideskitser, menu, schema, ejer-liste | `SGAP_Niche_Shop::plan()` (ren PHP) |
| Udrulning | opretter `product_cat`-termer, produkt-**kladder** og sidekladder med pris, SKU og `_sgap_niche` | `SGAP_Niche_Shop::apply()` |
| Rollback | sletter kun den kørsels egne kladder og tomme termer; publiceret indhold beholdes og rapporteres | `SGAP_Niche_Shop::rollback()` |
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
6. **Ejer-listen** (8 punkter) siger hvad der ikke kan automatiseres: Printful-adgang, publicering,
   valuta og priser, betalingsaftaler, CVR/navn/adresse/telefon, EU-samtykke, forsendelseszoner og
   godkendelse af butikkens navn.

Plan-nøglen er `sha1(engine | niche | count | margin)` — samme input giver samme nøgle, så en plan kan
vises, deles og godkendes, før en eneste række skrives.

## 3. Sikkerhedsreglerne i udrulningen

- **Kun kladder.** Intet publiceres automatisk, og ingen eksisterende vare røres: findes slugen,
  springes den over og rapporteres som `skipped`.
- **Uden WooCommerce skrives intet.** `post_type_exists('product')` tjekkes før første skrivning, så
  en halv udrulning ikke kan opstå. Målt i smoke: afvisningen giver `sgap_niche_no_woo` og efterlader
  ingen kørselsbog.
- **Run-bogen** (`sgap_niche_runs`, de ti nyeste) gemmer hvert oprettet id, så en kørsel kan rulles
  tilbage i sin helhed.
- **Rollback er konservativ.** Den sletter kun kladder med `_sgap_niche` lig kørslens nøgle og kun
  termer uden indhold. Publicerede varer og varer fra andre kørsler står i `kept` med årsag.
- **Adgang** kræver `manage_options` plus nonce på alle flader; evnerne er erklæret read-only
  (`niche-plan`, `niche-runs`) eller skrivende (`niche-apply`, `niche-rollback`).

## 4. Målt

| Måling | Resultat |
| --- | --- |
| Smoke-checks i alt efter 7.64.0 | **1081 PASS, 0 FAIL** (`php plugins/tests/smoke.php`) |
| Nye checks for modulet | **32** (`plugins/tests/niche-shop.php`) |
| `php -l` | ren på alle 43 PHP-filer i pluginnet |
| Zip | `plugins/steamgears-autopilot-v7.64.0.zip` byte-identisk med kilden (fejlscan) |
| Plan-determinisme | samme niche + indstillinger → samme nøgle; andet `count` → anden nøgle |
| Pris-gulv | alle planlagte priser ≥ margin-gulvet, alle ender på `,95` |
| Uden WooCommerce | kørslen afvises, intet skrives, ingen run-bog |
| Rollback | publiceret produkt beholdes, kladde-side slettes, kørslen glemmes |

## 5. Det der stadig kræver ejeren

1. **Opdatér pluginnet til 7.64.0** (Plugins → Update, eller zip-upload) — først derefter kan
   niche-feltet bruges på sitet.
2. **Kør ét niche-run med rollback** som prøve, så `product_cat`, pris-meta og slettevejen er målt i
   rigtig WooCommerce og ikke kun i stubber. Det er modulets sidste 0,15 d.
3. **Printful-adgang** for rigtige kostpriser og 5–8 mockups pr. produkt; indtil da er kostpriser
   estimater og hvert produkt har højst ét billede.
4. Alt det en butik ikke kan undvære, og som ikke må gættes: CVR/navn/adresse/telefon, valuta og
   priser, betalingsaftaler, forsendelseszoner, EU-samtykkemodel — de samme punkter som
   `docs/roadmap.md` §4c.

## 6. Ændrede filer

| Fil | Ændring |
| --- | --- |
| `plugins/steamgears-autopilot/includes/niche-shop.php` | ny: plan, apply, rollback, admin-side, REST |
| `plugins/steamgears-autopilot/includes/abilities.php` | fire evner + callbacks |
| `plugins/steamgears-autopilot/includes/admin-menu.php` | »Niche shop« i Shop-gruppen |
| `plugins/steamgears-autopilot/steamgears-autopilot.php` | 7.64.0, require + init |
| `plugins/tests/niche-shop.php` | ny: 32 checks |
| `plugins/tests/smoke.php` | kræver den nye testfil |
| `plugins/steamgears-autopilot-v7.64.0.zip` | ny release (7.63.0 slettet) |
| `plugins/steamgears-autopilot/README.md`, `ROADMAP.md` | afsnit »One niche, one shop« og P4 |
