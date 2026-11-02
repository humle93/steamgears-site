#!/usr/bin/env python3
"""mcp_rest.py — MCP mod sitets AI Engine-server over HTTP.

Sitet svarer på `https://steamgears.eu/wp-json/mcp/v1/http` (AI Engine, Meow Apps). Det er
standard MCP over HTTP med `Mcp-Session-Id`, og det kræver en credential:

  * **Bearer-token** fra OAuth (anbefalet) — hentes med `tools/mcp_oauth.py --start/--finish`
    og ligger uden for repoet, så det ikke kan committes.
  * **Application password** (Basic) som fallback, hvis sitet accepterer det på ruten.

Kør:
    python3 tools/mcp_oauth.py --start             # godkendelseslink (OAuth, PKCE)
    python3 tools/mcp_rest.py --check              # initialize + tools/list
    python3 tools/mcp_rest.py --abilities          # alle værktøjer med skema + skrive-flag
    python3 tools/mcp_rest.py --abilities --diff docs/library/mcp-adapter-abilities.csv
    python3 tools/mcp_rest.py --call <værktøj> '<json-objekt>'
    python3 tools/mcp_rest.py --ask                # application password i stedet (getpass)

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
URL = os.environ.get("MCP_REST_URL") or "https://steamgears.eu/wp-json/mcp/v1/http"
PROTOCOL = "2025-06-18"
NAMES = ("MCP_USER", "MCP_APP_PASSWORD")
CLIENT_NAME = "steamgears-agent"


def oauth_token() -> str:
    """Gyldigt Bearer-token fra OAuth-filerne (fornyes automatisk hvis det er ved at udløbe)."""
    token = os.environ.get("MCP_ACCESS_TOKEN", "").strip()
    if token:
        return token
    try:
        sys.path.insert(0, str(Path(__file__).resolve().parent))
        import mcp_oauth

        return mcp_oauth.access_token() or ""
    except Exception:
        return ""


def read_basic() -> tuple[str, str]:
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


def basic_header(user: str, secret: str) -> str:
    return "Basic " + base64.b64encode(f"{user}:{secret}".encode()).decode()


def resolve_auth() -> str:
    """Bearer foretrækkes; ellers Basic fra .env. Tom streng = ingen credential."""
    token = oauth_token()
    if token:
        return "Bearer " + token
    user, secret = read_basic()
    if user and secret:
        return basic_header(user, secret)
    return ""


def post(payload: dict, auth: str) -> tuple[int, str, dict]:
    headers = {
        "Content-Type": "application/json",
        "Accept": "application/json, text/event-stream",
        "User-Agent": CLIENT_NAME + "/1.0",
    }
    if auth:
        headers["Authorization"] = auth
    request = urllib.request.Request(URL, data=json.dumps(payload).encode(), headers=headers, method="POST")
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


def handshake(auth: str) -> tuple[bool, str]:
    status, raw, headers = post(
        {"jsonrpc": "2.0", "id": 1, "method": "initialize",
         "params": {"protocolVersion": PROTOCOL, "capabilities": {},
                    "clientInfo": {"name": CLIENT_NAME, "version": "1"}}},
        auth,
    )
    body = parse_body(raw)
    if status != 200 or not body or "result" not in body:
        message = (body or {}).get("message") if isinstance(body, dict) else raw[:160]
        return False, f"HTTP {status}: {message}"
    post({"jsonrpc": "2.0", "method": "notifications/initialized"}, auth)
    server = body["result"].get("serverInfo", {})
    session = headers.get("Mcp-Session-Id") or headers.get("mcp-session-id")
    scheme = auth.split(" ", 1)[0] if auth else "ingen"
    return True, json.dumps({"server": server, "session": bool(session), "auth": scheme}, ensure_ascii=False)


def tools_list(auth: str) -> tuple[int, list[dict]]:
    status, raw, _ = post({"jsonrpc": "2.0", "id": 2, "method": "tools/list"}, auth)
    body = parse_body(raw) or {}
    return status, (body.get("result") or {}).get("tools") or []


def store_credentials(user: str, secret: str) -> None:
    old = ENV.read_text(encoding="utf-8") if ENV.exists() else ""
    kept = [line for line in old.splitlines() if not any(line.strip().startswith(n + "=") for n in NAMES)]
    body = "\n".join([f"MCP_USER={user}", f"MCP_APP_PASSWORD={secret}", *kept]).strip() + "\n"
    tmp = ENV.with_suffix(".env.tmp")
    tmp.write_text(body, encoding="utf-8")
    os.chmod(tmp, 0o600)
    os.replace(tmp, ENV)


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
    ok, detail = handshake(basic_header(user, secret))
    print(f"Test mod MCP-serveren: {'OK' if ok else 'AFVIST'} — {detail}")
    if not ok:
        return 1
    store_credentials(user, secret)
    print(f"Skrev {ENV.name}: {len(secret)} tegn app-password for {user}, mode 600. Kildefilen er slettet.")
    return 0


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
    ok, detail = handshake(basic_header(user, secret))
    print(f"Test mod MCP-serveren: {'OK' if ok else 'AFVIST'} — {detail}")
    if not ok:
        return 1
    store_credentials(user, secret)
    print(f"Skrev .env: {len(secret)} tegn app-password for {user}, mode 600.")
    return 0


WRITE_HINTS = ("create", "update", "delete", "edit", "publish", "insert", "set", "upload", "trash",
               "duplicate", "execute", "run", "apply", "modify")
TARGETS = ("product", "page", "post", "order", "media", "user", "comment", "ability")


def classify(tool: dict) -> dict:
    """Afgør om et værktøj skriver, og hvad det rammer — ud fra navn, beskrivelse og MCP-annotations.

    Meta-værktøjet `execute-ability` har ingen `update`/`create` i navnet, så et rent navne-opslag
    ville kalde den eneste skrivevej for læsning. Derfor tælles annotations og verbet med.
    """
    name = (tool.get("name") or "").lower()
    description = tool.get("description") or ""
    annotations = tool.get("annotations") or {}
    write: bool | None = None
    if isinstance(annotations.get("readOnlyHint"), bool):
        write = not annotations["readOnlyHint"]
    if annotations.get("destructiveHint") is True:
        write = True
    if write is None:
        write = any(hint in name for hint in WRITE_HINTS)
    target = next((t for t in TARGETS if t in name), None)
    if target is None:
        folded = description.lower()
        target = next((t for t in TARGETS if t + "s" in folded or t in folded), "-")
    return {"name": tool.get("name"), "write": write, "target": target,
            "params": list(((tool.get("inputSchema") or {}).get("properties") or {}).keys()),
            "desc": description[:120]}


def sheet_names(path: Path) -> list[str]:
    """Læs ability-navnene fra det kuraterede regneark (kolonne 1, `(REST) `-præfiks fjernet)."""
    import csv as _csv

    if not path.exists():
        return []
    with path.open(encoding="utf-8") as handle:
        rows = list(_csv.DictReader(handle))
    names = []
    for row in rows:
        name = re.sub(r"^\(REST\)\s*", "", (row.get("ability") or "").strip()).strip()
        if name:
            names.append(name.lower())
    return names


def compare(rows: list[dict], known: list[str]) -> dict:
    """Sammenlign den levende værktøjsliste med regnearket, så opdateringen bliver mekanisk."""
    live = {row["name"].lower(): row for row in rows if row.get("name")}
    return {
        "live": len(live),
        "live_skriver": sorted(name for name, row in live.items() if row["write"]),
        "mangler_live": [name for name in known if name not in live],
        "ikke_i_arket": sorted(name for name in live if name not in known),
    }


def abilities(auth: str, csv_path: Path | None = None, diff_path: Path | None = None) -> int:
    ok, detail = handshake(auth)
    if not ok:
        print(json.dumps({"ok": False, "error": detail}, ensure_ascii=False))
        return 1
    status, tools = tools_list(auth)
    rows = [classify(tool) for tool in tools]
    if csv_path is not None:
        import csv as _csv

        with csv_path.open("w", newline="", encoding="utf-8") as handle:
            writer = _csv.writer(handle)
            writer.writerow(["navn", "skriver", "rammer", "parametre", "beskrivelse"])
            for row in rows:
                writer.writerow([row["name"], row["write"], row["target"], " ".join(row["params"]), row["desc"]])
        print(f"Skrev {len(rows)} værktøjer til {csv_path}")
        return 0

    if diff_path is not None:
        summary = compare(rows, sheet_names(diff_path))
        summary["ok"] = True
        summary["status"] = status
        print(json.dumps(summary, ensure_ascii=False, indent=1))
        return 0

    print(json.dumps({"ok": True, "status": status, "tools": len(rows), "rows": rows}, ensure_ascii=False, indent=1))
    return 0


def path_flag(args: list[str], flag: str) -> tuple[Path | None, int]:
    if flag not in args:
        return None, 0
    at = args.index(flag)
    if at + 1 >= len(args):
        print(json.dumps({"ok": False, "error": f"{flag} kraever en sti"}, ensure_ascii=False))
        return None, 1
    return Path(args[at + 1]), 0


def main() -> int:
    args = sys.argv[1:]
    if args[:1] == ["--ask"]:
        return ask()
    if args[:1] == ["--import"]:
        return import_file(Path(args[1]) if len(args) > 1 else ROOT / "mcp_rest.txt")
    if args[:1] == ["--abilities"]:
        csv_path, code = path_flag(args, "--csv")
        if code:
            return code
        diff_path, code = path_flag(args, "--diff")
        if code:
            return code
        auth = resolve_auth()
        if not auth:
            print(json.dumps({"ok": False, "error": "ingen credential — kør tools/mcp_oauth.py --start (OAuth) eller --ask"}, ensure_ascii=False))
            return 1
        return abilities(auth, csv_path, diff_path)

    auth = resolve_auth()
    if not auth:
        print(json.dumps({"ok": False, "error": "ingen credential — kør tools/mcp_oauth.py --start (OAuth) eller --ask"}, ensure_ascii=False))
        return 1

    if args[:1] == ["--call"]:
        if len(args) < 2:
            print("brug: --call <vaerktoej> '<json>'")
            return 1
        ok, detail = handshake(auth)
        if not ok:
            print(json.dumps({"ok": False, "error": detail}, ensure_ascii=False))
            return 1
        payload = {"jsonrpc": "2.0", "id": 3, "method": "tools/call",
                   "params": {"name": args[1], "arguments": json.loads(args[2]) if len(args) > 2 else {}}}
        status, raw, _ = post(payload, auth)
        body = parse_body(raw) or {}
        print(json.dumps(body.get("result", {"status": status, "raw": raw[:300]}), ensure_ascii=False, indent=2))
        return 0 if "result" in body else 1

    ok, detail = handshake(auth)
    status, tools = tools_list(auth) if ok else (0, [])
    print(json.dumps({
        "ok": ok,
        "detail": detail,
        "tools": len(tools),
        "names": [tool.get("name") for tool in tools[:12]],
    }, ensure_ascii=False, indent=2))
    return 0 if ok else 1


if __name__ == "__main__":
    raise SystemExit(main())
