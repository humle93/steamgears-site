# AI Engine-opdatering på steamgears.eu — tjekliste før og efter

3/11 2026. Søstertjekliste til [WooCommerce-tjeklisten](woo-opdaterings-tjekliste-2026-11-03.md), men med et
andet fokus: **Woo-opdateringen kan fjerne værktøjer, AI Engine-opdateringen kan flytte dem alle på én gang.**
AI Engine er ikke bare endnu et plugin her — den **er** MCP-serveren på `/wp-json/mcp/v1/http`, den ejer
OAuth-fladen, og dens abilities-bro bestemmer hvilke abilities fra alle andre plugins der overhovedet bliver
værktøjer. Ændrer Meow den bro, ændrer vores værktøjsliste sig uden at nogen har rørt sitet.

**Status nu:** installeret **3.9.0**, og `api.wordpress.org` svarer også **3.9.0** (2/11 2026, `requires_php`
**8.1**, testet til WP **7.1.4**). Der venter altså ingen opdatering i dag — tjeklisten er til den dag der
kommer en 3.9.x eller 3.10. Den er ikke teoretisk: 3.9.0's changelog er næsten en liste over nye
MCP-værktøjer (undo, approvals, blokredigering, SEO/ACF, builders, menuer, CSS, stilarter, coupons,
variationer, kommentarer, previews, Redirection, form entries, fejllog, Site Health, revisioner). Meow sender
MCP-ændringer i næsten hver mindre release, så næste gang er ikke et spørgsmål om «om».

## 1. De seks mekanismer der kan flytte værktøjslisten

Alle linjenumre er fra 3.9.0's release-zip (`labs/`), læst 3/11 2026.

**M1 · Nye, fjernede eller omdøbte indbyggede værktøjer.** De 74 navne står i `labs/mcp*.php`, ét pr.
værktøj, i de filer der bygger `tools/list`. Hver tilføjelse eller omdøbning flytter totalen — og kan fjerne et
navn som et fast prompt eller en dokumenteret opskrift peger på.

**M2 · Broen: hvilke abilities der eksponeres.** `labs/mcp-abilities.php:41-53` filtrerer: `mcp.public`
sat til `false` → ude; ellers inde hvis `mcp.public` er `true` **eller** `show_in_rest` ikke er tomt. Ændres
den regel, skifter hele ability-laget på én gang — Woo-spejlene, Rank Math og vores egne 32.

**M3 · Navne-sanitering og 64-tegns-klip.** `labs/mcp-abilities.php:230` erstatter alt ud over
`a-zA-Z0-9_-` med `_`, og `:235` klipper navne over 64 tegn til 57 tegn plus `_` plus 6 md5-tegn. Ændres
det, holder vores `steamgears-autopilot_`-prefix ikke længere, og `--split`'s spande flytter sig.

**M4 · Kollision: indbyggede værktøjer vinder.** `labs/mcp-abilities.php:84-96` springer en ability over
hvis værktøjsnavn allerede er taget, og skriver kun én `error_log`-linje pr. dag. Et nyt indbygget værktøj
kan altså fjerne en ability fra listen **uden at klienten får en fejlmeddelelse**.

**M5 · Vores egen liste bliver forældet.** `tools/mcp_rest.py:43` har de 74 navne hardcodet. Et nyt indbygget
værktøj havner derfor i spanden `ability_bridge` og kan fejltolkes som »Woo-spejlet er tilbage«. Det er den
fælde der koster en runde: `--split` siger `ability_bridge` om alt den ikke kender, så et nyt
AI Engine-værktøj og et nyt tredjeparts-ability ser ens ud i svaret. Derfor skal AI Engine-antallet
efterprøves mod kilden (§5), ikke kun mod totalen.

**M6 · OAuth- og discovery-fladen.** `/.well-known/*` og `mcp/v1/oauth/{authorize,token,register,revoke}`;
3.9.0 flyttede token-opsætningen til «Connect»-fanen. Ændres scope, ressource-URL eller grant-typer, dør
tokenet — og `tools/mcp_rest.py` svarer 401 uden at værktøjslisten er rørt.

## 2. Før opdateringen — uden credential (kan køres nu)

Syv målinger, samme syv efter opdateringen. Baseline er målt 3/11 2026.

**A1 · Version der opgraderes til, PHP-krav og WP-test**

```bash
curl -gsS 'https://api.wordpress.org/plugins/info/1.2/?action=plugin_information\
&request[slug]=ai-engine' \
  | python3 -c "import json,sys;d=json.load(sys.stdin)\
;print(d['version'],d['requires_php'],d['tested'])"
```

Baseline: `3.9.0` · PHP **8.1** · WP 7.1.4 · opdateret 2/11 2026.

**A2 · REST-namespaces**

```bash
curl -sS https://steamgears.eu/wp-json/ \
  | python3 -c "import json,sys;print(sorted(\
json.load(sys.stdin)['namespaces']))"
```

Baseline: **46** namespaces, med `mcp`, `mcp/v1`, `mwai/v1`, `mwai-ui/v1`, `wp-abilities/v1`.

