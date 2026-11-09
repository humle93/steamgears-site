#!/usr/bin/env python3
"""Værn for backup- og gendannelsesøvelsen (S5.8) — frist, RTO og rækkeparitet.

Øvelsen i ``tools/wp_db_drill.py`` er kun et værn, hvis den bliver **kørt igen**.
Målt 9/11 2026 blev den kørt én gang: 269 af 269 tabeller identiske, RTO 6 s.
Et engangstal ældes, og et site der vokser ændrer både dumpstørrelse og
gendannelsestid — derfor holder dette værns to ting op hver nat:

* **fristen** — hvor mange dage siden den seneste kørsel, og hvornår den næste
  senest skal være kørt (31 dage, med 3 dages varsel i ``--due``),
* **tallene** — at den seneste kørsel faktisk var grøn: 0 rækkeafvigelser,
  0 manglende tabeller, 0 rester efter oprydning, og en RTO inden for budgettet.

Historikken læses fra ``reports/db-drill-history.json`` (én række pr. kørsel,
skrevet af øvelsen selv) og fra ``reports/db-drill-<dato>.json``. Historikken
foretrækkes, fordi to kørsler samme dag ellers overskriver hinanden.

Brug::

    python3 tools/db_drill_check.py              # menneskelæsbar status + trend
    python3 tools/db_drill_check.py --strict     # exit 1 ved frist- eller talbrud
    python3 tools/db_drill_check.py --due        # exit 3 når øvelsen er ved at forfalde
    python3 tools/db_drill_check.py --json       # maskinlæsbar dom
    python3 tools/db_drill_check.py --selftest   # 10 fixtures, offline

Exit-koder: ``0`` alt godt · ``1`` brud (frist eller tal) · ``3`` øvelse nært
forfalden (kun med ``--due``).
"""

from __future__ import annotations

import argparse
import datetime as dt
import json
import pathlib
import sys
import tempfile

ROOT = pathlib.Path(__file__).resolve().parent.parent
REPORTS = ROOT / "reports"
HISTORY = REPORTS / "db-drill-history.json"

MAX_AGE_DAYS = 31          # månedlig kadence; øvelsen skal være kørt mindst én gang pr. 31. dag
LEAD_DAYS = 3              # --due varsler tre dage før fristen
RTO_BUDGET_SECONDS = 300   # 5 min: målt 6 s 9/11, så budgettet fanger en regression, ikke en dagsform
MIN_TABLES = 1


# ------------------------------------------------------------------ indlæsning

def read_json(path: pathlib.Path):
    """Returnerer (data, fejl) — en korrupt fil må ikke få værnet til at gætte."""
    try:
        return json.loads(path.read_text(encoding="utf-8")), ""
    except FileNotFoundError:
        return None, "findes ikke"
    except ValueError as exc:
        return None, f"kan ikke læses som JSON: {exc}"
    except OSError as exc:
        return None, f"kan ikke læses: {exc}"


def load_runs(reports: pathlib.Path, history: pathlib.Path) -> tuple[list[dict], list[str]]:
    """Alle kørsler, ældste først.

    Historikken er den primære kilde, fordi den har én række pr. kørsel (også to samme
    dag). Dagsfilerne læses kun som **bagudfyldning**: filer ældre end historikkens
    første række tælles med, så en måling fra før historikken blev indført ikke forsvinder.
    """
    problems: list[str] = []
    files: dict[str, dict] = {}

    for path in sorted(reports.glob("db-drill-[0-9][0-9][0-9][0-9]-[0-9][0-9]-[0-9][0-9].json")):
        data, error = read_json(path)
        if data is None:
            problems.append(f"{path.name}: {error}")
            continue
        if not isinstance(data, dict):
            problems.append(f"{path.name}: forventede et objekt")
            continue
        row = dict(data)
        row.setdefault("dato", path.stem.replace("db-drill-", ""))
        row["_kilde"] = path.name
        files[str(row.get("dato"))] = row

    history_rows: list[dict] = []
    if history.exists():
        data, error = read_json(history)
        if data is None:
            problems.append(f"{history.name}: {error}")
        elif isinstance(data, list):
            for index, row in enumerate(data):
                if not isinstance(row, dict) or not row.get("dato"):
                    problems.append(f"{history.name}: række {index + 1} mangler 'dato'")
                    continue
                row = dict(row)
                row["_kilde"] = history.name
                history_rows.append(row)
        else:
            problems.append(f"{history.name}: forventede en liste")

    if history_rows:
        first = min(str(row.get("dato")) for row in history_rows)
        runs = [row for date, row in files.items() if date < first] + history_rows
    else:
        runs = list(files.values())

    ordered = sorted(runs, key=lambda r: (str(r.get("dato")), str(r.get("tidspunkt", ""))))
    return ordered, problems


