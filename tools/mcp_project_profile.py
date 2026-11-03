#!/usr/bin/env python3
"""mcp_project_profile.py — lokal MCP-server (stdio) der serverer projektprofilen.

Formålet er at gøre profilen og den aktuelle status tilgængelig for **enhver** bot der
forbinder til repoet, uden at den først skal læse filer eller bruge site-credentials.
Serveren læser kun fra repoet; den skriver intet og kalder intet netværk.

Registreret i .mcp.json som stdio-serveren `steamgears-profil`:

    {"command": "python3", "args": ["tools/mcp_project_profile.py"]}

Værktøjer:  project_profile · project_status · roadmap_index
Ressourcer: steamgears://profil · steamgears://status

Test uden MCP-klient:
    printf '%s\n' '{"jsonrpc":"2.0","id":1,"method":"tools/list"}' | python3 tools/mcp_project_profile.py
"""

from __future__ import annotations

import json
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
PROFILE = ROOT / "docs" / "agent-profil.md"
STATE = ROOT / "projektstatus.json"
ROADMAPS = {
    "site": ROOT / "docs" / "roadmap.md",
    "plugin": ROOT / "plugins" / "steamgears-autopilot" / "ROADMAP.md",
}
SERVER_INFO = {"name": "steamgears-profil", "version": "1.0.0"}
PROTOCOL = "2025-06-18"


def log(message: str) -> None:
    """Alt der ikke er JSON-RPC skal til stderr, ellers brækker protokollen."""
    sys.stderr.write(f"[profil-mcp] {message}\n")
    sys.stderr.flush()


def read(path: Path, fallback: str) -> str:
    try:
        return path.read_text(encoding="utf-8")
    except OSError as exc:
        return f"{fallback} (kunne ikke læses: {exc})"


def number(value: float) -> str:
    text = f"{round(float(value), 2):.2f}".rstrip("0").rstrip(".")
    return (text or "0").replace(".", ",")


def status_text() -> str:
    """Beregner samme tal som projektstatus.py — uden at skrive noget."""
    try:
        data = json.loads(STATE.read_text(encoding="utf-8"))
    except (OSError, ValueError) as exc:
        return f"Kunne ikke læse projektstatus.json: {exc}"

    lines = ["# Projektstatus (beregnet fra projektstatus.json)", ""]
    lines.append("| Projekt | Status | Grundlag |")
    lines.append("| --- | --- | --- |")
    for project in data.get("projects", []):
        modules = [m for m in project.get("modules", []) if not m.get("recurring")]
        if not modules:
            continue
        done = sum(float(m["done"]) for m in modules)
        total = sum(float(m["total"]) for m in modules)
        unit = modules[0].get("unit", "")
        pct = round(done / total * 100, 1) if total else 0.0
        basis = " · ".join(
            f"{m['name'].split(' —')[0]} {number(m['done'])}/{number(m['total'])}" for m in modules
        )
        lines.append(
            f"| {project.get('name', project.get('id'))} | {number(pct)} % "
            f"| {number(done)} af {number(total)} {unit} ({basis}) |"
        )

    history = data.get("history", [])
    lines.append("")
    lines.append(f"Historik-rækker: {len(history)}")
    if history:
        last = history[-1]
        lines.append(
            f"Seneste række: {last.get('date', '?')} · {last.get('module', '?')} "
            f"{last.get('before', '?')} → {last.get('after', '?')} %"
        )
    return "\n".join(lines)


TOOLS = [
    {
        "name": "project_profile",
        "description": (
            "Læs hele agentprofilen for steamgears.eu: hvad projektet er, verificerede fakta, "
            "adgangsmatrix, opskrifter og fælder. Læs den før du ændrer noget i repoet."
        ),
        "inputSchema": {"type": "object", "properties": {}, "additionalProperties": False},
    },
    {
        "name": "project_status",
        "description": "Aktuel procentstatus for alle fem projekter, beregnet fra projektstatus.json.",
        "inputSchema": {"type": "object", "properties": {}, "additionalProperties": False},
    },
    {
        "name": "roadmap_index",
        "description": "Roadmap'en for et scope: 'site' (S0–S4) eller 'plugin' (P0–P3).",
        "inputSchema": {
            "type": "object",
            "properties": {
                "scope": {"type": "string", "enum": ["site", "plugin"], "default": "site"}
            },
            "additionalProperties": False,
        },
    },
]


