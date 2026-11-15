# Printful ud af repoet — Gelato er den eneste printvej (15/11 2026)

**Kort:** Printvejen er **Gelato**, og Printful er ikke længere en mulig vej noget sted i repoet. Pluginnet
fjernede koblingen i **7.88.0** (kostpris-import, nøgle, nøgletest og værktøj, plus oprydning af de to
gemte indstillinger ved opgradering), niche-planerne beder om Gelato, og de gældende dokumenter peger på
Gelato-forbindelsen. Dette notat dækker dokumentlaget: hvilke filer der stadig nævner Printful, hvorfor de
gør det, og hvilke fire der har fået en forældet-banner fordi de ellers kunne læses som en instruktion.

## Hvad der er fjernet i koden (7.88.0)

| Sted | Ændring |
| --- | --- |
| `includes/mcp-woo.php` | `woo-cost-import` og hele kostpris-importen er væk. `cost_for()` læser **kun** produktets `_sgap_cost` og svarer `unknown` med feltets navn |
| Værktøjskatalog | **35** abilities (var 36 med `woo-cost-import`) |
| `includes/core.php` | `printful_api_key` og `printful_store_id` er retired: `RETIRED_OPTIONS` sletter dem ved opgradering, og transient-cachen `sgap_printful_costs` ryddes |
| `includes/keys.php`, `includes/keycheck.php` | Printful-kortet og `check_printful()` er væk; nøgletesten dækker AI og Publer |
| `includes/niche-shop.php`, `deploy/nicher/*.json` | Readiness-listen og de fem niche-planer beder om **Gelato** |

**Konsekvens, sagt højt:** kostprisen kan ikke længere hentes fra en leverandør. `_sgap_cost` sættes på
produktet — i hånden eller af MCP-klienten — og indtil da svarer `woo-catalog` `unknown`, mens
`woo-propose` afviser med feltets navn. Et tal fra en leverandør vi ikke bruger er værre end ingen tal.

## Dokumentlaget

Fire dokumenter kunne stadig læses som en instruktion og har fået en banner øverst:

| Dokument | Hvorfor |
| --- | --- |
| `mockup-brief-2026-11-01.md` | Hele tjeklisten er Printfuls mockup-UI, trin for trin |
| `punk-lancering-2026-11-02.md` | Launch-plan med Printful-produktoprettelse og euro-priser |
| `punk-upload-2026-11-02.md` | "Trykfilen går til Printful" og en mangel-liste over Printful-delen |
| `kostpris-import-printful-2026-11-07.md` | Dokumenterer en funktion der ikke findes længere |

Resten er **daterede målinger** og står urørt, fordi de er bevis for hvad der blev målt den dag:
`butiksaudit-2026-11-01`, `butik-fund-2026-11-02`, `sitewide-audit-2026-11-03`, `om-maerkning-*`,
`deploy-roadmap-2026-11-03`, `tool-gaps-2026-11-02`, `niche-butik-2026-11-08`,
`tee-varianter-spec`, `phase-0-baseline`, `roadmap-slutstatus`, `vedligehold-daglig-log`, daily-briefs
og roadmap'ens §2-log. `batch-4-arbejdsordre-2026-11-08.md` havde allerede sin egen note fra 13/11 om at
printvejen er Gelato.

## Verifikation

- `plugins/tests/smoke.php`: **1 563 PASS · 0 FAIL**.
- `python3 tools/fejlscan.py`: ingen fund (version, zip, includes, bibliotek og testfiler konsistente).
- `python3 tools/patch_notes.py --check`: nyeste note (7.88.0) matcher `SGAP_VERSION`, header og zip-navn.
- Én zip i `plugins/`: `steamgears-autopilot-v7.88.0.zip`.

## Det der stadig mangler

Gelato-forbindelsen. Den kræver ejerens login: `wp-admin → Gelato → Connect`, vælg butik, og kobl til de
eksisterende `SG-AIR-…`-kladder, så butikken ikke får to produkter pr. motiv. Først derefter findes der en
målt kostpris, variant og mockup — og først derefter kan DKK-priserne for 50×70 og 70×100 fastsættes.
