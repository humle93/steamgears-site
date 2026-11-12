# Kaffeguiden: opsætning af WordPress og plugins med én kommando

**Dato:** 12/11 2026 · **Script:** [`tools/kaffeguiden_setup.sh`](../../tools/kaffeguiden_setup.sh) ·
**Plan:** [`kaffeguiden-plan-2026-11-12.md`](kaffeguiden-plan-2026-11-12.md)

Sitet findes endnu ikke, så intet er installeret. Scriptet gør det hele, når der er et tomt WordPress på domænet: tema,
plugins, indstillinger og alle 14 udkast som kladder. Det er afprøvet mod et falsk wp-cli (§5), ikke mod et rigtigt site.

## 1. Det, du gør først

1. **Køb domænet** (anbefalet: brygguiden.dk, se planen §1).
2. **Installér WordPress** hos hosten med deres 1-klik-installation. Brug en anden adminbruger end »admin«, og en stærk kode.
3. **Log ind på serveren med SSH**, og tjek, at `wp --info` virker. De fleste danske webhoteller med SSH har wp-cli. Har dit
   ikke, så se §4.

## 2. Kør scriptet

```bash
git clone <steamgears-site> && cd steamgears-site
bash tools/kaffeguiden_setup.sh --path ~/www/brygguiden.dk                  # tørkørsel: viser alt, ændrer intet
bash tools/kaffeguiden_setup.sh --path ~/www/brygguiden.dk --apply --app-user <din-admin>
```

Tilføj `--cache litespeed`, hvis hosten kører LiteSpeed (står i hostens kontrolpanel).

### Hvad scriptet gør

| Trin | Hvad |
| --- | --- |
| Sprog og indstillinger | Dansk, Europe/Copenhagen, datoformat »12. november 2026«, mandag som første ugedag, kommentarer slået fra, permalinks `/%postname%/`, titel »Kaffeguiden« |
| Søgemaskiner | **»Bed søgemaskiner om ikke at indeksere«** slås til, til du lancerer. Slå det fra under *Indstillinger → Læsning*, når de første sider er udgivet |
| Tema | GeneratePress (gratis, hurtigt og let at style) |
| Plugins | Se tabellen nedenfor. Hello Dolly og Akismet fjernes |
| Standardindhold | »Hello world« og »Eksempel-side« slettes |
| Kladder | `/guides/` + 8 guides under den, 3 søjler/sammenligninger og 3 troværdighedssider. Meta-titel, -beskrivelse og fokusord sættes i Rank Math |
| Command Center | Med `--app-user` laves en applikationsadgangskode »Command Center«, som vises **én gang** |

Scriptet **overskriver aldrig** en side, der findes, og stopper, hvis sitet har mere end 5 udgivne sider (så det ikke køres
på steamgears ved en fejl). Det kan køres igen uden skade.

### Plugins

| Plugin | Hvorfor |
| --- | --- |
| Rank Math SEO | Meta, sitemap, schema for FAQ og artikler. Scriptet udfylder meta på hver kladde |
| Complianz | Cookiebanner og cookie-/privatlivspolitik efter GDPR. Kræves, fordi affiliate-netværk sætter cookies |
| ThirstyAffiliates | Affiliate-links som `/go/<navn>/`, så klik kan tælles, og et link kan skiftes ét sted |
| UpdraftPlus | Backup |
| Wordfence | Firewall og login-beskyttelse |
| Site Kit by Google | Search Console og Analytics, så Command Center senere kan få GSC-data |
| Redirection | Omdirigeringer, når en side skifter adresse |
| LiteSpeed Cache | Kun med `--cache litespeed` |

## 3. Efter scriptet (ca. 30 min. i browseren)

1. **Rank Math:** kør opsætningsguiden. Vælg »Blog/Website«, slå sitemap til, og sæt »Organization« med sidens navn.
2. **Complianz:** kør guiden. Region EU, dansk, og angiv, at sitet bruger affiliate-cookies og statistik. Lad den lave
   cookie- og privatlivspolitik.
3. **ThirstyAffiliates:** *Settings → Link Appearance → Link Prefix* = `go`. Slå »nofollow« og »sponsored« til på alle links.
   (Linkene oprettes først, når Partner-Ads/Adtraction har godkendt sitet.)
4. **UpdraftPlus:** dagligt for databasen, ugentligt for filer, gem et andet sted end på serveren.
5. **Wordfence:** kør opsætningen, og slå to-faktor til for din bruger.
6. **Site Kit:** forbind Google-kontoen og bekræft domænet i Search Console.
7. **Menu:** *Udseende → Menuer*: Kaffekværn · Pour over · Kaffebønner · Guides · Om.
8. **Command Center:** projektvælgeren → *Nyt projekt »Kaffeguiden«* → *Moduler → Forbindelser*: sidens adresse, brugeren og
   adgangskoden fra scriptet. Læg søgeordene fra planen §2 ind.
9. **Gennemlæsning:** åbn hver kladde. Gule `[[…]]`-felter er pladsholdere (links, produkter, navn). Slet eller udfyld dem,
   før siden udgives. Udgiv **Om**, **Sådan tester vi** og **Affiliate** først, så **guides**, og søjlerne til sidst.

## 4. Uden SSH og wp-cli

Så gøres det i hånden: installér pluginnerne fra tabellen under *Plugins → Tilføj nyt*, og opret kladderne ved at kopiere HTML
ind i en »Brugerdefineret HTML«-blok. HTML'en laves med `python3 tools/kaffeguiden_md_html.py <fil.md> --html`. Det er
ca. 1 times arbejde i stedet for 2 minutter. Alternativt kan Command Center sende kladderne, når forbindelsen er sat op.

## 5. Verifikation

- `python3 tools/kaffeguiden_md_html.py --selftest`: 10 checks (redaktørnote og meta ude af HTML, overskrifter, lister,
  tabeller, citat, FAQ, synlige pladsholdere, escaping, meta).
- `tools/kaffeguiden_setup.sh` mod et falsk wp-cli med tilstand: tørkørsel ændrer intet; `--apply` gav 15 kladder (8 under
  `/guides/`), 14 med Rank Math-meta, 7 aktive plugins, GeneratePress, `blog_public = 0`, standardindholdet væk og én
  applikationsadgangskode; anden kørsel rørte intet (16 »findes«); et site med 15 udgivne sider stoppes (kode 5); ugyldige
  valg giver kode 2.
- **Ikke afprøvet:** et rigtigt WordPress og rigtigt wp-cli. Plugin-slugs er dem fra wordpress.org, men de er ikke slået op
  herfra.