def call_tool(name: str, arguments: dict) -> str:
    if name == "project_profile":
        return read(PROFILE, "Profilen mangler")
    if name == "project_status":
        return status_text()
    if name == "roadmap_index":
        scope = (arguments or {}).get("scope", "site")
        path = ROADMAPS.get(scope)
        if path is None:
            raise ValueError(f"ukendt scope: {scope}")
        return read(path, f"Roadmap for {scope} mangler")
    raise ValueError(f"ukendt værktøj: {name}")


def resources() -> list[dict]:
    return [
        {
            "uri": "steamgears://profil",
            "name": "Agentprofil",
            "description": "Alt en bot skal vide om steamgears.eu",
            "mimeType": "text/markdown",
        },
        {
            "uri": "steamgears://status",
            "name": "Projektstatus",
            "description": "Beregnet procentstatus pr. projekt",
            "mimeType": "text/markdown",
        },
    ]


def handle(message: dict) -> dict | None:
    method = message.get("method")
    params = message.get("params") or {}
    msg_id = message.get("id")

    if method is None:
        return None  # svar fra klienten, ikke en anmodning
    if method == "initialize":
        return result(msg_id, {
            "protocolVersion": PROTOCOL,
            "capabilities": {"tools": {"listChanged": False}, "resources": {"listChanged": False}},
            "serverInfo": SERVER_INFO,
        })
    if method.startswith("notifications/"):
        return None
    if method == "ping":
        return result(msg_id, {})
    if method == "tools/list":
        return result(msg_id, {"tools": TOOLS})
    if method == "tools/call":
        name = params.get("name")
        try:
            text = call_tool(name, params.get("arguments") or {})
        except Exception as exc:  # værktøjsfejl skal tilbage til klienten, ikke dræbe serveren
            return result(msg_id, {
                "content": [{"type": "text", "text": str(exc)}],
                "isError": True,
            })
        return result(msg_id, {"content": [{"type": "text", "text": text}], "isError": False})
    if method == "resources/list":
        return result(msg_id, {"resources": resources()})
    if method == "resources/read":
        uri = params.get("uri")
        if uri == "steamgears://profil":
            text = read(PROFILE, "Profilen mangler")
        elif uri == "steamgears://status":
            text = status_text()
        else:
            return error(msg_id, -32602, f"ukendt ressource: {uri}")
        return result(msg_id, {"contents": [{"uri": uri, "mimeType": "text/markdown", "text": text}]})
    if method == "shutdown":
        return result(msg_id, {})
    return error(msg_id, -32601, f"metode ikke understøttet: {method}")


def result(msg_id, payload) -> dict:
    return {"jsonrpc": "2.0", "id": msg_id, "result": payload}


def error(msg_id, code: int, message: str) -> dict:
    return {"jsonrpc": "2.0", "id": msg_id, "error": {"code": code, "message": message}}


def main() -> None:
    log(f"klar · profil: {PROFILE.relative_to(ROOT)}")
    for line in sys.stdin:
        line = line.strip()
        if not line:
            continue
        try:
            message = json.loads(line)
        except ValueError:
            sys.stdout.write(json.dumps(error(None, -32700, "ugyldig JSON")) + "\n")
            sys.stdout.flush()
            continue
        try:
            response = handle(message)
        except Exception as exc:
            response = error(message.get("id"), -32603, f"intern fejl: {exc}")
        if response is not None:
            sys.stdout.write(json.dumps(response, ensure_ascii=False) + "\n")
            sys.stdout.flush()


if __name__ == "__main__":
    try:
        main()
    except (BrokenPipeError, KeyboardInterrupt):
        pass
