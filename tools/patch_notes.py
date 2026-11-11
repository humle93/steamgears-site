#!/usr/bin/env python3
"""tools/patch_notes.py — patch notes som en del af versionen.

Reglen (AGENTS.md §7.4): hver gang plugin'et får et nyt versionsnummer, skal der
ligge en note i plugins/steamgears-autopilot/CHANGELOG.md der siger hvad der blev
tilføjet, ændret eller rettet — og noten skal med i svaret til ejeren.

    python3 tools/patch_notes.py --check         # grøn = hver version har sin note
    python3 tools/patch_notes.py --latest        # nyeste note, klar til svaret
    python3 tools/patch_notes.py --version 7.70.0
    python3 tools/patch_notes.py --selftest      # fixtures der skal fanges

Exitkoder: 0 = alt grønt, 1 = fund, 2 = intern fejl.
"""

from __future__ import annotations

import argparse
import json
import re
import subprocess
import sys
from dataclasses import dataclass
from datetime import date
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
PLUGIN = "steamgears-autopilot"
MAIN = ROOT / "plugins" / PLUGIN / f"{PLUGIN}.php"
CHANGELOG = ROOT / "plugins" / PLUGIN / "CHANGELOG.md"

HEADING = re.compile(r"^##\s+(\d+\.\d+\.\d+)\s+[\u2014\u2013-]\s+(\d{4}-\d{2}-\d{2})\s*$")
BASELINE = re.compile(r"<!--\s*patch-notes:\s*baseline\s+(\d+\.\d+\.\d+)\s*-->")
CONSTANT = re.compile(r"define\(\s*'SGAP_VERSION'\s*,\s*'([^']+)'")
HEADER = re.compile(r"^\s*\*?\s*Version:\s*(\S+)", re.MULTILINE)
ZIP = re.compile(PLUGIN + r"-v(\d+\.\d+\.\d+)\.zip$")
ADDED_VERSION = re.compile(r"^\+\s*define\(\s*'SGAP_VERSION'\s*,\s*'([^']+)'")


@dataclass
class Entry:
    version: str
    day: str
    body: str


def key(version: str) -> tuple[int, ...]:
    return tuple(int(part) for part in version.split("."))


def parse(text: str) -> tuple[str | None, list[Entry]]:
    """Del CHANGELOG.md op i baseline-markør og versioner."""
    marker = BASELINE.search(text)
    baseline = marker.group(1) if marker else None
    entries: list[Entry] = []
    current: tuple[str, str, list[str]] | None = None
    for line in text.splitlines():
        heading = HEADING.match(line)
        if heading:
            if current:
                entries.append(Entry(current[0], current[1], "\n".join(current[2]).strip()))
            current = (heading.group(1), heading.group(2), [])
        elif current is not None:
            current[2].append(line)
    if current:
        entries.append(Entry(current[0], current[1], "\n".join(current[2]).strip()))
    return baseline, entries


def analyse(text: str, current: str | None, bumps: list[str], today: date) -> list[str]:
    """Alle regler som ren funktion, så de kan prøves uden git og uden filer."""
    problems: list[str] = []
    baseline, entries = parse(text)

    if baseline is None:
        problems.append("CHANGELOG.md mangler markøren <!-- patch-notes: baseline X.Y.Z -->")
    if not entries:
        problems.append("CHANGELOG.md har ingen versioner (forventet '## X.Y.Z — ÅÅÅÅ-MM-DD')")
        return problems

    if current is None:
        problems.append("kunne ikke læse SGAP_VERSION i plugin-filen")
    elif entries[0].version != current:
        problems.append(
            f"nyeste note er {entries[0].version}, men pluginnet er {current} — skriv noten for {current} øverst"
        )

    seen: set[str] = set()
    for index, entry in enumerate(entries):
        if entry.version in seen:
            problems.append(f"{entry.version} står to gange i CHANGELOG.md")
        seen.add(entry.version)
        try:
            day = date.fromisoformat(entry.day)
        except ValueError:
            problems.append(f"{entry.version}: '{entry.day}' er ikke en gyldig dato")
        else:
            if day > today:
                problems.append(f"{entry.version}: datoen {entry.day} ligger i fremtiden")
        if not any(line.lstrip().startswith(("- ", "* ")) for line in entry.body.splitlines()):
            problems.append(f"{entry.version}: noten har ingen punkter — skriv hvad der blev ændret")
        if index and key(entry.version) >= key(entries[index - 1].version):
            problems.append(
                f"{entry.version} står under {entries[index - 1].version}; nyeste skal øverst"
            )

    known = {entry.version for entry in entries}
    if baseline and current:
        for version in bumps:
            if key(version) > key(baseline) and key(version) <= key(current) and version not in known:
                problems.append(f"{version} findes i git-historikken men mangler en note i CHANGELOG.md")
    return problems


