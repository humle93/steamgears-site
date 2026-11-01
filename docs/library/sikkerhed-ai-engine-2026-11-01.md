# Sikkerhed: AI Engine 3.8.1 på steamgears.eu — CVE-2026-110732

_Undersøgt 1/11 2026 · kun læsende requests mod produktion · gælder siden steamgears.eu, ikke dette repo_

## TL;DR

- Pluginnet **AI Engine (Meow Apps)** kører **3.8.1** på sitet. Det er **under 3.8.7**, som er den version der lukker
  **CVE-2026-110732** (CVSS 7.5, uautentificeret, fører til administrator-overtagelse).
- **Handling: opdatér AI Engine til 3.8.7 eller nyere i wp-admin.** Er MCP-funktionen ikke i brug, slå den også fra —
  så forsvinder angrebsfladen helt, uafhængigt af versionsnummeret.
- Selve det endpoint der blev spurgt til, `/wp-json/mwai/v1/simpleAuthCheck`, er **korrekt lukket** (401 anonymt). Det er
  ikke der hullet er.
- **Ikke bekræftet udnyttet.** Vi har ikke sendt en skrivende request til OAuth-registreringen, fordi det ville oprette en
  klient i produktionsdatabasen. Status er "versionen er omfattet ifølge advisory", ikke "hullet er demonstreret".

## 1. Hvad der blev tjekket

| Tjek | Resultat |
| --- | --- |
| `GET /wp-json/mwai/v1/simpleAuthCheck` | `401 rest_forbidden` — ruten findes, men afviser anonyme |
| `POST` samme rute | `404 rest_no_route` — ruten er kun registreret som GET |
| `GET /wp-json/mwai/v1/mcp/status`, `/settings/options`, `/get_logs` | `401` — også lukket |
| `GET /wp-json/mwai/v1` (ruteindeks) | `200`, ~110 ruter listet (normal WordPress-adfærd, afslører hvilke features er aktive) |
| `GET /wp-content/plugins/ai-engine/changelog.txt` | `200`, nyeste post **3.8.1 (2026/09/24)** |
| `GET /wp-content/plugins/ai-engine/readme.txt` | `455 Security Incident Detected` (Simplys WAF blokerede) |
| `/wp-json/mcp/v1/.well-known/oauth-authorization-server` | `200`, annoncerer `registration_endpoint: /wp-json/mcp/v1/oauth/register` |
| `OPTIONS /wp-json/mcp/v1/oauth/register` | `Allow: POST` — ruten accepterer POST |
| Forsiden | AI Engine-chatbot er **aktivt i brug** (`mwai-popup-container`, `ai-engine/themes/glass.css`) |

Versionen er udledt af `changelog.txt` og bekræftet af at forsiden indlæser `themes/glass.css`, som er 3.8.1's nye tema.
Det kan ikke helt udelukkes at changelog-filen er ældre end den installerede kode, men 3.8.1 er det eneste underbyggede bud —
og **enhver version under 3.8.7 er omfattet**.

## 2. Fundet: versionen er omfattet

WPScan's liste for `ai-engine` (opdateret 31/10 2026) mod den installerede 3.8.1:

| Alvor | Sag | Rettet i | Rammer 3.8.1? |
| --- | --- | --- | --- |
| **7.5 high** | **CVE-2026-110732** — Missing Authorization → administrator-overtagelse via uautentificeret MCP OAuth client registration (off. 29/10) | **3.8.7** | **Ja** |
| 4.3 medium | Missing Authorization (off. 26/10) | 3.8.7 | Ja |
| 4.2 medium | Subscriber+ begrænset sletning af mediefiler via `metadata` | 3.8.5 | Ja |
| 4.9 medium | Editor+ vilkårlig ændring af post-titel/uddrag (`update_post_title`, `update_post_excerpt`) | 3.8.6 | Ja |

Til sammenligning: `< 3.8.1` havde en stored XSS (7.2) — 3.8.1 var altså *fix-versionen* for den. Sitet står præcis på
grænsen og er bagud med alt derefter. Der er ingen grund til at gå længere tilbage; målet er 3.8.7+.

### Hvorfor CVE-2026-110732 er den vigtige

Det er den eneste af de fire der er **uautentificeret** og ender i **administrator**. Patchstack beskriver den som
*high priority / forventet masseudnyttet*, kræver til gengæld at en privilegeret bruger udfører en handling
(klikker på et craftet link til OAuth-consent-siden). Angrebsfladen er MCP OAuth-ruterne, og de er live på sitet:

```
/wp-json/mcp/v1/.well-known/oauth-authorization-server   (offentlig metadata, 200)
/wp-json/mcp/v1/oauth/register                            (Allow: POST)
/wp-json/mcp/v1/oauth/authorize | /token | /revoke | /apps
```

Bruger I ikke MCP (Claude/MCP-forbindelsen til sitet), er der ingen grund til at de ruter svarer overhovedet.

## 3. Opdateringsplan

Anslået samlet tid: **~30 minutter**, hvoraf det meste er verifikation. Alt kan gøres af ejer/admin i wp-admin og
Simply-panelet; intet kræver adgang til serveren.

| # | Handling | Hvor | Tid |
| --- | --- | --- | --- |
| 1 | Notér nuværende version og tag screenshot af Plugins-listen (bevis hvis noget går galt) | wp-admin → Plugins | 2 min |
| 2 | **Opdatér AI Engine til 3.8.7+** (Plugins → Opdateringer, eller Dashboard → Opdateringer) | wp-admin | 5 min |
| 3 | **Slå MCP fra** hvis det ikke bruges (AI Engine → Settings → MCP) | wp-admin | 2 min |
| 4 | Bloker `changelog.txt` og `readme.txt` for plugins i WAF'en — de afslører versioner | Simply kontrolpanel | 10 min |
| 5 | Kør verifikationskommandoerne i afsnit 4 | terminal | 5 min |
| 6 | Tjek for ukendte administratorer og nye OAuth-klienter, hvis der er mindste tvivl (afsnit 5) | wp-admin | 10 min |
| 7 | Skriv dato + version i `docs/library/vedligehold-ugentlig-log.md` og commit denne fil | dette repo | 3 min |

