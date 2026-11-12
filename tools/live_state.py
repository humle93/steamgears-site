#!/usr/bin/env python3
"""live_state.py — mål sitets MCP-tilstand og AI-profil gennem Novamira-fladen.

Spørgsmålet der besvares: **har profilen på det levende site faktisk tændt AI
Engines Dynamic REST og Abilities, og hvilken profil står der?** Svaret kan kun
læses med et token, så målingen ligger i CI, hvor `NOVAMIRA_REFRESH_TOKEN` findes.

Målingen går gennem Novamiras tre meta-værktøjer (`discover-abilities`,
`get-ability-info`, `execute-ability`) og kalder to abilities fra plugin'et:

    steamgears-autopilot/site-health    → ai_engine_mcp {abilities, dynamic_rest, role}, autopilot_version
    steamgears-autopilot/get-settings   → ai_profile (den gemte profil)

Argumentnavnene på `execute-ability` læses fra `tools/list` i stedet for at blive
gættet: fladen har heddet både `{name, input}` og `{ability, arguments}`. Kan
skemaet ikke læses, prøves de to former, og rapporten skriver hvilken der virkede.

Kør lokalt (kræver et token i `~/.config/steamgears-mcp/novamira/tokens.json`
eller `NOVAMIRA_ACCESS_TOKEN` i miljøet):

    python3 tools/live_state.py                  # skriv rapport i reports/
    python3 tools/live_state.py --out /tmp/x     # anden mappe
    python3 tools/live_state.py --print          # kun til skærmen

I CI kaldes den af `tools/mcp_keepalive.py --live-report reports`, så tokenet kun
fornyes og roteres **én** gang pr. kørsel.

Exit: 0 = alle krævede krav opfyldt · 1 = et krav fejlede eller kunne ikke måles ·
2 = ingen credential (målingen er sprunget over, intet er skrevet).
Tokenet printes aldrig — kun længder og sitets egne svar.
"""

from __future__ import annotations

import datetime
import json
import os
import re
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
RESOURCE = os.environ.get("NOVAMIRA_RESOURCE") or "https://steamgears.eu/wp-json/mcp/novamira-oauth"
HEALTH_ABILITY = os.environ.get("LIVE_HEALTH_ABILITY") or "steamgears-autopilot/site-health"
SETTINGS_ABILITY = os.environ.get("LIVE_SETTINGS_ABILITY") or "steamgears-autopilot/get-settings"
PLUGIN_FILE = ROOT / "plugins" / "steamgears-autopilot" / "steamgears-autopilot.php"

# Skema-navne set i praksis: AI Engine/adapteren bruger name+input, Novamira kan bruge
# ability+arguments. Rækkefølgen er den vi foretrækker, når skemaet ikke kan læses.
ABILITY_KEYS = ("name", "ability", "ability_name", "abilityName", "tool")
INPUT_KEYS = ("input", "arguments", "args", "parameters", "params")


# ---------------------------------------------------------------- små hjælpere

def repo_version() -> str:
    """Plugin-versionen i repoet — det tal den levende side bør svare med."""
    try:
        head = PLUGIN_FILE.read_text(encoding="utf-8", errors="replace")[:4000]
    except OSError:
        return ""
    match = re.search(r"^\s*\*?\s*Version:\s*([0-9][0-9A-Za-z.\-]*)", head, re.MULTILINE)
    return match.group(1) if match else ""


def pick_key(properties, candidates) -> str:
    """Det argumentnavn meta-værktøjet faktisk bruger, ud fra dets input-skema."""
    for key in candidates:
        if isinstance(properties, dict) and key in properties:
            return key
    return candidates[0]


def find_key(payload, key: str, depth: int = 0):
    """Første værdi med nøglen `key`, uanset hvor dybt den ligger (get-settings er grupperet)."""
    if depth > 6:
        return None
    if isinstance(payload, dict):
        if key in payload:
            return payload[key]
        for value in payload.values():
            found = find_key(value, key, depth + 1)
            if found is not None:
                return found
    elif isinstance(payload, list):
        for value in payload:
            found = find_key(value, key, depth + 1)
            if found is not None:
                return found
    return None


