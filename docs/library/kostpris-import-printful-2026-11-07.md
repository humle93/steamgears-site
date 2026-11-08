# Kostpris fra Printful — hvad der må importeres, og hvad der ikke må

7/11 2026, plugin 7.46.0 (P1.17). Skrevet fordi shop-værktøjerne i `steamgears-autopilot`
(`woo-catalog`, `woo-propose`, `woo-apply`) kun vil regne med én kostpris: produktets eget
`_sgap_cost`-felt. Det felt var tomt på hele kataloget, så hver eneste prisprofil blev afvist
med rette — og 200 kostpriser skrevet i hånden er præcis den slags tal der overlever som fejl.

**Kort:** `woo-cost-import` fylder feltet fra Printfuls **synkroniserede varianter**, matchet på
Woo-id først og SKU derefter. Den skriver variantens egen pris, gætter aldrig, rører ikke en pris
ejeren selv har skrevet, og gemmer kilden ved siden af tallet.

## 1. Hvor tallet kommer fra

Printfuls `/sync/products` giver hvert synkroniseret produkt med en liste af `sync_variants`.
Hver variant bærer:

| Felt | Betydning her |
| --- | --- |
| `external_id` | Woo-produktets id, som Printful fik det ved synkroniseringen — det stærkeste match |
| `sku` | Woo-SKU'en, når butikken bruger SKU i stedet for id |
| `variant.price` | Printfuls pris **for varen** i butikkens valuta — det er den kostpris butikken betaler |
| `id` | Printfuls egen variant-id; bruges kun til at skrive kilden |

`variant.price` er den pris Printful opkræver for at producere og sende varen til kunden, altså
kostprisen. Den er **ikke** sat ud fra butikkens salgspris og kan derfor ikke forveksles med et
margin-gulv. Det var netop fejlen i den gamle bro: den regnede »gulvet« som 40 % af den pris den
skulle beskytte, så kontrollen aldrig kunne fejle.

## 2. Reglerne importen følger

1. **Id før navn.** Match på `external_id` (Woo-id), derefter SKU. Navne er upålidelige: to
   produkter kan hedde det samme, og en omdøbning må ikke flytte en kostpris.
2. **Tørkørsel som standard.** Uden `confirm=true` skrives intet — svaret er planen: hvilke
   produkter, hvilken pris, hvilket match.
3. **Ejeren vinder.** Findes der allerede en kostpris, meldes den `kept` med mindre
   `overwrite=true`. Et tal skrevet i hånden er et udsagn om virkeligheden, ikke en cache.
4. **Genkørsel er ufarlig.** Er prisen allerede den samme, meldes `unchanged` — ikke endnu en
   skrivning, så to kald i træk giver samme tilstand.
5. **Ingen pris, ingen kostpris.** En variant uden `variant.price` meldes `no_price` og røres
   ikke. En kostpris der kun ligner en kostpris er værre end ingen, fordi den får marginen til at
   se rigtig ud.
6. **Ukendt vare meldes, ikke gættes.** En vare Printful ikke kender er `unmatched`. Den skal
   oprettes i Printful eller have sin pris manuelt — ikke arve naboens.
7. **Flere varianter, laveste pris.** Har et produkt flere Printful-varianter (fx størrelser),
   gælder den **laveste** variantpris. Den første i listen ville overvurdere kostprisen for
   halvdelen af kataloget og dermed hæve gulvet uden grund.
8. **Variable produkter får begge niveauer.** Hver variation får sin egen kostpris, og
   forælderen får den laveste af børnene, så en prisprofil på forælderen også har et tal at regne
   fra.
9. **Kilden gemmes.** `_sgap_cost_source` indeholder fx
   `Printful import: sync variant 56 (matched on Woo id)`, så `woo-catalog` kan sige hvor prisen
   kom fra i stedet for at lade den se ud som ejerens eget tal.

## 3. Fælder

- **`external_id` er tekst, ikke tal.** Sammenlignes som streng (`(string) $product->get_id()`),
  ellers matcher `"7"` ikke `7`.
- **En variant kan mangle `variant`-objektet** (fx et synkroniseret produkt hvor
  Printful-varen er slettet). Derfor læses `$variant['variant']['price'] ?? null` og ikke
  `['variant']['price']`.
- **Prisen er pr. vare, ikke pr. ordre.** Fragt, skat og rabat er ikke med — marginen skal stadig
  dække dem, og standardmarginen på 45 % gør det.
- **`fresh` findes fordi cachen er seks timer.** Lige efter en prisændring i Printful er cachen
  forkert, og så er et tvunget genopslag hurtigere end at vente.
- **`limit` (maks. 500) er der for at et stort katalog ikke skal ramme en klients tidsgrænse.**
  Kør dernæst igen: `unchanged`-rækkerne koster kun læsning.

## 4. Hvad der er målt, og hvad der ikke er

Testet i `plugins/tests/mcp-woo.php` gennem `apply_filters('sgap_woo_printful_response', …)`,
altså uden netværk: tørkørsel, `confirm`, id-match, SKU-match, `kept`, `overwrite`, `unchanged`,
`no_price`, `unmatched`, variabelt produkt, `limit` og registreringen i `steamgears-tools`.
16 checks, smoke gik fra 627 til **643 PASS, 0 FAIL**.

**Ikke målt:** det levende kald mod Printful med butikkens egen nøgle. Det kræver ejerens token
under *Settings → Shop & feeds* og en butik med synkroniserede varer. Indtil det er kørt, er det
en påstand at sige at kataloget nu har kostpriser — det er kun påstanden at værktøjet skriver dem
rigtigt, der er testet.

## 5. Næste skridt

1. Gem Printful-token og butiks-id under *Settings → Shop & feeds*.
2. Kør `woo-cost-import` uden `confirm` og læs planen — særligt `unmatched` og `no_price`.
3. Kør igen med `confirm=true`.
4. Kør `woo-catalog` og se efter produkter uden kostpris eller under gulvet.
