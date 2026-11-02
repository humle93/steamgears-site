#!/usr/bin/env python3
"""Synkroniserer startkontrakten (AGENTS.md §0) til alle projekter på GitHub.

Kontrakten i AGENTS.md §0 er den fælles besked til alle Freebuff-agenter: timer,
procenter, dokumentation, roadmap, procent færdig, resterende tid og MCP-login.
Fordi Freebuff læser AGENTS.md ved hver opstart, er det nok at filen ligger i
repoet — derfor opretter scriptet manglende repos og lægger kontrakten ind.

Brug:
    python3 sync_repos.py --dry-run     # vis planen, skriv intet (standard uden token)
    python3 sync_repos.py --check       # som dry-run, men fejler hvis noget mangler
    python3 sync_repos.py --apply       # opret repos og skriv AGENTS.md (kræver GITHUB_TOKEN)

Token læses kun fra miljøet (GITHUB_TOKEN) og skrives aldrig ud.
"""

from __future__ import annotations

import argparse
import base64
import json
import os
import re
import sys
import urllib.error
import urllib.request

API = "https://api.github.com"
HERE = os.path.dirname(os.path.abspath(__file__))
MANIFEST = os.path.join(HERE, "repos.json")
CONTRACT_FILE = "AGENTS.md"

NAME_RE = re.compile(r"^[A-Za-z0-9._-]{1,100}$")
BLOCK_RE = re.compile(r"<!-- AUTO:STARTKONTRAKT:START -->(.*?)<!-- AUTO:STARTKONTRAKT:END -->", re.S)

PREAMBLE = """# AGENTS.md — startkontrakt

Denne fil er den fælles besked til alle agenter, der arbejder i repoet. Den læses
ved hver opstart, før noget andet.

"""

FOOTER = """
---

_Kontrakten ejes af [`humle93/steamgears-site`](https://github.com/humle93/steamgears-site)
(`AGENTS.md` §0) og synkroniseres med `sync_repos.py`. Ret kilden, ikke kopien._
"""


def die(msg: str, code: int = 1) -> "None":
    print(f"[FEJL] {msg}")
    raise SystemExit(code)


def token() -> str:
    return os.environ.get("GITHUB_TOKEN", "").strip()


def request(method: str, path: str, payload: dict | None = None) -> tuple[int, dict]:
    """Returnerer (status, body). 404 giver (404, {}) i stedet for at kaste."""
    url = path if path.startswith("http") else API + path
    data = json.dumps(payload).encode() if payload is not None else None
    req = urllib.request.Request(url, data=data, method=method)
    req.add_header("Accept", "application/vnd.github+json")
    req.add_header("X-GitHub-Api-Version", "2022-11-28")
    req.add_header("User-Agent", "steamgears-sync-repos")
    if token():
        req.add_header("Authorization", f"Bearer {token()}")
    try:
        with urllib.request.urlopen(req, timeout=30) as resp:
            body = resp.read().decode() or "{}"
            return resp.status, json.loads(body)
    except urllib.error.HTTPError as exc:
        if exc.code == 404:
            return 404, {}
        detail = exc.read().decode(errors="replace")[:300]
        return exc.code, {"message": detail}
    except urllib.error.URLError as exc:
        die(f"netværksfejl mod GitHub: {exc.reason}")


def load_manifest() -> tuple[str, list[dict]]:
    if not os.path.exists(MANIFEST):
        die(f"{MANIFEST} mangler")
    try:
        data = json.load(open(MANIFEST, encoding="utf-8"))
    except json.JSONDecodeError as exc:
        die(f"repos.json er ikke gyldig JSON: {exc}")
    owner = (data.get("owner") or "").strip()
    if not owner:
        die("repos.json mangler 'owner'")
    repos = data.get("repos") or []
    if not repos:
        die("repos.json har ingen repos i 'repos'")
    seen = set()
    for repo in repos:
        name = (repo.get("name") or "").strip()
        if not NAME_RE.match(name):
            die(f"ugyldigt repo-navn: {name!r}")
        if name.lower() in seen:
            die(f"duplikeret repo-navn: {name}")
        seen.add(name.lower())
    return owner, repos


def contract_block() -> str:
    """Uddrager §0 fra den lokale AGENTS.md (kilden)."""
    path = os.path.join(HERE, CONTRACT_FILE)
    if not os.path.exists(path):
        die(f"{CONTRACT_FILE} mangler — kan ikke uddrage startkontrakten")
    text = open(path, encoding="utf-8").read()
    match = BLOCK_RE.search(text)
    if not match:
        die("AGENTS.md mangler AUTO:STARTKONTRAKT-markørerne — kontrakten kan ikke findes")
    return match.group(1).strip("\n") + "\n"


