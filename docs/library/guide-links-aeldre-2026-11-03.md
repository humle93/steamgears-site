# Link-plan: de ældre publicerede guides — 3/11 2026

De fire nye guides fik `/go/<id>/`-links og en fast struktur (link ved første omtale, hub i bunden, ét link pr.
produkt). De ældre publicerede guides har ikke fået samme behandling, og det er de sidste 0,05 d af **S2.6**.
Planen her er den del der kan gøres færdig i repoet: hvilket produkt hver guide skal linke, med hvilken
linktekst, og hvilken pris der står i pristabellen. Anvendelsen på de live sider venter på WordPress-adgang.

Kilde: [`meta-fixes-og-produktlinks-2026-11-02.md`](meta-fixes-og-produktlinks-2026-11-02.md) §3 (de ni guides),
produkt-ID'er fra [`om-maerkning-dark-academia-2026-11-01.md`](om-maerkning-dark-academia-2026-11-01.md),
priser fra [`valuta-og-priser-2026-11-02.md`](valuta-og-priser-2026-11-02.md). Intet er opdigtet: hver række
nedenfor er kontrolleret af `tools/link_plan_check.py` mod netop de to filer.

## 1. Planen

| Guide | Link | Linktekst | Pris |
| --- | --- | --- | --- |
| `/how-to-make-a-steampunk-costume/` | https://steamgears.eu/go/477/ | Copper Pilot goggles | $24.95 |
| `/how-to-make-a-steampunk-costume/` | https://steamgears.eu/go/481/ | Steampunk top hat with removable goggles | $34.95 |
| `/how-to-make-a-steampunk-costume/` | https://steamgears.eu/go/809/ | Steampunk costume checklist | gratis |
| `/3d-print-steampunk-parts/` | https://steamgears.eu/go/479/ | Vintage Driver goggles | $29.95 |
| `/best-steampunk-books/` | https://steamgears.eu/go/728/ | First Mate's Oath print | $21.90 |
| `/best-steampunk-books/` | https://steamgears.eu/go/721/ | The Kraken Widow print | $21.90 |
| `/casual-steampunk-outfits/` | https://steamgears.eu/go/563/ | Ink and Steam Muse tee | $26.95 |
| `/casual-steampunk-outfits/` | https://steamgears.eu/go/405/ | Clockwork Owl hoodie | $49.95 |
| `/closet-steampunk-outfits/` | https://steamgears.eu/go/561/ | Cabinet of Curiosities tee | $26.95 |
| `/closet-steampunk-outfits/` | https://steamgears.eu/go/483/ | Time Traveler top hat | $49.95 |
| `/sustainable-steampunk/` | https://steamgears.eu/go/809/ | Steampunk costume checklist | gratis |
| `/build/tools-and-materials/` | https://steamgears.eu/go/809/ | Steampunk costume checklist | gratis |

Prisen er med som kontrol, ikke som copy: linkteksten skal stå i den sætning hvor guiden allerede omtaler
stykket, ikke i en ny "shop dette look"-boks. `/go/809/` er den gratis tjekliste-PDF, derfor `gratis`.

## 2. Strukturen der følger med

Samme fire regler som de nye guides fik, fordi det er dem der gør linkene til en struktur og ikke til annoncer:

1. **Linket sidder ved første omtale** af stykket i brødteksten. Ingen boks i bunden, ingen "relaterede produkter".
2. **Højst ét link pr. produkt pr. guide.** To links til samme vare i samme tekst er et link, der prøver to gange.
3. **Hub-link i bunden:** `/steampunk-costumes/` for costume-guides, `/start-here/` for begynder-guides.
4. **`/build/*`-siderne er hubs**, ikke guides: der linkes i brødteksten, ikke i en produktblok — og kun til det
   der beskrives som værktøj eller materiale.

## 3. Hvad der ikke kunne planlægges

Seks produkt-ID'er fra 2/11-planen står ikke i ID-mappingen (766, 767, 940, 941, 942, 968). Uden en
mapping-række kan jeg ikke skrive en linktekst der er kontrolleret mod et produktnavn, så de er udeladt frem for
gættet:

| Guide | Produkt fra 2/11-planen | Status |
| --- | --- | --- |
| `/build/weathering/` | Digital Blueprint (767) | **Blokeret** — 767 mangler i mappingen, og det var guidens eneste produkt |
| `/steampunk-gift-guide-2026/` | 941, 940, 942, 968 | **Blokeret** — alle fire mangler i mappingen, og guiden hviler på fire prisniveauer |
| `/build/tools-and-materials/` | 767 | Delvis — 809 er med, 767 mangler |
| `/sustainable-steampunk/` | 767 | Delvis — 809 er med, 767 mangler |
| `/3d-print-steampunk-parts/` | 766 (bælte og holster) | Delvis — 479 er med, 766 mangler |

Det er ikke en mangel i planen men i mappingen: 39 designs er kortlagt, kataloget har 56 produkter. De 17 der
ikke er kortlagt, kan ikke få en kvalitetssikret linktekst før de er med i mappingen.

## 4. Verifikation

`python3 tools/link_plan_check.py` kontrollerer hver række: guide-stien, at `/go/<id>/` findes i mappingen, at
linkteksten deler ord med mappingens produktnavn, at prisen findes i pristabellen (eller er den gratis PDF), og
at ingen guide/produkt-par står to gange. `--selftest` har otte fixtures, der beviser at værnene fanger en
opdigtet pris, et ukendt id, et forkert produktnavn, en dublering og en tom plan.

**Det der venter på WordPress-adgang:** de tolv linkindsættelser på de syv guides, plus at `/go/`-ruten selv
virker (Redirection-gruppe eller Royal Links — ejer).
