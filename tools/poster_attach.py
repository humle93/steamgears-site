#!/usr/bin/env python3
"""poster_attach.py — map raw poster images to the SG poster SKUs, as a dry run.

The 50 poster designs live in `data/posters-50-import.csv` (the master catalog's
WooCommerce import sheet). The raw images from Drive are named after the design,
not after the SKU, and some carry a revision suffix (`-r2a`, `-k1b`) that the
catalog does not know. This tool is the only place that translation happens, so
an image is either attached to exactly one SKU or reported — never guessed.

Rules, in this order:

  1. **An explicit override wins.** `OVERRIDES` names the files whose target
     cannot be read off the catalog's filename, each with its reason:
     * **SG-AIR-002:** the catalog's `Filnavn_PNG` points at
       `sg-air-002-celestial-dome`, but the catalog's own character
       description (East Asian navigator, silver-streaked bun, brass
       spectacles, armillary sphere) is the *navigator* image. The owner chose
       navigator as the main image and celestial as the secondary gallery image
       on 13/11 2026, so both are mapped explicitly here instead of by filename.
     * **SG-AIR-005:** the generation batch wrote `crows-nest-bridge`, while the
       catalog calls the same design `crows-nest-lookout` ("Udkigsposten i
       Masten"). One SKU, one design, two slugs — mapped by hand once.
  2. **Exact catalog stem**, after the revision suffix is stripped
     (`-(r|k)<digits><letter>?$`). `sg-air-001-aurora-helm-r1a.jpg` therefore
     matches `sg-air-001-aurora-helm.png`.
  3. **Anything else is unmapped.** A near miss (`sg-air-009-...` when the
     catalog has no such file, or a typo) is reported with the closest catalog
     stem, and the run fails. No fuzzy matching, no "probably this one".

The tool never writes to WordPress and never uploads anything: it prints the
plan a later, authorised uploader would follow. It reads image dimensions with
Pillow when it is available (`tools/_deps`), and says so when it cannot.

Usage:

    python3 tools/poster_attach.py --dir /tmp/raw        # dry run on a folder
    python3 tools/poster_attach.py --dir /tmp/raw --json # same, as JSON
    python3 tools/poster_attach.py --selftest            # fixtures + catalog check

Exit: 0 = every file mapped to a distinct SKU and the five drop designs have a
main image · 1 = unmapped file, duplicate main image, missing drop image, or a
bad catalog · 2 = internal error.
"""

from __future__ import annotations

import argparse
import csv
import json
import re
import sys
import tempfile
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
CATALOG = ROOT / "data" / "posters-50-import.csv"
IMAGE_SUFFIXES = {".jpg", ".jpeg", ".png", ".webp"}

# The first drop: five designs with a raw image, all from Sky Armada.
DROP = ["SG-AIR-001", "SG-AIR-002", "SG-AIR-003", "SG-AIR-005", "SG-AIR-008"]
ACTIVE_CEILING = 50

# Files whose target cannot be read off the catalog's filename, with the reason.
OVERRIDES = {
    "sg-air-002-navigator-dome": (
        "SG-AIR-002",
        "main",
        "katalogets beskrivelse (østasiatisk navigatør, sølvstribet knold, "
        "messingbriller, armillarsfære) — valgt som hovedbillede 13/11",
    ),
    "sg-air-002-celestial-dome": (
        "SG-AIR-002",
        "secondary",
        "katalogets Filnavn_PNG, men en anden figur — beholdt som galleri/sekundært",
    ),
    "sg-air-005-crows-nest-bridge": (
        "SG-AIR-005",
        "main",
        "samme motiv som katalogets 'crows-nest-lookout' (Udkigsposten i Masten); "
        "genereringsbatchen brugte et andet slang-ord",
    ),
}

# Print targets from the master catalog (300 dpi, 3 mm bleed not included).
FORMATS = {
    "5:7": (5 / 7, 5906, 8268),   # 50 × 70 cm
    "2:3": (2 / 3, 4724, 7087),   # 40 × 60 cm
}
ASPECT_TOLERANCE = 0.01           # 864×1216 and 848×1264 sit this close; 896×1200 does not
# Raw aspects seen in the batch. 3:4 is not a catalog product size, so such a
# file has to be cropped deliberately — it must not be reported as 5:7.
RAW_ASPECTS = {"5:7": 5 / 7, "2:3": 2 / 3, "3:4": 3 / 4}
DIMENSION_SOURCE = "ukendt"


