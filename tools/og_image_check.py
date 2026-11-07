#!/usr/bin/env python3
"""Værn og måleværktøj for S2.5b: OG-billede pr. publiceret indlæg.

Kontrollerer både HTML-metadata og selve billedfilens binære header:
- <meta property="og:image"> findes og svarer HTTP 200
- <meta property="og:image:width"> og <meta property="og:image:height"> matcher
  de faktiske pixel-dimensioner dekodet fra JPEG/PNG/WebP-filen
- <meta property="og:image:alt"> og <meta name="twitter:card"> er sat
- Bredde < 200 px er en hård fejl (OpenGraph-minimum); bredde < 600 px er et
  fund (Facebook/LinkedIn nedgraderer store link-kort til lille thumbnail)
- PNG eller filstørrelse > 600 KB markeres som format-/vægt-fund
- Delte og:image-URL'er på tværs af flere indlæg registreres

Brug:
    python3 tools/og_image_check.py --selftest   # 10 offline-fixtures (ingen netværk)
    python3 tools/og_image_check.py --live       # måler alle publicerede indlæg på steamgears.eu
"""

from __future__ import annotations

import json
import re
import struct
import sys
import urllib.error
import urllib.request
from collections import Counter

UA = "steamgears-og-audit/1.0"
POSTS_API = "https://steamgears.eu/wp-json/wp/v2/posts?per_page=100&_fields=id,slug,link,title,featured_media"
MIN_OG_PX = 200
LARGE_CARD_MIN_W = 600
HEAVY_BYTES = 600 * 1024


def img_dims(data: bytes) -> tuple[str, int, int]:
    """Dekoder (format, width, height) direkte fra PNG-, WebP- eller JPEG-bytes."""
    if len(data) >= 24 and data[:8] == b"\x89PNG\r\n\x1a\n" and data[12:16] == b"IHDR":
        w, h = struct.unpack(">II", data[16:24])
        return "PNG", w, h
    if len(data) >= 30 and data[:4] == b"RIFF" and data[8:12] == b"WEBP":
        chunk = data[12:16]
        if chunk == b"VP8 ":
            w, h = struct.unpack("<HH", data[26:30])
            return "WEBP", w & 0x3FFF, h & 0x3FFF
        if chunk == b"VP8L":
            b0, b1, b2, b3 = data[21:25]
            w = 1 + (((b1 & 0x3F) << 8) | b0)
            h = 1 + (((b3 & 0xF) << 10) | (b2 << 2) | ((b1 & 0xC0) >> 6))
            return "WEBP", w, h
        if chunk == b"VP8X":
            w = 1 + int.from_bytes(data[24:27], "little")
            h = 1 + int.from_bytes(data[27:30], "little")
            return "WEBP", w, h
    if len(data) >= 4 and data[:2] == b"\xff\xd8":
        i = 2
        while i < len(data) - 9:
            if data[i] != 0xFF:
                i += 1
                continue
            marker = data[i + 1]
            while marker == 0xFF and i + 2 < len(data):
                i += 1
                marker = data[i + 1]
            if marker in (0xC0, 0xC1, 0xC2, 0xC3, 0xC5, 0xC6, 0xC7, 0xC9, 0xCA, 0xCB, 0xCD, 0xCE, 0xCF):
                h, w = struct.unpack(">HH", data[i + 5:i + 9])
                return "JPEG", w, h
            if marker in (0xD8, 0xD9) or 0xD0 <= marker <= 0xD7:
                i += 2
            else:
                if i + 4 > len(data):
                    break
                seg_len = struct.unpack(">H", data[i + 2:i + 4])[0]
                i += 2 + seg_len
    return "UNKNOWN", 0, 0


def extract_meta(html: str, key: str) -> str | None:
    """Finder <meta property|name="key" content="..."> uanset attribut-rækkefølge."""
    pat1 = rf'<meta[^>]+(?:property|name)=["\']{re.escape(key)}["\'][^>]+content=["\']([^"\']*)["\']'
    m = re.search(pat1, html, flags=re.IGNORECASE)
    if m:
        return m.group(1).strip()
    pat2 = rf'<meta[^>]+content=["\']([^"\']*)["\'][^>]+(?:property|name)=["\']{re.escape(key)}["\']'
    m = re.search(pat2, html, flags=re.IGNORECASE)
    return m.group(1).strip() if m else None


def extract_og_meta(html: str) -> dict:
    return {
        "og_image": extract_meta(html, "og:image"),
        "og_w": extract_meta(html, "og:image:width"),
        "og_h": extract_meta(html, "og:image:height"),
        "og_alt": extract_meta(html, "og:image:alt"),
        "og_type": extract_meta(html, "og:image:type"),
        "tw_card": extract_meta(html, "twitter:card"),
        "tw_img": extract_meta(html, "twitter:image"),
    }