def unwrap(body) -> tuple[dict | None, str]:
    """Værktøjets payload ud af et tools/call-svar, eller en kort fejltekst."""
    if not isinstance(body, dict):
        return None, f"ikke JSON: {str(body)[:120]}"
    if body.get("error"):
        return None, str(body["error"])[:200]
    result = body.get("result")
    if not isinstance(result, dict):
        return None, "svaret har intet result"
    if result.get("isError"):
        text = " ".join(part.get("text", "") for part in result.get("content") or [] if isinstance(part, dict))
        return None, (text.strip() or "værktøjet svarede isError")[:200]
    structured = result.get("structuredContent")
    if isinstance(structured, dict) and structured:
        inner = structured.get("result")
        return (inner if isinstance(inner, dict) else structured), ""
    for part in result.get("content") or []:
        if isinstance(part, dict) and part.get("type") == "text":
            try:
                payload = json.loads(part["text"])
            except ValueError:
                return {"text": part["text"]}, ""
            return (payload if isinstance(payload, dict) else {"value": payload}), ""
    return None, "tomt svar"


def setting_value(raw):
    """get-settings svarer {value, kind, help?}; en rå værdi accepteres også."""
    if isinstance(raw, dict) and "value" in raw:
        return raw["value"]
    return raw


# ---------------------------------------------------------------- målingen

def _call(post, token: str, session: str | None, tool: str, args: dict) -> tuple[dict | None, str]:
    extra = {"Mcp-Session-Id": session} if session else None
    status, body, _ = post(RESOURCE, {"jsonrpc": "2.0", "id": 3, "method": "tools/call",
                                      "params": {"name": tool, "arguments": args}},
                           token=token, extra=extra)
    if status != 200:
        return None, f"HTTP {status}: {str(body)[:160]}"
    return unwrap(body)


def measure(access_token: str, session: str | None = None,
            abilities: tuple[str, str] = (HEALTH_ABILITY, SETTINGS_ABILITY)) -> dict:
    """Kalder de to abilities og returnerer den målte tilstand plus hvad der gik galt."""
    from mcp_keepalive import post  # samme HTTP-lag som fornyelsen, ingen kopi

    health_ability, settings_ability = abilities
    out: dict = {"resource": RESOURCE, "measured_at": datetime.datetime.now(datetime.timezone.utc)
                 .strftime("%Y-%m-%dT%H:%M:%SZ"), "errors": {}}

    status, body, headers = post(RESOURCE, {"jsonrpc": "2.0", "id": 1, "method": "initialize",
                                            "params": {"protocolVersion": "2025-06-18", "capabilities": {},
                                                       "clientInfo": {"name": "steamgears-live-state",
                                                                      "version": "1.0"}}},
                                 token=access_token)
    if status != 200 or not isinstance(body, dict) or "result" not in body:
        out["errors"]["initialize"] = f"HTTP {status}: {str(body)[:160]}"
        return out
    session = session or headers.get("Mcp-Session-Id") or headers.get("mcp-session-id")
    extra = {"Mcp-Session-Id": session} if session else None
    post(RESOURCE, {"jsonrpc": "2.0", "method": "notifications/initialized"}, token=access_token, extra=extra)

    status, body, _ = post(RESOURCE, {"jsonrpc": "2.0", "id": 2, "method": "tools/list", "params": {}},
                           token=access_token, extra=extra)
    tools = (body.get("result") or {}).get("tools") if isinstance(body, dict) else None
    tools = tools or []
    out["tools"] = len(tools)

    execute = next((tool for tool in tools if str(tool.get("name", "")).endswith("execute-ability")), None)
    if execute is None:
        out["errors"]["tools/list"] = "fladen har intet execute-ability — kan ikke kalde en ability"
        return out
    tool_name = execute.get("name")
    properties = (execute.get("inputSchema") or {}).get("properties") or {}
    out["argument_navne"] = {"ability": pick_key(properties, ABILITY_KEYS),
                             "input": pick_key(properties, INPUT_KEYS)}

    def call(ability: str) -> tuple[dict | None, str]:
        args = {out["argument_navne"]["ability"]: ability}
        if out["argument_navne"].get("input"):
            # Novamira kræver både ability_name og parameters (begge required);
            # et tomt objekt er nok, fordi evnerne her ikke tager input.
            args[out["argument_navne"]["input"]] = {}
        payload, error = _call(post, access_token, session, tool_name, args)
        if payload is None and not properties:
            # Intet skema at læse: prøv den anden kendte form, før vi giver op.
            args = {ABILITY_KEYS[1]: ability, INPUT_KEYS[1]: {}}
            payload, error = _call(post, access_token, session, tool_name, args)
            if payload is not None:
                out["argument_navne"]["ability"] = ABILITY_KEYS[1]
        return payload, error

    health, error = call(health_ability)
    # Novamira svarer {"success": true, "data": {...}}; felterne ligger i data.
    if isinstance(health, dict) and isinstance(health.get("data"), dict):
        health = health["data"]
    if health is None:
        out["errors"][health_ability] = error
    else:
        mcp = health.get("ai_engine_mcp") if isinstance(health.get("ai_engine_mcp"), dict) else {}
        out["ai_engine_mcp"] = {
            "dynamic_rest": mcp.get("dynamic_rest"),
            "abilities": mcp.get("abilities"),
            "role": mcp.get("role"),
        } if mcp else None
        out["autopilot_version"] = health.get("autopilot_version")
        out["wordpress"] = health.get("wordpress")
        out["php"] = health.get("php")

    settings, error = call(settings_ability)
    if isinstance(settings, dict) and isinstance(settings.get("data"), dict):
        settings = settings["data"]
    if settings is None:
        out["errors"][settings_ability] = error
        out["ai_profile"] = None
    else:
        out["ai_profile"] = setting_value(find_key(settings, "ai_profile"))

    out["session"] = bool(session)
    return out


