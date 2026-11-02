#!/usr/bin/env python3
"""Publicerer de fire manglende hub-sider (S2.1) til steamgears.eu.

Copy og struktur kommer fra docs/library/hub-blueprint-2026-11-01.md §3.3–§3.6.
Scriptet gør tre ting:

  --dry-run   (standard) validerer alt uden netværk: titel- og meta-længder,
              ordantal, H1, og at hvert internt link står i den verificerede
              URL-liste. `--check-links` crawler dem også.
  --apply     opretter/opdaterer de fire sider som KLADDE via WP REST.
  --publish   publicerer i blueprintets rækkefølge: børn før hub.

Auth: WP_USER + WP_APP_PASS (application password) i miljøet, ellers
~/.gemini/steamgears_auth.json — samme kilde som seo_agent.py bruger.
"""

import argparse
import base64
import json
import os
import re
import sys
import urllib.error
import urllib.parse
import urllib.request

WP_SITE = "https://steamgears.eu"
WP_USER = os.getenv("WP_USER", "")
WP_APP_PASS = os.getenv("WP_APP_PASS", "")
AUTH_FILE = os.path.expanduser("~/.gemini/steamgears_auth.json")

# URLs der er målt 200 (1/11-audits + crawl af de to live hubs 2/11).
# Hub-siderne selv er med, så børnene kan pege på dem.
VERIFIED = {
    "/start-here/", "/build/", "/inspiration/", "/build/corsets/",
    "/build/jackets/", "/build/props/", "/shop/", "/faq/", "/about/",
    "/journal/", "/delivery/", "/size-guide/", "/refund_returns/",
    "/steampunk-costumes/", "/steampunk-halloween/", "/steampunk-cosplay-gallery/",
    "/steampunk-cosplay-for-beginners/", "/steampunk-cosplay-on-a-budget/",
    "/casual-steampunk-outfits/", "/closet-steampunk-outfits/",
    "/steampunk-costume-ideas/", "/steampunk-outfit-ideas-for-women/",
    "/steampunk-outfit-ideas-men/", "/steampunk-couple-costumes/",
    "/steampunk-vs-dieselpunk/", "/what-is-dieselpunk/", "/what-is-gaslamp-fantasy/",
    "/best-steampunk-books/", "/the-clockwork-herbarium-short-story/",
    "/the-clockwork-seer-short-story/",
    "/the-last-flight-of-the-meridian-short-story/",
    "/diy-steampunk-goggles/", "/diy-vs-buy-steampunk-goggles/",
    "/how-to-make-a-steampunk-costume/", "/3d-print-steampunk-parts/",
    "/sustainable-steampunk/", "/steampunk-accessories/", "/dieselpunk-costume/",
    "/how-to-build-a-steampunk-costume-for-under-100-complete-2027-workshop-plan/",
    "/product-category/steampunk-goggles/", "/product-category/accessories/",
    "/product-category/posters-prints/", "/product-category/hoodies/",
    "/product-category/t-shirts/", "/product-category/dark-academia/",
    "/product-category/gothic-victorian/", "/product-category/natural-history/",
    "/product-category/vintage-maritime/", "/product-category/witchy-tarot/",
    "/product-category/steampunk-capsule/", "/product-category/airship-captain/",
    "/product-category/victorian-inventor/",
}

# --------------------------------------------------------------------------
# Copy. post_title er H1, seo_title er <title>, meta er description-feltet.
# Rækkefølgen er publiceringsrækkefølgen: hub først, børn derefter.
# --------------------------------------------------------------------------

