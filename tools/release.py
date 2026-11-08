#!/usr/bin/env python3
"""tools/release.py — promote-gaten: preflight, tag og beskyttelsesrapport.

S5.9 i roadmap'en kræver fire ting: branch protection, et manuelt promote-trin,
et release-tag og en skrevet rollback-rutine. Tre af dem kan bo i repoet. Den
fjerde — GitHubs egen beskyttelse — kan **ikke** slås til her: repoet er privat
på Free-planen, hvor beskyttede branches og environment-regler kræver en
betalt plan. Derfor gør værktøjet to ting: det håndhæver reglerne lokalt, og det
skriver præcis hvilke indstillinger der mangler den dag planen tillader dem.

    python3 tools/release.py --check          # preflight, exit 1 ved fejl
    python3 tools/release.py --check --guards # + projektstatus og fejlscan
    python3 tools/release.py --notes          # udgivelsesnoter siden sidste tag
    python3 tools/release.py --tag --confirm 7.65.1
    python3 tools/release.py --protection     # hvad der er slået til,og hvad der mangler
    python3 tools/release.py --install-hook   # pre-push-hook i .git/hooks/

Reglen om at alt arbejde ender på `main` (AGENTS.md §5) står ved magt: gaten
forbyder ikke pushes til main, den forbyder pushes der ikke kan stå distancen —
ucommittede ændringer, en zip der ikke matcher headeren, et tag der allerede
findes, eller et rødt værn.
"""

from __future__ import annotations

import argparse
import json
import re
import subprocess
import sys
from dataclasses import dataclass, field
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
PLUGIN_MAIN = "plugins/steamgears-autopilot/steamgears-autopilot.php"
ZIP_GLOB = "plugins/steamgears-autopilot-v*.zip"
HOOK = ".git/hooks/pre-push"

VERSION_RE = re.compile(r"^\s*\*?\s*Version:\s*([0-9][0-9A-Za-z.\-+]*)", re.MULTILINE)
ZIP_RE = re.compile(r"steamgears-autopilot-v([0-9][0-9A-Za-z.\-+]*)\.zip$")

#: Indstillinger der skal slås til i GitHub, når planen tillader det.
#: Rækkefølgen er den rækkefølge de skal sættes i.
PROTECTION_SETTINGS = [
    ("Branch protection på `main`", "Require a pull request before merging", "Pro/Team for private repos"),
    ("Branch protection på `main`", "Require status checks: `commit-vaern`, `plugin-ci`", "Pro/Team for private repos"),
    ("Branch protection på `main`", "Do not allow bypassing the above settings", "Pro/Team for private repos"),
    ("Tags", "Beskyttelse af `v*`: ingen sletning, intet tvunget flyt", "Pro/Team for private repos"),
    ("Environments", "`production` med required reviewer", "Free kun for offentlige repos"),
]


@dataclass
class State:
    """Det preflighten kigger på. Adskilt fra git, så den kan prøves."""

    header_version: str | None
    zip_versions: list[str]
    tags: list[str]
    dirty: bool
    branch: str
    ahead: int = 0
    behind: int = 0
    confirm: str | None = None


@dataclass
class Report:
    problems: list[str] = field(default_factory=list)
    warnings: list[str] = field(default_factory=list)

    @property
    def ok(self) -> bool:
        return not self.problems


# --------------------------------------------------------------------- regler


def parse_header_version(php: str) -> str | None:
    """Læs `Version:` fra plugin-headerne. Første forekomst vinder."""
    match = VERSION_RE.search(php)
    return match.group(1) if match else None


def zip_version(path: str) -> str | None:
    match = ZIP_RE.search(str(path))
    return match.group(1) if match else None


def tag_name(version: str) -> str:
    return f"v{version}"


def check_state(state: State, require_main: bool = False) -> Report:
    """Preflighten som ren funktion: alle regler, ingen git."""
    report = Report()

    if state.header_version is None:
        report.problems.append(f"kunne ikke læse Version: i {PLUGIN_MAIN}")

    if not state.zip_versions:
        report.problems.append("der ligger ingen plugin-zip i plugins/ — reglen kræver præcis én")
    elif len(state.zip_versions) > 1:
        report.problems.append(
            "der ligger %d zips i plugins/ (%s) — plugin-ci fejler på mere end én"
            % (len(state.zip_versions), ", ".join(sorted(state.zip_versions)))
        )

    if state.header_version and state.zip_versions:
        wrong = [v for v in state.zip_versions if v != state.header_version]
        if wrong:
            report.problems.append(
                f"zip {', '.join(sorted(wrong))} matcher ikke headerens {state.header_version}"
            )

    if state.dirty:
        report.problems.append("arbejdstræet er ikke rent — commit eller smid væk før et release")

    # Grenen er kun et krav ved et release-tag. Ellers ville preflighten blokere det
    # normale arbejde: agenten committer på en sidegren og pusher den til main.
    if state.branch != "main":
        note = f"HEAD står på '{state.branch}', ikke 'main'"
        if require_main:
            report.problems.append(note + " — et tag må kun sættes på main")
        else:
            report.warnings.append(note + " (kun et krav ved --tag)")

    if state.behind:
        report.problems.append(f"main er {state.behind} commit(s) bagud i forhold til origin/main")

    if state.header_version:
        tag = tag_name(state.header_version)
        if tag in state.tags:
            report.problems.append(f"tagget {tag} findes allerede — versionér op i stedet for at genbruge det")

    if state.confirm is not None and state.header_version is not None:
        if state.confirm != state.header_version:
            report.problems.append(
                f"--confirm {state.confirm} matcher ikke versionen {state.header_version}"
            )

    if state.ahead:
        report.warnings.append(
            f"{state.ahead} commit(s) på main er ikke pushet endnu — tag først, når de er på origin"
        )

    return report


