# Hub-links på de resterende indlæg — S2.8, målt 7/11 2026

S2.8 handlede om de publicerede indlæg, der ikke sendte ét eneste kontekstuelt link til
`/start-here/` eller `/build/`. Målingen før passet: **30 publicerede indlæg, 9 med et hub-link, 21 uden.**
Efter passet: **29 af 30 har et hub-link**, og det sidste er ikke en artikel — det er indlæg 898, hvis
indhold er ordet `test`.

Hvert link er sat som én sætning i brødteksten, ikke som en blok af links, og hver side er hentet igen
bagefter for at se linket i den HTML læseren faktisk får. Intet er gættet: sætningerne står i tabellen
nedenfor, og både indsættelsespunktet og resultatet er kontrolleret maskinelt.

## 1. Resultat

| Måling | Før | Efter |
| --- | --- | --- |
| Publicerede indlæg i alt | 30 | 30 |
| Med mindst ét link til `/start-here/` eller `/build/` | **9** | **29** |
| Uden | **21** | **1** (indlæg 898, indhold = `test`) |
| Nye links sat i dette pas | — | **20** |
| Sider hentet igen med 200 og linket i HTML'en | — | **20 af 20** |

Fordelingen efter passet: 14 indlæg peger på `/start-here/`, 8 på `/build/`, og 7 havde et hub-link i
forvejen. Retningen er valgt efter indlæggets ærinde — **vælg-og-køb** peger på `/start-here/`,
**byg-og-forarbejd** peger på `/build/`.

## 2. Hvordan linket blev sat

Indsættelsen er ikke en tekstbehandling af hele indlægget. For hvert indlæg blev der kun lagt **én ny
`<p>`** ind, og den blev placeret efter denne regel, i den rækkefølge:

1. Slutter indlægget med `<!-- /wp:html -->`, lægges afsnittet **før** den markør, så det bliver inde i
   den blok der allerede findes (913).
2. Ellers, hvis indlægget har en `</script>`: lægges afsnittet **efter** den `</p>`, der lukker
   FAQ-blokken (14, 572, 615, 751, 757, 798, 803, 804, 806, 875, 891, 1394). Er der ingen `</p>` efter
   scriptet, lægges det lige efter scriptet (791).
3. Ellers, hvis indlægget slutter med `</div>`, lægges afsnittet **før** den lukning.
4. Ellers til sidst i indlægget (973, 974, 988, 989, 993, 994).

**Fælden der kostede en runde:** første forsøg fandt `"<p>"` som *sidste* forekomst før `<script`, men
flere indlæg har HTML inde i selve JSON-LD-strengen (fx et spørgsmål med `</p>` i teksten). Indsættelsen
endte dermed **inde i schema-JSON'en** på to indlæg. Rettelsen er at maskere alle `<script>…</script>`-
blokke med mellemrum af samme længde, så positionerne stadig passer, men indholdet i schemaet ikke kan
forveksles med markup. Herefter består hver indsættelse fem kontroller:

| Kontrol | Krav | Resultat |
| --- | --- | --- |
| `p_delta` | præcis +1 `<p>` | 20 af 20 |
| `script_delta` | 0 — schemaet må ikke røres | 20 af 20 |
| `div_delta` | 0 — div-balancen må ikke ændres | 20 af 20 |
| `inside_script` | falsk — aldrig inde i en script-blok | 20 af 20 |
| `saved_matches` | det gemte indhold er byte-identisk med det beregnede | 20 af 20 |

## 3. De 20 links

