#!/usr/bin/env python3
"""pinterest_connect.py — forbind Pinterest-kanalen med en rigtig OAuth-kode.

Kanalen kræver `boards:read` på det token der ligger på sitet. Et token med det scope får man kun
ved at godkende appen og veksle den kode Pinterest sender tilbage. Vekslingen kræver appens
`client_id` **og** `client_secret`; hemmeligheden ligger på sitet, id'et er offentligt og skal stå i
feltet `pinterest_client_id` (Settings → Keys).

Værktøjet gør alt server-side, så hverken kode eller token forlader WordPress:

    python3 tools/pinterest_connect.py --status
    python3 tools/pinterest_connect.py --authorize        # autorisations-URL (kræver id'et)
    python3 tools/pinterest_connect.py --finish '<callback-URL eller kode>'
    python3 tools/pinterest_connect.py --verify           # /v5/boards med det gemte token
    python3 tools/pinterest_connect.py --selftest         # offline, ingen netværk

Der printes aldrig en hemmelighed: kun længder, scope og Pinterests egne svarlynjer.
"""

from __future__ import annotations

import json
import os
import re
import secrets
import subprocess
import sys
import time
import urllib.parse
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
SCOPE = "boards:read,pins:read,user_accounts:read"
DEFAULT_REDIRECT = "http://127.0.0.1:8765/callback"
AUTH_URL = "https://www.pinterest.com/oauth/"
TOKEN_URL = "https://api.pinterest.com/v5/oauth/token"
BOARDS_URL = "https://api.pinterest.com/v5/boards"
STATE_OPTION = "sgap_pinterest_connect_state"
MCP_TOOL = "novamira_run-wp-cli"


def wp(php: str, retries: int = 2) -> str:
    """Kør PHP på sitet over MCP og returnér stdout. Tomt svar gentages (kendt flakiness)."""
    payload = json.dumps({"args": ["eval", php]})
    last = ""
    for attempt in range(retries + 1):
        done = subprocess.run(
            [sys.executable, str(ROOT / "tools/mcp_rest.py"), "--call", MCP_TOOL, payload],
            capture_output=True, text=True,
        )
        last = done.stdout
        try:
            body = json.loads(last)
            data = body.get("data") or {}
            if isinstance(data, str):
                data = json.loads(data)
            out = (data.get("stdout") or "").strip()
            if out:
                return out
        except (ValueError, AttributeError):
            pass
        if attempt < retries:
            time.sleep(1.5 * (attempt + 1))
    raise SystemExit(f"Sitet svarede ikke på kaldet: {last.strip()[:300]}")


# ---------------------------------------------------------------- PHP-skabeloner

STATUS_PHP = (
    "$id=SGAP_Pinterest::client_id();$sec=SGAP_Pinterest::client_secret();"
    "$acc=SGAP_Pinterest::saved_token();$ref=SGAP_Pinterest::refresh_token();"
    "$sc=get_option('sgap_pinterest_scope',[]);$rf=get_option('sgap_pinterest_refused',[]);"
    "echo json_encode(['id_len'=>strlen($id),'secret_len'=>strlen($sec),"
    "'access_len'=>strlen($acc),'refresh_len'=>strlen($ref),"
    "'auto_refresh'=>($id!==''&&$sec!==''&&$ref!==''),"
    "'scope_path'=>is_array($sc)?($sc['path']??''):'','scope_hit'=>is_array($sc)?($sc['scope']??''):'',"
    "'scope_at'=>is_array($sc)?($sc['at']??''):'','refused_at'=>is_array($rf)?($rf['at']??''):'',"
    "'token_source'=>SGAP_Pinterest::token_source()], JSON_UNESCAPED_SLASHES);"
)

