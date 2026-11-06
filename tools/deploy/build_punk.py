#!/usr/bin/env python3
"""
Build the punk launch manifest from deploy/steamgears/punk-catalogue.json.

  3 collections -> wc_create_product_category (product_cat terms)
  6 products    -> wc_create_product, status draft

The point of this script is what it refuses to do. A price that was never
decided, a {{TOKEN}} that was never filled and a collection id that does not
exist yet all become a blocked step with a reason, never a plausible-looking
call. The manifest is the review artifact: read it before running --apply.

    python3 tools/deploy/build_punk.py --check          # validate, write nothing
    python3 tools/deploy/build_punk.py                 # write punk-manifest.json
    python3 tools/deploy/build_punk.py --fetch-blocks  # read the 5 blocks off the
                                                       # live product (needs creds)

Blocked steps are skipped by deploy.py unless --force. After a term is created,
put its id in terms.<key> and re-run this script: the product steps then send
{"id": N} instead of {"slug": "..."}.
"""

from __future__ import annotations

import argparse
import html
import importlib.util
import json
import os
import re
import sys

HERE = os.path.dirname(os.path.abspath(__file__))
ROOT = os.path.dirname(os.path.dirname(HERE))
DEPLOY = os.path.join(ROOT, "deploy", "steamgears")

CATALOGUE = os.path.join(DEPLOY, "punk-catalogue.json")
BLOCKS = os.path.join(DEPLOY, "publish-blocks.json")
MANIFEST = os.path.join(DEPLOY, "punk-manifest.json")
MEDIA_IDS = os.path.join(ROOT, "assets", "punk", "attachment-ids.json")
SOURCE_DOC = os.path.join(ROOT, "docs", "library", "punk-lancering-2026-11-02.md")
RAW = os.path.join(DEPLOY, "_sources")

TYPES = {"tee", "hoodie", "poster"}
APPAREL = {"tee", "hoodie"}
REQUIRED = ("design", "priority", "name", "slug", "type", "collections",
            "seo_title", "meta_description", "focus_keyword",
            "hook", "bullets", "details")
TOKEN = re.compile(r"\{\{([A-Z_]+)\}\}")
SLUG = re.compile(r"^[a-z0-9]+(?:-[a-z0-9]+)*$")
META_MAX = 160


def load(path: str) -> dict:
    with open(path, encoding="utf-8") as fh:
        return json.load(fh)


def save(path: str, data: dict) -> None:
    with open(path, "w", encoding="utf-8") as fh:
        json.dump(data, fh, ensure_ascii=False, indent=2)
        fh.write("\n")


# --------------------------------------------------------------------------
# copy -> description
# --------------------------------------------------------------------------

def token_values(product: dict, blocks: dict) -> dict:
    values = {
        "DELIVERY_BLOCK": blocks.get("delivery_block", ""),
        "CARE_BLOCK": blocks.get("care_block", ""),
        "GPSR_BLOCK": blocks.get("gpsr_block", ""),
    }
    if product["type"] in APPAREL:
        values["FABRIC_LINE"] = (blocks.get("fabric") or {}).get(product["type"], "")
        values["SIZE_TABLE"] = blocks.get("size_table", "")
    return values


