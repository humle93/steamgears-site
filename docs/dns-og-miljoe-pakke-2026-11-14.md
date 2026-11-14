# DNS- og miljøpakke — S5.1, S5.7, S5.8, S5.9 (målt 14/11 2026)

Alt der kan skrives uden adgang til ejerens paneler, skrevet som **indsæt og klik**: præcis navn, type, værdi
og TTL pr. post, taget fra den zone der svarer lige nu, plus de fem GitHub-indstillinger og de tal, der skal
noteres for backup-øvelsen. Zonen er målt mod alle tre autoritative navneservere
(`ns1`–`ns3.simply.com`), ikke mod en resolver-cache.

**Målt zone 14/11 2026** (identisk på `ns1`, `ns2` og `ns3`):

| Navn | Type | Værdi | TTL |
| --- | --- | --- | --- |
| `steamgears.eu` | A | `94.231.103.182` | 3600 |
| `www.steamgears.eu` | A | `94.231.103.182` | 3600 |
| `*.steamgears.eu` | A | `94.231.103.182` | 3600 |
| `steamgears.eu` | TXT | `v=spf1 include:spf.simply.com -all` | 3600 |
| `steamgears.eu` | MX | `10 mx.simply.com.` | 3600 |
| `_dmarc.steamgears.eu` | **CNAME** | `dmarc.simply.com.` | 3600 |
| `dmarc.simply.com` (pejl) | TXT | `v=DMARC1; p=reject; rua=mailto:dmarc@robot.simply.com; ruf=mailto:dmarc@robot.simply.com;` | — |
| `_domainkey.steamgears.eu` | — | **findes ikke** | — |
| `staging.steamgears.eu` | A (via `*`) | `94.231.103.182` | 3600 |

**To fælder, der er målt i dag — og som gør at et opslag ikke beviser noget:**

1. **Zonen har wildcard.** `findes-ikke-8f3a.steamgears.eu` svarer også `94.231.103.182`. Derfor kan et
   `dig A staging.steamgears.eu` **ikke** bruges som bevis for, at subdomænet er oprettet — svaret kommer fra
   `*`, uanset om navnet findes i panelet.
2. **`_dmarc` er en CNAME, og en CNAME blokerer for en TXT på samme navn.** Så længe den står, kan en
   DMARC-TXT ikke slå igennem, uanset hvad panelet melder. Den skal **slettes først**.

## S5.7 — de to DNS-poster (0,15 d)

| # | Handling | Navn | Type | Værdi | TTL |
| --- | --- | --- | --- | --- | --- |
| 1 | **Slet** | `_dmarc.steamgears.eu` | CNAME | `dmarc.simply.com.` | — |
| 2 | **Opret** | `_dmarc.steamgears.eu` | TXT | `v=DMARC1; p=reject; fo=1; rua=mailto:«rapport-adresse»; ruf=mailto:«rapport-adresse»` | 3600 |
| 3 | **Opret** | `<selector>._domainkey.steamgears.eu` | TXT | `v=DKIM1; k=rsa; p=…` — **kopieres ordret** fra Simplys mailindstillinger | 3600 |

- `«rapport-adresse»` er den **eneste** pladsholder her: det skal være en postkasse, du faktisk læser. Brug
  samme domæne som butikken (fx `dmarc@steamgears.eu`), ikke en privat adresse — rapportmail kan indeholde
  modtageradresser.
- `p=reject` og `fo=1` beholdes: `p=reject` er allerede Simplys politik, og `fo=1` giver en rapport også når
  DKIM fejler men SPF består — det er netop den fejl, der ellers er usynlig.
- **DKIM:** navnet på selectoren står kun i Simplys panel (den er ikke i zonen i dag). Slå DKIM til under
  mailindstillingerne, og kopiér den TXT, panelet giver, ordret. Der findes **ingen** DKIM-post nu: 32
  selectorer er prøvet (otte også som CNAME-pejl), og `_domainkey` er tom.
- **Rækkefølgen er ikke valgfri:** slet CNAME'en, gem, og opret derefter TXT'en. Opretter du TXT'en først,
  afviser panelet den eller lægger den et sted, zonen ikke svarer fra.

**Sådan bekræftes det (kør efter ændringen, mod den autoritative server):**

```bash
dig +short CNAME _dmarc.steamgears.eu @ns1.simply.com     # skal være TOM
dig +short TXT   _dmarc.steamgears.eu @ns1.simply.com     # skal vise din rua/ruf
dig +short TXT   <selector>._domainkey.steamgears.eu @ns1.simply.com   # skal vise v=DKIM1; k=rsa; p=
python3 tools/s5_measure.py --strict                      # 20/20 hvis alt holder
```

