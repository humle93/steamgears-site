# Hub-blueprint — S2.1 (`/start-here/`, `/build/`, `/inspiration/`)

_1/11 2026 · grundlag for punkt **S2.1** i [site-roadmap-2026-11-01.md](../site-roadmap-2026-11-01.md) · bygger på
[indholdsaudit-2026-11-01.md](indholdsaudit-2026-11-01.md), [dode-links-2026-11-01.md](dode-links-2026-11-01.md),
[om-maerkning-dark-academia-2026-11-01.md](om-maerkning-dark-academia-2026-11-01.md) og
[mangler-og-plan-25-10.md](mangler-og-plan-25-10.md) §4._

Dette dokument er **copy + struktur, klar til at blive lagt ind over MCP**. Da det blev skrevet (1/11), svarede
hubene 404 (målt i roadmap §3.1), og MCP-fladen i den session var læsende.

## Status — målt 2/11 2026

| URL | Status | Målt |
| --- | --- | --- |
| `/start-here/` | ✅ **live** (side-id 1183), titel og meta identisk med §3 | 200 |
| `/build/` | ✅ **live** (side-id 1185), titel og meta identisk med §4 | 200 |
| `/inspiration/` | ❌ mangler | 404 |
| `/build/corsets/` | ❌ mangler | 404 |
| `/build/jackets/` | ❌ mangler | 404 |
| `/build/props/` | ❌ mangler | 404 |

**Verifikation af de to live hubs (2/11):** 951 ord (`/start-here/`) og 810 ord (`/build/`) i brødteksten, H1 er
henholdsvis «Start Here» og «Build», og H2-rækkefølgen følger §3/§4. **39 unikke interne links er tjekket,
39 svarer 200** — ingen nye døde links. De to sider linker til otte guides og fire kategorier, som ikke stod i
§1's tabel, og som alle svarer 200: `/what-is-dieselpunk/`, `/what-is-gaslamp-fantasy/`,
`/best-steampunk-books/`, `/the-clockwork-seer-short-story/`,
`/the-last-flight-of-the-meridian-short-story/`, `/steampunk-outfit-ideas-for-women/`,
`/steampunk-outfit-ideas-men/`, `/steampunk-couple-costumes/`, `/closet-steampunk-outfits/`,
`/steampunk-accessories/`, `/dieselpunk-costume/`, `/product-category/airship-captain/`,
`/product-category/victorian-inventor/`, `/product-category/hoodies/`, `/product-category/t-shirts/`.
**`/size-guide/` og `/delivery/` svarer nu 200** og er dermed ude af «verificér før brug»-listen i §1.

De fire manglende sider er ikke lenket fra nogen af de to live hubs, så de skaber ingen 404'er i navigationen,
men hub-strukturen er halvt bygget: `/start-here/` peger på `/build/`, og `/build/` peger tilbage på
`/start-here/` — uden `/inspiration/` og de tre komponent-sider er der ingen vej til galleri, journal, noveller
eller til corsets/jakker/props.

---

## 0. TL;DR — hvad der skal bygges, og hvad der ikke skal

| # | URL | Type | Ny side? | Effort |
| --- | --- | --- | --- | --- |
| 1 | `/start-here/` | Indgangshub: 5-trins sti gennem **eksisterende** guides | ✅ ny | 0,5 d |
| 2 | `/build/` | Komponent-hub: 6 dele af et outfit | ✅ ny | 0,4 d |
| 3 | `/inspiration/` | Visningshub: galleri, journal, noveller, kollektioner | ✅ ny | 0,3 d |
| 4 | `/build/corsets/` | Købs-/pasforms-side (7 corsets i katalog, prio-16-søgeord) | ✅ ny | 0,4 d |
| 5 | `/build/jackets/` | Købs-/pasforms-side (12 hoodies/jakker) | ✅ ny | 0,3 d |
| 6 | `/build/props/` | Rekvisit-hub (lommeure, stokke, hatte, 3D-print) | ✅ ny | 0,3 d |
| — | `/build/goggles/` | **Oprettes ikke** — dækket af `/diy-steampunk-goggles/` + `/diy-vs-buy-steampunk-goggles/` | ❌ | 0 |
| — | `/build/accessories/` | **Oprettes ikke** — dækket af live-post 30 + kategori | ❌ | 0 |
| — | `/build/full-outfits/` | **Oprettes ikke** — dækket af `/steampunk-costumes/` (550) + `/how-to-make-a-steampunk-costume/` | ❌ | 0 |
| — | `/start-here/` 5 børn | **Oprettes ikke som sider** — bliver de fem trin på hubben, der peger på live-guides | ❌ | 0 |

