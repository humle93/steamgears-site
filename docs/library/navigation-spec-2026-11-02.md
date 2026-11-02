# Navigation og menu — S2.3, verificeret 2/11 2026

_Navigationen er stadig `<!-- wp:page-list /-->`, som auto-lister **alle 29 sider** — inkl. kurv, kasse,
min konto og cookie-indstillinger. Det er både en UX-fejl og et link-equity-problem: de fem hub-sider fra
runde 3–4 får ingen site-wide links, mens `/cart/` og `/checkout/` gør. Alle URL'er nedenfor er slået op i
REST API'et 2/11 2026; ingen er antaget._

## 1. Hvad der faktisk findes (målt, ikke antaget)

`/wp-json/wp/v2/pages` returnerer **29 sider**. De fem hub-børn er bekræftet som rigtige sider under deres
hub, og de tre vanity-URL'er fra roadmap'en findes fortsat ikke:

| Side | ID | URL | Findes |
| --- | --- | --- | --- |
| Start Here | 1183 | `/start-here/` | ✅ |
| ↳ Steampunk Glossary | 1205 | `/start-here/glossary/` | ✅ parent 1183 |
| ↳ Steampunk Characters | 1208 | `/start-here/characters/` | ✅ parent 1183 |
| ↳ Where to Wear It | 1211 | `/start-here/occasions/` | ✅ parent 1183 |
| Build | 1185 | `/build/` | ✅ |
| ↳ Tools and Materials | 1214 | `/build/tools-and-materials/` | ✅ parent 1185 |
| ↳ Weathering and Ageing | 1217 | `/build/weathering/` | ✅ parent 1185 |
| Shop | 22 | `/shop/` | ✅ |
| Gallery | 32 | `/steampunk-cosplay-gallery/` | ✅ |
| Costumes-hub | 550 | `/steampunk-costumes/` | ✅ |
| Inspiration | — | `/inspiration/` | ❌ findes ikke |
| Shipping | — | `/shipping/` | ❌ findes ikke (`/delivery/` 582 dækker) |
| Returns | — | `/returns/` | ❌ findes ikke (`/refund_returns/` 26 dækker) |

Bemærk: `/steampunk-costume-ideas/` er et **indlæg**, ikke en side — den skal ikke i page-list, men kan
gerne være menupunkt.

## 2. Fejlen i den nuværende menu

`wp:page-list` viser alle sider i id-rækkefølge. Det betyder at header-menuen lige nu indeholder:

- `/cart/` (23), `/checkout/` (24), `/my-account/` (25) — handelsflow, ikke navigation
- `/opt-out-preferences/` (872) — cookie-side, sat til `noindex` 1/11, hører i footeren
- `/privacy-policy/`, `/cookie-policy/`, `/terms/` — juridiske sider, hører i footeren
- 29 punkter i alt, uden hierarki: hub-børnene står på samme niveau som deres hub

Konsekvensen er ikke kosmetisk: hver side på sitet sender 29 links i headeren, og `/start-here/` og `/build/`
konkurrerer med `/cart/` om den samme site-wide linkvægt.

## 3. Foreslået header (5 punkter)

Fem punkter er grænsen for, hvad der kan stå i en mobil header uden at blive en hamburger-bunke. Hvert
punkt har en verificeret destination.

| # | Label | URL | Børn i dropdown |
| --- | --- | --- | --- |
| 1 | **Start her** | `/start-here/` | Glossary · Characters · Occasions |
| 2 | **Byg** | `/build/` | Tools and Materials · Weathering |
| 3 | **Butik** | `/shop/` | se §4 |
| 4 | **Inspiration** | `/steampunk-cosplay-gallery/` | — |
| 5 | **Info** | `/about/` | FAQ · Levering · Størrelser · Retur · Fortrydelse · Kontakt |

`/inspiration/` er bevidst ikke oprettet: galleriet dækker samme søgeintent, og en tredje index-side ville
konkurrere med den. Menu-labelen »Inspiration« peger derfor på galleriet, og en eventuel 301 fra
`/inspiration/` kan komme senere via S0.4.

## 4. Butik-dropdown (6 af 21 kategorier)

Butikken har 21 produktkategorier. Alle kan ikke i en menu; disse seks er valgt på antal produkter og på
hvad der beskriver sortimentet efter rebranden:

| Label | URL | Produkter |
| --- | --- | --- |
| Alle produkter | `/shop/` | 39 |
| Dark Academia | `/product-category/dark-academia/` | 24 |
| Hoodies | `/product-category/hoodies/` | 12 |
| T-Shirts | `/product-category/t-shirts/` | 9 |
| Prints og plakater | `/product-category/posters-prints/` | 5 |
| Gaver | `/product-category/steampunk-gifts/` | 13 |

De øvrige 15 kategorier bliver i `/shop/`-filtreringen. Særligt skal `digital-downloads` **ikke** i menuen:
den er sat til `noindex`, og et menupunkt ville pege ind i en side, Google ikke må indeksere.

## 5. Footer (erstatter det page-list skjuler)

To kolonner, så de juridiske krav fra butiksauditten også bliver opfyldt strukturelt:

| Butik | Information |
| --- | --- |
| `/shop/` | `/about/` (Om) |
| `/delivery/` (Levering) | `/faq/` |
| `/refund_returns/` (Retur) | `/journal/` (Workshop Journal) |
| `/right-of-withdrawal/` (Fortrydelse) | `/contact/` (Kontakt) |
| `/terms/` (Handelsbetingelser) | `/privacy-policy/` |
| `/size-guide/` (Størrelser) | `/cookie-policy/`, `/opt-out-preferences/` |

`/right-of-withdrawal/` **skal** stå i footeren på hver side — den er den digitale fortrydelsesfunktion fra
19/6 2026, og den skal være lettilgængelig, ikke gemt i en dropdown.

## 6. Sådan ændres det (blok-tema, Extendable 2.1.9)

Menuen er en `wp:page-list`-blok, altså Site Editor, ikke klassiske menuer:

1. **Udseende → Editor → Skabeloner → Header** (eller Navigation, hvis temaet viser den direkte).
2. Vælg `page-list`-blokken → **Erstat** med en **Navigation**-blok (ikke »tilføj«, ellers står begge der).
3. Byg de fem punkter fra §3. Underpunkter laves ved at vælge et punkt → *Add submenu*.
4. Sæt »Butik«-dropdown efter §4.
5. Gem, og rediger derefter **Footer**-skabelonen efter §5.
6. **Mobil:** kontrollér at de fem punkter kan læses uden at bryde i to linjer ved 390 px.

Klassisk tema i stedet? Så er det **Udseende → Menuer**: opret menuen »Hovedmenu«, tilknyt placeringen
»Primary«, og fjern den auto-genererede side-liste.

## 7. Hvad der ikke skal gøres

- **Ikke** opret `/inspiration/`, `/shipping/` eller `/returns/` som nye sider — de to sidste ville duplikere
  `/delivery/` og `/refund_returns/`. S2.2 løses som 301, ikke som nye sider.
- **Ikke** tilføj `/cart/`, `/checkout/`, `/my-account/` til menuen. Kurv-ikonet og checkout-flowet dækker dem.
- **Ikke** flyt `/start-here/` eller `/build/` til nye slugs. Der er interne links fra 10 guides ind i dem
  (runde 3), og slug-skift ville brække dem.
- **Ikke** sæt menu-labelen til »Start Here« på dansk site; menupunkter er UI, og resten af sitet er engelsk
  i indholdet — hold labels engelske for konsistens med sidetitlerne, eller skift dem alle på én gang.

## 8. Verifikation bagefter

- [ ] Header viser 5 punkter, ikke 29
- [ ] `/cart/`, `/checkout/`, `/my-account/`, `/opt-out-preferences/` er væk fra headeren
- [ ] Alle fem hub-sider er nået fra headeren på **enhver** side
- [ ] `wp:page-list` findes ikke længere i header-skabelonen (søg i koden)
- [ ] Mobil 390 px: ingen punkter skjuler hinanden
- [ ] Footer indeholder `/right-of-withdrawal/`
- [ ] Rank Math → Redirects: ingen nye 404 (menuskift fjerner ikke sider)

## 9. Begrænsning i denne runde

Selve sitets HTML kan **ikke** læses herfra: Simplys WAF svarer `454 Checking your browser` på alle
sidevisninger fra dette workspace. REST API'et svarer derimod `200`, og hele §1-tabellen er derfor målt.
Men om menuen faktisk er ændret, og hvordan den ser ud, kan kun afgøres i browseren eller via MCP — derfor
er ingen procenter flyttet for S2.3 i denne runde.