| Indlæg | Hub | Sætningen der blev sat |
| --- | --- | --- |
| 14 `steampunk-cosplay-for-beginners` | `/start-here/` | If you are still deciding what to build first, Start Here lays the pieces out in the order they matter. |
| 572 `curiosities-vol-3-live` | `/start-here/` | Not sure where a printed tee fits in a costume? Start Here shows how one layer sits inside the whole look. |
| 615 `casual-steampunk-outfits` | `/start-here/` | For the wider wardrobe these looks come from, Start Here maps the pieces and the order to buy them. |
| 751 `pirate-cosplay-for-beginners` | `/build/` | The techniques behind the props are in Build, which covers tools, materials and weathering. |
| 757 `how-to-build-a-steampunk-costume-for-under-100…` | `/build/` | The material list and the ageing techniques behind this plan are collected in Build. |
| 791 `steampunk-halloween-costume-women` | `/start-here/` | Starting from an empty wardrobe? Start Here picks the order to buy the pieces in. |
| 798 `steampunk-vs-dieselpunk` | `/start-here/` | Once you have picked a side, Start Here turns the choice into a shopping order. |
| 803 `closet-steampunk-outfits` | `/start-here/` | The rest of the wardrobe these workday looks belong to is mapped in Start Here. |
| 804 `steampunk-gift-guide-2026` | `/start-here/` | If you would rather let the recipient choose, Start Here explains what each piece does in an outfit. |
| 806 `sustainable-steampunk` | `/build/` | The build side of thrifting — tools, finishes and weathering — lives in Build. |
| 875 `dieselpunk-costume` | `/build/` | The tools and ageing techniques this build depends on are covered in Build. |
| 891 `steampunk-halloween-costume-men` | `/start-here/` | Starting from an empty wardrobe? Start Here lists the pieces in the order they matter. |
| 913 `steampunk-costume-for-women` | `/start-here/` | The pieces in this guide sit inside the wider wardrobe mapped in Start Here. |
| 973 `the-clockwork-herbarium-short-story` | `/start-here/` | If the twelve pieces are your first steampunk purchase, Start Here explains how they fit a costume. |
| 974 `what-is-dieselpunk` | `/build/` | Ready to build one? Build covers the tools, materials and weathering that make the decade read. |
| 988 `best-steampunk-books` | `/start-here/` | If the books are where you started, Start Here turns the genre into an outfit. |
| 989 `what-is-gaslamp-fantasy` | `/start-here/` | For the costume side of the same mood, Start Here is the entry point. |
| 993 `the-clockwork-seer-short-story` | `/start-here/` | If the story made you want the look, Start Here is where the pieces are laid out. |
| 994 `the-last-flight-of-the-meridian-short-story` | `/start-here/` | The wardrobe the story belongs to starts in Start Here. |
| 1394 `steampunk-corset-guide` | `/start-here/` | A corset is one layer of a full outfit; Start Here shows what goes over and under it. |

I tabellen er ankerteksten skrevet uden HTML; i indlæggene står den som
`<a href="https://steamgears.eu/start-here/">Start Here</a>` — altså absolut URL, som resten af sidernes
links, ikke en relativ sti.

## 4. Kontrollen bagefter

Hver af de 20 sider blev hentet fra serveren selv (`wp_remote_get` med cache-buster, samme metode som
tidligere målinger, fordi Simplys WAF giver browser-UA en challenge):

| Kontrol | Resultat |
| --- | --- |
| HTTP-status | **200** på alle 20 |
| Linket findes i den renderede HTML med den rigtige hub-URL | **20 af 20** |
| `Fatal error` i svaret | 0 af 20 |
| Sidens størrelse | 132–154 KB HTML, samme leje som før passet |

To målinger undervejs ramte en **503 fra Simply** midt i en batch. Det var forbigående: forsiden og
`/wp-json/` svarede 200 igen efter ca. 45 sekunder, og de samme sider svarede 200 ved næste forsøg.
Batchene blev derefter delt op i fem ad gangen med pause imellem, så der ikke blev hentet tyve
fulde sider i træk.

## 5. Hvad dette ikke beviser

- **At rækkefølgen i Google ændrer sig.** Linket er intern link-equity og en vej for læseren; effekten
  kan først måles i GSC efter næste crawl, og det er ikke gjort her.
- **At teksten er den bedste formulering.** Sætningerne er skrevet til indlæggets ærinde, men de er ikke
  læst af ejeren. De kan rettes uden at linket flytter sig.
- **At indlæg 898 er færdigt.** `steampunk-couple-costumes` står publiceret med indholdet `test` og nul
  links. Det er en ejerbeslutning (skriv indlægget eller sæt det til kladde), ikke et link-arbejde.
- **At der ikke findes andre huller.** Målingen tæller kun links til de to hubs. Links til
  `/steampunk-costumes/`, produktkategorier og produktlinks er ikke genmålt i dette pas.

## 6. Filer

- Roadmap-punktet: [`../roadmap.md`](../roadmap.md) → **S2.8**.
- Status: [`../../projektstatus.json`](../../projektstatus.json) → `site` / `s2`.
- Den ældre linkmåling: [`guide-links-live-2026-11-05.md`](guide-links-live-2026-11-05.md).
- Værnet for linkplanen: [`../../tools/link_plan_check.py`](../../tools/link_plan_check.py).
