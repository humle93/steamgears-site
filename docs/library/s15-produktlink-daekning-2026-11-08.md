# S1.5 målt igen 8/11 — 29 af 30 indlæg har et /go/-link, men 33 af 45 id'er er kladder

_Målt live på steamgears.eu 8/11 2026 gennem Novamira (`wp eval`), efter en konvertering af direkte
`/product/`-links til `/go/<id>/`. S1.5 er **ikke** lukket: dækningen er der, men målene er ikke offentlige._

## Hvad der blev ændret

30 publicerede indlæg blev gennemgået. **21 manglede et `/go/`-link** (roadmappen målte 17 den 6/11, fordi
tre indlæg er kommet til siden). To ting blev gjort:

1. **91 direkte `/product/<slug>/`-links i 13 indlæg blev konverteret til `/go/<produkt-id>/`** — samme
   destination, men nu gennem sporingsvejen. `/go/<id>/` uden en kø-række slår op i
   `SGAP_Tracking::product_target()`, så et produkt-id virker som short link uden at der skal oprettes en
   kø-række. Verificeret: `/go/477/` → 302 → `/product/copper-pilot-goggles/`,
   `/go/493/` → 302 → `/product/wine-velvet-frock-coat/`, `/go/941/` → 302 → `/product/the-aeronaut-pocket-watch/`.
2. **Syv indlæg uden ét eneste produktlink fik et kort «Shop the look»-afsnit** med 1–3 `/go/`-links
   (10, 14, 15, 30, 974, 989, 994). Teksten er skrevet til det enkelte indlæg — ingen fælles skabelon.

Resultat: **29 af 30 publicerede indlæg har nu mindst ét `/go/`-link** (før: 9 af 30).

## Fælden: linket peger på et produkt, kunderne ikke kan købe

En gennemgang af **alle 45 unikke `/go/`-id'er** i de publicerede indlæg viste, at **33 peger på et
`draft`-produkt** — altså sider der ikke er offentligt tilgængelige:

| Produkt-id | Status | Eksempel |
| --- | --- | --- |
| 475, 479, 481, 485, 487, 497 | `draft` | goggles, top hatte, corset, piratfrakke |
| 553, 555, 559, 561, 563, 565, 567 | `draft` | t-shirts (hummingbird, nautilus, fox, …) |
| 719, 721, 728, 766, 767, 809 | `draft` | postere, bælte, PDF-mønster, tjekliste |
| 393, 395, 397, 405, 409, 411 | `draft` | hoodies (compass, sun, skull, owl, corset, heart) |
| 942, 953, 955, 956, 968, 969, 971, 972 | `draft` | smykker og Herbarium-serien |

Kun **12 af 45** id'er peger på et publiceret produkt eller en kø-række. Det betyder, at et link som
`/go/487/` svarer 302 til `?post_type=product&p=487` — et produkt, en besøgende ikke kan se eller købe.
Linkdækning uden en offentlig destination er ikke en lukket S1.5.

## Hvorfor det ikke er rettet her

At gøre et `draft`-produkt offentligt er en ejerbeslutning: prisen, lagerstatus og billederne skal være
endelige, og et forkert offentliggjort produkt kan sælges. Alternativet — at pege hvert link om til et
*andet* produkt — er en redaktionel beslutning om, hvad det enkelte indlæg skal sælge, og kan ikke gættes
maskinelt uden at ændre indholdets betydning.

## Hvad der blev bogført

**S1.5: 0,6 af 1,0 d** — konverteringen og de syv afsnit er leveret og målt (29/30). De sidste **0,4 d**
venter på, at de pågældende produkter bliver publiceret (eller at linkene bevidst peges om), hvorefter
`/go/`-svarene skal måles igen: hvert `/go/<id>/` skal give 302 til en **200-side**.
