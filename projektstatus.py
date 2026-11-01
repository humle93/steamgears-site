#!/usr/bin/env python3
"""projektstatus.py — automatisk fremskridtsprocent for steamgears-site.

Automatiserer reglen i AGENTS.md §1–2:

  * procenterne *beregnes* ud fra projektstatus.json (de indtastes ikke),
  * docs/projektstatus.md skrives kun i de markerede AUTO-blokke, så manuel tekst overlever,
  * historikken er append-only og må ikke falde uden dokumenteret scope-ændring.

Kommandoer:

  python3 projektstatus.py --selftest   # FØR installation: tester alle værn på fixtures
  python3 projektstatus.py --check      # validering + drift-tjek, skriver intet
  python3 projektstatus.py --dry-run    # vis hvad --update ville gøre
  python3 projektstatus.py --update     # beregn, skriv, verificer (atomisk + backup)

Exitkoder: 0 = ok, 1 = valideringsfejl/drift fundet, 2 = intern fejl eller forkert brug.
"""

from __future__ import annotations

import argparse
import copy
import datetime as dt
import json
import os
import re
import shutil
import sys
import tempfile
from pathlib import Path

ROOT = Path(__file__).resolve().parent
STATE_PATH = ROOT / "projektstatus.json"
DOC_PATH = ROOT / "docs" / "projektstatus.md"
BRIEF_DIR = ROOT / "docs" / "daily-briefs"
BRIEF_MARKERS = ("<!-- AUTO:PROJEKTSTATUS:START -->", "<!-- AUTO:PROJEKTSTATUS:END -->")
BACKUP_DIR = ROOT / ".projektstatus-backups"
LOCK_PATH = ROOT / ".projektstatus.lock"

MAX_HISTORY = 5000
MAX_BACKUPS = 10
LOCK_TIMEOUT_SECONDS = 600

MARKERS = {
    "daglig": ("<!-- AUTO:DAGLIG:START -->", "<!-- AUTO:DAGLIG:END -->"),
    "blokeringer": ("<!-- AUTO:BLOKERINGER:START -->", "<!-- AUTO:BLOKERINGER:END -->"),
    "log": ("<!-- AUTO:LOG:START -->", "<!-- AUTO:LOG:END -->"),
}

ID_RE = re.compile(r"^[a-z0-9][a-z0-9-]*$")
DATE_RE = re.compile(r"^\d{4}-\d{2}-\d{2}$")
TOLERANCE = 0.05


class Guard(Exception):
    """En fejl der skal stoppe kørslen med en forståelig besked."""


# --------------------------------------------------------------------------- format


def today_iso(today: dt.date | None = None) -> str:
    return (today or dt.date.today()).isoformat()


def fmt_num(value: float) -> str:
    """Dansk talformat, op til 2 decimaler: 21.1 -> '21,1', 0.15 -> '0,15'."""
    text = f"{round(float(value), 4):.2f}".rstrip("0").rstrip(".")
    return text.replace(".", ",")


def fmt_pct(value: float) -> str:
    """3.6 -> '3,6 %', 63.0 -> '63 %'."""
    text = f"{round(float(value), 1):.1f}".rstrip("0").rstrip(".")
    return f"{text.replace('.', ',')} %"


def fmt_delta(delta: float | None) -> str:
    if delta is None:
        return "—"
    if abs(delta) < TOLERANCE:
        return "0"
    sign = "+" if delta > 0 else "-"
    return sign + fmt_num(abs(delta))


# --------------------------------------------------------------------------- model


def active_modules(project: dict) -> list[dict]:
    return [m for m in project.get("modules", []) if not m.get("recurring")]


def recurring_modules(project: dict) -> list[dict]:
    return [m for m in project.get("modules", []) if m.get("recurring")]


def project_totals(project: dict) -> tuple[float, float]:
    modules = active_modules(project)
    done = sum(float(m.get("done", 0)) for m in modules)
    total = sum(float(m.get("total", 0)) for m in modules)
    return done, total


def project_pct(project: dict) -> float:
    done, total = project_totals(project)
    if total <= 0:
        raise Guard(f"projekt '{project.get('id')}' har ingen aktivt modul med total > 0")
    return round(done / total * 100, 1)


def project_unit(project: dict) -> str:
    modules = active_modules(project)
    return str(modules[0].get("unit", "")) if modules else ""


def project_basis(project: dict) -> str:
    """Regnestykket, så procenten altid kan genberegnes."""
    done, total = project_totals(project)
    parts = []
    for module in active_modules(project):
        parts.append(f"{module['name'].split(' —')[0]} {fmt_num(module.get('done', 0))}/{fmt_num(module.get('total', 0))}")
    basis = f"{fmt_num(done)} af {fmt_num(total)} {project_unit(project)}"
    if len(parts) > 1:
        basis += " (" + " · ".join(parts) + ")"
    return basis


