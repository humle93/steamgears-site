#!/usr/bin/env python3
"""Vedligeholder docs/samtalepunkter.md — "samtalens vigtigste punkter".

Filen har to slags indhold:

* en AUTO-blok mellem ``<!-- AUTO:SAMTALEPUNKTER:START -->`` og ``<!-- AUTO:SAMTALEPUNKTER:END -->``,
  som dette script skriver: projekternes procent, seneste commits, åbne blokeringer og næste skridt.
  Blokken må ikke håndredigeres — ret i ``projektstatus.json`` eller i git-historikken.
* prosa uden for blokken: beslutninger, fund og næste skridt som kun den arbejdende kan skrive.
  Scriptet rører den ikke.

Brug:

    python3 tools/samtalepunkter.py --update     # skriv AUTO-blokken (atomisk, med backup)
    python3 tools/samtalepunkter.py --check      # fejl hvis blokken er bagud (drift)
    python3 tools/samtalepunkter.py --dry-run    # vis hvad --update ville skrive
    python3 tools/samtalepunkter.py --selftest   # test værnene på fixtures

Formålet er context-håndtering: når samtalen nærmer sig grænsen (klientens context meter omkring 95 %),
skal de vigtigste punkter stå i denne fil og være committet — ikke kun i chat-historikken, som forsvinder
ved komprimering.
"""

from __future__ import annotations

import argparse
import json
import os
import shutil
import subprocess
import sys
import tempfile
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
STATUS = ROOT / "projektstatus.json"
TARGET = ROOT / "docs" / "samtalepunkter.md"
START = "<!-- AUTO:SAMTALEPUNKTER:START -->"
END = "<!-- AUTO:SAMTALEPUNKTER:END -->"
BACKUPS = ROOT / ".samtalepunkter-backups"
KEEP_BACKUPS = 10


# --------------------------------------------------------------------------- data

def load_status(path: Path = STATUS) -> dict:
    return json.loads(path.read_text(encoding="utf-8"))


def counted_modules(project: dict) -> list[dict]:
    """Moduler der tæller i procenten. `recurring` (fx den daglige loop) gør ikke — samme regel som
    projektstatus.py, ellers ville de to værktøjer melde to forskellige tal for samme projekt."""
    return [m for m in project.get("modules", []) if not m.get("recurring")]


def project_percent(project: dict) -> float:
    """Procent = sum(done) / sum(total) * 100, afrundet til én decimal."""
    modules = counted_modules(project)
    total = sum(float(m.get("total", 0)) for m in modules)
    done = sum(float(m.get("done", 0)) for m in modules)
    if total <= 0:
        return 0.0
    return round(done / total * 100, 1)


def project_basis(project: dict) -> str:
    """Regnestykket bag procenten, fx '13,2 af 21,8 d (S0 1,9/2,6 · S1 2,15/4,6)'."""
    modules = counted_modules(project)
    if not modules:
        return "ingen moduler"
    unit = modules[0].get("unit", "")
    done = sum(float(m.get("done", 0)) for m in modules)
    total = sum(float(m.get("total", 0)) for m in modules)
    parts = " · ".join(
        f"{m.get('id', '?').upper()} {fmt(m.get('done'))}/{fmt(m.get('total'))}" for m in modules
    )
    return f"{fmt(done)} af {fmt(total)} {unit} ({parts})"


def fmt(value) -> str:
    """12.7 -> '12,7'; 5.0 -> '5' (dansk decimal-komma, uden overflødige nuller)."""
    number = float(value)
    text = f"{number:.2f}".rstrip("0").rstrip(".")
    return text.replace(".", ",")


def recent_commits(limit: int = 6, cwd: Path = ROOT) -> list[str]:
    try:
        out = subprocess.run(
            ["git", "log", f"-n{limit}", "--pretty=format:%h %s"],
            cwd=cwd, capture_output=True, text=True, check=True,
        ).stdout
    except (subprocess.CalledProcessError, FileNotFoundError):
        return []
    return [line.strip() for line in out.splitlines() if line.strip()]