EXCHANGE_PHP = (
    "$id=SGAP_Pinterest::client_id();$sec=SGAP_Pinterest::client_secret();"
    "if($id===''||$sec===''){echo json_encode(['ok'=>false,'why'=>'no_credentials']);return;}"
    "$r=wp_remote_post('" + TOKEN_URL + "',['timeout'=>30,"
    "'headers'=>['Authorization'=>'Basic '.base64_encode($id.':'.$sec),"
    "'Content-Type'=>'application/x-www-form-urlencoded'],"
    "'body'=>['grant_type'=>'authorization_code','code'=>'%CODE%',"
    "'redirect_uri'=>'%REDIRECT%','continuous_refresh'=>'true']]);"
    "$s=wp_remote_retrieve_response_code($r);$raw=wp_remote_retrieve_body($r);$b=json_decode($raw,true);"
    "if($s!==200||!is_array($b)||empty($b['access_token'])){"
    "echo json_encode(['ok'=>false,'why'=>'refused','status'=>$s,"
    "'error'=>is_array($b)?($b['error']??''):'','message'=>is_array($b)?substr((string)($b['error_description']??$b['message']??''),0,200):substr($raw,0,200)],"
    "JSON_UNESCAPED_SLASHES);return;}"
    "SGAP_Settings::set('pinterest_access_token',$b['access_token']);"
    "if(!empty($b['refresh_token'])){SGAP_Settings::set('pinterest_refresh_token',$b['refresh_token']);}"
    "if(!empty($b['expires_in'])){update_option('sgap_pinterest_access_expires',time()+(int)$b['expires_in'],false);}"
    "if(!empty($b['refresh_token_expires_in'])){update_option('sgap_pinterest_refresh_expires',time()+(int)$b['refresh_token_expires_in'],false);}"
    "delete_option('sgap_pinterest_refused');delete_option('sgap_pinterest_scope');"
    "delete_option('" + STATE_OPTION + "');"
    "echo json_encode(['ok'=>true,'access_len'=>strlen($b['access_token']),"
    "'refresh'=>empty($b['refresh_token'])?false:true,'scope'=>(string)($b['scope']??''),"
    "'expires_in'=>(int)($b['expires_in']??0),"
    "'refresh_expires_in'=>(int)($b['refresh_token_expires_in']??0)], JSON_UNESCAPED_SLASHES);"
)

VERIFY_PHP = (
    "$src=SGAP_Pinterest::token_source();$tok=SGAP_Pinterest::token();"
    "if($tok===''){echo json_encode(['ok'=>false,'why'=>'no_token']);return;}"
    "$r=wp_remote_get('" + BOARDS_URL + "?page_size=25',['timeout'=>30,"
    "'headers'=>['Authorization'=>'Bearer '.$tok]]);"
    "$s=wp_remote_retrieve_response_code($r);$b=json_decode(wp_remote_retrieve_body($r),true);"
    "$names=[];foreach((array)($b['items']??[]) as $i){$names[]=(string)($i['name']??'');}"
    "$msg='';if($s!==200){$msg=is_array($b)?(string)($b['message']??''):'';}"
    "echo json_encode(['ok'=>$s===200,'status'=>$s,'boards'=>count($names),"
    "'first'=>array_slice($names,0,5),'message'=>substr($msg,0,200),'token_source'=>$src],"
    "JSON_UNESCAPED_SLASHES);"
)

STATE_WRITE_PHP = (
    "update_option('" + STATE_OPTION + "',['state'=>'%STATE%','at'=>time()],false);"
    "echo json_encode(['ok'=>true]);"
)

AUTHORIZE_PHP = (
    "$id=SGAP_Pinterest::client_id();echo json_encode(['id'=>$id], JSON_UNESCAPED_SLASHES);"
)

STATE_READ_PHP = (
    "$s=get_option('" + STATE_OPTION + "',[]);"
    "echo json_encode(['state'=>is_array($s)?($s['state']??''):'','at'=>is_array($s)?(int)($s['at']??0):0]);"
)


# ---------------------------------------------------------------- logik

def php_str(value: str) -> str:
    """Sikker PHP-enkeltstreng uden at værdien kan bryde ud af citationstegnene."""
    return value.replace("\\", "\\\\").replace("'", "\\'")