PAGES = [
    {
        "slug": "inspiration",
        "parent": None,
        "post_title": "Inspiration: Looks, Stories and Collections",
        "seo_title": "Steampunk Inspiration: Looks, Stories & Collections",
        "meta": "Real steampunk looks, the stories behind the designs, and six collections to browse when you know the mood but not the piece. Browse by mood, then by piece.",
        "content": """
<p>Inspiration is cheaper than gear and it lasts longer. Most people buy the wrong goggles because they picked the object before they picked the look. Start with a look you would actually wear on a Tuesday, then work backwards to the pieces that make it.</p>

<h2>The gallery</h2>
<p>Costumes built by readers and by us, shot in daylight instead of a studio, because that is the light you will actually be standing in. If you want to see how a corset reads next to a wool coat rather than next to a product page, this is the fastest place to start.</p>
<p><a href="/steampunk-cosplay-gallery/">Cosplay gallery</a> · <a href="/steampunk-costume-ideas/">Costume ideas</a> · <a href="/steampunk-couple-costumes/">Couple costumes</a></p>

<h2>Six collections, six moods</h2>
<p>Each collection groups the designs that share a palette, so you can browse by atmosphere instead of by product type. Pick the mood first; the individual pieces are easy once the mood is settled.</p>
<ul>
<li><strong>Dark Academia</strong> — ink, brass instruments, library light. <a href="/product-category/dark-academia/">Browse Dark Academia</a></li>
<li><strong>Gothic &amp; Victorian</strong> — high collars, mourning jewellery, deep reds. <a href="/product-category/gothic-victorian/">Browse Gothic &amp; Victorian</a></li>
<li><strong>Natural History</strong> — specimens, ferns, cabinet labels. <a href="/product-category/natural-history/">Browse Natural History</a></li>
<li><strong>Vintage Maritime</strong> — airships, charts, harbour towns. <a href="/product-category/vintage-maritime/">Browse Vintage Maritime</a></li>
<li><strong>Witchy &amp; Tarot</strong> — cards, candlelight, silver. <a href="/product-category/witchy-tarot/">Browse Witchy &amp; Tarot</a></li>
<li><strong>Steampunk Capsule</strong> — the classic look, nothing experimental. <a href="/product-category/steampunk-capsule/">Browse the capsule</a></li>
</ul>

<h2>Stories</h2>
<p>Two of the designs come with fiction attached. <em>The Clockwork Herbarium</em> follows a botanist cataloguing plants that should not exist, and reading it changes what the prints look like on a wall — the ferns stop being decoration and start being evidence.</p>
<p><a href="/the-clockwork-herbarium-short-story/">The Clockwork Herbarium</a> · <a href="/the-clockwork-seer-short-story/">The Clockwork Seer</a> · <a href="/the-last-flight-of-the-meridian-short-story/">The Last Flight of the Meridian</a> · <a href="/journal/">All journal posts</a></p>

<h2>Adapt a look you already own</h2>
<p>Most steampunk outfits are one modern garment away from working. Swap sneakers for boots, add a waistcoat over the shirt you already have, and put a watch on a chain instead of on your wrist. That is usually the whole difference between "dressed up" and "in costume".</p>
<p><a href="/casual-steampunk-outfits/">Casual steampunk outfits</a> · <a href="/closet-steampunk-outfits/">Closet steampunk</a> · <a href="/steampunk-outfit-ideas-men/">Outfit ideas for men</a> · <a href="/steampunk-outfit-ideas-for-women/">Outfit ideas for women</a></p>

<h2>Where to wear it</h2>
<p>Conventions, maker fairs, Halloween, photo walks. A look that never leaves the house does not improve, and the deadline of a real event is what forces the last three decisions.</p>
<p><a href="/steampunk-halloween/">Halloween hub</a> · <a href="/steampunk-cosplay-for-beginners/">Cosplay for beginners</a></p>

<h2>Then go build it</h2>
<p>Once you know the mood, the <a href="/build/">build hub</a> splits the outfit into six parts and tells you which one carries the look. New to the style entirely? Start at <a href="/start-here/">Start Here</a>.</p>
""",
    },
    {
        "slug": "corsets",
        "parent": "build",
        "post_title": "Steampunk Corsets: How to Choose One That Fits",
        "seo_title": "Steampunk Corsets: How to Choose One That Fits",
        "meta": "Overbust or underbust, steel busk or stretch panel, and how to measure yourself once so the corset fits the first time. Skip the returns queue.",
        "content": """
<p>A corset changes the silhouette more than anything else you can put on, and it is the item most often bought in the wrong size — usually because the buyer compared their body measurements to a garment that was measured flat. This page is about fit and construction, not about styling.</p>

<h2>Overbust or underbust</h2>
<p>An overbust corset carries the whole look on its own: it replaces the top, so the character is decided the moment you put it on. It suits the Victorian naturalist and anyone dressing for a salon or a portrait.</p>
<p>An underbust sits below the bust and layers over a shirt. It is the more flexible piece, because the shirt above it can change the character without changing the corset — which is why it suits the tinkerer and the airship captain. If you only buy one, buy the underbust, then decide later whether you want the overbust for photographs.</p>

<h2>What the boning actually does</h2>
<p>A steel busk at the front and spiral bones through the body hold a shape and distribute pressure. A stretch panel smooths the line but does not shape it: it behaves like firm underwear, not like a corset.</p>
<p>Listings are not always honest about which of the two you are buying. If the description does not say steel busk, spiral bones or boning count, assume the cheaper construction — and expect the silhouette to be softer than the photo.</p>

<h2>Measure once, in three places</h2>
<p>You need three numbers, taken with a soft tape and no clothes in the way:</p>
<ol>
<li><strong>Underbust</strong> — around the ribcage, directly under the bust.</li>
<li><strong>Natural waist</strong> — the narrowest point, usually just above the navel. Do not suck in.</li>
<li><strong>Torso length</strong> — from the underbust line down to the top of the lap, measured at the side.</li>
</ol>
<p>Then compare those numbers to the garment's flat measurements, doubled — not to your body measurement plus a guess. A corset that is 2 cm too small at the waist does not break in; it just becomes the one you never wear. Cross-check the shop's own table on the <a href="/size-guide/">size guide</a> before you order.</p>

<h2>What to wear underneath</h2>
<p>A thin cotton layer, no bulk, nothing that bunches at the waist. A corset liner costs less than a return and much less than a corset you stop wearing because it chafes. Skip anything with an elastic waistband — it will fold exactly where the corset presses.</p>

<h2>Wearing it for a whole day</h2>
<p>Lace it closed at the waist first, then work upwards and downwards. Leave the last two centimetres open at the top and at the bottom: that is what lets you sit, eat and breathe. If you cannot take a full breath, it is too tight — that is not a stage of breaking it in, it is a fit problem.</p>
<p>For a first event, wear it for two hours at home before you wear it for eight hours in public. You will find the pressure point in private instead of in a queue.</p>

<h2>Care</h2>
<p>Spot clean, dry flat, never machine wash, never hang by the laces. Sweat is the enemy of both the boning channels and the fabric, so air it out after every wear rather than washing it.</p>

<h2>The corsets in the catalogue</h2>
<p>Seven corsets are in the catalogue at the moment, mostly in the Victorian and Gothic palettes. Start with the <a href="/product-category/gothic-victorian/">Gothic &amp; Victorian collection</a> if you want the high-collar look, or <a href="/shop/">browse the full shop</a> to compare them side by side. Product links are added as each listing is finished — until then, the collection page is the reliable entry point.</p>

<h2>FAQ</h2>
<p><strong>Can you sit down in a corset?</strong> Yes, if the bottom edge is not laced shut. Leave the lower two centimetres open and sit upright rather than slouched.</p>
<p><strong>How tight should it be?</strong> Tight enough that you feel the support, loose enough that a full breath is possible. A corset does not change your body; it holds a line.</p>
<p><strong>Can you wear one every day?</strong> Many people do, for a few hours at a time. Build up slowly, and stop if you get numbness or bruising — that is a fit problem, not endurance.</p>
<p>Once the corset is right, the rest is easier: the <a href="/build/">build hub</a> covers the other five parts, and <a href="/how-to-make-a-steampunk-costume/">how to make a steampunk costume</a> covers what goes over it.</p>
""",
    },
    {
        "slug": "jackets",
        "parent": "build",
        "post_title": "Steampunk Jackets and Layers",
        "seo_title": "Steampunk Jackets: Coats, Waistcoats and Layers",
        "meta": "Long coat, waistcoat or hoodie: how each layer changes a steampunk silhouette, and which one suits the character you picked. Sizing notes included.",
        "content": """
<p>The top layer is the first thing anyone reads about your character, before they get close enough to see the goggles. It is also the cheapest place to change the whole look, because one layer can turn a modern outfit into a period one without touching anything underneath.</p>

<h2>Three silhouettes</h2>
<ul>
<li><strong>Authority</strong> — a long coat with a high collar, boots, and something metal at the throat. This is the airship captain, and it works because the outline is unmistakable from twenty metres away.</li>
<li><strong>Craft</strong> — a waistcoat over rolled sleeves, an apron, a tool in a pocket. This is the tinkerer: it reads as someone who was working before you arrived.</li>
<li><strong>Comfort</strong> — a hoodie or a printed jersey layer. This is the modern end of the style, and it works when the print is the point rather than a costume pretending to be period clothing.</li>
</ul>
<p>All three are legitimate. What does not work is mixing them: a long coat over a printed hoodie reads as two people in one outfit.</p>

<h2>Length is the decision</h2>
<p>A coat that ends below the knee adds height and slows the walk — it changes posture, which changes how the costume reads in motion. A cropped layer keeps the proportions modern and is far easier on public transport.</p>
<p>Neither is more correct. Decide by venue: standing still for photographs, take the long coat; walking a hall for six hours, take the short one and put the drama in the hat.</p>

<h2>Fabric and print</h2>
<p>Wool and heavy cotton read as period because they hang and crease like period clothing. Printed jersey reads as merchandise, which is fine if that is the point — the failure mode is a print imitating wool, because it does neither job.</p>
<p>Matte before shine, always. Pleather that catches the light looks like a party costume in daylight and convincing in almost nothing else. The <a href="/product-category/posters-prints/">posters and prints collection</a> shows how the same artwork behaves on a flat surface, which is a fair preview of how it sits on a garment.</p>

<h2>Layering under a corset</h2>
<p>A thin shirt, no elastic waistband, sleeves that fit inside the armhole. Anything bulky under a corset creates a ridge exactly where the boning presses, and you will feel it within the hour. Wearing an <a href="/build/corsets/">underbust corset</a>? Then the shirt above it is doing half the character work, so choose it as deliberately as the coat.</p>

<h2>Care and fit</h2>
<p>Printed-on-demand garments shrink once, on the first wash. Wash cold, inside out, and do not tumble dry. Check the <a href="/size-guide/">size guide</a> before ordering: the shop prints to order, so a wrong size cannot be resold.</p>

<h2>Where to start</h2>
<p>For the comfort end, compare the <a href="/product-category/hoodies/">hoodies</a> and <a href="/product-category/t-shirts/">t-shirts</a>. For the craft end, the <a href="/product-category/dark-academia/">Dark Academia collection</a> has the waistcoat-and-library palette, and the <a href="/product-category/steampunk-capsule/">Steampunk Capsule</a> is the safest classic look. For the full outfit around the layers, see <a href="/build/">the build hub</a>.</p>
""",
    },
    {
        "slug": "props",
        "parent": "build",
        "post_title": "Steampunk Props and Carry Pieces",
        "seo_title": "Steampunk Props: Pocket Watches, Canes & 3D Parts",
        "meta": "The one object you carry all day does more for a steampunk look than five you leave in the bag. Here is how to choose it. One good prop beats five.",
        "content": """
<p>One object, held properly, does more than five objects in a bag. The prop is what gives a stranger something to ask about, and the question is the point: steampunk is a style that expects a story, and a story needs a thing to hang on.</p>

<h2>One prop, carried properly</h2>
<p>Pick something you can hold for eight hours without thinking about it: a pocket watch on a chain, a cane, a leather satchel, a specimen case. If it cannot survive a full day in your hand — or in the same pocket — it is a photo prop, not a costume prop, and it will end up in the bag by noon.</p>
<p>The test is boring and reliable: carry it around the house for an afternoon. If you put it down twice, leave it at home.</p>

<h2>Metal you can touch</h2>
<p>Patina beats polish. Aged brass, darkened steel and worn leather read as old; chrome and mirror-finish metal read as new, which is the opposite of what the style is doing. A watch that looks like it has been in a pocket for thirty years works harder than a brighter one that looks like it arrived yesterday.</p>
<p>The same rule applies to the rest of the outfit, and it is the most common reason a careful costume still photographs badly.</p>

<h2>Printed and 3D parts</h2>
<p>Gears, keys, brackets and gauge faces are where 3D printing genuinely wins: light, cheap, replaceable, and nobody expects them to be structural. The <a href="/3d-print-steampunk-parts/">3D-printed steampunk parts guide</a> covers materials and finishing; this page is about choosing which one to carry.</p>
<p>One printed piece, finished matte and aged, beats a belt of six unfinished ones. The belt reads as a kit; the single piece reads as an object.</p>

<h2>Hats</h2>
<p>A top hat changes posture — you stand differently, and people treat you differently, which is useful at an event and awkward on a train. A flat cap keeps the look casual and survives wind. Both are period-safe; choose by character rather than by photograph.</p>

<h2>Safety and venues</h2>
<p>Convention rules on replica weapons, metal-tipped canes and anything that looks like it could be swung vary by venue and by country. Check the event's own policy before you travel, and prefer a rubber tip on any cane you plan to use in a crowd. A prop confiscated at the door is a prop you carried for nothing.</p>

<h2>Where to start</h2>
<p>The <a href="/product-category/accessories/">accessories collection</a> covers the carry pieces already in the shop, and <a href="/product-category/posters-prints/">posters and prints</a> cover the wall version of the same idea. For how the prop fits the rest of the outfit, go back to <a href="/build/">the build hub</a>; for parts you can print yourself, see <a href="/3d-print-steampunk-parts/">3D-printed parts</a>.</p>
""",
    },
]