def compute(state: dict) -> dict[str, dict]:
    out: dict[str, dict] = {}
    for project in state.get("projects", []):
        done, total = project_totals(project)
        out[str(project["id"])] = {
            "pct": project_pct(project),
            "done": done,
            "total": total,
            "basis": project_basis(project),
            "name": project["name"],
        }
    return out


# --------------------------------------------------------------------------- validering


def validate_state(state: dict, today: str | None = None) -> list[str]:
    """Alle invariants. Returnerer en liste af fejl; tom liste = ok."""
    errors: list[str] = []
    today = today or today_iso()

    if not isinstance(state, dict):
        return ["roden i projektstatus.json skal være et objekt"]
    if state.get("schema") != 1:
        errors.append(f"schema skal være 1 (fik {state.get('schema')!r})")

    updated = state.get("updated")
    if not isinstance(updated, str) or not DATE_RE.match(updated):
        errors.append(f"updated skal være YYYY-MM-DD, fik {updated!r}")
    else:
        try:
            if dt.date.fromisoformat(updated) > dt.date.fromisoformat(today):
                errors.append(f"updated ({updated}) ligger i fremtiden (i dag er {today})")
        except ValueError:
            errors.append(f"updated er ikke en gyldig dato: {updated!r}")

    projects = state.get("projects")
    if not isinstance(projects, list) or not projects:
        errors.append("projects skal være en ikke-tom liste")
        projects = []

    seen_projects: set[str] = set()
    for index, project in enumerate(projects):
        label = f"projects[{index}]"
        if not isinstance(project, dict):
            errors.append(f"{label} skal være et objekt")
            continue

        pid = project.get("id")
        if not isinstance(pid, str) or not ID_RE.match(pid):
            errors.append(f"{label}.id skal være en slug (a-z, 0-9, bindestreg), fik {pid!r}")
        elif pid in seen_projects:
            errors.append(f"dupleret projekt-id: {pid}")
        else:
            seen_projects.add(pid)
            label = f"projekt '{pid}'"

        if not str(project.get("name", "")).strip():
            errors.append(f"{label}: name mangler")

        modules = project.get("modules")
        if not isinstance(modules, list) or not modules:
            errors.append(f"{label}: modules skal være en ikke-tom liste")
            continue

        seen_modules: set[str] = set()
        for m_index, module in enumerate(modules):
            mlabel = f"{label}, modul[{m_index}]"
            if not isinstance(module, dict):
                errors.append(f"{mlabel} skal være et objekt")
                continue
            mid = module.get("id")
            if not isinstance(mid, str) or not ID_RE.match(mid):
                errors.append(f"{mlabel}.id skal være en slug, fik {mid!r}")
            elif mid in seen_modules:
                errors.append(f"{label}: dupleret modul-id {mid}")
            else:
                seen_modules.add(mid)
                mlabel = f"{label}, modul '{mid}'"

            if not str(module.get("name", "")).strip():
                errors.append(f"{mlabel}: name mangler")
            if not str(module.get("unit", "")).strip():
                errors.append(f"{mlabel}: unit mangler (fx 'd', 't', 'links')")

            for field in ("done", "total"):
                value = module.get(field)
                if isinstance(value, bool) or not isinstance(value, (int, float)):
                    errors.append(f"{mlabel}: {field} skal være et tal, fik {value!r}")
                    break
            else:
                done = float(module["done"])
                total = float(module["total"])
                if total <= 0:
                    errors.append(f"{mlabel}: total skal være > 0 (fik {total})")
                if done < 0:
                    errors.append(f"{mlabel}: done må ikke være negativ (fik {done})")
                if total > 0 and done > total:
                    errors.append(
                        f"{mlabel}: done ({fmt_num(done)}) er større end total ({fmt_num(total)}) — "
                        "procenten ville blive over 100"
                    )

        active = active_modules(project)
        if not active:
            errors.append(f"{label}: mindst ét ikke-recurring modul kræves, ellers findes der ingen procent")
        units = {str(m.get("unit", "")) for m in active}
        if len(units) > 1:
            errors.append(
                f"{label}: alle aktive moduler skal have samme enhed, ellers kan de ikke lægges sammen "
                f"(fandt {sorted(units)})"
            )
        if active and project.get("verified") is not True:
            done, total = project_totals(project)
            if total > 0 and round(done / total * 100, 1) >= 100:
                errors.append(
                    f"{label}: 100 % kræver \"verified\": true — 100 % betyder færdigt og verificeret, "
                    "ikke 'kode skrevet'"
                )

    errors.extend(validate_history(state, {p.get("name") for p in projects if isinstance(p, dict)}, today))
    return errors


