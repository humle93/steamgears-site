#!/usr/bin/env python3
"""mcp_rest.py — MCP mod WordPress' egen MCP Adapter over REST med application password.

Royal MCP blev slukket 2/11 2026, men `WordPress/mcp-adapter` svarer på
`/wp-json/mcp/mcp-adapter-default-server`. Den bruger WordPress' egen REST-auth, så
her er credential et **application password** (Basic), ikke en Bearer-nøgle.

Kør:
    python3 tools/mcp_rest.py --ask                   # sprørger i terminalen (getpass, ingen echo)
    python3 tools/mcp_rest.py --import mcp_rest.txt   # læser "bruger:app-password", tester, gemmer i .env, sletter filen
    python3 tools/mcp_rest.py --check                 # initialize + tools/list
    python3 tools/mcp_rest.py --call <værktøj> '<json-objekt>'
    python3 tools/mcp_rest.py --abilities             # alle værktøjer med skema + skrive-flag

Hemmeligheden printer aldrig: kun brugernavn, længder og serverens svar.
"""

from __future__ import annotations

import base64
import json
import os
import re
import sys
import urllib.error
import urllib.request
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
ENV = ROOT / ".env"
URL = os.environ.get("MCP_REST_URL") or "https://steamgears.eu/wp-json/mcp/mcp-adapter-default-server"
PROTOCOL = "2025-06-18"
NAMES = ("MCP_USER", "MCP_APP_PASSWORD")


def read_credentials() -> tuple[str, str]:
    user = os.environ.get("MCP_USER", "").strip()
    secret = os.environ.get("MCP_APP_PASSWORD", "").strip()
    if user and secret:
        return user, secret
    if ENV.exists():
        found: dict[str, str] = {}
        for line in ENV.read_text(encoding="utf-8", errors="replace").splitlines():
            match = re.match(rf"\s*({'|'.join(NAMES)})\s*=\s*(.+?)\s*$", line)
            if match:
                found[match.group(1)] = match.group(2).strip().strip("'\"")
        if found.get("MCP_USER") and found.get("MCP_APP_PASSWORD"):
            return found["MCP_USER"], found["MCP_APP_PASSWORD"]
    return user, secret


def post(payload: dict, user: str, secret: str) -> tuple[int, str, dict]:
    token = base64.b64encode(f"{user}:{secret}".encode()).decode()
    request = urllib.request.Request(
        URL,
        data=json.dumps(payload).encode(),
        headers={
            "Content-Type": "application/json",
            "Accept": "application/json, text/event-stream",
            "Authorization": "Basic " + token,
            "User-Agent": "steamgears-mcp-rest/1.0",
        },
        method="POST",
    )
    try:
        with urllib.request.urlopen(request, timeout=60) as response:
            return response.status, response.read().decode("utf-8", "replace"), dict(response.headers)
    except urllib.error.HTTPError as exc:
        return exc.code, exc.read().decode("utf-8", "replace"), dict(exc.headers or {})
    except urllib.error.URLError as exc:
        return 0, f"nåede ikke {URL}: {exc.reason}", {}


def parse_body(raw: str) -> dict | None:
    raw = raw.strip()
    if not raw:
        return None
    if raw.startswith("event:") or raw.startswith("data:"):
        for line in raw.splitlines():
            if line.startswith("data:"):
                try:
                    return json.loads(line[5:].strip())
                except ValueError:
                    return None
    try:
        return json.loads(raw)
    except ValueError:
        return None


def handshake(user: str, secret: str) -> tuple[bool, str]:
    status, raw, headers = post(
        {"jsonrpc": "2.0", "id": 1, "method": "initialize",
         "params": {"protocolVersion": PROTOCOL, "capabilities": {},
                    "clientInfo": {"name": "steamgears-agent", "version": "1"}}},
        user, secret,
    )
    body = parse_body(raw)
    if status != 200 or not body or "result" not in body:
        message = (body or {}).get("message") if isinstance(body, dict) else raw[:160]
        return False, f"HTTP {status}: {message}"
    post({"jsonrpc": "2.0", "method": "notifications/initialized"}, user, secret)
    server = body["result"].get("serverInfo", {})
    session = headers.get("Mcp-Session-Id") or headers.get("mcp-session-id")
    return True, json.dumps({"server": server, "session": bool(session)}, ensure_ascii=False)


def tools_list(user: str, secret: str) -> tuple[int, list[dict]]:
    status, raw, _ = post({"jsonrpc": "2.0", "id": 2, "method": "tools/list"}, user, secret)
    body = parse_body(raw) or {}
    return status, (body.get("result") or {}).get("tools") or []


def import_file(path: Path) -> int:
    if not path.exists():
        print(f"Filen {path} findes ikke — .env er uaendret.")
        return 1
    raw = path.read_text(encoding="utf-8", errors="replace").strip()
    path.unlink(missing_ok=True)
    if ":" not in raw:
        print("Forventede 'brugernavn:app-password' paa én linje — .env er uaendret.")
        return 1
    user, secret = (part.strip() for part in raw.split(":", 1))
    if not user or len(secret) < 8:
        print("Brugernavn eller app-password mangler — .env er uaendret.")
        return 1

    ok, detail = handshake(user, secret)
    print(f"Test mod adapteren: {'OK' if ok else 'AFVIST'} — {detail}")
    if not ok:
        return 1

    old = ENV.read_text(encoding="utf-8") if ENV.exists() else ""
    kept = [line for line in old.splitlines() if not any(line.strip().startswith(n + "=") for n in NAMES)]
    body = "\n".join([f"MCP_USER={user}", f"MCP_APP_PASSWORD={secret}", *kept]).strip() + "\n"
    tmp = ENV.with_suffix(".env.tmp")
    tmp.write_text(body, encoding="utf-8")
    os.chmod(tmp, 0o600)
    os.replace(tmp, ENV)
    print(f"Skrev {ENV.name}: {len(secret)} tegn app-password for {user}, mode 600. Kildefilen er slettet.")
    return 0


