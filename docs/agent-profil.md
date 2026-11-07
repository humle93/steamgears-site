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
| Katalog | **56 publicerede produkter** (målt 2/11; heraf Herbarium-serien 953–972), produceret via **Printify** |
| Indhold | **29 sider** + 18 guides i guide-stakken; sitemap har **108 URL'er** der alle svarer 200 |
| Sprog | Engelsk på sitet, **dansk i commits og dokumentation** (tekniske termer på engelsk) |
| Design | "Dark Clockwork Theatre v3"; template part til headeren ligger i [`css/`](../css/) |

Der er **fem aktive projekter**, alle målt i [`projektstatus.json`](../projektstatus.json):

| id | Projekt | Status | Enhed |
| --- | --- | --- | --- |
| `site` | steamgears.eu — site-optimering | 61,6 % (14,65 af 23,8 d) | developer-dage |
| `plugin` | Steamgears Autopilot — plugin | 74,0 % (19,9 af 26,9 d) | developer-dage |
| `rebrand` | Dark academia-rebrand | 63,0 % (8,5 af 13,5 t) | timer |
| `seo` | SEO-indholdsproduktion | 62,5 % (5 af 8 guides) | guides |
| `links` | Døde internlinks | 100 % (39 af 39 links) | links |

Roadmap'erne: [`docs/roadmap.md`](roadmap.md) (site, S0–S4) og
[`plugins/steamgears-autopilot/ROADMAP.md`](../plugins/steamgears-autopilot/ROADMAP.md) (plugin, P0–P3).
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

Alt her er målt mod den live side eller læst i kilden. Er noget ændret siden 2/11 2026, mål igen.

**Sitet**

- 108 sitemap-URL'er svarer 200; 0 fejl i sitemap; 0 døde interne links (39 rettet, hvert mål crawlet igen).
- Titler over 62 tegn: 19 → 0. Manglende meta descriptions: 3 → 0. Billeder uden alt-tekst: 0 af 210.
- Tre hubs er bygget: `/start-here/` (id 1183, børn 1205/1208/1211), `/build/` (id 1185, børn 1214/1217)
  og `/inspiration/` (id **1243**, verificeret 2/11 via REST). Det er 9 af de 13 sider S2.1 beskriver.
- `/shipping/` og `/returns/` **findes ikke** (REST `pages?slug=` → `[]`). Copy ligger klar i
  `library/roadmap-status-2026-11-02.md` §5, med `[EJER]`-felter markeret.
- Navigationen er `wp:page-list` og viser **alle 29 sider**, inkl. `/cart/`, `/checkout/`, `/my-account/`,
  `/opt-out-preferences/`. Spec ligger klar i `docs/library/navigation-spec-2026-11-02.md` (S2.3).
- Butikken prissætter i **USD**; Complianz kører **US opt-out**; kun **Stripe** som betaling.
- `/shop/` tager ~5,8 s og ~470 KB; produktside har ~151 KB inline CSS og ~105 scripts.
- GA4 fyrer **dobbelt** på hvert pageview, så konverteringer kan ikke aflæses sikkert.

**Pluginnet** (`plugins/steamgears-autopilot/`, v7.41.1)

- **Version i repo mod live:** begge **7.41.1** (live målt 7/11 med `get_plugins()`) — et
  plugin-folder kan kun skiftes af WordPress selv, så en release slutter med ejerens *Plugins → Update*-klik,
  ikke med et push. Status: `docs/roadmap.md` §2b; procedure og efterkontrol: plugin-README §Updates.
- 44 klasser, 30 PHP-filer, 12.356 linjer (målt 6/11 på 7.41.0); hvert subsystem kan nås fra en rigtig
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
  kampagne kan måles i GA4 ved siden af evergreen-strømmen. 523 smoke-checks, alle grønne.
- Kæden virker: generér → godkend → publicér (RSS eller Publer API) → mål.
- Self-updateren virker: zip i repoet er **byte-identisk** med kilden; 20 versioner på fire dage.
- 9 AJAX- og 5 admin_post-handlere har capability + nonce; **kun ét `wp_mail`** i hele pluginnet (ugerapport).
- **Mangler (målt 6/11):** P1.5 platform-analytics (4,0 d), eksternt blokeret af Pinterest-token og Meta-review;
  P3.1 mangler kun `labor_rate` (ejerbeslutning). Alt det andet er bygget:
  fejl-alarmering, kø-udtørrings-alarm, backoff og auditlog (P0.1–P0.5), P1.4 evergreen-recycling (7.41.0),
  Etsy skjult med manuel import (7.33.4), og den døde metode plus de ni usynlige indstillinger er væk.