def validate_history(state: dict, project_names: set, today: str) -> list[str]:
    errors: list[str] = []
    history = state.get("history", [])
    if not isinstance(history, list):
        return ["history skal være en liste"]
    if len(history) > MAX_HISTORY:
        errors.append(f"history har {len(history)} rækker (loft {MAX_HISTORY}) — arkivér gamle rækker")

    last_by_project: dict[str, dict] = {}
    for index, row in enumerate(history):
        label = f"history[{index}]"
        if not isinstance(row, dict):
            errors.append(f"{label} skal være et objekt")
            continue
        date = row.get("date")
        if not isinstance(date, str) or not DATE_RE.match(date):
            errors.append(f"{label}.date skal være YYYY-MM-DD, fik {date!r}")
        else:
            try:
                parsed = dt.date.fromisoformat(date)
            except ValueError:
                errors.append(f"{label}.date er ikke en gyldig dato: {date!r}")
                parsed = None
            if parsed and parsed > dt.date.fromisoformat(today):
                errors.append(f"{label}.date ({date}) ligger i fremtiden (i dag er {today})")

        for field in ("before", "after"):
            value = row.get(field)
            if value is None and field == "before":
                continue
            if isinstance(value, bool) or not isinstance(value, (int, float)):
                errors.append(f"{label}.{field} skal være et tal eller null")
            elif not (0 <= float(value) <= 100):
                errors.append(f"{label}.{field} skal ligge i 0–100 (fik {value})")

        before, after, delta = row.get("before"), row.get("after"), row.get("delta")
        if isinstance(before, (int, float)) and isinstance(after, (int, float)):
            expected = round(float(after) - float(before), 1)
            if isinstance(delta, (int, float)) and abs(float(delta) - expected) > TOLERANCE:
                errors.append(
                    f"{label}.delta ({delta}) passer ikke med after - before ({expected})"
                )
            if row.get("scope_change") is not True and float(after) + TOLERANCE < float(before):
                errors.append(
                    f"{label}: procenten falder ({before} → {after}) uden \"scope_change\": true — "
                    "fald kræver dokumenteret årsag"
                )

        name = row.get("project")
        if name not in project_names:
            errors.append(f"{label}.project '{name}' findes ikke i projects")
        if not str(row.get("basis", "")).strip():
            errors.append(f"{label}.basis mangler — hvert tal skal have et regnestykke")

        previous = last_by_project.get(name)
        if previous and isinstance(after, (int, float)) and isinstance(previous.get("after"), (int, float)):
            if row.get("scope_change") is not True and float(after) + TOLERANCE < float(previous["after"]):
                errors.append(
                    f"{label}: {name} falder fra {previous['after']} til {after} i forhold til forrige række "
                    "uden scope_change"
                )
        last_by_project[name] = row
    return errors


# --------------------------------------------------------------------------- rendering


def render_daily(state: dict, computed: dict, today: str, json_href: str = "../projektstatus.json") -> str:
    lines = [
        "",
        f"Status pr. **{today}** · beregnet af `projektstatus.py` · kilder: [`projektstatus.json`]({json_href})",
        "",
        "| Projekt | Status i dag | Grundlag |",
        "| --- | --- | --- |",
    ]
    for project in state["projects"]:
        info = computed[str(project["id"])]
        name = f"**{project['name']}**"
        if project.get("note"):
            name += f" *({project['note']})*"
        basis = info["basis"]
        recurring = recurring_modules(project)
        if recurring:
            bits = []
            for module in recurring:
                done = module.get("done", 0)
                total = module.get("total", 1)
                state_word = "kører" if float(done) >= float(total) else "ikke kørt"
                bits.append(f"{module['name'].split(':')[0]} {state_word}")
            basis += " · drift: " + "; ".join(bits)
        lines.append(f"| {name} | **{fmt_pct(info['pct'])}** | {basis} |")
    lines.append("")
    return "\n".join(lines)


def render_blockers(state: dict, computed: dict) -> str:
    lines = [
        "",
        "| Projekt | Næste skridt | Blokering |",
        "| --- | --- | --- |",
    ]
    for project in state["projects"]:
        info = computed[str(project["id"])]
        blockers = []
        if project.get("blocked_by"):
            blockers.append(str(project["blocked_by"]))
        for module in project.get("modules", []):
            if module.get("blocked"):
                blockers.append(f"{module['name']}: {module['blocked']}")
        lines.append(
            f"| {project['name']} ({fmt_pct(info['pct'])}) | {project.get('next', '—')} | "
            f"{'; '.join(b for b in blockers if b) or 'ingen'} |"
        )
    lines.append("")
    return "\n".join(lines)


def render_log(state: dict) -> str:
    lines = [
        "",
        "Nyeste først. Append-only — rækker må ikke slettes eller ændres.",
        "",
        "| Dato | Projekt | Modul | Før → Efter | Δ | Grundlag |",
        "| --- | --- | --- | --- | --- | --- |",
    ]
    for row in reversed(state.get("history", [])):
        before = "—" if row.get("before") is None else fmt_pct(row["before"])
        lines.append(
            "| {date} | {project} | {module} | {before} → {after} | {delta} | {basis} |".format(
                date=row.get("date", "?"),
                project=row.get("project", "?"),
                module=row.get("module", "—"),
                before=before,
                after=fmt_pct(row.get("after", 0)),
                delta=fmt_delta(row.get("delta")),
                basis=row.get("basis", ""),
            )
        )
    lines.append("")
    return "\n".join(lines)