def parse_date(value) -> dt.date | None:
    try:
        return dt.date.fromisoformat(str(value))
    except (TypeError, ValueError):
        return None


# ------------------------------------------------------------------ dommen

def evaluate(runs: list[dict], problems: list[str], today: dt.date | None = None,
             max_age: int = MAX_AGE_DAYS, lead: int = LEAD_DAYS,
             rto_budget: int = RTO_BUDGET_SECONDS) -> dict:
    """Ren funktion: kørsler ind, dom ud. Ingen filer, ingen ur udefra."""
    today = today or dt.date.today()
    out: dict = {"today": today.isoformat(), "max_age_days": max_age, "lead_days": lead,
                 "rto_budget_seconds": rto_budget, "runs": len(runs),
                 "checks": [], "problems": list(problems)}

    def add(name: str, ok: bool, detail: str) -> None:
        out["checks"].append({"navn": name, "ok": bool(ok), "detalje": detail})

    if not runs:
        add("der findes en måling", False, "ingen kørsler fundet — øvelsen er aldrig kørt")
        out["ok"] = False
        out["due"] = True
        out["age_days"] = None
        out["next_due"] = None
        return out

    latest = runs[-1]
    ran = parse_date(latest.get("dato"))
    if ran is None:
        add("datoen kan læses", False, f"seneste kørsel har datoen {latest.get('dato')!r}")
        out["ok"] = False
        out["due"] = True
        out["age_days"] = None
        out["next_due"] = None
        return out

    age = (today - ran).days
    next_due = ran + dt.timedelta(days=max_age)
    out["age_days"] = age
    out["last_run"] = ran.isoformat()
    out["next_due"] = next_due.isoformat()
    out["days_left"] = (next_due - today).days

    add("fristen er holdt", 0 <= age <= max_age,
        f"seneste kørsel {ran.isoformat()} — {age} dage siden (frist {max_age} dage, "
        f"næste senest {next_due.isoformat()})")

    ok_run = latest.get("ok")
    add("seneste kørsel var grøn", ok_run is True, f"ok = {ok_run!r}")

    expected = latest.get("tables_expected")
    add("der blev målt tabeller", isinstance(expected, int) and expected >= MIN_TABLES,
        f"tables_expected = {expected!r}")

    for field, label in (("row_mismatches", "rækkeafvigelser"), ("missing_tables", "manglende tabeller"),
                         ("left_behind", "rester efter oprydning")):
        value = latest.get(field)
        add(f"0 {label}", value == 0, f"{field} = {value!r}")

    rto = latest.get("rto_seconds", latest.get("restore_seconds"))
    add("RTO inden for budget", isinstance(rto, (int, float)) and 0 < rto <= rto_budget,
        f"rto_seconds = {rto!r} (budget {rto_budget} s)")

    dump = latest.get("dump_bytes")
    add("dumpet er ikke tomt", isinstance(dump, int) and dump > 0, f"dump_bytes = {dump!r}")

    out["ok"] = all(check["ok"] for check in out["checks"])
    out["due"] = age >= max_age - lead
    out["overdue"] = age > max_age
    out["latest"] = {key: latest.get(key) for key in
                     ("dato", "dump_bytes", "restore_seconds", "rto_seconds", "tables_expected",
                      "row_mismatches", "missing_tables", "left_behind", "ok")}
    return out