- `uninstall.php` rydder 8 tabeller, alle `sgap_%` options/transients og 9 cron-hooks.
- Cron er WP-Cron på et cachet site → publicering kan blive forsinket (`DISABLE_WP_CRON` detekteres).

**Rebrand**

- 39 designs kortlagt: 21 dark academia, 6 gothic, 7 steampunk. 32 af 39 har nye titler + SEO-titel + meta.
- 6 kollektioner, 39 nye produkttekster med GPSR-blok. **Mockups er 0 af 5 timer** — hvert produkt har 1–2 billeder.
- Slugs er urørte, så indgående links stadig virker. GPSR mangler postadresse (ingen CVR-data endnu).

---

## 4. Adgangsmatrix — hvad du kan gøre herfra

| Kapacitet | Status | Konsekvens |
| --- | --- | --- |
| Læse/skrive filer, git commit | ✅ | alt repoarbejde |
| Push til GitHub | ✅ kun via `cloud_git` | aldrig force-push |
| Læse live HTML + REST (`wp-json`) | ✅ | strukturelle tjek, linkcrawl, produktdata |
| Python 3.12 + PDF-biblioteker | ✅ i `tools/_deps` | rapporter i `reports/` |
| PHP 8.5.8 (statisk binær) | ✅ i `tools/_deps/bin/php` | `php -l`, ikke tests |
| **Skrive til sitet** | ✅ **virker (målt 4/11 2026)** | `wp_alter_post`, `wp_update_post` og plugin-værktøjerne skriver; 20 links indsat i ni guides og 14 hrefs konverteret på outfit-guiden. `mcp_drafts_only` er `false` — er den `true`, afvises alle skrivende kald, og den kan ikke slås fra over MCP med vilje |
| MCP mod sitet | ✅ **to flader, begge virker** — AI Engine `/wp-json/mcp/v1/http` og **Novamira** `/wp-json/mcp/novamira-oauth` (1.12.14, OAuth 2.1 + PKCE, `full_access`, 3 meta-værktøjer over **207 abilities**, heraf 60 fra plugin'et). Målt 6/11 2026. Tokenet ligger i `~/.config/steamgears-mcp/` (mode 600), aldrig i repoet, og **hver ny sandkasse skal godkende igen** | `/wp-json/mcp/v1/http` (AI Engine), **236** værktøjer, rolle `admin`, Bearer-token fra OAuth med PKCE. `python3 tools/mcp_oauth.py --start` giver linket, `--finish '<kode>'` gemmer token uden for repoet; `tools/mcp_rest.py --check` tester det. Redirecten er `127.0.0.1:8765` **uden lytter**: kopiér hele adressen fra browserens adresselinje. Application password (Basic) er fallback og ikke målt endnu — se `docs/mcp-auth.md` |
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

**Blokeringer der kræver ejeren** (skriv dem i svaret, forsøg dem ikke): ét klik på OAuth-linket fra
`tools/mcp_oauth.py --start` (eller et WordPress application password til REST-skrivning), Publer-nøgle,
repo-secrets `GEMINI_API_KEY`/`WP_USER`/`WP_APP_PASS`, CVR/adresse til GPSR og imprint, Printify-variantmapping,
én Redirection-gruppe til de fem vanity-301'er, og **et nyt OAuth-token i hver ny sandkasse** (`--start` → klik →
`--finish '<kode>'`; token gemmes uden for repoet).

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
| Hvor langt er vi, og hvorfor? | `projektstatus.json` → `docs/projektstatus.md` |
| SEO-regler, GEO-skabelon, måling | `docs/seo.md` |
| MCP-auth, fejlkoder, Publer, Simply | `docs/mcp-auth.md` |
| Alt hvad der er undersøgt, med dato | `docs/library/README.md` (indeks) |
| Butik, produkter, tekster, mockups | `docs/library/butiksaudit-*`, `produkttekster-*`, `mockup-brief-*` |
| Hvad der virker / ikke virker pr. projekt | `reports/*.pdf` |
| Plugin-arkitektur og subsystemer | `plugins/steamgears-autopilot/README.md` |