def load_catalog(path: Path = CATALOG) -> list[dict]:
    """Rows from the import sheet, with the filename stem the catalog expects."""
    with path.open(encoding="utf-8-sig", newline="") as handle:
        rows = list(csv.DictReader(handle))
    for row in rows:
        filename = (row.get("Filnavn_PNG") or "").strip()
        row["stem"] = Path(filename).stem.lower()
    return rows


def strip_revision(stem: str) -> str:
    """`sg-air-001-aurora-helm-r1a` → `sg-air-001-aurora-helm`."""
    return re.sub(r"-(?:r|k)\d+[a-z]?$", "", stem)


def catalog_index(rows: list[dict]) -> dict[str, dict]:
    index: dict[str, dict] = {}
    for row in rows:
        stem = row["stem"]
        if not stem:
            continue
        if stem in index:
            raise ValueError(f"kataloget har to rækker med samme filnavn: {stem}")
        index[stem] = row
    return index


def map_filename(name: str, index: dict[str, dict]) -> dict:
    """One filename → one SKU, or an explicit miss. Never a guess."""
    path = Path(name)
    stem = path.stem.lower()
    base = strip_revision(stem)

    if base in OVERRIDES:
        sku, role, why = OVERRIDES[base]
        row = next((r for r in index.values() if r["SKU"] == sku), None)
        return {
            "file": name,
            "sku": sku,
            "role": role,
            "title": row["Titel"] if row else "",
            "note": why,
            "error": None,
        }

    row = index.get(base)
    if row is None:
        close = [s for s in index if s.startswith(base[:12])]
        return {
            "file": name,
            "sku": None,
            "role": None,
            "title": "",
            "note": "",
            "error": "ingen katalogrække matcher; nærmeste: " + (", ".join(sorted(close)[:3]) or "ingen"),
        }

    return {
        "file": name,
        "sku": row["SKU"],
        "role": "main",
        "title": row["Titel"],
        "note": "",
        "error": None,
    }


def measure(path: Path) -> tuple[int, int] | None:
    try:
        from PIL import Image  # noqa: PLC0415 — optional dependency in tools/_deps
    except ImportError:
        return None
    try:
        with Image.open(path) as image:
            return image.size
    except OSError:
        return None


def judge(size: tuple[int, int] | None) -> dict:
    """Aspect and print readiness against the catalog's own numbers."""
    if not size:
        return {"width": None, "height": None, "format": None, "crop": None,
                "print_cm": None, "upscale": None}
    width, height = size
    ratio = width / height
    fmt = min(FORMATS, key=lambda key: abs(FORMATS[key][0] - ratio))
    target_ratio, target_w, target_h = FORMATS[fmt]
    label = next((name for name, value in RAW_ASPECTS.items()
                  if abs(value - ratio) <= ASPECT_TOLERANCE), f"{ratio:.2f}")
    off_format = label not in FORMATS or label != fmt
    crop = abs(ratio - target_ratio) > ASPECT_TOLERANCE
    return {
        "width": width,
        "height": height,
        "format": fmt,
        "label": label,
        "off_format": off_format,
        "crop": crop,
        "print_cm": (round(width / 300 * 2.54, 1), round(height / 300 * 2.54, 1)),
        "upscale": round(target_w / width, 1),
        "target_px": (target_w, target_h),
    }