def render_description(product: dict, blocks: dict, terms: dict) -> tuple:
    """Seven-part description, or the list of tokens that are still unresolved."""
    values = token_values(product, blocks)
    missing: list = []

    def sub(text: str) -> str:
        def replace(m):
            name = m.group(1)
            value = values.get(name, "")
            if not value:
                missing.append(name)
                return m.group(0)
            return value
        return TOKEN.sub(replace, text)

    esc = lambda s: html.escape(s, quote=False)

    # the size table is block-level: inside a <p> the browser closes the
    # paragraph early, so it is always rendered as a sibling of the paragraph
    details = esc(product["details"]).replace("{{SIZE_TABLE}}", "").strip()
    body = [
        "<p>" + sub(esc(product["hook"])) + "</p>",
        "<h3>Why you'll like it</h3>",
        "<ul>" + "".join("<li>" + sub(esc(b)) + "</li>" for b in product["bullets"]) + "</ul>",
        "<h3>Details &amp; fit</h3>",
        "<p>" + sub(details) + "</p>",
    ]
    if product["type"] in APPAREL:
        if values.get("SIZE_TABLE"):
            body.append(values["SIZE_TABLE"])
        else:
            missing.append("SIZE_TABLE")
    body += [
        "<h3>Made to order &amp; delivery</h3>",
        sub("{{DELIVERY_BLOCK}}"),
        "<h3>Care</h3>",
        sub("{{CARE_BLOCK}}"),
        "<h3>Product safety (GPSR)</h3>",
        sub("{{GPSR_BLOCK}}"),
    ]
    for key in product["collections"]:
        collection = next((c for c in _CACHE["collections"] if c["key"] == key), None)
        if collection:
            body.append(f'<p><a href="/product-category/{collection["slug"]}/">'
                        f'See the {esc(collection["name"])} collection</a></p>')

    # a missing block leaves its heading behind; drop headings with nothing under them
    cleaned = []
    for i, chunk in enumerate(body):
        if chunk.startswith("<h3>") and i + 1 < len(body):
            following = body[i + 1]
            if not following.strip() or following.startswith("<h3>"):
                continue
        cleaned.append(chunk)

    return "\n".join(cleaned), sorted(set(missing))


# --------------------------------------------------------------------------
# manifest
# --------------------------------------------------------------------------

_CACHE: dict = {}


def load_media_ids() -> dict:
    """Attachment ids written by the runner's media steps: {file path: id}."""
    if not os.path.exists(MEDIA_IDS):
        return {}
    with open(MEDIA_IDS, encoding="utf-8") as fh:
        return json.load(fh)


