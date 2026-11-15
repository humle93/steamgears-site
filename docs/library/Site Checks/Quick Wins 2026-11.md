# Quick Wins — November 2026 (GSC pos 8–20)

Kørt 11/11 2026 af Juno. Data: GSC search analytics 14/10–10/11 (28 dage), steamgears.eu, web-search.
Metode: queries+pages kombineret → sider med pos 8–20 og >50 impressions = Quick Wins.

## Datagrundlag (28 dage)

| Side | Impressioner | Klik | Snit-pos | Status |
|---|---|---|---|---|
| /diy-steampunk-goggles/ | 151 | 7 | 8,16 | **Quick Win #1** |
| /steampunk-costume-ideas/ | 103 | 2 | 11,31 | **Quick Win #2** |
| /how-to-make-a-steampunk-costume/ | 84 | 3 | 11,58 | **Quick Win #3** |
| /steampunk-accessories/ | 70 | 0 | 18,06 | **Quick Win #4** |
| /steampunk-cosplay-for-beginners/ | 34 | 1 | 9,2 | under threshold (imp < 50) |
| /steampunk-vs-dieselpunk/ | 25 | 1 | 9,8 | under threshold |
| / (forside) | 40 | 1 | 14,4 | under threshold |

Under-50-impressions-sider på side 1-2 (pos 7–13, bevogtes næste måned): /steampunk-cosplay-on-a-budget/ (pos 8,8), /steampunk-vs-dieselpunk/ (pos 9,8–12,9), /start-here/glossary/ (pos 7,5, q "steampunk terms"), /diy-vs-buy-steampunk-goggles/ (pos 7,3, q "homemade goggles price comparison").

## Nøgle-queries pr. Quick Win

### 1. /diy-steampunk-goggles/ (pos 8,2 → mål: side 1 top)
- "how to make steampunk goggles out of cardboard" — 8 impr, pos 9,0, 0 klik
- "steampunk goggles diy" — 5 impr, pos 13,0
- ✅ Siden HAR allerede: `<h2>How to Make Steampunk Goggles Out of Cardboard</h2>` sektion (tilføjet efter tidligere rutine), FAQ-skema, Cardboard-anchor i Method 1.
- **Handling:** internt-link boost fra 3 donor-sider med eksakt anchor "steampunk goggles diy" / "make steampunk goggles out of cardboard".
  - post 8 "Step 3" h2-anchor "DIY Steampunk Goggles" → behold (brand-anchor ok), men ny sætning i Body? nej — post 8 har allerede link. Donorer: post 14 (anchor allerede "DIY steampunk goggles guide" — fine), post 15 mangler link → tilføjes i "Building Any of These on a Budget"-afsnittet.
  - Resultat: post 15 fik eksakt-ankret link.

### 2. /steampunk-costume-ideas/ (pos 11,3 → mål: pos < 10)
- "steampunk costume ideas" — 25 impr, pos 11,2, 1 klik (CTR 4%)
- "steam punk costume ideas" — 5 impr, pos 9,8
- ✅ H1/title matcher allerede ("30+ Steampunk Costume Ideas: Airship Captains, Inventors & Explorers").
- **Handling:** eksakte ankre "steampunk costume ideas" fundet i post 8, 14, 17 + hub 550 — 3+ eksakte ankre på plads. Tilføjet: FAQ-spørgsmål "What are some easy steampunk costume ideas?" i post 15 + FAQPage-schema + link til /steampunk-accessories/ med anchor "steampunk accessories for costumes" (post 15 havde NUL links til accessories-siden).
- Forventet effekt: pos 11,3 → 8-10 inden december.

### 3. /how-to-make-a-steampunk-costume/ (pos 11,6)
- "diy steampunk costume" — 3 impr, pos 25,3 (rå query rangerer dårligt)
- ✅ Siden har allerede "The Easiest DIY Steampunk Costume: A 60-Second Recipe"-sektion med eksakt-match overskrift.
- **Handling:** internt-link boost: post 10 havde link med anchor "DIY steampunk costume" ✅ (allerede eksakt). Ny: post 30 fik eksakt-ankret "DIY steampunk costume"-link i "Where to Buy"-sektionen + link i FAQ ("What about corsets?" svar peger på costume-guiden — eksisterende).
- Forventet effekt: "diy steampunk costume" pos 25 → 15-20; side-position 11,6 → 9-11.

### 4. /steampunk-accessories/ (pos 18,1)
- "steampunk gear(s)" — 14 impr på tværs, pos 26–49 (dårligt matchet)
- ✅ Siden dækker "accessories"-intentionen fint; "gear"-queries rammer forside/kategorier.
- **Handling:** post 15 fik nu "steampunk accessories for costumes"-link (eksakt anchor-del). Post 10 + post 8 har allerede "steampunk accessories"-ankre.
- ⏳ IKKE fixet (mangler beslutning): titel/meta for post 30 matcher "accessories" ikke "gear" — Rank Math-metaværdier kan ikke læses/skrives via REST (rank_math_title/description = tomme via REST; /rankmath/v1/updateMeta-endpoint findes, men der er ingen verificeret test). Henning-valg: skal "steampunk gear" som keyword testes i titel (fx "Steampunk Accessories & Gear: …")? Kan også gøres i Rank Math GUI direkte.

## Fixet i denne runde (WP REST PUTs)

1. **Post 15 (/steampunk-costume-ideas/):** 2 links tilføjet:
   - "Building Any of These on a Budget": + sætning med eksakt anchor **"steampunk goggles diy"** → /diy-steampunk-goggles/
   - Budget-afsnit: + sætning med anchor **"steampunk accessories for costumes"** → /steampunk-accessories/
   - FAQ: + spørgsmål "What are some easy steampunk costume ideas?" med svar der linker /diy-steampunk-goggles/ + /steampunk-accessories/ — OG synkroniseret FAQPage JSON-LD (5→6 spørgsmål).
2. **Post 30 (/steampunk-accessories/):** "Where to Buy"-sektion fik eksakt anchor **"DIY steampunk costume"** → /how-to-make-a-steampunk-costume/ (post 30 havde kun costume-linket i FAQ/schema, nu 2 links).

## IKKE fixet (input / Henning-trin)

1. Rank Math-metas kan ikke læses/skrives pålideligt via REST (returnerer tomme) — "steampunk gear"-titeltest måske GUI-arbejde.
2. Featured-image/kategorien er fint; ingen flere sider kræver overskrifts-ændringer — h2-match på plads på alle 4.
3. "steampunk gears"-query (pos 26,9 fra forside) — forside PÅVIRKES af hele sitets gear-relevans; overvejes ved næste indholdsbatch.

## Overvågning (næste rutine 1/12)

Sammenlign pos på de 4 sider + de 5 under-threshold-sider. Forventet: /diy-steampunk-goggles/ → pos ≤ 6, /steampunk-costume-ideas/ → ≤ 10, costume → ≤ 10, accessories → ≤ 15.
