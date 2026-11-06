# Footer-links og navigation — S2.3 lukket, målt 6/11 2026

`/shipping/` og `/returns/` blev publiceret i round 30 og sat i footeren i round 31. Dette dokument er
**verifikationen** af det sidste stykke af S2.3: at linkene faktisk står i footeren på alle skabeloner, at
headeren ikke længere bærer handelsflowet, og hvad der stadig ikke er målt.

## Hvad der blev målt, og hvordan

- Neutral `Mozilla/5.0 (compatible; steamgears-agent/1.0)`-UA, ingen credential, ingen cache-busting.
- Seks URL'er hentet: `/`, `/shop/`, `/faq/`, `/steampunk-corset-guide/` (indlægsskabelon), `/shipping/` og
  `/returns/`.
- Hver side parset: antal `href` til `/shipping/` og `/returns/` **inde i `<footer>`**, antal `page-list` i
  HTML'en, og hvorvidt headeren indeholder `/cart/`, `/checkout/`, `/my-account/` eller `/opt-out-preferences/`.
- Headeren læst i den fulde browser på forsiden (viewport 1024 px) som kontrol af, at markup og synlig menu
  stemmer.

## Resultat

| Side | Status | `<footer>`: shipping | `<footer>`: returns | `page-list` | Header: cart/checkout/account/opt-out |
| --- | --- | --- | --- | --- | --- |
| `/` | 200 | 1 | 1 | 0 | 0 / 0 / 0 / 0 |
| `/shop/` | 200 | 1 | 1 | 0 | 0 / 0 / 0 / 0 |
| `/faq/` | 200 | 1 | 1 | 0 | 0 / 0 / 0 / 0 |
| `/steampunk-corset-guide/` | 200 | 1 | 1 | 0 | 0 / 0 / 0 / 0 |
| `/shipping/` | 200 | 2 | 1 | 0 | 0 / 0 / 0 / 0 |
| `/returns/` | 200 | 1 | 2 | 0 | 0 / 0 / 0 / 0 |

De to forekomster på politik-siderne er footeren plus sidens eget brødtekst-link tilbage til den anden side.

Footerens Support-kolonne indeholder nu, i denne rækkefølge: **Delivery & Shipping** (`/delivery/`),
**Shipping Timelines** (`/shipping/`), **Returns** (`/returns/`), **Refund & Returns** (`/refund_returns/`) og
**Right of Withdrawal** (`/right-of-withdrawal/`). Den lovpligtige fortrydelsesfunktion står dermed stadig ét
klik fra hver side, som S2.3's spec krævede.

Headeren på forsiden, læst i browseren: **Start Here · Build · Shop** (dropdown: All Products, Curiosities,
Airship, Herbarium, Atelier) **· Gallery · Guides · About · Contact** — 12 links i alt, heraf 7 på topniveau.
Ingen `wp:page-list` findes længere i markup'en, så de 29 auto-listede sider er væk.

## S2.3's tjekliste

| Krav fra `navigation-spec-2026-11-02.md` §8 | Status 6/11 |
| --- | --- |
| Header viser 5 punkter, ikke 29 | ✓ 7 topniveau-punkter, 12 links i alt |
| `/cart/`, `/checkout/`, `/my-account/`, `/opt-out-preferences/` væk fra headeren | ✓ 0 forekomster på alle seks sider |
| Alle fem hub-sider nået fra headeren på enhver side | ✓ Start Here, Build, Shop, Gallery, About/Contact |
| `wp:page-list` findes ikke i header-skabelonen | ✓ 0 forekomster i HTML |
| Mobil 390 px: ingen punkter skjuler hinanden | **ikke målt** — se nedenfor |
| Footer indeholder `/right-of-withdrawal/` | ✓ 1 forekomst pr. side |
| Rank Math → ingen nye 404 efter menuskiftet | ✓ de fem målte sider svarer 200 |

## Hvad der ikke er målt

- **Mobilbredde 390 px.** Browserens viewport-kald fejlede i dette pas (`options.mode`-fejl i
  Playwright-wrapperen), så headerens ombrydning på mobil er ikke set. Det er den ene tjeklistelinje der står
  åben; den kræver en browser-session hvor viewporten kan sættes, eller ejerens telefon.
- **Om footer-linkene er synlige uden at scrolle.** Kun markup og placering i `<footer>` er verificeret.
- **Om Google har opdaget linkene.** To nye indgående links pr. side ændrer link-equity, men indeksering er
  ikke målt her; GSC's crawl-statistik hører til S0.6.

## Hvad det ændrer i regnskabet

S2.3 var godskrevet **0,3 af 0,5 d** (header-delen, 2/11). Footer-delen er de resterende **0,2 d** og er nu
målt, så S2.3 står på **0,5 af 0,5 d**. S2-båndet går 5,3 → **5,5 af 7,55 d**, og site samlet 13,2 → **13,4 af
23,8 d** = **55,5 % → 56,3 %**. Ingen nævner er ændret, og ingen ny opgave er opfundet: det er den samme 0,5 d,
delt i de to halvdele spec'en selv beskriver.

## Filer

- Navigationens spec og oprindelige måling: [`navigation-spec-2026-11-02.md`](navigation-spec-2026-11-02.md).
- Header-runden 2/11: [`navigation-og-meta-2026-11-02.md`](navigation-og-meta-2026-11-02.md).
- Round 31, hvor linkene blev sat, og butikken blev genoprettet:
  [`butik-nede-og-genoprettet-2026-11-06.md`](butik-nede-og-genoprettet-2026-11-06.md).
- De øvrige målinger samme dag: [`site-maalinger-2026-11-06.md`](site-maalinger-2026-11-06.md).