**Hvorfor 6 sider og ikke 13.** Workshop-planen (25/10) bad om hub + 11 undersider. Fire af de fem
`/start-here/`-børn og tre af de seks `/build/`-børn ville blive tynde dubletter af sider, der **allerede
ligger på side 1** — `/how-to-make-a-steampunk-costume/` (pos. 7,4), `/diy-steampunk-goggles/` (pos. 8,7),
`/steampunk-cosplay-for-beginners/`, `/steampunk-cosplay-on-a-budget/`, `/diy-vs-buy-steampunk-goggles/`,
`/3d-print-steampunk-parts/`. At bygge dem ville kannibalisere de eneste URL'er, der reelt henter visninger
(3 klik / 110 impressions i uge 44), og de ville hver især mangle unikt indhold. Planen beholdes som
**sti-struktur**, ikke som URL-struktur: hubben ejer overblikket, guiden ejer detaljen.

Det er en ændring af beslutning 1 i roadmap §6 og skal bekræftes af ejeren. Effekten er 3,0 d → ca. 2,2 d for
S2.1; de sparede dage er **ikke** bogført i `projektstatus.json` endnu.

**Hvad hubene ikke må blive:** endnu en liste over de samme links. Hver af de seks sider nedenfor har et
eget job, et eget søgeord og en egen sektion, som ingen anden side har — se §2.

---

## 1. Verificerede URLs, der må linkes til

Kun disse er brugt i linkene nedenfor. Status er fra 1/11-målingerne; alt andet skal verifies **før**
indsættelse, ellers gentager vi de 39 døde links.

| URL | Status | Kilde |
| --- | --- | --- |
| `/` | 200 | roadmap §3.1 |
| `/shop/` | 200 | roadmap §3.1 |
| `/faq/` | 200 | roadmap §3.1 |
| `/about/` | 200 | roadmap §3.1 |
| `/journal/` | 200 | roadmap §3.1 |
| `/steampunk-costumes/` | 200 (ikke indekseret endnu) | roadmap §3.1, site-check §3 |
| `/steampunk-halloween/` | 200 | roadmap §3.1 |
| `/steampunk-cosplay-gallery/` | 200 | døde-links §D/§E-måling + site-check §3 |
| `/the-clockwork-herbarium-short-story/` | 200 | roadmap §3.1 |
| `/steampunk-cosplay-for-beginners/` | 200 + indekseret | site-check §3 |
| `/how-to-make-a-steampunk-costume/` | 200 + indekseret | site-check §3 |
| `/steampunk-cosplay-on-a-budget/` | 200 + indekseret | site-check §3 |
| `/diy-vs-buy-steampunk-goggles/` | 200 + indekseret | site-check §3 |
| `/diy-steampunk-goggles/` | 200 + indekseret | site-check §3 |
| `/casual-steampunk-outfits/` | 200 + indekseret | site-check §3 |
| `/steampunk-costume-ideas/` | 200 + indekseret | site-check §3 |
| `/steampunk-vs-dieselpunk/` | 200 | døde-links (post 798) |
| `/3d-print-steampunk-parts/` | 200 | døde-links §Fordeling |
| `/pirate-cosplay-for-beginners/` | 200 | døde-links §Fordeling |
| `/steampunk-gift-guide-2026/` | 200 | døde-links §Fordeling |
| `/sustainable-steampunk/` | 200 | døde-links §Fordeling |
| `/how-to-build-a-steampunk-costume-for-under-100-complete-2027-workshop-plan/` | 200 | døde-links §Fordeling |
| `/refund_returns/` | 200 | døde-links §E |
| `/product-category/steampunk-goggles/` | 200 | døde-links §D |
| `/product-category/accessories/` | 200 | døde-links §D |
| `/product-category/posters-prints/` | 200 | døde-links §D |
| `/product-category/dark-academia/` | 200 | om-mærkning, batch 2 |
| `/product-category/gothic-victorian/` | 200 | om-mærkning, batch 2 |
| `/product-category/natural-history/` | 200 | om-mærkning, batch 2 |
| `/product-category/vintage-maritime/` | 200 | om-mærkning, batch 2 |
| `/product-category/witchy-tarot/` | 200 | om-mærkning, batch 2 |
| `/product-category/steampunk-capsule/` | 200 | om-mærkning, batch 2 |

