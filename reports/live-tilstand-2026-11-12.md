# Live-tilstand — MCP-adgang og AI-profil

Målt **2026-11-12T14:44:20Z** gennem Novamira-fladen (`https://steamgears.eu/wp-json/mcp/novamira-oauth`) af `tools/live_state.py`, som kører i den natlige CI-kørsel ([`novamira-keepalive.yml`](../.github/workflows/novamira-keepalive.yml)). Ingen token står i denne fil.

**Dom: bestået** · 5 af 5 krav opfyldt, 3 MCP-værktøjer på fladen.

| Krav | Forventet | Målt | |
| --- | --- | --- | --- |
| AI Engine Dynamic REST | `true` | `true` | ✓ |
| AI Engine Abilities | `true` | `true` | ✓ |
| MCP-rolle | `dokumentation: admin` | `admin` | ✓ |
| Plugin-version på sitet | `7.79.0 (repoet)` | `7.79.0` | ✓ |
| AI-profil | `oplyses (ikke et krav)` | `site_admin` | ✓ |

## Hvad tallene betyder

- **Dynamic REST** og **Abilities** er de to nøgler i AI Engines `mwai_options` som profilen *Site administration* slår til gennem `enable_full_access()`. Står de på `true`, er den fulde REST-flade tændt — uanset om profilen blev sat i går eller 2/11.
- **AI-profil** er den gemte `sgap_ai_profile`. Profilen styrer Autopilotens **egen** AI Chat (værktøjsliste og persona); den eksterne MCP-flade læser de to nøgler ovenfor. Derfor kan profilen skifte uden at `mwai_options` ændrer sig.
- **Plugin-version på sitet** er kun en advarsel: et plugin-folder skiftes af WordPress, så sitet kan stå på en ældre version end repoet, indtil nogen klikker *Plugins → Update*.

Genberegn med `python3 tools/live_state.py` (lokalt, kræver token uden for repoet) — eller se næste natlige kørsel.