# --------------------------------------------------------------------------- render

def render_block(status: dict, commits: list[str], today: str) -> str:
    lines: list[str] = []
    lines.append(f"_Opdateret {today} af `tools/samtalepunkter.py`. Blokken er maskinelt genereret —"
                 " ret i `projektstatus.json`, ikke her._")
    lines.append("")
    lines.append("### Status")
    lines.append("")
    lines.append("| Projekt | Status | Grundlag |")
    lines.append("| --- | --- | --- |")
    for project in status.get("projects", []):
        lines.append(f"| **{project['name']}** | **{fmt(project_percent(project))} %** | {project_basis(project)} |")
    lines.append("")
    lines.append("### Åbne blokeringer")
    lines.append("")
    blocked = [p for p in status.get("projects", []) if (p.get("blocked_by") or "").strip()]
    if blocked:
        for project in blocked:
            lines.append(f"- **{project['name']}** — {project['blocked_by'].strip()}")
    else:
        lines.append("- ingen noteret blokering")
    lines.append("")
    lines.append("### Næste skridt")
    lines.append("")
    for project in status.get("projects", []):
        nxt = (project.get("next") or "").strip()
        if nxt:
            lines.append(f"- **{project['name']}** — {nxt}")
    lines.append("")
    lines.append("### Seneste commits")
    lines.append("")
    if commits:
        for line in commits:
            lines.append(f"- `{line}`")
    else:
        lines.append("- (git-historik kunne ikke læses)")
    return "\n".join(lines)


def split_document(text: str) -> tuple[str, str, str]:
    """Returnér (før, blok, efter). Fejler hvis markørerne mangler eller står i omvendt rækkefølge."""
    if START not in text or END not in text:
        raise SystemExit(f"{TARGET.name}: markørerne {START} / {END} mangler")
    head, rest = text.split(START, 1)
    block, tail = rest.split(END, 1)
    return head, block, tail


def assemble(text: str, block: str) -> str:
    head, _, tail = split_document(text)
    return f"{head}{START}\n{block}\n{END}{tail}"


# --------------------------------------------------------------------------- write

def write_atomic(path: Path, text: str) -> None:
    path.parent.mkdir(parents=True, exist_ok=True)
    fd, tmp = tempfile.mkstemp(dir=str(path.parent), prefix=".tmp-", suffix=path.suffix)
    try:
        with os.fdopen(fd, "w", encoding="utf-8") as handle:
            handle.write(text)
        os.replace(tmp, path)
    finally:
        if os.path.exists(tmp):
            os.unlink(tmp)


def rotate_backups(path: Path) -> None:
    if not path.exists():
        return
    BACKUPS.mkdir(exist_ok=True)
    stamp = path.stat().st_mtime_ns
    shutil.copy2(path, BACKUPS / f"{path.stem}-{stamp}{path.suffix}")
    files = sorted(BACKUPS.glob(f"{path.stem}-*{path.suffix}"), key=lambda p: p.stat().st_mtime_ns)
    for old in files[:-KEEP_BACKUPS]:
        old.unlink(missing_ok=True)


def update(today: str, dry_run: bool = False) -> int:
    status = load_status()
    block = render_block(status, recent_commits(), today)
    if not TARGET.exists():
        raise SystemExit(f"{TARGET} findes ikke — opret den med markørerne først")
    old = TARGET.read_text(encoding="utf-8")
    new = assemble(old, block)
    if new == old:
        print("[OK] AUTO-blokken er allerede opdateret")
        return 0
    if dry_run:
        print("[dry-run] ville skrive:\n")
        print(block)
        return 0
    rotate_backups(TARGET)
    write_atomic(TARGET, new)
    written = TARGET.read_text(encoding="utf-8")
    if assemble(written, render_block(load_status(), recent_commits(), today)) != written:
        write_atomic(TARGET, old)
        raise SystemExit("[FEJL] skrivningen kunne ikke læses tilbage — den forrige version er gendannet")
    print(f"[OK] skrev {TARGET.relative_to(ROOT)}")
    return 0