def release_notes(commit_lines: list[str]) -> str:
    """Lav udgivelsesnoter af `git log --oneline`. Bot- og merge-commits springes over."""
    bullets = []
    for line in commit_lines:
        line = line.strip()
        if not line:
            continue
        subject = line.split(" ", 1)[1] if " " in line else line
        # Emne-linjen bærer nogle gange hele %-blokken efter " Modul:" — noterne skal
        # kun have overskriften, ellers gentager udgivelsesnoten commit-beskeden.
        subject = re.split(r"\s+(?:Modul|Før|Referat):", subject, maxsplit=1)[0].strip()
        low = subject.lower()
        if low.startswith(("merge ", "revert ", "generated with codebuff", "genereret af codebuff")):
            continue
        if subject.startswith(("🤖", "Co-Authored-By")):
            continue
        bullets.append(f"- {subject}")
    return "\n".join(bullets) if bullets else "- Ingen ændringer siden sidste tag."


def protection_report(can_enable: bool) -> tuple[list[str], list[str]]:
    """Hvad der kan slås til nu, og hvad der venter på en plan."""
    slaaet_til: list[str] = []
    mangler = [f"{where}: **{what}** ({plan})" for where, what, plan in PROTECTION_SETTINGS]
    if can_enable:
        slaaet_til.append("GitHub tillader beskyttelse — indstillingerne ovenfor kan sættes nu")
    return slaaet_til, mangler


# ----------------------------------------------------------------------- git


def git(*args: str) -> str:
    result = subprocess.run(
        ["git", *args], cwd=ROOT, capture_output=True, text=True, check=False
    )
    return result.stdout.strip()


def collect_state(confirm: str | None = None) -> State:
    php_path = ROOT / PLUGIN_MAIN
    header = parse_header_version(php_path.read_text(encoding="utf-8")) if php_path.exists() else None
    zips = [zip_version(p.name) for p in sorted(ROOT.glob(ZIP_GLOB))]
    ahead = behind = 0
    counts = git("rev-list", "--left-right", "--count", "origin/main...HEAD")
    if counts and "\t" in counts:
        behind_s, ahead_s = counts.split("\t")[:2]
        behind, ahead = int(behind_s), int(ahead_s)
    return State(
        header_version=header,
        zip_versions=[z for z in zips if z],
        tags=git("tag", "--list").splitlines(),
        dirty=bool(git("status", "--porcelain")),
        branch=git("rev-parse", "--abbrev-ref", "HEAD") or "?",
        ahead=ahead,
        behind=behind,
        confirm=confirm,
    )


def run_guard(*command: str) -> str | None:
    """Kør et værn og returnér fejlteksten, eller None hvis det er grønt."""
    result = subprocess.run(list(command), cwd=ROOT, capture_output=True, text=True, check=False)
    if result.returncode == 0:
        return None
    tail = (result.stdout + result.stderr).strip().splitlines()[-3:]
    return f"{' '.join(command)} fejlede: " + " / ".join(tail)


# ---------------------------------------------------------------------- CLI


def cmd_check(args: argparse.Namespace) -> int:
    report = check_state(collect_state(confirm=args.confirm), require_main=args.require_main)

    if args.guards:
        for command in (("python3", "projektstatus.py", "--check"), ("python3", "tools/fejlscan.py")):
            problem = run_guard(*command)
            if problem:
                report.problems.append(problem)

    if args.json:
        print(json.dumps({"ok": report.ok, "problems": report.problems,
                          "warnings": report.warnings}, ensure_ascii=False, indent=2))
    elif not args.quiet:
        print("Preflight for release")
        for warning in report.warnings:
            print(f"  ! {warning}")
        if report.ok:
            print("  ✓ alle regler holder")
        for problem in report.problems:
            print(f"  ✗ {problem}")

    return 0 if report.ok else 1


def cmd_notes(args: argparse.Namespace) -> int:
    tags = git("tag", "--list", "--sort=-v:refname").splitlines()
    span = f"{tags[0]}..HEAD" if tags else "-30"
    lines = git("log", "--oneline", span).splitlines()
    print(release_notes(lines))
    return 0


