#!/usr/bin/env python3
"""cirla_watch.py — mål Cirla-modulet på det levende site og gem en tidsserie.

C7 i `projektstatus.json` kræver en overvågning på **24 timer** oven på den kørsel der
allerede er dokumenteret i `docs/library/cirla-c7-produktion-2026-11-11.md`. En agent-sandkasse
kan ikke holde et ur kørende et døgn, så målingen lægges der hvor der allerede kører en natlig
kørsel: `novamira-keepalive.yml` fornyer tokenet kl. 03 og kalder dette værktøj med det samme
fornyede access-token. Hver kørsel tilføjer **én** række, og `--summary` svarer om vinduet
faktisk er dækket.

Hvad der måles (og hvad det betyder):

  * `enabled`            — er modulet stadig tændt, eller faldt det tilbage?
  * `actions`            — hvilke af de fire handlinger er tilladte
  * `cron.next`          — er det daglige job planlagt, og hvornår kører det næste gang
  * `ai_calls_today`     — forbrug mod det daglige loft (skal være 0/5 uden kald)
  * `pending_drafts`     — kladder skrevet af Cirla (må ikke vokse af sig selv)
  * `log`                — antal og nyeste rækker i modulets egen log, så et cron-tick ses

Kør:

    python3 tools/cirla_watch.py --observe            # mål nu og tilføj en række
    python3 tools/cirla_watch.py --summary            # er 24-timers-vinduet dækket?
    python3 tools/cirla_watch.py --observe --print    # kun til skærmen, intet skrevet

Exit: 0 = målt · 1 = målingen fejlede · 2 = ingen credential (intet skrevet).
Tokenet printes aldrig.
"""

from __future__ import annotations

import datetime
import json
import os
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
RESOURCE = os.environ.get("NOVAMIRA_RESOURCE") or "https://steamgears.eu/wp-json/mcp/novamira-oauth"
STATUS_ABILITY = os.environ.get("CIRLA_STATUS_ABILITY") or "steamgears-autopilot/cirla-status"
SERIES = ROOT / "reports" / "cirla-c7-observationer.json"
WINDOW_HOURS = 24


def parse_time(value) -> datetime.datetime | None:
    """ISO-tid fra site eller fil → aware datetime; None når feltet er tomt eller ugyldigt."""
    if not value or not isinstance(value, str):
        return None
    text = value.strip().replace("Z", "+00:00")
    try:
        moment = datetime.datetime.fromisoformat(text)
    except ValueError:
        return None
    if moment.tzinfo is None:
        moment = moment.replace(tzinfo=datetime.timezone.utc)
    return moment


def observation(status: dict, at: datetime.datetime | None = None) -> dict:
    """Den ene række vi gemmer. Kun felter der kan læses ud af cirla-status."""
    if not isinstance(status, dict) or status.get("_error"):
        raise ValueError(f"cirla-status svarede ikke et objekt: {str(status)[:160]}")
    at = at or datetime.datetime.now(datetime.timezone.utc)
    cron = status.get("cron") if isinstance(status.get("cron"), dict) else {}
    budget = status.get("ai_calls_today") if isinstance(status.get("ai_calls_today"), dict) else {}
    log = status.get("log") if isinstance(status.get("log"), list) else []
    newest = log[0] if log and isinstance(log[0], dict) else {}
    return {
        "at": at.strftime("%Y-%m-%dT%H:%M:%SZ"),
        "enabled": bool(status.get("enabled")),
        "actions": [str(a) for a in (status.get("actions") or [])],
        "cron_next": cron.get("next"),
        "cron_scheduled": bool(cron.get("next")),
        "ai_calls_used": budget.get("used"),
        "ai_calls_limit": budget.get("limit"),
        "pending_drafts": status.get("pending_drafts"),
        "log_rows": len(log),
        "log_newest": {"at": newest.get("at"), "action": newest.get("action")} if newest else None,
        "version": status.get("version"),
    }


def load_series(path: Path = SERIES) -> list[dict]:
    try:
        rows = json.loads(path.read_text(encoding="utf-8"))
    except (OSError, ValueError):
        return []
    return [row for row in rows if isinstance(row, dict) and row.get("at")] if isinstance(rows, list) else []


def append(path: Path = SERIES, row: dict | None = None) -> list[dict]:
    """Tilføj én række; samme dag overskrives, så en genkørsel ikke laver dubletter."""
    if row is None:
        raise ValueError("append kræver en række")
    rows = load_series(path)
    day = str(row["at"])[:10]
    rows = [existing for existing in rows if str(existing.get("at", ""))[:10] != day]
    rows.append(row)
    rows.sort(key=lambda existing: str(existing.get("at", "")))
    path.parent.mkdir(parents=True, exist_ok=True)
    path.write_text(json.dumps(rows, ensure_ascii=False, indent=1) + "\n", encoding="utf-8")
    return rows