def strip_tags(html):
    return re.sub(r"\s+", " ", re.sub(r"<[^>]+>", " ", html)).strip()


def validate(page):
    problems = []
    title, meta, content = page["seo_title"], page["meta"], page["content"]
    if len(title) > 62:
        problems.append(f"SEO-titel {len(title)} tegn (> 62)")
    if not 140 <= len(meta) <= 158:
        problems.append(f"meta {len(meta)} tegn (krav 140-158)")
    if re.search(r"<h1[^>]*>", content):
        problems.append("H1 i indholdet - H1 kommer fra post_title")
    words = len(strip_tags(content).split())
    if words < 400:
        problems.append(f"kun {words} ord (minimum 400 mod tynde sider)")
    links = re.findall(r'href="(/[^"]*)"', content)
    for link in sorted(set(links)):
        if link not in VERIFIED:
            problems.append(f"link ikke i verificeret liste: {link}")
    if not links:
        problems.append("ingen interne links")
    return problems, words, links


def page_path(page):
    """URL-sti som siden faar, ud fra slug + parent."""
    parent = f"{page['parent']}/" if page["parent"] else ""
    return f"/{parent}{page['slug']}/"


NEW_PATHS = {page_path(page) for page in PAGES}


def check_links(links):
    """Crawler linkene. Links til sider dette script selv opretter taeles som
    'venter paa publicering', ikke som fejl - ellers kan dry-run aldrig blive groen."""
    bad, pending = [], []
    for link in sorted(set(links)):
        if link in NEW_PATHS:
            pending.append(link)
            continue
        req = urllib.request.Request(f"{WP_SITE}{link}",
                                     headers={"User-Agent": "Steamgears-HubCheck/1.0"})
        try:
            with urllib.request.urlopen(req, timeout=20) as res:
                if res.status != 200:
                    bad.append((link, res.status))
        except urllib.error.HTTPError as e:
            bad.append((link, e.code))
        except Exception as e:
            bad.append((link, str(e)))
    for link in pending:
        print(f"    venter paa publicering: {link}")
    return bad


