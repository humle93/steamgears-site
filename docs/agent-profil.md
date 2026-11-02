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
| Katalog | **39 publicerede produkter** (posters, tees, hoodies, accessories), produceret via **Printify** |
| Indhold | **29 sider** + 18 guides i guide-stakken; sitemap har **108 URL'er** der alle svarer 200 |
| Sprog | Engelsk på sitet, **dansk i commits og dokumentation** (tekniske termer på engelsk) |
| Design | "Dark Clockwork Theatre v3"; template part til headeren ligger i [`css/`](../css/) |

Der er **fem aktive projekter**, alle målt i [`projektstatus.json`](../projektstatus.json):

| id | Projekt | Status | Enhed |
| --- | --- | --- | --- |
| `site` | steamgears.eu — site-optimering | 43,8 % (9,25 af 21,1 d) | developer-dage |
| `plugin` | Steamgears Autopilot — plugin | 2,0 % (0,45 af 23,0 d) | developer-dage |
| `rebrand` | Dark academia-rebrand | 63,0 % (8,5 af 13,5 t) | timer |
| `seo` | SEO-indholdsproduktion | 0,0 % (0 af 3 guides) | guides |
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
- To hubs er bygget: `/start-here/` (id 1183, børn 1205/1208/1211) og `/build/` (id 1185, børn 1214/1217).
- `/inspiration/`, `/shipping/` og `/returns/` **findes ikke** — de skal 301'es, ikke bygges.
- Navigationen er `wp:page-list` og viser **alle 29 sider**, inkl. `/cart/`, `/checkout/`, `/my-account/`,
  `/opt-out-preferences/`. Spec ligger klar i `docs/library/navigation-spec-2026-11-02.md` (S2.3).
- Butikken prissætter i **USD**; Complianz kører **US opt-out**; kun **Stripe** som betaling.
- `/shop/` tager ~5,8 s og ~470 KB; produktside har ~151 KB inline CSS og ~105 scripts.
- GA4 fyrer **dobbelt** på hvert pageview, så konverteringer kan ikke aflæses sikkert.

**Pluginnet** (`plugins/steamgears-autopilot/`, v7.18.0)

- 15 PHP-filer, ~6.273 linjer, 904 linjer tests; **20 af 21 subsystemer er live**.
- Kæden virker: generér → godkend → publicér (RSS eller Publer API) → mål.
- Self-updateren virker: zip i repoet er **byte-identisk** med kilden; 20 versioner på fire dage.
- 9 AJAX- og 5 admin_post-handlere har capability + nonce; **kun ét `wp_mail`** i hele pluginnet (ugerapport).
- Mangler: fejl-alarmering, backoff, auditlog, kø-udtørrings-alarm, 15 ubrugte indstillinger, 1 død metode
  (`SGAP_Admin::get_setting_help_link()`), og fem Etsy-felter der lover en integration der ikke findes.
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
| **Skrive til sitet** | ❌ 401 | S2.3, S4.1, S4.3, S0.4 kan forberedes, ikke udføres |
| MCP mod sitet | ❌ ingen `ROYAL_MCP_AUTH` | Royal MCP svarer 401 |
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

**Blokeringer der kræver ejeren** (skriv dem i svaret, forsøg dem ikke): WordPress application password
(`ROYAL_MCP_AUTH`), Publer-nøgle, repo-secrets `GEMINI_API_KEY`/`WP_USER`/`WP_APP_PASS`, CVR/adresse til
GPSR og imprint, Printify-variantmapping, én Redirection-gruppe til de fem vanity-301'er.

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
schema, intern linkning, ydeevne — kræver WordPress-skriveadgang eller en ejerbeslutning.

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

# MCP-håndtryk (kræver ROYAL_MCP_AUTH)
curl -sS -X POST https://steamgears.eu/wp-json/royal-mcp/v1/mcp \
  -H 'Content-Type: application/json' -H 'Accept: application/json, text/event-stream' \
  -u "$(cat ~/.royal_mcp_auth)" \
  -d '{"jsonrpc":"2.0","id":1,"method":"initialize","params":{"protocolVersion":"2025-06-18","capabilities":{},"clientInfo":{"name":"verify","version":"1"}}}'
```

MCP-opsætningen i detaljer (Basic vs OAuth, fejlkoder, Publers to nøgletyper, Simply-connectoren): [`docs/mcp-auth.md`](mcp-auth.md).
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
5. Commit-beskeder på dansk, med `🤖 Generated with Codebuff` + `Co-Authored-By`-fod. Push med `cloud_git`.
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
