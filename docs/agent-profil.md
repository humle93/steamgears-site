# Agentprofil — steamgears.eu

**Læs denne fil før du rører noget.** Den er skrevet så en bot, der aldrig har set repoet, kan arbejde
selvstændigt: hvad projektet er, hvad der er sandt lige nu, hvad du kan og ikke kan gøre herfra, og hvilke
fælder der allerede er betalt for.

Arbejdsreglerne (procenter, daglig status, AUTO-blokke) står i [`../AGENTS.md`](../AGENTS.md). Denne fil er
*indholdet*; AGENTS.md er *formen*. Er de uenige om et tal, vinder `projektstatus.json`.

---

## 1. Hvad projektet er

**steamgears.eu** er en lille dansk webshop med print-on-demand i steampunk/dark academia-stil. Kunden er
typisk en voksen, der skal til cosplay, halloween, marked eller et temafest — ikke en børneforælder.

| Led | Fakta |
| --- | --- |
| Platform | WordPress 7.1.4 + WooCommerce, PHP 8.5.12, hostet hos **Simply** |
| Repo | Dokumentation, assets, plugin-kilde og status — **ikke** live-koden. Sitet ændres over MCP |
| Katalog | **24 produkter** målt 8/11: **20 publicerede, 4 kladder**, 26 billeder i alt (højst 2 pr. produkt); produceret via **Printful**. Ældre logger nævner Printify-plugin'et, som ikke er leverandøren |
| Indhold | **29 sider** + 18 guides i guide-stakken; seneste roadmap måler sitemap og live-siderne |
| Sprog | Engelsk på sitet, **dansk i commits og dokumentation** (tekniske termer på engelsk) |
| Design | "Dark Clockwork Theatre v3"; template part til headeren ligger i [`css/`](../css/) |

Der er **seks aktive projekter**, alle målt i [`projektstatus.json`](../projektstatus.json) (status pr. 8/11 2026):

| id | Projekt | Status | Enhed |
| --- | --- | --- | --- |
| `site` | steamgears.eu — site-optimering | **100 %** (20,95 af 20,95 d) | developer-dage |
| `plugin` | Steamgears Autopilot — plugin | **100 %** (29,85 af 29,85 d) | developer-dage |
| `rebrand` | Dark academia-rebrand | **65,3 %** (9,4 af 14,4 t) | timer |
| `seo` | SEO-indholdsproduktion | **100 %** (8 af 8 guides) | guides |
| `links` | Døde internlinks | **100 %** (39 af 39 links) | links |
| `niche` | Niche-butik — plugin P4 | **100 %** (1 af 1 d) | developer-dage |

Roadmap'erne: [`docs/roadmap.md`](roadmap.md) (site, S0–S4) og
[`plugins/steamgears-autopilot/ROADMAP.md`](../plugins/steamgears-autopilot/ROADMAP.md) (plugin, P0–P4).
PDF-rapport pr. projekt ligger i [`reports/`](../reports/).

---

## 2. Sådan måles sandheden

Procenter er **beregnede**, aldrig håndskrevne. Ret `done`/`total` i `projektstatus.json` og kør scriptet —
så skriver det `docs/projektstatus.md` (§1 daglig status + §2 append-only log), dagens brief og historikken.

```bash
python3 projektstatus.py --selftest   # 22 fixtures: værnene fanger fejl
python3 projektstatus.py --check      # drift, skema, fald i procent, 100 % uden verified
python3 projektstatus.py --update     # skriv status + brief
python3 projektstatus.py --brief      # kun briefen
```

Værn du ikke kan omgå, og ikke skal prøve på:

- **AUTO-blokke er read-only.** Redigering i `docs/projektstatus.md` mellem markørerne giver `drift` ved næste
  `--check`. Ret i JSON i stedet.
- **100 % kræver `"verified": true`**, ellers afvises tallet.
- **Procent må ikke falde** uden `"scope_change": true` og en forklaring i `basis`.
- **Blandede enheder** (`d` + `t` + `guides`) i samme projekt afvises.
- Efter skrivning læses filerne igen og rulles tilbage hvis indholdet ikke matcher beregningen.

Efter en manuel JSON-ændring vil `--check` med vilje melde *drift* indtil `--update` er kørt. Det er
funktionen, ikke en fejl.