# ---------------------------------------------------------------- dommen

def evaluate(measured: dict, expect_profile: str = "", expect_version: str = "") -> list[dict]:
    """Kravene som rækker: navn, krav, målt, status (ok/fejl/advarsel/ukendt)."""
    checks: list[dict] = []

    def add(name: str, krav: str, maalt, status: str) -> None:
        checks.append({"navn": name, "krav": krav, "maalt": maalt, "status": status})

    if measured.get("errors", {}).get("initialize"):
        add("MCP-forbindelse", "initialize svarer 200", measured["errors"]["initialize"], "fejl")
        return checks
    if "tools/list" in measured.get("errors", {}):
        add("MCP-værktøjer", "execute-ability findes", measured["errors"]["tools/list"], "fejl")
        return checks

    mcp = measured.get("ai_engine_mcp")
    health_error = next((v for k, v in measured.get("errors", {}).items()
                         if k.endswith("site-health")), "")
    if mcp is None:
        add("AI Engine MCP-niveau", "site-health svarer ai_engine_mcp",
            health_error or "feltet mangler — plugin'et er ældre end feltet", "fejl")
    else:
        for felt, label in (("dynamic_rest", "Dynamic REST"), ("abilities", "Abilities")):
            value = mcp.get(felt)
            status = "ok" if value is True else ("fejl" if value is False else "ukendt")
            add(f"AI Engine {label}", "true", {True: "true", False: "false"}.get(value, "ikke målt"), status)

        role = mcp.get("role")
        add("MCP-rolle", "dokumentation: admin", role if role else "ikke målt",
            "ok" if role in ("admin", "administrator") else "advarsel")

    version = measured.get("autopilot_version")
    target = expect_version or repo_version()
    if version and target:
        add("Plugin-version på sitet", f"{target} (repoet)",
            version, "ok" if version == target else "advarsel")
    elif version is None:
        add("Plugin-version på sitet", target or "læses fra repoet",
            health_error or "ikke målt", "ukendt")

    profile = measured.get("ai_profile")
    if profile is None:
        add("AI-profil", expect_profile or "læses",
            measured.get("errors", {}).get(SETTINGS_ABILITY, "ikke målt"), "ukendt")
    elif expect_profile:
        add("AI-profil", expect_profile, profile, "ok" if profile == expect_profile else "fejl")
    else:
        add("AI-profil", "oplyses (ikke et krav)", profile, "ok")

    return checks


def verdict(checks: list[dict]) -> str:
    if any(check["status"] == "fejl" for check in checks):
        return "fejlet"
    if any(check["status"] == "ukendt" for check in checks):
        return "delvist målt"
    if any(check["status"] == "advarsel" for check in checks):
        return "bestået med advarsel"
    return "bestået"


def failed(checks: list[dict]) -> int:
    return sum(1 for check in checks if check["status"] in ("fejl", "ukendt"))


# ---------------------------------------------------------------- rapporten

MARK = {"ok": "✓", "fejl": "✗", "advarsel": "!", "ukendt": "?"}


