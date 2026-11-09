# Publiceringskørsel — alt åbent arbejde, i den rækkefølge det skal udføres

_Sidste kørsel: 9/11 2026. Kilder: copyfilerne i `docs/library/`, priserne i [`priser-live-2026-11-09.md`](priser-live-2026-11-09.md), ejerpunkterne i [`docs/projektstatus.md`](../projektstatus.md) §2 og [`docs/roadmap.md`](../roadmap.md) §4d–§4e._

Dette er den ene kørsel: **syv faser**, hvor hvert trin har et sted at gå hen, en konkret værdi at taste, og en kommando der beviser at det virkede. Copyen nedenfor er den **færdige tekst**, ikke en henvisning — blokkene er genereret ud af de filer `tools/guide_check.py` validerer, så de kan ikke være nyere eller ældre end det værn, der kontrollerer dem.

**Ingen data er opdigtet.** Alt agenten ikke må gætte står som `[EJER: …]` med en sætning om hvad der mangler og hvor det skal tastes. Der er **49 `[EJER: …]`-markeringer**, grupperet i 16 datamangler i §10.

**Udførelsesrækkefølgen er ikke vilkårlig:** 699 først (en live side i forkert valuta), derefter det der gør butikken lovlig og salgbar i EU, derefter indholdet, derefter billeder — som er det der låser flest procent op pr. time — og til sidst miljø og kanaler, fordi de kræver paneler uden for WordPress.


## 1. Faserne

| Fase | Hvad den lukker | Tid | Hvorfor her |
| ---: | --- | --- | --- |
| 1 | Post 699 i DKK, EU-fragt, CVR/telefon, betaling | 2–3 t | En live side med USD og en kurv EU-kunder ikke kan bruge koster penge nu |
| 2 | 292, 296, 295 og side 550 | 3–4 t | Copyen er færdig og målt; den venter kun på indsættelse |
| 3 | To egne fotos pr. guide + Batch 4-mockups | 6–8 t **eller** et Printful-token | Låser **+28,7 pp** op i rebrand — den største enkeltstående gevinst |
| 4 | S5: staging, DMARC/DKIM, backup-politik, branch protection | 1–2 t + Simply-sag | Låser **+40 pp** op i miljø; to af trinnene tager under en time |
| 5 | Plugin-zip, forumside, Reddit-app, kanal-tokens | 2 t + ventetid | Lukker P5's sidste 3,3 pp |
| 6 | KABINE E1–E6 | uger | Kræver CVR, leverandør og annoncekonto — ikke en WP-handling |
| 7 | Efterkontrol (§11) | 20 min | Beviset, ikke påstanden |


## 2. Fase 1 — butikken før indholdet

### 2.1 Post 699: «Prices are in USD» ud af den live side

**Hvor:** WordPress → Indlæg → 699. **Behold slug og post-ID** — der er indgående links og Rank Math-historik på dem. **Kilde:** [`steampunk-outfit-ideas-for-women.md`](library/steampunk-outfit-ideas-for-women.md).

| Felt | Værdi |
| --- | --- |
| Post-ID | **699** (rør ikke) |
| Slug | `` |
| Rank Math title (55 tegn, max 62) | `Steampunk Outfit Ideas for Women: 12 Looks, Real Prices` |
| Meta description (153 tegn, skal være 140–155) | `Twelve complete steampunk outfits for women, built from ten pieces in our own shop: corsets, tailcoats, goggles and tees, with DKK prices from 580,00 kr.` |
| Focus keyword | `steampunk outfit ideas for women` |
| Produkter linket | 13 id'er: 1282, 399, 403, 477, 483, 489, 491, 493, 495, 557, 940, 965, 967 |
| FAQ | 6 spørgsmål — brug Rank Math FAQ-blok, så schema matcher 1:1 |
| Omfang | 1798 ord |

**Sådan gør du:**

1. Åbn 699. Tryk **⋮ → Kodeeditor** (ikke den visuelle editor; blokken er HTML).
2. Markér **alt** i indholdsområdet og slet det.
3. Sæt blokken nedenfor ind. Den starter med `<h1>` og slutter med shortcoderne `[sg-shoprow]` og `[sg-buy]`, som pluginnet renderer — de skal blive stående.
4. **Opdater**. Kontrollér i den visuelle editor at der ikke står `**` eller `###` tilbage.
5. Rank Math → title, meta og focus keyword som i tabellen; FAQ-spørgsmålene nedenfor ind i FAQ-blokken.
6. GSC → URL-inspektion → **Request indexing**.
**Bevis:** `curl -s https://steamgears.eu/steampunk-outfit-ideas-for-women/ | grep -c '\$[0-9]'` svarer **0**, og `grep -c 'kr\.'` er større end **0**; siden har 12 looks og 6 FAQ-spørgsmål.

**Indsætningsklar blok (699):**

