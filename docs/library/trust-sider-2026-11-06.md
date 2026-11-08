# Trust-sider: /shipping/ og /returns/ — copy og kilder

*Skrevet 6/11 2026 til roadmap S2.2. Begge sider er bygget **udelukkende** af fakta der allerede står
på sitets egne, publicerede sider — ingen nye løfter om leveringstid, fragtpris eller fortrydelse.*

## Kilder (læst 6/11 2026 over Novamira-MCP)

| Side | ID | Hvad den leverer |
| --- | --- | --- |
| `/delivery/` | 582 | Produktion 3–7 hverdage på trykte varer; DK 6–10 hverdage i alt; resten af EU 7–12; fragtpris og -valg vises i checkout; sporing på mail; delte forsendelser, fragt betales kun én gang |
| `/refund_returns/` | 26 | 14 dages fortrydelsesret; kontakt før retur (varer sendes fra forskellige lagre); returfragt betales af kunden med mindre varen er defekt; ubrugt og i original emballage; refusion inden 14 dage efter skriftlig fortrydelse, samme betalingsmetode, inkl. standardfragt; hygiejne-undtagelse for hudnær beklædning; 2 års reklamationsret; svar inden 2–3 hverdage |
| `/terms/` | 581 | Leveringstider står på produktsiden og på `/delivery/`; 24 måneders reklamationsret |

**Hvorfor to nye sider og ikke bare 301:** Redirection har ingen gruppe på sitet (målt 2/11), så en
301 kunne ikke oprettes over MCP. Siderne er derfor skrevet som **opgave-orienterede** indgange
("hvornår kommer min pakke", "hvordan sender jeg noget retur"), ikke som kopier af politik-siderne, og
hver af dem peger på den fulde politik. Det holder dem ude af tynd-duplikat-territorium.

---

## `/shipping/`

**Title:** Shipping — Timelines, Tracking and Split Deliveries
**Rank Math title:** Shipping — Timelines, Tracking and Split Deliveries
**Rank Math description:** Made-to-order production takes 3–7 business days, then 6–10 days in Denmark and 7–12 in the rest of the EU. Tracking, split parcels and what to check first.
**Focus keyword:** shipping

```markdown
Most Steamgears pieces are **made to order**: nothing sits in a warehouse waiting, so
your item is produced after you order it. That is why the timeline has two parts instead
of one.

## Production, then delivery

- **Production:** your item is printed and finished — 3–7 business days for printed
  items (t-shirts, hoodies).

- **Delivery:** the parcel travels to you — added on top, depending on your country.

Total delivery time after ordering:

- **Denmark:** 6–10 business days

- **Rest of the EU:** 7–12 business days

Gear and accessories can ship from a partner warehouse, so their timeline may differ
from a printed item in the same order. The exact shipping options and prices for your
address are always shown at checkout before you pay.

## Why your order may arrive in more than one parcel

When you order several items, they can be produced and shipped by different partners.
That means two parcels and two tracking links — but **you only pay shipping once**.

## Tracking

As soon as your parcel ships you get an e-mail with tracking information. If tracking
has not updated for a few days, that is usually a handover between carriers rather than
a lost parcel; write to us and we will chase it.

## Before you order

Sizing is the most common reason a garment comes back, and it is the easiest to avoid:
measure a piece you already own and compare it with the [size guide](/size-guide/).

## The full policy

Delivery times, rates and the legal wording live on the [Delivery & Shipping
page](/delivery/). For returns and the 14-day right of withdrawal, see
[Returns](/returns/) and the [Refund & Returns policy](/refund_returns/).

## Questions

Write to us through the [contact page](/contact/) — we reply within 2–3 business days.
```

---

## `/returns/`

**Title:** Returns — Start a Return, Refunds and Faulty Items
**Rank Math title:** Returns — Start a Return, Refunds and Faulty Items
**Rank Math description:** How to return an item to Steamgears: the 14-day right of withdrawal, who pays return shipping, when the refund arrives, and what to do with a faulty item.
**Focus keyword:** returns

```markdown
Something does not fit, or is not what you expected? Here is the shortest path from "I
want to return this" to "the money is back".

## 1. The 14-day right of withdrawal

If you order as a consumer in the EU, you can cancel within **14 days of receiving the
goods** — no reason needed. The step-by-step version, including the standard EU form, is
on the [Right of withdrawal page](/right-of-withdrawal/).

## 2. Tell us before you send anything

E-mail **cpaleadsis@gmail.com** with your order number and a clear statement that you
want to cancel or return. We send you the return instructions and the correct return
address, because items ship from different warehouses — a parcel sent to the wrong
address can take weeks to find its way back.

Please do not send an item back before you have heard from us.

## 3. Pack it as it arrived

Items must be unused and in their original packaging. You are only liable for any loss
of value caused by handling beyond what is necessary to check the item — trying a
garment on is fine, wearing it to a party is not.

## Who pays the return shipping?

You do, unless the item is faulty or we sent the wrong thing. In that case we cover it.

## When the refund arrives

We refund **within 14 days of receiving your withdrawal notice**, using the same payment
method you paid with. The refund covers the purchase price and the standard shipping
cost of the original order. We may hold the refund until the goods are back with us, or
until you have sent proof that you returned them.

## The one exception: hygiene

Clothing worn in direct contact with the skin — including corsets with intimate contact
areas — cannot be returned once the hygiene seal is removed, unless the item is faulty.
This is a hygiene rule, not a judgement call.

## Faulty or wrong item

Contact us within a reasonable time with your order number and photos of the problem. We
send a replacement or refund, and your statutory rights apply: Danish consumer law gives
a **2-year complaint right** on manufacturing defects.

## Avoid the return entirely

Most returns are sizing. Compare a garment you already own with the [size guide](/size-
guide/) before ordering — and if you are between two sizes, write to us and we will tell
you which one the piece is cut for.

## The full policy

The legal wording is on the [Refund & Returns policy](/refund_returns/) page, and
shipping times are on [Shipping](/shipping/) and [Delivery & Shipping](/delivery/).

## Questions

E-mail **cpaleadsis@gmail.com** or use the [contact page](/contact/). We reply within
2–3 business days.
```

---

## Sådan blev siderne lagt ind

Oprettet over Novamira-MCP med `miriamschwab/create-post` (`post_type: page`), Markdown konverteret til
native Gutenberg-blokke server-side, og Rank Math-felterne sat i samme kald:

```
rank_math_title        = sidens titel
rank_math_description  = beskrivelsen ovenfor
rank_math_focus_keyword = shipping / returns
```

Begge sider er oprettet som **kladde** først og publiceret efter indholdskontrol, så en fejl i
Markdown-konverteringen ikke nåede live.