def extract(value: str) -> dict:
    """Træk code, state og redirect_uri ud af en callback-URL — eller tag koden som den er."""
    value = (value or "").strip().strip("'\"")
    if "code=" not in value:
        return {"code": value, "state": "", "redirect": ""}
    query = value.split("?", 1)[-1].split("#", 1)[0]
    found = {}
    for part in query.split("&"):
        if "=" in part:
            key, val = part.split("=", 1)
            found[key.lower()] = urllib.parse.unquote(val)
    return {
        "code": found.get("code", ""),
        "state": found.get("state", ""),
        "redirect": value.split("?", 1)[0] if value.startswith("http") else "",
    }


def authorize_url(client_id: str, redirect: str, state: str) -> str:
    query = "&".join([
        "response_type=code",
        "client_id=" + urllib.parse.quote(client_id, safe=""),
        "redirect_uri=" + urllib.parse.quote(redirect, safe=""),
        "scope=" + urllib.parse.quote(SCOPE, safe=",:,"),
        "state=" + urllib.parse.quote(state, safe=""),
    ])
    return AUTH_URL + "?" + query


def status() -> int:
    print(json.dumps(json.loads(wp(STATUS_PHP)), ensure_ascii=False, indent=1))
    return 0


def authorize(redirect: str) -> int:
    state = secrets.token_urlsafe(16)
    info = json.loads(wp(STATUS_PHP))
    if not info.get("id_len"):
        print(json.dumps({
            "ok": False,
            "why": "client_id_missing",
            "message": ("Appens client id mangler: feltet pinterest_client_id er tomt. Indsæt appens "
                        "App ID (offentligt, står i autorisationslinket og på developers.pinterest.com → "
                        "My apps) under Settings → Keys, og kør kommandoen igen. Secret'en ("
                        f"{info.get('secret_len', 0)} tegn) ligger allerede på sitet."),
            "redirect": redirect,
            "scope": SCOPE,
        }, ensure_ascii=False, indent=1))
        return 2
    client_id = json.loads(wp(AUTHORIZE_PHP)).get("id", "")
    if not client_id:
        print(json.dumps({"ok": False, "why": "client_id_missing"}, ensure_ascii=False))
        return 2
    wp(STATE_WRITE_PHP.replace("%STATE%", php_str(state)))
    url = authorize_url(client_id=client_id, redirect=redirect, state=state)
    print(url)
    print("\nAabn linket, godkend board-adgang, og koer derefter:")
    print("  python3 tools/pinterest_connect.py --finish '<hele callback-adressen>'")
    return 0


def finish(value: str, redirect: str) -> int:
    parts = extract(value)
    if not parts["code"]:
        print(json.dumps({"ok": False, "why": "no_code"}, ensure_ascii=False))
        return 2
    saved = json.loads(wp(STATE_READ_PHP))
    if saved.get("state") and parts["state"] and saved["state"] != parts["state"]:
        print(json.dumps({"ok": False, "why": "state_mismatch",
                          "message": "Callbackens state matcher ikke den vi sendte — kør --authorize igen."},
                         ensure_ascii=False, indent=1))
        return 2
    target_redirect = parts["redirect"] or redirect
    php = (EXCHANGE_PHP
           .replace("%CODE%", php_str(parts["code"]))
           .replace("%REDIRECT%", php_str(target_redirect)))
    result = json.loads(wp(php))
    if not result.get("ok"):
        if result.get("why") == "refused":
            line = (result.get("error") or "") + " " + (result.get("message") or "")
            if "invalid_grant" in line or "invalid code" in line.lower():
                result["hint"] = "Koden er udloebet eller allerede brugt — koer --authorize for en frisk."
            elif "invalid_client" in line:
                result["hint"] = "Id og secret passer ikke sammen — tjek pinterest_client_id."
            elif "boards:read" not in line and "scope" in line.lower():
                result["hint"] = "Appen fik ikke board-scope med — godkend board-adgang i stedet for kun pins."
        print(json.dumps(result, ensure_ascii=False, indent=1))
        return 1
    print(json.dumps(result, ensure_ascii=False, indent=1))
    check = json.loads(wp(VERIFY_PHP))
    print(json.dumps(check, ensure_ascii=False, indent=1))
    if check.get("token_source") == "woocommerce":
        print("Bemaerk: kanalen bruger stadig Pinterest for WooCommerce-integrationens token "
              "(token_source=woocommerce). Det nye token ligger klar i feltet, men bruges foerst naar "
              "integrationen er afbrudt — eller naar dens eget token har boards:read.")
    return 0 if check.get("ok") else 1


