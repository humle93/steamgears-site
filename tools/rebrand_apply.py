#!/usr/bin/env python3
"""rebrand_apply.py — tildel de seks dark academia-kollektioner til produkterne.

`rebrand_audit.py` måler; dette værktøj retter. Det er skrevet til at kunne køres to steder:

  * lokalt med et WordPress-application-password i miljøet (`WP_USER` + `WP_APP_PASSWORD`)
  * i GitHub Actions (.github/workflows/rebrand-apply.yml) hvor de to ligger som repo-secrets

Uden credentials kører det i **plan-tilstand**: det læser den offentlige butik, viser præcis hvilke produkter
der ville få hvilke kollektioner, og rører intet. Det er standard — `--apply` skal siges eksplicit.

    python3 tools/rebrand_apply.py                    # plan (offentlig læsning, ingen skrivning)
    python3 tools/rebrand_apply.py --apply            # skriv via WP REST (WP_USER + WP_APP_PASSWORD)
    python3 tools/rebrand_apply.py --apply --via-cli  # skriv via Novamira-MCP (novamira run … execute-php)
    python3 tools/rebrand_apply.py --verify           # tæl produkter i de seks kollektioner
    python3 tools/rebrand_apply.py --selftest         # 9 fixtures, ingen netværk

Skrivevejen der faktisk virkede 8/11 2026 var `--via-cli`: Novamira-CLI'en har et gyldigt token i
`~/.local/state/novamira/credentials/v1/` (owner-only fil, fornyes automatisk af `novamira auth status`), og
abilityen `novamira/execute-php` kører `wp_set_object_terms()` på sitet. Det er samme mekanisme som blev brugt
til at omdøbe produkterne 1/11. REST-vejen kræver et application password og er reservevejen (og den
`.github/workflows/rebrand-apply.yml` bruger).

Tildelingen er den dokumenterede fra docs/library/om-maerkning-dark-academia-2026-11-01.md §»Ny
kollektionsstruktur«. Overlap er tilsigtet. Eksisterende kategorier bevares — værktøjet lægger kun til.
"""

from __future__ import annotations

import base64
import json
import os
import pathlib
import shutil
import subprocess
import sys
import tempfile
import time
import urllib.error
import urllib.parse
import urllib.request

SITE = "https://steamgears.eu"
STORE = SITE + "/wp-json/wc/store/v1/products"
TERMS = SITE + "/wp-json/wp/v2/product_cat"
PRODUCTS = SITE + "/wp-json/wp/v2/product"

# Kollektionsstrukturen fra om-mærkningsdokumentet (ID'er fra kortlægningen 1/11).
ASSIGNMENT: dict[str, list[int]] = {
    "dark-academia": [391, 393, 395, 397, 399, 401, 403, 405, 407, 409, 411, 413,
                      553, 555, 557, 559, 561, 563, 565, 719, 721, 724, 726, 728],
    "gothic-victorian": [485, 487, 491, 493, 495, 497],
    "natural-history": [399, 403, 405, 553, 559, 565],
    "vintage-maritime": [401, 555, 719, 721, 724, 726, 728],
    "witchy-tarot": [395, 411, 567],
    "steampunk-capsule": [475, 477, 479, 481, 483, 489, 809],
}


def targets() -> dict[int, list[str]]:
    """Vend kortet om: produkt-id → de kollektioner det skal ligge i."""
    out: dict[int, list[str]] = {}
    for slug, ids in ASSIGNMENT.items():
        for product_id in ids:
            out.setdefault(product_id, []).append(slug)
    return {pid: sorted(slugs) for pid, slugs in out.items()}


def merged(existing: list[int], wanted: list[int]) -> list[int]:
    """Behold alt hvad produktet står i, og læg kollektionerne til. Sorteret, uden dubletter."""
    return sorted({int(c) for c in existing} | {int(c) for c in wanted})