def render_regions(state: dict, computed: dict, today: str) -> dict[str, str]:
    return {
        "daglig": render_daily(state, computed, today),
        "blokeringer": render_blockers(state, computed),
        "log": render_log(state),
    }


# --------------------------------------------------------------------------- fil-io


def load_json(path: Path) -> dict:
    try:
        with open(path, encoding="utf-8") as handle:
            return json.load(handle)
    except FileNotFoundError:
        raise Guard(f"{path.name} findes ikke i {path.parent}")
    except json.JSONDecodeError as exc:
        raise Guard(f"{path.name} er ikke gyldig JSON: {exc}")


def read_text(path: Path) -> str:
    try:
        with open(path, encoding="utf-8") as handle:
            return handle.read()
    except FileNotFoundError:
        raise Guard(f"{path} findes ikke")


def backup_file(path: Path, backup_dir: Path | None = None) -> Path | None:
    if not path.exists():
        return None
    backup_dir = backup_dir or BACKUP_DIR
    backup_dir.mkdir(parents=True, exist_ok=True)
    stamp = dt.datetime.now().strftime("%Y%m%d-%H%M%S-%f")
    target = backup_dir / f"{path.name}.{stamp}.bak"
    shutil.copy2(path, target)
    old = sorted(backup_dir.glob(f"{path.name}.*.bak"))
    for stale in old[:-MAX_BACKUPS]:
        stale.unlink(missing_ok=True)
    return target


def atomic_write(path: Path, text: str) -> None:
    """Skriv til temp-fil i samme mappe og omdøb, så filen aldrig er halvskrevet."""
    path.parent.mkdir(parents=True, exist_ok=True)
    handle = tempfile.NamedTemporaryFile(
        "w", encoding="utf-8", delete=False, dir=str(path.parent), prefix=f".{path.name}.", suffix=".tmp"
    )
    try:
        with handle:
            handle.write(text)
            handle.flush()
            os.fsync(handle.fileno())
        os.replace(handle.name, path)
    except BaseException:
        try:
            os.unlink(handle.name)
        except OSError:
            pass
        raise


def extract_region(doc: str, name: str) -> str:
    start, end = MARKERS[name]
    for marker in (start, end):
        count = doc.count(marker)
        if count != 1:
            raise Guard(
                f"markøren {marker} findes {count} gange i dokumentet — skal være præcis 1, "
                "ellers kan scriptet ikke skrive sikkert"
            )
    begin = doc.index(start) + len(start)
    finish = doc.index(end)
    if finish < begin:
        raise Guard(f"markørerne for '{name}' står i forkert rækkefølge")
    return doc[begin:finish]


def replace_region(doc: str, name: str, body: str) -> str:
    extract_region(doc, name)  # validerer markørerne før noget erstattes
    start, end = MARKERS[name]
    begin = doc.index(start) + len(start)
    finish = doc.index(end)
    return doc[:begin] + body + doc[finish:]


def render_document(template: str, regions: dict[str, str]) -> str:
    doc = template
    for name, body in regions.items():
        doc = replace_region(doc, name, body)
    return doc


def write_document(doc_path: Path, text: str, expected: dict[str, str], backup_dir: Path | None = None) -> Path | None:
    """Skriv og verificér bagefter; ruller tilbage hvis indholdet ikke er som forventet."""
    previous = doc_path.read_text(encoding="utf-8") if doc_path.exists() else None
    backup = backup_file(doc_path, backup_dir)
    atomic_write(doc_path, text)
    try:
        verify_document(doc_path, expected)
    except Guard:
        if previous is not None:
            atomic_write(doc_path, previous)
        elif doc_path.exists():
            doc_path.unlink()
        raise
    return backup


def verify_document(doc_path: Path, expected: dict[str, str]) -> None:
    doc = read_text(doc_path)
    for name, body in expected.items():
        found = extract_region(doc, name)
        if found.strip() != body.strip():
            raise Guard(f"verifikation fejlede: blokken '{name}' i {doc_path.name} matcher ikke det beregnede indhold")


# --------------------------------------------------------------------------- kommandoer


class lock:
    """Simpel lås, så to samtidige kørsler ikke kan overskrive hinanden."""

    def __init__(self, path: Path = LOCK_PATH) -> None:
        self.path = path
        self.acquired = False

    def __enter__(self):
        if self.path.exists():
            age = dt.datetime.now().timestamp() - self.path.stat().st_mtime
            if age > LOCK_TIMEOUT_SECONDS:
                self.path.unlink(missing_ok=True)
            else:
                raise Guard(
                    f"en anden kørsel holder låsen ({self.path.name}, {int(age)} s gammel); "
                    "fjern filen hvis den er efterladt"
                )
        try:
            self.path.write_text(str(os.getpid()), encoding="utf-8")
        except OSError as exc:
            raise Guard(f"kunne ikke oprette låsen {self.path.name}: {exc}")
        self.acquired = True
        return self

    def __exit__(self, *exc_info):
        if self.acquired:
            self.path.unlink(missing_ok=True)
        return False


