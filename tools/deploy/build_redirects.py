#!/usr/bin/env python3
"""
Build a Redirection-importable CSV from deploy/steamgears/redirects.json.

Why a CSV: Redirection 5.10.1 needs a group before an entry can be created, and
`wp_redirection_groups` is empty on this site — which is what blocked S0.4 on
1/11. Its CSV importer creates the group as part of the import, so this file is
the unblock that does not need database access or an MCP tool that does not exist.

    python3 tools/deploy/build_redirects.py            # write the CSV
    python3 tools/deploy/build_redirects.py --verify   # also check every row live

--verify uses a cache-busting query parameter: the site's page cache replays a
404 with HTTP 200, so a plain status check calls a dead URL healthy.
"""

from __future__ import annotations

import argparse
import csv
import json
import os
import re
import sys
import urllib.error
import urllib.request
import uuid

HERE = os.path.dirname(os.path.abspath(__file__))
ROOT = os.path.dirname(os.path.dirname(HERE))
SOURCE = os.path.join(ROOT, "deploy", "steamgears", "redirects.json")
OUTPUT = os.path.join(ROOT, "deploy", "steamgears", "redirects.csv")

BASE = "https://steamgears.eu"
KINDS = {"hub", "legacy-slug", "never-existed", "moved"}
UA = {"User-Agent": "steamgears-deploy/1.0"}


def fetch(path: str) -> tuple:
    url = f"{BASE}{path}" + ("&" if "?" in path else "?") + "nc=" + uuid.uuid4().hex[:8]
    try:
        with urllib.request.urlopen(urllib.request.Request(url, headers=UA), timeout=25) as res:
            return res.status, res.read().decode("utf-8", "replace")
    except urllib.error.HTTPError as exc:
        return exc.code, ""
    except Exception:
        return 0, ""


def alive(path: str) -> tuple:
    """(is_alive, reason). Body beats status — see link_check.py."""
    status, html = fetch(path)
    if status != 200:
        return False, f"http-{status}"
    title = re.search(r"<title>(.*?)</title>", html, re.S)
    if title and "Page Not Found" in title.group(1):
        return False, "soft-404"
    if "error404" in html[:6000]:
        return False, "soft-404"
    return True, "ok"


def validate(rows: list) -> list:
    errors, seen = [], set()
    for row in rows:
        source, target = row.get("source", ""), row.get("target", "")
        for field, value in (("source", source), ("target", target)):
            if not value.startswith("/") or " " in value:
                errors.append(f"{source or row.get('kind')}: {field} must be a path, got {value!r}")
        if source == target:
            errors.append(f"{source}: redirects to itself")
        if source in seen:
            errors.append(f"{source}: appears twice")
        seen.add(source)
        if row.get("kind") not in KINDS:
            errors.append(f"{source}: unknown kind {row.get('kind')!r}")
        if not row.get("reason"):
            errors.append(f"{source}: no reason — a redirect without one outlives its usefulness")
    targets = {r.get("target") for r in rows}
    for row in rows:
        if row.get("source") in targets:
            errors.append(f"{row['source']}: is both a source and a target — chain")
    return errors


def write_csv(rows: list) -> None:
    with open(OUTPUT, "w", encoding="utf-8", newline="") as fh:
        writer = csv.writer(fh)
        # Redirection's importer maps these columns; the names are just for humans
        writer.writerow(["source", "target", "regex", "http_code", "group"])
        for row in rows:
            writer.writerow([row["source"], row["target"], 0, 301, "Steamgears 404"])
    print(f"wrote {len(rows)} rows -> {os.path.relpath(OUTPUT, ROOT)}")


def main() -> None:
    ap = argparse.ArgumentParser(description="Build the Redirection CSV")
    ap.add_argument("--verify", action="store_true", help="check every source and target live")
    args = ap.parse_args()

    with open(SOURCE, encoding="utf-8") as fh:
        data = json.load(fh)
    rows = data["redirects"]

    errors = validate(rows)
    if errors:
        for message in errors:
            print("error: " + message)
        raise SystemExit(1)

    if args.verify:
        print(f"{'source':58} {'status':8} target")
        print("-" * 100)
        problems = 0
        for row in rows:
            source_alive, source_reason = alive(row["source"])
            target_alive, target_reason = alive(row["target"])
            state = "ok"
            if source_alive:
                state, problems = "source lives!", problems + 1
            if not target_alive:
                state, problems = f"target {target_reason}", problems + 1
            print(f"{row['source']:58} {state:8} {row['target']}")
        print("-" * 100)
        print(f"{len(rows)} rows, {problems} problem(s)")
        if problems:
            raise SystemExit(1)

    write_csv(rows)
    print("\nImport: Tools -> Redirection -> Import/Export -> Import, choose the CSV, "
          "pick 'Add as new', map source/target, run.\n"
          "Then purge W3TC and check one source with a cache-busting query: "
          "it must answer 301 to the target.")


if __name__ == "__main__":
    main()