def plan(products: list[dict], term_ids: dict[str, int]) -> list[dict]:
    """Ren funktion: butikkens produkter + term-id'er ind, handlingsliste ud.

    `products` er [{'id', 'name', 'categories': [id, ...]}]. Kun produkter der faktisk mangler noget kommer med.
    """
    wanted_by_id = targets()
    rows = []
    for product in products:
        pid = int(product["id"])
        slugs = wanted_by_id.get(pid, [])
        if not slugs:
            continue
        wanted_ids = [term_ids[slug] for slug in slugs if slug in term_ids]
        missing = [slug for slug in slugs if slug in term_ids and term_ids[slug] not in product.get("categories", [])]
        if not missing:
            continue
        rows.append({
            "id": pid,
            "name": product.get("name", ""),
            "add": missing,
            "categories": merged(product.get("categories", []), wanted_ids),
        })
    return rows


def _auth_header() -> dict[str, str]:
    token = os.environ.get("WP_TOKEN", "").strip()
    if token:
        return {"Authorization": f"Bearer {token}"}
    user = os.environ.get("WP_USER", "").strip()
    # Repoet har hidtil kaldt den WP_APP_PASS i seo_audit.yml og WP_APP_PASSWORD i deploy.py — begge læses.
    password = (os.environ.get("WP_APP_PASSWORD") or os.environ.get("WP_APP_PASS") or "").strip()
    if user and password:
        raw = f"{user}:{password}".encode()
        return {"Authorization": "Basic " + base64.b64encode(raw).decode()}
    return {}


def call(url: str, method: str = "GET", body: dict | None = None, auth: dict | None = None) -> tuple[int, object]:
    data = json.dumps(body).encode() if body is not None else None
    headers = {"User-Agent": "steamgears-rebrand-apply", "Accept": "application/json"}
    if data:
        headers["Content-Type"] = "application/json"
    headers.update(auth or {})
    request = urllib.request.Request(url, data=data, headers=headers, method=method)
    try:
        with urllib.request.urlopen(request, timeout=45) as response:
            return response.status, json.load(response)
    except urllib.error.HTTPError as exc:
        try:
            return exc.code, json.loads(exc.read().decode() or "{}")
        except (ValueError, UnicodeDecodeError):
            return exc.code, {}


def term_ids_by_slug() -> dict[str, int]:
    code, terms = call(TERMS + "?" + urllib.parse.urlencode({"_fields": "id,slug,count", "per_page": 100}))
    if code != 200 or not isinstance(terms, list):
        raise SystemExit(f"kunne ikke læse produktkategorier (HTTP {code})")
    return {term["slug"]: int(term["id"]) for term in terms}


def public_products() -> list[dict]:
    code, products = call(STORE + "?" + urllib.parse.urlencode({"per_page": 100, "status": "publish"}))
    if code != 200 or not isinstance(products, list):
        raise SystemExit(f"kunne ikke læse produkter (HTTP {code})")
    return [{"id": p["id"], "name": p.get("name", ""),
             "categories": [c.get("id") for c in (p.get("categories") or []) if c.get("id")]} for p in products]


def authorised_products(auth: dict) -> list[dict]:
    """Alle produkter undtagen dem i papirkurven — dem skal ejeren tage stilling til, ikke værktøjet."""
    found: list[dict] = []
    for page in (1, 2, 3):
        query = urllib.parse.urlencode({"status": "publish,draft,pending,private", "per_page": 100,
                                        "page": page, "_fields": "id,title,categories,status"})
        code, rows = call(PRODUCTS + "?" + query, auth=auth)
        if code != 200 or not isinstance(rows, list):
            raise SystemExit(f"kunne ikke læse produkter med de givne credentials (HTTP {code})")
        if not rows:
            break
        for row in rows:
            found.append({"id": row["id"], "name": ((row.get("title") or {}).get("rendered") or "").strip(),
                          "categories": row.get("categories") or []})
        if len(rows) < 100:
            break
    return found


def show(rows: list[dict], term_ids: dict[str, int]) -> None:
    names = {value: key for key, value in term_ids.items()}
    for row in rows:
        additions = ", ".join(row["add"])
        after = ", ".join(names.get(int(c), str(c)) for c in row["categories"])
        print(f'{row["id"]:>5}  +{additions:<46} → [{after}]  {row["name"][:48]}')
    print(f"\n{len(rows)} produkter mangler tildeling · "
          f'{sum(len(r["add"]) for r in rows)} kollektions-placeringer i alt')