```html
<h1>Steampunk Outfit Ideas for Women: 12 Complete Looks (With Prices)</h1>
<p><strong>Short answer:</strong> a complete steampunk outfit for women needs five things — a corset or a fitted vest, one styled layer, goggles, a brass accessory and shoes you can walk in. Start with a printed tee and the leather corset at <strong>580,00 kr.</strong>, or go straight to the full Victorian look at <strong>1.300,00 kr.</strong> Below are twelve complete outfits, every piece linked, and every price read from our own shop in DKK.</p>
<p>Every look below is a <strong>complete outfit</strong>: the pieces listed add up to something you can put on, not a mood board. One corset carries five of the twelve looks, so the cheapest way through this page is to pick the look you would actually wear and build outward from it. Prices were read from the live shop on 9 November 2026; the product page wins if a price has moved since.</p>
<h3>1. The Airship Captain</h3>
<p>The look people recognise from across the room. Goggles pushed up on the tall hat, brocade tailcoat over whatever you already wear, and nothing else needed: this is the cheapest complete costume on the page, and it is the one to buy first if you are only buying once.</p>
<ul>
  <li><a href="https://steamgears.eu/go/477/">Copper Pilot Goggles — Vintage Windproof</a> — <strong>165,00 kr.</strong></li>
  <li><a href="https://steamgears.eu/go/483/">Time Traveler Top Hat with Goggles</a> — <strong>335,00 kr.</strong></li>
  <li><a href="https://steamgears.eu/go/495/">Victorian Vampire Tailcoat — Brocade</a> — <strong>265,00 kr.</strong></li>
  <li><strong>Total: 765,00 kr.</strong></li>
</ul>
<p>It works because the same three pieces carry a photo, a party and a Tuesday — take the hat off and the coat still reads as clothing.</p>
<h3>2. The Clockwork Duchess</h3>
<p>Silhouette first. The leather overbust corset fixes the waist, the wine velvet frock coat gives you the length, and the goggles are the one prop that stops it reading as evening wear.</p>
<ul>
  <li><a href="https://steamgears.eu/go/489/">Steampunk Overbust Corset — Leather Lace-Up</a> — <strong>400,00 kr.</strong></li>
  <li><a href="https://steamgears.eu/go/493/">Wine Red Velvet Tailcoat — Victorian Frock Coat</a> — <strong>435,00 kr.</strong></li>
  <li><a href="https://steamgears.eu/go/477/">Copper Pilot Goggles — Vintage Windproof</a> — <strong>165,00 kr.</strong></li>
  <li><strong>Total: 1.000,00 kr.</strong></li>
</ul>
<p>Wear the coat open so the corset line stays visible; closed, it becomes a dress and loses the character.</p>
<h3>3. The Gothic Aviator</h3>
<p>The silver corset is the coolest piece we make and the hardest to style, so the rest of the look stays quiet: black hat, brass goggles, nothing shiny below the waist.</p>
<ul>
  <li><a href="https://steamgears.eu/go/491/">Gothic Overbust Corset — Faux Leather, Silver</a> — <strong>435,00 kr.</strong></li>
  <li><a href="https://steamgears.eu/go/483/">Time Traveler Top Hat with Goggles</a> — <strong>335,00 kr.</strong></li>
  <li><a href="https://steamgears.eu/go/477/">Copper Pilot Goggles — Vintage Windproof</a> — <strong>165,00 kr.</strong></li>
  <li><strong>Total: 935,00 kr.</strong></li>
</ul>
<p>It is the look for anyone who wants steampunk without brown leather.</p>
<h3>4. The Casual Tuesday</h3>
<p>A printed tee and the corset worn open as a layer, over jeans and boots. This is the outfit that survives a normal day and still photographs as steampunk at the end of it.</p>
<ul>
  <li><a href="https://steamgears.eu/go/557/">Airship Armada — Vintage Engraving Tee</a> — <strong>180,00 kr.</strong></li>
  <li><a href="https://steamgears.eu/go/489/">Steampunk Overbust Corset — Leather Lace-Up</a> — <strong>400,00 kr.</strong></li>
  <li><strong>Total: 580,00 kr.</strong></li>
</ul>
<p>If you own one steampunk piece, make it the corset; if you own two, add this tee.</p>
<h3>5. The Raven Widow</h3>
<p>Built around the Raven Chronometer print: black, brass and one bird. The corset keeps the shape and the goggles keep it from being a museum wall.</p>
<ul>
  <li><a href="https://steamgears.eu/go/967/">The Raven Chronometer — Herbarium Art Print</a> — <strong>395,00 kr.</strong></li>
  <li><a href="https://steamgears.eu/go/489/">Steampunk Overbust Corset — Leather Lace-Up</a> — <strong>400,00 kr.</strong></li>
  <li><a href="https://steamgears.eu/go/477/">Copper Pilot Goggles — Vintage Windproof</a> — <strong>165,00 kr.</strong></li>
  <li><strong>Total: 960,00 kr.</strong></li>
</ul>
<p>Hang the print behind you and the outfit has a background for the cost of a poster.</p>
<h3>6. The Winter Mechanic</h3>
<p>A hoodie and goggles. That is the whole look, and it is the one that gets worn from October to March because the printed layer is warm and the goggles fit over a beanie.</p>
<ul>
  <li><a href="https://steamgears.eu/go/399/">Clockwork Octopus — Curiosity Hoodie</a> — <strong>335,00 kr.</strong></li>
  <li><a href="https://steamgears.eu/go/477/">Copper Pilot Goggles — Vintage Windproof</a> — <strong>165,00 kr.</strong></li>
  <li><strong>Total: 500,00 kr.</strong></li>
</ul>
<p>It is also the cheapest way to test whether you like the aesthetic before spending on a coat.</p>
<h3>7. The Vampire Aristocrat</h3>
<p>Brocade tailcoat, silver corset, tall hat. Three pieces, all of them dramatic, which is exactly why it works for a ball or a Halloween party and nowhere in daylight.</p>
<ul>
  <li><a href="https://steamgears.eu/go/495/">Victorian Vampire Tailcoat — Brocade</a> — <strong>265,00 kr.</strong></li>
  <li><a href="https://steamgears.eu/go/491/">Gothic Overbust Corset — Faux Leather, Silver</a> — <strong>435,00 kr.</strong></li>
  <li><a href="https://steamgears.eu/go/483/">Time Traveler Top Hat with Goggles</a> — <strong>335,00 kr.</strong></li>
  <li><strong>Total: 1.035,00 kr.</strong></li>
</ul>
<p>Keep the shirt plain; the coat and the corset are already two statements.</p>
<h3>8. The Sky Navigator</h3>
<p>The astrolabe tee is the piece that makes this look: a navigation instrument printed on cotton, worn with the leather corset and goggles, reads as a uniform rather than a costume.</p>
<ul>
  <li><a href="https://steamgears.eu/go/1282/">Astrolabe Planisphere — Steampunk Tee</a> — <strong>530,00 kr.</strong></li>
  <li><a href="https://steamgears.eu/go/489/">Steampunk Overbust Corset — Leather Lace-Up</a> — <strong>400,00 kr.</strong></li>
  <li><a href="https://steamgears.eu/go/477/">Copper Pilot Goggles — Vintage Windproof</a> — <strong>165,00 kr.</strong></li>
  <li><strong>Total: 1.095,00 kr.</strong></li>
</ul>
<p>Add the pocket watch from the next look if you want one brass detail more.</p>
<h3>9. The Tarot Seer</h3>
<p>Two pieces and a lot of presence. The brass pocket watch pendant is the most detailed thing we sell, and against the wine velvet coat it does all the work — no corset, no hat, no goggles.</p>
<ul>
  <li><a href="https://steamgears.eu/go/940/">Clockwork Seer — Brass Pocket Watch Pendant</a> — <strong>865,00 kr.</strong></li>
  <li><a href="https://steamgears.eu/go/493/">Wine Red Velvet Tailcoat — Victorian Frock Coat</a> — <strong>435,00 kr.</strong></li>
  <li><strong>Total: 1.300,00 kr.</strong></li>
</ul>
<p>This is the look to wear when the invitation says cocktail, not convention.</p>
<h3>10. The Museum Curator</h3>
<p>The Clockwork Rose print, the brocade tailcoat and the tall hat: a person who catalogues things for a living and dresses like it.</p>
<ul>
  <li><a href="https://steamgears.eu/go/965/">Clockwork Rose — Herbarium Art Print</a> — <strong>395,00 kr.</strong></li>
  <li><a href="https://steamgears.eu/go/495/">Victorian Vampire Tailcoat — Brocade</a> — <strong>265,00 kr.</strong></li>
  <li><a href="https://steamgears.eu/go/483/">Time Traveler Top Hat with Goggles</a> — <strong>335,00 kr.</strong></li>
  <li><strong>Total: 995,00 kr.</strong></li>
</ul>
<p>It is the most "smart casual" of the twelve, and the one that works at a wedding with a sense of humour.</p>
<h3>11. The Automaton Engineer</h3>
<p>The Mechanical Elephant hoodie is the strangest print in the shop and the easiest to wear: brass lines on a dark ground, goggles on the head, done.</p>
<ul>
  <li><a href="https://steamgears.eu/go/403/">Mechanical Elephant — Curiosity Hoodie</a> — <strong>335,00 kr.</strong></li>
  <li><a href="https://steamgears.eu/go/477/">Copper Pilot Goggles — Vintage Windproof</a> — <strong>165,00 kr.</strong></li>
  <li><strong>Total: 500,00 kr.</strong></li>
</ul>
<p>Same price as the winter look, completely different character.</p>
<h3>12. The Octopus Navigator</h3>
<p>Curiosity hoodie plus the tall hat. The octopus reads as an engraving rather than a cartoon, which is why it survives being worn by an adult in daylight.</p>
<ul>
  <li><a href="https://steamgears.eu/go/399/">Clockwork Octopus — Curiosity Hoodie</a> — <strong>335,00 kr.</strong></li>
  <li><a href="https://steamgears.eu/go/483/">Time Traveler Top Hat with Goggles</a> — <strong>335,00 kr.</strong></li>
  <li><strong>Total: 670,00 kr.</strong></li>
</ul>
<p>Pair it with the goggles from look one and you have the full airship crew.</p>
<h2>All twelve at a glance</h2>
<table>
  <thead><tr><th>Look</th><th>Total</th><th>Best for</th></tr></thead>
  <tbody>
    <tr><td>The Winter Mechanic</td><td>500,00 kr.</td><td>winter, pre-con drinks</td></tr>
    <tr><td>The Automaton Engineer</td><td>500,00 kr.</td><td>the strangest print in the shop</td></tr>
    <tr><td>The Casual Tuesday</td><td>580,00 kr.</td><td>everyday wear, work, school</td></tr>
    <tr><td>The Octopus Navigator</td><td>670,00 kr.</td><td>airship crew</td></tr>
    <tr><td>The Airship Captain</td><td>765,00 kr.</td><td>first con, hero look</td></tr>
    <tr><td>The Gothic Aviator</td><td>935,00 kr.</td><td>goth-leaning steampunk</td></tr>
    <tr><td>The Raven Widow</td><td>960,00 kr.</td><td>photos with a background</td></tr>
    <tr><td>The Museum Curator</td><td>995,00 kr.</td><td>smart casual steampunk</td></tr>
    <tr><td>The Clockwork Duchess</td><td>1.000,00 kr.</td><td>full Victorian drama</td></tr>
    <tr><td>The Vampire Aristocrat</td><td>1.035,00 kr.</td><td>balls, Halloween</td></tr>
    <tr><td>The Sky Navigator</td><td>1.095,00 kr.</td><td>pirate and navigator vibes</td></tr>
    <tr><td>The Tarot Seer</td><td>1.300,00 kr.</td><td>cocktail, not convention</td></tr>
    <tr><td><strong>Range</strong></td><td><strong>500,00–1.300,00 kr.</strong></td><td>all twelve share pieces</td></tr>
  </tbody>
</table>
<h2>The five-piece rule</h2>
<ol>
  <li><strong>A corset or a corset belt</strong> — it sets the silhouette, and it is the piece that makes the rest look deliberate.</li>
  <li><strong>One styled layer</strong> — a tee, a hoodie or a tailcoat, never two.</li>
  <li><strong>Goggles</strong> — on the hat, around the neck or pushed up; they are the signal.</li>
  <li><strong>One brass accessory</strong> — a pocket watch, a pendant, the buttons on a coat. One, not five.</li>
  <li><strong>Shoes you can walk in</strong> — boots beat heels at every convention we have been to.</li>
</ol>
<h2>FAQ</h2>
<p><strong>How much does a complete steampunk outfit for women cost?</strong> Between <strong>500,00 kr.</strong> for a hoodie-and-goggles look and <strong>1.300,00 kr.</strong> for the full Victorian set, at the prices in our own shop. The tee-and-corset look at <strong>580,00 kr.</strong> is the cheapest one that still reads as steampunk from a distance.</p>
<p><strong>What should I buy first?</strong> The corset, if you want one piece that changes every outfit you already own; the goggles at <strong>165,00 kr.</strong> if you want the cheapest piece that makes a photo look steampunk.</p>
<p><strong>Can I wear this outside a convention?</strong> Yes — that is what the tee and hoodie looks are for. Printed cotton reads as a normal outfit; the goggles and the hat are what you take off at the train.</p>
<p><strong>Do the pieces work for plus-size women?</strong> The lace-up corsets are adjustable by design, and the hoodies and tees are printed on standard unisex cuts. The winter and automaton looks are the two easiest starting points because nothing is fitted.</p>
<p><strong>How do I keep it from looking like a costume?</strong> Three steampunk elements, everything else in muted colours — brown, black, olive, brass. One brass accessory reads as style; five read as fancy dress.</p>
<p><strong>Are the prices in kroner?</strong> Yes. The shop prices in DKK, and every amount on this page was read from the live catalogue on 9 November 2026. The product page wins if a price has changed.</p>
<h2>Where to go next</h2>
<p>Start at <a href="https://steamgears.eu/steampunk-costumes/">steampunk costumes</a> if you want the method rather than the looks, then read <a href="https://steamgears.eu/mens-steampunk-costume-essentials/">men's essentials</a> for the ten-piece build and <a href="https://steamgears.eu/steampunk-corset-guide/">the corset guide</a> before you buy a corset online.</p>
<p>[sg-shoprow]</p>
<p>[sg-buy]</p>
```

**FAQ-spørgsmålene i 699 (til Rank Math FAQ-blok, i denne rækkefølge):**

- How much does a complete steampunk outfit for women cost?
- What should I buy first?
- Can I wear this outside a convention?
- Do the pieces work for plus-size women?
- How do I keep it from looking like a costume?
- Are the prices in kroner?


### 2.2 EU-forsendelse — i dag findes metoden kun for Danmark

**Hvor:** WooCommerce → Indstillinger → Forsendelse. **Målt 8/11:** kun Denmark har en metode (fri fragt, intet minimum), **zone 0 har 0 metoder**, og resten af verdenen har ingen — en EU-kunde kan altså ikke gennemføre en ordre. **Kilde:** `docs/roadmap.md` §4d række B og §2 i `docs/projektstatus.md`.

| Trin | Handling | Værdi |
| ---: | --- | --- |
| 1 | Opret zone **Europe** med de lande du vil sælge til | `[EJER: hvilke EU-lande]` — og om Storbritannien og Norge skal med |
| 2 | Tilføj metoden **Flat rate** i zonen | `[EJER: fragtpris i kr. pr. ordre]` |
| 3 | Fri fragt over en grænse (valgfrit, men anbefalet) | `[EJER: beløb, fx 600]` |
| 4 | Beslut **Rest of world**: metode eller helt lukket | `[EJER: åbn/luk]` |
| 5 | Fragtklasser: kun hvis noget vejer mere end resten | `[EJER: ja/nej]` |
**Bevis:** læg en vare i kurven, skriv en tysk postkode i fragtberegneren, og se at der kommer en pris og ikke «No shipping options». **Kilde:** `docs/roadmap.md` §4d B.


### 2.3 Complianz: CVR og telefon

**Hvor:** Complianz → Wizard → **Organization**. **Målt 8/11:** telefonfeltet er tomt, og der findes intet CVR-felt i formularen; navn står som »Henning 6«, adresse »Lavgade 44a st th«, e-mail og DK er udfyldt. **Kilde:** `docs/roadmap.md` §4d række A.

| Felt | Værdi |
| --- | --- |
| Telephone number | `[EJER: telefonnummer med landekode]` |
| Company registration number (CVR) | `[EJER: CVR-nummer, 8 cifre]` |
| Company name — **kontrollér** | `[EJER: det registrerede firmanavn]` — »Henning 6« ser ud som en pladsholder og skal stå rigtigt i handelsbetingelser og fortrydelsesret |
**Bevis:** CVR og telefon står i footer/privacypolitik på den live side, og felterne er ikke tomme i wizarden. **Kilde:** `docs/roadmap.md` §4d A.


