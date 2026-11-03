#!/usr/bin/env python3
"""
Warpstorm deploy runner.

Two APIs, on purpose:

  REST (WordPress core)  — installs and activates plugins, creates pages.
                           POST /wp-json/wp/v2/plugins is core since WP 5.5 and
                           needs nothing but an administrator + application password.
  MCP  (Royal MCP)       — JSON-RPC over /wp-json/royal-mcp/v1/mcp, used for the
                           things REST cannot do: menus, options, WooCommerce
                           products, site status.

Royal MCP cannot install plugins ("no plugin installs or upgrades"), so the plugin
step must go through REST. Everything else prefers MCP where a tool exists.

Credentials come from the environment only — never from this file:
    WP_SITE          https://warpstorm.online
    WP_USER          deploy-bot            (dedicated admin user, not the owner)
    WP_APP_PASSWORD  xxxx xxxx xxxx ...    (application password, spaces allowed)

Usage:
    python3 deploy.py tools                       # what the MCP server exposes
    python3 deploy.py plugins                     # what is installed right now
    python3 deploy.py run                         # dry run: print every call
    python3 deploy.py run --apply                 # execute
    python3 deploy.py run --apply --only page-shop
"""

from __future__ import annotations

import argparse
import base64
import json
import mimetypes
import os
import sys
import urllib.error
import urllib.request
import uuid

MANIFEST = os.path.join(os.path.dirname(os.path.abspath(__file__)), "..", "..",
                        "deploy", "warpstorm", "manifest.json")


# the manifest actually loaded, so content_file resolves next to it and not
# next to whatever the default constant points at
_MANIFEST_PATH = None


def resolve_manifest(path=None) -> str:
    """--manifest wins; a bare name is looked up under deploy/<site>/."""
    if not path:
        return os.path.normpath(MANIFEST)
    if os.path.exists(path):
        return os.path.normpath(path)
    deploy_dir = os.path.dirname(os.path.dirname(os.path.normpath(MANIFEST)))
    for folder in sorted(os.listdir(deploy_dir)):
        candidate = os.path.join(deploy_dir, folder, path)
        if os.path.isfile(candidate):
            return os.path.normpath(candidate)
    return os.path.normpath(path)

OK = "ok"
SKIP = "skip"
FAIL = "fail"


# --------------------------------------------------------------------------
# transport
# --------------------------------------------------------------------------

class Credentials:
    def __init__(self, offline: bool = False) -> None:
        self.site = os.environ.get("WP_SITE", "").rstrip("/")
        self.user = os.environ.get("WP_USER", "")
        self.password = os.environ.get("WP_APP_PASSWORD", "")
        self.token = os.environ.get("WP_TOKEN", "")
        # Royal MCP keys are their own thing: they open the MCP endpoint, not
        # /wp/v2. Keeping them apart is what makes "deploy.py tools" work with
        # only the MCP key set, and stops a REST call from being attempted with
        # a bearer token that cannot authenticate there.
        self.mcp_token = os.environ.get("ROYAL_MCP_TOKEN", "")

        if offline:
            self.site = self.site or "https://warpstorm.online"
            return
        if not self.site:
            die("WP_SITE is not set (e.g. https://steamgears.eu)")
        if not self.token and not self.mcp_token and not (self.user and self.password):
            die("Set ROYAL_MCP_TOKEN for MCP, and WP_USER + WP_APP_PASSWORD (or WP_TOKEN) for REST.")

    def header(self) -> str:
        """WordPress REST: application password or token."""
        if self.token:
            return "Bearer " + self.token
        raw = f"{self.user}:{self.password}".encode("utf-8")
        return "Basic " + base64.b64encode(raw).decode("ascii")

    def mcp_header(self) -> str:
        """Royal MCP endpoint: its own key, or fall back to the REST token."""
        if self.mcp_token or self.token:
            return "Bearer " + (self.mcp_token or self.token)
        return self.header()


def die(msg: str) -> None:
    print("error: " + msg, file=sys.stderr)
    raise SystemExit(2)