def dry_run(do_links):
    print(f"{'slug':14} {'titel':>6} {'meta':>5} {'ord':>5} {'links':>6}  status")
    all_links, total = [], 0
    for page in PAGES:
        problems, words, links = validate(page)
        all_links += links
        total += len(problems)
        print(f"{page['slug']:14} {len(page['seo_title']):>6} {len(page['meta']):>5} "
              f"{words:>5} {len(set(links)):>6}  {'OK' if not problems else 'FEJL'}")
        for p in problems:
            print(f"    - {p}")
    if do_links:
        bad = check_links(all_links)
        print(f"\nLinkkontrol: {len(set(all_links))} unikke, {len(bad)} fejl")
        for link, status in bad:
            print(f"    {status}  {link}")
        total += len(bad)
    print(f"\n{len(PAGES)} sider - {total} problem(er)")
    return total == 0


def auth_headers():
    user, password = WP_USER, WP_APP_PASS
    if (not user or not password) and os.path.exists(AUTH_FILE):
        with open(AUTH_FILE, "r", encoding="utf-8") as f:
            data = json.load(f)
        user = user or data.get("user", "")
        password = password or data.get("password", "")
    if not user or not password:
        return None
    token = base64.b64encode(f"{user}:{password}".encode("utf-8")).decode("utf-8")
    return {
        "Content-Type": "application/json",
        "User-Agent": "Steamgears-HubPublisher/1.0",
        "Authorization": f"Basic {token}",
    }


