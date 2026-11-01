# Produkttekster — 39 produkter — 1/11 2026

Batch 3 i [om-maerkning-dark-academia-2026-11-01.md](om-maerkning-dark-academia-2026-11-01.md). Alle 39 produkter har fået ny `description` og `short_description` lagt ind via `wc_update_product`.

## Hvad teksten indeholder nu

Én struktur for alle, så kunden møder det samme uanset hvor de lander:

1. **Hook** — én sætning om motivet, skrevet pr. produkt (39 forskellige, ikke en skabelon)
2. **Why you'll like it** — tre punkter pr. varefamilie
3. **Details & fit** — materiale og pasform, med måletabel (S–2XL, chest/length i cm) på tøj
4. **Made to order & delivery** — produktion 3–7 hverdage, levering 6–10 hverdage i DK / 7–12 i resten af EU, tracking-mail, gear kan komme separat, porto betales kun én gang
5. **Care** — 30 °C, vrangen ud, ikke tørretumbler, ikke stryge på trykket
6. **Product safety (GPSR)** — materiale, producent, kontakt, EU-ansvarlig
7. **Kollektionslink** — til den relevante af de seks nye kollektioner

Gennemsnitlig længde faldt fra ca. 2.900 til ca. 2.000 tegn for tøj, og de tidligere dubletter (»Details & fit« + »Size Guide & Fit« + »Before You Order« + »The Steamgears Promise« + »More Questions« sagde delvist det samme) er væk. Intet faktum er gået tabt: måletabellen og materialebeskrivelserne er de samme som før.

## Fakta og hvor de kommer fra

Alt indhold er hentet fra siten selv — intet er opfundet:

| Fakta | Kilde |
|---|---|
| Produktion 3–7 hverdage | `/delivery/` og `/faq/` |
| Levering 6–10 hverdage DK, 7–12 dage resten af EU | `/delivery/` |
| Tracking-mail ved afsendelse | `/delivery/` |
| Gear kan ankomme separat, porto betales én gang | `/delivery/` |
| 14 dages fortrydelsesret, uindviet/vasket | `/faq/` og `/refund_returns/` |
| 30 °C, vrangen ud, ingen tørretumbler | `/faq/` |
| S–2XL-måletabel (chest/length i cm) | de eksisterende produktbeskrivelser |
| 30 × 40 og 50 × 70 cm, mat papir, sendes i rør | den eksisterende plakatbeskrivelse |

**Rettet undervejs:** 475 (Aviator Goggles) havde `short_description` = »Ships in 2-3 weeks«, hvilket modsagde både `/delivery/` og de 3–7 hverdage, der stod i samme produkts egen beskrivelse. Den er nu erstattet af de faktiske tal.

## GPSR-blokken er ikke komplet

Blokken indeholder materiale, producent, kontaktadresse og en henvisning til kontaktformularen — men **ikke en fysisk adresse**, fordi selskabsnavn, CVR og adresse stadig mangler fra ejer (samme blokering som i [butiksaudit-2026-11-01.md](butiksaudit-2026-11-01.md)).

GPSR (EU 2023/988) kræver producentens navn, varemærke, **postadresse** og e-mail. Linjen »Postal address on request via the contact page« er sand og bedre end ingenting, men den opfylder ikke kravet. Så snart CVR og adresse foreligger, skal fire linjer skiftes på alle 39 produkter — det er et script, ikke manuelt arbejde.

## Risiko: Printify kan overskrive det hele

Teksterne er skrevet i WooCommerce. Hvis Printify har katalog-synkronisering kørende, kan næste push erstatte både titler og beskrivelser. De tidligere beskrivelser indeholdt håndskrevne sektioner (»The Steamgears Promise«, FAQ), som Printify ikke genererer — det tyder på, at sync ikke har rørt beskrivelserne hidtil. **Det er ikke verificeret.** Hvis det sker, er teksten i dette dokument og i `copygen`-logikken kilden til at genskabe den.

## Verifikation

- 39/39 opdateret, 0 fejl
- Alle 39 har GPSR-blok, produktionstid, leveringstid og kollektionslink (tjekket via `wc_get_product` efter skrivning)
- 809 (gratis PDF) har en digital variant: ingen produktion, ingen levering, download ved gennemført køb
- HTML overlevede `wc_update_product` intakt (h3, ul, table)
- W3TC purget; tre produktsider tjekket live — 200 og alle sektioner synlige