def post_file(creds: Credentials, url: str, file_path: str, fields=None, timeout=180):
    """multipart/form-data upload — /wp/v2/media wants bytes, not JSON."""
    boundary = "----deploy" + uuid.uuid4().hex
    filename = os.path.basename(file_path)
    ctype = mimetypes.guess_type(filename)[0] or "application/octet-stream"
    with open(file_path, "rb") as fh:
        payload = fh.read()

    chunks = []
    for name, value in (fields or {}).items():
        chunks.append(f'--{boundary}\r\nContent-Disposition: form-data; name="{name}"\r\n\r\n'
                      .encode("utf-8") + str(value).encode("utf-8") + b"\r\n")
    chunks.append((f'--{boundary}\r\nContent-Disposition: form-data; name="file"; '
                   f'filename="{filename}"\r\nContent-Type: {ctype}\r\n\r\n').encode("utf-8"))
    chunks.append(payload)
    chunks.append(b"\r\n")
    chunks.append(f"--{boundary}--\r\n".encode("utf-8"))

    headers = {
        "Authorization": creds.header(),
        "Accept": "application/json",
        "Content-Type": f"multipart/form-data; boundary={boundary}",
        "Content-Disposition": f'attachment; filename="{filename}"',
        "User-Agent": "warpstorm-deploy/1.0",
    }
    req = urllib.request.Request(url, data=b"".join(chunks), headers=headers, method="POST")
    try:
        with urllib.request.urlopen(req, timeout=timeout) as res:
            raw = res.read().decode("utf-8", "replace")
            return res.status, (json.loads(raw) if raw.strip() else None)
    except urllib.error.HTTPError as e:
        raw = e.read().decode("utf-8", "replace")
        try:
            return e.code, json.loads(raw)
        except Exception:
            return e.code, {"raw": raw[:400]}
    except urllib.error.URLError as e:
        return 0, {"error": str(e)}


def parse_body(raw: str, content_type: str = ""):
    """JSON, or the last SSE `data:` frame — streamable HTTP servers use both."""
    raw = raw.strip()
    if not raw:
        return None
    if "event-stream" in content_type or raw.startswith("event:") or raw.startswith("data:"):
        payload = None
        for line in raw.splitlines():
            if line.startswith("data:"):
                chunk = line[5:].strip()
                if chunk:
                    try:
                        payload = json.loads(chunk)
                    except Exception:
                        continue
        return payload
    return json.loads(raw)


def request(creds: Credentials, method: str, url: str, body=None, timeout=60, auth=None):
    data = None
    headers = {
        "Authorization": auth or creds.header(),
        "Accept": "application/json, text/event-stream",
        "User-Agent": "warpstorm-deploy/1.0",
    }
    if body is not None:
        data = json.dumps(body).encode("utf-8")
        headers["Content-Type"] = "application/json"

    req = urllib.request.Request(url, data=data, headers=headers, method=method)
    try:
        with urllib.request.urlopen(req, timeout=timeout) as res:
            raw = res.read().decode("utf-8", "replace")
            return res.status, parse_body(raw, res.headers.get("Content-Type", ""))
    except urllib.error.HTTPError as e:
        raw = e.read().decode("utf-8", "replace")
        try:
            payload = json.loads(raw)
        except Exception:
            payload = {"raw": raw[:800]}
        return e.code, payload
    except urllib.error.URLError as e:
        return 0, {"error": str(e)}


# --------------------------------------------------------------------------
# MCP (JSON-RPC 2.0 over HTTPS)
# --------------------------------------------------------------------------