### 2.4 Betaling: tre Stripe-metoder og to manglende aftaler

**Hvor:** WooCommerce → Indstillinger → Betalinger (og Stripe Dashboard). **Målt 8/11:** `amazon_pay`, `klarna` og `link` er slået til i Woo men utilgængelige i Stripe; MobilePay og PayPal findes slet ikke. **Kilde:** `docs/roadmap.md` §4d række C og D.

| Trin | Handling | Værdi |
| ---: | --- | --- |
| 1 | Amazon Pay, Klarna, Link | Slå dem **fra i Woo**, eller slå dem **til i Stripe Dashboard** — `[EJER: fra eller til]` |
| 2 | MobilePay | `[EJER: aftale + nøgler]` — indsættes i WooCommerce → Betalinger → MobilePay |
| 3 | PayPal | `[EJER: aftale + nøgler]` |
**Bevis:** kassen viser kun metoder der faktisk kan gennemføre en betaling; en testordre pr. metode.


### 2.5 Fire kladder med pris og SKU

**Hvor:** Produkter → kladderne **942, 765, 766, 767**. **Målt 8/11:** alle fire har pris og SKU og venter på et ja/nej. **Kilde:** `docs/roadmap.md` §4d række F.

| Produkt-ID | Beslutning |
| --- | --- |
| 942 | `[EJER: publicér / slet]` |
| 765 | `[EJER: publicér / slet]` |
| 766 | `[EJER: publicér / slet]` |
| 767 | `[EJER: publicér / slet]` |
**Bevis:** listen over kladder er tom, eller de fire er publiceret med billede og alt-tekst.


## 3. Fase 2 — de fire indholdssider

**Kilde for alle fire:** de copyfiler `tools/guide_check.py` måler mod den levende butik. Samlet: **5 sider · 6324 ord · 15 unikke `/go/`-id'er · 0 døde · 0 valutaafvigelser** (målt 9/11).

**Fælles fremgangsmåde** (gælder 292, 296, 295 og 550):

1. Åbn posten/siden, **⋮ → Kodeeditor**, markér alt i indholdsområdet, sæt blokken ind, **Opdater**.
2. Rank Math: title, meta og focus keyword fra tabellen. FAQ-spørgsmålene i FAQ-blokken.
3. For 292/296/295: sæt status til **Publiceret** og slå kommentarer fra som på de øvrige guides.
4. GSC → URL-inspektion → **Request indexing** for hver af de fire.
5. Kontrollér at hub og guides linker begge veje — det står allerede i copyen, men tjek det i den visuelle editor.


### 3.1 Post 292

**Hvor:** Indlæg → 292 (kladde). **Kilde:** `docs/library/guides-2026-11-03.md` → «Guide 1 — Men's Steampunk Costume Essentials (post 292)».

| Felt | Værdi |
| --- | --- |
| ID | **292** (behold) |
| Slug | `mens-steampunk-costume-essentials` |
| Rank Math title (45 tegn) | `Men's Steampunk Costume: 10 Pieces, 3.370 kr.` |
| Meta description (142 tegn) | `Ten steampunk pieces that read as one outfit: goggles, top hat, two coats, two hoodies, two tees and two prints. 3.370,00 kr. in our own shop.` |
| Focus keyword | `steampunk costume men` |
| `/go/`-id'er | 10: 399, 413, 477, 483, 493, 495, 557, 954, 965, 967 |
| Totaler (valideret af guide_check) | `` |
| Omfang | 1359 ord · 8 FAQ |

**Indsætningsklar blok (292):**

```html
<h1>Men's Steampunk Costume Essentials: 10 Pieces That Read as One Outfit</h1>
<p>Ten pieces, 3.370,00 kr., and one rule: three of them carry the silhouette, the other seven only support it. Most men's steampunk costumes fail because the wearer buys twelve small accessories and no anchor piece — a necklace, a watch chain, a pin and a pair of gloves all shouting at once, with nothing holding them together.</p>
<p><strong>Quick answer:</strong> a men's steampunk costume needs three anchors — eyewear, headwear and an outer layer — and then two garments with a single print language, one print for the wall and two backups. In our shop that is <strong>3.370,00 kr.</strong> for all ten pieces, and the first three cost <strong>765,00 kr.</strong></p>
<h3>1. Eyewear is the anchor, not an accessory</h3>
<p><strong>In one line:</strong> goggles do more for the read than any other item, because they are the one thing no modern outfit contains.</p>
<p>Our <a href="https://steamgears.eu/go/477/">Copper Pilot Goggles — Vintage Windproof</a> cost <strong>165,00 kr.</strong> and are the piece to buy first. They sit on the hat, not over the eyes, for anything longer than a photo — a steampunk costume is worn at dinner, on a train, at a convention hall, and lenses you cannot see through end the evening early.</p>
<p><strong>What we used:</strong> 477 Copper Pilot Goggles — Vintage Windproof, 165,00 kr. Copper-toned frames, windproof lens, adjustable strap that fits over a hat band.</p>
<p>The cheaper mistake is the second pair. If you already own dark glasses, do not buy vintage driving goggles "for variety" — put the money into the coat instead. The goggles are the signal; the coat is what makes the photograph look expensive.</p>
<h3>2. Headwear sets the decade</h3>
<p><strong>In one line:</strong> a tall hat says 1890, a bare head says cosplay.</p>
<p>The <a href="https://steamgears.eu/go/483/">Time Traveler Top Hat with Goggles</a> is <strong>335,00 kr.</strong>, and the removable goggle strap is the reason to choose it over a plain top hat: the hat works without the goggles for a dinner, and the goggles work on their own for a daytime look.</p>
<p><strong>What we used:</strong> 483 Time Traveler Top Hat with Goggles, 335,00 kr. Felt body, grosgrain band, goggles on a clip strap.</p>
<p>It is the tallest silhouette in the catalogue, and it is the piece that makes the costume legible from thirty metres. Do not buy a second hat "for variety" before you own a coat.</p>
<h3>3. The outer layer decides whether it reads as clothing or as a costume</h3>
<p><strong>In one line:</strong> a coat with a collar does more than any prop, and it is the only piece strangers will compliment.</p>
<p>Two options, and they are different characters:</p>
<p>more theatrical of the two, and the cheapest way into a real outer layer.</p>
<ul>
  <li><a href="https://steamgears.eu/go/495/">Victorian Vampire Tailcoat — Brocade</a> — <strong>265,00 kr.</strong> Brocade, tail, the</li>
</ul>
<p>formal option, the one to wear if the event has a dress code.</p>
<ul>
  <li><a href="https://steamgears.eu/go/493/">Wine Red Velvet Tailcoat — Victorian Frock Coat</a> — <strong>435,00 kr.</strong> Velvet, the</li>
</ul>
<p><strong>What we used:</strong> 495 for the daytime look and 493 for the evening, and never both at once. A tailcoat over a hoodie is a working outfit; two coats is a wardrobe.</p>
<h3>4. Print language: pick one, repeat it twice</h3>
<p><strong>In one line:</strong> two garments from the same print family look intentional; two garments from different families look like shopping.</p>
<p>The <a href="https://steamgears.eu/go/413/">Airship Storm — Vintage Engraving Hoodie</a> at <strong>335,00 kr.</strong> and the <a href="https://steamgears.eu/go/557/">Airship Armada — Vintage Engraving Tee</a> at <strong>180,00 kr.</strong> both use the engraved line work of the prints below. Worn together they look like a set; worn with a neon graphic tee they look like laundry day.</p>
<p><strong>What we used:</strong> 413 hoodie over 557 tee, sleeves pushed up, coat open. For a second day, swap the hoodie for the <a href="https://steamgears.eu/go/399/">Clockwork Octopus — Curiosity Hoodie</a>, also 335,00 kr.</p>
<h3>5. Two tees, not five</h3>
<p><strong>In one line:</strong> tees are the cheapest way to make the costume survive a weekend, and the easiest place to overspend.</p>
<ul>
  <li><a href="https://steamgears.eu/go/557/">Airship Armada — Vintage Engraving Tee</a> — 180,00 kr.</li>
  <li><a href="https://steamgears.eu/go/954/">Brass Luna Moth — Clockwork Herbarium Tee</a> — 530,00 kr.</li>
</ul>
<p>The second tee is the expensive one, and that is deliberate: it is also the piece you will wear on a Tuesday with jeans, so it earns its price outside the costume too.</p>
<h3>6. The wall print is part of the costume</h3>
<p><strong>In one line:</strong> a framed print behind the photo is what turns a mirror selfie into a scene.</p>
<ul>
  <li><a href="https://steamgears.eu/go/965/">Clockwork Rose — Herbarium Art Print</a> — 395,00 kr.</li>
  <li><a href="https://steamgears.eu/go/967/">The Raven Chronometer — Herbarium Art Print</a> — 395,00 kr.</li>
</ul>
<h3>Complete this look — three characters, one wardrobe</h3>
<table>
  <thead><tr><th>Character</th><th>Anchors</th><th>Garment</th><th>Total</th></tr></thead>
  <tbody>
    <tr><td>The airship officer</td><td>477 goggles, 483 hat, 495 tailcoat</td><td>557 tee</td><td>945,00 kr.</td></tr>
    <tr><td>The scholar</td><td>477 goggles, 483 hat, 493 tailcoat</td><td>413 hoodie</td><td>1.270,00 kr.</td></tr>
    <tr><td>The collector</td><td>483 hat, 495 tailcoat</td><td>399 hoodie, 954 tee</td><td>1.465,00 kr.</td></tr>
  </tbody>
</table>
<h3>Budget table — all ten pieces</h3>
<table>
  <thead><tr><th>#</th><th>Piece</th><th>Price</th></tr></thead>
  <tbody>
    <tr><td>1</td><td>Copper Pilot Goggles — Vintage Windproof</td><td>165,00 kr.</td></tr>
    <tr><td>2</td><td>Time Traveler Top Hat with Goggles</td><td>335,00 kr.</td></tr>
    <tr><td>3</td><td>Victorian Vampire Tailcoat — Brocade</td><td>265,00 kr.</td></tr>
    <tr><td>4</td><td>Wine Red Velvet Tailcoat — Victorian Frock Coat</td><td>435,00 kr.</td></tr>
    <tr><td>5</td><td>Airship Storm — Vintage Engraving Hoodie</td><td>335,00 kr.</td></tr>
    <tr><td>6</td><td>Clockwork Octopus — Curiosity Hoodie</td><td>335,00 kr.</td></tr>
    <tr><td>7</td><td>Airship Armada — Vintage Engraving Tee</td><td>180,00 kr.</td></tr>
    <tr><td>8</td><td>Brass Luna Moth — Clockwork Herbarium Tee</td><td>530,00 kr.</td></tr>
    <tr><td>9</td><td>Clockwork Rose — Herbarium Art Print</td><td>395,00 kr.</td></tr>
    <tr><td>10</td><td>The Raven Chronometer — Herbarium Art Print</td><td>395,00 kr.</td></tr>
    <tr><td></td><td><strong>Total</strong></td><td><strong>3.370,00 kr.</strong></td></tr>
  </tbody>
</table>
<h3>FAQ</h3>
<p><strong>What is the minimum for a men's steampunk costume?</strong> Three pieces: goggles at 165,00 kr., a top hat at 335,00 kr. and a tailcoat at 265,00 kr. — 765,00 kr. Everything else is an upgrade, not a requirement.</p>
<p><strong>Do I need a waistcoat?</strong> No. The coat does the work a waistcoat does, and a waistcoat under a tailcoat adds bulk where the silhouette needs to stay long.</p>
<p><strong>Can I wear this outside a convention?</strong> Yes, and that is the point of the print pieces. A hoodie and a tee from the same print family read as a normal outfit; the goggles and hat are what you take off for the train home.</p>
<p><strong>Which piece should I buy first?</strong> Goggles. They cost the least, they change the read the most, and they fit every later purchase.</p>
<p><strong>Is a top hat too much?</strong> Only if you wear it indoors. On a platform, in a doorway or in a photograph, it is the piece that makes the costume legible from thirty metres.</p>
<p><strong>What sizes do the garments come in?</strong> The hoodies and tees listed here are unisex; check the size chart on the product page before ordering, and remember that a hoodie worn under a coat should be the same size you normally wear, not one up.</p>
<p><strong>Why are there no dollar prices any more?</strong> Because the shop prices in Danish kroner. Every amount here was read in the store on 9 November 2026; if a price has moved since, the product page wins.</p>
<p><strong>Where do I start if I want the whole shop?</strong> <a href="https://steamgears.eu/start-here/">Start here</a> lists the entry points by intent, and the <a href="https://steamgears.eu/steampunk-costumes/">steampunk costumes hub</a> groups the capsules.</p>
<h3>Read next — the four-guide cluster</h3>
<p><strong>In one line:</strong> these four guides share one wardrobe, so each links the others instead of repeating them.</p>
<ul>
  <li><a href="https://steamgears.eu/steampunk-clothing-three-budgets/">Steampunk clothing at three budgets</a> — what the three tiers actually buy, and which pieces survive the event.</li>
  <li><a href="https://steamgears.eu/womens-steampunk-essentials/">Women's steampunk essentials</a> — the same ten-piece method with corset, coat and hat.</li>
  <li><a href="https://steamgears.eu/steampunk-corset-guide/">Steampunk corset guide</a> — choosing by structure, and what to wear under and over it.</li>
</ul>
<p>The hub, <a href="https://steamgears.eu/steampunk-costumes/">steampunk costumes</a>, lists the capsules all four belong to.</p>
```