def report_errors(errors: list[str], title: str) -> int:
    print(f"[FEJL] {title}", file=sys.stderr)
    for error in errors:
        print(f"  - {error}", file=sys.stderr)
    print(f"[FEJL] {len(errors)} fejl fundet — intet blev skrevet.", file=sys.stderr)
    return 1


def cmd_check(state_path: Path = STATE_PATH, doc_path: Path = DOC_PATH, today: str | None = None) -> int:
    today = today or today_iso()
    state = load_json(state_path)
    errors = validate_state(state, today)
    if errors:
        return report_errors(errors, "projektstatus.json er ikke gyldig")

    # Drift tjekkes mod den dato der står i filen, ikke dagens dato — ellers ville
    # tjekket give falsk alarm hver gang datoen skifter.
    stored_date = str(state.get("updated") or today)
    try:
        computed = compute(state)
        regions = render_regions(state, computed, stored_date)
    except Guard as exc:
        return report_errors([str(exc)], "kunne ikke beregne procenter")

    try:
        doc = read_text(doc_path)
    except Guard as exc:
        return report_errors([str(exc)], "dokumentet mangler")

    drift = []
    for name, body in regions.items():
        try:
            found = extract_region(doc, name)
        except Guard as exc:
            drift.append(str(exc))
            continue
        if found.strip() != body.strip():
            drift.append(f"blokken '{name}' er ændret uden om scriptet (drift) — kør --update")
    if drift:
        return report_errors(drift, "docs/projektstatus.md stemmer ikke med projektstatus.json")

    for project in state["projects"]:
        info = computed[str(project["id"])]
        print(f"[OK] {info['name']}: {fmt_pct(info['pct'])} — {info['basis']}")
    if stored_date != today:
        print(f"[NOTE] status er fra {stored_date}; kør --update for at skrive dagens status")
    print(f"[OK] {len(state['projects'])} projekter · {len(state['history'])} historik-rækker · ingen drift")
    return 0


def plan_history(state: dict, computed: dict, today: str) -> list[dict]:
    """Nye historik-rækker: én pr. projekt pr. dag, og kun når noget ændrer sig."""
    rows: list[dict] = []
    for project in state["projects"]:
        info = computed[str(project["id"])]
        pct = info["pct"]
        previous = [r for r in state.get("history", []) if r.get("project") == project["name"]]
        last = previous[-1] if previous else None
        if last and last.get("date") == today and abs(float(last.get("after", -1)) - pct) < TOLERANCE:
            continue  # allerede målt i dag med samme tal
        if last and abs(float(last.get("after", -1)) - pct) < TOLERANCE:
            rows.append(
                {
                    "date": today,
                    "project": project["name"],
                    "module": "ingen ændring",
                    "before": pct,
                    "after": pct,
                    "delta": 0,
                    "basis": info["basis"],
                }
            )
            continue
        rows.append(
            {
                "date": today,
                "project": project["name"],
                "module": "daglig måling" if last else "baseline",
                "before": None if last is None else last.get("after"),
                "after": pct,
                "delta": None if last is None else round(pct - float(last.get("after", 0)), 1),
                "basis": info["basis"],
            }
        )
    return rows


def cmd_update(
    state_path: Path = STATE_PATH,
    doc_path: Path = DOC_PATH,
    today: str | None = None,
    dry_run: bool = False,
    use_lock: bool = True,
    backup_dir: Path | None = None,
) -> int:
    today = today or today_iso()
    state = load_json(state_path)
    errors = validate_state(state, today)
    if errors:
        return report_errors(errors, "projektstatus.json er ikke gyldig")

    template = read_text(doc_path)
    for name in MARKERS:
        extract_region(template, name)  # fejler tidligt hvis markørerne mangler

    computed = compute(state)
    new_rows = plan_history(state, computed, today)
    new_state = copy.deepcopy(state)
    new_state["history"] = list(state.get("history", [])) + new_rows
    new_state["updated"] = today

    history_errors = validate_state(new_state, today)
    if history_errors:
        return report_errors(history_errors, "de nye historik-rækker ville gøre filen ugyldig")

    regions = render_regions(new_state, compute(new_state), today)
    rendered = render_document(template, regions)
    changed = rendered.strip() != template.strip() or new_rows

    if dry_run or not changed:
        state_word = "ville ændre" if changed else "uændret"
        print(f"[OK] {state_word}: {len(new_rows)} ny(e) historik-række(r) pr. {today}")
        for project in new_state["projects"]:
            info = compute(new_state)[str(project["id"])]
            print(f"  {info['name']}: {fmt_pct(info['pct'])} — {info['basis']}")
        return 0

    context = lock() if use_lock else _nullcontext()
    with context:
        json_text = json.dumps(new_state, ensure_ascii=False, indent=2) + "\n"
        verify_state_text = json.loads(json_text)
        if validate_state(verify_state_text, today):
            return report_errors(validate_state(verify_state_text, today), "serialiseringen ændrede data")

        try:
            backup_file(state_path, backup_dir)
            atomic_write(state_path, json_text)
            write_document(doc_path, rendered, regions, backup_dir)
            # efterkontrol: læs det der faktisk ligger på disken
            reloaded = load_json(state_path)
            if validate_state(reloaded, today):
                raise Guard("den skrevne projektstatus.json er ugyldig")
            verify_document(doc_path, regions)
        except Guard as exc:
            return report_errors([str(exc)], "skrivningen fejlede — filerne er rullet tilbage")

    for row in new_rows:
        delta = fmt_delta(row["delta"])
        print(f"[OK] {row['project']}: {fmt_pct(row['after'])} ({delta}) — {row['basis']}")
    print(f"[OK] skrevet: {state_path.name} + {doc_path.relative_to(ROOT) if doc_path.is_relative_to(ROOT) else doc_path}")
    print(f"[OK] {len(new_state['history'])} historik-rækker · verificeret efter skrivning")
    return 0


