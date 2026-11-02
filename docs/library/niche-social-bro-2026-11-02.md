# Niche-bro — fra score til draft-række (2026-11-02)

**Status: bygget og testet uden WP-adgang — 100 % af det der kan gøres uden adgang.** Koden er [`niche_bridge.py`](../../niche_bridge.py) med tests i [`tests/test_niche_bridge.py`](../../tests/test_niche_bridge.py).

## Hvorfor den findes

[`niche_scan.py`](../../niche_scan.py) svarer på *hvilke nicher er værd at gå efter*. Autopilotens kø svarer på *hvad skal ud i dag*. De to kan ikke tale sammen af sig selv, fordi et socialt opslag skal bruge en `destination_url` på steamgears.eu — og for en ny niche findes den side ikke endnu.

Der er derfor tre hop, ikke et:

| # | Hop | Ejerskab |
| --- | --- | --- |
| 1 | scan → grøn/gul/nej | `niche_scan.py` (færdig) |
| 2 | niche → side/opslag med en URL, titel og tekst | `write-article` i pluginnet, eller et menneske |
| 3 | side → `sgap_queue` → Publer | `POST /sgap/v1/queue`, derefter `generate-image` til foto |

Broen dækker **hop 1 → 3's payload** og lægger hop 2 som en menneskelig beslutning imellem. Den er bevidst ikke en pipeline der publicerer af sig selv.

## Hvad broen gør

Tager de grønne nicher fra scanneren, slår deres URL op i en linkfil og laver præcis de felter `SGAP_REST::create_queue` læser:

| Felt | Værdi | Hvorfor |
| --- | --- | --- |
| `status` | `draft` | intet går til Publer uden godkendelse (`SGAP_Queue::add` accepterer `draft`) |
| `channel` | fra `--channels` | begrænset til `SGAP_Publer::CHANNELS` |
| `content_type` / `format` | `custom` / `post` | samme standarder som `queue_post`-ability'en |
| `angle` | `niche` | så opslag fra broen kan skelnes fra de øvrige |
| `title` | `Steampunk goggles` | nichenavnet, stort begyndelsesbogstav — ikke en overskrift vi fandt på |
| `body` | `""` | tom med vilje; den skal fyldes af `write-article` eller et menneske |
| `image_url` | `""` | broen laver ingen billeder |
| `destination_url` | fra linkfilen | **aldrig** opdigtet |
| `scheduled_at` | `null` | køen sætter tiden selv |

Derudover en `attribution` pr. række med `niche`, `score` og `verdict`, og en `fingerprint` der er `sha256(channel|url|title)` — samme duplikatnøgle som `SGAP_Queue::add()` bruger, så to kørsler ikke kan give samme opslag to gange. Er der to ens rækker i samme batch, bliver den anden sprunget over med en grund.

## Hvad broen ikke gør

- **Opdigter ikke en URL.** Mangler nichen i linkfilen, kommer den i "sprunget over" med grunden `ingen URL i linkfilen`. Det er den vigtigste egenskab: en grøn score er ikke en side.
- **Skriver ikke tekst eller titel.** `body` er tom.
- **Sender intet.** Ingen HTTP-kald overhovedet; payloads skrives til en fil.
- **Gemmer ikke attribution i databasen.** `sgap_queue` har ingen `niche`/`score`-kolonner, så feltet findes kun i broens egen JSON. Skal det overleve i WP, kræver det to kolonner — se nedenfor.

## Sådan køres den

```
# linkfil: CSV med niche,url (eller JSON: {"links": {"steampunk goggles": "https://…"}})
python3 niche_bridge.py --input docs/library/niche-kandidater-2026-11-02.csv \
    --links links.csv --channels pinterest,instagram --out drafts.json --plan plan.md

python3 -m unittest discover -s tests -v      # 62 tests, 32 af dem for broen
```

Kanaler der kræver foto (`instagram`, `pinterest`) kommer med i listen og bliver markeret i planen — Publer afviser dem først ved afsendelse. Vil man holde dem ude med det samme, brug `--require-image`.

Uden en linkfil der matcher kandidaterne, er output tomt og planen siger `Ingen draft-rækker: ingen grøn niche havde en URL.` Det er den korrekte opførsel, ikke en fejl — på de fire nuværende kandidater findes ingen af siderne endnu.

## Attribution: hvad der mangler i databasen

For at kunne se *hvilke scorede data der gav klik* skal `sgap_queue` have nichen og scoren. Den ændring ligger i pluginnet og kan ikke verifieres herfra (ingen WP-adgang, og et plugin-zip skal genbygges):

```sql
ALTER TABLE wp_sgap_queue
  ADD niche VARCHAR(60) NOT NULL DEFAULT '',
  ADD niche_score DECIMAL(5,1) NULL;
```

Indtil den er på plads, er broens JSON den eneste kilde til koblingen mellem et opslag og den score der udløste det. Det er nok til at komme i gang, men ikke nok til at lave statistik i WordPress.

## Blokeret af

`WP_USER` + `WP_APP_PASS` (eller `ROYAL_MCP_TOKEN`) er ikke sat, så broens payloads er ikke POSTet mod den kørende kø, og `publer_api_key`/`publer_workspace_id` er ikke bekræftet. Testene kører uden WP-adgang med vilje: de dækker payload-formen, duplikatnøglen og at intet opdigtes — ikke at WordPress tager imod.
