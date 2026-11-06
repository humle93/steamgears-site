#!/usr/bin/env python3
"""mcp_oauth.py — OAuth 2.1 (authorization code + PKCE) mod AI Engines MCP-server.

Den nye MCP-server på sitet er AI Engine (Meow Apps) og svarer på
`/wp-json/mcp/v1/http` med `Bearer`-token. Den udstiller sin egen authorization server,
så vi registrerer en klient dynamisk (DCR), henter et token med PKCE og fornyer det med
refresh-token. Intet client secret, ingen nøgle i repoet, ingen hemmelighed i chatten.

Kør:
    python3 tools/mcp_oauth.py --start          # registrér klient (én gang) og print godkendelseslink
    python3 tools/mcp_oauth.py --finish <kode>  # byt koden til tokens og gem dem (mode 600)
    python3 tools/mcp_oauth.py --status         # er der et gyldigt token, og hvornår udløber det

Filerne ligger uden for repoet i `~/.config/steamgears-mcp/` (mode 600), så de ikke kan
committes ved et uheld. Koden i linket lever kun kort — kør `--finish` med det samme.

Sitet har mere end én MCP-server. Peger miljøvariablerne på en anden, bruges samme flow:
`MCP_METADATA_URL` (authorization-server-metadata), `MCP_RESOURCE` (den beskyttede resource),
`MCP_SCOPE` (standard `mcp`) og `MCP_CONFIG_DIR` (så to servere ikke deler token-fil).
"""

from __future__ import annotations

import base64
import hashlib
import json
import os
import secrets
import sys
import time
import urllib.error
import urllib.parse
import urllib.request
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
API = os.environ.get("MCP_API_BASE") or "https://steamgears.eu/wp-json/mcp/v1"
RESOURCE = os.environ.get("MCP_RESOURCE") or "https://steamgears.eu/wp-json/mcp/v1/http"
CONFIG_DIR = Path(os.environ.get("MCP_CONFIG_DIR") or Path.home() / ".config" / "steamgears-mcp")
CLIENT_FILE = CONFIG_DIR / "client.json"
TOKEN_FILE = CONFIG_DIR / "tokens.json"
REDIRECT = os.environ.get("MCP_REDIRECT_URI") or "http://127.0.0.1:8765/callback"
METADATA_URL = os.environ.get("MCP_METADATA_URL") or "https://steamgears.eu/.well-known/oauth-authorization-server"
SCOPE = os.environ.get("MCP_SCOPE") or "mcp"


def b64url(raw: bytes) -> str:
    return base64.urlsafe_b64encode(raw).decode().rstrip("=")


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


def request(url: str, payload: dict | None = None, method: str = "POST") -> tuple[int, dict | str]:
    body = json.dumps(payload).encode() if payload is not None else None
    req = urllib.request.Request(
        url,
        data=body,
        method=method,
        headers={"Content-Type": "application/json", "Accept": "application/json",
                 "User-Agent": "steamgears-mcp-oauth/1.0"},
    )
    try:
        with urllib.request.urlopen(req, timeout=40) as response:
            raw = response.read().decode("utf-8", "replace")
            try:
                return response.status, json.loads(raw)
            except ValueError:
                return response.status, raw
    except urllib.error.HTTPError as exc:
        raw = exc.read().decode("utf-8", "replace")
        try:
            return exc.code, json.loads(raw)
        except ValueError:
            return exc.code, raw
    except urllib.error.URLError as exc:
        return 0, f"nåede ikke {url}: {exc.reason}"


def metadata() -> dict:
    status, body = request(METADATA_URL, method="GET")
    if status != 200 or not isinstance(body, dict):
        raise SystemExit(f"kunne ikke læse authorization-server-metadata (HTTP {status})")
    return body


def register_client(meta: dict) -> dict:
    payload = {
        "client_name": "steamgears-agent",
        "redirect_uris": [REDIRECT],
        "grant_types": ["authorization_code", "refresh_token"],
        "response_types": ["code"],
        "token_endpoint_auth_method": "none",
        "scope": SCOPE,
    }
    status, body = request(meta["registration_endpoint"], payload)
    if status not in (200, 201) or not isinstance(body, dict) or not body.get("client_id"):
        raise SystemExit(f"klientregistrering afvist (HTTP {status}): {str(body)[:200]}")
    return body


def authorize_url(endpoint: str, query: str) -> str:
    """Godkendelseslinket — `&` når endepunktet allerede har en query.

    AI Engines endepunkt er bart, men en WordPress-side som
    `admin.php?page=novamira-oauth-authorize` ville ellers får et ekstra `?`, og så læser
    WordPress `page` som `novamira-oauth-authorize?response_type=code`.
    """
    return endpoint + ("&" if "?" in endpoint else "?") + query


