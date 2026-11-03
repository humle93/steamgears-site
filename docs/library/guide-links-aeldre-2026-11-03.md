# Link-plan: de ældre publicerede guides — 3/11 2026

De fire nye guides fik `/go/<id>/`-links og en fast struktur. De ældre publicerede guides har ikke fået samme
behandling. **Tyve af linkene er nu indsat i den ene ældre guide der findes som fil i repoet**
([`steampunk-outfit-ideas-for-women.md`](steampunk-outfit-ideas-for-women.md)), og tolv står klar til de guides
der kun findes i WordPress. Otte rækker har et kortlagt produkt-ID men ingen prisrække i repoet — de er mærket
`pris mangler` i stedet for at få en opdigtet pris.

Link-kolonnen er stien på sitet (`/go/<id>/`). Produkt-ID'er: [`om-maerkning-dark-academia-2026-11-01.md`](om-maerkning-dark-academia-2026-11-01.md) (39 designs
plus appendix med de seks produkter uden for dem). Priser:
[`valuta-og-priser-2026-11-02.md`](valuta-og-priser-2026-11-02.md). Intet er gættet; hver række nedenfor er
kontrolleret af `tools/link_plan_check.py`, og rækker mærket `indsat` tjekkes **mod filen selv**.

## 1. Indsat i repoet — `/steampunk-outfit-ideas-for-women/`

Guiden er en lookbook med 12 looks og priser, men uden ét eneste produktlink. Alle 20 produkter er nu linket på
den linje hvor de allerede stod, plus en "Read Next"-sektion med de fire nye guides og hubben. Fire produkter
står to gange i guiden (corset-top i look 1 og 4, overbust i 3 og 7, driver goggles i 2 og 6); de er linket ved
første omtale og bevidst ikke igen — ét link pr. produkt pr. guide.

| Guide | Link | Linktekst | Pris | Status |
| --- | --- | --- | --- | --- |
| `/steampunk-outfit-ideas-for-women/` | /go/487/ | Victorian corset top | $44.95 | `indsat` |
| `/steampunk-outfit-ideas-for-women/` | /go/497/ | Medieval Assassin coat | $39.95 | `indsat` |
| `/steampunk-outfit-ideas-for-women/` | /go/481/ | Steampunk top hat with removable goggles | $34.95 | `indsat` |
| `/steampunk-outfit-ideas-for-women/` | /go/489/ | Steampunk overbust corset | $59.95 | `indsat` |
| `/steampunk-outfit-ideas-for-women/` | /go/493/ | Wine Red velvet tailcoat | $64.95 | `indsat` |
| `/steampunk-outfit-ideas-for-women/` | /go/479/ | Vintage Driver goggles | $29.95 | `indsat` |
| `/steampunk-outfit-ideas-for-women/` | /go/491/ | Gothic PU overbust corset | $64.95 | `indsat` |
| `/steampunk-outfit-ideas-for-women/` | /go/485/ | Gothic bowler top hat | $39.95 | `indsat` |
| `/steampunk-outfit-ideas-for-women/` | /go/477/ | Copper Pilot goggles | $24.95 | `indsat` |
| `/steampunk-outfit-ideas-for-women/` | /go/559/ | Clockwork Fox tee | $26.95 | `indsat` |
| `/steampunk-outfit-ideas-for-women/` | /go/555/ | Nautilus Voyage tee | $26.95 | `indsat` |
| `/steampunk-outfit-ideas-for-women/` | /go/475/ | Steampunk Aviator goggles | $24.95 | `indsat` |
| `/steampunk-outfit-ideas-for-women/` | /go/409/ | Gear Corset Lady hoodie | $49.95 | `indsat` |
| `/steampunk-outfit-ideas-for-women/` | /go/495/ | Victorian Vampire tailcoat | $39.95 | `indsat` |
| `/steampunk-outfit-ideas-for-women/` | /go/483/ | Time Traveler top hat | $49.95 | `indsat` |
| `/steampunk-outfit-ideas-for-women/` | /go/719/ | Sky Corsair print | $21.90 | `indsat` |
| `/steampunk-outfit-ideas-for-women/` | /go/567/ | Clockwork Seer tee | $26.95 | `indsat` |
| `/steampunk-outfit-ideas-for-women/` | /go/561/ | Cabinet of Curiosities tee | $26.95 | `indsat` |
| `/steampunk-outfit-ideas-for-women/` | /go/403/ | Mechanical Elephant hoodie | $49.95 | `indsat` |
| `/steampunk-outfit-ideas-for-women/` | /go/399/ | Clockwork Octopus hoodie | $49.95 | `indsat` |

## 2. Klar til WordPress-adgang — syv guides der kun findes på sitet

Ingen af disse guides har en fil i repoet. Derfor er der **ikke** skrevet på dem: rækkerne er den redigering
der skal laves, med den linktekst der skal bruges.

