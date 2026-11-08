#!/usr/bin/env python3
"""live_channels.py — verify P1.5: which platform numbers actually landed in sgap_events.

The question this answers is the one P1.5 is judged on, per channel:

    Pinterest: token works (`ready`) — did impressions/saves/outbound clicks land?
    Telegram:  no analytics API — this channel can never answer, and says so.

It calls one ability on the live site, `steamgears-autopilot/channel-health`, which combines
the two halves that must not be confused: whether the next refresh *can* run (token stored,
scope missing, refused) and whether it *did* land a row in `sgap_events`. A channel that is
`ready` and `empty` is the interesting row — the credential works and nothing came back.

Målingen kræver et token til Novamira-fladen, så den kan ikke køre i en tom sandkasse:

    python3 tools/live_channels.py                 # 90-dages vinduet
    python3 tools/live_channels.py --days 30

Exit: 0 = alle kanaler med en metric har landet tal · 1 = en kanal med et virkende token
landede intet (eller forbindelsen fejlede) · 2 = ingen credential (intet er målt) · 3 = intet
er fejlet, men målingen kan ikke laves endnu: en kanal mangler token eller scope, eller
plugin'et på sitet er ældre end abilityen.

Tokenet printes aldrig — kun sitets egne svar.
"""

from __future__ import annotations

import datetime
import os
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent))

import live_state  # noqa: E402  (samme HTTP-lag, samme svar-udpakning)
from mcp_keepalive import post  # noqa: E402

CHANNELS_ABILITY = os.environ.get("LIVE_CHANNELS_ABILITY") or "steamgears-autopilot/channel-health"
DEFAULT_DAYS = 90


# ---------------------------------------------------------------- målingen

def measure(access_token: str, days: int = DEFAULT_DAYS) -> dict:
    """Kalder channel-health-abilityen og returnerer dens svar plus hvad der gik galt."""
    out: dict = {
        "measured_at": datetime.datetime.now(datetime.timezone.utc).strftime("%Y-%m-%dT%H:%M:%SZ"),
        "days": days,
        "errors": {},
    }
    status, body, headers = post(live_state.RESOURCE, {
        "jsonrpc": "2.0", "id": 1, "method": "initialize",
        "params": {"protocolVersion": "2025-06-18", "capabilities": {},
                   "clientInfo": {"name": "steamgears-live-channels", "version": "1.0"}},
    }, token=access_token)
    if status != 200 or not isinstance(body, dict) or "result" not in body:
        out["errors"]["initialize"] = f"HTTP {status}: {str(body)[:160]}"
        return out
    session = headers.get("Mcp-Session-Id") or headers.get("mcp-session-id")
    extra = {"Mcp-Session-Id": session} if session else None
    post(live_state.RESOURCE, {"jsonrpc": "2.0", "method": "notifications/initialized"},
         token=access_token, extra=extra)

    status, body, _ = post(live_state.RESOURCE, {"jsonrpc": "2.0", "id": 2, "method": "tools/list",
                                                 "params": {}}, token=access_token, extra=extra)
    tools = ((body or {}).get("result") or {}).get("tools") if isinstance(body, dict) else None
    tools = tools or []
    out["tools"] = len(tools)

    execute = next((tool for tool in tools if str(tool.get("name", "")).endswith("execute-ability")), None)
    if execute is None:
        out["errors"]["tools/list"] = "fladen har intet execute-ability — kan ikke kalde en ability"
        return out
    properties = (execute.get("inputSchema") or {}).get("properties") or {}
    ability_key = live_state.pick_key(properties, live_state.ABILITY_KEYS)
    input_key = live_state.pick_key(properties, live_state.INPUT_KEYS)
    out["argument_navne"] = {"ability": ability_key, "input": input_key}

    arguments = {ability_key: CHANNELS_ABILITY}
    if input_key in properties:
        arguments[input_key] = {"days": days}
    payload, error = live_state._call(post, access_token, session, execute.get("name"), arguments)
    if payload is None and not properties:
        arguments = {live_state.ABILITY_KEYS[1]: CHANNELS_ABILITY}
        payload, error = live_state._call(post, access_token, session, execute.get("name"), arguments)
        if payload is not None:
            out["argument_navne"]["ability"] = live_state.ABILITY_KEYS[1]
    if payload is None:
        out["errors"][CHANNELS_ABILITY] = error
        return out

    out["window_days"] = payload.get("window_days")
    out["channels"] = payload.get("channels") or {}
    out["analytics"] = payload.get("analytics") or {}
    out["analytics_summary"] = payload.get("analytics_summary") or {}
    return out


# ---------------------------------------------------------------- dommen

MEASURABLE = ("ready", "needs_refresh")