def start() -> int:
    config = read_json(CLIENT_FILE)
    meta = metadata()
    if not config.get("client_id"):
        client = register_client(meta)
        config = {"client_id": client["client_id"], "redirect_uri": REDIRECT,
                  "token_endpoint": meta["token_endpoint"], "authorize_endpoint": meta["authorization_endpoint"]}
        write_private(CLIENT_FILE, config)
        print(f"Registrerede klient {config['client_id'][:12]}… (offentlig klient, intet secret)")
    verifier = b64url(secrets.token_bytes(48))
    state = b64url(secrets.token_bytes(16))
    config.update({"code_verifier": verifier, "state": state, "started_at": int(time.time())})
    write_private(CLIENT_FILE, config)
    challenge = b64url(hashlib.sha256(verifier.encode()).digest())
    query = "&".join([
        "response_type=code",
        f"client_id={config['client_id']}",
        f"redirect_uri={urllib.parse.quote(REDIRECT, safe='')}",
        f"scope={SCOPE}",
        f"state={state}",
        f"code_challenge={challenge}",
        "code_challenge_method=S256",
        f"resource={urllib.parse.quote(RESOURCE, safe='')}",
    ])
    print("\nÅbn linket i browseren, log ind som administrator og godkend:")
    print(f"\n{authorize_url(config['authorize_endpoint'], query)}\n")
    print("Du lander på 127.0.0.1:8765, som ikke svarer — det er med vilje.")
    print("Kopier hele adressen fra browserens adresselinje og kør:")
    print("  python3 tools/mcp_oauth.py --finish '<kode>'")
    return 0


def extract_code(value: str) -> str:
    value = value.strip()
    if "code=" in value:
        query = value.split("?", 1)[-1]
        for part in query.split("&"):
            if part.startswith("code="):
                return urllib.parse.unquote(part[5:])
    return value


def finish(raw_code: str) -> int:
    config = read_json(CLIENT_FILE)
    if not config.get("client_id") or not config.get("code_verifier"):
        print("Ingen igangværende godkendelse — kør --start først.")
        return 1
    code = extract_code(raw_code)
    payload = {
        "grant_type": "authorization_code",
        "code": code,
        "redirect_uri": config["redirect_uri"],
        "client_id": config["client_id"],
        "code_verifier": config["code_verifier"],
    }
    status, body = request(config["token_endpoint"], payload)
    if status != 200 or not isinstance(body, dict) or not body.get("access_token"):
        print(f"Token-udveksling afvist (HTTP {status}): {str(body)[:200]}")
        return 1
    save_tokens(body)
    config.pop("code_verifier", None)
    config.pop("state", None)
    write_private(CLIENT_FILE, config)
    print(f"Har nu adgang: token gyldigt i {body.get('expires_in', '?')} s, refresh-token gemt, mode 600.")
    return 0


def save_tokens(body: dict) -> None:
    expires_in = body.get("expires_in")
    write_private(TOKEN_FILE, {
        "access_token": body["access_token"],
        "refresh_token": body.get("refresh_token"),
        "expires_at": int(time.time()) + int(expires_in) - 60 if expires_in else None,
        "scope": body.get("scope"),
        "token_type": body.get("token_type", "Bearer"),
    })


def refresh() -> str | None:
    config, tokens = read_json(CLIENT_FILE), read_json(TOKEN_FILE)
    if not tokens.get("refresh_token") or not config.get("client_id"):
        return None
    payload = {"grant_type": "refresh_token", "refresh_token": tokens["refresh_token"],
               "client_id": config["client_id"]}
    status, body = request(config.get("token_endpoint") or f"{API}/oauth/token", payload)
    if status != 200 or not isinstance(body, dict) or not body.get("access_token"):
        return None
    save_tokens(body)
    return body["access_token"]


def access_token(force_refresh: bool = False) -> str | None:
    """Gyldigt access token, fornyet automatisk når det udløber om lidt."""
    tokens = read_json(TOKEN_FILE)
    token = tokens.get("access_token")
    expires_at = tokens.get("expires_at")
    if force_refresh or (token and expires_at and time.time() > expires_at):
        return refresh()
    return token


def status() -> int:
    config, tokens = read_json(CLIENT_FILE), read_json(TOKEN_FILE)
    if not config.get("client_id"):
        print("Ingen klient registreret endnu — kør --start.")
        return 1
    if not tokens.get("access_token"):
        print("Klient registreret, men intet token — kør --start og derefter --finish.")
        return 1
    expires_at = tokens.get("expires_at")
    left = f"{int(expires_at - time.time())} s" if expires_at else "ukendt"
    print(json.dumps({
        "klient": config["client_id"][:12] + "…",
        "token": "gyldigt" if not expires_at or expires_at > time.time() else "udløbet (fornyes ved brug)",
        "udløber_om": left,
        "refresh_token": bool(tokens.get("refresh_token")),
        "scope": tokens.get("scope"),
    }, ensure_ascii=False, indent=1))
    return 0


def main() -> int:
    args = sys.argv[1:]
    if args[:1] == ["--start"]:
        return start()
    if args[:1] == ["--finish"]:
        if len(args) < 2:
            print("brug: --finish '<kode eller hele redirect-adressen>'")
            return 1
        return finish(args[1])
    if args[:1] == ["--status"]:
        return status()
    if args[:1] == ["--refresh"]:
        token = refresh()
        print("fornyet" if token else "kunne ikke forny — kør --start igen")
        return 0 if token else 1
    print(__doc__)
    return 1


if __name__ == "__main__":
    raise SystemExit(main())
