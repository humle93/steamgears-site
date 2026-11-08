# Release-preflight — 7.64.9

**Målt:** 8/11 2026, i samme pas som zip'en blev pakket. **Formål:** fjerne den eneste fejlårsag der kan ramme
ejerens *Plugins → Update*-klik uden at nogen kan se det på forhånd — at WordPress' selvopdatering ikke finder
en pakke, eller finder en anden end den der ligger i repoet (fælden fra 7.58.2).

## De to kald WordPress selv laver

| Kald | Hvad det skal svare | Målt |
| --- | --- | --- |
| `SGAP_Updater::remote_version()` → `raw.githubusercontent.com/humle93/steamgears-site/main/plugins/steamgears-autopilot/steamgears-autopilot.php` | `Version: 7.64.9` | `200`, `Version: 7.64.9` |
| `SGAP_Updater::package_url('7.64.9')` → `…/main/plugins/steamgears-autopilot-v7.64.9.zip` | `200` og byte-identisk med repoets zip | `200`, se nedenfor |

## Pakken

| Egenskab | Værdi |
| --- | --- |
| Fil | `plugins/steamgears-autopilot-v7.64.9.zip` |
| Størrelse | **385 602 bytes** |
| SHA-256 | **`4fb6483b26e2314bfa982df13687ae9d29248437778a1bb64e5479261d196b85`** |
| Poster | 49 (én rodmappe: `steamgears-autopilot/`) |
| Plugin-header i zip'en | `Version: 7.64.9` |
| `tests/` i zip'en | nej — tests følger repoet, ikke installationen |
| Stier ud af mappen (`..`, absolutte) | ingen |

## Den publicerede kopi er efterprøvet

Efter push (`060161e`) blev den fil WordPress faktisk henter hentet igen og sammenlignet: **385 602 bytes, SHA-256
`4fb6483b…d196b85` — byte-identisk med repoet.** Det tog **~2 minutter** før `raw.githubusercontent.com` serverede
den nye fil; indtil da svarede samme URL den *forrige* build af 7.64.9 (385 291 bytes). Koden er identisk i de to
builds — kun `ROADMAP.md` inde i zip'en er anderledes — men det er værd at vide: et *Update*-klik i de første
minutter efter et push kan installere den forrige build af samme version.

## Hvorfor det er den rigtige kontrol

7/11 kostede et *Update*-klik en hel runde, fordi der intet var at installere: versionscache og pakke-link pegede
forskellige steder hen. Kontrollen her er de samme to HTTP-kald, som `SGAP_Updater` laver, målt udefra — så et
klik i wp-admin kan ikke fejle på et link, en 404 eller en forkert version.

**Det kontrollen ikke kan:** at WordPress faktisk skriver filerne (kræver admin), og at 7.64.9 opfører sig som
7.64.9 på sitet (kræver et token til `site-health`). Begge er ejer-blokerede og står i
[`docs/library/live-7-64-9-2026-11-08.md`](../docs/library/live-7-64-9-2026-11-08.md).