**FAQ-spørgsmål (292):** What is the minimum for a men's steampunk costume? · Do I need a waistcoat? · Can I wear this outside a convention? · Which piece should I buy first? · Is a top hat too much? · What sizes do the garments come in? · Why are there no dollar prices any more? · Where do I start if I want the whole shop?


### 3.2 Post 296

**Hvor:** Indlæg → 296 (kladde). **Kilde:** `docs/library/guides-2026-11-03.md` → «Guide 2 — Steampunk Clothing at Three Budgets (post 296)».

| Felt | Værdi |
| --- | --- |
| ID | **296** (behold) |
| Slug | `steampunk-clothing-three-budgets` |
| Rank Math title (58 tegn) | `Steampunk Clothing at Three Budgets: 560, 1.075, 2.010 kr.` |
| Meta description (143 tegn) | `Three steampunk outfits at 560,00, 1.075,00 and 2.010,00 kr., built from live shop prices in DKK: what each tier buys, and what it still lacks.` |
| Focus keyword | `steampunk clothing` |
| `/go/`-id'er | 7: 399, 413, 477, 483, 495, 557, 965 |
| Totaler (valideret af guide_check) | `` |
| Omfang | 1103 ord · 7 FAQ |

**Indsætningsklar blok (296):**

```html
<h1>Steampunk Clothing at Three Budgets: What 560, 1.075 and 2.010 kr. Actually Buy</h1>
<p>Most "steampunk on a budget" articles quote tiers and then never name a product. Ours uses the shop's own prices, read in the store on 9 November 2026, so every number below can be checked against a product page.</p>
<p><strong>Quick answer:</strong> 560,00 kr. gets you goggles and a print. 1.075,00 kr. adds a top hat and a tee. 2.010,00 kr. adds a tailcoat and two hoodies. Past that you are buying duplicates, not a better costume.</p>
<p>If you want the ten-piece version with the product-by-product reasoning, our <a href="https://steamgears.eu/mens-steampunk-costume-essentials/">men's steampunk costume essentials</a> walks the same catalogue one piece at a time, and the <a href="https://steamgears.eu/steampunk-corset-guide/">steampunk corset guide</a> answers the question every tier starts with for a fitted silhouette.</p>
<h3>The first tier — read the costume, don't own it</h3>
<p><strong>In one line:</strong> two pieces prove the look before you spend on a wardrobe.</p>
<ul>
  <li><a href="https://steamgears.eu/go/477/">Copper Pilot Goggles — Vintage Windproof</a> — 165,00 kr.</li>
  <li><a href="https://steamgears.eu/go/965/">Clockwork Rose — Herbarium Art Print</a> — 395,00 kr.</li>
  <li><strong>Total: 560,00 kr.</strong></li>
</ul>
<p><strong>What we used:</strong> the goggles on a hat you already own, the print behind the photo. This tier is for the person who wants to know whether they enjoy the look before committing a coat's worth of money to it.</p>
<p><strong>What it does not buy:</strong> a silhouette. Without a coat or a hoodie, the outfit is "person with goggles". That is fine for a party photo and not fine for a convention.</p>
<h3>The second tier — a costume you can wear for a day</h3>
<p><strong>In one line:</strong> the top hat and a print tee turn two accessories into an outfit.</p>
<p>Add:</p>
<ul>
  <li><a href="https://steamgears.eu/go/483/">Time Traveler Top Hat with Goggles</a> — 335,00 kr.</li>
  <li><a href="https://steamgears.eu/go/557/">Airship Armada — Vintage Engraving Tee</a> — 180,00 kr.</li>
  <li><strong>Total: 1.075,00 kr.</strong></li>
</ul>
<p><strong>What we used:</strong> all four pieces at once, goggles clipped to the hat band, tee tucked, boots from the existing wardrobe. The tee is the piece that stops the outfit from looking like a costume rental: it is a garment someone chose, not a prop.</p>
<p><strong>What it does not buy:</strong> an outer layer. A cold evening or a photograph in shade still wants a coat.</p>
<h3>The third tier — the whole character</h3>
<p><strong>In one line:</strong> the tailcoat and two hoodies are what make strangers ask where the outfit is from.</p>
<p>Add:</p>
<ul>
  <li><a href="https://steamgears.eu/go/495/">Victorian Vampire Tailcoat — Brocade</a> — 265,00 kr.</li>
  <li><a href="https://steamgears.eu/go/413/">Airship Storm — Vintage Engraving Hoodie</a> — 335,00 kr.</li>
  <li><a href="https://steamgears.eu/go/399/">Clockwork Octopus — Curiosity Hoodie</a> — 335,00 kr.</li>
  <li><strong>Total: 2.010,00 kr.</strong></li>
</ul>
<p><strong>What we used:</strong> the tailcoat over the Airship Storm hoodie for the daytime look, the Clockwork Octopus hoodie under the same coat for the evening, the tee underneath both. Two hoodies is not greed at this tier — it is how one costume survives a two-day event without washing.</p>
<p><strong>What it does not buy:</strong> a better costume. The jump from 1.075,00 to 2.010,00 kr. is breadth, not quality; the goggles at 165,00 kr. are exactly the same goggles at any tier.</p>
<h3>How the three tiers were set</h3>
<p>They are not marketing brackets. They follow the order the pieces matter in: eyewear first, then headwear, then a garment, then an outer layer. Each tier adds exactly one of those steps and no tier skips one — which is why the jump from the first to the second tier buys a hat <em>and</em> a tee rather than a coat. If you already own a coat, spend the second tier's money on goggles and the hat and stop there; the <a href="https://steamgears.eu/steampunk-costumes/">steampunk costumes hub</a> lists the other capsules if you want a different character.</p>
<p>One more thing worth knowing before you choose: the tiers are cumulative, not alternatives. Buying the top tier does not replace the first purchase, it includes it — so nothing is wasted if you start small and add a layer a month. The only irreversibly wasted money in this catalogue is a second pair of goggles bought for variety.</p>
<h3>Budget table</h3>
<table>
  <thead><tr><th>Tier</th><th>Pieces</th><th>What it contains</th><th>Total</th></tr></thead>
  <tbody>
    <tr><td>Read it</td><td>2</td><td>goggles, print</td><td>560,00 kr.</td></tr>
    <tr><td>Wear it</td><td>4</td><td>+ top hat, tee</td><td>1.075,00 kr.</td></tr>
    <tr><td>Own it</td><td>7</td><td>+ tailcoat, two hoodies</td><td>2.010,00 kr.</td></tr>
  </tbody>
</table>
<h3>FAQ</h3>
<p><strong>Is 560 kr. enough for a steampunk costume?</strong> Yes, for goggles and a print — 560,00 kr. in this shop. It is enough to photograph and to decide whether you like the look; it is not enough to walk into a convention and be recognised as a character.</p>
<p><strong>Why does the second tier cost almost double the first?</strong> Because it adds the two pieces that carry the silhouette: a top hat at 335,00 kr. and a garment at 180,00 kr. The first tier is two accessories; the second is an outfit.</p>
<p><strong>Which single piece gives the most costume per krone?</strong> Goggles at 165,00 kr. They are the only item no modern outfit contains, so they change the read of clothes you already own.</p>
<p><strong>Can I mix the tiers?</strong> That is the sensible way to do it: buy the first tier, wear it once, then add the coat rather than the hat if you live somewhere cold.</p>
<p><strong>Do prices include shipping?</strong> No. Shipping is calculated at checkout before you confirm the order, so the totals above are the clothing only.</p>
<p><strong>Will these pieces work after Halloween?</strong> The hoodies and tees do; the goggles and hat are the costume part. That split is deliberate in the tiers: the more you spend, the more of the outfit stays in the wardrobe after the event.</p>
<p><strong>Where do the prices come from?</strong> From our own product pages on 9 November 2026, in DKK. If a price has moved since, the product page wins.</p>
<h3>Read next — the four-guide cluster</h3>
<p><strong>In one line:</strong> these four guides share one wardrobe, so each links the others instead of repeating them.</p>
<ul>
  <li><a href="https://steamgears.eu/mens-steampunk-costume-essentials/">Men's steampunk costume essentials</a> — ten pieces that read as one outfit, from goggles to print.</li>
  <li><a href="https://steamgears.eu/womens-steampunk-essentials/">Women's steampunk essentials</a> — the same ten-piece method with corset, coat and hat.</li>
  <li><a href="https://steamgears.eu/steampunk-corset-guide/">Steampunk corset guide</a> — choosing by structure, and what to wear under and over it.</li>
</ul>
<p>The hub, <a href="https://steamgears.eu/steampunk-costumes/">steampunk costumes</a>, lists the capsules all four belong to.</p>
```