def trend(runs: list[dict], limit: int = 6) -> list[str]:
    """De seneste kørsler som tabel-linjer — så RTO og paritet ses over tid, ikke som ét tal."""
    rows = ["| Kørsel | Dump | Gendannelse | Tabeller | Afvigelser | Rester | Dom |",
            "| --- | --- | --- | --- | --- | --- | --- |"]
    for run in runs[-limit:]:
        rto = run.get("rto_seconds", run.get("restore_seconds"))
        rows.append("| {dato} | {dump} | {rto} s | {tab} | {mis} | {left} | {dom} |".format(
            dato=run.get("dato", "?"),
            dump=f"{(run.get('dump_bytes') or 0) / 1_000_000:.2f} MB",
            rto=rto if rto is not None else "?",
            tab=run.get("tables_expected", "?"),
            mis=run.get("row_mismatches", "?"),
            left=run.get("left_behind", "?"),
            dom="✓" if run.get("ok") else "✗"))
    return rows


def render(result: dict, runs: list[dict]) -> str:
    lines = [f"Backup-øvelse (S5.8) — {result['runs']} kørsel(ler) i historikken"]
    if result.get("next_due"):
        lines.append(f"Seneste: {result['last_run']} · {result['age_days']} dage siden · "
                     f"næste senest {result['next_due']} ({result['days_left']} dage tilbage)")
    for check in result["checks"]:
        lines.append(f"  {'OK  ' if check['ok'] else 'FEJL'}  {check['navn']}: {check['detalje']}")
    for problem in result["problems"]:
        lines.append(f"  FEJL  kilden kan ikke læses: {problem}")
    if runs:
        lines.append("")
        lines.extend(trend(runs))
    lines.append("")
    if result["ok"] and not result["problems"]:
        lines.append("OK — fristen er holdt, og rækkeparitet og RTO holder.")
    elif result.get("due") and result["ok"]:
        lines.append("FORFALDEN SNART — kør `python3 tools/wp_db_drill.py` inden "
                     f"{result['next_due']}.")
    else:
        lines.append("FEJL — kør `python3 tools/wp_db_drill.py` og ret det der står ovenfor.")
    return "\n".join(lines)


# ------------------------------------------------------------------ selftest