def build_manifest(catalogue: dict, blocks: dict, media_ids=None) -> dict:
    terms = catalogue["terms"]
    media_ids = load_media_ids() if media_ids is None else media_ids
    _CACHE["collections"] = catalogue["collections"]
    steps = []
    products = sorted(catalogue["products"], key=lambda p: p.get("priority", 99))

    for collection in catalogue["collections"]:
        if terms.get(collection["key"]):
            continue  # already created; nothing to do
        steps.append({
            "id": "mcp-cat-" + collection["key"],
            "type": "mcp",
            "title": f"Collection {collection['name']} — product_cat '{collection['slug']}'",
            "tool": "wc_create_product_category",
            "tool_aliases": ["wc_create_product_cat", "wc_create_category", "wc_create_term"],
            "optional": False,
            "args": {
                "name": collection["name"],
                "slug": collection["slug"],
                "description": collection["description"],
                "parent": collection.get("parent", 0),
            },
            "after": f"put the returned term id in terms.{collection['key']} "
                     f"in punk-catalogue.json, then re-run build_punk.py",
        })

    # mockups go to the media library before the products need their ids
    for product in products:
        for mockup in product.get("mockups") or []:
            steps.append({
                "id": f"media-{product['design'].lower()}-{mockup['n']:02d}",
                "type": "media",
                "title": f"Upload {os.path.basename(mockup['file'])} — {product['design']} {mockup['view']}",
                "file": mockup["file"],
                "alt": mockup["alt"],
                "title_text": f"{product['name']} — {mockup['view']}",
                "optional": True,
            })

    for product in products:
        description, missing = render_description(product, blocks, terms)

        categories, by_slug = [], []
        for key in product["collections"]:
            term_id = terms.get(key)
            if term_id:
                categories.append({"id": term_id})
            else:
                categories.append({"slug": key})
                by_slug.append(key)

        args = {
            "name": product["name"],
            "slug": product["slug"],
            "type": "simple",
            "status": product.get("status", "draft"),
            "short_description": product["meta_description"],
            "description": description,
            "categories": categories,
            "manage_stock": False,
        }
        if product.get("regular_price"):
            args["regular_price"] = product["regular_price"]

        # gallery order is the mockup order in the catalogue: image 1 is the
        # clean product shot, exactly as on the 39 existing products
        files = [m["file"] for m in product.get("mockups") or []]
        attached = [media_ids[f] for f in files if f in media_ids]
        if attached:
            args["images"] = attached
        notes = []
        if len(attached) < len(files):
            notes.append(f"{len(attached)}/{len(files)} mockups uploaded — the rest attach later"
                         if attached else
                         f"no mockups uploaded yet ({len(files)} planned) — product has no images")

        step = {
            "id": "mcp-product-" + product["design"].lower(),
            "type": "mcp",
            "title": f"Draft {product['design']} — {product['name']}",
            "tool": "wc_create_product",
            "optional": True,
            "args": args,
            "seo": {
                "title": product["seo_title"],
                "meta": product["meta_description"],
                "focus": product["focus_keyword"],
                "why": "Rank Math fields, set after creation with whatever SEO tool "
                       "deploy.py tools exposes — the values are kept here either way",
            },
        }
        blocked = []
        if missing:
            blocked.append("unresolved tokens: " + ", ".join(missing))
        if by_slug:
            blocked.append("collection id unknown for " + ", ".join(by_slug)
                           + " — sending {\"slug\": ...}, which wc_create_product may reject")
        if product["type"] in APPAREL and not blocks.get("size_table"):
            blocked.append("no size table: apparel reads as a fit guide without one")
        if blocked:
            step["blocked"] = blocked
        if notes:
            step["notes"] = notes
        steps.append(step)

    return {
        "site": catalogue["site"],
        "generated_from": "deploy/steamgears/punk-catalogue.json",
        "note": "Generated — do not hand-edit. Tool names vary by Royal MCP version; "
                "'deploy.py tools' is the source of truth. Blocked steps are skipped "
                "unless --force. Media steps upload a file once and record its "
                "attachment id in media_ids.",
        "media_ids": os.path.relpath(MEDIA_IDS, ROOT).replace(os.sep, "/"),
        "steps": steps,
    }


# --------------------------------------------------------------------------
# check
# --------------------------------------------------------------------------