def check(today: str) -> int:
    if not TARGET.exists():
        print(f"[FEJL] {TARGET.name} findes ikke")
        return 1
    text = TARGET.read_text(encoding="utf-8")
    _, block, _ = split_document(text)
    expected = render_block(load_status(), recent_commits(), today)
    if block.strip() != expected.strip():
        print("[FEJL] AUTO-blokken er bagud — kør: python3 tools/samtalepunkter.py --update")
        return 1
    print(f"[OK] {TARGET.name}: AUTO-blokken matcher status og git-historik")
    return 0


# --------------------------------------------------------------------------- tests

def selftest() -> int:
    checks: list[tuple[str, bool]] = []

    def ok(name: str, condition: bool) -> None:
        checks.append((name, bool(condition)))

    demo = {
        "projects": [
            {"name": "A", "modules": [{"id": "a1", "done": 1.9, "total": 2.6, "unit": "d"},
                                      {"id": "a2", "done": 0.5, "total": 0.5, "unit": "d"}],
             "blocked_by": "venter på ejer", "next": "gør noget"},
            {"name": "B", "modules": [{"id": "b1", "done": 1, "total": 4, "unit": "guides"}],
             "blocked_by": None, "next": ""},
        ]
    }
    ok("procent = sum(done)/sum(total)", project_percent(demo["projects"][0]) == 77.4)
    ok("procent uden moduler = 0", project_percent({"modules": []}) == 0.0)
    ok("recurring-modul tæller ikke med",
       project_percent({"modules": [{"id": "a", "done": 1, "total": 1, "unit": "g"},
                                     {"id": "loop", "done": 1, "total": 1, "unit": "k", "recurring": True}]}) == 100.0)
    ok("dansk komma i grundlag", project_basis(demo["projects"][0]).startswith("2,4 af 3,1 d"))
    ok("fmt dropper nuller", fmt(5.0) == "5" and fmt(12.70) == "12,7")
    block = render_block(demo, ["abc123 Modul: test"], "2026-11-06")
    ok("blok har alle tre afsnit", all(h in block for h in ("### Status", "### Åbne blokeringer", "### Næste skridt")))
    ok("blok viser blokering", "venter på ejer" in block)
    ok("blok udelader tom next", block.count("- **B**") == 0)
    doc = "# Titel\n\n" + START + "\ngammel\n" + END + "\n\nProsa\n"
    ok("assemble bevarer prosa", assemble(doc, "ny") == "# Titel\n\n" + START + "\nny\n" + END + "\n\nProsa\n")
    try:
        split_document("uden markører")
        ok("manglende markør fejler", False)
    except SystemExit:
        ok("manglende markør fejler", True)

    failed = [name for name, passed in checks if not passed]
    for name, passed in checks:
        print(f"  {'ok' if passed else 'FEJL'}  {name}")
    print(f"{len(checks) - len(failed)}/{len(checks)} selftest-fixtures bestået")
    if failed:
        print("fejlede: " + ", ".join(failed))
        return 1
    return 0


def main() -> int:
    parser = argparse.ArgumentParser(description="Vedligehold docs/samtalepunkter.md")
    group = parser.add_mutually_exclusive_group(required=True)
    group.add_argument("--update", action="store_true")
    group.add_argument("--check", action="store_true")
    group.add_argument("--dry-run", action="store_true")
    group.add_argument("--selftest", action="store_true")
    parser.add_argument("--today", default=None, help="ISO-dato (til test)")
    args = parser.parse_args()

    if args.selftest:
        return selftest()
    from datetime import date
    today = args.today or date.today().isoformat()
    if args.check:
        return check(today)
    return update(today, dry_run=args.dry_run)


if __name__ == "__main__":
    sys.exit(main())