def plan(directory: Path) -> dict:
    rows = load_catalog()
    index = catalog_index(rows)
    files = sorted(
        p for p in directory.iterdir()
        if p.is_file() and p.suffix.lower() in IMAGE_SUFFIXES
    )
    mapped: list[dict] = []
    problems: list[str] = []
    ignored = sorted(p.name for p in directory.iterdir()
                     if p.is_file() and p.suffix.lower() not in IMAGE_SUFFIXES)

    for path in files:
        result = map_filename(path.name, index)
        result["size"] = measure(path)
        result.update(judge(result["size"]))
        mapped.append(result)
        if result["error"]:
            problems.append(f"{path.name}: {result['error']}")

    by_sku: dict[str, list[dict]] = {}
    for item in mapped:
        if item["sku"]:
            by_sku.setdefault(item["sku"], []).append(item)

    for sku, items in sorted(by_sku.items()):
        mains = [i for i in items if i["role"] == "main"]
        if len(mains) > 1:
            problems.append(
                f"{sku}: {len(mains)} hovedbilleder ({', '.join(i['file'] for i in mains)})"
            )

    coverage = {
        sku: [i["file"] for i in by_sku.get(sku, [])]
        for sku in DROP
    }
    for sku, names in coverage.items():
        if not names:
            problems.append(f"drop-designet {sku} har intet billede i mappen")

    return {
        "files": len(files),
        "mapped": mapped,
        "by_sku": {sku: [i["file"] for i in items] for sku, items in by_sku.items()},
        "coverage": coverage,
        "drop": DROP,
        "backlog": len({r["SKU"] for r in rows}) - len(DROP),
        "ignored": ignored,
        "problems": problems,
    }


def print_plan(result: dict) -> None:
    print(f"POSTER-ATTACH — dry run, {result['files']} billeder, intet uploadet")
    print()
    print(f"{'fil':44} {'px':11} {'format':9} {'SKU':12} {'rolle':10} tryk")
    for item in result["mapped"]:
        size = f"{item['width']}×{item['height']}" if item["width"] else "?"
        fmt = item["format"] or "?"
        shown = item.get("label") or fmt
        if item.get("off_format"):
            shown = f"{shown}→{fmt}"
        print_cm = item["print_cm"]
        if print_cm is None:
            verdict = "ukendt"
        elif item.get("off_format"):
            verdict = (f"uden for katalogformat ({item['label']}); beskår til {fmt}; "
                       f"{print_cm[0]} cm nu; {item['upscale']}× op")
        elif item["crop"]:
            verdict = f"beskår til {fmt}; {print_cm[0]} cm nu; {item['upscale']}× op"
        else:
            verdict = f"{print_cm[0]}×{print_cm[1]} cm @300 dpi → {item['upscale']}× op"
        print(f"{item['file']:44} {size:11} {shown:9} {item['sku'] or '—':12} "
              f"{item['role'] or '—':10} {verdict}")
        if item["note"]:
            print(f"{'':44} ↳ {item['note']}")
    if result["ignored"]:
        print()
        print("Ignoreret (ikke et billede): " + ", ".join(result["ignored"]))
    print()
    print(f"Drop (5): {', '.join(result['drop'])}")
    for sku, names in result["coverage"].items():
        print(f"  {sku}: {', '.join(names) if names else 'MANGLER'}")
    print(f"Backlog: {result['backlog']} designs som kladder (loft {ACTIVE_CEILING} aktive produkter)")
    print()
    if result["problems"]:
        print(f"STOP — {len(result['problems'])} problem(er):")
        for problem in result["problems"]:
            print("  - " + problem)
    else:
        print("OK — hvert billede er koblet til præcis én SG-SKU, og alle fem drop-designs har et hovedbillede.")