**Skal verifies før brug (ikke linket i denne blueprint):** `/size-guide/` (side 597), `/delivery/` (582),
`/free-starter-kit/` (801), lead magnet 764, produkt-permalinks (kun IDs kendes sikkert — se §3, "produktvalg").
De 6 kladde-produkter i døde-links §A (27, 28, 29, 75, 183, 765) må **først** linkes efter S1.1/S1.2-er
publicering, ellers skaber hubene nye 404'er med det samme.

---

## 2. Kannibaliseringsværn — hver sides unikke job

| Side | Søgeord (focus) | Job som ingen anden side har | Må ikke gentage |
| --- | --- | --- | --- |
| `/start-here/` | steampunk starter guide | Rækkefølgen: hvad man gør i hvilken orden, og hvornår man går videre | Guidernes indhold — kun 2–3 linjers begrundelse pr. trin |
| `/build/` | build a steampunk outfit | Opdele et outfit i 6 dele og sige hvad der bærer looket | Materiale-guides, DIY-trin |
| `/inspiration/` | steampunk inspiration | Visuelt forbrug: galleri, noveller, kollektioner — 0 HowTo | Alle guides |
| `/build/corsets/` | steampunk corset | Pasform og konstruktion ved køb (overbust/underbust, busk, mål) | `how-to-make-a-steampunk-costume` |
| `/build/jackets/` | steampunk jacket | Lag-på-lag over tøj: hvilken jakke/hoodie til hvilket look | Produktteksterne (kun uddrag) |
| `/build/props/` | steampunk props | Rekvisitter og tilbehør som look: ur, stok, hat, 3D-print | `3d-print-steampunk-parts` (den er teknisk) |

Alle seks sider får `H1` der ikke er identisk med nogen eksisterende titel, og evig `noindex` er **ikke** sat —
de skal indekseres, fordi de er indgange, ikke dubletter.

---

## 3. Copy

Al tekst nedenfor er engelsk som resten af sitet. Titler er holdt ≤ 62 tegn og meta 140–158 tegn
(sitet har 0 titler over 62 efter 1/11-runden — det skal blive ved med at være 0).

---

### 3.1 `/start-here/`

| Felt | Værdi |
| --- | --- |
| Titel (H1) | Start Here: Build Your First Steampunk Look |
| SEO-titel | Start Here: Your First Steampunk Look \| Steamgears |
| Meta | New to steampunk? Follow the five-step path from first idea to finished outfit — with budget levels, DIY-or-buy calls and gear that actually fits. |
| Focus keyword | steampunk starter guide |
| Sideopsætning | Fuldbredde, ingen sidebar, `wp_get_page`-parent 0 |

**Intro (omtalen bruges også som excerpt, så den skal stå alene):**

> Steampunk is not a costume you buy in one click. It is a handful of decisions — how historical you want to
> look, how much of it you want to make, and how much you want to spend — and each decision has a cheap answer
> and a good answer. This page is the order to take them in.