---

## 3. Faktakort — verificeret, ikke antaget

Dette kort er afstemt mod målinger og status pr. **8/11 2026**. Nyere live-tal findes i [`docs/roadmap.md`](roadmap.md), [`projektstatus.json`](../projektstatus.json) og de daterede kilder i `docs/library/`; gamle målinger nedenfor er markeret som historik.

**Sitet**

- Kataloget blev målt 8/11 til **24 produkter (20 publiceret, 4 kladder)**; 46 gamle kladder er i papirkurven.
  Alle 24 har færre end 3 billeder (26 samlet), og 0 af 41 billeder i brug mangler alt-tekst.
- Alle **33 af 33 publicerede indlæg** har mindst ét `/go/`-link; de 22 unikke id'er er målt 302 → produkt → 200,
  og ingen peger på en kladde. Se [`library/s15-produktlink-daekning-2026-11-08.md`](library/s15-produktlink-daekning-2026-11-08.md).
- `/start-here/`, `/build/`, `/inspiration/`, `/shipping/` og `/returns/` er live; shop-H1, mobilheader,
  designsystem og payload-loft er målt og lukket i [`docs/roadmap.md`](roadmap.md) §4.
- `/shop/` målte 263 070 bytes og 2,93–3,16 s 8/11 (mod tidligere 470 KB / 5,8 s). Shoppen bruger fortsat USD,
  Complianz er fortsat US opt-out, og Stripe er den eneste betalingsaftale — ejerbeslutninger står i §4c.
- GA4-dobbelttællingen blev rettet 7/11 i Rank Maths option: én `page_view` blev målt på `/`, `/?nocache=1`
  og `/shop/`; Ads-tagget blev bevaret. Kilde: [`library/analytics-dobbelt-2026-11-06.md`](library/analytics-dobbelt-2026-11-06.md).
- GSC Crawl stats → By response er stadig ejer-blokeret; se S0.6 i roadmappet. Tal og resterende butikspunkter
  skal læses fra den aktuelle roadmap frem for de gamle audits.

**Pluginnet** (`plugins/steamgears-autopilot/`)

- **Version i repo mod live:** repo **7.64.9**, live **7.64.6, aktív** (8/11 2026: `Plugin_Upgrader` fra
  release-zippen, og de installerede filer er SHA-1-identiske med kilden; `SGAP_VERSION` svarer 7.64.6 i et
  frisk request. 7.64.7 er tokenrettelsen, 7.64.8 gør kanalstatus entydig, og 7.64.9 gør P1.5 målbar
  (`channel-health`-abilityen + `tools/live_channels.py`); alle tre venter på ejerens *Plugins → Update*-klik). Ældre
  historik: 7.58.2 blev lagt på 7/11 med `wp plugin install` fra
  repoets zip og `wp plugin activate` gennem Novamira; `/wp-json/sgap/v1` svarer 200 med 19 ruter, inkl. de tre
  `/facebook/*`). Vejen dertil kostede en runde: 7.58.0 lå på disken men var **inaktiv**, WooCommerce var også
  inaktiv, og `mu-plugins/sga-payload-trim.php:134` kaldte `is_woocommerce()` → **500 på hver side**. Efter
  aktiveringen blev 500 ved med at komme fra **W3 Total Caches cachede fatale side**, indtil `w3tc_flush_all()`.
  Plugin-listen er ryddet (18 slettet, 5 beholdt inaktive fordi de har data): se
  [library/genaktivering-og-oprydning-2026-11-07.md](library/genaktivering-og-oprydning-2026-11-07.md). 7.58.1 retter de fire referencer til
  `SGAP_REST::NS`; se `docs/library/rest-nedbrud-7-58-0-2026-11-07.md`. Et plugin-folder kan kun skiftes af
  WordPress selv, så en release slutter med ejerens *Plugins → Update*-klik, ikke med et push.
  Status: `docs/roadmap.md` §2b; procedure og efterkontrol: plugin-README §Updates.