def store(user: str, secret: str) -> None:
    old = ENV.read_text(encoding="utf-8") if ENV.exists() else ""
    kept = [line for line in old.splitlines() if not any(line.strip().startswith(n + "=") for n in NAMES)]
    body = "\n".join([f"MCP_USER={user}", f"MCP_APP_PASSWORD={secret}", *kept]).strip() + "\n"
    tmp = ENV.with_suffix(".env.tmp")
    tmp.write_text(body, encoding="utf-8")
    os.chmod(tmp, 0o600)
    os.replace(tmp, ENV)


def ask() -> int:
    import getpass

    user = input("WordPress-brugernavn: ").strip()
    try:
        secret = getpass.getpass("Application password (vises ikke): ").strip()
    except (EOFError, KeyboardInterrupt):
        print("\nAfbrudt — .env er uaendret.")
        return 1
    if not user or len(secret) < 8:
        print("Brugernavn eller app-password mangler — .env er uaendret.")
        return 1
    ok, detail = handshake(user, secret)
    print(f"Test mod adapteren: {'OK' if ok else 'AFVIST'} — {detail}")
    if not ok:
        return 1
    store(user, secret)
    print(f"Skrev .env: {len(secret)} tegn app-password for {user}, mode 600.")
    return 0


WRITE_HINTS = ("create", "update", "delete", "edit", "publish", "insert", "set", "upload", "trash", "duplicate")


def abilities(user: str, secret: str, csv_path: Path | None = None) -> int:
    ok, detail = handshake(user, secret)
    if not ok:
        print(json.dumps({"ok": False, "error": detail}, ensure_ascii=False))
        return 1
    status, tools = tools_list(user, secret)
    rows = []
    for tool in tools:
        name = (tool.get("name") or "").lower()
        desc = (tool.get("description") or "")[:120]
        props = list(((tool.get("inputSchema") or {}).get("properties") or {}).keys())
        write = any(hint in name for hint in WRITE_HINTS)
        target = "product" if "product" in name else "page" if "page" in name else "post" if "post" in name else "-"
        rows.append({"name": tool.get("name"), "write": write, "target": target, "params": props, "desc": desc})
    if csv_path is not None:
        import csv as _csv

        with csv_path.open("w", newline="", encoding="utf-8") as handle:
            writer = _csv.writer(handle)
            writer.writerow(["navn", "skriver", "rammer", "parametre", "beskrivelse"])
            for row in rows:
                writer.writerow([row["name"], row["write"], row["target"], " ".join(row["params"]), row["desc"]])
        print(f"Skrev {len(rows)} værktøjer til {csv_path}")
        return 0

    print(json.dumps({"ok": True, "status": status, "tools": len(rows), "rows": rows}, ensure_ascii=False, indent=1))
    return 0


def main() -> int:
    args = sys.argv[1:]
    if args[:1] == ["--ask"]:
        return ask()
    if args[:1] == ["--import"]:
        return import_file(Path(args[1]) if len(args) > 1 else ROOT / "mcp_rest.txt")
    if args[:1] == ["--abilities"]:
        user, secret = read_credentials()
        if not user or not secret:
            print(json.dumps({"ok": False, "error": "ingen MCP_USER/MCP_APP_PASSWORD — kør --ask"}, ensure_ascii=False))
            return 1
        csv_path = Path(args[args.index("--csv") + 1]) if "--csv" in args[:-1] else None
        return abilities(user, secret, csv_path)

    user, secret = read_credentials()
    if not user or not secret:
        print(json.dumps({"ok": False, "error": "ingen MCP_USER/MCP_APP_PASSWORD — kør --import mcp_rest.txt"}, ensure_ascii=False))
        return 1

    if args[:1] == ["--call"]:
        if len(args) < 2:
            print("brug: --call <vaerktoej> '<json>'")
            return 1
        ok, detail = handshake(user, secret)
        if not ok:
            print(json.dumps({"ok": False, "error": detail}, ensure_ascii=False))
            return 1
        payload = {"jsonrpc": "2.0", "id": 3, "method": "tools/call",
                   "params": {"name": args[1], "arguments": json.loads(args[2]) if len(args) > 2 else {}}}
        status, raw, _ = post(payload, user, secret)
        body = parse_body(raw) or {}
        print(json.dumps(body.get("result", {"status": status, "raw": raw[:300]}), ensure_ascii=False, indent=2))
        return 0 if "result" in body else 1

    ok, detail = handshake(user, secret)
    status, tools = tools_list(user, secret) if ok else (0, [])
    print(json.dumps({
        "ok": ok,
        "detail": detail,
        "tools": len(tools),
        "names": [tool.get("name") for tool in tools[:12]],
    }, ensure_ascii=False, indent=2))
    return 0 if ok else 1


if __name__ == "__main__":
    raise SystemExit(main())
