#!/usr/bin/env python3
"""mcp_keepalive.py — hold Novamira-adgangen i live ved at forny refresh-tokenet.

Access-tokenet lever 3600 s, og refresh-tokenet **roteres** ved hver fornyelse: det
gamle er dødt bagefter. Derfor skal fornyelsen gemme det nye par med det samme.

To tilstande:

    python3 tools/mcp_keepalive.py                 # lokalt: læs og skriv tokenfilen
    python3 tools/mcp_keepalive.py --ci \\
        --emit-refresh-file /tmp/refresh           # CI: læs miljøvariabler, skriv nyt token til fil

Miljøvariabler (begge tilstande kan overstyre stierne):
    NOVAMIRA_CLIENT_ID      offentlig klient-id (ikke en hemmelighed)
    NOVAMIRA_REFRESH_TOKEN  refresh-tokenet (kun --ci; ellers læses tokenfilen)
    MCP_CONFIG_DIR          hvor tokenfilen ligger (uden for repoet)
    NOVAMIRA_TOKEN_ENDPOINT standard https://steamgears.eu/wp-json/novamira/v1/oauth/token
    NOVAMIRA_RESOURCE       standard https://steamgears.eu/wp-json/mcp/novamira-oauth

Efter fornyelsen verificeres adgangen med `initialize` + `tools/list`, så et token
der ikke virker ikke bliver gemt som "fornyet". Exit 0 = fornyet og verificeret.
"""

from __future__ import annotations

import json
import os
import sys
import time
import urllib.error
import urllib.parse
import urllib.request
from pathlib import Path

TOKEN_ENDPOINT = os.environ.get("NOVAMIRA_TOKEN_ENDPOINT") or "https://steamgears.eu/wp-json/novamira/v1/oauth/token"
RESOURCE = os.environ.get("NOVAMIRA_RESOURCE") or "https://steamgears.eu/wp-json/mcp/novamira-oauth"
CONFIG_DIR = Path(os.environ.get("MCP_CONFIG_DIR") or Path.home() / ".config" / "steamgears-mcp" / "novamira")
TOKEN_FILE = CONFIG_DIR / "tokens.json"
CLIENT_FILE = CONFIG_DIR / "client.json"
LOG_FILE = CONFIG_DIR / "keepalive.log"
UA = "steamgears-mcp-keepalive/1.0"


def write_private(path: Path, payload: dict) -> None:
    path.parent.mkdir(parents=True, exist_ok=True)
    os.chmod(path.parent, 0o700)
    tmp = path.with_suffix(path.suffix + ".tmp")
    tmp.write_text(json.dumps(payload, indent=1) + "\n", encoding="utf-8")
    os.chmod(tmp, 0o600)
    os.replace(tmp, path)


def read_json(path: Path) -> dict:
    try:
        return json.loads(path.read_text(encoding="utf-8"))
    except (OSError, ValueError):
        return {}


def post(url: str, payload: dict, form: bool = False, token: str = "",
         extra: dict | None = None) -> tuple[int, dict | str, dict]:
    body = urllib.parse.urlencode(payload).encode() if form else json.dumps(payload).encode()
    headers = {"Content-Type": "application/x-www-form-urlencoded" if form else "application/json",
               "Accept": "application/json, text/event-stream", "User-Agent": UA}
    if token:
        headers["Authorization"] = "Bearer " + token
    if extra:
        headers.update(extra)
    req = urllib.request.Request(url, data=body, method="POST", headers=headers)
    try:
        with urllib.request.urlopen(req, timeout=45) as response:
            raw = response.read().decode("utf-8", "replace")
            try:
                return response.status, json.loads(raw), dict(response.headers)
            except ValueError:
                return response.status, raw, dict(response.headers)
    except urllib.error.HTTPError as exc:
        raw = exc.read().decode("utf-8", "replace")
        try:
            return exc.code, json.loads(raw), dict(exc.headers)
        except ValueError:
            return exc.code, raw, dict(exc.headers)
    except urllib.error.URLError as exc:
        return 0, f"nåede ikke {url}: {exc.reason}", {}


def credentials() -> tuple[str, str, bool]:
    """(client_id, refresh_token, ci-tilstand)."""
    ci = "--ci" in sys.argv
    client_id = os.environ.get("NOVAMIRA_CLIENT_ID") or read_json(CLIENT_FILE).get("client_id") or ""
    refresh = os.environ.get("NOVAMIRA_REFRESH_TOKEN") if ci else ""
    if not refresh:
        refresh = read_json(TOKEN_FILE).get("refresh_token") or ""
    if not client_id:
        raise SystemExit("mangler klient-id: sæt NOVAMIRA_CLIENT_ID eller kør tools/mcp_oauth.py --start først")
    if not refresh:
        raise SystemExit("mangler refresh-token: sæt NOVAMIRA_REFRESH_TOKEN (CI) eller godkend klienten lokalt")
    return client_id, refresh, ci