class Mcp:
    """Minimal MCP client: initialize, tools/list, tools/call."""

    def __init__(self, creds: Credentials) -> None:
        self.creds = creds
        self.url = creds.site + "/wp-json/royal-mcp/v1/mcp"
        self._id = 0
        self._initialized = False

    def _rpc(self, method: str, params=None):
        self._id += 1
        payload = {"jsonrpc": "2.0", "id": self._id, "method": method}
        if params is not None:
            payload["params"] = params
        status, body = request(self.creds, "POST", self.url, payload, timeout=90,
                               auth=self.creds.mcp_header())
        if status == 0:
            return None, (body or {}).get("error", "transport error")
        if status == 401:
            return None, {"unauthorized": "ROYAL_MCP_TOKEN was rejected (401). "
                                         "Create a key in Royal MCP settings and set the secret."}
        if status >= 400:
            return None, body
        if isinstance(body, dict) and body.get("error"):
            return None, body["error"]
        return (body or {}).get("result"), None

    def initialize(self):
        """Handshake once. Streamable HTTP servers may insist on it."""
        if self._initialized:
            return None
        result, err = self._rpc("initialize", {
            "protocolVersion": "2025-06-18",
            "capabilities": {},
            "clientInfo": {"name": "steamgears-deploy", "version": "1.0"},
        })
        self._initialized = True
        return err

    def tools(self):
        err = self.initialize()
        if err:
            return None, err
        result, err = self._rpc("tools/list")
        if err:
            return None, err
        return (result or {}).get("tools", []), None

    def call(self, name: str, args=None):
        err = self.initialize()
        if err:
            return None, err
        result, err = self._rpc("tools/call", {"name": name, "arguments": args or {}})
        if err:
            return None, err
        # MCP returns {content:[{type:"text",text:"..."}], isError?}
        if isinstance(result, dict) and result.get("isError"):
            return None, result
        return result, None


def mcp_text(result) -> str:
    if not isinstance(result, dict):
        return json.dumps(result)[:400]
    parts = []
    for block in result.get("content", []):
        if isinstance(block, dict) and block.get("type") == "text":
            parts.append(block.get("text", ""))
    return "\n".join(parts)[:600] or json.dumps(result)[:400]


# --------------------------------------------------------------------------
# steps
# --------------------------------------------------------------------------

def rest_plugins(creds: Credentials):
    return request(creds, "GET", creds.site + "/wp-json/wp/v2/plugins")


def step_plugin(creds: Credentials, step: dict, apply: bool, offline: bool = False) -> str:
    slug = step["slug"]
    status = step.get("status", "active")

    if offline:
        print(f"  would ensure {slug} is {status} (POST /wp-json/wp/v2/plugins)")
        return OK

    code, listing = rest_plugins(creds)
    if code != 200 or not isinstance(listing, list):
        print(f"  cannot list plugins ({code}): {str(listing)[:200]}")
        return FAIL

    installed = {p.get("plugin", "").split("/")[0]: p for p in listing}
    current = installed.get(slug)

    if current:
        if current.get("status") == status:
            return SKIP
        print(f"  installed, status={current.get('status')} -> {status}")
        if not apply:
            return OK
        code, body = request(creds, "PUT",
                             creds.site + f"/wp-json/wp/v2/plugins/{current['plugin']}",
                             {"status": status})
        if code != 200:
            print(f"  activate failed {code}: {str(body)[:300]}")
            return FAIL
        return OK

    print(f"  not installed -> POST /wp-json/wp/v2/plugins {{slug:{slug}, status:{status}}}")
    if not apply:
        return OK
    code, body = request(creds, "POST", creds.site + "/wp-json/wp/v2/plugins",
                         {"slug": slug, "status": status})
    if code not in (200, 201):
        print(f"  install failed {code}: {str(body)[:300]}")
        return FAIL
    return OK


def page_content(step: dict) -> str:
    """Inline content, or a payload file built by build_payloads.py."""
    if step.get("content"):
        return step["content"]
    ref = step.get("content_file")
    if not ref:
        die(f"page step {step.get('id')} has neither content nor content_file")
    base = os.path.dirname(os.path.normpath(_MANIFEST_PATH or MANIFEST))
    path = os.path.join(base, ref)
    with open(path, encoding="utf-8") as fh:
        return json.load(fh)["content"]


def step_page(creds: Credentials, step: dict, apply: bool, update: bool,
              offline: bool = False) -> str:
    slug = step["slug"]
    if offline:
        print(f"  would create /{slug}/ as {step.get('status', 'draft')} "
              f"({len(page_content(step)) // 1024} KB)")
        return OK

    code, found = request(creds, "GET",
                          creds.site + f"/wp-json/wp/v2/pages?slug={slug}&status=any")
    existing = found[0] if isinstance(found, list) and found else None

    if existing and not update:
        print(f"  exists (id {existing['id']}) — pass --update to overwrite")
        return SKIP

    body = {
        "title": step["title"],
        "slug": slug,
        "status": step.get("status", "draft"),
        "content": page_content(step),
    }
    if existing:
        print(f"  updating page {existing['id']}")
        if not apply:
            return OK
        code, res = request(creds, "POST", creds.site + f"/wp-json/wp/v2/pages/{existing['id']}", body)
    else:
        print(f"  creating page /{slug}/ as {body['status']}")
        if not apply:
            return OK
        code, res = request(creds, "POST", creds.site + "/wp-json/wp/v2/pages", body)

    if code not in (200, 201):
        print(f"  page failed {code}: {str(res)[:300]}")
        return FAIL
    return OK