**FAQ-spørgsmål (296):** Is 560 kr. enough for a steampunk costume? · Why does the second tier cost almost double the first? · Which single piece gives the most costume per krone? · Can I mix the tiers? · Do prices include shipping? · Will these pieces work after Halloween? · Where do the prices come from?


### 3.3 Post 295

**Hvor:** Indlæg → 295 (kladde). **Kilde:** `docs/library/guides-2026-11-03.md` → «Guide 3 — Women's Steampunk Essentials (post 295)».

| Felt | Værdi |
| --- | --- |
| ID | **295** (behold) |
| Slug | `womens-steampunk-essentials` |
| Rank Math title (46 tegn) | `Women's Steampunk Essentials: 10 Pieces in DKK` |
| Meta description (154 tegn) | `Ten women's steampunk pieces priced in DKK: two corsets, goggles, a top hat, two tailcoats, a hoodie, a tee, a print and a pendant. 4.160,00 kr. in total.` |
| Focus keyword | `women's steampunk clothing` |
| `/go/`-id'er | 10: 413, 477, 483, 489, 491, 493, 495, 940, 954, 965 |
| Totaler (valideret af guide_check) | `` |
| Omfang | 1096 ord · 7 FAQ |

**Indsætningsklar blok (295):**

```html
<h1>Women's Steampunk Essentials: 10 Pieces, Priced in Kroner</h1>
<p>This is the companion to our men's essentials, and it is built the other way round: instead of starting with outerwear, it starts with the piece that sets the silhouette — the corset — and builds outward. Ten pieces, <strong>4.160,00 kr.</strong> in total, with a complete outfit at <strong>1.695,00 kr.</strong></p>
<p><strong>Quick answer:</strong> one corset, one pair of goggles, one hat, one tailcoat and one printed layer is a complete women's steampunk costume for <strong>1.695,00 kr.</strong> (489 corset, 477 goggles, 483 hat, 495 tailcoat, 954 tee). The other five pieces are variations, not requirements.</p>
<h3>1. The corset is the silhouette</h3>
<p><strong>In one line:</strong> choose the corset first; everything else is fitted to it.</p>
<p>option: overbust, leather, the piece that reads "character" in a photo.</p>
<ul>
  <li><a href="https://steamgears.eu/go/489/">Steampunk Overbust Corset — Leather Lace-Up</a> — <strong>400,00 kr.</strong> The structured</li>
</ul>
<p>option: hardware, silver tone, the most jewellery-adjacent of the two.</p>
<ul>
  <li><a href="https://steamgears.eu/go/491/">Gothic Overbust Corset — Faux Leather, Silver</a> — <strong>435,00 kr.</strong> The evening</li>
</ul>
<p><strong>What we used:</strong> 489 for a day look with a hoodie over it, 491 for the evening with nothing over it. Both are sized by the chart on their own product page — a corset is fitted to the ribcage and the waist, not to the shoulder, so read that chart rather than ordering your usual clothing size.</p>
<h3>2. Goggles read the same on everyone</h3>
<p><strong>In one line:</strong> the cheapest piece in the outfit does the most work, and it is unisex.</p>
<p><a href="https://steamgears.eu/go/477/">Copper Pilot Goggles — Vintage Windproof</a> — <strong>165,00 kr.</strong> Worn on the hat, not over the eyes, for anything longer than a photograph.</p>
<h3>3. One hat, one decade</h3>
<p><strong>In one line:</strong> the tall hat is the 1890s; pick the decade, then the hat.</p>
<p><a href="https://steamgears.eu/go/483/">Time Traveler Top Hat with Goggles</a> — <strong>335,00 kr.</strong> It is the tallest silhouette in the catalogue, and the removable goggle strap means the hat works on its own at a dinner.</p>
<h3>4. Outer layer: two tailcoats, two registers</h3>
<p><strong>In one line:</strong> a coat with a collar is what makes the outfit survive a cold evening and a photograph.</p>
<p>works over a corset without hiding it.</p>
<ul>
  <li><a href="https://steamgears.eu/go/495/">Victorian Vampire Tailcoat — Brocade</a> — <strong>265,00 kr.</strong> Brocade and a tail,</li>
</ul>
<p>formal piece in the catalogue; the one to wear if the event has a dress code.</p>
<ul>
  <li><a href="https://steamgears.eu/go/493/">Wine Red Velvet Tailcoat — Victorian Frock Coat</a> — <strong>435,00 kr.</strong> The most</li>
</ul>
<h3>5. The printed layer</h3>
<ul>
  <li><a href="https://steamgears.eu/go/413/">Airship Storm — Vintage Engraving Hoodie</a> — <strong>335,00 kr.</strong></li>
  <li><a href="https://steamgears.eu/go/954/">Brass Luna Moth — Clockwork Herbarium Tee</a> — <strong>530,00 kr.</strong></li>
</ul>
<p><strong>In one line:</strong> the hoodie and the tee are what keep the outfit wearable after the event, and they are the only two pieces here that read as ordinary clothing on a Tuesday.</p>
<h3>6. Print and metal, for the wall and the wrist</h3>
<ul>
  <li><a href="https://steamgears.eu/go/965/">Clockwork Rose — Herbarium Art Print</a> — <strong>395,00 kr.</strong></li>
  <li><a href="https://steamgears.eu/go/940/">Clockwork Seer — Brass Pocket Watch Pendant</a> — <strong>865,00 kr.</strong></li>
</ul>
<p>The pendant is the one piece in this wardrobe that is jewellery rather than costume: it finishes a corset look without adding another layer.</p>
<h3>Complete this look — two characters</h3>
<table>
  <thead><tr><th>Character</th><th>Pieces</th><th>Total</th></tr></thead>
  <tbody>
    <tr><td>The apothecary</td><td>489 corset, 477 goggles, 483 hat, 495 tailcoat, 954 tee</td><td>1.695,00 kr.</td></tr>
    <tr><td>The captain</td><td>491 corset, 477 goggles, 483 hat, 493 tailcoat, 413 hoodie</td><td>1.705,00 kr.</td></tr>
  </tbody>
</table>
<h3>Budget table — all ten pieces</h3>
<table>
  <thead><tr><th>#</th><th>Piece</th><th>Price</th></tr></thead>
  <tbody>
    <tr><td>1</td><td>Steampunk Overbust Corset — Leather Lace-Up</td><td>400,00 kr.</td></tr>
    <tr><td>2</td><td>Gothic Overbust Corset — Faux Leather, Silver</td><td>435,00 kr.</td></tr>
    <tr><td>3</td><td>Copper Pilot Goggles — Vintage Windproof</td><td>165,00 kr.</td></tr>
    <tr><td>4</td><td>Time Traveler Top Hat with Goggles</td><td>335,00 kr.</td></tr>
    <tr><td>5</td><td>Victorian Vampire Tailcoat — Brocade</td><td>265,00 kr.</td></tr>
    <tr><td>6</td><td>Wine Red Velvet Tailcoat — Victorian Frock Coat</td><td>435,00 kr.</td></tr>
    <tr><td>7</td><td>Airship Storm — Vintage Engraving Hoodie</td><td>335,00 kr.</td></tr>
    <tr><td>8</td><td>Brass Luna Moth — Clockwork Herbarium Tee</td><td>530,00 kr.</td></tr>
    <tr><td>9</td><td>Clockwork Rose — Herbarium Art Print</td><td>395,00 kr.</td></tr>
    <tr><td>10</td><td>Clockwork Seer — Brass Pocket Watch Pendant</td><td>865,00 kr.</td></tr>
    <tr><td></td><td><strong>Wardrobe total (all ten)</strong></td><td><strong>4.160,00 kr.</strong></td></tr>
    <tr><td></td><td><strong>Minimum outfit (1, 3, 4, 5, 8)</strong></td><td><strong>1.695,00 kr.</strong></td></tr>
  </tbody>
</table>
<h3>FAQ</h3>
<p><strong>What is the cheapest complete women's steampunk costume here?</strong> 1.695,00 kr.: the leather overbust corset at 400,00 kr., goggles at 165,00 kr., the top hat at 335,00 kr., the brocade tailcoat at 265,00 kr. and the herbarium tee at 530,00 kr.</p>
<p><strong>Do I need a corset to look steampunk?</strong> No, but it is the fastest way to change a silhouette. A hoodie over a structured overbust reads steampunk without looking like a costume rental.</p>
<p><strong>Are the corsets sized for everyday wear?</strong> Check the size chart on each product page; the two overbusts sit differently — the leather one is the more forgiving, the silver one the most structured. If you are between sizes, take the larger: lacing can tighten, it cannot create room.</p>
<p><strong>Can I wear a tailcoat over a corset without it bunching?</strong> Yes — the brocade tailcoat is cut straight, which is why it works over a structured top. A tailored coat would not.</p>
<p><strong>Will the silver corset work with the brass goggles?</strong> Yes, and that mix is deliberate: brass and silver in one outfit is period-correct for a workshop look, less so for a formal one.</p>
<p><strong>What about shoes and hair?</strong> Out of scope here on purpose — the ten pieces above are what the shop sells, and boots plus a low bun or a braid do the rest.</p>
<p><strong>Where should I start if I already own a corset?</strong> With <a href="https://steamgears.eu/go/477/">goggles</a> and the <a href="https://steamgears.eu/steampunk-costumes/">steampunk costumes hub</a>, then add the tailcoat. The <a href="https://steamgears.eu/steampunk-corset-guide/">steampunk corset guide</a> has the structure-first version of this decision.</p>
<h3>Read next — the four-guide cluster</h3>
<p><strong>In one line:</strong> these four guides share one wardrobe, so each links the others instead of repeating them.</p>
<ul>
  <li><a href="https://steamgears.eu/mens-steampunk-costume-essentials/">Men's steampunk costume essentials</a> — ten pieces that read as one outfit, from goggles to print.</li>
  <li><a href="https://steamgears.eu/steampunk-clothing-three-budgets/">Steampunk clothing at three budgets</a> — what the three tiers actually buy, and which pieces survive the event.</li>
  <li><a href="https://steamgears.eu/steampunk-corset-guide/">Steampunk corset guide</a> — choosing by structure, and what to wear under and over it.</li>
</ul>
<p>The hub, <a href="https://steamgears.eu/steampunk-costumes/">steampunk costumes</a>, lists the capsules all four belong to.</p>
```