def render(measured: dict, checks: list[dict], when: str = "") -> str:
    when = when or measured.get("measured_at", "")
    lines = [
        "# Live-tilstand — MCP-adgang og AI-profil",
        "",
        f"Målt **{when}** gennem Novamira-fladen (`{measured.get('resource', RESOURCE)}`) af "
        "`tools/live_state.py`, som kører i den natlige CI-kørsel "
        "([`novamira-keepalive.yml`](../.github/workflows/novamira-keepalive.yml)). "
        "Ingen token står i denne fil.",
        "",
        f"**Dom: {verdict(checks)}** · {len(checks) - failed(checks)} af {len(checks)} krav opfyldt, "
        f"{measured.get('tools', 0)} MCP-værktøjer på fladen.",
        "",
        "| Krav | Forventet | Målt | |",
        "| --- | --- | --- | --- |",
    ]
    for check in checks:
        lines.append(f"| {check['navn']} | `{check['krav']}` | `{check['maalt']}` | {MARK.get(check['status'], '?')} |")
    lines += [
        "",
        "## Hvad tallene betyder",
        "",
        "- **Dynamic REST** og **Abilities** er de to nøgler i AI Engines `mwai_options` som profilen "
        "*Site administration* slår til gennem `enable_full_access()`. Står de på `true`, er den fulde "
        "REST-flade tændt — uanset om profilen blev sat i går eller 2/11.",
        "- **AI-profil** er den gemte `sgap_ai_profile`. Profilen styrer Autopilotens **egen** AI Chat "
        "(værktøjsliste og persona); den eksterne MCP-flade læser de to nøgler ovenfor. Derfor kan "
        "profilen skifte uden at `mwai_options` ændrer sig.",
        "- **Plugin-version på sitet** er kun en advarsel: et plugin-folder skiftes af WordPress, så "
        "sitet kan stå på en ældre version end repoet, indtil nogen klikker *Plugins → Update*.",
        "",
    ]
    if measured.get("errors"):
        lines += ["## Fejl under målingen", ""]
        for key, value in measured["errors"].items():
            lines.append(f"- `{key}`: {value}")
        lines.append("")
    lines += [
        "Genberegn med `python3 tools/live_state.py` (lokalt, kræver token uden for repoet) — "
        "eller se næste natlige kørsel.",
        "",
    ]
    return "\n".join(lines)


def write_report(measured: dict, checks: list[dict], out_dir: Path, when: str = "") -> tuple[Path, Path]:
    out_dir.mkdir(parents=True, exist_ok=True)
    date = (when or measured.get("measured_at", ""))[:10] or datetime.date.today().isoformat()
    markdown = out_dir / f"live-tilstand-{date}.md"
    payload = out_dir / f"live-tilstand-{date}.json"
    markdown.write_text(render(measured, checks, when), encoding="utf-8")
    payload.write_text(json.dumps({"measured_at": measured.get("measured_at"), "verdict": verdict(checks),
                                   "failed": failed(checks), "checks": checks,
                                   "measured": measured}, ensure_ascii=False, indent=1) + "\n",
                       encoding="utf-8")
    return markdown, payload


def report(access_token: str, session: str | None = None, out_dir: Path | str = "reports",
           when: str = "") -> tuple[Path, Path, list[dict]]:
    """Mål, døm og skriv — den vej CI'en bruger."""
    measured = measure(access_token, session)
    checks = evaluate(measured, os.environ.get("LIVE_EXPECT_PROFILE", ""),
                      os.environ.get("LIVE_EXPECT_VERSION", ""))
    paths = write_report(measured, checks, Path(out_dir), when)
    return paths[0], paths[1], checks


# ---------------------------------------------------------------- CLI

def local_token() -> str:
    token = os.environ.get("NOVAMIRA_ACCESS_TOKEN", "").strip()
    if token:
        return token
    try:
        from mcp_keepalive import TOKEN_FILE, read_json
    except Exception:
        return ""
    return str(read_json(TOKEN_FILE).get("access_token") or "")


def main() -> int:
    args = sys.argv[1:]
    out_dir = Path("reports")
    if "--out" in args:
        index = args.index("--out")
        if index + 1 >= len(args):
            print("brug: --out <mappe>")
            return 1
        out_dir = Path(args[index + 1])
    token = local_token()
    if not token:
        print("Ingen credential: sæt NOVAMIRA_ACCESS_TOKEN, eller kør tools/mcp_oauth.py --start "
              "--finish for Novamira-fladen. Intet er målt og intet er skrevet.")
        return 2
    markdown, payload, checks = report(token, out_dir=out_dir)
    measured = json.loads(payload.read_text(encoding="utf-8"))["measured"]
    print(render(measured, checks))
    if "--print" not in args:
        print(f"Skrev {markdown} og {payload}")
    return 0 if failed(checks) == 0 else 1


if __name__ == "__main__":
    raise SystemExit(main())