def cmd_brief(
    state_path: Path = STATE_PATH,
    brief_dir: Path = BRIEF_DIR,
    today: str | None = None,
    dry_run: bool = False,
) -> int:
    """Skriver dagens procentstatus ind i dagens daily brief (samme AUTO-princip)."""
    today = today or today_iso()
    state = load_json(state_path)
    errors = validate_state(state, today)
    if errors:
        return report_errors(errors, "projektstatus.json er ikke gyldig")

    computed = compute(state)
    body = render_daily(state, computed, str(state.get("updated") or today), json_href="../../projektstatus.json")
    start, end = BRIEF_MARKERS
    section = f"\n## Projektstatus — procentvis fremgang\n\n{start}{body}{end}\n"

    brief_path = brief_dir / f"{today}.md"
    if brief_path.exists():
        text = read_text(brief_path)
        if text.count(start) == 1 and text.count(end) == 1:
            merged = replace_brief_region(text, body)
        elif start in text or end in text:
            return report_errors(
                [f"{brief_path.name} har kun den ene af brief-markørerne — ret dem manuelt først"],
                "brief-markørerne er i ubalance",
            )
        else:
            merged = text.rstrip("\n") + "\n" + section
    else:
        merged = f"# Daily Brief — {today}\n" + section

    if dry_run:
        print(merged)
        return 0

    try:
        backup_file(brief_path, None)
        atomic_write(brief_path, merged)
        written = read_text(brief_path)
        if written.count(start) != 1 or written.count(end) != 1:
            raise Guard("skrev brief uden entydige markører — rullet tilbage")
        if extract_brief_region(written).strip() != body.strip():
            raise Guard("brief-verifikation fejlede: indholdet matcher ikke beregningen")
    except Guard as exc:
        return report_errors([str(exc)], "kunne ikke skrive dagens brief")

    print(f"[OK] projektstatus skrevet ind i {brief_path.relative_to(ROOT) if brief_path.is_relative_to(ROOT) else brief_path}")
    for project in state["projects"]:
        info = computed[str(project["id"])]
        print(f"  {info['name']}: {fmt_pct(info['pct'])}")
    return 0


def extract_brief_region(text: str) -> str:
    start, end = BRIEF_MARKERS
    return text[text.index(start) + len(start) : text.index(end)]


def replace_brief_region(text: str, body: str) -> str:
    start, end = BRIEF_MARKERS
    begin = text.index(start) + len(start)
    finish = text.index(end)
    return text[:begin] + body + text[finish:]


class _nullcontext:
    def __enter__(self):
        return self

    def __exit__(self, *exc_info):
        return False


# --------------------------------------------------------------------------- selftest