**FAQ-spørgsmål (295):** What is the cheapest complete women's steampunk costume here? · Do I need a corset to look steampunk? · Are the corsets sized for everyday wear? · Can I wear a tailcoat over a corset without it bunching? · Will the silver corset work with the brass goggles? · What about shoes and hair? · Where should I start if I already own a corset?


### 3.4 Side 550 — hub

**Hvor:** Sider → 550 «Steampunk Costumes». **Kilde:** `docs/library/hub-steampunk-costumes-2026-11-03.md` → «Hub-copy: /steampunk-costumes/ som indgang til de fire guides».

Siden er publiceret men står i GSC som *Discovered — currently not indexed*. Copyen gør den til svaret på «hvor begynder jeg» og kobler de fire guides.

| Felt | Værdi |
| --- | --- |
| ID | **550** (behold) |
| Slug | `/steampunk-costumes/ (side, ingen slug-felt i filen)` |
| Rank Math title (56 tegn) | `Steampunk Costumes: Start With One Piece, Build the Rest` |
| Meta description (153 tegn) | `How to build a steampunk costume from one piece upward: goggles, hat and tailcoat first, then corset and layer. Four guides, prices in DKK from our shop.` |
| Focus keyword | `steampunk costumes` |
| `/go/`-id'er | 10: 399, 413, 477, 483, 489, 491, 493, 495, 557, 954 |
| Totaler (valideret af guide_check) | `` |
| Omfang | 968 ord · 7 FAQ |

**Indsætningsklar blok (550):**

```html
<h1>Steampunk Costumes: Start With One Piece, Build the Rest</h1>
<p>Most people build a steampunk costume backwards: they buy the biggest, cheapest bundle first, wear it once, and find that nothing fits together. It works better the other way. One piece that changes how you are seen, then the silhouette around it, then the layer you will still wear in February.</p>
<p><strong>Quick answer:</strong> start with goggles (<strong>165,00 kr.</strong>). They cost the least, they change the read the most, and they fit every purchase you make afterwards. Add a top hat and a tailcoat and you have a complete costume for <strong>765,00 kr.</strong> — everything else on this page is an upgrade of that, not a requirement.</p>
<h3>1. The piece people notice</h3>
<p><strong>In one line:</strong> the head and the eyes decide whether a stranger reads “costume” or “person in a coat”.</p>
<ul>
  <li><a href="https://steamgears.eu/go/477/">Copper Pilot Goggles — Vintage Windproof</a> — <strong>165,00 kr.</strong></li>
  <li><a href="https://steamgears.eu/go/483/">Time Traveler Top Hat with Goggles</a> — <strong>335,00 kr.</strong></li>
</ul>
<p><strong>What we used:</strong> goggles on the hat band for photographs, goggles over the eyes only for the ten minutes someone is actually looking. The tall hat reads salon; a bare head reads cosplay.</p>
<h3>2. The silhouette</h3>
<p><strong>In one line:</strong> the coat does more for the shape than any accessory, and the corset does more than the coat.</p>
<p>layer under it stays visible.</p>
<ul>
  <li><a href="https://steamgears.eu/go/495/">Victorian Vampire Tailcoat — Brocade</a> — <strong>265,00 kr.</strong>, cut straight so the</li>
</ul>
<p>evening option.</p>
<ul>
  <li><a href="https://steamgears.eu/go/493/">Wine Red Velvet Tailcoat — Victorian Frock Coat</a> — <strong>435,00 kr.</strong>, the formal one.</li>
  <li><a href="https://steamgears.eu/go/489/">Steampunk Overbust Corset — Leather Lace-Up</a> — <strong>400,00 kr.</strong>, the structured</li>
</ul>
<ul>
  <li><a href="https://steamgears.eu/go/491/">Gothic Overbust Corset — Faux Leather, Silver</a> — <strong>435,00 kr.</strong>, the hardware one.</li>
</ul>
<p><strong>What we used:</strong> coat open, always. Closed, it hides the piece you paid for.</p>
<h3>3. The layer you keep wearing</h3>
<p><strong>In one line:</strong> buy the printed layer last, because it is the only part that survives the event.</p>
<ul>
  <li><a href="https://steamgears.eu/go/413/">Airship Storm — Vintage Engraving Hoodie</a> — <strong>335,00 kr.</strong></li>
  <li><a href="https://steamgears.eu/go/399/">Clockwork Octopus — Curiosity Hoodie</a> — <strong>335,00 kr.</strong></li>
  <li><a href="https://steamgears.eu/go/557/">Airship Armada — Vintage Engraving Tee</a> — <strong>180,00 kr.</strong></li>
  <li><a href="https://steamgears.eu/go/954/">Brass Luna Moth — Clockwork Herbarium Tee</a> — <strong>530,00 kr.</strong></li>
</ul>
<h3>4. Three characters, one wardrobe</h3>
<table>
  <thead><tr><th>Character</th><th>Anchors</th><th>Garment</th><th>Total</th></tr></thead>
  <tbody>
    <tr><td>The airship officer</td><td><a href="https://steamgears.eu/go/477/">goggles</a>, <a href="https://steamgears.eu/go/483/">hat</a>, <a href="https://steamgears.eu/go/495/">tailcoat</a></td><td><a href="https://steamgears.eu/go/557/">tee</a></td><td>945,00 kr.</td></tr>
    <tr><td>The scholar</td><td><a href="https://steamgears.eu/go/477/">goggles</a>, <a href="https://steamgears.eu/go/483/">hat</a>, <a href="https://steamgears.eu/go/493/">velvet tailcoat</a></td><td><a href="https://steamgears.eu/go/413/">hoodie</a></td><td>1.270,00 kr.</td></tr>
    <tr><td>The collector</td><td><a href="https://steamgears.eu/go/483/">hat</a>, <a href="https://steamgears.eu/go/495/">tailcoat</a></td><td><a href="https://steamgears.eu/go/399/">hoodie</a>, <a href="https://steamgears.eu/go/954/">tee</a></td><td>1.465,00 kr.</td></tr>
  </tbody>
</table>
<p>The three characters share five of their seven pieces. That is the point of buying this way: the second costume costs one garment, not a whole new outfit.</p>
<h3>5. The four guides behind this page</h3>
<p><strong>In one line:</strong> each guide takes one question all the way down; this page only decides which one you need.</p>
<p>that read as one outfit, with the budget table and the three-character breakdown in full.</p>
<ul>
  <li><a href="https://steamgears.eu/mens-steampunk-costume-essentials/">Men's steampunk costume essentials</a> — ten pieces</li>
</ul>
<p>tiers actually buy, and which pieces survive the event.</p>
<ul>
  <li><a href="https://steamgears.eu/steampunk-clothing-three-budgets/">Steampunk clothing at three budgets</a> — what the three</li>
</ul>
<p>corset, coat and hat instead of hoodie and tee.</p>
<ul>
  <li><a href="https://steamgears.eu/womens-steampunk-essentials/">Women's steampunk essentials</a> — the same method with</li>
</ul>
<p>what to wear under and over it.</p>
<ul>
  <li><a href="https://steamgears.eu/steampunk-corset-guide/">Steampunk corset guide</a> — choosing a corset by structure, and</li>
</ul>
<h3>6. Where to go next</h3>
<p>is fixed and you need a character rather than a wardrobe.</p>
<ul>
  <li><a href="https://steamgears.eu/steampunk-halloween-costume-women/">Steampunk Halloween costumes for women</a> if the date</li>
</ul>
<p>version first.</p>
<ul>
  <li><a href="https://steamgears.eu/steampunk-goggles/">Steampunk goggles</a> if you only want the one piece.</li>
  <li><a href="https://steamgears.eu/start-here/">Start here</a> if you have no idea what steampunk is and want the short</li>
</ul>
<ul>
  <li><a href="https://steamgears.eu/shop/">Shop</a> for everything in one list.</li>
</ul>
<h3>FAQ</h3>
<p><strong>What is the cheapest complete steampunk costume here?</strong> 765,00 kr.: goggles at 165,00 kr., the tall hat at 335,00 kr. and the brocade tailcoat at 265,00 kr. It reads as a costume in a photograph and as clothing on the way home.</p>
<p><strong>Do I need a corset?</strong> No. It is the fastest way to change a silhouette, not a requirement, and the leather overbust is the version that works over clothes for a whole evening.</p>
<p><strong>What should I buy first if I am not sure?</strong> Goggles. They are 165,00 kr., they work with every outfit on this page, and they are the piece people comment on.</p>
<p><strong>Can I wear this outside a convention?</strong> Yes — that is what the hoodie and tee are for. The printed layer reads as a normal outfit; the goggles and hat are what you take off at the train.</p>
<p><strong>Which guide should I read first?</strong> <a href="https://steamgears.eu/mens-steampunk-costume-essentials/">Men's essentials</a> or <a href="https://steamgears.eu/womens-steampunk-essentials/">women's essentials</a> depending on the silhouette you want; both use the same ten-piece method, so you can switch later without starting over.</p>
<p><strong>How do I know the sizes?</strong> Each product page has its own size chart. Read that one rather than your usual clothing size, especially for the corsets and the coats.</p>
<p><strong>What if the prices have moved?</strong> The prices here were read from our own shop on 9 November 2026, in DKK. The product page wins if they differ.</p>
```

**FAQ-spørgsmål (550):** What is the cheapest complete steampunk costume here? · Do I need a corset? · What should I buy first if I am not sure? · Can I wear this outside a convention? · Which guide should I read first? · How do I know the sizes? · What if the prices have moved?


## 4. Fase 3 — billederne

### 4.1 To egne fotos pr. guide og pr. look

**Hvor:** Mediebibliotek → upload → vælg som **fremhævet billede** og indsæt det andet i brødteksten ved det afsnit det hører til. **Kilde:** hver copyfils `Publishing notes` punkt 1/5 og `docs/projektstatus.md` §2 (SEO).

| Fil | Foto 1 (fremhævet) | Foto 2 (detalje) | Alt-tekst 1 |
| --- | --- | --- | --- |
| 699 | hele looket, fx Airship Captain, neutral væg, dagslys | goggles skubbet op på hatten | Steampunk outfit for women: goggles on a top hat with a brocade tailcoat |
| 292 | fuld figur med alle tre ankre | goggles på hatten, tæt på | Men's steampunk costume with copper goggles, tall hat and brocade tailcoat |
| 296 | det billigste af de tre budgetter | prisen på de tre dele ved siden af hinanden | Three steampunk outfits at three budgets, 560 to 2.010 kroner |
| 295 | fuld figur med corset og frakke | snøringen på corsetten, tæt på | Women's steampunk essentials: leather corset, velvet coat and goggles |
| 550 | de tre første køb: goggles, hat, frakke | hubben i brug: samme person, to afsnit | Steampunk costume built from one piece: goggles, hat and tailcoat |