- 58 klasser, 45 PHP-filer, 20.424 linjer (målt 8/11 på 7.64.9); hvert subsystem kan nås fra en rigtig
  indgang (cron, REST, admin-side, ability eller AJAX). Version **7.19.0** tilføjede
  helsides SEO/GEO/AEO-auditten (`includes/site-seo.php`), som erstatter den per-side-optimering der krævede
  ét klik pr. URL. **7.20.0** tilføjede pre-flight-valideringen (`includes/preflight.php`): hver kø-post tjekkes
  mod nettets egne grænser før det første Publer-kald, og det der kan rettes mekanisk (caption-længde, hashtags)
  rettes i stedet for at blive afvist. **7.21.0** tilføjede blackout-kalenderen (`includes/blackout.php`):
  datointervaller hvor intet frigives, og kø-poster flyttes til næste frie dag samme klokkeslæt. **7.22.0**
  tilføjede quality-gaten (`includes/quality.php`) i `SGAP_Queue::add()`: bandeord, døde links, manglende
  alt-tekst og næsten-dubletter stoppes før posten når køen. **7.23.0** tilføjede `meta-audit`
  (`includes/meta-audit.php`): en læsende gennemgang af titler og metabeskrivelser med de værste poster først.
  **7.24.0** gjorde `utm_campaign` til en parameter:
  indstillingen *Campaign name* plus et `campaign`-felt pr. post i `/queue` og `queue-post`, så en navngivet
  kampagne kan måles i GA4 ved siden af evergreen-strømmen. **7.53.1** tilføjede fejlsøgning som fast kørsel
  (`tools/fejlscan.py`, værn nr. 23–24) og rettede fem fejl i Pinterest-modulet og dets tests — se
  `docs/library/fejlsogning-fast-vaern-2026-11-07.md`. **7.54.0** tilføjede Instagram-insights (P1.5c):
  `includes/instagram.php` læser `views`, `likes`, `comments`, `shares` og `saved` pr. medie — `views` afløser
  det deprecerede `impressions`, en metric Instagram endnu ikke har rapporteret udelades i stedet for at blive
  gemt som 0, og matchningen sker på captionen mod `/go/<id>/`. **7.57.0** gjorde fejlsøgningen til et fast
værn, og **7.58.0** tilføjede Facebook-siden som kanal (P2.6): publicering, insights og moderation med
direkte publicering som opt-in. **1 128 smoke-checks, alle grønne** (målt 8/11 2026, efter 7.64.9).
- **Niche-butikken (P4) er færdig og målt live:** ét niche-navn blev til 5 kollektioner (210–214), 8
  produktkladder (1517–1524) med `_price`/`_regular_price`/SKU/`_sgap_niche`, en sideskits (1525) og en
  `wp_navigation` (1526) med 8 links — og rollback via admin-knappen slettede alle 15 rækker og gav en
  tilstand identisk med før-kørslen. 1,0 af 1,0 d, 100 %. Kilde:
  [`library/niche-butik-2026-11-08.md`](library/niche-butik-2026-11-08.md).
- Kæden virker: generér → godkend → publicér (RSS eller Publer API) → mål.
- Self-updateren virker: zip i repoet er **byte-identisk** med kilden; 20 versioner på fire dage.
- 9 AJAX- og 5 admin_post-handlere har capability + nonce; **kun ét `wp_mail`** i hele pluginnet (ugerapport).
- **Mangler (målt 8/11):** kun den **live-verifikation** der kræver ejerens tokens. Alle seks kanaler er bygget
  (P1.5a–c) og kanalstatus skelner `ready`/`needs_refresh`/`missing_token`/`refused`/`needs_scope`.
  Pinterest har nu et token — det ligger i **Pinterest for WooCommerce**, og 7.64.6 læser det gennem
  integrationens egen dekryptering, men appen mangler `boards:read`, så `/pins` afvises; de fem andre kanaler
  har intet token. P3.1 er lukket: `labor_rate` læses (7.59.0). Alt det andet er bygget:
  fejl-alarmering, kø-udtørrings-alarm, backoff og auditlog (P0.1–P0.5), P1.4 evergreen-recycling (7.41.0),
  Etsy skjult med manuel import (7.33.4), og den døde metode plus de ni usynlige indstillinger er væk.
- `uninstall.php` rydder 9 tabeller, alle `sgap_%` options/transients og 12 cron-hooks plus `sgap`-gruppen i Action Scheduler.
- Cron er WP-Cron på et cachet site → publicering kan blive forsinket (`DISABLE_WP_CRON` detekteres).

**Rebrand**