def verify() -> int:
    result = json.loads(wp(VERIFY_PHP))
    print(json.dumps(result, ensure_ascii=False, indent=1))
    return 0 if result.get("ok") else 1


# ---------------------------------------------------------------- selftest

def selftest() -> int:
    failures: list[str] = []
    checks = 0

    def check(name: str, condition: bool, detail: str = "") -> None:
        nonlocal checks
        checks += 1
        if not condition:
            failures.append(f"{name}{(': ' + detail) if detail else ''}")

    url = authorize_url("1234567", DEFAULT_REDIRECT, "abc_DEF-1")
    check("authorize: response_type", "response_type=code" in url)
    check("authorize: client id", "client_id=1234567" in url)
    check("authorize: redirect er encodet",
          "redirect_uri=http%3A%2F%2F127.0.0.1%3A8765%2Fcallback" in url, url)
    check("authorize: boards:read med", "boards%3Aread" in url or "boards:read" in url, url)
    check("authorize: state med", "state=abc_DEF-1" in url)

    parsed = extract("http://127.0.0.1:8765/callback?code=def50200aa&state=xyz")
    check("extract: kode", parsed["code"] == "def50200aa", str(parsed))
    check("extract: state", parsed["state"] == "xyz")
    check("extract: redirect", parsed["redirect"] == "http://127.0.0.1:8765/callback")
    check("extract: raa kode", extract("  abc123  ")["code"] == "abc123")
    check("extract: url-encodet kode",
          extract("http://x/cb?code=a%2Bb")["code"] == "a+b")

    check("php_str: apostrof escapes", php_str("a'b") == "a\\'b")
    check("php_str: backslash escapes", php_str("a\\b") == "a\\\\b")
    check("php_str: ingen raa apostrof tilbage",
          "'" not in php_str("x'y").replace("\\'", ""))

    check("skabelon: exchange har code-pladsholder", "%CODE%" in EXCHANGE_PHP)
    check("skabelon: token-endpoint", TOKEN_URL in EXCHANGE_PHP)
    check("skabelon: basic auth", "base64_encode" in EXCHANGE_PHP)
    check("skabelon: continuous refresh", "continuous_refresh" in EXCHANGE_PHP)
    check("skabelon: rydder scope-afslag", "delete_option('sgap_pinterest_scope')" in EXCHANGE_PHP)
    check("skabelon: gemmer refresh", "pinterest_refresh_token" in EXCHANGE_PHP)
    check("skabelon: boards-kald", BOARDS_URL in VERIFY_PHP)
    check("skabelon: verify bruger plugin'ets token()", "SGAP_Pinterest::token()" in VERIFY_PHP)
    check("skabelon: verify melder token-kilde", "token_source" in VERIFY_PHP)

    if failures:
        for item in failures:
            print("FAIL", item)
        return 1
    print(f"pinterest_connect: {checks} checks OK")
    return 0


def main() -> int:
    args = sys.argv[1:]
    redirect = os.environ.get("PINTEREST_REDIRECT") or DEFAULT_REDIRECT
    if "--redirect" in args:
        redirect = args[args.index("--redirect") + 1]
    if not args or args[0] == "--status":
        return status()
    if args[0] == "--authorize":
        return authorize(redirect)
    if args[0] == "--finish":
        if len(args) < 2:
            print("brug: --finish '<callback-URL eller kode>'")
            return 2
        return finish(args[1], redirect)
    if args[0] == "--verify":
        return verify()
    if args[0] == "--selftest":
        return selftest()
    print(__doc__)
    return 2


if __name__ == "__main__":
    raise SystemExit(main())