**A3 · Beskyttet ressource**

```bash
curl -sS https://steamgears.eu/.well-known/oauth-protected-resource
```

Baseline: `200` · resource `/wp-json/mcp/v1/http` · `scopes_supported: ["mcp"]` ·
`bearer_methods_supported: ["header"]`.

**A4 · Authorization server**

```bash
curl -sS https://steamgears.eu/.well-known/oauth-authorization-server
```

Baseline: `200` · issuer `/wp-json/mcp/v1` · `authorization_code` + `refresh_token` · PKCE `S256` ·
`none` / `client_secret_basic` / `client_secret_post`.

**A5 · Fingerprint af MCP-ruten**

```bash
curl -sS -X POST -H 'Content-Type: application/json' \
  -d '{"jsonrpc":"2.0","id":1,"method":"tools/list"}' \
  -D - -o /dev/null https://steamgears.eu/wp-json/mcp/v1/http \
  | grep -i 'www-authenticate\|allow-headers'
```

Baseline: `401` `rest_forbidden` · `Bearer realm="MCP"` med `resource_metadata` pegende på
`…/?rest_route=/mcp/v1/.well-known/oauth-protected-resource` · tilladte headers: `Authorization`,
`X-WP-Nonce`, `Content-Disposition`, `Content-MD5`, `Content-Type`, `Mcp-Protocol-Version`,
`Mcp-Session-Id`, `Accept`.

**A6 · MCP-statusruten** og **A7 · abilities-API'et anonymt**

```bash
for u in mwai/v1/mcp/status wp-abilities/v1/abilities; do
  printf '%-32s ' "$u"
  curl -sS -o /dev/null -w '%{http_code}\n' "https://steamgears.eu/wp-json/$u"
done
```

Baseline: `401` på begge.

A5 er den billigste vagthund: den koster intet login og viser både at ruten stadig ejes af AI Engine og om
OAuth-fladen er ændret. A3/A4 viser om scope- eller endpoint-navne er skiftet — det er den slags ændring der
får et gyldigt token til at se dødt ud.

## 3. Før opdateringen — med credential (kan ikke køres herfra)

| # | Måling | Hvorfor |
| --- | --- | --- |
| B1 | `python3 tools/mcp_rest.py --split` — gem hele JSON'en | `total`, `counts.vores`, `counts.ai_engine`, `counts.ability_bridge`, `mangler_live`, `uventet_live`. Dokumenteret baseline: total **206**, vores **32** (7.23.0), ai_engine **74** → `ability_bridge` **100** (sidste tal er regnet, ikke målt) |
| B2 | `python3 tools/mcp_rest.py --check` | `ok: true` og servernavnet `AI Engine - Steamgears`. Fejler den, er problemet token eller rute — ikke værktøjerne |
| B3 | Prompts og resources | 3.9.0 har fire prompts (`tour_my_site`, `write_a_draft`, `audit_my_content`, `improve_a_post`) og **nul** resources. Kommer der resources, enumererer klienter noget nyt |
| B4 | Niveauer og sperrer | Er «Drafts Only» og de nye «approvals» stadig slået til som før? En ændret default gør skrivende kald afvist, og det ser ud som en ødelagt integration |
| B5 | Kollisionslinjer i fejlloggen | Linjer med `Ability … skipped: the tool name … is already used`. Kan læses med AI Engines eget værktøj `wp_get_error_log` (tilføjet i 3.9.0) eller i Site Health |
| B6 | PHP-version | `requires_php` er **8.1** i dag. Hæves den i næste release, skal PHP hæves **før** plugin'et, ellers afvises aktiveringen |

## 4. Selve opdateringen

1. **Mål A1–A7 og B1–B2, og gem output som fil.** Uden et »før« er der intet at sammenligne med.
2. **AI Engine alene** — ikke i samme klik som Woo, `mcpwp` eller `vibe-ai`. Tre plugins registrerer
   MCP-agtige ruter, og triage-dokumentet har stadig til gode at afgøre hvem der ejer `/mcp/v1`
   ([triage](plugin-opdateringer-triage-2026-11-02.md)). A5 afgør det: `resource_metadata` peger på
   `mcp/v1`, og `mwai`-namespacet er AI Engines.
3. **Ikke midt i en kampagne.** Clockwork Punk-køen kører 3.–14/11
   ([kampagneplanen](clockwork-punk-kampagne-2026-11-02.md)); falder MCP-værktøjerne væk, kan køen ikke
   læses eller tjekkes imens.
4. **Forvent at nogle navne er væk.** Omdøbte indbyggede værktøjer er normal udvikling, ikke en fejl — men
   et fast prompt eller en dokumenteret opskrift der peger på det gamle navn skal rettes.
5. **Rør ikke `AI_ENGINE_NATIVE` før udtrækket i §5 er kørt.** Listen er vores eneste skelnen mellem
   »AI Engines egne« og »alt andet«; en håndrettet liste gør `--split` værre end ingen liste.

## 5. Sådan udledes de 74 navne igen (efterprøvet 3/11 2026)

