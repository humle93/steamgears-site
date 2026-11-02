# Roadmap-status — 2/11 2026 (målt)

Frisk crawl af hele sitet, kørt 2/11 mod den live side, plus en rettelse af en konklusion jeg selv
drog for hurtigt. Dokumentet supplerer [`roadmap.md`](../roadmap.md) og den løbende status i
[`projektstatus.md`](../projektstatus.md); tallene her er de samme som i `projektstatus.json`, som er
den autoritative kilde.

## 1. Metode — og hvorfor den ikke kan gentages lige nu

Alle URL'er i de fem sitemaps (`post`, `page`, `product`, `product_cat`, `category`) blev hentet og
målt: HTTP-status, `<title>`, meta description, H1, ordantal, interne links og schema-typer.

| Måling (2/11, formiddag) | Resultat |
| --- | --- |
| URL'er i sitemaps | **119** |
| Svarer 200 | **119** (0 fejl) |
| Fordeling | 55 sider/indlæg · 39 produkter · 20 produktkategorier · 5 blogkategorier |
| Gennemsnitligt ordantal | 969 |
| Titler over 62 tegn | **4** |
| Manglende meta description | **1** |
| Description uden for 70–165 tegn | **6** |
| Sider med to H1 | **2** |
| Sider uden link til en hub | 0 |

**Vigtigt forbehold:** kort efter crawlet begyndte edge-WAF'en på Simply at afvise alt fra denne
maskine — `454` til en almindelig browser-UA og `455` til Googlebot og bingbot, på forsiden, på
`/start-here/`, på et indlæg og på `product-sitemap.xml`:

```
Googlebot/2.1   /  → 455    /start-here/ → 455    /product-sitemap.xml → 455
bingbot/2.0     /  → 455    /start-here/ → 455    /product-sitemap.xml → 455
Chrome-UA       /  → 454    /start-here/ → 454    /product-sitemap.xml → 454
```

Det betyder to ting. For det første kan målingen ovenfor ikke gentages, før blokeringen er ophævet.
For det andet — og vigtigere — **den kan ikke bruges som bevis for, at søgemaskinerne kan læse
sitet**. Derfor er S0.7 (sitemap-verifikation) korrekt trukket tilbage i `projektstatus.json` med
`scope_change`, og min første konklusion om at S0.7 var lukket, er hermed forkert: 200-svarene kom
fra en klient WAF'en ikke klassificerede som bot. Om Googlebot reelt får 455 i produktion, eller om
det kun rammer denne IP efter 119 hurtige requests, skal afgøres af ejeren — men indtil da tæller
S0.7 ikke som udført.

## 2. Hvad crawlet viste

**Hub-strukturen er bygget.** Roadmap'ens oprindelige indvending — at `/start-here/`, `/build/` og
`/inspiration/` var 404 — holder ikke længere. Otte sider svarede 200 med reelt indhold:

| Side | Ord | H1 |
| --- | --- | --- |
| `/start-here/` | 932 | Start Here |
| `/start-here/characters/` | 641 | Steampunk Characters: Pick Your Archetype |
| `/start-here/occasions/` | 632 | Where to Wear It: Steampunk by Occasion |
| `/start-here/glossary/` | 984 | Steampunk Glossary: The Terms, Explained |
| `/build/` | 756 | Build |
| `/build/weathering/` | 791 | Weathering and Ageing: Five Techniques |
| `/build/tools-and-materials/` | 729 | Tools and Materials for Steampunk Builds |
| `/inspiration/` | 1112 | Inspiration |

`/inspiration/` svarede 200 i dette crawl, mens noten i `projektstatus.json` stadig regner den som
manglende. Det bør afklares ved næste måling, når WAF'en igen svarer — ikke ved at rette tallet på
formodning.

**Metadata.** Efter meta-runden er der stadig fire titler over 62 tegn og syv descriptions uden for
længdekravet:

| Problem | Sider |
| --- | --- |
| Titel over 62 tegn | `/product-category/posters-prints/` (64) · `/product/steampunk-aviator-goggles-retro-motorcycle-windproof/` (67) · `/product/steampunk-overbust-corset-leather-lace-up-burlesque/` (66) · `/product/victorian-vampire-tailcoat-brocade-gothic-jacket/` (63) |
| Ingen description | `/category/uncategorized/` |
| Description for lang | `/product-category/airship-captain/` (389) · `/product-category/beginner-steampunk/` (408) · `/product-category/dark-clockwork/` (390) · `/product-category/mechanical-explorer/` (393) · `/product-category/victorian-inventor/` (369) · `/product/first-mates-oath-steampunk-pirate-poster/` (170) |

De fem kollektionsbeskrivelser er skrevet som kategoritekst, men Rank Math bruger dem som meta —
derfor er de 2,4× for lange. Løsningen er at flytte teksten ned i sidens indhold og give kategorien
en kort description ved siden af.

**To H1'er på samme side.** `/free-starter-kit/` (`Free Steampunk Starter Kit (PDF)` +
`The Steampunk Starter Kit`) og `/steampunk-halloween/` (`Steampunk Halloween: Costumes, DIY Ideas`
+ `Steampunk Halloween Costumes`). Den ene skal degraderes til H2.

**Navigationen** er `Start Here · Build · Shop` (mega-menu med syv kategorier) `· Gallery`.
*Inspiration* og en *Info*-gruppe mangler stadig, så hverken hubben eller de to handelsbetingelser
kan nås fra headeren.

**Produktlinks.** 44 af 79 ikke-produktsider linker ingen produkter. Kategorisider og juridiske
sider tæller med i de 44; de reelle huller er ni guides: `/3d-print-steampunk-parts/`,
`/best-steampunk-books/`, `/casual-steampunk-outfits/`, `/closet-steampunk-outfits/`,
`/build/tools-and-materials/`, `/build/weathering/`, `/how-to-make-a-steampunk-costume/`,
`/sustainable-steampunk/`, `/steampunk-gift-guide-2026/`.

## 3. Status pr. band

Tallene er `projektstatus.json` pr. 2/11: 10,55 af 21,1 dage = **50 %**.

| Band | Estimat | Udført | Rest | Hvad der mangler |
| --- | --- | --- | --- | --- |
| S0 — brudt/akut | 2,6 d | 1,9 d | 0,7 d | S0.2 robots.txt (stadig to `User-agent: *`-grupper), S0.5 Health Check, S0.6 Coming Soon, S0.7 bot-adgang, to vanity-301 |
| S1 — butik og indhold | 4,5 d | 1,7 d | 2,8 d | Herbarium-varianter, `/go/`-tracking, lead magnets |
| S2 — struktur og SEO | 6,0 d | 3,95 d | 2,05 d | syv descriptions + fire titler, to H1'er, nav-punkter, `/shipping/` + `/returns/`, H1-beslutning |
| S3 — hygiejne | 3,0 d | 1,6 d | 1,4 d | webp/Imagify, tre private designsider, plugin-oprydning |
| S4 — salgsklar butik | 5,0 d | 1,4 d | 3,6 d | valuta, betalinger, anmeldelser, mockups, forsendelse |
| **I alt** | **21,1 d** | **10,55 d** | **10,55 d** | |

`docs/projektstatus.md` §4 er rettet til de samme tal; den manuelle tabel stod på 9,25 d, fordi S1,
S2 og S3 var opdateret i JSON men ikke i tabellen.

## 4. Blokeringer

| Blokering | Hvem | Effekt |
| --- | --- | --- |
| Ingen `ROYAL_MCP_TOKEN` i miljøet | ejer: Royal MCP → Settings → WordPress API Key → ind i Settings → Secrets | Ingen skrivning til sitet: ingen publicering, ingen nav-ændring, ingen meta-fix |
| Bot-blokering (454/455) | ejer/host: Simply | Uafklaret om søgemaskiner kan læse sitet — S0.7 kan ikke lukkes før |
| Redirection uden gruppe | ejer: Tools → Redirection → Add Group | De sidste to vanity-301 kan ikke oprettes via MCP |
| Manglende CVR/adresse/telefon | ejer | GPSR-blok, `/shipping/`, `/returns/` kan ikke publiceres korrekt |

---

# 5. Klar til publicering: `/shipping/` og `/returns/`