def selftest() -> int:
    today = dt.date(2026, 11, 9)
    good = {"dato": "2026-11-09", "dump_bytes": 3338987, "restore_seconds": 6, "rto_seconds": 6,
            "tables_expected": 269, "row_mismatches": 0, "missing_tables": 0, "left_behind": 0,
            "ok": True}
    fixtures = [
        ("frisk kørsel er grøn", [good], [], 0),
        ("kørsel for 40 dage siden er forfalden", [dict(good, dato="2026-09-30")], [], 1),
        ("tom historik er et brud", [], [], 1),
        ("én rækkeafvigelse er et brud", [dict(good, row_mismatches=1)], [], 1),
        ("rester efter oprydning er et brud", [dict(good, left_behind=2)], [], 1),
        ("RTO over budget er et brud", [dict(good, rto_seconds=601, restore_seconds=601)], [], 1),
        ("tomt dump er et brud", [dict(good, dump_bytes=0)], [], 1),
        ("nul tabeller er et brud", [dict(good, tables_expected=0)], [], 1),
        ("en kørsel der ikke er ok er et brud", [dict(good, ok=False)], [], 1),
        ("en korrupt kilde gør dommen rød", [good], ["db-drill-x.json: kan ikke læses som JSON: x"], 1),
    ]
    failures = []
    for name, runs, problems, expected in fixtures:
        result = evaluate(runs, problems, today=today)
        code = 0 if (result["ok"] and not result["problems"]) else 1
        if code != expected:
            failures.append(f"{name}: fik exit {code}, forventede {expected}")
    # --due: 29 dage gammel kørsel er grøn men varsler (frist 31, varsel 3)
    due = evaluate([dict(good, dato="2026-10-11")], [], today=today)
    if not (due["ok"] and due["due"] and due["days_left"] == 2):
        failures.append(f"--due: forventede grøn + varsel med 2 dage tilbage, fik ok={due['ok']} "
                        f"due={due.get('due')} days_left={due.get('days_left')}")
    fresh = evaluate([good], [], today=today)
    if fresh["due"]:
        failures.append("--due: en kørsel fra i dag må ikke varsle")
    # trend: to kørsler giver to rækker, ældste først
    rows = trend([dict(good, dato="2026-10-01"), good])
    if len(rows) != 4 or "2026-10-01" not in rows[2] or "2026-11-09" not in rows[3]:
        failures.append("trend: forventede to rækker i kronologisk rækkefølge")
    # indlæsning: historikken er primær, dagsfiler ældre end den første historik-række fylder bagud
    with tempfile.TemporaryDirectory() as tmp:
        root = pathlib.Path(tmp)
        (root / "db-drill-2026-11-09.json").write_text(json.dumps(good), encoding="utf-8")
        (root / "db-drill-2026-10-01.json").write_text(
            json.dumps(dict(good, dato="2026-10-01")), encoding="utf-8")
        (root / "db-drill-history.json").write_text(
            json.dumps([dict(good, tidspunkt="05:01")]), encoding="utf-8")
        runs, problems = load_runs(root, root / "db-drill-history.json")
        if problems or len(runs) != 2:
            failures.append(f"indlæsning: forventede 2 kørsler uden fejl, fik {len(runs)} og {problems}")
        (root / "db-drill-2026-11-08.json").write_text("{ ikke json", encoding="utf-8")
        runs, problems = load_runs(root, root / "db-drill-history.json")
        if not problems:
            failures.append("indlæsning: en korrupt dagsfil skal give en fejl, ikke et gæt")
        (root / "db-drill-history.json").unlink()
        runs, problems = load_runs(root, root / "db-drill-history.json")
        if len(runs) != 2:
            failures.append(f"indlæsning: uden historik skal dagsfilerne bruges, fik {len(runs)}")

    for failure in failures:
        print("FAIL", failure)
    if failures:
        return 1
    print(f"OK — {len(fixtures) + 5} fixtures: frist, paritet, RTO, dødt dump, korrupte kilder og trend")
    return 0


# ------------------------------------------------------------------ CLI

def main() -> int:
    ap = argparse.ArgumentParser(description="Værn for backup-øvelsen (S5.8)")
    ap.add_argument("--selftest", action="store_true")
    ap.add_argument("--json", action="store_true", help="kun JSON til stdout")
    ap.add_argument("--strict", action="store_true", help="exit 1 ved frist- eller talbrud")
    ap.add_argument("--due", action="store_true", help="exit 3 når øvelsen er nær fristen")
    ap.add_argument("--max-age-days", type=int, default=MAX_AGE_DAYS)
    ap.add_argument("--lead-days", type=int, default=LEAD_DAYS)
    ap.add_argument("--rto-budget", type=int, default=RTO_BUDGET_SECONDS)
    ap.add_argument("--reports", default=str(REPORTS))
    args = ap.parse_args()

    if args.selftest:
        return selftest()

    reports = pathlib.Path(args.reports)
    runs, problems = load_runs(reports, reports / "db-drill-history.json")
    result = evaluate(runs, problems, max_age=args.max_age_days, lead=args.lead_days,
                      rto_budget=args.rto_budget)

    if args.json:
        print(json.dumps(result, ensure_ascii=False, indent=1))
    else:
        print(render(result, runs))

    if not result["ok"] or result["problems"]:
        return 1 if args.strict else 0
    if args.due and result["due"]:
        return 3
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
