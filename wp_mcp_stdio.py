#!/usr/bin/env python3
"""wp_mcp_stdio.py — stdio-bro til Royal MCP på steamgears.eu.

MCP-ruten accepterer **kun** `Authorization: Bearer <nøgle>` — ikke et WordPress
application password. Denne bro læser en Royal MCP-API-nøgle og videresender
JSON-RPC-linjer fra stdin til `https://steamgears.eu/mcp`, så en klient uden
browser (CI, script, Claude Desktop uden OAuth) kan bruge de samme værktøjer.

Nøglen læses i denne rækkefølge:
  1. miljøvariablen ROYAL_MCP_API_KEY
  2. .env i repoets rod (gitignored)
  3. ~/.royal_mcp_key

Brug:
    printf '%s\\n' '{"jsonrpc":"2.0","id":1,"method":"tools/list"}' | python3 wp_mcp_stdio.py
    python3 wp_mcp_stdio.py --check     # initialize + tools/list, KPI-linje på stdout

Fejler kaldet, skrives et JSON-RPC-error-objekt på stdout, så protokollen holdes intakt;
diagnostik går til stderr. Nøglen printes aldrig.
"""

from __future__ import annotations

import json
import os
import re
import sys
import urllib.error
import urllib.request
from pathlib import Path

ROOT = Path(__file__).resolve().parent
SITE = os.getenv("WP_SITE", "https://steamgears.eu").rstrip("/")
URL = f"{SITE}/mcp"
PROTOCOL = "2025-06-18"
KEY_FILE = Path.home() / ".royal_mcp_key"


def log(message: str) -> None:
    sys.stderr.write(f"[royal-mcp] {message}\n")
    sys.stderr.flush()


def read_key() -> str:
    key = os.getenv("ROYAL_MCP_API_KEY", "").strip()
    if key:
        return key
    env_file = ROOT / ".env"
    if env_file.exists():
        for line in env_file.read_text(encoding="utf-8", errors="replace").splitlines():
            match = re.match(r"\s*ROYAL_MCP_API_KEY\s*=\s*(.+?)\s*$", line)
            if match:
                return match.group(1).strip().strip("'\"")
    if KEY_FILE.exists():
        return KEY_FILE.read_text(encoding="utf-8").strip()
    return ""


def parse_messages(raw: str, content_type: str) -> list[dict]:
    """Royal MCP svarer enten rent JSON eller som SSE (`data:`-linjer)."""
    if "text/event-stream" in content_type:
        messages: list[dict] = []
        chunk: list[str] = []
        for line in raw.splitlines() + [""]:
            if line.startswith("data:"):
                chunk.append(line[5:].strip())
            elif not line.strip() and chunk:
                payload = "\n".join(chunk)
                chunk = []
                try:
                    messages.append(json.loads(payload))
                except ValueError:
                    log(f"kunne ikke læse SSE-besked: {payload[:120]}")
        return messages
    if not raw.strip():
        return []
    try:
        parsed = json.loads(raw)
    except ValueError:
        log(f"svaret var ikke JSON: {raw[:200]}")
        return []
    return parsed if isinstance(parsed, list) else [parsed]


class Bridge:
    def __init__(self, key: str) -> None:
        self.key = key
        self.session_id = ""
        self.initialized = False

    def post(self, payload: dict) -> list[dict]:
        headers = {
            "Content-Type": "application/json",
            "Accept": "application/json, text/event-stream",
            "User-Agent": "steamgears-mcp-stdio/2.0",
        }
        if self.key:
            headers["Authorization"] = f"Bearer {self.key}"
        if self.session_id:
            headers["Mcp-Session-Id"] = self.session_id
        request = urllib.request.Request(
            URL, data=json.dumps(payload).encode("utf-8"), headers=headers, method="POST"
        )
        try:
            with urllib.request.urlopen(request, timeout=60) as response:
                session = response.headers.get("Mcp-Session-Id", "")
                if session:
                    self.session_id = session
                return parse_messages(
                    response.read().decode("utf-8", "replace"),
                    response.headers.get("Content-Type", ""),
                )
        except urllib.error.HTTPError as exc:
            body = exc.read().decode("utf-8", "replace")[:300]
            if exc.code == 401 and not self.key:
                raise RuntimeError(
                    "401 — ingen ROYAL_MCP_API_KEY. Lav nøglen i wp-admin → Royal MCP → "
                    "Settings og sæt den som miljøvariabel eller i ~/.royal_mcp_key."
                ) from exc
            raise RuntimeError(f"HTTP {exc.code}: {body}") from exc
        except urllib.error.URLError as exc:
            raise RuntimeError(f"nåede ikke {URL}: {exc.reason}") from exc

    def handle(self, message: dict) -> list[dict]:
        method = message.get("method", "")
        results = self.post(message)
        if method == "initialize":
            self.post({"jsonrpc": "2.0", "method": "notifications/initialized"})
            self.initialized = True
        return results


def main() -> int:
    key = read_key()
    bridge = Bridge(key)
    if not key:
        log("ingen nøgle fundet — kalder alligevel, så fejlen bliver synlig")

    if "--check" in sys.argv:
        try:
            messages = bridge.handle(
                {
                    "jsonrpc": "2.0",
                    "id": 1,
                    "method": "initialize",
                    "params": {
                        "protocolVersion": PROTOCOL,
                        "capabilities": {},
                        "clientInfo": {"name": "verify", "version": "1"},
                    },
                }
            )
            server = next((m.get("result", {}).get("serverInfo", {}) for m in messages if "result" in m), {})
            tools = bridge.handle({"jsonrpc": "2.0", "id": 2, "method": "tools/list"})
            names = [
                tool.get("name", "")
                for message in tools
                for tool in message.get("result", {}).get("tools", [])
            ]
            print(json.dumps({
                "ok": True,
                "server": server,
                "session": bool(bridge.session_id),
                "tools": len(names),
                "first": names[:8],
            }, ensure_ascii=False))
            return 0
        except RuntimeError as exc:
            print(json.dumps({"ok": False, "error": str(exc)}, ensure_ascii=False))
            return 1

    for line in sys.stdin:
        line = line.strip()
        if not line:
            continue
        try:
            message = json.loads(line)
        except ValueError:
            log("stdin var ikke JSON")
            continue
        try:
            for reply in bridge.handle(message):
                sys.stdout.write(json.dumps(reply, ensure_ascii=False) + "\n")
                sys.stdout.flush()
        except RuntimeError as exc:
            sys.stdout.write(json.dumps({
                "jsonrpc": "2.0",
                "id": message.get("id"),
                "error": {"code": -32000, "message": str(exc)},
            }, ensure_ascii=False) + "\n")
            sys.stdout.flush()
    return 0


if __name__ == "__main__":
    sys.exit(main())