Hvis opdateringen fejler (WAF, filrettigheder eller "download failed"): hent `ai-engine.3.8.7.zip` fra
wordpress.org/plugins/ai-engine og upload manuelt via Plugins → Tilføj ny → Upload. Indtil det lykkes: slå MCP fra
(trin 3) — det fjerner den konkrete angrebsflade uden at røre koden.

## 4. Tjekliste

### Nu (i dag)
- [ ] Screenshot af Plugins-listen gemt (nuværende version dokumenteret)
- [ ] AI Engine opdateret til **3.8.7 eller nyere**
- [ ] MCP slået fra (medmindre det aktivt bruges — så notér hvorfor)
- [ ] `changelog.txt`/`readme.txt` blokeret i WAF
- [ ] Verifikation nedenfor kørt og output gemt

### Efter opdatering (verifikation)
- [ ] `changelog.txt` viser 3.8.7+ som nyeste post
- [ ] `/wp-json/mwai/v1/simpleAuthCheck` stadig `401` anonymt
- [ ] `/wp-json/mcp/v1` svarer **404/401** (MCP slået fra) — eller er bevidst beholdt
- [ ] Chatbotten på forsiden svarer stadig (opdateringen må ikke have brudt den)
- [ ] En AI-draft kan stadig genereres i Autopilot (deler AI-nøgle-sti med AI Engine, hvis samme nøgle bruges)

### Forebyggelse
- [ ] Slå **auto-updates til for sikkerhedsrettelser** på AI Engine (og på `steamgears-autopilot` er det egne releases)
- [ ] Abonnér på WPScan/Patchstack-mail for `ai-engine` og for de øvrige plugins på sitet
- [ ] Tilføj "plugin-versioner" til den ugentlige vedligeholdelsesrutine — `changelog.txt` er den hurtigste vej til at se hvad der kører
- [ ] Overvej at fjerne plugins der ikke bruges; hver aktiv plugin er en offentlig REST-namespace

### Hvis der er mistanke om kompromittering
- [ ] Gennemgå Brugere for nye administratorer og ændrede roller
- [ ] Gennemgå AI Engine → logs/discussions for ukendte aktiviteter
- [ ] Rotér WordPress application passwords (Brugere → Profil) og AI-nøgler (Anthropic/OpenAI/AIMLAPI)
- [ ] Tjek Område → Site Health for ændrede filer, og sammenlign `wp-content/plugins` med wordpress.org's zip

## 5. Verifikationskommandoer (kun læsende)

```bash
BASE=https://steamgears.eu
# 1. Hvilken version kører der? (nyeste post i changelog)
curl -s "$BASE/wp-content/plugins/ai-engine/changelog.txt" | grep -nE '^= [0-9]' | head -3

# 2. Er simpleAuthCheck stadig lukket? (forventet: 401 rest_forbidden)
curl -s -o /dev/null -w '%{http_code}\n' "$BASE/wp-json/mwai/v1/simpleAuthCheck"

# 3. Er MCP stadig eksponeret? (forventet efter trin 3: 404)
curl -s -o /dev/null -w '%{http_code}\n' "$BASE/wp-json/mcp/v1"
curl -s "$BASE/wp-json/mcp/v1/.well-known/oauth-authorization-server" | head -c 120
```

Bemærk: en `POST` til `/wp-json/mcp/v1/oauth/register` **skal ikke** bruges som test — den opretter en OAuth-klient i
produktionsdatabasen. Det er derfor fundet står som "omfattet", ikke "bekræftet udnytteligt".

## 6. Relateret på samme site

- `/wp-json/sgap/v1` svarer `200` → **Steamgears Autopilot er installeret live**, så `/snapshot`, `/stats`, `/feed` og
  `/queue` er offentligt nåbare ruter. De kræver application password (og `/feed` en feed-secret) — men de skal
  tjekkes med samme briller ved hver release.
- `/wp-json/`-indekset er 1,4 MB og lister alle namespaces (`mwai/v1`, `mcp/v1`, `rankmath/v1`, `wc/v3`, `sgap/v1`, …).
  Det er standard WordPress og ikke et hul i sig selv, men det er den nemmeste måde for en angriber at se hvad der er
  installeret — derfor er versions-blokering i WAF'en i trin 4 stadig værd at gøre.

## 7. Kilder

- [Patchstack — AI Engine <= 3.8.6, Privilege Escalation, CVE-2026-110732](https://patchstack.com/database/wordpress/plugin/ai-engine/vulnerability/wordpress-ai-engine-mcp-server-ai-chatbot-for-wordpress-with-claude-chatgpt-gemini-plugin-3-8-6-missing-authorization-to-administrator-account-takeover-vulnerability) — CVSS 7.5, uautentificeret, patchet i 3.8.7, off. 29/10 2026
- [WPScan — ai-engine plugin oversigt](https://wpscan.com/plugin/ai-engine/) — 44 sager, listen brugt i afsnit 2 (opdateret 31/10 2026)
- [Wordfence Threat Intel — samme sag](https://www.wordfence.com/threat-intel/vulnerabilities/wordpress-plugins/ai-engine/ai-engine-the-chatbot-ai-framework-mcp-for-wordpress-386-missing-authorization-to-administrator-account-takeover-via-unauthenticated-mcp-oauth-client-registration)