def evaluate_post(post_id: int, slug: str, html: str, img_status: int, img_bytes: bytes) -> dict:
    """Vurderer ét indlægs OG-billede og adskiller hårde fejl (`errors`) fra indholdsfund (`warnings`)."""
    meta = extract_og_meta(html)
    errors: list[str] = []
    warnings: list[str] = []

    og_img = meta["og_image"]
    if not og_img:
        errors.append("mangler <meta property='og:image'>")
        return {"id": post_id, "slug": slug, "meta": meta, "errors": errors, "warnings": warnings}

    if img_status != 200:
        errors.append(f"og:image svarer HTTP {img_status}: {og_img}")
        return {"id": post_id, "slug": slug, "meta": meta, "errors": errors, "warnings": warnings}

    fmt, real_w, real_h = img_dims(img_bytes)
    if fmt == "UNKNOWN" or real_w <= 0 or real_h <= 0:
        errors.append(f"kunne ikke dekode billeddimensioner ({len(img_bytes)} B)")
        return {"id": post_id, "slug": slug, "meta": meta, "errors": errors, "warnings": warnings}

    if real_w < MIN_OG_PX or real_h < MIN_OG_PX:
        errors.append(f"billede under OpenGraph-minimum ({real_w}x{real_h} < {MIN_OG_PX}x{MIN_OG_PX})")

    if meta["og_w"] and meta["og_h"]:
        try:
            mw, mh = int(meta["og_w"]), int(meta["og_h"])
            if (mw, mh) != (real_w, real_h):
                errors.append(f"og:image:width/height ({mw}x{mh}) afviger fra filens {real_w}x{real_h}")
        except ValueError:
            errors.append(f"ugyldige dimensionstal i meta: {meta['og_w']}x{meta['og_h']}")
    else:
        warnings.append("mangler og:image:width eller og:image:height")

    if not meta["og_alt"]:
        warnings.append("mangler og:image:alt")
    if not meta["tw_card"]:
        warnings.append("mangler twitter:card")

    if real_w < LARGE_CARD_MIN_W:
        warnings.append(f"bredde {real_w} px < {LARGE_CARD_MIN_W} px (nedgraderes fra stort link-kort på FB/LinkedIn)")
    if fmt == "PNG" or len(img_bytes) > HEAVY_BYTES:
        warnings.append(f"format/vægt: {fmt} på {len(img_bytes) // 1024} KB")

    return {
        "id": post_id,
        "slug": slug,
        "meta": meta,
        "fmt": fmt,
        "real_w": real_w,
        "real_h": real_h,
        "bytes": len(img_bytes),
        "errors": errors,
        "warnings": warnings,
    }


def find_shared_images(records: list[dict]) -> dict[str, list[int]]:
    counts: Counter[str] = Counter()
    for r in records:
        url = (r.get("meta") or {}).get("og_image")
        if url:
            counts[url] += 1
    return {
        url: [r["id"] for r in records if (r.get("meta") or {}).get("og_image") == url]
        for url, c in counts.items()
        if c > 1
    }


def make_png(w: int, h: int) -> bytes:
    return b"\x89PNG\r\n\x1a\n" + struct.pack(">I", 13) + b"IHDR" + struct.pack(">II", w, h) + b"\x08\x02\x00\x00\x00"


def make_jpeg(w: int, h: int) -> bytes:
    return b"\xff\xd8\xff\xe0\x00\x10JFIF\x00\x01\x01\x00\x00\x01\x00\x01\x00\x00\xff\xc0\x00\x11\x08" + struct.pack(">HH", h, w) + b"\x03\x01\x22\x00\x02\x11\x01\x03\x11\x01\xff\xd9"


def make_webp_vp8(w: int, h: int) -> bytes:
    payload = b"\x00" * 6 + struct.pack("<HH", w, h)
    return b"RIFF" + struct.pack("<I", 4 + 8 + len(payload)) + b"WEBP" + b"VP8 " + struct.pack("<I", len(payload)) + payload