def git(*args: str) -> str:
    result = subprocess.run(["git", *args], cwd=ROOT, capture_output=True, text=True, check=False)
    return result.stdout if result.returncode == 0 else ""


def git_bumps() -> list[str]:
    """Versionerne i den rækkefølge de blev sat, læst fra diffene i git-historikken."""
    rel = MAIN.relative_to(ROOT).as_posix()
    log = git("log", "--reverse", "-p", "--unified=0", "--no-color", "--format=@@%H", "--", rel)
    version = None
    bumps: list[str] = []
    for line in log.splitlines():
        match = ADDED_VERSION.match(line)
        if match and match.group(1) != version:
            version = match.group(1)
            bumps.append(version)
    return bumps


def current_version() -> tuple[str | None, list[str]]:
    """Versionen fra headeren, og de problemer der gør tallet usikkert."""
    problems: list[str] = []
    source = MAIN.read_text(encoding="utf-8") if MAIN.is_file() else ""
    constant = CONSTANT.search(source)
    header = HEADER.search(source)
    zips = sorted((ROOT / "plugins").glob(f"{PLUGIN}-v*.zip"))
    packed = [m.group(1) for name in (z.name for z in zips) if (m := ZIP.match(name))]

    if not constant:
        problems.append("SGAP_VERSION er ikke defineret i plugin-filen")
    if not header:
        problems.append("plugin-headeren har ingen Version:-linje")
    if len(zips) != 1:
        problems.append(f"{len(zips)} zips i plugins/ (forventet præcis 1)")
    if constant and header and constant.group(1) != header.group(1):
        problems.append(f"SGAP_VERSION {constant.group(1)} mod header {header.group(1)}")
    if constant and packed and packed[0] != constant.group(1):
        problems.append(f"zip hedder {packed[0]}, men versionen er {constant.group(1)}")
    return (constant.group(1) if constant else None), problems


def report(problems: list[str], as_json: bool = False) -> int:
    if as_json:
        print(json.dumps({"ok": not problems, "problems": problems}, ensure_ascii=False, indent=2))
    elif problems:
        print("Patch notes")
        for problem in problems:
            print(f"  ✗ {problem}")
        print("\nRet CHANGELOG.md (eller versionsnummeret), og kør igen.")
    else:
        print("Patch notes: hver version har sin note, og den nyeste matcher plugin'et.")
    return 0 if not problems else 1


def cmd_check(as_json: bool) -> int:
    version, problems = current_version()
    text = CHANGELOG.read_text(encoding="utf-8") if CHANGELOG.is_file() else ""
    if not CHANGELOG.is_file():
        problems.append("plugins/steamgears-autopilot/CHANGELOG.md findes ikke")
    problems.extend(analyse(text, version, git_bumps(), date.today()))
    return report(problems, as_json)


def cmd_latest() -> int:
    if not CHANGELOG.is_file():
        print("CHANGELOG.md findes ikke", file=sys.stderr)
        return 1
    _, entries = parse(CHANGELOG.read_text(encoding="utf-8"))
    if not entries:
        print("CHANGELOG.md har ingen versioner", file=sys.stderr)
        return 1
    entry = entries[0]
    print(f"## {entry.version} — {entry.day}\n\n{entry.body}")
    return 0


