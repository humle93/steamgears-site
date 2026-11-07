# Merchant-schema: produktionstid — og det krav der ikke kan opfyldes

S4.13's kode lå allerede i `wordpress/mu-plugins/sgap-product-schema.php` med 19 grønne påstande, så
denne runde handlede ikke om at skrive filteret, men om at **måle det mod Googles nuværende krav** i stedet
for mod min hukommelse. Det gav to ændringer og én blokering, der er værd at kende præcist.

Kilde: [Merchant listing (`Product`, `Offer`) structured data](https://developers.google.com/search/docs/appearance/structured-data/merchant-listing),
Google Search Central, læst 7/11 2026.

## 1. Fund: fragt-linjen kan ikke kvalificere uden en pris

`OfferShippingDetails` har ifølge dokumentationen **tre påkrævede felter**, hvis fragtoplysningen skal
kunne vises: `deliveryTime`, `shippingDestination` **og `shippingRate`** — hvor `shippingRate.value` eller
`shippingRate.maxValue` plus `shippingRate.currency` skal angives.

Fragtprisen blev bevidst udeladt 2/11, fordi sitet skriver »Shipping costs are shown before you confirm
your order« og ikke har en fast pris. Den beslutning står ved magt: at skrive `0` (gratis fragt) eller
`4.95` ville være et løfte butikken ikke har givet, og et forkert `shippingRate` er værre end intet.

Konsekvensen skal siges højt, for den er ikke indlysende:

| Del | Status |
| --- | --- |
| Markupen er gyldig Schema.org | Ja — `shippingDetails` med `deliveryTime` og `shippingDestination` er velformet |
| Returretten kvalificerer | Ja — `applicableCountry` + `returnPolicyCategory` er de krævede felter, og de er der |
| **Fragt-linjen i merchant listings** | **Nej** — den kræver `shippingRate`, som ikke findes |

Så S4.13 kan ikke blive «fuldt udfyldt» ved at skrive mere kode. Det kræver enten en fast fragtpris fra
ejeren eller en Merchant Center-konfiguration, hvor fragten sættes dér i stedet. Det er en ejerbeslutning,
ikke en schema-fix.

## 2. Fund: produktionstiden hører i `handlingTime`, ikke i `transitTime`

Dokumentationen lader `deliveryTime` indeholde **både** `handlingTime` (tiden fra ordre til varen forlader
lageret) og `transitTime` (tiden derefter), og den siger eksplicit, at man ikke må angive mere end én
`deliveryTime`.

Sitet oplyser selv begge led på `/shipping/`: »**Production:** your item is printed and finished — 3–7
business days for printed goods«, derefter 6–10 hverdage i DK og 7–12 i resten af EU. Den gamle markup lod
`transitTime` være hele leveringstiden og sprang produktionen over, så en kunde i DK så 6–10 dage, hvor
sitets egen tekst lover 3–7 **plus** 6–10.

Rettelsen: `handlingTime` 3–7 dage på begge destinationer, med samme `unitCode: DAY` som `transitTime`.
Ingen nye tal er opfundet — begge værdier er citater fra sitets egen side.

## 3. Fund: `returnPolicyCountry` var ikke det, det så ud som

En nyhedsartikel fra marts 2025 skrev, at `returnPolicyCountry` nu er et påkrævet felt i
`MerchantReturnPolicy`. Det gælder **organisation-niveau**. På offer-niveau, som er det dette filter skriver,
er de krævede felter `applicableCountry` og `returnPolicyCategory` — og `applicableCountry` har været der
siden 2/11.

Det er præcis den slags der ikke må bygges på en overskrift: havde jeg lagt `returnPolicyCountry` ind, var
den blevet ignoreret eller behandlet som en fejl, og dokumentationen ville have påstået noget forkert.
Derfor er der **ingen** ændring af returretten i denne runde, kun en tilføjelse til testen der holder
`returnMethod` og fraværet af `returnFees` fast.

## 4. Hvad der blev ændret

| Fil | Ændring |
| --- | --- |
| `wordpress/mu-plugins/sgap-product-schema.php` | `handlingTime` 3–7 dage i `deliveryTime` på begge destinationer (konstanterne `SGAP_HANDLING_DAYS_MIN`/`_MAX`), `itemCondition: NewCondition` på Offer når feltet mangler, og hovedkommentaren forklarer nu `shippingRate`-konsekvensen |
| `tools/schema_filter_test.php` | 19 → **30 påstande**: produktionstid i DK og EU, `unitCode` på begge, kun ét `deliveryTime` pr. destination, `itemCondition`, at en allerede sat `itemCondition` ikke overskrives, og at `returnFees` stadig ikke er opdigtet |
| `tools/natlige-vaern.sh` | `produkt-schema` er nu **værn nr. 22** — testen fandtes siden 2/11, men kørte ikke i den natlige kontrol, så en regression i filteret ville være gået ubemærket hen |

`itemCondition` er `NewCondition` og ikke en påstand om stand: butikken er print-on-demand og sælger ikke
returvarer. Feltet sættes kun hvis det mangler, så et andet filter eller et felt fra Woo kan vinde.

## 5. Verifikation

```
./.tools/php -l wordpress/mu-plugins/sgap-product-schema.php   # rent
./.tools/php tools/schema_filter_test.php                      # 30 PASS / 0 FAIL
PHP_BIN=./.tools/php bash tools/natlige-vaern.sh               # 22/22 grønne
```

Testen kører mod `tools/fixtures/product-schema-before.json`, som er et rigtigt JSON-LD-udsnit fanget fra
en produktside 2/11 — den kan altså ikke bestå af sig selv.

**Ikke verificeret:** filteret er stadig **ikke** på det levende site. Sessionen har ingen WordPress-adgang,
så hverken markupen eller Googles læsning af den er set. Når filen er lagt i `wp-content/mu-plugins/` og
cachen er ryddet, er verifikationen:

```
python3 tools/schema_check.py            # alle produkter: brand + returret + levering
```

Den forventes at give `fuldt udfyldt: 39` for brand, returret og leveringstid. **Fragt-linjen bliver ikke
fuld**, uanset hvad — se §1.

## 6. Procenter

| Modul | Før | Efter | Δ | Grundlag |
| --- | --- | --- | --- | --- |
| S4.13 merchant schema | 0 % | 40 % | +40 | 0,1 af 0,25 d — krav målt mod Googles dokumentation, produktionstid og `itemCondition` bygget, 11 nye påstande, værn tilføjet; de sidste 0,15 d er deploy og live-verifikation |
| S4 — butik og salgsklar | 26,7 % | 28,6 % | +1,9 | 1,5 af 5,25 d |
| Site-projektet | 64,0 % | 64,4 % | +0,4 | 15,65 af 24,3 d (S0 2,15/2,85 · S1 2,15/4,6 · S2 7,35/8,05 · S3 2,5/3,55 · S4 1,5/5,25) |