def refresh_once(client_id: str, refresh_token: str) -> dict:
    status, body, _ = post(TOKEN_ENDPOINT, {"grant_type": "refresh_token",
                                            "refresh_token": refresh_token,
                                            "client_id": client_id}, form=True)
    if status != 200 or not isinstance(body, dict) or not body.get("access_token"):
        raise SystemExit(f"fornyelse afvist (HTTP {status}): {str(body)[:200]}")
    rotated = body.get("refresh_token") not in (None, "", refresh_token)
    expires_in = body.get("expires_in")
    return {
        "access_token": body["access_token"],
        "refresh_token": body.get("refresh_token") or refresh_token,
        "expires_at": int(time.time()) + int(expires_in) - 60 if expires_in else None,
        "expires_in": int(expires_in) if expires_in else None,
        "rotated": rotated,
        "scope": body.get("scope"),
        "token_type": body.get("token_type", "Bearer"),
    }


def verify(access_token: str) -> int:
    """initialize + tools/list. Session-headeren skal med på hvert efterfølgende kald."""
    status, body, headers = post(RESOURCE, {"jsonrpc": "2.0", "id": 1, "method": "initialize",
                                            "params": {"protocolVersion": "2025-06-18", "capabilities": {},
                                                       "clientInfo": {"name": "steamgears-keepalive",
                                                                      "version": "1.0"}}},
                                 token=access_token)
    if status != 200 or not isinstance(body, dict) or "result" not in body:
        raise SystemExit(f"adgangen virker ikke efter fornyelsen (initialize HTTP {status}): {str(body)[:160]}")
    session = headers.get("Mcp-Session-Id") or headers.get("mcp-session-id")
    extra = {"Mcp-Session-Id": session} if session else None
    post(RESOURCE, {"jsonrpc": "2.0", "method": "notifications/initialized"}, token=access_token, extra=extra)
    status, body, _ = post(RESOURCE, {"jsonrpc": "2.0", "id": 2, "method": "tools/list", "params": {}},
                           token=access_token, extra=extra)
    tools = (body.get("result") or {}).get("tools") if isinstance(body, dict) else None
    if status != 200 or not tools:
        raise SystemExit(f"tools/list svarede HTTP {status} — tokenet er ikke brugbart: {str(body)[:160]}")
    return len(tools)


def main() -> int:
    client_id, refresh_token, ci = credentials()
    result = refresh_once(client_id, refresh_token)
    tools = verify(result["access_token"])

    if ci:
        emit = None
        if "--emit-refresh-file" in sys.argv:
            index = sys.argv.index("--emit-refresh-file")
            if index + 1 < len(sys.argv):
                emit = Path(sys.argv[index + 1])
        if emit is not None:
            emit.parent.mkdir(parents=True, exist_ok=True)
            emit.write_text(result["refresh_token"], encoding="utf-8")
            os.chmod(emit, 0o600)
            print(f"[OK] fornyet i CI: {tools} værktøjer, nyt refresh-token skrevet til {emit.name} "
                  f"(roteret: {result['rotated']})")
        else:
            print(f"[OK] fornyet i CI: {tools} værktøjer (roteret: {result['rotated']}) — "
                  "intet token skrevet, brug --emit-refresh-file for at gemme det nye")
        return 0

    tokens = read_json(TOKEN_FILE)
    tokens.update({"access_token": result["access_token"], "refresh_token": result["refresh_token"],
                   "expires_at": result["expires_at"], "scope": result["scope"] or tokens.get("scope"),
                   "token_type": result["token_type"], "resource": RESOURCE})
    write_private(TOKEN_FILE, tokens)
    CONFIG_DIR.mkdir(parents=True, exist_ok=True)
    with LOG_FILE.open("a", encoding="utf-8") as log:
        log.write(f"{time.strftime('%Y-%m-%dT%H:%M:%S')} fornyet expires_in={result['expires_in']} "
                  f"roteret={result['rotated']} vaerktoejer={tools}\n")
    print(f"[OK] fornyet: {tools} værktøjer, gyldigt {result['expires_in']} s, "
          f"refresh roteret: {result['rotated']}. Tokenfil: {TOKEN_FILE} (mode 600)")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
