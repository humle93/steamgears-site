#!/usr/bin/env python3
"""set_mcp_key.py — læg en ny Royal MCP-nøgle i `.env` uden at den rammer historik eller chat.

To veje, begge uden at nøglen vises:

    python3 tools/set_mcp_key.py                    # spørger i en terminal (getpass, ingen echo)
    python3 tools/set_mcp_key.py --from-file FILE   # læser en fil, fx mcp_key.txt fra editor-panelet

Nøglen læses, den gamle `ROYAL_MCP_TOKEN`/`ROYAL_MCP_API_KEY` fjernes, så broen ikke kan bruge
en forældet linje (`wp_mcp_stdio.read_key` tager den første match i filen), `.env` får mode 600,
og broens eget `--check` køres til sidst. Scriptet printer kun nøglens **længde**, aldrig værdien,
og sletter kildefilen med `--from-file`, så den ikke bliver liggende.
"""

from __future__ import annotations

import getpass
import os
import subprocess
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
# `SGAP_ENV_FILE` og `SGAP_MCP_KEY` findes kun for at kunne afprøve omskrivningen på en
# fil uden for repoet; den normale vej er prompten eller `--from-file`.
ENV = Path(os.environ.get("SGAP_ENV_FILE") or ROOT / ".env")
NAMES = ("ROYAL_MCP_TOKEN", "ROYAL_MCP_API_KEY")


def ask_key() -> str:
    if len(sys.argv) > 2 and sys.argv[1] == "--from-file":
        source = Path(sys.argv[2])
        if not source.exists():
            print(f"Filen {source} findes ikke — .env er uaendret.")
            raise SystemExit(1)
        key = source.read_text(encoding="utf-8", errors="replace").strip()
        source.unlink(missing_ok=True)
        print(f"Laeste {source.name} og slettede den igen.")
        return key
    if os.environ.get("SGAP_MCP_KEY"):
        return os.environ["SGAP_MCP_KEY"]
    try:
        return getpass.getpass("Ny Royal MCP-noegle (vises ikke): ")
    except (EOFError, KeyboardInterrupt):
        print("\nAfbrudt — .env er uaendret.")
        raise SystemExit(1) from None


def main() -> int:
    key = ask_key().strip().strip("'\"")
    if key.startswith(tuple(name + "=" for name in NAMES)):
        key = key.split("=", 1)[1].strip().strip("'\"")
    if not key:
        print("Ingen noegle indtastet — .env er uaendret.")
        return 1
    if len(key) < 16:
        print(f"Noeglen er kun {len(key)} tegn — afbrudt, .env er uaendret.")
        return 1

    old = ENV.read_text(encoding="utf-8") if ENV.exists() else ""
    kept = [
        line
        for line in old.splitlines()
        if not any(line.strip().startswith(name + "=") for name in NAMES)
    ]
    body = "\n".join(["ROYAL_MCP_TOKEN=" + key, *kept]).strip() + "\n"

    tmp = ENV.with_suffix(".env.tmp")
    tmp.write_text(body, encoding="utf-8")
    os.chmod(tmp, 0o600)
    os.replace(tmp, ENV)

    print(f"Skrev {ENV}: 1 noegle + {len(kept)} andre linjer, {len(key)} tegn, mode 600.")
    if os.environ.get("SGAP_ENV_FILE"):
        return 0

    check = subprocess.run(
        [sys.executable, str(ROOT / "wp_mcp_stdio.py"), "--check"],
        capture_output=True,
        text=True,
        cwd=ROOT,
    )
    print("Broens --check:", check.stdout.strip() or check.stderr.strip()[:200])
    return 0 if check.returncode == 0 else 1


if __name__ == "__main__":
    raise SystemExit(main())