def evaluate(measured: dict) -> list[dict]:
    """Én række pr. kanal: kan den hente, landede der noget, og hvad betyder det."""
    if measured.get("errors"):
        first = next(iter(measured["errors"].items()))
        # Kun forbindelsen selv er en fejl. Et plugin der ikke har abilityen endnu
        # (live kørte 7.64.6 da denne måling blev bygget) kan ikke måles — og det er
        # blokeret, ikke rødt: ejerens *Plugins → Update* er svaret, ikke en fejlretning.
        transport = first[0] in ("initialize", "tools/list")
        return [{"kanal": "MCP-forbindelse" if transport else "channel-health",
                 "kan_hente": "—", "landet": "—",
                 "status": "fejl" if transport else "blokeret",
                 "detalje": f"{first[0]}: {first[1]}"}]
    analytics = measured.get("analytics") or {}
    if not analytics:
        return [{"kanal": "channel-health", "kan_hente": "—", "landet": "—",
                 "status": "blokeret",
                 "detalje": "svaret har ingen analytics — plugin'et er ældre end abilityen "
                            "(opdater til 7.64.9)"}]

    rows: list[dict] = []
    for channel, row in analytics.items():
        health = (measured.get("channels") or {}).get(channel) or {}
        can = str(health.get("state") or "ukendt")
        landed = str(row.get("state") or "ukendt")
        total = row.get("total")
        if landed == "measured":
            status, detail = "ok", f"{len(row.get('counts') or {})} event-typer i sgap_events"
        elif landed == "no_metric":
            status, detail = "ok", "kanalen har ingen analytics-API — 0 er ikke en manglende måling"
        elif landed == "empty" and can in MEASURABLE:
            status, detail = "fejl", "tokenet virker, men intet er landet — kør refresh og se fejlloggen"
        elif landed == "empty":
            status, detail = "blokeret", str(health.get("next") or "kanalen kan ikke måles endnu")
        else:
            status, detail = "ukendt", "tilstanden kunne ikke læses"
        rows.append({
            "kanal": str(health.get("label") or row.get("label") or channel),
            "kan_hente": can, "landet": landed,
            "total": total, "sidste": row.get("last_at"), "status": status, "detalje": detail,
        })
    return rows


def verdict(rows: list[dict]) -> str:
    if any(row["status"] in ("fejl", "ukendt") for row in rows):
        return "fejlet"
    if any(row["status"] == "blokeret" for row in rows):
        return "blokeret"
    return "bestået"


MARK = {"ok": "✓", "fejl": "✗", "ukendt": "?", "blokeret": "·"}


def render(measured: dict, rows: list[dict]) -> str:
    lines = [
        "# P1.5 live — platformtal pr. kanal i sgap_events",
        "",
        f"Målt **{measured.get('measured_at', '')}** gennem `{CHANNELS_ABILITY}`"
        f" ({measured.get('window_days') or measured.get('days')} dages vindue). "
        "Ingen token står i denne rapport.",
        "",
        f"**Dom: {verdict(rows)}**",
        "",
        "| Kanal | Kan hente | Landet | Total | Sidste | |",
        "| --- | --- | --- | --- | --- | --- |",
    ]
    for row in rows:
        total = "" if row.get("total") is None else f"{row['total']:g}"
        lines.append(f"| {row['kanal']} | `{row['kan_hente']}` | `{row['landet']}` | {total} | "
                     f"{row.get('sidste') or '—'} | {MARK.get(row['status'], '?')} |")
    lines.append("")
    for row in rows:
        lines.append(f"- **{row['kanal']}:** {row['detalje']}")
    lines += [
        "",
        "`ready` + `empty` er den række der betyder noget: tokenet virker, og intet kom tilbage. "
        "`measured` er P1.5's bevis. `no_metric` (Telegram) kan aldrig blive `measured`, fordi "
        "Bot API ikke har tal pr. opslag.",
        "",
    ]
    return "\n".join(lines)


# ---------------------------------------------------------------- CLI

def main() -> int:
    args = sys.argv[1:]
    days = DEFAULT_DAYS
    if "--days" in args:
        index = args.index("--days")
        if index + 1 >= len(args) or not args[index + 1].isdigit():
            print("brug: --days <1-365>")
            return 1
        days = max(1, min(365, int(args[index + 1])))
    token = live_state.local_token()
    if not token:
        print("Ingen credential: sæt NOVAMIRA_ACCESS_TOKEN, eller kør tools/mcp_oauth.py --start "
              "--finish for Novamira-fladen. Intet er målt.")
        return 2
    measured = measure(token, days)
    rows = evaluate(measured)
    print(render(measured, rows))
    return {"bestået": 0, "fejlet": 1, "blokeret": 3}[verdict(rows)]


if __name__ == "__main__":
    raise SystemExit(main())