def cmd_selftest(today: str | None = None) -> int:
    """Tester værnene på fixtures — køres FØR installationen og i CI som gate."""
    today = today or today_iso()
    base = load_json(STATE_PATH)
    doc_template = read_text(DOC_PATH)
    results: list[tuple[str, bool, str]] = []

    def case(name: str, fn) -> None:
        try:
            fn()
            results.append((name, True, ""))
        except AssertionError as exc:
            results.append((name, False, str(exc)))
        except Exception as exc:  # uventet fejl er også en fejl i værnene
            results.append((name, False, f"{type(exc).__name__}: {exc}"))

    def mutated() -> dict:
        return copy.deepcopy(base)

    def expect_error(state: dict, needle: str) -> None:
        errors = validate_state(state, today)
        assert errors, f"forventede en fejl med '{needle}', men fik ingen"
        assert any(needle in e for e in errors), f"forventede '{needle}' blandt: {errors}"

    case("gyldig status giver ingen fejl", lambda: assert_no_errors(validate_state(base, today)))
    case(
        "done > total fanges",
        lambda: expect_error(_with_module(mutated(), "site", "s0", {"done": 9}), "done"),
    )
    case(
        "total = 0 fanges",
        lambda: expect_error(_with_module(mutated(), "site", "s0", {"total": 0}), "total skal være > 0"),
    )
    case(
        "blandet enhed i samme projekt fanges",
        lambda: expect_error(_with_module(mutated(), "site", "s1", {"unit": "t"}), "samme enhed"),
    )
    case("dupleret projekt-id fanges", lambda: expect_error(_duplicate_project(mutated()), "dupleret projekt-id"))
    case(
        "kun recurring-moduler fanges",
        lambda: expect_error(_only_recurring(mutated()), "ikke-recurring"),
    )
    case(
        "100 % uden verified fanges",
        lambda: expect_error(
            _with_module(mutated(), "links", "fix", {"done": 39}, project_patch={"verified": False}), "verified"
        ),
    )
    case(
        "100 % med verified accepteres",
        lambda: assert_no_errors(
            validate_state(_with_module(mutated(), "links", "fix", {"done": 39}, project_patch={"verified": True}), today)
        ),
    )
    case("ugyldig dato fanges", lambda: expect_error(_with_history(mutated(), {"date": "11-01-2026"}), "YYYY-MM-DD"))
    case(
        "fremtidig dato fanges",
        lambda: expect_error(_with_history(mutated(), {"date": "2099-01-01"}), "fremtiden"),
    )
    case(
        "delta der ikke passer fanges",
        lambda: expect_error(_with_history(mutated(), {"delta": 42}), "delta"),
    )
    case(
        "fald uden scope_change fanges",
        lambda: expect_error(
            _with_history(mutated(), {"project": base["projects"][0]["name"], "before": 90.0, "after": 10.0}),
            "falder",
        ),
    )
    case(
        "fald med scope_change accepteres",
        lambda: assert_no_errors(
            validate_state(
                _with_history(
                    mutated(),
                    {"project": base["projects"][0]["name"], "before": 90.0, "after": 10.0, "delta": -80.0, "scope_change": True},
                ),
                today,
            )
        ),
    )
    case("ukendt projekt i historik fanges", lambda: expect_error(_with_history(mutated(), {"project": "spøgelse"}), "findes ikke"))
    case("manglende grundlag fanges", lambda: expect_error(_with_history(mutated(), {"basis": ""}), "basis"))

    def marker_missing() -> None:
        broken = doc_template.replace(MARKERS["log"][0], "")
        try:
            extract_region(broken, "log")
        except Guard:
            return
        raise AssertionError("manglende markør blev ikke fanget")

    def marker_duplicate() -> None:
        broken = doc_template + MARKERS["daglig"][0]
        try:
            extract_region(broken, "daglig")
        except Guard:
            return
        raise AssertionError("dupleret markør blev ikke fanget")

    def roundtrip_preserves_text() -> None:
        computed = compute(base)
        regions = render_regions(base, computed, today)
        rendered = render_document(doc_template, regions)
        before = doc_template.split(MARKERS["daglig"][0])[0]
        assert rendered.startswith(before), "manuel tekst før første markør blev ændret"
        assert doc_template.count("## 2. Log") == rendered.count("## 2. Log"), "manuel overskrift forsvandt"

    def tampered_document_caught() -> None:
        with tempfile.TemporaryDirectory() as tmp:
            path = Path(tmp) / "doc.md"
            computed = compute(base)
            regions = render_regions(base, computed, today)
            rendered = render_document(doc_template, regions)
            stale = dict(regions)
            stale["log"] = "\n| manipuleret |\n"
            try:
                write_document(path, rendered, stale, Path(tmp) / "backup")
            except Guard:
                assert not path.exists() or "manipuleret" not in path.read_text(encoding="utf-8"), (
                    "filen blev ikke rullet tilbage"
                )
                return
            raise AssertionError("manipuleret indhold blev ikke fanget af verifikationen")

    def broken_json_caught() -> None:
        with tempfile.TemporaryDirectory() as tmp:
            path = Path(tmp) / "state.json"
            path.write_text("{ ikke json", encoding="utf-8")
            try:
                load_json(path)
            except Guard:
                return
            raise AssertionError("ugyldig JSON blev ikke fanget")

    def end_to_end() -> None:
        with tempfile.TemporaryDirectory() as tmp:
            state_path = Path(tmp) / "projektstatus.json"
            doc_path = Path(tmp) / "projektstatus.md"
            backup_dir = Path(tmp) / "backup"
            state_path.write_text(json.dumps(base, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")
            doc_path.write_text(doc_template, encoding="utf-8")
            code = cmd_update(state_path, doc_path, today, use_lock=False, backup_dir=backup_dir)
            assert code == 0, f"--update returnerede {code}"
            first = doc_path.read_text(encoding="utf-8")
            code = cmd_update(state_path, doc_path, today, use_lock=False, backup_dir=backup_dir)
            assert code == 0 and doc_path.read_text(encoding="utf-8") == first, "--update er ikke idempotent"
            assert cmd_check(state_path, doc_path, today) == 0, "--check fejlede efter --update"

    case("manglende markør fanges", marker_missing)
    case("dupleret markør fanges", marker_duplicate)
    case("rendering bevarer manuel tekst", roundtrip_preserves_text)
    case("manipuleret dokument rulles tilbage", tampered_document_caught)
    case("ugyldig JSON fanges", broken_json_caught)
    def brief_roundtrip() -> None:
        with tempfile.TemporaryDirectory() as tmp:
            brief_dir = Path(tmp)
            path = brief_dir / f"{today}.md"
            path.write_text("# Daily Brief — test\n\nEksisterende tekst.\n", encoding="utf-8")
            assert cmd_brief(STATE_PATH, brief_dir, today) == 0, "brief-skrivning fejlede"
            first = path.read_text(encoding="utf-8")
            assert "Eksisterende tekst." in first, "eksisterende brief-tekst blev overskrevet"
            assert cmd_brief(STATE_PATH, brief_dir, today) == 0, "anden brief-kørsel fejlede"
            assert path.read_text(encoding="utf-8") == first, "brief-skrivning er ikke idempotent"
            broken = first.replace(BRIEF_MARKERS[1], "")
            path.write_text(broken, encoding="utf-8")
            assert cmd_brief(STATE_PATH, brief_dir, today) == 1, "ubalancerede brief-markører blev ikke afvist"

    case("end-to-end: update er idempotent og checker bagefter", end_to_end)
    case("brief: bevarer tekst, er idempotent og afviser ubalancerede markører", brief_roundtrip)

    failed = [r for r in results if not r[1]]
    for name, ok, note in results:
        print(f"  {'PASS' if ok else 'FAIL'}  {name}" + (f" — {note}" if note else ""))
    if failed:
        print(f"[FEJL] {len(failed)} af {len(results)} værn fejlede — installation må ikke fortsætte.", file=sys.stderr)
        return 1
    print(f"[OK] alle {len(results)} værn bestået")
    return 0


def assert_no_errors(errors: list[str]) -> None:
    assert not errors, f"forventede ingen fejl, fik: {errors}"


def _with_module(state: dict, project_id: str, module_id: str, patch: dict, project_patch: dict | None = None) -> dict:
    for project in state["projects"]:
        if project["id"] == project_id:
            if project_patch:
                project.update(project_patch)
            for module in project["modules"]:
                if module["id"] == module_id:
                    module.update(patch)
                    return state
    raise AssertionError(f"fixture fandt ikke {project_id}/{module_id}")


def _with_history(state: dict, patch: dict) -> dict:
    row = {
        "date": "2026-11-01",
        "project": state["projects"][0]["name"],
        "module": "test",
        "before": 1.0,
        "after": 2.0,
        "delta": 1.0,
        "basis": "1 af 2",
    }
    row.update(patch)
    state.setdefault("history", []).append(row)
    return state


def _duplicate_project(state: dict) -> dict:
    clone = copy.deepcopy(state["projects"][0])
    clone["name"] = clone["name"] + " (kopi)"
    state["projects"].append(clone)
    return state


def _only_recurring(state: dict) -> dict:
    project = state["projects"][2]
    project["modules"] = [m for m in project["modules"] if m.get("recurring")]
    return state


# --------------------------------------------------------------------------- cli


def main(argv: list[str] | None = None) -> int:
    parser = argparse.ArgumentParser(description="Automatisk fremskridtsprocent for steamgears-site")
    group = parser.add_mutually_exclusive_group(required=True)
    group.add_argument("--selftest", action="store_true", help="test værnene på fixtures (før installation)")
    group.add_argument("--check", action="store_true", help="valider og tjek for drift, skriv intet")
    group.add_argument("--dry-run", action="store_true", help="vis hvad --update ville gøre")
    group.add_argument("--update", action="store_true", help="beregn, skriv og verificer")
    group.add_argument("--brief", action="store_true", help="skriv dagens status ind i dagens daily brief")
    parser.add_argument("--today", default=None, help="ISO-dato der bruges som 'i dag' (til test)")
    args = parser.parse_args(argv)

    try:
        if args.selftest:
            return cmd_selftest(args.today)
        if args.check:
            return cmd_check(today=args.today)
        if args.dry_run:
            return cmd_update(today=args.today, dry_run=True)
        if args.brief:
            return cmd_brief(today=args.today)
        return cmd_update(today=args.today)
    except Guard as exc:
        print(f"[FEJL] {exc}", file=sys.stderr)
        return 1
    except Exception as exc:  # intern fejl skal aldrig se ud som en valideringsfejl
        print(f"[FEJL] intern fejl: {type(exc).__name__}: {exc}", file=sys.stderr)
        return 2


if __name__ == "__main__":
    sys.exit(main())