def verify(term_ids: dict[str, int]) -> bool:
    code, terms = call(TERMS + "?" + urllib.parse.urlencode({"_fields": "id,slug,count", "per_page": 100}))
    counts = {term["slug"]: int(term.get("count") or 0) for term in terms} if code == 200 else {}
    empty = [slug for slug in ASSIGNMENT if counts.get(slug, 0) == 0]
    for slug in ASSIGNMENT:
        print(f"  {slug:<20} {counts.get(slug, 0):>3} produkter")
    print("Dom: " + ("alle seks kollektioner har produkter" if not empty
                     else f"stadig tom: {', '.join(empty)}"))
    return not empty


def _novamira() -> str:
    """Find Novamira-CLI'en: PATH først, ellers den npm-installation repoets opsætning bruger."""
    found = shutil.which("novamira") or shutil.which("novamira", path=os.path.expanduser("~/.novamira-npm/bin"))
    fallback = os.path.expanduser("~/.novamira-npm/bin/novamira")
    if found:
        return found
    if os.path.exists(fallback):
        return fallback
    raise SystemExit("STOP: novamira-CLI'en findes ikke (hverken i PATH eller ~/.novamira-npm/bin).")


def apply_via_cli(rows: list[dict], site: str = "steamgears.eu") -> int:
    """Skriv gennem Novamira-MCP: én execute-php-kørsel der sætter de endelige term-lister.

    Samme mekanisme som blev brugt 1/11 (og som virkede 8/11): `wp_set_object_terms()` med de gamle
    kategorier bevaret. Kørslen er idempotent — listen er den færdige union, ikke en tilføjelse.
    """
    mapping = ",\n".join(f"{row['id']} => [{','.join(str(int(c)) for c in row['categories'])}]" for row in rows)
    code = f"""$map = [{mapping}];
$report = [];
foreach ($map as $pid => $ids) {{
    $post = get_post($pid);
    if (!$post || $post->post_status === 'trash') {{ $report[] = ['id' => $pid, 'skip' => true]; continue; }}
    $res = wp_set_object_terms($pid, array_map('intval', $ids), 'product_cat', false);
    clean_post_cache($pid);
    $slugs = [];
    foreach (get_the_terms($pid, 'product_cat') ?: [] as $term) {{ $slugs[] = $term->slug; }}
    sort($slugs);
    $report[] = ['id' => $pid, 'slugs' => $slugs, 'error' => is_wp_error($res) ? $res->get_error_message() : null];
}}
if (function_exists('w3tc_flush_all')) {{ w3tc_flush_all(); }}
return $report;"""
    with tempfile.NamedTemporaryFile("w", suffix=".json", delete=False, encoding="utf-8") as handle:
        json.dump({"code": code}, handle)
        payload = handle.name
    try:
        result = subprocess.run([_novamira(), "--site", site, "--json", "--yes",
                                 "run", "novamira/execute-php", "--input", "@" + payload],
                                capture_output=True, text=True, timeout=180)
    finally:
        os.unlink(payload)
    raw = result.stdout
    start = raw.find("{")
    if start < 0:
        print(f"STOP: novamira svarede uden JSON (exit {result.returncode}): {result.stderr[-300:]}")
        return 1
    try:
        answer = json.loads(raw[start:])
    except ValueError as exc:
        print(f"STOP: kunne ikke læse novamira-svaret: {exc}")
        return 1
    if not answer.get("ok"):
        print(f"STOP: novamira afviste kørslen: {str(answer.get('error'))[:300]}")
        return 1
    failures = 0
    for row in answer["data"]["return_value"]:
        if row.get("skip") or row.get("error"):
            failures += 1
            print(f'  {row["id"]:>5}  FEJL/SKIP {row.get("error") or "papirkurv"}')
        else:
            print(f'  {row["id"]:>5}  OK  → {", ".join(row["slugs"])}')
    return failures


