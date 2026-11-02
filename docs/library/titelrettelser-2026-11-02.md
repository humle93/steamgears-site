# Titelrettelser — 2/11 2026

Fire live-titler bryder grænsen på 62 tegn fra 1/11-reglen. Målt med `seo_title_audit.py`
mod alle 121 URLs i sitemappen, cache-bustet, med HTML-entiteter afkodet (`&amp;` tæller som ét tegn).

**Status: ikke rettet.** Der er ingen skrivetilgang fra denne sandkasse (`WP_USER`, `WP_APP_PASS` og
`ROYAL_MCP_TOKEN` er alle usatte), så nedenstående er den præcise ændring, der skal laves — ikke en
påstand om at den er lavet. Tallene i parentes er tegn efter rettelsen.

| # | URL | Nu | Ny titel | Hvor |
| --- | --- | --- | --- | --- |
| 1 | `/start-here/` (side 1183) | 65 | `Start Here: Steampunk Outfits, Guides & Builds \| Steamgears` (59) | Rank Math-titel på siden |
| 2 | `/product/steampunk-aviator-goggles-retro-motorcycle-windproof/` | 67 | `Steampunk Aviator Goggles, Windproof \| Steamgears` (49) | Rank Math-titel på produktet |
| 3 | `/product/steampunk-overbust-corset-leather-lace-up-burlesque/` | 66 | `Steampunk Overbust Corset: Leather Lace-Up \| Steamgears` (55) | Rank Math-titel på produktet |
| 4 | `/product/victorian-vampire-tailcoat-brocade-gothic-jacket/` | 63 | `Victorian Vampire Tailcoat: Brocade Gothic \| Steamgears` (55) | Rank Math-titel på produktet |

## Hvorfor præcis disse

- **`/start-here/`**: «steampunk outfits» og «guides» bevares, fordi det er sidens søgeintention
  (indgang for begyndere). «DIY» flyttes ud af titlen og ligger fortsat på `/build/` (57 tegn) og på
  `/diy-steampunk-goggles/` — så søgeordet er stadig dækket på sitet, blot ikke to steder i samme titel.
  Alternativet der beholder «DIY» (`Start Here: Steampunk Outfits, DIY Builds | Steamgears`, 53) blev valgt
  fra, fordi det dropper «guides», som er den bredere indgang.
- **Produkttitlerne** mister bindestreger og den engelske «- Steamgears»-hale til fordel for `| Steamgears`,
  som allerede bruges på siderne. Det er samme mønster som resten af sitet, ikke en ny konvention.
- Ingen af de fire titler går under 30 tegn, som er den nedre grænse i auditten.

## Sådan gennemføres det

1. Sæt `WP_USER` + `WP_APP_PASS` som secrets (Settings → Secrets).
2. Ret Rank Math-titlen på de fire poster — enten i wp-admin eller via REST, hvis `rank_math_title` er
   eksponeret i `meta` for den pågældende post type.
3. Kør `python3 seo_title_audit.py` igen: forventet resultat er **0 titler over 62**.
   Scriptet er den kontrol, der afgør om rettelsen virkede — ikke en genlæsning af tabellen ovenfor.

## Kendte, ikke-blokerende fund fra samme måling

| Type | Antal | Kommentar |
| --- | --- | --- |
| Titler under 30 tegn | 20 | Mest kategorier og politik-sider (`Privacy Policy` 27, `Terms` 26, `size-guide` 23) |
| Meta under 140 tegn | 46 | Mange produkter ligger på 104–106 tegn, altså genereret af temaet frem for skrevet |
| Meta over 158 tegn | 38 | Produkter på 164–165 tegn; fem kategorier på 389–408 tegn (auto-genereret) |
| Ingen meta | 3 | To forfattersider + `/category/uncategorized/` |
| Soft-404 blandt sitemap-URLs | 0 | Sitemappen indeholder ingen døde sider — soft-404'en rammer kun ukendte URL'er |

De 20 korte titler og 84 meta-afvigelser er ikke rettet her: de kræver samme skriveadgang, og
kategori-/produktmeta hænger sammen med temaets auto-generering, som skal besluttes først (skriv dem
manuelt eller lad temaet gøre det). Det er en egen opgave, ikke en del af hub-indsættelsen.