def render(existing: str | None, block: str) -> str:
    """Lægger kontrakten ind uden at røre resten af en eksisterende fil."""
    region = "<!-- AUTO:STARTKONTRAKT:START -->\n" + block + "<!-- AUTO:STARTKONTRAKT:END -->"
    if existing is None:
        return PREAMBLE + region + "\n" + FOOTER
    if BLOCK_RE.search(existing):
        return BLOCK_RE.sub(lambda _: region, existing, count=1)
    return region + "\n" + existing.lstrip("\n")


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    group = parser.add_mutually_exclusive_group()
    group.add_argument("--dry-run", action="store_true", help="vis planen uden at skrive")
    group.add_argument("--check", action="store_true", help="fejl hvis noget mangler")
    group.add_argument("--apply", action="store_true", help="opret repos og skriv AGENTS.md")
    args = parser.parse_args()

    owner, repos = load_manifest()
    block = contract_block()
    has_token = bool(token())
    apply = args.apply

    if apply and not has_token:
        print("[BLOKERET] --apply kræver GITHUB_TOKEN i miljøet. Intet er skrevet.")
        print("           Sæt tokenet som projekt-secret, eller kør --dry-run.")
        return 1
    if not apply:
        print(f"{'DRY-RUN' if not has_token else 'PLAN'} — kontrakt: {len(block)} tegn, {len(block.splitlines())} linjer")

    created = updated = unchanged = missing = failed = 0

    for repo in repos:
        name = repo["name"].strip()
        full = f"{owner}/{name}"
        status, body = request("GET", f"/repos/{full}")

        if status == 404:
            missing += 1
            line = f"[MANGLER] {full}"
            if apply:
                code, created_body = request("POST", "/user/repos", {
                    "name": name,
                    "private": bool(repo.get("private", True)),
                    "description": repo.get("description", ""),
                    "auto_init": True,
                })
                if code not in (200, 201):
                    print(f"[FEJL] kunne ikke oprette {full}: {code} {created_body.get('message', '')}")
                    failed += 1
                    continue
                print(f"[OPRETTET] {full}")
                created += 1
            else:
                print(line)
                continue
        elif status != 200:
            print(f"[FEJL] {full}: HTTP {status} {body.get('message', '')}")
            failed += 1
            continue
        else:
            if apply:
                print(f"[FINDES] {full}")

        # Læs og sammenlign AGENTS.md
        cstatus, cbody = request("GET", f"/repos/{full}/contents/{CONTRACT_FILE}")
        existing = None
        sha = None
        if cstatus == 200:
            existing = base64.b64decode(cbody.get("content", "")).decode("utf-8", "replace")
            sha = cbody.get("sha")
        elif cstatus != 404:
            print(f"[FEJL] {full}/{CONTRACT_FILE}: HTTP {cstatus}")
            failed += 1
            continue

        wanted = render(existing, block)
        if existing == wanted:
            unchanged += 1
            print(f"[OK]      {full} — kontrakten er opdateret")
            continue

        if not apply:
            action = "opdaterer" if existing else "tilføjer"
            print(f"[PLAN]    {full} — {action} {CONTRACT_FILE}")
            continue

        payload = {
            "message": "chore(agents): synkroniser startkontrakt fra steamgears-site",
            "content": base64.b64encode(wanted.encode()).decode(),
        }
        if sha:
            payload["sha"] = sha
        pcode, pbody = request("PUT", f"/repos/{full}/contents/{CONTRACT_FILE}", payload)
        if pcode in (200, 201):
            updated += 1
            print(f"[SKREVET] {full} — {CONTRACT_FILE} opdateret")
        else:
            failed += 1
            print(f"[FEJL]   {full} — HTTP {pcode} {pbody.get('message', '')}")

    print()
    print(f"Repos: {len(repos)} · oprettet {created} · skrevet {updated} · i orden {unchanged} · mangler {missing} · fejl {failed}")
    if not has_token and not apply:
        print("Intet er skrevet: GITHUB_TOKEN er ikke sat. Planen er kun vist.")

    if args.check:
        return 1 if (missing or updated or failed) else 0
    return 1 if failed else 0


if __name__ == "__main__":
    sys.exit(main())