def cmd_tag(args: argparse.Namespace) -> int:
    state = collect_state(confirm=args.confirm)
    report = check_state(state, require_main=True)
    if not report.ok:
        for problem in report.problems:
            print(f"  ✗ {problem}", file=sys.stderr)
        return 1

    tag = tag_name(state.header_version or "")
    notes = release_notes(git("log", "--oneline", "-20").splitlines())
    message = f"Release {state.header_version}\n\n{notes}\n"
    result = subprocess.run(
        ["git", "tag", "--annotate", tag, "--message", message],
        cwd=ROOT, capture_output=True, text=True, check=False,
    )
    if result.returncode != 0:
        print(result.stderr.strip(), file=sys.stderr)
        return 1

    print(f"  ✓ tagget {tag} er oprettet lokalt")
    print(f"  → push det: git push origin {tag}")
    print("  → derefter: gh release create %s --notes-file <fil> (eller workflowen Release)" % tag)
    return 0


def cmd_protection(args: argparse.Namespace) -> int:
    authenticated = subprocess.run(
        ["gh", "auth", "status"], capture_output=True, text=True, check=False
    ).returncode == 0
    slaaet_til, mangler = protection_report(authenticated)

    if args.json:
        print(json.dumps({"kan_slaas_til": authenticated, "slaaet_til": slaaet_til,
                          "mangler": mangler}, ensure_ascii=False, indent=2))
        return 0

    print("Branch protection og release-gate")
    for line in slaaet_til:
        print(f"  ✓ {line}")
    if not authenticated:
        print("  ! gh er ikke logget ind her, så GitHubs indstillinger kan ikke læses eller sættes")
    print("\n  Mangler (kræver ejerens GitHub-indstillinger):")
    for line in mangler:
        print(f"    - {line}")
    print("\n  Indtil da håndhæves reglerne af `tools/release.py --check` og pre-push-hooken.")
    return 0


def hooks_dir() -> Path:
    """Find hooks-mappen via git selv.

    I en worktree er `.git` en fil med en sti til den rigtige git-mappe, så
    `.git/hooks` findes ikke som mappe. `git rev-parse --git-path hooks` svarer
    med det rigtige sted i begge tilfælde.
    """
    result = subprocess.run(
        ["git", "rev-parse", "--git-path", "hooks"],
        cwd=ROOT, capture_output=True, text=True, check=False,
    )
    path = Path(result.stdout.strip() or ".git/hooks")
    return path if path.is_absolute() else (ROOT / path)


def cmd_install_hook(args: argparse.Namespace) -> int:
    hook = hooks_dir() / "pre-push"
    hook.parent.mkdir(parents=True, exist_ok=True)
    hook.write_text(
        "#!/usr/bin/env bash\n"
        "# pre-push — installeret af tools/release.py --install-hook\n"
        "#\n"
        "# Free-planen kan ikke beskytte main på et privat repo, så gaten ligger her:\n"
        "# et push afvises hvis version, zip, tag eller værn ikke holder.\n"
        "set -euo pipefail\n"
        "cd \"$(git rev-parse --show-toplevel)\"\n"
        "exec python3 tools/release.py --check --guards\n",
        encoding="utf-8",
    )
    hook.chmod(0o755)
    print(f"  ✓ {hook} installeret")
    print("    (hooken gælder denne klon. I en delt sandkasse ligger .git uden for worktreen —")
    print("     rør ikke den delte hooks-mappe; CI kører samme preflight i promote-vaern.yml)")
    return 0


def main() -> int:
    parser = argparse.ArgumentParser(description="Promote-gate og release-tag.")
    parser.add_argument("--check", action="store_true", help="kør preflight")
    parser.add_argument("--guards", action="store_true", help="kør også projektstatus og fejlscan")
    parser.add_argument("--confirm", default=None, help="version der skal bekræftes ved tag")
    parser.add_argument("--tag", action="store_true", help="opret annotated tag for versionen")
    parser.add_argument("--notes", action="store_true", help="udgivelsesnoter")
    parser.add_argument("--protection", action="store_true", help="vis beskyttelsesstatus")
    parser.add_argument("--install-hook", action="store_true", help="installér pre-push-hook")
    parser.add_argument("--require-main", action="store_true",
                        help="kræv at HEAD står på main (bruges af --tag)")
    parser.add_argument("--json", action="store_true")
    parser.add_argument("--quiet", action="store_true")
    args = parser.parse_args()

    if not any((args.check, args.tag, args.notes, args.protection, args.install_hook)):
        parser.print_help()
        return 2

    if args.check:
        return cmd_check(args)
    if args.tag:
        return cmd_tag(args)
    if args.notes:
        return cmd_notes(args)
    if args.protection:
        return cmd_protection(args)
    return cmd_install_hook(args)


if __name__ == "__main__":
    sys.exit(main())
