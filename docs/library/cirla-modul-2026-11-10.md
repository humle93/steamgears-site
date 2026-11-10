# Cirla som til/fra-modul i Steamgears Autopilot (7.71.0)

**P9 — Cirla er nu et modul, der kan slås til og fra, og som er slukket som standard.**

Roadmap-punktet stod i `docs/samtalepunkter.md` under *Næste skridt*: »Cirla som til/fra-modul i pluginnet
efter dokumentets §9.4 — start med trin 1, modulet slukket som standard.« Kilden er
[`cirla-teknisk-dokumentation-2026-11-06.md`](cirla-teknisk-dokumentation-2026-11-06.md) §9.3–9.5, som
beskriver modulets opbygning, byggetrin og den røgtest der skal bestås. Dette dokument er målingen af det.

## Hvad der blev bygget

| Del | Placering | Virkning |
| --- | --- | --- |
| Modulfil | `modules/cirla/class-cirla.php` | Indlæses kun, når modulet er slået til |
| Til/fra | `sgap_cirla_enabled` (`yes`/`no`, standard `no`) | Hovedafbryder for hele modulet |
| Indstillinger | *Settings & keys → Cirla* | Til/fra, tilladte handlinger, dagligt AI-loft, log-niveau |
| Handlinger | Fire abilities i kategorien `steamgears-autopilot` | `cirla-status`, `cirla-draft`, `cirla-seo-check`, `cirla-social-suggestions` |
| Rettighed | `sgap_cirla_run`, mappet til `manage_options` | En redaktør kan senere få modulet uden at få hele sitet |
| Log | `sgap_cirla_log` (20 rækker) + `SGAP_Diagnostics` | Hver handling med tidspunkt og resultat; fejl også i Fejlsøgning |
| Dagligt job | `sgap_cirla_daily` | Registreres når modulet slås til, ryddes når det slås fra |

**»Slukket« betyder ikke »skjult«.** Hovedfilen kræver først modulfilen, når indstillingen er `yes`:

```php
if (get_option('sgap_cirla_enabled', 'no') === 'yes') {
    require_once __DIR__ . '/modules/cirla/class-cirla.php';
}
```

Et modul der er slået fra kan derfor ikke registrere én ability eller ét cron-job — der er ingen klasse at
kalde. Det er den forskel testen måler først, og den er grunden til at filen ikke blot indlæses og selv
kigger på indstillingen.

## Handlingerne gør kun det sikre

- `cirla-status` — læser modulet: til/fra, tilladte handlinger, næste cron-kørsel, brugt AI-budget, antal
  kladder med `angle = cirla`, de sidste log-rækker. Read-only.
- `cirla-draft` — skriver **ét** opslag for et emne (eller en side) gennem samme AI-kald som den daglige
  forfatter og gemmer det som `pending` i `sgap_ai_drafts` med `angle = cirla`. Kvalitetsgaten kører først;
  et dødt eller upubliceret link stopper kladden. Den køer og publicerer aldrig.
- `cirla-seo-check` — tjekker én side mod pluginets egne meta-grænser (titel 60, beskrivelse 70–155) og
  skriver intet.
- `cirla-social-suggestions` — foreslår op til fem opslag, ét pr. kanal, **uden** at gemme noget.

To ting er føjet til dokumentets §9.4, fordi pluginets egne mønstre kræver dem: et **dagligt loft** for
AI-kald (`sgap_cirla_daily_calls`, standard 5, `0` = ingen) og et **filter** `sgap_cirla_generate`, der kan
erstatte AI-kaldet med en menneskelig redaktør eller en anden model — samme greb som
`sgap_evergreen_variation`. Filteret er også det testen bruger, så intet OpenRouter- eller AIMLAPI-kald
bliver sendt fra en test.

## Røgtesten fra §9.5, målt

| Test | Forventet | Målt |
| --- | --- | --- |
| Modulet slået fra (standard) | Ingen handlinger, ingen cron | ✓ klasse findes ikke, 0 cirla-abilities, 0 cron |
| Slå til | Handlinger dukker op | ✓ 4 abilities, dagligt job planlagt |
| Handling uden rettighed | Afvist med tydelig fejl | ✓ `sgap_cirla_forbidden` navngiver `sgap_cirla_run` |
| Lav kladde | Kladde, intet publiceret | ✓ `INSERT wp_sgap_ai_drafts` med `angle: cirla`, `status: pending`, intet i køen |
| Samme opgave to gange | Ingen dublet | ✓ `duplicate: true`, samme id, ingen ny række |
| Indhold med dødt link | Stoppet og logget | ✓ `sgap_cirla_gate`, fundet står i modul-loggen |
| Slå fra igen | Handlinger og cron væk, kladder bevares | ✓ 0 abilities, cron ryddet, ingen DELETE/UPDATE af kladder |
| `self-check` | Ingen nye fejl | ✓ hele suiten grøn |

Målt: **1 471 PASS / 0 FAIL** (1 443 før; 28 nye checks i `plugins/tests/cirla.php`), `php -l` rent på de
ændrede filer, zip byte-identisk med kilden.

## Afvigelser fra kildedokumentet, med grund

- Indstillingen hedder `sgap_cirla_enabled`, ikke `cirla_enabled`: pluginets indstillingslag præfikser alle
  nøgler med `sgap_`, og en nøgle uden for det lag ville ikke blive registreret, saniteret eller vist.
- Handlingerne ligger i den eksisterende kategori `steamgears-autopilot` i stedet for en ny kategori, så et
  MCP-klient ser dem sammen med resten af pluginnet.
- Det daglige job skriver og sender **intet**. Dokumentet nævner Cron som en del af modulet; her bruges det
  kun til at vise at modulet kører, fordi et job der selv bruger ejerens kredit er en dyr overraskelse.
- `sgap_ai_drafts` er pluginets eksisterende kladdekø; der er ikke lavet en ny tabel.

## Hvad der ikke er verificeret

Ingen WordPress-installation har kørt modulet: formularen på indstillingssiden, `dbDelta`-opgraderingen af en
kørende installation og et rigtigt AI-kald er ikke målt. Kaldene er stubbet gennem `sgap_cirla_generate`, og
sitet kører 7.70.1 (målt 10/11), ikke 7.71.0. Det næste skridt er ejerens: installer zip'en, slå modulet til
under *Settings & keys → Cirla*, og kør `cirla-status` over MCP.