**H2: What steampunk actually is (60 seconds)**
Brass and leather get the attention, but the look is built on three things: Victorian silhouette, industrial
materials, and a story the wearer can tell. A corset alone reads as costume; a corset, aviation goggles and a
pocket watch on a chain read as a character. If you cannot tell steampunk from dieselpunk yet, read
[Steampunk vs. Dieselpunk](/steampunk-vs-dieselpunk/) before you buy anything — the two share a silhouette and
almost nothing else.
_Videre til:_ forklaring, ingen CTA.

**H2: The five-step path**

1. **Decide the character, not the costume.** Pick one of three entries: the tinkerer (apron, goggles, rolled
   sleeves), the airship captain (long coat, boots, brass), or the Victorian naturalist (waistcoat, spectacles,
   specimen jars). Everything you buy afterwards should serve that one story.
   → [Steampunk Cosplay for Beginners](/steampunk-cosplay-for-beginners/)
2. **Fix your budget before your wish list.** A convincing look is €75. A very good one is €150. Past that you
   are paying for materials, not for recognition.
   → [Steampunk Cosplay on a Budget](/steampunk-cosplay-on-a-budget/)
3. **Choose DIY or buy, per item — not for the whole outfit.** Goggles and props are the two items where making
   them yourself usually looks better than the cheap alternative.
   → [DIY vs. Buy: Steampunk Goggles](/diy-vs-buy-steampunk-goggles/)
4. **Assemble from the inside out.** Base layer, then structure (corset, waistcoat, coat), then hardware
   (goggles, straps, watch), then the one prop you will actually carry all day.
   → [How to Make a Steampunk Costume](/how-to-make-a-steampunk-costume/)
5. **Wear it somewhere.** A look that never leaves the house does not improve. Start with a convention, a
   market, or Halloween — the [Halloween hub](/steampunk-halloween/) is the shortest deadline of the year.

**H2: Pick your first investment**
Three places your money does the most work, in order: a structured top layer (corset or waistcoat), wearable
eyewear, and one metal thing you touch often — a watch, a flask, a buckle. Everything else can be thrifted.
→ [Dark Academia](/product-category/dark-academia/) · [Gothic & Victorian](/product-category/gothic-victorian/) ·
[Steampunk Capsule](/product-category/steampunk-capsule/)

**H2: What you can skip**
Foam armour, glowing props and full-face masks look impressive in photos and are miserable in a crowd. Nobody
regrets leaving them at home.

**H2: Five mistakes that make a look read as "costume"** (kort, hver 1–2 linjer)
Mismatched eras · shiny pleather instead of matte · too many props at once · modern sneakers left in frame ·
no story when someone asks what you are.

**H2: Start with the checklist**
The free starter kit gathers the five steps, the shopping list and the budget table in one PDF.
→ lead magnet 801 (verificér permalink før indsættelse)

**CTA-blok:** `Shop the pieces →` (knap til `/shop/`) · sekundær: `Read the guides →` (`/journal/`)

---

### 3.2 `/build/`

| Felt | Værdi |
| --- | --- |
| Titel (H1) | Build Your Steampunk Outfit, Piece by Piece |
| SEO-titel | Build a Steampunk Outfit, Piece by Piece \| Steamgears |
| Meta | Six parts make a steampunk look: eyewear, corsets, jackets, props, accessories and the full outfit. Here is what each one does and where to start. |
| Focus keyword | build a steampunk outfit |
| Sideopsætning | Fuldbredde, 6 komponentkort øverst, produktgitter nederst |

**Intro:**

> Every steampunk outfit is the same six decisions made in a different order. Get the silhouette right and the
> details can be cheap; get it wrong and no amount of brass will save it.

**H2: 1. Eyewear** — Goggles are the single most recognisable object in the style, and the only one where
homemade can beat bought. Pick a lens shape that matches your character: round for the laboratory, aviator for
the deck, monocle for the salon.
→ [DIY Steampunk Goggles](/diy-steampunk-goggles/) · [DIY vs. Buy](/diy-vs-buy-steampunk-goggles/) ·
[Goggles category](/product-category/steampunk-goggles/)

