#!/usr/bin/env python3
"""
Connect to Royal MCP on steamgears.eu and report what it actually exposes.

The tool names are the one thing no document in this repo can tell you: Royal
MCP's surface varies by version and by which plugins are active. Every manifest
here guesses at names and marks the guess as optional. This script removes the
guesswork in one call.

It uses the same client as deploy.py, so if this works, the runner works.

    python3 tools/deploy/mcp_connect.py                     # handshake + inventory
    python3 tools/deploy/mcp_connect.py --grep wc_product   # filter by substring
    python3 tools/deploy/mcp_connect.py --call wc_get_product --args '{"id": 563}'

Needs ROYAL_MCP_TOKEN in the environment. A key is created in the site's
Royal MCP settings; it is not something this script or the repo can generate,
and it must never be pasted into chat or committed.
"""

from __future__ import annotations

import argparse
import importlib.util
import json
import os
import sys
from collections import defaultdict

HERE = os.path.dirname(os.path.abspath(__file__))

# what the manifests in deploy/ need, and what happens when it is missing
WANTED = [
    (["wc_create_product"], "required", "deploy/steamgears: the six product drafts"),
    (["wc_create_product_category", "wc_create_product_cat", "wc_create_category",
      "wc_create_term"], "required", "deploy/steamgears: the three punk collections"),
    (["wc_get_product"], "useful", "build_punk.py --fetch-blocks: the five copy blocks"),
    (["wc_update_product"], "useful", "prices, GPSR block and the second pass after creation"),
    (["wc_get_products"], "useful", "catalogue checks without the public Store API"),
    (["wp_get_plugins"], "useful", "plugin inventory for the hygiene band"),
    (["wp_get_site_status", "wp_get_site_info"], "useful", "runtime facts for the audit trail"),
]


def load_runner():
    spec = importlib.util.spec_from_file_location("steamgears_deploy",
                                                 os.path.join(HERE, "deploy.py"))
    module = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(module)
    return module


def explain_missing_token() -> None:
    print("ROYAL_MCP_TOKEN is not set, so the endpoint answers 401.\n")
    print("To fix it, in this order:")
    print("  1. steamgears.eu -> wp-admin -> Royal MCP -> API keys -> create a key")
    print("     (name it something you can revoke later, e.g. 'freebuff-agent')")
    print("  2. Freebuff -> Settings -> Secrets -> add it as ROYAL_MCP_TOKEN")
    print("  3. For the REST half (plugins, media upload, redirect verification),")
    print("     also add WP_SITE=https://steamgears.eu, WP_USER=<a deploy user>,")
    print("     WP_APP_PASSWORD=<its application password>")
    print("\nDo not paste the key into the chat: a secret that has been in a")
    print("transcript has to be rotated. The secret store is enough.")
    raise SystemExit(2)


def main() -> None:
    ap = argparse.ArgumentParser(description="Royal MCP handshake and inventory")
    ap.add_argument("--grep", help="only show tool names containing this substring")
    ap.add_argument("--all", action="store_true", help="print every tool, not just the summary")
    ap.add_argument("--call", help="call one tool")
    ap.add_argument("--args", default="{}", help="JSON arguments for --call")
    args = ap.parse_args()

    if not os.environ.get("ROYAL_MCP_TOKEN") and not os.environ.get("WP_TOKEN"):
        explain_missing_token()

    deploy = load_runner()
    creds = deploy.Credentials()
    mcp = deploy.Mcp(creds)

    err = mcp.initialize()
    if err:
        message = err.get("unauthorized") if isinstance(err, dict) else str(err)[:300]
        print(f"initialize failed: {message}")
        raise SystemExit(1)

    if args.call:
        try:
            payload = json.loads(args.args)
        except json.JSONDecodeError as exc:
            print(f"--args is not JSON: {exc}")
            raise SystemExit(2)
        result, err = mcp.call(args.call, payload)
        if err:
            print(f"{args.call} failed: {str(err)[:600]}")
            raise SystemExit(1)
        print(deploy.mcp_text(result))
        return

    tools, err = mcp.tools()
    if err:
        print(f"tools/list failed: {str(err)[:300]}")
        raise SystemExit(1)

    names = sorted(t.get("name", "") for t in tools)
    print(f"connected to {creds.site} — {len(names)} tools\n")

    groups = defaultdict(list)
    for name in names:
        groups[name.split("_", 1)[0] if "_" in name else "core"].append(name)
    print("by prefix:")
    for prefix in sorted(groups, key=lambda p: -len(groups[p])):
        print(f"  {prefix:16} {len(groups[prefix]):3}  {', '.join(groups[prefix][:4])}"
              f"{' …' if len(groups[prefix]) > 4 else ''}")

    print("\nwhat the manifests need:")
    missing_required = 0
    for candidates, weight, why in WANTED:
        hit = next((c for c in candidates if c in names), None)
        if hit:
            print(f"  yes   {hit:32} {why}")
        else:
            if weight == "required":
                missing_required += 1
            mark = "NO " if weight == "required" else "—  "
            print(f"  {mark}  {' / '.join(candidates[:2]):32} {why}")

    if args.grep:
        print(f"\nnames containing {args.grep!r}:")
        for name in names:
            if args.grep.lower() in name.lower():
                print("  " + name)
    elif not args.all:
        print("\n(pass --all for every name, --grep <text> to search)")

    if missing_required:
        print(f"\n{missing_required} required tool(s) absent — do not run the punk manifest "
              f"until the names are known. deploy.py will report and skip them, "
              f"not guess.")
        raise SystemExit(1)


if __name__ == "__main__":
    main()