def step_mcp(creds: Credentials, step: dict, apply: bool, mcp: Mcp, tools: set) -> str:
    tool = step["tool"]
    aliases = step.get("tool_aliases") or []
    if tools and tool not in tools:
        # Royal MCP renames tools between versions; the manifest lists the
        # spellings seen in the wild and the first one that exists wins.
        for alias in aliases:
            if alias in tools:
                print(f"  '{tool}' is not exposed; using '{alias}'")
                tool = alias
                break
    if tools and tool not in tools:
        tried = ", ".join([step["tool"]] + aliases)
        print(f"  none of [{tried}] is exposed by this MCP server")
        return SKIP if step.get("optional") else FAIL
    print(f"  mcp call {tool} {json.dumps(step.get('args', {}))[:180]}")
    if not apply:
        return OK
    result, err = mcp.call(tool, step.get("args", {}))
    if err:
        print(f"  mcp failed: {str(err)[:300]}")
        return FAIL
    print(f"  -> {mcp_text(result)[:200]}")
    return OK


def step_media(creds: Credentials, step: dict, apply: bool, ids: dict, capture: str,
               root: str, offline: bool = False) -> str:
    """Upload a mockup to the media library once; remember the attachment id."""
    rel = step["file"]
    path = os.path.join(root, rel)
    if not os.path.exists(path):
        print(f"  {rel} is not here yet — drop the file in and re-run")
        return SKIP
    if rel in ids:
        print(f"  already uploaded (attachment {ids[rel]})")
        return SKIP
    if offline:
        print(f"  would upload {rel} ({os.path.getsize(path) // 1024} KB)")
        return OK

    print(f"  uploading {rel} ({os.path.getsize(path) // 1024} KB)")
    if not apply:
        return OK
    code, body = post_file(creds, creds.site + "/wp-json/wp/v2/media", path,
                           {"alt_text": step.get("alt", ""),
                            "title": step.get("title_text", ""),
                            "post": step.get("post", "")})
    if code not in (200, 201) or not isinstance(body, dict) or "id" not in body:
        print(f"  upload failed {code}: {str(body)[:300]}")
        return FAIL
    ids[rel] = body["id"]
    if capture:
        capture_path = os.path.join(root, capture)
        os.makedirs(os.path.dirname(capture_path), exist_ok=True)
        with open(capture_path, "w", encoding="utf-8") as fh:
            json.dump(ids, fh, ensure_ascii=False, indent=2, sort_keys=True)
            fh.write("\n")
    print(f"  -> attachment {body['id']}  {body.get('source_url', '')}")
    return OK


def step_curl_note(step: dict) -> str:
    print("  manual step: " + step.get("why", "no automated path"))
    return SKIP


# --------------------------------------------------------------------------
# commands
# --------------------------------------------------------------------------

def load_manifest(path=None) -> dict:
    global _MANIFEST_PATH
    path = resolve_manifest(path)
    if not os.path.exists(path):
        die(f"manifest not found: {path}")
    _MANIFEST_PATH = path
    with open(path, encoding="utf-8") as fh:
        return json.load(fh)


def cmd_tools(creds: Credentials) -> None:
    mcp = Mcp(creds)
    tools, err = mcp.tools()
    if err:
        if isinstance(err, dict) and "unauthorized" in err:
            die(err["unauthorized"])
        die(f"MCP tools/list failed: {str(err)[:300]}")
    for t in tools:
        print(t.get("name"), "-", (t.get("description") or "")[:90])