De to sider svarer stadig 404, og de er de sidste i S2's rest. Copy nedenfor er skrevet til den
produktionstid (3–7 arbejdsdage) og leveringstid (6–10 dage i DK, 7–12 dage i EU), som
produktteksterne allerede lover. Felter markeret `[EJER]` kræver oplysninger jeg ikke har og **må
ikke publiceres som de står** — det er samme mangel som GPSR-blokken og butiksauditen venter på.

## `/shipping/`

```
SEO-titel:   Shipping and Delivery | Steamgears
Meta:        Production takes 3–7 business days and delivery 6–10 days in Denmark, 7–12 in the
             rest of the EU. Here is exactly how your order travels. (147 tegn)
H1:          Shipping and Delivery
```

**Made to order, not pulled from a shelf.** Every Steamgears piece is printed or produced after you
order it. That is why the timeline has two steps instead of one.

### 1. Production — 3 to 7 business days
Your item is produced at our print partner's facility nearest to you. In busy periods — Halloween,
Christmas, Valentine's — production can take up to 10 business days. If your order is affected, we
email you before the stated delivery window closes.

### 2. Delivery — 6 to 10 days in Denmark, 7 to 12 days in the rest of the EU
| Destination | Delivery after production | Carrier |
| --- | --- | --- |
| Denmark | 6–10 days | [EJER: carrier] |
| EU | 7–12 days | [EJER: carrier] |
| Outside the EU | [EJER: countries + times] | [EJER: carrier] |

You get a tracking link by email when the parcel leaves production. Tracking sometimes lags a day
behind the handover, so "no information yet" on day one is normal.

### Shipping cost
[EJER: price table or free-shipping threshold. It must match what the cart calculates — do not
invent one.]

### Customs and VAT outside the EU
Orders shipped outside the EU may be charged import VAT and customs duty on arrival. Those charges
are set by your country and are not included in the price you pay us.

### If something goes wrong
Write to [EJER: email] with your order number. If a parcel is lost or damaged in transit we
reproduce or refund it — you do not have to argue with the carrier.

*Wrong size, changed your mind, or want to know how a return works? See
[Returns and Refunds](/returns/). The formal terms are in our [refund policy](/refund_returns/) and
[terms](/terms/).*

## `/returns/`

```
SEO-titel:   Returns and Refunds | Steamgears
Meta:        Fourteen days to change your mind, and made-to-order items are covered too. How to
             return an order, who pays the postage, and how fast the refund lands. (144 tegn)
H1:          Returns and Refunds
```

**You have 14 days.** From the day the parcel arrives you can withdraw from the purchase without
giving a reason — that is the EU right of withdrawal, and it applies to our made-to-order pieces as
well. The full procedure and the EU model withdrawal form are on
[the right of withdrawal page](/right-of-withdrawal/).

### How to return an order
1. Email [EJER: email] within 14 days of delivery with your order number.
2. We reply with the return address and a return reference. Do not ship before you have it — an
   unannounced parcel cannot be matched to an order.
3. Pack the item so it arrives in the condition you received it, and send it within 14 days of
   telling us.
[EJER: who pays return postage, and whether it differs for faulty items.]

### Refunds
We refund within 14 days of receiving the returned item, to the same payment method you used. The
refund covers the item price and the original shipping cost when you withdraw from the whole order.
If you return part of an order, the original shipping cost is not refunded.

### Faulty or wrong item
Send a photo and your order number. We reproduce or refund, and you do not pay return postage for a
fault on our side.

### What cannot be returned
[EJER: confirm before publishing. Personalised items and sealed digital downloads are the usual
exceptions under EU rules — check whether any of the 39 products are sold as personalised.]

*Delivery times and production windows are on [Shipping and Delivery](/shipping/). The formal terms
are in our [refund policy](/refund_returns/) and [terms](/terms/).*

## Rækkefølge når skriveadgangen er der

1. Udfyld `[EJER]`-felterne, opret begge sider, sæt Rank Math-titel og -description som angivet.
2. Læg dem i en `Info`-gruppe i navigationen sammen med FAQ, About og Contact (S2.3).
3. De to sidste vanity-301 i S0.4 behøves så ikke — målene er nu rigtige sider.
4. Kør `python3 projektstatus.py --update` bagefter; S2.2 tæller først når siderne svarer 200.
5. Genmål med et bot-klassificeret request (S0.7) og afklar 454/455 med Simply.