def apply(rows: list[dict], auth: dict) -> int:
    failures = 0
    for index, row in enumerate(rows, 1):
        code, body = call(f'{PRODUCTS}/{row["id"]}', method="POST", auth=auth,
                          body={"categories": row["categories"]})
        ok = code == 200
        if not ok:
            failures += 1
        print(f'[{index:>2}/{len(rows)}] {row["id"]:>5} {"OK " if ok else f"FEJL {code} "} +{", ".join(row["add"])}'
              + ("" if ok else f' — {str(body)[:120]}'))
        time.sleep(0.4)
    return failures


def selftest() -> int:
    term_ids = {"dark-academia": 94, "gothic-victorian": 95, "natural-history": 96,
                "vintage-maritime": 97, "witchy-tarot": 98, "steampunk-capsule": 99}

    # 1: produktet står i en gammel kategori og skal have to nye — de gamle bevares
    rows = plan([{"id": 401, "name": "Whale Airship", "categories": [182]}], term_ids)
    assert rows and rows[0]["categories"] == [94, 97, 182], rows

    # 2: allerede tildelt → ingen handling
    assert plan([{"id": 401, "name": "x", "categories": [94, 97]}], term_ids) == []

    # 3: produkt uden for planen røres ikke
    assert plan([{"id": 940, "name": "pendant", "categories": []}], term_ids) == []

    # 4: merge er idempotent og sorteret
    assert merged([182, 16], [94]) == [16, 94, 182]
    assert merged([94], [94]) == [94]

    # 5: ukendt term-slug ignoreres i stedet for at skrive forkert id
    assert plan([{"id": 485, "name": "x", "categories": []}], {"gothic-victorian": 95})[0]["add"] == ["gothic-victorian"]
    assert plan([{"id": 567, "name": "x", "categories": []}], {"gothic-victorian": 95}) == []

    # 6: alle 39 kortlagte id'er findes i planen, og ingen kollektion er tom i kortet
    assert set(targets()) == {i for ids in ASSIGNMENT.values() for i in ids}
    assert all(ids for ids in ASSIGNMENT.values())

    # 7: 401 ligger i både dark academia og vintage maritime (overlap er tilsigtet)
    assert targets()[401] == ["dark-academia", "vintage-maritime"]
    # 8: 399 ligger i både dark academia og natural history
    assert targets()[399] == ["dark-academia", "natural-history"]
    # 9: steampunk-kapslen rører ikke de omdøbte hoodies
    assert 413 not in ASSIGNMENT["steampunk-capsule"] and 475 in ASSIGNMENT["steampunk-capsule"]

    print("[OK] rebrand_apply: 9 fixtures — tildeling, overlap, idempotens, ukendt term, ingen tom kollektion")
    return 0


def main() -> int:
    args = sys.argv[1:]
    if "--selftest" in args:
        return selftest()
    term_ids = term_ids_by_slug()
    missing_terms = [slug for slug in ASSIGNMENT if slug not in term_ids]
    if missing_terms:
        print(f"STOP: termerne findes ikke på sitet: {', '.join(missing_terms)}")
        return 2
    if "--verify" in args:
        return 0 if verify(term_ids) else 1

    if "--apply" in args and "--via-cli" in args:
        rows = plan(public_products(), term_ids)
        show(rows, term_ids)
        if not rows:
            return 0 if verify(term_ids) else 1
        failures = apply_via_cli(rows)
        ok = verify(term_ids)
        return 0 if ok and failures == 0 else 1

    auth = _auth_header()
    if "--apply" in args:
        if not auth:
            print("STOP: --apply kræver WP_USER + WP_APP_PASSWORD (application password) eller WP_TOKEN.")
            print("      Kør uden --apply for at se planen; sæt credentials som repo-secrets for at skrive.")
            return 2
        rows = plan(authorised_products(auth), term_ids)
        show(rows, term_ids)
        if not rows:
            return 0 if verify(term_ids) else 1
        failures = apply(rows, auth)
        ok = verify(term_ids)
        return 0 if ok and failures == 0 else 1

    rows = plan(public_products(), term_ids)
    show(rows, term_ids)
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