def cmd_plugins(creds: Credentials) -> None:
    code, listing = rest_plugins(creds)
    if code != 200:
        die(f"plugin list failed ({code}): {str(listing)[:300]}")
    for p in listing:
        print(f"{p.get('status','?'):10} {p.get('plugin')}")


def cmd_run(creds: Credentials, apply: bool, only, update: bool, offline: bool = False,
            manifest_path=None, force: bool = False) -> None:
    manifest = load_manifest(manifest_path)

    declared = (manifest.get("site") or "").rstrip("/")
    if declared and not offline and declared != creds.site:
        print(f"note: manifest was written for {declared}, running against {creds.site}")
        print("      fine on a test deploy — relative paths only, nothing domain-bound in the payloads\n")

    root = os.path.dirname(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
    capture = manifest.get("media_ids") or ""
    media_ids = {}
    if capture:
        capture_path = os.path.join(root, capture)
        if os.path.exists(capture_path):
            with open(capture_path, encoding="utf-8") as fh:
                media_ids = json.load(fh)

    mcp = Mcp(creds)
    tools = set()
    if not offline and any(s.get("type") == "mcp" for s in manifest.get("steps", [])):
        listed, err = mcp.tools()
        if err:
            print(f"warning: could not list MCP tools ({str(err)[:120]}) — mcp steps will be reported, not verified")
        else:
            tools = {t.get("name") for t in listed}
            print(f"MCP: {len(tools)} tools available\n")

    counts = {OK: 0, SKIP: 0, FAIL: 0}
    for step in manifest.get("steps", []):
        sid = step.get("id", step.get("type", "?"))
        if only and sid not in only:
            continue
        print(f"[{sid}] {step.get('title', step.get('type', ''))}")
        kind = step.get("type")

        if step.get("blocked") and not force:
            # generated steps carry their own reason: a missing price, an
            # unfilled token, a collection id that does not exist yet
            for reason in step["blocked"]:
                print(f"  blocked: {reason}")
            print("  fix the source and regenerate, or pass --force")
            counts[SKIP] += 1
            print(f"  {SKIP}\n")
            continue

        if kind == "media":
            res = step_media(creds, step, apply, media_ids, capture, root, offline)
        elif kind == "plugin":
            res = step_plugin(creds, step, apply, offline)
        elif kind == "page":
            res = step_page(creds, step, apply, update, offline)
        elif kind == "mcp":
            res = step_mcp(creds, step, apply, mcp, tools) if not offline else (
                print(f"  would call {step['tool']}") or OK)
        elif kind == "manual":
            res = step_curl_note(step)
        else:
            print(f"  unknown step type '{kind}'")
            res = FAIL

        counts[res] += 1
        print(f"  {res}\n")

    print(f"done: {counts[OK]} ok, {counts[SKIP]} skipped, {counts[FAIL]} failed")
    if counts[FAIL]:
        raise SystemExit(1)


def main() -> None:
    ap = argparse.ArgumentParser(description="Warpstorm deploy runner")
    sub = ap.add_subparsers(dest="cmd", required=True)

    sub.add_parser("tools", help="list MCP tools")
    sub.add_parser("plugins", help="list installed plugins")

    run = sub.add_parser("run", help="run the manifest")
    run.add_argument("--apply", action="store_true", help="actually write (default is a dry run)")
    run.add_argument("--only", nargs="*", help="only these step ids")
    run.add_argument("--update", action="store_true", help="overwrite existing pages")
    run.add_argument("--offline", action="store_true", help="print the plan without any network call")
    run.add_argument("--manifest", help="manifest path, or a name under deploy/ (e.g. punk-manifest.json)")
    run.add_argument("--force", action="store_true", help="run steps that are marked blocked")

    args = ap.parse_args()
    offline = getattr(args, "offline", False)
    creds = Credentials(offline=offline)

    if args.cmd == "tools":
        cmd_tools(creds)
    elif args.cmd == "plugins":
        cmd_plugins(creds)
    else:
        if not args.apply:
            print("DRY RUN — nothing is written. Add --apply to execute.\n")
        cmd_run(creds, args.apply, args.only, args.update, offline,
                getattr(args, "manifest", None), getattr(args, "force", False))


if __name__ == "__main__":
    main()