def check(catalogue: dict, blocks: dict) -> int:
    errors, warnings = [], []
    terms = catalogue["terms"]
    _CACHE["collections"] = catalogue["collections"]

    keys = [c["key"] for c in catalogue["collections"]]
    if len(keys) != len(set(keys)):
        errors.append("duplicate collection key")
    for collection in catalogue["collections"]:
        if not SLUG.match(collection["slug"]):
            errors.append(f"collection slug not kebab-case: {collection['slug']}")
        if collection["key"] not in terms:
            errors.append(f"collection {collection['key']} has no entry in terms")
        if not collection.get("description"):
            warnings.append(f"collection {collection['key']} has no description")

    seen_slugs, seen_designs, seen_files = set(), set(), set()
    doc = ""
    if os.path.exists(SOURCE_DOC):
        with open(SOURCE_DOC, encoding="utf-8") as fh:
            doc = fh.read()

    print(f"{'#':>2} {'id':4} {'type':7} {'collections':34} {'price':6} {'copy':6}  name")
    print("-" * 108)

    for product in sorted(catalogue["products"], key=lambda p: p.get("priority", 99)):
        design = product.get("design", "??")
        for field in REQUIRED:
            if product.get(field) in (None, "", []):
                errors.append(f"{design}: missing field '{field}'")
        if product.get("type") not in TYPES:
            errors.append(f"{design}: unknown type '{product.get('type')}'")
        if len(product.get("bullets") or []) != 3:
            errors.append(f"{design}: {len(product.get('bullets') or [])} bullets, expected 3")
        if not SLUG.match(product.get("slug", "")):
            errors.append(f"{design}: slug not kebab-case: {product.get('slug')}")
        if product.get("slug") in seen_slugs:
            errors.append(f"{design}: duplicate slug {product.get('slug')}")
        seen_slugs.add(product.get("slug"))
        if design in seen_designs:
            errors.append(f"{design}: duplicate design id")
        seen_designs.add(design)

        for key in product.get("collections") or []:
            if key not in terms:
                errors.append(f"{design}: collection '{key}' is not in terms")
        if not product.get("collections"):
            errors.append(f"{design}: no collection")

        if len(product.get("meta_description", "")) > META_MAX:
            warnings.append(f"{design}: meta description is "
                            f"{len(product['meta_description'])} chars (>{META_MAX})")
        if not product.get("seo_title", "").endswith("| Steamgears"):
            warnings.append(f"{design}: SEO title does not end with '| Steamgears'")
        if "— " not in product.get("name", ""):
            warnings.append(f"{design}: name does not follow '{Motiv} — {Collection} {Type}'")
        mockups = product.get("mockups") or []
        for mockup in mockups:
            if not mockup.get("alt"):
                errors.append(f"{design}: {mockup.get('file')} has no alt text")
            if mockup.get("file") in seen_files:
                errors.append(f"{design}: {mockup.get('file')} appears twice")
            seen_files.add(mockup.get("file"))
        if [m.get("n") for m in mockups] != list(range(1, len(mockups) + 1)):
            errors.append(f"{design}: mockup numbering is not 1..N")
        print_spec = product.get("print") or {}
        if not print_spec.get("file") or not (print_spec.get("min_px") or [None])[0]:
            errors.append(f"{design}: print master or its minimum pixel width is missing")
        if print_spec.get("destination") != "printify":
            errors.append(f"{design}: print master is not marked for printify")

        if doc and (product.get("name") not in doc or product.get("slug") not in doc):
            warnings.append(f"{design}: name or slug is not in {os.path.basename(SOURCE_DOC)} "
                            f"— the brief and the deploy source have drifted")

        _, missing = render_description(product, blocks, terms)
        price = "set" if product.get("regular_price") else "none"
        copy = "ok" if not missing else ",".join(missing)[:30]
        collections = ",".join(product.get("collections") or [])[:34]
        print(f"{product.get('priority', 0):>2} {design:4} {product.get('type',''):7} "
              f"{collections:34} {price:6} {copy:6}  {product.get('name','')[:34]}")
        if not product.get("regular_price"):
            warnings.append(f"{design}: no price — step stays a draft "
                            f"({product.get('price_layer', 'layer not stated')})")

    print("-" * 108)
    manifest = build_manifest(catalogue, blocks)
    blocked = sum(1 for s in manifest["steps"] if s.get("blocked"))
    media = sum(1 for s in manifest["steps"] if s.get("type") == "media")
    print(f"{len(catalogue['products'])} products, {len(catalogue['collections'])} collections, "
          f"{media} mockup uploads · {blocked} step(s) blocked · "
          f"{len(errors)} error(s), {len(warnings)} warning(s)")

    for message in errors:
        print("error:   " + message)
    for message in warnings:
        print("warning: " + message)
    return 1 if errors else 0


# --------------------------------------------------------------------------
# fetch blocks (best effort, needs credentials)
# --------------------------------------------------------------------------

def load_runner():
    path = os.path.join(HERE, "deploy.py")
    spec = importlib.util.spec_from_file_location("steamgears_deploy", path)
    module = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(module)
    return module


def full_text(result) -> str:
    if not isinstance(result, dict):
        return ""
    parts = [b.get("text", "") for b in result.get("content", [])
             if isinstance(b, dict) and b.get("type") == "text"]
    return "\n".join(parts)


def normalize(text: str) -> str:
    """MCP may hand back the product description JSON-escaped."""
    if '\\"' in text or "\\n" in text:
        text = text.replace('\\"', '"').replace("\\n", "\n").replace("\\/", "/")
    return text