def wp_request(path, headers, payload=None, method="GET"):
    data = json.dumps(payload).encode("utf-8") if payload is not None else None
    req = urllib.request.Request(f"{WP_SITE}{path}", data=data, headers=headers, method=method)
    try:
        with urllib.request.urlopen(req, timeout=30) as res:
            return json.loads(res.read().decode("utf-8") or "{}"), None
    except urllib.error.HTTPError as e:
        return None, f"HTTP {e.code}: {e.read().decode('utf-8', 'replace')[:300]}"
    except Exception as e:
        return None, str(e)


def find_page(headers, slug):
    found, err = wp_request(
        f"/wp-json/wp/v2/pages?slug={urllib.parse.quote(slug)}&status=any&context=edit",
        headers)
    if err:
        return None, err
    return (found[0] if found else None), None


def apply_pages(publish=False):
    headers = auth_headers()
    if not headers:
        print("Mangler credentials: saet WP_USER + WP_APP_PASS (application password),\n"
              "eller laeg dem i ~/.gemini/steamgears_auth.json.", file=sys.stderr)
        return 1

    order = PAGES[::-1] if publish else PAGES  # boern foer hub ved publicering
    handled = 0
    for page in order:
        parent_id = 0
        if page["parent"]:
            parent, err = find_page(headers, page["parent"])
            if err:
                print(f"[FEJL] {page['slug']}: {err}", file=sys.stderr)
                return 1
            if not parent:
                print(f"[FEJL] {page['slug']}: parent '/{page['parent']}/' findes ikke",
                      file=sys.stderr)
                return 1
            parent_id = parent["id"]
        payload = {
            "title": page["post_title"],
            "slug": page["slug"],
            "content": page["content"].strip(),
            "status": "publish" if publish else "draft",
            "parent": parent_id,
            "comment_status": "closed",
            "ping_status": "closed",
        }
        existing, err = find_page(headers, page["slug"])
        if err:
            print(f"[FEJL] {page['slug']}: {err}", file=sys.stderr)
            return 1
        if existing:
            result, err = wp_request(f"/wp-json/wp/v2/pages/{existing['id']}", headers,
                                     payload, "POST")
            action = "opdateret"
        else:
            result, err = wp_request("/wp-json/wp/v2/pages", headers, payload, "POST")
            action = "oprettet"
        if err:
            print(f"[FEJL] {page['slug']}: {err}", file=sys.stderr)
            return 1
        handled += 1
        print(f"[OK] {action} id {result['id']} - /{page['slug']}/ - {payload['status']} "
              f"- parent {parent_id}")

    print(f"\n{handled} sider behandlet. Meta description skal ind i SEO-feltet:")
    for page in PAGES:
        print(f"  /{page['slug']}/ - {page['meta']}")
    return 0


def main():
    parser = argparse.ArgumentParser(
        description="Validerer og publicerer de fire manglende hub-sider.",
        formatter_class=argparse.RawDescriptionHelpFormatter)
    parser.add_argument("--check-links", action="store_true",
                        help="crawl ogsaa alle interne links (dry-run)")
    parser.add_argument("--apply", action="store_true", help="opret/opdater som kladde")
    parser.add_argument("--publish", action="store_true", help="opret/opdater og publicer")
    args = parser.parse_args()

    if args.apply or args.publish:
        return apply_pages(publish=args.publish)
    return 0 if dry_run(args.check_links) else 1


if __name__ == "__main__":
    sys.exit(main())