def selftest() -> int:
    clean_html = (
        '<meta property="og:image" content="https://steamgears.eu/wp-content/uploads/hero.jpg" />'
        '<meta property="og:image:width" content="1408" />'
        '<meta property="og:image:height" content="768" />'
        '<meta property="og:image:alt" content="Steampunk goggles on brass workbench" />'
        '<meta name="twitter:card" content="summary_large_image" />'
    )
    clean_jpg = make_jpeg(1408, 768)

    # 1. Ren post går glat igennem
    r1 = evaluate_post(1, "clean-post", clean_html, 200, clean_jpg)
    assert not r1["errors"] and not r1["warnings"], f"ren post fejlede: {r1}"

    # 2. Omvendt attribut-rækkefølge (content før property) læses korrekt
    rev_html = '<meta content="https://steamgears.eu/a.jpg" property="og:image"><meta content="1200" property="og:image:width"><meta content="630" property="og:image:height"><meta content="Alt" property="og:image:alt"><meta content="summary_large_image" name="twitter:card">'
    r2 = evaluate_post(2, "rev-attr", rev_html, 200, make_jpeg(1200, 630))
    assert not r2["errors"] and not r2["warnings"], f"omvendt attribut-rækkefølge fejlede: {r2}"

    # 3. Manglende og:image er en hård fejl
    r3 = evaluate_post(3, "no-og", "<html><head></head></html>", 200, clean_jpg)
    assert any("og:image" in e for e in r3["errors"]), "manglende og:image ikke fanget"

    # 4. HTTP 404 på billedfilen er en hård fejl
    r4 = evaluate_post(4, "dead-img", clean_html, 404, b"")
    assert any("HTTP 404" in e for e in r4["errors"]), "404 på og:image ikke fanget"

    # 5. Mismatch mellem meta-dimensioner og faktiske pixel-dimensioner er en hård fejl
    r5 = evaluate_post(5, "mismatch", clean_html, 200, make_jpeg(1024, 576))
    assert any("afviger" in e for e in r5["errors"]), "dimensions-mismatch ikke fanget"

    # 6. Under 200x200 er en hård fejl
    tiny_html = clean_html.replace("1408", "150").replace("768", "150")
    r6 = evaluate_post(6, "tiny", tiny_html, 200, make_jpeg(150, 150))
    assert any("OpenGraph-minimum" in e for e in r6["errors"]), "under 200x200 ikke fanget"

    # 7. Bredde 576 px (< 600 px) giver advarsel om nedgraderet link-kort
    narrow_html = clean_html.replace("1408", "576").replace("768", "1024")
    r7 = evaluate_post(7, "narrow-615", narrow_html, 200, make_jpeg(576, 1024))
    assert not r7["errors"] and any("576 px < 600 px" in w for w in r7["warnings"]), f"576 px bredde ikke fanget: {r7}"

    # 8. PNG-header dekodes og markeres som format-advarsel
    png_html = clean_html.replace("1408", "848").replace("768", "1264")
    r8 = evaluate_post(8, "png-751", png_html, 200, make_png(848, 1264))
    assert r8["fmt"] == "PNG" and any("PNG" in w for w in r8["warnings"]), f"PNG ikke fanget: {r8}"

    # 9. WebP VP8-header dekodes korrekt
    webp_html = clean_html.replace("1408", "1200").replace("768", "630")
    r9 = evaluate_post(9, "webp", webp_html, 200, make_webp_vp8(1200, 630))
    assert r9["fmt"] == "WEBP" and not r9["errors"], f"WebP fejlede: {r9}"

    # 10. Delte og:image på tværs af indlæg registreres
    shared = find_shared_images([r1, evaluate_post(10, "dup", clean_html, 200, clean_jpg)])
    assert shared == {"https://steamgears.eu/wp-content/uploads/hero.jpg": [1, 10]}, f"delte billeder ikke fanget: {shared}"

    print("[OK] og_image_check --selftest: 10/10 fixtures bestået")
    return 0


def fetch(url: str) -> tuple[int, bytes]:
    req = urllib.request.Request(url, headers={"User-Agent": UA})
    try:
        with urllib.request.urlopen(req, timeout=25) as resp:
            return resp.status, resp.read()
    except urllib.error.HTTPError as exc:
        return exc.code, b""


def run_live() -> int:
    st, raw = fetch(POSTS_API)
    if st != 200:
        print(f"[FEJL] kunne ikke hente indlæg fra REST API (HTTP {st})", file=sys.stderr)
        return 1
    posts = json.loads(raw)
    img_cache: dict[str, tuple[int, bytes]] = {}
    records: list[dict] = []

    for p in sorted(posts, key=lambda x: x["id"]):
        pst, html_bytes = fetch(p["link"])
        html = html_bytes.decode("utf-8", "replace") if pst == 200 else ""
        meta = extract_og_meta(html)
        og_url = meta.get("og_image")
        if og_url:
            if og_url not in img_cache:
                img_cache[og_url] = fetch(og_url)
            ist, ibytes = img_cache[og_url]
        else:
            ist, ibytes = 0, b""
        rec = evaluate_post(p["id"], p["slug"], html, ist, ibytes)
        rec["featured_media"] = p.get("featured_media", 0)
        records.append(rec)

    shared = find_shared_images(records)
    hard_errors = [r for r in records if r["errors"]]
    with_warnings = [r for r in records if r["warnings"]]

    print(f"Målt {len(records)} publicerede indlæg ({len(img_cache)} unikke og:image-URL'er):")
    print(f"  HTTP 200 + gyldig dimension + match mod meta: {len(records) - len(hard_errors)}/{len(records)}")
    print(f"  Indholdsfund (bredde < 600 px eller PNG/>600 KB): {len(with_warnings)}")
    print(f"  Delte og:image på tværs af indlæg: {len(shared)} billeder ({sum(len(v) for v in shared.values())} indlæg)")
    for r in with_warnings:
        print(f"  [FUND] post {r['id']} ({r['slug']}): " + "; ".join(r["warnings"]))
    for url, ids in shared.items():
        print(f"  [DELT] {url.split('/')[-1]} bruges på indlæg {ids}")
    if hard_errors:
        for r in hard_errors:
            print(f"  [FEJL] post {r['id']} ({r['slug']}): " + "; ".join(r["errors"]), file=sys.stderr)
        return 1
    return 0


def main() -> int:
    if "--selftest" in sys.argv:
        return selftest()
    if "--live" in sys.argv:
        return run_live()
    print(__doc__.strip())
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