- De oprindelige 39 designs er kortlagt; batch 1–3 (8,5/8,5 t) er udført, og **tildelingen er gennemført 8/11**
  (0,4/0,4 t) gennem Novamira-MCP: 11 publicerede produkter, term-tællinger 5/3/2/1/3, witchy-tarot 0 fordi dens
  tre produkter ligger i papirkurven. Batch 4 mockups står på 0/5 t, forberedelsen er lukket med 0,5/0,5 t.
- Samlet status er **65,3 % (9,4 af 14,4 t)**. Mockup-generering kræver ejerens Printful-adgang eller færdige
  billeder; witchy-tarot kræver et ja/nej til at gendanne 567/395/411;
  GPSR-oplysninger kræver CVR og postadresse. Se `projektstatus.json` og `docs/library/batch-4-arbejdsordre-2026-11-08.md`.

---

## 4. Adgangsmatrix — hvad du kan gøre herfra

| Kapacitet | Status | Konsekvens |
| --- | --- | --- |
| Læse/skrive filer, git commit | ✅ | alt repoarbejde |
| Push til GitHub | ✅ kun via `cloud_git` | aldrig force-push |
| Læse live HTML + REST (`wp-json`) | ✅ | strukturelle tjek, linkcrawl, produktdata |
| Python 3.12 + PDF-biblioteker | ✅ i `tools/_deps` | rapporter i `reports/` |
| PHP 8.5.8 (statisk binær) | ✅ i `tools/_deps/bin/php` | `php -l`, ikke tests |
| **Skrive til sitet** | ✅ via autoriseret MCP, når OAuth er godkendt i den aktuelle sandkasse | Hver ny sandkasse kræver ejerens browsergodkendelse. Plugin-installation kræver ejerens *Plugins → Update*-klik. Opret kun kladder efter tydelig opgave, og publicér aldrig ejerafhængige data på et gæt |
| MCP mod sitet | ✅ når ejeren har godkendt OAuth i denne sandkasse | AI Engine `/wp-json/mcp/v1/http` og Novamira `/wp-json/mcp/novamira-oauth`; credential ligger uden for repoet. Hver ny sandkasse kræver ejerens browsergodkendelse igen. Se `docs/mcp-auth.md`; antag ikke at token eller værktøjsantal fra en tidligere sandkasse gælder her |
| Publer MCP | ❌ ingen `PUBLER_API_KEY` | kan ikke planlægge opslag |
| Browser / Lighthouse | ❌ | ingen visuel eller ydelsesverifikation |
| Root / `apt` | ❌ | brug statiske binærer (se opskrifter) |
| WordPress + PHPUnit | ❌ | plugin-tests kører kun i CI |

Genskab værktøjerne efter en reset af sandkassen:

```bash
bash tools/setup-agent-tools.sh          # PHP 8.5 + fpdf2/pypdf/pypdfium2 i tools/_deps
PYTHONPATH=tools/_deps python3 tools/project_reports.py
tools/_deps/bin/php -l plugins/steamgears-autopilot/steamgears-autopilot.php
```

**Blokeringer der kræver ejeren:** OAuth-browsergodkendelse i en ny sandkasse; *Plugins → Update* til 7.64.9
(tokenrettelsen, kanalstatus-entydigheden og P1.5-målingen) og en genforbindelse af Pinterest med `boards:read`; P1.5-kanalnøgler; Printful-kostpriser/mockups; ja/nej for produkterne
942/765/766/767; samt CVR, navn, adresse, telefon, valuta/priser, betalingsaftaler, Complianz-valg og
forsendelseszoner. GSC Crawl stats → By response kræver ejerens adgang. Se den aktuelle og komplette liste i
[`docs/roadmap.md`](roadmap.md) §4c. S0.4's vanity-301'er er lukket som overflødige — opret ikke en Redirection-gruppe.

### Simply.com-connectoren (`simplycom`)

