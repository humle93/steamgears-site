#!/usr/bin/env python3
"""Finish the 17 draft products: featured image, short description, copy fixes.

Reads the live draft snapshot (`.secrets/drafts-full.json`) so replacements match
byte for byte, then writes through Royal MCP. Dry run by default.

    python3 tools/deploy/finish_drafts.py            # show the plan
    python3 tools/deploy/finish_drafts.py --apply    # write
    python3 tools/deploy/finish_drafts.py --apply --publish
"""

from __future__ import annotations

import argparse
import json
import os
import re
import sys

ROOT = os.path.dirname(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
sys.path.insert(0, os.path.join(ROOT, ".secrets"))

DRAFTS = os.path.join(ROOT, ".secrets", "drafts-full.json")
SITE = "https://steamgears.eu"

GREETING = "Why you'll like it"

# ---------------------------------------------------------------- copy pieces

DELIVERY = (
    "<h3>Made to order &amp; delivery</h3>\n"
    "<p>Made to order: production takes <strong>3–7 business days</strong>. Delivery after "
    "ordering is <strong>6–10 business days</strong> in Denmark and <strong>7–12 business "
    "days</strong> in the rest of the EU. You get a tracking email when the parcel ships. If "
    "your order includes gear or accessories, they may arrive in a separate package — you pay "
    "shipping only once.</p>"
)

ATELIER_DELIVERY = (
    "<h3>Dispatch &amp; delivery</h3>\n"
    "<p>Prepared and dispatched within <strong>3–7 business days</strong>. Delivery after "
    "ordering is <strong>6–10 business days</strong> in Denmark and <strong>7–12 business "
    "days</strong> in the rest of the EU. You get a tracking email when the parcel ships. If "
    "your order includes print-on-demand pieces, they may arrive in a separate package — you "
    "pay shipping only once.</p>"
)

GPSR = (
    "<h3>Product safety (GPSR)</h3>\n"
    "<ul>\n"
    "<li><strong>Material:</strong> {material}</li>\n"
    "<li><strong>Producer:</strong> {producer}</li>\n"
    "<li><strong>Contact:</strong> cpaleadsis@gmail.com</li>\n"
    "<li><strong>EU responsible person:</strong> Steamgears. Postal address on request via the "
    '<a href="{site}/contact/">contact page</a>.</li>\n'
    "</ul>"
)


def gpsr(material: str, producer: str = "Steamgears — printed on demand by our production partners") -> str:
    return GPSR.format(material=material, producer=producer, site=SITE)


def collection_link(label: str, slug: str, second_label: str = "", second_slug: str = "") -> str:
    out = f'See the rest of the <a href="{SITE}/product-category/{slug}/">{label}</a>'
    if second_slug:
        out += f' and the other <a href="{SITE}/product-category/{second_slug}/">{second_label}</a>'
    return f"<p>{out}.</p>"


RESS = {
    940: {
        "image": 946,
        "short": "Hand-assembled brass pocket watch pendant with a visible movement, on a chain. "
                 "Numbered Atelier piece, EU 14-day returns.",
        "description": (
            "<p><strong>A seer's watch worn at the throat: an open brass case, a movement you can "
            "see working, and a chain that catches the candlelight when you lean across the "
            "table.</strong></p>\n"
            f"<h3>{GREETING}</h3>\n"
            "<ul>\n"
            "<li>A real mechanical movement behind glass — the mechanism is the ornament, not a "
            "picture of one</li>\n"
            "<li>Antique brass finish that settles into a costume instead of glaring out of it</li>\n"
            "<li>Wears over a waistcoat, a corset or a plain black shirt</li>\n"
            "</ul>\n"
            "<h3>Details</h3>\n"
            "<p>Part of the Atelier line: hand-assembled, numbered, and supplied on a chain so it "
            "can be worn as a necklace or carried in a pocket. The case opens to show the "
            "movement.</p>\n"
            f"{ATELIER_DELIVERY}\n"
            "<h3>Care</h3>\n"
            "<p>Keep it dry. Wipe the case with a soft cloth; brass darkens with wear, so polish "
            "it with a dry cloth if you want the bright finish back.</p>\n"
            f"{gpsr('Antique-brass-finish metal with a mechanical movement under glass', 'Steamgears')}\n"
            f"{collection_link('Clockwork collection', 'clockwork', 'Jewelry pieces', 'steampunk-jewelry')}"
        ),
    },
    941: {
        "image": 947,
        "short": "Brass skeleton pocket watch with an open movement under a glass lid, on a "
                 "matching chain. Numbered Atelier piece, EU 14-day returns.",
        "description": (
            "<p><strong>A skeleton watch with nothing to hide: every wheel and spring on show, in "
            "a brass case that looks like it has already flown once.</strong></p>\n"
            f"<h3>{GREETING}</h3>\n"
            "<ul>\n"
            "<li>Open-worked movement under glass — the reason to wear a skeleton watch at all</li>\n"
            "<li>Antique brass case and chain in the same finish, so the piece reads as one object</li>\n"
            "<li>The one item that turns a waistcoat into a character rather than an outfit</li>\n"
            "</ul>\n"
            "<h3>Details</h3>\n"
            "<p>The largest piece in the Atelier line: a pocket watch with a glass lid over an "
            "open-worked movement, and a chain in the same brass finish. Hand-assembled and "
            "numbered.</p>\n"
            f"{ATELIER_DELIVERY}\n"
            "<h3>Care</h3>\n"
            "<p>Keep it dry and away from perfume and salt water. Wipe the case and glass with a "
            "soft dry cloth; brass darkens with wear, so polish it if you want the bright finish "
            "back.</p>\n"
            f"{gpsr('Antique-brass-finish metal with an open-worked mechanical movement under glass', 'Steamgears')}\n"
            f"{collection_link('Steam &amp; Brass collection', 'steam-and-brass', 'Watches', 'steampunk-watches')}"
        ),
    },
    942: {
        "image": 948,
        "short": "Brass-gear charm bracelet, hand-assembled and numbered. Antique finish, EU "
                 "14-day returns.",
        "description": (
            "<p><strong>Small charms, big story: brass gears and dark links, like something found "
            "on a workshop bench and put on a wrist.</strong></p>\n"
            f"<h3>{GREETING}</h3>\n"
            "<ul>\n"
            "<li>Reads as a found object rather than jewellery — gears, a charm, and dark links</li>\n"
            "<li>Antique brass that sits just as well with everyday clothes as with a costume</li>\n"
            "<li>The lightest way into the Atelier line, and the easiest to wear daily</li>\n"
            "</ul>\n"
            "<h3>Details</h3>\n"
            "<p>Part of the Atelier line, hand-assembled and numbered, with each charm fixed so it "
            "hangs flat against the wrist. Check the images for the exact charm layout.</p>\n"
            f"{ATELIER_DELIVERY}\n"
            "<h3>Care</h3>\n"
            "<p>Keep it dry and put it on last, after perfume or lotion. Wipe with a soft dry "
            "cloth; brass darkens with wear, so polish it if you want the bright finish back.</p>\n"
            f"{gpsr('Antique-brass-finish metal charms on a metal link bracelet', 'Steamgears')}\n"
            f"{collection_link('Clockwork collection', 'clockwork', 'Jewelry pieces', 'steampunk-jewelry')}"
        ),
    },
    766: {
        "image": 771,
        "short": "Adjustable faux-leather utility belt with brass hardware and modular pouches. "
                 "Fits 71–112 cm, EU 14-day returns.",
        "description": (
            "<p><strong>Carry your gadgets, tools and potions in something that looks like it has "
            "been on a dozen expeditions.</strong></p>\n"
            f"<h3>{GREETING}</h3>\n"
            "<ul>\n"
            "<li>Modular loops and pouches — one belt covers a tinker, a captain or a plague doctor</li>\n"
            "<li>Antique brass buckles, rivets and reinforced stitching that survive a full "
            "convention weekend</li>\n"
            "<li>Turns a plain shirt and trousers into a finished silhouette without a single "
            "sewing skill</li>\n"
            "</ul>\n"
            "<h3>Details &amp; fit</h3>\n"
            "<p>Cruelty-free faux leather with heavy-duty antique brass hardware, adjustable waist "
            "<strong>28–44 inches (71–112 cm)</strong>. Universal pouch sizing fits most modern "
            "gadgets and small props; the images show the pouch layout.</p>\n"
            f"{DELIVERY}\n"
            "<h3>Care</h3>\n"
            "<p>Wipe with a damp cloth and let it dry flat. Do not soak it, and never put it in a "
            "washing machine.</p>\n"
            f"{gpsr('Cruelty-free faux leather with antique-brass-finish buckles, rivets and metal hardware', 'Steamgears')}\n"
            f"{collection_link('Accessories collection', 'accessories')}"
        ),
    },
    767: {
        "image": 935,
        "short": "Instant-download PDF pattern for a Victorian corset and vest, sizes XS–3XL. "
                 "Print at home, nothing shipped.",
        "description": (
            "<p><strong>Draft a Victorian corset and a matching vest from a pattern that has "
            "already done the maths.</strong></p>\n"
            f"<h3>{GREETING}</h3>\n"
            "<ul>\n"
            "<li>Multi-size pattern <strong>XS–3XL</strong> with measurement tables in centimetres "
            "and inches</li>\n"
            "<li>Printable on A4 or US Letter, tiled with clear match marks</li>\n"
            "<li>Instant download — no production time and no shipping</li>\n"
            "</ul>\n"
            "<h3>What you get</h3>\n"
            "<p>An instant PDF download with high-resolution vector lines, sizing markers, "
            "material requirements and step-by-step assembly instructions for both the corset and "
            "the vest.</p>\n"
            "<h3>Delivery</h3>\n"
            "<p>Digital product: the download link is available as soon as checkout is complete. "
            "Nothing is shipped, and there is no production time.</p>\n"
            "<h3>Printing</h3>\n"
            "<p>Print at 100% scale on A4 or US Letter, then tape the sheets together along the "
            "match marks before cutting. Plain paper is fine; heavier paper survives repeated "
            "use.</p>\n"
            f"{collection_link('Digital Downloads', 'digital-downloads')}"
        ),
    },
}

# Herbarium: keep the existing body, fix the trailing collection paragraph and
# state the print format / phone-model step that the drafts left out.
HERBARIUM_LINKS = {
    953: collection_link("Gaslight collection", "gaslight", "T-Shirts", "t-shirts"),
    954: collection_link("Gaslight collection", "gaslight", "T-Shirts", "t-shirts"),
    955: collection_link("Gaslight collection", "gaslight", "T-Shirts", "t-shirts"),
    956: collection_link("Gaslight collection", "gaslight", "T-Shirts", "t-shirts"),
    965: collection_link("Gaslight collection", "gaslight", "Posters &amp; Wall Art prints", "posters-prints"),
    966: collection_link("Gaslight collection", "gaslight", "Posters &amp; Wall Art prints", "posters-prints"),
    967: collection_link("Gaslight collection", "gaslight", "Posters &amp; Wall Art prints", "posters-prints"),
    968: collection_link("Gaslight collection", "gaslight", "Posters &amp; Wall Art prints", "posters-prints"),
    969: collection_link("Gaslight collection", "gaslight", "Phone Cases", "gothic-steampunk-phone-cases"),
    970: collection_link("Gaslight collection", "gaslight", "Phone Cases", "gothic-steampunk-phone-cases"),
    971: collection_link("Gaslight collection", "gaslight", "Phone Cases", "gothic-steampunk-phone-cases"),
    972: collection_link("Gaslight collection", "gaslight", "Phone Cases", "gothic-steampunk-phone-cases"),
}

DESIGN = {
    "953": ("Clockwork Rose", 949), "954": ("Brass Luna Moth", 950),
    "955": ("Raven Chronometer", 951), "956": ("Nightshade Apothecary", 952),
    "965": ("Clockwork Rose", 957), "966": ("Brass Luna Moth", 958),
    "967": ("Raven Chronometer", 959), "968": ("Nightshade Apothecary", 960),
    "969": ("Clockwork Rose", 961), "970": ("Brass Luna Moth", 962),
    "971": ("Raven Chronometer", 963), "972": ("Nightshade Apothecary", 964),
}

PRINT_REPLACEMENTS = [(
    "Printed to order on matte art paper and supplied unframed, so you can match the frame to "
    "the room.",
    "Printed to order on matte art paper in 30 × 40 or 50 × 70 cm, shipped in a rigid tube and "
    "supplied unframed, so you can match the frame to the room.",
)]

CASE_REPLACEMENTS = [(
    "Printed to order for the phone model selected above, with the print wrapped around a hard "
    'shell. Model availability is listed with the options — if your phone is not there, '
    f'<a href="{SITE}/contact/">write to us</a> and we will check.',
    "Printed to order for the phone you actually carry: add your model in the order notes at "
    "checkout and the print is wrapped around a hard shell made for it. If you are unsure "
    f'whether we cover your model, <a href="{SITE}/contact/">write to us</a> and we will check '
    "before it goes to print.",
)]


def short_for(pid: str) -> str:
    design, _ = DESIGN[pid]
    if pid.startswith("953") or pid in ("954", "955", "956"):
        return (f"Soft cotton unisex tee with the {design} engraving, printed to order. "
                "Sizes S–2XL, EU 14-day returns.")
    if pid in ("965", "966", "967", "968"):
        return (f"The {design} engraving on matte art paper, 30 × 40 or 50 × 70 cm. Printed to "
                "order, shipped in a tube.")
    return (f"Hard-shell phone case printed to order with the {design} engraving. Add your "
            "model in the order notes.")


def tail_replace(desc: str, new_tail: str) -> str:
    idx = desc.rfind("See the rest of the")
    if idx == -1:
        return desc
    start = desc.rfind("<p>", 0, idx)
    end = desc.find("</p>", idx)
    if start == -1 or end == -1:
        return desc
    return desc[:start] + new_tail + desc[end + 4:]


def main() -> int:
    ap = argparse.ArgumentParser()
    ap.add_argument("--apply", action="store_true")
    ap.add_argument("--publish", action="store_true")
    args = ap.parse_args()

    import mcp

    drafts = json.load(open(DRAFTS, encoding="utf-8"))
    sid, _ = mcp.connect()
    plan = []

    for pid in [f"{i}" for i in list(RESS) + [953, 954, 955, 956, 965, 966, 967, 968, 969, 970, 971, 972]]:
        pid_n = int(pid)
        current = drafts[pid]
        if pid_n in RESS:
            entry = RESS[pid_n]
            plan.append({
                "id": pid_n,
                "image": entry["image"],
                "short": entry["short"],
                "description": entry["description"],
                "replacements": [],
            })
            continue

        desc = current["description"]
        reps = list(PRINT_REPLACEMENTS) if pid_n in (965, 966, 967, 968) else list(CASE_REPLACEMENTS)
        reps = [(f, r) for f, r in reps if f in desc]
        plan.append({
            "id": pid_n,
            "image": DESIGN[pid][1],
            "short": short_for(pid),
            "description": tail_replace(desc, HERBARIUM_LINKS[pid_n]),
            "replacements": reps,
        })

    for item in plan:
        desc = item["description"]
        for find, repl in item["replacements"]:
            if find not in desc:
                raise SystemExit(f'{item["id"]}: replacement target not found')
            desc = desc.replace(find, repl)
        item["description"] = desc
        item["html_ok"] = desc.count("<p>") == desc.count("</p>") and desc.count("<ul>") == desc.count("</ul>")
        print(f'{item["id"]:>4} | img {item["image"]:>4} | {len(desc):>5} tegn | html {"ok" if item["html_ok"] else "FEJL"} | {item["short"][:60]}')
        if not item["html_ok"]:
            raise SystemExit("unbalanced HTML - stop")

    if not args.apply:
        print(f"\n{len(plan)} varer planlagt. Kør med --apply for at skrive.")
        return 0

    for item in plan:
        pid = item["id"]
        r = mcp.call("wp_set_featured_image", {"post_id": pid, "media_id": item["image"]}, sid)
        if (r.get("result") or {}).get("isError"):
            print(pid, "billede FEJL", mcp.text_of(r)[:200])
        r = mcp.call("wc_update_product", {
            "id": pid,
            "short_description": item["short"],
            "description": item["description"],
        }, sid)
        err = (r.get("result") or {}).get("isError")
        print(pid, "opdateret" if not err else f"FEJL {mcp.text_of(r)[:200]}")

    if args.publish:
        for item in plan:
            r = mcp.call("wc_update_product", {"id": item["id"], "status": "publish"}, sid)
            err = (r.get("result") or {}).get("isError")
            print(item["id"], "udgivet" if not err else f"FEJL {mcp.text_of(r)[:200]}")

    return 0


if __name__ == "__main__":
    raise SystemExit(main())