def section(text: str, heading: str) -> str:
    pattern = re.escape(heading).replace("&", "&(?:amp;)?")
    m = re.search(r"<h3[^>]*>\s*" + pattern + r"\s*</h3>(.*?)(?=<h3|\Z)", text, re.S | re.I)
    return m.group(1).strip() if m else ""


def fetch_blocks() -> None:
    runner = load_runner()
    creds = runner.Credentials()
    mcp = runner.Mcp(creds)
    blocks = load(BLOCKS)
    refs = blocks.get("_reference_products") or {}
    os.makedirs(RAW, exist_ok=True)
    found: list = []

    for kind in ("tee", "hoodie"):
        product_id = refs.get(kind)
        if not product_id:
            print(f"{kind}: no reference product id")
            continue
        result, err = mcp.call("wc_get_product", {"id": product_id})
        if err:
            print(f"{kind} ({product_id}): {str(err)[:200]}")
            continue
        text = normalize(full_text(result))
        with open(os.path.join(RAW, f"{product_id}.txt"), "w", encoding="utf-8") as fh:
            fh.write(text)
        print(f"{kind} ({product_id}): {len(text)} chars -> deploy/steamgears/_sources/{product_id}.txt")

        details = section(text, "Details & fit")
        if details:
            paragraph = re.search(r"<p[^>]*>(.*?)</p>", details, re.S)
            if paragraph and not blocks["fabric"].get(kind):
                blocks["fabric"][kind] = paragraph.group(1).strip()
                found.append(f"fabric.{kind}")
        if not blocks.get("size_table"):
            table = re.search(r"<table.*?</table>", details or text, re.S)
            if table:
                blocks["size_table"] = table.group(0).strip()
                found.append("size_table")

        for key, heading in (("delivery_block", "Made to order & delivery"),
                             ("care_block", "Care"),
                             ("gpsr_block", "Product safety (GPSR)")):
            if blocks.get(key):
                continue
            body = section(text, heading)
            if body:
                blocks[key] = body
                found.append(key)

    missing = [k for k in ("delivery_block", "care_block", "gpsr_block", "size_table")
               if not blocks.get(k)]
    missing += [f"fabric.{k}" for k in ("tee", "hoodie") if not blocks["fabric"].get(k)]

    save(BLOCKS, blocks)
    print(f"\nfilled: {', '.join(found) if found else 'nothing'}")
    print(f"still empty: {', '.join(missing) if missing else 'nothing'}")
    if missing:
        print("Paste the rest from deploy/steamgears/_sources/*.txt (not from memory), "
              "then run build_punk.py --check.")


# --------------------------------------------------------------------------

def main() -> None:
    ap = argparse.ArgumentParser(description="Build the punk launch manifest")
    ap.add_argument("--check", action="store_true", help="validate and print, write nothing")
    ap.add_argument("--fetch-blocks", action="store_true",
                    help="read the five blocks off the live reference products (needs credentials)")
    args = ap.parse_args()

    if args.fetch_blocks:
        fetch_blocks()
        return

    catalogue = load(CATALOGUE)
    blocks = load(BLOCKS)

    if args.check:
        sys.exit(check(catalogue, blocks))

    manifest = build_manifest(catalogue, blocks)
    save(MANIFEST, manifest)

    blocked = [s for s in manifest["steps"] if s.get("blocked")]
    print(f"manifest: {len(manifest['steps'])} steps -> "
          f"{os.path.relpath(MANIFEST, ROOT)}")
    for step in manifest["steps"]:
        state = "BLOCKED" if step.get("blocked") else "ready"
        print(f"  {state:8} {step['id']:20} {step['title'][:56]}")
        for reason in step.get("blocked") or []:
            print(f"           - {reason}")
    print(f"\n{len(manifest['steps']) - len(blocked)} ready, {len(blocked)} blocked. "
          f"Review it, then: python3 tools/deploy/deploy.py run "
          f"--manifest deploy/steamgears/punk-manifest.json")


if __name__ == "__main__":
    main()