Den sidste del af beviset er en rigtig mail: send en ordrebekræftelse til dig selv og se
`Authentication-Results` hos modtageren — der skal stå `dkim=pass` **og** `dmarc=pass`.

## S5.1 — staging (0,75 d)

Subdomænet **ser** ud til at findes, fordi zonen har wildcard. Det gør det ikke: certifikatet dækker kun
`steamgears.eu` og `www.steamgears.eu`, så `curl https://staging.steamgears.eu` fejler med
**`SSL: no alternative certificate subject name matches target host name`** (målt 14/11). Der er altså
hverken certifikat eller site bag navnet.

| # | Handling i Simply | Detalje |
| --- | --- | --- |
| 1 | **Hjemmeside → Subdomæne** | opret `staging`, peg det på en **egen** mappe, ikke på live-roden |
| 2 | **SSL** | slå Let's Encrypt til for `staging.steamgears.eu` (ikke kun apex) |
| 3 | **Database** | opret en **kopi** af `steamgears_eu_db` til staging — ikke en delt database |
| 4 | **Adgang** | HTTP-auth (brugernavn + adgangskode) på hele staging |
| 5 | **Synlighed** | `noindex` via header **og** `robots.txt`; staging må ikke kunne findes |
| 6 | **Miljø** | `WP_ENVIRONMENT_TYPE=staging` i `wp-config.php` |
| 7 | **Betaling** | Stripes **testnøgler** i staging, aldrig live-nøgler |

**Beviset bagefter (og det eneste, der tæller):**

```bash
curl -sI https://staging.steamgears.eu | head -3                  # 401 (HTTP-auth)
echo | openssl s_client -connect staging.steamgears.eu:443 \
  -servername staging.steamgears.eu 2>/dev/null | openssl x509 -noout -ext subjectAltName
                                                                   # SAN skal indeholde staging.steamgears.eu
python3 tools/s5_measure.py --strict                               # «? sandkasse findes» bliver ✓ (20/20)
```

## S5.8 — de fire tal, der mangler (0,05 d)

Øvelsen er kørt og målt 9/11 (dump 3 338 987 bytes, 269/269 tabeller, RTO 6 s). Det, der mangler, er
**værtens** halvdel. Skriv de fire svar ind i tabellen her, så er posten lukket:

| Spørgsmål | Hvor svaret står | Svar |
| --- | --- | --- |
| Har Simply daglig backup, og hvor længe opbevares den? (krav: **≥ 14 dage**) | Hjemmeside → Backup / Kontrolpanel | «…» |
| Hvor stor er det seneste arkiv? | samme sted | «…» MB/GB |
| Hvor lang tid tager en gendannelse til staging? | måles ved at gøre det | «…» minutter |
| Hvad manglede bagefter? | samme måling | «…» |

Gendannelsen skal ske **til staging** (derfor S5.1 først), ikke til live.

## S5.9 — de fem GitHub-indstillinger (0,25 d)

`python3 tools/release.py --protection` lister dem, fordi API'et svarer **403** på Free-planen med et privat
repo. Efter Pro/Team (eller offentligt repo):

| # | Indstilling | Hvor | Værdi |
| --- | --- | --- | --- |
| 1 | Require a pull request before merging | Settings → Branches → `main` | slået til |
| 2 | Require status checks: `commit-vaern`, `plugin-ci` | samme | begge valgt |
| 3 | Do not allow bypassing the above settings | samme | slået til |
| 4 | Beskyttelse af `v*`: ingen sletning, intet tvunget flyt | Settings → Tags | regel oprettet |
| 5 | Environment `production` med required reviewer | Settings → Environments | oprettet |

```bash
gh api -X PUT repos/humle93/steamgears-site/branches/main/protection \
  -F required_pull_request_reviews.required_approving_review_count=1 \
  -F enforce_admins=true -F required_status_checks.strict=true
gh api repos/humle93/steamgears-site/branches/main/protection   # skal svare 200 med de fem nøgler
```

## Status: hvad er forberedt, og hvad kræver dig

| Post | Forberedt her | Tilbage til ejeren |
| --- | --- | --- |
| S5.7 | post-for-post tabel med navn/type/værdi/TTL, rækkefølge, beviskommandoer | slet CNAME, opret TXT, slå DKIM til, kopiér TXT |
| S5.1 | syv trin + de tre beviser | klikke dem i Simply, vente på certifikatet |
| S5.8 | de fire spørgsmål og hvor svaret findes | læse dem i panelet og skrive dem ind |
| S5.9 | de fem indstillinger og de to `gh`-kald | købe planen og køre dem |

Ingen af de fire er talt som fremgang: zonen svarer det samme, indtil post 1-3 er lagt ind.