**Krav til begge fotos:** 1600 px på den lange led, JPEG, under 300 KB, ingen tekst i billedet, og billedet skal vise det produkt der linkes i samme afsnit. **Bevis:** hver af de fem sider har mindst to billeder med alt-tekst, og det fremhævede billede er sat.


### 4.2 Batch 4 — 5–8 billeder pr. produkt (eller et Printful-token)

**Hvor:** Enten **Printful** (Settings → Keys i pluginnet, så mockups hentes pr. produkt) eller Mediebibliotek. **Målt 8/11:** 22 billeder på 20 publicerede produkter, **0 produkter med 3 eller flere**, 62 billedplaceringer tomme. **Kilde:** `docs/roadmap.md` §4d række E og §4e række Batch 4.

| Krav | Værdi |
| --- | --- |
| Antal pr. produkt | **3 som minimum** (audit-tærsklen), 5–8 er målet |
| Filnavn | `p<produkt-id>-<nummer>.jpg`, fx `p477-1.jpg` |
| Alt-tekst | Produktets navn + hvad billedet viser, fx «Copper Pilot Goggles — Vintage Windproof, detalje af brillestel» |
| Rækkefølge | 1 = produktet alene, 2 = båret, 3 = detalje, 4–8 = variationer |
| Alternativ | `[EJER: Printful-token]` — så hentes mockups automatisk i stedet |

**De 20 produkter der mangler billeder** (id · navn · pris, fra [`priser-live-2026-11-09.md`](library/priser-live-2026-11-09.md)):

| ID | Produkt | Pris | Billeder i dag |
| ---: | --- | ---: | --- |
| 557 | Airship Armada — Vintage Engraving Tee | 180,00 | 1–2 (skal være ≥ 3) |
| 413 | Airship Storm — Vintage Engraving Hoodie | 335,00 | 1–2 (skal være ≥ 3) |
| 1282 | Astrolabe Planisphere — Steampunk Tee | 530,00 | 1–2 (skal være ≥ 3) |
| 1255 | Brass Fox Automaton — Steampunk Tee | 530,00 | 1–2 (skal være ≥ 3) |
| 954 | Brass Luna Moth — Clockwork Herbarium Tee | 530,00 | 1–2 (skal være ≥ 3) |
| 966 | Brass Luna Moth — Herbarium Art Print | 395,00 | 1–2 (skal være ≥ 3) |
| 970 | Brass Luna Moth — Herbarium Phone Case | 330,00 | 1–2 (skal være ≥ 3) |
| 399 | Clockwork Octopus — Curiosity Hoodie | 335,00 | 1–2 (skal være ≥ 3) |
| 965 | Clockwork Rose — Herbarium Art Print | 395,00 | 1–2 (skal være ≥ 3) |
| 940 | Clockwork Seer — Brass Pocket Watch Pendant | 865,00 | 1–2 (skal være ≥ 3) |
| 477 | Copper Pilot Goggles — Vintage Windproof | 165,00 | 1–2 (skal være ≥ 3) |
| 491 | Gothic Overbust Corset — Faux Leather, Silver | 435,00 | 1–2 (skal være ≥ 3) |
| 403 | Mechanical Elephant — Curiosity Hoodie | 335,00 | 1–2 (skal være ≥ 3) |
| 489 | Steampunk Overbust Corset — Leather Lace-Up | 400,00 | 1–2 (skal være ≥ 3) |
| 941 | The Aeronaut — Brass Skeleton Pocket Watch | 1000,00 | 1–2 (skal være ≥ 3) |
| 967 | The Raven Chronometer — Herbarium Art Print | 395,00 | 1–2 (skal være ≥ 3) |
| 483 | Time Traveler Top Hat with Goggles | 335,00 | 1–2 (skal være ≥ 3) |
| 495 | Victorian Vampire Tailcoat — Brocade | 265,00 | 1–2 (skal være ≥ 3) |
| 401 | Whale Airship — Vintage Voyage Hoodie | 335,00 | 1–2 (skal være ≥ 3) |
| 493 | Wine Red Velvet Tailcoat — Victorian Frock Coat | 435,00 | 1–2 (skal være ≥ 3) |

**Bevis:** `python3 tools/rebrand_audit.py` rapporterer **≥ 3 billeder pr. produkt** og **0 tomme billedplaceringer**; rebrand går fra 71,3 % til 100 % (+28,7 pp).


## 5. Fase 4 — miljø, server og sandbox (S5, 60 %)

Fire trin, alle i paneler uden for WordPress. **Kilde:** `docs/roadmap.md` §4e (de fem sidste handlinger) og `docs/projektstatus.md` §2.

### 5.1 Staging først — det gør de øvrige ændringer prøvbare (S5.1, +25,0 pp)

**Hvor:** Simply → Hjemmeside → **Subdomæne**. **Målt 9/11:** navnet findes ikke, certifikatet dækker kun apex + `www`, så TLS fejler.

| Indstilling | Værdi |
| --- | --- |
| Navn | `staging.steamgears.eu` |
| Certifikat | Let's Encrypt, eget certifikat |
| Database | egen kopi af produktion |
| Adgang | HTTP-auth, brugernavn `[EJER: brugernavn]`, adgangskode `[EJER: adgangskode]` |
| Indeksering | `noindex` + `WP_ENVIRONMENT_TYPE=staging` |
| Betaling | Stripe **testnøgler** |

**Bevis:** `curl -sI https://staging.steamgears.eu` svarer **401**, certifikatets SAN dækker navnet, og `python3 tools/s5_measure.py --strict` går fra «sandkasse findes ?» til **20/20 ✓**.


### 5.2 DMARC: to poster, og CNAME'en skal væk først (S5.7, +5,0 pp)

**Hvor:** Simply → DNS-panel. **Målt 9/11:** `_dmarc.steamgears.eu` er en **CNAME** til `dmarc.simply.com` med Simplys egne `rua`/`ruf`, og apex har kun SPF.

1. **Slet** CNAME-posten `_dmarc` (en TXT og en CNAME kan ikke ligge på samme navn).
2. Opret TXT på `_dmarc.steamgears.eu` med præcis denne værdi — kun adressen er din:

```text
v=DMARC1; p=reject; rua=mailto:[EJER: butikkens adresse]; ruf=mailto:[EJER: samme adresse]; fo=1
```
3. **Bevis:** `dig +short TXT _dmarc.steamgears.eu` viser butikkens adresse, og `p=reject` er beholdt.

### 5.3 DKIM (samme trin, S5.7)

**Hvor:** Simply → Mailindstillinger → slå **DKIM** til. Der findes **ingen** DKIM-post i dag.

| Felt | Værdi |
| --- | --- |
| Postnavn | `<selector>._domainkey.steamgears.eu` — `[EJER: selector]` står i panelet når DKIM slås til |
| Værdi | `[EJER: den TXT Simply giver]` — den skal starte med `v=DKIM1; k=rsa; p=` |
**Bevis:** `dig +short TXT <selector>._domainkey.steamgears.eu` svarer med `v=DKIM1`, og en modtaget ordremail har `dkim=pass` i `Authentication-Results`.


### 5.4 Backup-politikken og staging-gendannelsen (S5.8, +1,7 pp)

**Hvor:** Simply → Backup. **Målt 9/11:** agent-delen er kørt — dumpet gendannes med **269/269 tabeller**, **RPO 0 t / RTO 6 s** — men værtens opbevaring er ikke dokumenteret.

| Trin | Handling | Værdi |
| ---: | --- | --- |
| 1 | Slå op hvor længe Simply opbevarer backup | `[EJER: antal dage, krav ≥ 14]` |
| 2 | Gendan den nyeste dump i staging fra §5.1 og tag tiden | `[EJER: minutter]` |
| 3 | Skriv begge tal i `docs/library/` som en måling | agenten gør det, når tallene findes |

**Bevis:** en tabel med arkivets størrelse, gendannelsens varighed og hvad der manglede bagefter.


### 5.5 Branch protection (S5.9, +8,3 pp)

**Hvor:** GitHub → repo → Settings → Branches. **Målt 9/11:** API'et svarer **403 »Upgrade to GitHub Pro or make this repository public«**.

1. Vælg: **GitHub Pro/Team** `[EJER: ja/nej]` — eller gør repoet offentligt.
2. Sæt de fem indstillinger; `python3 tools/release.py --protection` viser dem, og
   `gh api -X PUT repos/humle93/steamgears-site/branches/main/protection` sætter dem.
**Bevis:** `gh api repos/humle93/steamgears-site/branches/main/protection` svarer **200** med de fem nøgler. **Regnestykket:** miljø bliver 1,80 + (0,75 + 0,15 + 0,05 + 0,25) = **3,00 d → 100 %**.


## 6. Fase 5 — plugin og kanaler (P5, 96,7 %)

**Kilde:** `docs/projektstatus.md` §2 (Forum + Reddit), plugin-roadmappens §8 og `docs/roadmap.md` §4d række I.

### 6.1 Installér zip'en — selvopdateringen kan ikke hente den

Repoet er privat, så `SGAP_Updater` kan ikke hente pakken anonymt. **Hvor:** Plugins → Tilføj nyt → Upload Plugin.

| Felt | Værdi |
| --- | --- |
| Fil | `plugins/steamgears-autopilot-v7.66.0.zip` (ligger i repoet, versionsnummer i filnavnet) |
| Bagefter | Plugins → kontrollér at versionen står som **7.66.0** |

### 6.2 Forum-siden

**Hvor:** Sider → Tilføj ny. Indsæt shortcoden på sin egen linje, publicér, og læg siden i menuen.

```text
[steamgears_forum]
```
**Bevis:** siden viser formularen, og et indlæg kan oprettes og kommenteres med native kommentarer.


### 6.3 Reddit script-app (kun dig)

**Hvor:** reddit.com → Preferences → Apps → **create another app** → type **script**. Reddits vilkår (5/10 2026) kræver forhåndsgodkendelse til Data API og en skriftlig aftale ved kommerciel brug — spor A (post i hånden) virker i dag uden app.

| Felt | Værdi |
| --- | --- |
| name | `steamgears` |
| type | `script` |
| redirect uri | `http://localhost:8080` (bruges ikke af script-typen) |
| App-id, secret, brugernavn, adgangskode | `[EJER: fire værdier]` — indsættes i plugin → Settings → Keys |
**Bevis:** `channel-health` viser `ready` for Reddit, og den første link-post kan køres.

### 6.4 Tavler → subreddits

**Hvor:** Forum-termernes meta `reddit_sub`. Uden den mapping poster køen intet.