| Guide | Link | Linktekst | Pris | Status |
| --- | --- | --- | --- | --- |
| `/how-to-make-a-steampunk-costume/` | /go/477/ | Copper Pilot goggles | $24.95 | `klar` |
| `/how-to-make-a-steampunk-costume/` | /go/481/ | Steampunk top hat with removable goggles | $34.95 | `klar` |
| `/how-to-make-a-steampunk-costume/` | /go/809/ | Steampunk costume checklist | gratis | `klar` |
| `/3d-print-steampunk-parts/` | /go/479/ | Vintage Driver goggles | $29.95 | `klar` |
| `/best-steampunk-books/` | /go/728/ | First Mate's Oath print | $21.90 | `klar` |
| `/best-steampunk-books/` | /go/721/ | Kraken Widow print | $21.90 | `klar` |
| `/casual-steampunk-outfits/` | /go/563/ | Ink and Steam Muse tee | $26.95 | `klar` |
| `/casual-steampunk-outfits/` | /go/405/ | Clockwork Owl hoodie | $49.95 | `klar` |
| `/closet-steampunk-outfits/` | /go/561/ | Cabinet of Curiosities tee | $26.95 | `klar` |
| `/closet-steampunk-outfits/` | /go/483/ | Time Traveler top hat | $49.95 | `klar` |
| `/sustainable-steampunk/` | /go/809/ | Steampunk costume checklist | gratis | `klar` |
| `/build/tools-and-materials/` | /go/809/ | Steampunk costume checklist | gratis | `klar` |

## 3. Kortlagt, men uden pris i repoet

De seks produkter nedenfor var ikke i ID-mappingen; de er lagt ind i
[appendix i mappingen](om-maerkning-dark-academia-2026-11-01.md#appendix--produkter-uden-for-de-39-designs-tilføjet-311-2026)
med den kilde i repoet der nævner dem. **Ingen af dem har en prisrække i pristabellen**, så rækkerne står med
`—` og status `pris mangler`: linket kan godt sættes, men teksten må ikke love en pris jeg ikke kan slå op.

| Guide | Link | Linktekst | Pris | Status |
| --- | --- | --- | --- | --- |
| `/3d-print-steampunk-parts/` | /go/766/ | Utility belt and holster | — | `pris mangler` |
| `/build/weathering/` | /go/767/ | Victorian corset blueprint PDF | — | `pris mangler` |
| `/build/tools-and-materials/` | /go/767/ | Victorian corset blueprint PDF | — | `pris mangler` |
| `/sustainable-steampunk/` | /go/767/ | Victorian corset blueprint PDF | — | `pris mangler` |
| `/steampunk-gift-guide-2026/` | /go/940/ | Clockwork Seer pendant | — | `pris mangler` |
| `/steampunk-gift-guide-2026/` | /go/941/ | The Aeronaut pocket watch | — | `pris mangler` |
| `/steampunk-gift-guide-2026/` | /go/942/ | Ink and Steam bracelet | — | `pris mangler` |
| `/steampunk-gift-guide-2026/` | /go/968/ | Nightshade Apothecary print | — | `pris mangler` |

Det er ikke længere en tavs mangel: hvert ID har en række i mappingen med en kilde, og hullet er prisen — som
kræver et opslag i butikken, ikke en gætning her.

**Hvad der stadig ikke kan linkes:** intet. Alle ni guides fra 2/11-planen har nu mindst ét kortlagt produkt.
`/build/weathering/` og `/steampunk-gift-guide-2026/` var de to der så ud til at være blokeret; de er dækket af
henholdsvis 767 og de fire Atelier/Herbarium-ID'er, blot uden pris.

## 4. Strukturen der følger med

1. **Linket sidder ved første omtale** af stykket i brødteksten. Ingen boks i bunden, ingen "relaterede produkter".
2. **Højst ét link pr. produkt pr. guide** — også når guiden nævner varen to gange.
3. **Hub og klynge i bunden:** `/steampunk-costumes/` plus de fire 2026-guides, så den gamle guide ikke er en
   isoleret side.
4. **`/build/*` er hubs**, ikke guides: der linkes i brødteksten, ikke i en produktblok.

## 5. Verifikation

`python3 tools/link_plan_check.py` tjekker hver række: guide-stien, at `/go/<id>/` findes i mappingen (inkl.
appendix), at linkteksten deler ord med produktnavnet, at prisen findes i pristabellen (eller er `gratis` for
809, eller `—` for de seks produkter der beviseligt ikke har en prisrække), at status er gyldig, og at en række
mærket `indsat` **faktisk har sit `/go/<id>/` i den fil guiden bor i**. `--selftest` har tolv fixtures, der
beviser at værnene fanger en opdigtet pris, et ukendt id, et forkert produktnavn, en dublering, en ukendt status,
en tom plan og et påstået-indsat link der ikke står i filen.

**Det der venter på WordPress-adgang:** de tolv indsættelser i §2, de otte i §3, og at `/go/`-ruten selv virker
(Redirection-gruppe eller Royal Links — ejer).