Kildeudtrækket giver **82** navne, fordi fire prompts og fire ikke-værktøjs-navne også står som `'name' =>`.
Træk de otte fra, og resultatet skal være præcis de 74 i `tools/mcp_rest.py:43`:

```bash
cd /tmp && rm -rf ae && mkdir ae && cd ae
curl -gsS -o ae.zip https://downloads.wordpress.org/plugin/ai-engine.3.9.0.zip
unzip -q ae.zip
python3 - <<'PY'
import re, glob, sys
names = set()
for f in glob.glob('ai-engine/labs/mcp*.php'):
    t = open(f, encoding='utf-8', errors='replace').read()
    names |= set(re.findall(r"'name'\s*=>\s*'([a-z0-9_]+)'", t))
# fire prompts + fire navne der ikke er værktøjer (prompt-argumenter m.m.)
noise = {'audit_my_content', 'improve_a_post', 'post', 'sidebars_widgets',
         'topic', 'tour_my_site', 'widget_', 'write_a_draft'}
found = names - noise
sys.path.insert(0, 'tools')   # kør fra repoets rod
try:
    import mcp_rest
except ImportError:
    sys.path.insert(0, '/sti/til/repo/tools')
    import mcp_rest
known = set(mcp_rest.AI_ENGINE_NATIVE)
print('fundet', len(found), '| kendt', len(known))
print('nye   :', sorted(found - known))
print('væk   :', sorted(known - found))
PY
```

Er `nye`/`væk` tomme, er listen stadig korrekt. Er de ikke, skal hvert navn klassificeres ved hånden —
og `noise`-sættet tjekkes samtidig, for en ny prompt eller et nyt prompt-argument dukker op som et falsk
værktøj. Opdatér `AI_ENGINE_NATIVE`, kør `python3 tools/test_mcp_rest.py` (18 tests, heraf den der kræver
74 navne), og skriv den nye version i kommentaren over listen.

## 6. Efter opdateringen — acceptkriterier

| Måling | Godkendt hvis | Hvis ikke |
| --- | --- | --- |
| A2 namespaces | Stadig 46, og `mcp/v1`, `mwai/v1`, `wp-abilities/v1` til stede | Mangler `mcp/v1`, er MCP-serveren ikke registreret — rul tilbage |
| A3/A4 discovery | Samme scopes (`mcp`), samme endpoints, `200` | Ændrede scopes betyder nyt token; ændrede URL'er betyder at klienter skal rettes |
| A5 fingerprint | `401`, `realm="MCP"`, `resource_metadata` peger på `mcp/v1` | `200` anonymt er en åben dør. `500`/`404` er brudt registrering |
| B1 total | `206 ± AI Engines ændringer`, og **`vores` stadig 32** | Falder `vores`, er det M3 eller M4 — ikke en Woo-sag |
| B1 ai_engine | Svarer til §5-udtrækket for den nye version | Afvigelse = `AI_ENGINE_NATIVE` er forældet, ikke at værktøjerne mangler |
| B2 `--check` | `ok: true` | 401 → token; 404 → rute |
| B4 skrivning | Samme adfærd som før (Drafts Only/approvals) | Ændret default skal være en beslutning, ikke en overraskelse |
| B5 fejllog | Ingen **nye** `skipped`-linjer | Nye linjer = M4: et indbygget værktøj har overtaget et ability-navn |

## 7. Hvornår der rulles tilbage

- A5 giver ikke `401`, eller `mcp/v1` er væk fra A2 (MCP-serveren er nede).
- B2 fejler med 401 **og** et nyt token kan ikke skaffes — altså ingen vej ind.
- `vores` falder i B1, og årsagen er ikke M3/M4 men at abilities ikke registreres.
- Nye `skipped`-linjer i B5 rammer vores egne 32 eller de abilities kampagnen bruger.
- PHP-kravet i A1 er højere end sitets PHP (B6) — så aktiveres plugin'et slet ikke.

Alt andet — nye eller omdøbte indbyggede værktøjer, flere eller færre bridgede abilities — er **forventet
adfærd** og rettes i dokumentationen og i `AI_ENGINE_NATIVE`, ikke med en rollback.

## 8. Hvad der ikke kan måles herfra

1. Selve værktøjslisten (B1–B3) — `tools/mcp_rest.py` stoppede med »ingen credential« 3/11, og
   `wp-abilities/v1/abilities` samt `mwai/v1/mcp/status` svarer `401` anonymt.
2. Om der faktisk ligger en opdatering i wp-admin (A1 viser kun hvad wordpress.org har; sitet kan køre en
   nyere eller ældre build end den offentlige).
3. «Drafts Only», approvals og redigeringsniveauer (B4), fejlloggen (B5) og PHP-versionen (B6).
4. Om `mcpwp` eller `vibe-ai` også registrerer `mcp/v1` — det afgøres i wp-admin, ikke her.

**Om tallene:** 206, 32 og 74 er dokumenterede målinger (2/11 og 7.23.0); `ability_bridge` 100 er regnet ud af
dem. Efter en opdatering er det kun A5 og A2 der kan siges uden login — resten kræver et token, og et token
kræver en browser.