| Tavle (term) | Subreddit |
| --- | --- |
| `[EJER: tavle 1]` | `[EJER: subreddit]` |
| `[EJER: tavle 2]` | `[EJER: subreddit]` |
**Bevis:** term-metafeltet er udfyldt på hver tavle, og køen viser en planlagt post.


### 6.5 Kanalerne: Pinterest-scope og fem tokens

**Hvor:** Plugin → Settings → Keys. **Målt 8/11:** Pinterest-tokenet virker men mangler **`boards:read`** (status `needs_scope`), og **5 af 6** kanaler har intet token (`missing_token`).

| Kanal | Handling |
| --- | --- |
| Pinterest | Genforbind med `boards:read` i scopes `[EJER: godkend appen igen]` |
| Instagram | `[EJER: token]` |
| Bluesky | `[EJER: app password]` |
| Mastodon | `[EJER: token]` |
| Telegram | `[EJER: bot-token fra BotFather]` |
| Facebook | `[EJER: token]` |
**Bevis:** `python3 tools/live_channels.py` svarer **bestået** for alle seks, og `channel-health` viser `ready` × 6.

### 6.6 Indholdsproduktionen: AI-kredit og auto vs. godkendelse

| Trin | Hvor | Værdi |
| ---: | --- | --- |
| 1 | aimlapi.com → Billing | `[EJER: fyld op]` — sidste generering fejlede med «You've run out of funds», og **45 kladder** venter |
| 2 | Plugin → Settings | `sgap_full_auto_enabled`: **40 elementer** venter på godkendelse; `[EJER: auto eller godkend]` |
| 3 | Plugin → Settings | `publish_mode = rss`, 0 Publer-konti; `[EJER: rss eller Publer]` |
| 4 | Plugin → Command Center | `[EJER: ja/nej til énvejs-integration]` (P2.1–P2.3) |
**Bevis:** en kladde genereres, gennemgås og publiceres til mindst én kanal; køen falder under loftet.


## 7. Fase 6 — KABINE (48,4 %, seks ejerhandlinger)

**Kilde:** `docs/projektstatus.md` §2 (KABINE) og `docs/library/bilinterioer-launch-2026-11-09.md`. Intet af det kan gættes: hvert punkt kræver en konto, et panel eller en underskrift.

| # | Handling | Hvor | Værdi |
| --- | --- | --- | --- |
| E1 | CVR, telefon, adresse og bankaftale | Erhvervsstyrelsen + banken | `[EJER: CVR, telefon, adresse, bank]` |
| E2 | Leverandørvalg med GPSR-dokumentation og to prøver | leverandøren | `[EJER: leverandør + GPSR-papirer]` |
| E3 | Registrering i producentregistret for emballage | DPA | `[EJER: registreringsnummer]` |
| E4 | Navn og domæne + Shopify-konto | registrar + Shopify | `[EJER: domæne]` — kabine.dk, oploft.dk, stille.dk og immer.dk er alle taget (DNS, 9/11) |
| E5 | Start 9/11 i Q4-priser eller 5/1 til lavere CPA | beslutning | `[EJER: dato]` |
| E6 | Annoncekonto med betalingsmetode og 10.000 kr. | Meta/Google | `[EJER: kontonummer]` |
**Bevis:** de fire butiksmoduler (6 t), leverandør (4 t), juridiske sider (2,5 t) og første annoncerunde (4 t) kan først starte når E1–E6 foreligger; projektet går fra 48,4 % til 100 % i den rækkefølge.


## 8. Resten af ejerpunkterne (korte, men ikke glemt)

| # | Handling | Hvor | Værdi |
| --- | --- | --- | --- |
| G | To inaktive plugins indlæser ingen kode | Plugins | `[EJER: slet / behold]` |
| L | GSC svarer 401 på API | Search Console → Crawl-statik → **By response** | `[EJER: screenshot]` |
| H | 29 designs i papirkurven; witchy-tarot har 0 produkter | Produkter → Papirkurv | `[EJER: ja/nej til 567, 395, 411]` — derefter kører `python3 tools/rebrand_apply.py --apply --via-cli` selv tildelingen |
| — | Valuta-switcher hvis EUR skal vises | WooCommerce → Multi-currency | `[EJER: ja/nej]` |
**Bevis:** plugin-listen har ingen inaktive plugins med kode, GSC-screenshot ligger i `docs/library/`, og witchy-tarot har mindst ét produkt eller er bevidst tom.


## 9. Sporbarhed — hvert trin til sin kilde

| Trin | Copy/fakta kommer fra | Målt |
| --- | --- | --- |
| 2.1 (699) | `docs/library/steampunk-outfit-ideas-for-women.md` · `priser-live-2026-11-09.md` | 9/11 |
| 2.2 (EU-fragt) | `docs/roadmap.md` §4d B · `docs/projektstatus.md` §2 (site) | 8/11 |
| 2.3 (CVR/telefon) | `docs/roadmap.md` §4d A | 8/11 |
| 2.4 (betaling) | `docs/roadmap.md` §4d C–D | 8/11 |
| 2.5 (kladder) | `docs/roadmap.md` §4d F | 8/11 |
| 3 (292/296/295/550) | `docs/library/guides-2026-11-03.md` · `hub-steampunk-costumes-2026-11-03.md` | 9/11 |
| 4.1 (fotos) | copyfilernes `Publishing notes` | 9/11 |
| 4.2 (Batch 4) | `docs/roadmap.md` §4d E + §4e Batch 4 | 8/11 |
| 5.1–5.5 (S5) | `docs/roadmap.md` §4e | 9/11 |
| 6 (P5) | `docs/projektstatus.md` §2 (Forum + Reddit) · plugin-ROADMAP §8 | 8/11 |
| 7 (KABINE) | `docs/projektstatus.md` §2 (KABINE) | 9/11 |
| 8 (G/H/L) | `docs/roadmap.md` §4d | 8/11 |

Fælles for hele tabellen: **priser og `/go/`-id'er** er de samme som [`priser-live-2026-11-09.md`](library/priser-live-2026-11-09.md), og de valideres af `python3 tools/guide_check.py` — 6 af 6 copy-blokke, 15 unikke id'er, 0 døde, 0 valutaafvigelser.


## 10. Pladsholder-register

Alt nedenfor er data agenten **ikke** må gætte. Ingen af felterne er udfyldt med et opdigtet tal.

| Pladsholder | Hvad der mangler | Hvor det tastes | Ejer |
| --- | --- | --- | --- |
| `[EJER: hvilke EU-lande]` | zonens lande | WooCommerce → Forsendelse → Europe | ejer |
| `[EJER: fragtpris i kr.]` | pris pr. ordre | samme zone → Flat rate | ejer |
| `[EJER: beløb, fx 600]` | fri fragt-grænse | samme zone | ejer |
| `[EJER: åbn/luk]` | resten af verdenen | WooCommerce → Forsendelse | ejer |
| `[EJER: telefonnummer]` | telefon med landekode | Complianz → Organization | ejer |
| `[EJER: CVR-nummer]` | 8 cifre | Complianz → Organization | ejer |
| `[EJER: firmanavn]` | registreret navn (nu »Henning 6«) | Complianz → Organization | ejer |
| `[EJER: ja/nej]` (flere) | beslutninger: Stripe-metoder, valuta-switcher, repo-offentlighed, plugins, auto/godkendelse, Publer, Command Center, witchy-tarot | de nævnte sider | ejer |
| `[EJER: aftale + nøgler]` | MobilePay/PayPal | WooCommerce → Betalinger | ejer |
| `[EJER: Printful-token]` eller 5–8 fotos | mockups | Plugin → Settings → Keys / Mediebibliotek | ejer |
| `[EJER: staging-adgang]`, `[EJER: selector]`, `[EJER: DKIM-TXT]` | subdomæne, DKIM | Simply | ejer |
| `[EJER: butikkens adresse]` | DMARC `rua`/`ruf` | Simply → DNS | ejer |
| `[EJER: Reddit-app-id/secret/brugernavn/adgangskode]` | script-app | Plugin → Settings → Keys | ejer |
| `[EJER: subreddit]` | tavle-mapping | forum-term meta `reddit_sub` | ejer |
| `[EJER: token]` × 5 | Instagram, Bluesky, Mastodon, Telegram, Facebook | Plugin → Settings → Keys | ejer |
| `[EJER: CVR, telefon, adresse, bank]` m.fl. | KABINE E1–E6 | Erhvervsstyrelsen, bank, DPA, registrar, Shopify, annoncekonto | ejer |


## 11. Efterkontrol — det der skal stå i outputtet

| Kommando | Forventet | Dækker |
| --- | --- | --- |
| `python3 tools/guide_check.py` | `6 af 6 copy-blokke bestod`, 15 id'er målt, **0 døde**, **0 valutaafvigelser** | 292/296/295/550/699 |
| `python3 tools/guide_check.py --selftest` | `24 af 24` | værnene selv |
| `bash tools/natlige-vaern.sh` | `Alle 40 værn grønne` | hele repoet |
| `python3 tools/s5_measure.py --strict` | `20/20 ✓` | S5.1 |
| `python3 tools/rebrand_audit.py` | ≥ 3 billeder pr. produkt, 0 tomme placeringer | 4.2 |
| `python3 tools/live_channels.py` | `bestået` for alle seks kanaler | 6.5 |
| `gh api repos/humle93/steamgears-site/branches/main/protection` | `200` | 5.5 |
| `curl -s https://steamgears.eu/steampunk-outfit-ideas-for-women/ \| grep -c '\$[0-9]'` | `0` | 2.1 |
| `curl -sI https://staging.steamgears.eu` | `401` | 5.1 |

**Definition of done pr. projekt:** site-optimering og SEO står på 100 % og bliver der; rebrand går 71,3 % → **100 %** (Batch 4), forum 96,7 % → **100 %** (kanaler), miljø 60 % → **100 %** (S5.1–S5.9), KABINE 48,4 % → **100 %** når E1–E6 er leveret. **Regnestykket** står i `docs/roadmap.md` §4e.


## 12. Hvad denne kørsel ikke gør

- Den publicerer intet. Hvert trin kræver et login, en konto eller en underskrift — der findes ingen credential i agentens sandkasse (målt igen 9/11).
- Den opfinder ingen data. Hvor et tal mangler, står `[EJER: …]`; alle markeringer står grupperet i §10.
- **Kopiér HTML-blokkene fra denne markdown-fil, ikke fra PDF'en.** PDF-eksporten gengiver `—` som `--` inde i kodeblokke (samme tekst, andet tegn); links og beløb er upåvirkede, men produktnavne skal have em-dash med, som butikken skriver dem.
- Den ændrer ikke copyen: blokkene er genereret ud af de filer `tools/guide_check.py` validerer, og priserne kommer fra `priser-live-2026-11-09.md`. Ændrer du i en copyfil, skal blokken herover genereres igen — værnene vil fange det, hvis en pris eller et id ikke længere holder.