def selftest() -> int:
    rows = load_catalog()
    index = catalog_index(rows)
    assert len(rows) == 50, f"kataloget har {len(rows)} rækker, forventet 50"
    assert len({r['SKU'] for r in rows}) == 50, "kataloget har dubletter i SKU"
    checks = 0

    def expect(name: str, sku: str | None, role: str | None) -> None:
        nonlocal checks
        got = map_filename(name, index)
        assert got["sku"] == sku, f"{name} → {got['sku']}, forventet {sku}"
        assert got["role"] == role, f"{name} → {got['role']}, forventet {role}"
        assert (got["error"] is None) == (sku is not None), f"{name}: fejlflag passer ikke"
        checks += 1

    expect("sg-air-001-aurora-helm-r1a.jpg", "SG-AIR-001", "main")
    expect("sg-air-002-navigator-dome-r2b.jpg", "SG-AIR-002", "main")
    expect("sg-air-002-celestial-dome-r2a.jpg", "SG-AIR-002", "secondary")
    expect("sg-air-003-sky-boarding-r3a.jpg", "SG-AIR-003", "main")
    expect("sg-air-005-crows-nest-bridge-r2f.jpg", "SG-AIR-005", "main")
    expect("sg-air-008-armada-victorian-city-k1a.jpg", "SG-AIR-008", "main")
    expect("sg-eng-011-chief-engineer-valve-r4a.jpg", "SG-ENG-011", "main")
    expect("sg-nat-031-strix-mechanica-owl-k2a.jpg", "SG-NAT-031", "main")
    expect("sg-air-009-sky-corsair-mechanical-falcon.jpg", None, None)
    expect("sg-air-001-aurora-helmm-r1a.jpg", None, None)

    assert len(DROP) == 5, "drop-listen skal have præcis 5 designs"
    assert set(DROP) <= {r["SKU"] for r in rows}, "drop indeholder en SKU der ikke findes"
    checks += 2

    with tempfile.TemporaryDirectory() as tmp:
        folder = Path(tmp)
        for name in ("sg-air-001-aurora-helm-r1a.jpg",
                     "sg-air-002-navigator-dome-r2b.jpg",
                     "sg-air-002-celestial-dome-r2a.jpg",
                     "sg-air-003-sky-boarding-r3a.jpg",
                     "sg-air-005-crows-nest-bridge-r2f.jpg",
                     "sg-air-008-armada-victorian-city-k1a.jpg"):
            (folder / name).write_bytes(b"x")
        result = plan(folder)
        assert result["files"] == 6, "seks filer skulle læses"
        assert not result["problems"], f"uventede problemer: {result['problems']}"
        assert result["by_sku"]["SG-AIR-002"] == [
            "sg-air-002-celestial-dome-r2a.jpg",
            "sg-air-002-navigator-dome-r2b.jpg",
        ], result["by_sku"].get("SG-AIR-002")
        assert result["backlog"] == 45, f"backlog er {result['backlog']}, forventet 45"
        checks += 4

        (folder / "ukendt-motiv.jpg").write_bytes(b"x")
        result = plan(folder)
        assert result["problems"] and "ukendt-motiv.jpg" in result["problems"][0], \
            "en ukendt fil skal rapporteres"
        checks += 1

    # Formatdommen: 3:4 må ikke blive kaldt 5:7, og 5:7/2:3 må ikke kræve beskæring.
    for size, label, off in (((896, 1200), "3:4", True),
                              ((864, 1216), "5:7", False),
                              ((848, 1264), "2:3", False)):
        got = judge(size)
        assert got["label"] == label, f"{size} blev kaldt {got['label']}, forventet {label}"
        assert got["off_format"] is off, f"{size}: off_format={got['off_format']}"
        if off:
            assert got["crop"] is True, f"{size} skal markeres som beskæring"
        else:
            assert got["crop"] is False, f"{size} kræver beskæring men er i format"
        checks += 1
    print(f"PASS {checks} · 0 FAIL — kataloget har 50 SKU'er, 5 i drop og 45 i backlog")
    return 0


def main(argv: list[str]) -> int:
    parser = argparse.ArgumentParser(description="Map raw poster images to SG SKUs (dry run).")
    parser.add_argument("--dir", help="folder with the raw images")
    parser.add_argument("--json", action="store_true", help="print the plan as JSON")
    parser.add_argument("--selftest", action="store_true", help="run the offline fixtures")
    args = parser.parse_args(argv)

    if args.selftest:
        try:
            return selftest()
        except AssertionError as error:
            print(f"FAIL — {error}")
            return 1

    if not args.dir:
        parser.error("brug --dir <mappe> eller --selftest")

    directory = Path(args.dir)
    if not directory.is_dir():
        print(f"FEJL — mappen findes ikke: {directory}")
        return 1

    try:
        result = plan(directory)
    except (OSError, ValueError) as error:
        print(f"FEJL — {error}")
        return 2

    if args.json:
        print(json.dumps(result, ensure_ascii=False, indent=2))
    else:
        print_plan(result)
    return 1 if result["problems"] else 0


if __name__ == "__main__":
    sys.exit(main(sys.argv[1:]))