Hostens egen MCP-server (`https://mcp.simply.com/v2`, OAuth 2.1, ingen nøgle) er den anden halvdel af
adgangen: Royal MCP rører WordPress, connectoren rører **hostens** flade — DNS, nameservere og DNSSEC,
mailkonti, subdomæner, MySQL, website- og slow-logs, deploy-oplysninger og support-sager. Den kan **ikke**
bestille, opsige eller betale, og login kræver en browser, så kun ejeren kan gennemføre det første kald.
Opsætning, login og tilbagekald står i [`mcp-auth.md`](mcp-auth.md#simplycom-simplycom).

**A1 er ikke længere en P0.** Målingen 2/11 viste at 455 rammer **spoofede** bot-UA'er fra denne maskines
IP, ikke Google: en `site:`-søgning viser indekserede sider med friske datoer, og GSC-data viser sitemap hentet
31/10. Simply hvidlister allerede Googlebot, bingbot, OpenAI, Anthropic med flere — på IP — og kræver en
**offentlig IP-liste** for at hvidliste nye tjenester, hvilket en agent-container ikke har. Hele sagen står i
[`library/waf-diagnose-2026-11-02.md`](library/waf-diagnose-2026-11-02.md).

Connectoren giver DNS, mail, databaser, logs og support-sager — **ikke** WAF-regler. Den kan altså ikke
hvidliste noget. Det der stadig venter:

| Fund | Hvad der mangler | Uden det |
| --- | --- | --- |
| **A1** (P1) | Læse GSC → Crawl stats → By response og bekræfte at Googlebot får 200 | Googlebotens faktiske svar er ikke dokumenteret; indekseringen er |
| **B1** (P1) | Afklare hvem der ejer `robots.txt`: host mu-plugin, Rank Math eller W3TC | De to `User-agent: *`-grupper bliver stående — kræver WP-adgang |
| **I1** (P2) | Samme Crawl Stats-måling som A1, for sitemap og 455/5xx | Sitemappet er kun verificeret for neutrale klienter |
| **S0.6** i [`roadmap.md`](roadmap.md) | ✅ Omskrevet 2/11 — præmissen »søgemaskiner kan ikke crawle« er væk, og punktet er nu samme opgave som **A1**: læs GSC Crawl stats | Intet ud over A1 |

Crawl og verifikation kører med **neutral UA** ([`tools/waf_check.sh`](../tools/waf_check.sh) bekræfter at den
giver 200). At efterligne Googlebot er både unødvendigt og det der udløser 455. Alt andet i auditten — titler,
schema, intern linkning, ydeevne — kræver WordPress-skriveadgang (findes nu over MCP, se `docs/mcp-auth.md`)
eller en ejerbeslutning.

---

## 5. Opskrifter

```bash
# Status og værn
python3 projektstatus.py --check

# Linkcrawl af én side (soft-404 fanges ikke af statuskoder — se §6)
curl -s -A 'Mozilla/5.0' https://steamgears.eu/<side>/ \
  | grep -oE 'href="https://steamgears\.eu/(product|product-category)/[^"]+"' | sort -u

# REST uden auth (læsning)
curl -s 'https://steamgears.eu/wp-json/wp/v2/product?per_page=100&_fields=id,slug,link' | jq 'length'
curl -s -D- -o /dev/null 'https://steamgears.eu/wp-json/wp/v2/product?per_page=1' | grep -i x-wp-total

# MCP-håndtryk (Bearer-token fra OAuth ligger i ~/.config/steamgears-mcp, ikke i repoet)
python3 tools/mcp_oauth.py --status        # er der et gyldigt token?
python3 tools/mcp_rest.py --check          # initialize + tools/list mod /wp-json/mcp/v1/http
python3 tools/mcp_rest.py --abilities --diff docs/library/mcp-adapter-abilities.csv

# Offentlig læsning uden nogen credential (Store API)
curl -sS 'https://steamgears.eu/wp-json/wc/store/v1/products?per_page=1' | head -c 200
```

MCP-opsætningen i detaljer (målt auth-matrix, API-nøgle vs OAuth, fejlkoder, Publers to nøgletyper):
[`docs/mcp-auth.md`](mcp-auth.md).
Profilen kan også læses over MCP via den lokale server `tools/mcp_project_profile.py`
(se [`../.mcp.json`](../.mcp.json)) — den kræver ingen site-credential.

---

## 6. Fælder der allerede er betalt for

| Fælde | Lærdom |
| --- | --- |
| **Soft-404** | WordPress serverer 200 med "produkt ikke fundet"-tekst for slettede produkter. Verificér på **sidetitel/indhold**, ikke statuskode. 39 døde links var usynlige i en statusbaseret crawl. |
| **WAF** | `curl` mod HTML gav `454 Checking your browser`, mens `/wp-json/` svarede 200. Konkludér aldrig "sitet er nede" ud fra én blokeret HTML-forespørgsel — prøv REST. |
| **TTF i PDF** | Med indlejret TrueType skriver fpdf2 glyf-koder, så `grep` i PDF-bytes finder intet. Brug `pypdf` til tekst og `pypdfium2` til at *se* siden. |
| **pip uden root** | `pip install` fejler på `site-packages`. Brug `--target tools/_deps` + `PYTHONPATH=tools/_deps`. |
| **Ingen PHP** | `apt` kræver root. Hent statisk binær fra `dl.static-php.dev/static-php-cli/bulk/` (nyeste `php-X.Y.Z-cli-linux-x86_64.tar.gz`). |
| **Værktøjs-gaten** | Platformens gate afviser en turns første batch uden en `AGENTS.md`-læsning. Læg læsningen i samme batch som arbejdet. |
| **Parallelle skrivninger** | En skrivning og dens efterkontrol må ikke køre i samme batch — repoet kan stå halvt opdateret. |
| **Plugin-zip** | Zip i `plugins/` skal være byte-identisk med kilden, ellers opdaterer self-updateren til noget andet end repoet. |
| **Slettede filer** | Brug `git rm` (ikke `rm`), så indholdet kan hentes med `git show <commit>:<sti>`. |

---

## 7. Sådan arbejder du i repoet

1. Læs `AGENTS.md` + denne fil, og kør `python3 projektstatus.py --check` før du ændrer noget.
2. Meld **Modul / % før / % efter / Δ / Grundlag** i svaret — og den daglige status for alle fem projekter.
   Blokeret arbejde tæller ikke som fremgang.
3. Nye dokumenter i `docs/`, audits og research i `docs/library/`, og de skal skrives ind i
   [`docs/library/README.md`](library/README.md) — ellers bliver de forældreløse.
4. Én roadmap pr. scope: site i `docs/roadmap.md`, plugin i `plugins/steamgears-autopilot/ROADMAP.md`.
   Én SEO-kilde: `docs/seo.md`.
5. Commit-beskeder på dansk, med `🤖 Generated with Codebuff` + `Co-Authored-By`-fod. Push med `cloud_git` i
   samme tur — og senest af den natlige auto-push kl. 02 dansk tid (`.github/workflows/natlig-push.yml`), der
   committer alt i træet og pusher med genforsøg. Nattens kørsel er et sikkerhedsnet: den ser ikke ucommittet
   arbejde i en sandkasse, så et push i turen er stadig den regel der gælder.
6. **Rør ikke** site-data, nøgler eller `.mcp.json`-hemmeligheder. Nøgler hører i miljøvariabler, aldrig i repo.

## 8. Kort over viden

| Spørgsmål | Fil |
| --- | --- |
| Hvad skal bygges, i hvilken rækkefølge, hvor lang tid? | `docs/roadmap.md`, `plugins/steamgears-autopilot/ROADMAP.md` |
| Hvordan publiceres den næste side hurtigt og sikkert? | [`library/deploy-playbook-2026-11-08.md`](library/deploy-playbook-2026-11-08.md) — faser, kontrolscript, DoD, fælder |
| Hvilken metode skal et **nyt** site bygges på — og hvordan sikres slutproduktet? | [`library/website-guide-2026-11-08.md`](library/website-guide-2026-11-08.md) — Git + staging vs. vores metode, produktbrief, kritisk brugerrejse, server-/sandbox-forhold (§9) og målbare release-gates |
| Hvor langt er vi, og hvorfor? | `projektstatus.json` → `docs/projektstatus.md` |
| SEO-regler, GEO-skabelon, måling | `docs/seo.md` |
| MCP-auth, fejlkoder, Publer, Simply | `docs/mcp-auth.md` |
| Alt hvad der er undersøgt, med dato | `docs/library/README.md` (indeks) |
| Butik, produkter, tekster, mockups | `docs/library/butiksaudit-*`, `produkttekster-*`, `mockup-brief-*` |
| Hvad der virker / ikke virker pr. projekt | `reports/*.pdf` |
| Plugin-arkitektur og subsystemer | `plugins/steamgears-autopilot/README.md` |
