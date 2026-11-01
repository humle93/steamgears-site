import sys
import os
import json
import base64
import urllib.request
import urllib.parse
import subprocess
from http.server import HTTPServer, BaseHTTPRequestHandler

WP_SITE = "https://steamgears.eu"
WP_MCP_URL = f"{WP_SITE}/wp-json/royal-mcp/v1/mcp"
AUTH_FILE = os.path.expanduser("~/.gemini/steamgears_auth.json")
CALLBACK_PORT = 8765

class AuthCallbackHandler(BaseHTTPRequestHandler):
    auth_data = None

    def log_message(self, format, *args):
        pass

    def do_GET(self):
        parsed = urllib.parse.urlparse(self.path)
        params = urllib.parse.parse_qs(parsed.query)

        if "user_login" in params and "password" in params:
            AuthCallbackHandler.auth_data = {
                "user": params["user_login"][0],
                "password": params["password"][0]
            }
            self.send_response(200)
            self.send_header("Content-Type", "text/html; charset=utf-8")
            self.end_headers()
            html = """
            <html><body style="background:#121212;color:#00e676;font-family:sans-serif;text-align:center;padding-top:20%;">
            <h1>&#10004; Steamgears.eu Autoriseret!</h1>
            <p style="color:#ccc;">Nøglen er gemt i Termux. Du kan lukke fanen og gå tilbage til terminalen.</p>
            </body></html>
            """
            self.wfile.write(html.encode("utf-8"))
        else:
            self.send_response(400)
            self.end_headers()
            self.wfile.write(b"Mangler login-parametre eller autorisering blev afvist.")

def authorize_via_browser():
    os.makedirs(os.path.dirname(AUTH_FILE), exist_ok=True)
    callback_url = f"http://127.0.0.1:{CALLBACK_PORT}/callback"
    auth_url = (
        f"{WP_SITE}/wp-admin/authorize-application.php?"
        f"app_name=Gemini_CLI_Termux&"
        f"success_url={urllib.parse.quote(callback_url, safe='')}"
    )

    sys.stderr.write("\n[Steamgears MCP] Åbner browser for autorisering...\n")
    sys.stderr.write(f"Hvis browseren ikke åbner automatisk, åbn dette link:\n{auth_url}\n\n")
    sys.stderr.flush()

    try:
        subprocess.run(["termux-open-url", auth_url], check=False)
    except FileNotFoundError:
        pass

    server = HTTPServer(("127.0.0.1", CALLBACK_PORT), AuthCallbackHandler)
    while AuthCallbackHandler.auth_data is None:
        server.handle_request()
    server.server_close()

    with open(AUTH_FILE, "w") as f:
        json.dump(AuthCallbackHandler.auth_data, f)
    os.chmod(AUTH_FILE, 0o600)
    sys.stderr.write("[Steamgears MCP] Autorisering gennemført og gemt!\n")
    sys.stderr.flush()
    return AuthCallbackHandler.auth_data

def get_auth_header():
    if os.path.exists(AUTH_FILE):
        try:
            with open(AUTH_FILE, "r") as f:
                data = json.load(f)
            if "token" in data and data["token"]:
                return f"Bearer {data['token']}"
            creds = f"{data['user']}:{data['password']}"
            encoded = base64.b64encode(creds.encode("utf-8")).decode("utf-8")
            return f"Basic {encoded}"
        except Exception:
            pass
    data = authorize_via_browser()
    creds = f"{data['user']}:{data['password']}"
    encoded = base64.b64encode(creds.encode("utf-8")).decode("utf-8")
    return f"Basic {encoded}"

if __name__ == "__main__":
    if "--auth" in sys.argv:
        authorize_via_browser()
        sys.exit(0)

    auth_header = get_auth_header()

    for line in sys.stdin:
        line = line.strip()
        if not line:
            continue
        try:
            req = urllib.request.Request(
                WP_MCP_URL,
                data=line.encode("utf-8"),
                headers={
                    "Content-Type": "application/json",
                    "Authorization": auth_header,
                    "User-Agent": "Gemini-CLI-Termux/1.0"
                },
                method="POST"
            )
            with urllib.request.urlopen(req, timeout=30) as response:
                res_body = response.read().decode("utf-8").strip()
                if res_body:
                    sys.stdout.write(res_body + "\n")
                    sys.stdout.flush()
        except Exception as e:
            try:
                msg_id = json.loads(line).get("id")
            except Exception:
                msg_id = None
            err = {
                "jsonrpc": "2.0",
                "id": msg_id,
                "error": {"code": -32000, "message": str(e)}
            }
            sys.stdout.write(json.dumps(err) + "\n")
            sys.stdout.flush()