def window(rows: list[dict], hours: int = WINDOW_HOURS) -> dict:
    """Er der to målinger med mindst `hours` mellem sig, og hvad siger de om modulet?"""
    stamps = [parse_time(row.get("at")) for row in rows]
    stamps = sorted(stamp for stamp in stamps if stamp)
    if not stamps:
        return {"covered": False, "hours": 0.0, "needed": hours, "observations": 0,
                "reason": "ingen målinger endnu"}
    span = (stamps[-1] - stamps[0]).total_seconds() / 3600
    failing = [row for row in rows if not row.get("enabled")]
    drifted = [
        row for row in rows
        if row.get("enabled") and (row.get("ai_calls_used") not in (0, None)
                                   or not row.get("cron_scheduled"))
    ]
    covered = span >= hours
    reason = ""
    if not covered:
        reason = f"kun {span:.1f} af {hours} timer mellem første og nyeste måling"
    elif failing:
        reason = f"modulet var slukket i {len(failing)} måling(er): " + ", ".join(
            str(row.get("at")) for row in failing)
    elif drifted:
        reason = (f"{len(drifted)} måling(er) viser forbrug eller manglende cron: "
                  + ", ".join(str(row.get("at")) for row in drifted))
    return {"covered": covered, "hours": round(span, 1), "needed": hours,
            "observations": len(rows), "first": rows[0].get("at"), "last": rows[-1].get("at"),
            "failing": len(failing), "drifted": len(drifted), "reason": reason}


def measure(access_token: str) -> dict:
    """Kald cirla-status gennem Novamira-fladen og returnér modulets eget svar."""
    from mcp_keepalive import post

    status, body, headers = post(RESOURCE, {"jsonrpc": "2.0", "id": 1, "method": "initialize",
                                            "params": {"protocolVersion": "2025-06-18", "capabilities": {},
                                                       "clientInfo": {"name": "steamgears-cirla-watch",
                                                                      "version": "1.0"}}},
                                 token=access_token)
    if status != 200 or not isinstance(body, dict) or "result" not in body:
        raise RuntimeError(f"initialize svarede HTTP {status}: {str(body)[:160]}")
    session = headers.get("Mcp-Session-Id") or headers.get("mcp-session-id")
    extra = {"Mcp-Session-Id": session} if session else None
    post(RESOURCE, {"jsonrpc": "2.0", "method": "notifications/initialized"}, token=access_token, extra=extra)

    status, body, _ = post(RESOURCE, {"jsonrpc": "2.0", "id": 2, "method": "tools/list", "params": {}},
                           token=access_token, extra=extra)
    tools = (body.get("result") or {}).get("tools") if isinstance(body, dict) else None
    execute = next((tool for tool in tools or [] if str(tool.get("name", "")).endswith("execute-ability")), None)
    if execute is None:
        raise RuntimeError("fladen har intet execute-ability")
    properties = (execute.get("inputSchema") or {}).get("properties") or {}
    ability_key = "ability_name" if "ability_name" in properties else "ability"
    input_key = "parameters" if "parameters" in properties else "input"

    status, body, _ = post(RESOURCE, {"jsonrpc": "2.0", "id": 3, "method": "tools/call",
                                      "params": {"name": execute.get("name"),
                                                 "arguments": {ability_key: STATUS_ABILITY, input_key: {}}}},
                           token=access_token, extra=extra)
    if status != 200 or not isinstance(body, dict):
        raise RuntimeError(f"tools/call svarede HTTP {status}: {str(body)[:160]}")
    result = body.get("result") or {}
    if result.get("isError"):
        text = " ".join(part.get("text", "") for part in result.get("content") or [] if isinstance(part, dict))
        raise RuntimeError(f"cirla-status svarede isError: {text[:160]}")
    payload = result.get("structuredContent")
    if isinstance(payload, dict):
        inner = payload.get("data", payload)
        if isinstance(inner, dict) and isinstance(inner.get("result"), dict):
            inner = inner["result"]
        return inner if isinstance(inner, dict) else {"value": inner}
    for part in result.get("content") or []:
        if isinstance(part, dict) and part.get("type") == "text":
            try:
                return json.loads(part["text"])
            except ValueError:
                return {"text": part["text"]}
    raise RuntimeError("cirla-status svarede et tomt resultat")


def local_token() -> str:
    token = os.environ.get("NOVAMIRA_ACCESS_TOKEN", "").strip()
    if token:
        return token
    try:
        from mcp_keepalive import TOKEN_FILE, read_json
    except Exception:  # noqa: BLE001 — uden helper er der ingen credential
        return ""
    return str(read_json(TOKEN_FILE).get("access_token") or "")


def render_row(row: dict) -> str:
    return (f"{row['at']} · {'tændt' if row['enabled'] else 'SLUKKET'} · "
            f"handlinger {','.join(row['actions']) or 'ingen'} · "
            f"cron {'planlagt' if row['cron_scheduled'] else 'MANGLER'} · "
            f"AI {row['ai_calls_used']}/{row['ai_calls_limit']} · "
            f"kladder {row['pending_drafts']} · log {row['log_rows']} rækker · v{row['version']}")


def main(argv: list[str] | None = None) -> int:
    args = list(sys.argv[1:] if argv is None else argv)
    if "--summary" in args:
        rows = load_series()
        state = window(rows)
        print(json.dumps(state, ensure_ascii=False, indent=1))
        for row in rows:
            print(render_row(row))
        return 0 if state["covered"] and not state.get("reason") else 1
    token = local_token()
    if not token:
        print("Ingen credential: sæt NOVAMIRA_ACCESS_TOKEN, eller kør tools/mcp_oauth.py. "
              "Intet er målt og intet er skrevet.")
        return 2
    try:
        row = observation(measure(token))
    except (RuntimeError, ValueError) as exc:
        print(f"[FEJL] {exc}")
        return 1
    print(render_row(row))
    if "--print" not in args:
        rows = append(SERIES, row)
        state = window(rows)
        print(f"Skrev {SERIES.relative_to(ROOT)} ({len(rows)} målinger, "
              f"{state['hours']:.1f} af {state['needed']} timer)")
        if state["reason"]:
            print(f"Vinduet er ikke dækket endnu: {state['reason']}")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