**H2: 2. Corsets** — The structural piece. It changes the silhouette more than anything else you can put on,
and it is the item most often bought in the wrong size.
→ [Steampunk Corsets: How to Choose One That Fits](/build/corsets/)

**H2: 3. Jackets and layers** — A long coat reads as authority, a waistcoat reads as craft, a hoodie reads as
comfort. All three are period-plausible; only one of them suits a given character.
→ [Steampunk Jackets and Layers](/build/jackets/)

**H2: 4. Props** — The story-carrier: a pocket watch, a cane, a specimen jar, a printed gear set. One is
atmosphere; four is a costume shop.
→ [Steampunk Props](/build/props/) · [3D-Printed Steampunk Parts](/3d-print-steampunk-parts/)

**H2: 5. Accessories** — Belts, straps, gloves, jewellery and hats do the "lived-in" work, and they are where a
budget look is lifted fastest.
→ live post 30 (Steampunk Accessories) · [Accessories category](/product-category/accessories/)

**H2: 6. The full outfit** — Once the pieces exist, the remaining job is proportion: high collar with low
waistline, heavy boots with a light top.
→ [Steampunk Costumes](/steampunk-costumes/) · [How to Make a Steampunk Costume](/how-to-make-a-steampunk-costume/)

**H2: Materials that photograph well** — Matte leather over gloss, aged brass over chrome, wool over polyester
fleece, cotton over satin. The camera punishes shine.

**H2: Fit and sizing** — Apparel in this shop is measured flat and printed to order; check the size guide before
you commit, because printed garments cannot be resold. (link: `/size-guide/` — verificér)

**CTA:** `Browse the shop →` (`/shop/`) · `Explore collections →` (`/inspiration/`)

---

### 3.3 `/inspiration/`

| Felt | Værdi |
| --- | --- |
| Titel (H1) | Inspiration: Looks, Stories and Collections |
| SEO-titel | Steampunk Inspiration: Looks, Stories & Collections |
| Meta | Real steampunk looks, the stories behind the designs, and six collections to browse when you know the mood but not the piece. Browse by mood, then by piece. |
| Focus keyword | steampunk inspiration |

**Intro:**

> Inspiration is cheaper than gear and lasts longer. Start with a look you would actually wear on a Tuesday,
> then work backwards to the pieces.

**H2: The gallery** — Costumes built by readers and by us, photographed in daylight rather than in a studio.
→ [Cosplay Gallery](/steampunk-cosplay-gallery/)

**H2: Six collections, six moods** — Dark Academia (ink, brass instruments, library light) · Gothic & Victorian
(high collars, mourning jewellery) · Natural History (specimens, ferns, cabinet labels) · Vintage Maritime
(airships, charts, harbour towns) · Witchy & Tarot (cards, candlelight) · Steampunk Capsule (the classic look).
Each collection page groups the designs that share a palette.

**H2: Stories** — Two of the designs come with fiction. The Clockwork Herbarium follows a botanist cataloguing
plants that should not exist; reading it changes what the prints look like on a wall.
→ [The Clockwork Herbarium](/the-clockwork-herbarium-short-story/) · [Journal](/journal/)

**H2: Adapt a look you already own** — Most steampunk outfits are one modern garment away from working: swap
sneakers for boots, add a waistcoat, put a watch on a chain. → [Casual Steampunk Outfits](/casual-steampunk-outfits/)

**H2: Where to wear it** — Conventions, maker fairs, Halloween, photo walks. → [Steampunk Costume Ideas](/steampunk-costume-ideas/) ·
[Halloween](/steampunk-halloween/)

**CTA:** `Shop the collections →` (`/shop/`) · `Send us your look →` (`/about/`, kontakt)

---

### 3.4 `/build/corsets/`