def cmd_version(version: str) -> int:
    _, entries = parse(CHANGELOG.read_text(encoding="utf-8")) if CHANGELOG.is_file() else (None, [])
    for entry in entries:
        if entry.version == version:
            print(f"## {entry.version} — {entry.day}\n\n{entry.body}")
            return 0
    print(f"ingen note for {version}", file=sys.stderr)
    return 1


def sample(entries: list[tuple[str, str, str]], baseline: str = "7.65.0") -> str:
    text = "# Patch notes\n\n<!-- patch-notes: baseline %s -->\n\n" % baseline
    for version, day, body in entries:
        text += f"## {version} — {day}\n\n{body}\n\n"
    return text


def selftest() -> int:
    today = date(2026, 11, 11)
    note = "**Kort.**\n\n### Tilføjet\n- noget nyt\n"
    bumps = ["7.65.0", "7.65.1", "7.71.0", "7.71.1"]
    cases: list[tuple[str, str, str | None, list[str], str | None]] = [
        ("ren kasse", sample([("7.71.1", "2026-11-11", note), ("7.71.0", "2026-11-10", note),
                              ("7.65.1", "2026-11-08", note), ("7.65.0", "2026-11-08", note)]),
         "7.71.1", bumps, None),
        ("nyeste note mangler", sample([("7.71.0", "2026-11-10", note)]), "7.71.1", bumps, "nyeste note"),
        ("hul i historikken", sample([("7.71.1", "2026-11-11", note)]), "7.71.1", bumps, "mangler en note"),
        ("forkert rækkefølge", sample([("7.71.1", "2026-11-11", note), ("7.71.0", "2026-11-10", note),
                                       ("7.72.0", "2026-11-09", note)]), "7.71.1", bumps, "nyeste skal øverst"),
        ("note uden punkter", sample([("7.71.1", "2026-11-11", "**Kort.**\n")]), "7.71.1", bumps,
         "ingen punkter"),
        ("dato i fremtiden", sample([("7.71.1", "2026-12-01", note)]), "7.71.1", bumps, "fremtiden"),
        ("ugyldig dato", sample([("7.71.1", "11-11-2026", note)]), "7.71.1", bumps, "ingen versioner"),
        ("baseline mangler", "# Patch notes\n\n## 7.71.1 — 2026-11-11\n\n- noget\n", "7.71.1", bumps,
         "baseline"),
        ("dublet", sample([("7.71.1", "2026-11-11", note), ("7.71.1", "2026-11-10", note)]), "7.71.1",
         bumps, "to gange"),
    ]

    failures = 0
    for name, text, current, bumps_for_case, expected in cases:
        problems = analyse(text, current, bumps_for_case, today)
        joined = " | ".join(problems)
        if expected is None and not problems:
            print(f"  ✓ {name} — ingen fund")
        elif expected is not None and expected in joined:
            print(f"  ✓ {name} — fanget som '{expected}'")
        else:
            print(f"  ✗ {name} — forventede '{expected}', fik: {joined or 'intet'}")
            failures += 1

    print()
    if failures:
        print(f"{failures} fixture(s) blev ikke fanget.")
        return 1
    print("Alle fixtures fanget — og den rene kasse er grøn.")
    return 0


def main() -> int:
    parser = argparse.ArgumentParser(description="Patch notes pr. plugin-version.")
    parser.add_argument("--check", action="store_true", help="kør værnene")
    parser.add_argument("--latest", action="store_true", help="print nyeste note")
    parser.add_argument("--version", default=None, help="print noten for en version")
    parser.add_argument("--selftest", action="store_true", help="kør fixtures")
    parser.add_argument("--json", action="store_true")
    args = parser.parse_args()

    if not any((args.check, args.latest, args.version, args.selftest)):
        parser.print_help()
        return 2
    if args.selftest:
        return selftest()
    if args.latest:
        return cmd_latest()
    if args.version:
        return cmd_version(args.version)
    return cmd_check(args.json)


if __name__ == "__main__":
    sys.exit(main())
