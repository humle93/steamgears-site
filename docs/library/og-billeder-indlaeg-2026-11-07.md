# S2.5b — OG-billeder på alle 30 publicerede indlæg — 7/11 2026

**Målt:** 7/11 2026 med [`tools/og_image_check.py`](../../tools/og_image_check.py) (`--live`, read-only mod
`https://steamgears.eu/wp-json/wp/v2/posts?per_page=100` og de 30 indlægssider).
**Formål:** lukke måledelen af **S2.5b** (0,15 af 0,25 d), som blev skilt ud fra S2.5, fordi meta-runden 2/11
kun dækkede titel, metabeskrivelse og fokusnøgleord — ikke hvorvidt hvert indlæg har et `og:image`, der svarer
200 og har rigtig dimension.

---

## 1. Hovedresultat

| Kontrol | Målt | Dom |
| --- | --- | --- |
| Publicerede indlæg i alt | **30** | 24 fra den oprindelige audit + 6 publiceret siden |
| Har `featured_media` + `<meta property="og:image">` | **30 af 30** | 0 indlæg mangler socialt billede |
| Har `og:image:width`, `og:image:height`, `og:image:alt` og `twitter:card` | **30 af 30** | alle har `summary_large_image` og ikke-tom alt-tekst (14–140 tegn) |
| `og:image`-URL svarer **HTTP 200** | **30 af 30** (27 unikke URL'er) | 0 døde billedlinks |
| Deklareret `og:image:width × height` matcher filens binære header | **30 af 30** | 0 dimensions-afvigelser mellem HTML og JPEG/PNG-header |
| Over OpenGraph-minimum (`200 × 200 px`) | **30 af 30** | mindste dimension er `576 px` |
| Over grænsen for stort link-kort (`bredde >= 600 px`) | **29 af 30** | **indlæg 615** er `576 × 1024 px` (se §2.1) |
| Format og vægt (`JPEG/WebP <= 600 KB`) | **28 af 30** | **indlæg 751** er `1.378 KB PNG`; **indlæg 806** er `845 KB JPEG` |
| Unikt billede pr. indlæg | **24 af 30** | **3 billeder deles** af i alt 6 indlæg (se §2.3) |

Samtidig blev det forbehold, der stod åbent på **S0.4**, lukket med cache-buster (`?nocache=20261107`) nu hvor
PHP kører igen: `/start-here/` (73.402 B), `/build/` (71.346 B), `/inspiration/` (75.209 B), `/shipping/`
(69.496 B) og `/returns/` (70.254 B) svarer alle **HTTP 200** uden om sidecachen.

---

## 2. De fire konkrete fund (de sidste 0,10 d af S2.5b)

Intet indlæg har et dødt eller manglende `og:image`, men fire indholdsmæssige fund afgør, hvordan kortene
vises på Facebook, X, LinkedIn, Discord og Pinterest:

### 2.1 Indlæg 615 nedgraderes under 600 px på grund af WordPress' `large`-skalering

- **Indlæg 615** (`casual-steampunk-outfits`, `featured_media = 1096`) udstiller
  `.../2026/11/casual-look-1-576x1024.jpg` (**576 × 1024 px**, 98 KB JPEG).
- Originalfilen i mediebiblioteket (`casual-look-1.jpg`) er **1280 × 2276 px**, og størrelserne `1536x1536` og
  `2048x2048` findes på serveren.
- Fordi billedet er stående (`9:16`), skalerer WordPress' `large`-størrelse (`max 1024 × 1024`) højden til
  `1024` og bredden proportionalt ned til **576 px** — lige under **600 px-grænsen**, hvor Facebook og LinkedIn
  nedgraderer et stort link-kort til et lille kvadratisk thumbnail.
- Samme mekanisme rammer **indlæg 805** (`3d-print-steampunk-parts`, `featured_media = 1124`), hvor originalen
  på `1920 × 2550 px` bliver til `print-1-771x1024.jpg` (`771 × 1024 px`, 96 KB) — her holder bredden sig dog
  over 600 px.
- **Rettelse ved skriveadgang:** sæt et liggende social-billede i Rank Math (`_rank_math_facebook_image`) på
  de fem stående indlæg (`572`, `615`, `751`, `804`, `805`), eller lad filteret
  `rank_math/opengraph/image_size` pege på `'1536x1536'` når `'large'` giver en bredde under 600 px.

### 2.2 To tunge filer: én 1,38 MB PNG og én 845 KB JPEG

- **Indlæg 751** (`pirate-cosplay-for-beginners`, `featured_media = 1098`) bruger `pin-pirate-01.png`
  (**848 × 1264 px**, **1.378 KB PNG**) — det eneste PNG-billede blandt de 30 indlæg og ni gange tungere end
  medianen (148 KB).
- **Indlæg 806** (`sustainable-steampunk`, `featured_media = 1126`) bruger `upcycle-1.jpg`
  (**1920 × 1440 px**, **845 KB JPEG**).

### 2.3 Tre fremhævede billeder deles af seks indlæg

27 unikke billedfiler dækker de 30 indlæg. Tre billeder går igen på to indlæg hver:

| Medie-ID | Fil | Dimension | Bruges på indlæg | Bemærkning |
| ---: | --- | --- | --- | --- |
| `1082` | `ideas-airship.jpg` | `1408 × 768` | **15** (`steampunk-costume-ideas`), **699** (`steampunk-outfit-ideas-for-women`) | To hovedguides deler samme luftskibsbillede |
| `488` | `ae-05.jpg` | `960 × 960` | **791** (`steampunk-halloween-costume-women`), **913** (`steampunk-costume-for-women`) | Kvadratisk produktbillede genbrugt som artikel-cover |
| `474` | `ae-01.jpg` | `960 × 960` | **798** (`steampunk-vs-dieselpunk`), **875** (`dieselpunk-costume`) | Kvadratisk produktbillede; `/wp/v2/media/474` svarer **401** fordi `post_parent` er et ikke-publiceret produkt |

### 2.4 Fordeling på billedformater (liggende mod stående/kvadratisk)

- **21 liggende (`16:9` / `1.83:1` / `3:2` / `4:3`):** 11 i `1408 × 768`, 1 i `1376 × 768`, 1 i `1600 × 1024`,
  1 i `1920 × 1277`, 1 i `1920 × 1440` og 6 i `1024 × 576`. Disse passer direkte til `summary_large_image` og
  OpenGraph `1.91:1`.
- **4 kvadratiske (`960 × 960`):** indlæg `791`, `798`, `875`, `913` — beskæres top/bund på vandrette kort.
- **5 stående (`572`, `615`, `751`, `804`, `805`):** ideelle til Pinterest, men beskæres kraftigt midtpå på X,
  Facebook og Discord.

---

## 3. Samtlige 30 publicerede indlæg

| ID | Slug | Medie | Dimension | Format | Vægt | `alt` | Fil |
| ---: | --- | ---: | --- | --- | ---: | ---: | --- |
| 8 | `how-to-make-a-steampunk-costume` | 1058 | `1408 × 768` | JPEG | 100 KB | 100 tegn | `costume-hero.jpg` |
| 10 | `diy-steampunk-goggles` | 1068 | `1408 × 768` | JPEG | 198 KB | 95 tegn | `goggles-materials.jpg` |
| 14 | `steampunk-cosplay-for-beginners` | 1079 | `1408 × 768` | JPEG | 137 KB | 79 tegn | `beginner-hero.jpg` |
| 15 | `steampunk-costume-ideas` | 1082 | `1408 × 768` | JPEG | 158 KB | 65 tegn | `ideas-airship.jpg` (delt med 699) |
| 17 | `steampunk-cosplay-on-a-budget` | 1085 | `1408 × 768` | JPEG | 145 KB | 76 tegn | `budget-thrift.jpg` |
| 30 | `steampunk-accessories` | 1088 | `1408 × 768` | JPEG | 148 KB | 73 tegn | `acc-gears.jpg` |
| 572 | `curiosities-vol-3-live` | 1117 | `1000 × 1792` | JPEG | 316 KB | 17 tegn | `12-brass-hummingbird.jpg` |
| 614 | `diy-vs-buy-steampunk-goggles` | 1094 | `1408 × 768` | JPEG | 161 KB | 17 tegn | `diy-build.jpg` |
| 615 | `casual-steampunk-outfits` | 1096 | `576 × 1024` | JPEG | 98 KB | 16 tegn | `casual-look-1-576x1024.jpg` (**< 600 px bred**) |
| 699 | `steampunk-outfit-ideas-for-women` | 1082 | `1408 × 768` | JPEG | 158 KB | 65 tegn | `ideas-airship.jpg` (delt med 15) |
| 751 | `pirate-cosplay-for-beginners` | 1098 | `848 × 1264` | PNG | 1378 KB | 14 tegn | `pin-pirate-01.png` (**PNG > 1 MB**) |
| 757 | `how-to-build-a-steampunk-costume-for-under-100-complete-2027-workshop-plan` | 904 | `1376 × 768` | JPEG | 160 KB | 69 tegn | `u100-hero.jpg` |
| 791 | `steampunk-halloween-costume-women` | 488 | `960 × 960` | JPEG | 47 KB | 66 tegn | `ae-05.jpg` (delt med 913) |
| 798 | `steampunk-vs-dieselpunk` | 474 | `960 × 960` | JPEG | 131 KB | 67 tegn | `ae-01.jpg` (delt med 875) |
| 803 | `closet-steampunk-outfits` | 1105 | `1920 × 1277` | JPEG | 371 KB | 16 tegn | `closet-1.jpg` |
| 804 | `steampunk-gift-guide-2026` | 1107 | `1000 × 1792` | JPEG | 282 KB | 20 tegn | `01-dirigible-dawn.jpg` |
| 805 | `3d-print-steampunk-parts` | 1124 | `771 × 1024` | JPEG | 96 KB | 24 tegn | `print-1-771x1024.jpg` |
| 806 | `sustainable-steampunk` | 1126 | `1920 × 1440` | JPEG | 845 KB | 21 tegn | `upcycle-1.jpg` (**> 600 KB**) |
| 875 | `dieselpunk-costume` | 474 | `960 × 960` | JPEG | 131 KB | 67 tegn | `ae-01.jpg` (delt med 798) |
| 891 | `steampunk-halloween-costume-men` | 1131 | `1408 × 768` | JPEG | 152 KB | 31 tegn | `mens-halloween-hero.jpg` |
| 897 | `steampunk-outfit-ideas-men` | 1054 | `1408 × 768` | JPEG | 107 KB | 80 tegn | `mens-outfits-hero.jpg` |
| 898 | `steampunk-couple-costumes` | 1056 | `1408 × 768` | JPEG | 121 KB | 73 tegn | `couples-hero.jpg` |
| 913 | `steampunk-costume-for-women` | 488 | `960 × 960` | JPEG | 47 KB | 66 tegn | `ae-05.jpg` (delt med 791) |
| 973 | `the-clockwork-herbarium-short-story` | 975 | `1024 × 576` | JPEG | 528 KB | 118 tegn | `file-01a18983-55e7-722f-ab97-92d1efec0dd5.jpg` |
| 974 | `what-is-dieselpunk` | 976 | `1024 × 576` | JPEG | 450 KB | 140 tegn | `file-01a18983-814f-710e-b0fa-62bcefb5f729.jpg` |
| 988 | `best-steampunk-books` | 990 | `1024 × 576` | JPEG | 117 KB | 113 tegn | `file-01a18ea0-bd02-71c8-b6a0-310bd8eb6c54.jpg` |
| 989 | `what-is-gaslamp-fantasy` | 991 | `1024 × 576` | JPEG | 148 KB | 102 tegn | `file-01a18ea0-e537-74ef-9b05-c77d582d1668.jpg` |
| 993 | `the-clockwork-seer-short-story` | 996 | `1024 × 576` | JPEG | 102 KB | 105 tegn | `file-01a18ea5-b672-77ec-a0f3-62831133ea65.jpg` |
| 994 | `the-last-flight-of-the-meridian-short-story` | 997 | `1024 × 576` | JPEG | 154 KB | 101 tegn | `file-01a18ea5-df14-7676-8267-a83ceaf8f359.jpg` |
| 1394 | `steampunk-corset-guide` | 1395 | `1600 × 1024` | JPEG | 350 KB | 132 tegn | `underbust-and-overbust-steampunk-corset-side-by-side.jpg` |

---

## 4. Værn og reproduktion

[`tools/og_image_check.py`](../../tools/og_image_check.py) kører nu som **værn nr. 29** (`og-image-selftest`) i
[`tools/natlige-vaern.sh`](../../tools/natlige-vaern.sh):

```bash
python3 tools/og_image_check.py --selftest   # 10 offline-fixtures (JPEG/PNG/WebP, mismatch, <200 px, <600 px, dublet)
python3 tools/og_image_check.py --live       # read-only kontrol af alle 30 publicerede indlæg
```
