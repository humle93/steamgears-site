#!/usr/bin/env python3
"""Værn: hver ``run:``-blok i workflows skal være gyldig bash (S5.12).

Fundet 15/11 2026: en ``::warning``-linje i ``backup-doelse.yml`` manglede en afsluttende
citation, fordi der blev lukket en ``$(...)`` med python indefra en dobbeltciteret streng.
Bash svarede ``unexpected EOF while looking for matching `"`` og jobbet døde med exit 2 —
men først efter pushet, som en rød kørsel uden andet signal end en annotation.

``bash -n`` fanger den slags uden at køre noget. Værnet udtrækker hver ``run:``-blok
(både ``run: |`` og én-linjes ``run:``) og kører ``bash -n`` på den. Det fejler også hvis
der slet ikke findes blokke, så et ændret YAML-layout ikke kan gøre værnèt tandløst.

Brug::

    python3 tools/workflow_bash_check.py            # alle .github/workflows/*.yml
    python3 tools/workflow_bash_check.py --verbose
"""

from __future__ import annotations

import argparse
import pathlib
import re
import subprocess
import sys
import tempfile

ROOT = pathlib.Path(__file__).resolve().parent.parent
WORKFLOWS = ROOT / ".github" / "workflows"
MINIMUM_BLOCKS = 5   # et loft over "vi fandt ingenting, så alt er fint"


def blocks(text: str) -> list[tuple[int, str]]:
    """(linjenummer, script) for hver ``run:``-blok, med YAML-indrykningen fjernet."""
    found: list[tuple[int, str]] = []
    lines = text.splitlines()
    for index, line in enumerate(lines):
        match = re.match(r"^(\s*)(?:- )?run:\s*(\||>)?\s*(.*)$", line)
        if not match:
            continue
        indent = len(match.group(1))
        block, inline = match.group(2), match.group(3)
        if block:
            body: list[str] = []
            for follow in lines[index + 1:]:
                if follow.strip() and len(follow) - len(follow.lstrip()) <= indent:
                    break
                body.append(follow[indent + 2:] if follow.strip() else "")
            found.append((index + 1, "\n".join(body) + "\n"))
        elif inline and not inline.startswith("#"):
            found.append((index + 1, inline + "\n"))
    return found


def check(path: pathlib.Path, verbose: bool = False) -> list[str]:
    problems = []
    for number, script in blocks(path.read_text(encoding="utf-8")):
        with tempfile.NamedTemporaryFile("w", suffix=".sh", delete=False) as handle:
            handle.write(script)
            name = handle.name
        done = subprocess.run(["bash", "-n", name], capture_output=True, text=True)
        if done.returncode != 0:
            detail = done.stderr.strip().splitlines()
            problems.append(f"{path.name}:{number}: {detail[0] if detail else 'bash -n fejlede'}")
        elif verbose:
            print(f"  OK   {path.name}:{number}")
        pathlib.Path(name).unlink()
    return problems


def main() -> int:
    ap = argparse.ArgumentParser(description="bash -n på alle workflow-run-blokke")
    ap.add_argument("--verbose", action="store_true")
    args = ap.parse_args()

    files = sorted(WORKFLOWS.glob("*.yml"))
    if not files:
        print("FEJL: ingen workflow-filer fundet")
        return 1

    total = 0
    problems: list[str] = []
    for path in files:
        count = len(blocks(path.read_text(encoding="utf-8")))
        total += count
        problems += check(path, args.verbose)
        if not count:
            problems.append(f"{path.name}: ingen run-blokke fundet — udtrækket virker ikke")

    for problem in problems:
        print("FAIL", problem)
    if problems:
        return 1
    if total < MINIMUM_BLOCKS:
        print(f"FAIL: kun {total} run-blokke fundet (forventede mindst {MINIMUM_BLOCKS})")
        return 1
    print(f"OK — {total} run-blokke i {len(files)} workflows er gyldig bash")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