| Felt | Værdi |
| --- | --- |
| Titel (H1) | Steampunk Corsets: How to Choose One That Fits |
| SEO-titel | Steampunk Corsets: How to Choose One That Fits |
| Meta | Overbust or underbust, steel busk or stretch panel, and how to measure yourself once so the corset fits the first time. Skip the returns queue. |
| Focus keyword | steampunk corset |

**Indhold (H2-rækkefølge):**
1. **Overbust or underbust** — Overbust carries the whole look on its own and suits the naturalist and the
   salon; underbust layers over a shirt and suits the tinkerer and the airship captain.
2. **Structure, honestly described** — Steel busk and spiral bones hold a shape; a stretch panel smooths but
   does not shape. If the listing does not say which, assume the cheaper one.
3. **Measure once** — Underbust, natural waist, and torso length from underbust to lap. Compare against the
   garment's *flat* measurements, not your body measurement plus a guess. Cross-check the
   [size guide](/size-guide/) (verificér) for the shop's own table.
4. **What to wear underneath** — A thin cotton layer, no bulk, nothing that bunches at the waist. A corset liner
   is cheaper than a return.
5. **Wearing it for a whole day** — Lace it closed at the waist first, then work upwards; leave the last two
   inches open at the top and bottom. If you cannot take a full breath, it is too tight — not "not broken in yet".
6. **Care** — Spot clean, dry flat, never machine wash, never hang by the laces.
7. **The seven corsets in the catalogue** — produktvalg: ID 28 (`victorian-underbust-corset`, pt. kladde — S1.1),
   plus 6 øvrige (verificér permalinks og priser før indsættelse).
8. **FAQ (3 spørgsmål)** — Kan man sidde ned i en corset? Hvor stramt skal det være? Kan den bruges til everyday?

---

### 3.5 `/build/jackets/`

| Felt | Værdi |
| --- | --- |
| Titel (H1) | Steampunk Jackets and Layers |
| SEO-titel | Steampunk Jackets: Coats, Waistcoats and Layers |
| Meta | Long coat, waistcoat or hoodie: how each layer changes a steampunk silhouette, and which one suits the character you picked. Sizing notes included. |
| Focus keyword | steampunk jacket |

**Indhold (H2-rækkefølge):**
1. **Three silhouettes** — Authority (long coat, high collar) · Craft (waistcoat, rolled sleeves, apron) ·
   Comfort (hoodie, print-led look). Same design language, three different characters.
2. **Length is the decision** — A coat that ends below the knee adds height and slows the walk; a cropped
   layer keeps proportions modern. Neither is more correct.
3. **Fabric and print** — Wool and heavy cotton read as period; printed jersey reads as merch, which is fine if
   the print is the point. → [Posters & Prints](/product-category/posters-prints/)
4. **Layering under a corset** — Thin shirt, no elastic waistband, sleeves that fit inside the armhole.
5. **Care and fit** — Printed-on-demand garments shrink once; wash cold, inside out, and check the
   [size guide](/size-guide/) (verificér).
6. **Product picks** — 12 hoodies/jakker i kataloget; vælg 3 pr. silhouette (verificér IDs og permalinks).

---

### 3.6 `/build/props/`

| Felt | Værdi |
| --- | --- |
| Titel (H1) | Steampunk Props and Carry Pieces |
| SEO-titel | Steampunk Props: Pocket Watches, Canes & 3D Parts |
| Meta | The one object you carry all day does more for a steampunk look than five you leave in the bag. Here is how to choose it. One good prop beats five. |
| Focus keyword | steampunk props |

**Indhold (H2-rækkefølge):**
1. **One prop, carried properly** — A watch on a chain, a cane, a leather satchel or a specimen case. If it
   cannot survive a full day in your hand, it is a photo prop.
2. **Metal you can touch** — Patina beats polish. Aged brass, darkened steel, worn leather.
3. **Printed and 3D parts** — Gears, keys and brackets are where printing shines; they are light, cheap and
   replaceable. → [3D-Printed Steampunk Parts](/3d-print-steampunk-parts/)
4. **Hats** — A top hat changes posture; a flat cap keeps it casual. Both are period-safe; pick by character.
5. **Safety and venues** — Convention rules on replica weapons and metal-tipped canes vary by venue; check
   before you travel.
6. **Product picks** — accessories-kategorien + 3D-print; verificér IDs.

---

## 4. Link-map — hvem linker til hvem

| Fra | Til | Ankertekst |
| --- | --- | --- |
| Forside | `/start-here/` | Start here |
| `/steampunk-costumes/` (550) | `/build/`, `/inspiration/` | Build the look · Get inspired |
| `/start-here/` | 5 live-guides + `/steampunk-halloween/` | se §3.1 |
| `/build/` | `/build/corsets/`, `/build/jackets/`, `/build/props/`, goggles-guides, accessories-post, `/steampunk-costumes/` | se §3.2 |
| `/inspiration/` | galleri, journal, novelle, 6 kollektioner, `/casual-steampunk-outfits/` | se §3.3 |
| `/build/corsets/` | `/how-to-make-a-steampunk-costume/`, `/product-category/gothic-victorian/` | "the full look" |
| `/build/jackets/` | `/product-category/dark-academia/`, `/product-category/steampunk-capsule/` | "see the collections" |
| `/build/props/` | `/3d-print-steampunk-parts/`, `/product-category/accessories/` | "printed parts" |
| Alle tre hubs | hinanden (sidebjælke/brødkrumme) | Start Here · Build · Inspiration |

**Navigation (S2.3) forudsætter disse sider:** Start Here · Build · Shop · Inspiration · Info. Den rækkefølge
kan først sættes ind, når `/start-here/`, `/build/` og `/inspiration/` svarer 200 — ellers bygger vi fem nye
døde links i menuen, hvor der i dag er nul.

**Rækkefølge ved indsættelse (undgå 404-vindue):**
1. `/start-here/`, `/build/`, `/inspiration/` — som **kladde**.
2. `/build/corsets/`, `/build/jackets/`, `/build/props/` — som kladder, med `/build/` som parent.
3. Indsæt links fra hub → børn, og fra eksisterende sider → hub (link-map ovenfor).
4. Publicér børnene først, derefter hubene, derefter navigationen (S2.3). Sidste skridt: W3TC purge +
   `curl` mod alle ni URL'er.

---

## 5. Verifikation efter indsættelse

| Tjek | Krav |
| --- | --- |
| HTTP | 6 nye + 9 eksisterende URL'er i link-mappet svarer 200 |
| Titler | Alle 6 SEO-titler ≤ 62 tegn; meta 140–158 tegn |
| H1 | Præcis én pr. side, og ingen af dem identisk med en eksisterende titel |
| Døde links | `dode-links`-krybbet kørt igen: 0 nye 404'er (baseline: 39 → 0 pr. 1/11) |
| Sitemap | De 6 nye sider optræder i `page-sitemap`, når de er publiceret |
| Navigation | S2.3 først efter alle 200 |
| Cache | W3TC purget, og forsiden + `/build/` tjekket uden `mwai-`/`ai-engine`-markup |

---

## 6. Hvad der ikke er gjort i denne runde

- **Intet er skrevet til sitet.** MCP-fladen var læsende i sessionen, og hubene svarer stadig 404.
- **Ingen billeder eller featured images** er valgt; 20 af 24 posts mangler allerede featured image, og
  og:image mangler derfor på alle guides (site-check §7.1). De 6 nye sider bør have ét billede hver — ellers
  gentager vi fejlen på de sider, der skal være indgange.
- **Produktvalg er angivet som ID'er**, ikke permalinks, fordi 6 relevante produkter stadig er kladder
  (døde-links §A) og fordi 43 produkter ligger i papirkurven (§B). Permalinks skal bekræftes, før de indsættes.
- **Effekten er ikke målt.** Der er ingen klik-, visnings- eller indekseringsdata for sider, der ikke findes.
