#!/usr/bin/env python3
"""tools/commit_check.py — værn for commit-beskeder i steamgears-site.

AGENTS.md §1 kræver at hvert arbejdspas melder **Modul / % før / % efter / Δ / Grundlag**.
Indtil nu blev reglen holdt i hånden. Dette værn kontrollerer den maskinelt på tre flader:

  * **commit-msg-hook** — `--install` skriver `.git/hooks/commit-msg`, så en commit uden
    blokken afvises lokalt, før den findes.
  * **CI** — `--range <base>..<head>` tjekker præcis de commits der blev pushet.
  * **selftest** — `--selftest` kører fixtures, som de øvrige værn gør.

Kommandoer:

  python3 tools/commit_check.py --selftest
  python3 tools/commit_check.py --commit HEAD
  python3 tools/commit_check.py --range origin/main..HEAD
  python3 tools/commit_check.py --message-file .git/COMMIT_EDITMSG
  python3 tools/commit_check.py --install

Tallene krydstjekkes desuden mod historik-rækkerne i `projektstatus.json`: Før/Efter/Δ skal matche
en række, og Grundlag skal kunne læses ud af den rækkes `basis`. Et opdigtet regnestykke afvises.

Undtaget, fordi de ikke kan bære procenter: merge- og revert-commits samt bot-committene fra
`.github/workflows/projektstatus.yml` (`chore(status): …`), `.github/workflows/natlig-push.yml`
(`chore: natlig auto-push …`) og `.github/workflows/rebrand-apply.yml` (`chore(rebrand): …`).
De tre er maskinens egne rapport-commits: de flytter ingen procenter, så der findes ingen række at
krydstjekke imod.

Exitkoder: 0 = ok, 1 = regelbrud, 2 = intern fejl eller forkert brug.
"""

from __future__ import annotations

import argparse
import json
import re
import subprocess
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
STATE_PATH = ROOT / "projektstatus.json"
NBSP = "\u00a0"
ARROW = "\u2192"
BOLD_RE = re.compile(r"\*\*|__")
DASHES = ("—", "–", "-")
TOLERANCE = 0.05
HISTORY_TOLERANCE = 0.05

# Emner der med vilje ikke bærer procenter. Maskingenereret eller ikke-arbejde.
SKIP_SUBJECTS = (
    "merge ",
    "revert ",
    "chore(status):",
    "chore: natlig auto-push",
    "chore(rebrand):",
)

# ------------------------------------------------------------------- verificeret register

# Ældre commits i main-historikken hvis procenter ikke kan rettes bagefter: en commit er
# uforanderlig uden force-push, og det er forbudt. Hver post er efterprøvet mod
# projektstatus.json og forklaret i docs/commit-vaern-register.md.
#
# `relax` nævner PRÆCIS de regler der er efterprøvet og godkendt for den commit — ikke hele
# regelsættet. En registreret commit skal stadig have Modul/Før/Efter/Δ/Grundlag, stadig have
# et Δ der passer med Efter − Før, og stadig have et regnestykke. Nye regler gælder derfor også
# for de gamle commits, medmindre de udtrykkeligt står her.
ALLOWED_RELAX = frozenset({"basis", "history"})
RELAX_LABELS = {
    "basis": "Grundlag mod historik-rækkens basis",
    "history": "Før/Efter/Δ mod en historik-række",
}
VERIFIED_LEGACY: dict[str, dict] = {
    "1c15f939": {
        "relax": ("basis",),
        "reason": "række 96 (76,3 → 76,7 %, 22,75 af 29,65 d): Før/Efter/Δ er rækkens egne tal, men "
                  "Grundlag citerede modul-deltaet (P1 10,7 → 11,2 = 0,5 d) i stedet for totalen",
    },
    "c747a47a": {
        "relax": ("history",),
        "reason": "P1.5c-arbejdet ligger på main, men beskedens statuslinje (91,0 → 96,2 %, 27,8 af 28,9 d) "
                  "blev udregnet før det merge der satte række 110 (91,2 → 96,3 %, 28,3 af 29,4 d)",
    },    "45a11e00": {
        "relax": ("basis",),
        "reason": "P10 (Autopilot 7.72.0) blev skrevet mod 34,1 d og meldte 34,6 af 34,6 d; da grenen blev "
                  "flettet med main (7.75.0, Cirla P9.1-P9.4 = +0,95 d) blev P10-rækken 189 skrevet som "
                  "35,55 af 35,55 d. Før/Efter/Δ (100 → 100, 0) er rækkens egne; force-push var ikke tilladt",
    },
}


FIELDS = (
    ("modul", "Modul"),
    ("før", "Før"),
    ("efter", "Efter"),
    ("delta", "Δ"),
    ("grundlag", "Grundlag"),
)

FIELD_RE = re.compile(
    r"(?i)^[ \t]*(?:\*\*)?(?:%[ \t]*)?(modul|før|efter|delta|Δ|grundlag)(?:\*\*)?[ \t]*:[ \t]*(.*?)[ \t]*$"
)
DELTA_RE = re.compile(r"(?i)(?:Δ|delta)[ \t]*([+\-−]?[0-9]+(?:[.,][0-9]+)?|—|–|-)")
PCT_RE = re.compile(r"[0-9]+(?:[.,][0-9]+)?[ \t]*%?")
DELTA_VALUE_RE = re.compile(r"[+\-−]?[0-9]+(?:[.,][0-9]+)?")
BASIS_HEAD_RE = re.compile(r"^(\d+(?:\.\d+)?) af (\d+(?:\.\d+)?) ?(.*)$")
BAD_GROUNDS = ("tbd", "todo", "ved ikke", "føles", "??", "xxx")

EXAMPLE = """\
Modul: S2.3 navigation — nav-links
Før: 55,4 %  →  Efter: 56,3 %  (Δ +0,9)
Grundlag: 12,25 af 21,75 d (S0 1,9/2,6 · S1 2,15/4,6 · S2 4,65/6 · S3 2,15/3,55 · S4 1,4/5)"""

HOOK = """\
#!/usr/bin/env bash
# Skrevet af `python3 tools/commit_check.py --install`.
# Afviser commits uden Modul/Før/Efter/Δ/Grundlag — se AGENTS.md §1 og §4.
# Fjernes med `rm .git/hooks/commit-msg` (eller skriv en besked der opfylder reglen).
set -euo pipefail
root="$(git rev-parse --show-toplevel 2>/dev/null)" || exit 0
exec python3 "$root/tools/commit_check.py" --message-file "$1"
"""


class Guard(Exception):
    """Fejl der skal stoppe kørslen med en forståelig besked."""


# --------------------------------------------------------------------------- parsing


def as_number(text: str) -> float | None:
    """'55,4 %' -> 55.4, '+0,5' -> 0.5, '—' -> None (stregen betyder »ingen måling«)."""
    value = (text or "").strip().replace("%", "").replace(" ", "").replace(NBSP, "")
    value = value.replace("−", "-").lstrip("+")
    if value in ("", "—", "–", "-"):
        return None
    try:
        return float(value.replace(",", "."))
    except ValueError:
        return None


def extract_delta(text: str) -> str | None:
    match = DELTA_RE.search(text or "")
    return match.group(1) if match else None


def parse_fields(message: str) -> dict[str, str]:
    """Læs felterne linje for linje. Både `Før: 63 % → Efter: 78 % (Δ +15)` og
    `% før:`, `% efter:`, `Delta:` på hver sin linje accepteres."""
    found: dict[str, str] = {}
    for raw in (message or "").splitlines():
        line = BOLD_RE.sub("", raw).replace(NBSP, " ").strip()
        match = FIELD_RE.match(line)
        if not match:
            continue
        key = match.group(1).lower()
        if key == "δ":
            key = "delta"
        if key == "Δ":
            key = "delta"
        value = match.group(2).strip()
        if key == "før":
            head, arrow, tail = value.partition(ARROW)
            if arrow:
                value = head.strip()
                efter = re.search(r"(?i)efter[ \t]*:[ \t]*([^()]*)", tail)
                if efter:
                    found.setdefault("efter", efter.group(1).strip())
                delta = extract_delta(tail)
                if delta:
                    found.setdefault("delta", delta)
        elif key == "efter":
            delta = extract_delta(value)
            if delta:
                found.setdefault("delta", delta)
                value = re.split(r"\(", value)[0].strip()
        found.setdefault(key, value)
    return found


# --------------------------------------------------------------------------- regler


def check_pct(value: str, label: str, errors: list[str]) -> float | None:
    """Et procentfelt er enten et tal 0–100 (med valgfrit %) eller en streg."""
    text = (value or "").strip()
    if text in DASHES:
        return None
    if not PCT_RE.fullmatch(text):
        errors.append(f"'{label}' skal være et tal (fx '55,4 %') eller en streg (—), ikke '{text}'")
        return None
    number = as_number(text)
    if number is None or number < 0 or number > 100:
        errors.append(f"'{label}' skal ligge mellem 0 og 100, ikke '{text}'")
        return None
    return number


def check_delta(value: str, errors: list[str]) -> float | None:
    text = (value or "").strip()
    if text in DASHES:
        return None
    if not re.fullmatch(r"[+\-−]?[0-9]+(?:[.,][0-9]+)?", text):
        errors.append(f"'Δ' skal være et tal (fx '+0,5', '-2,4', '0') eller en streg (—), ikke '{text}'")
        return None
    return as_number(text)


def check_grounds(value: str, errors: list[str]) -> None:
    text = (value or "").strip()
    low = text.lower()
    bad = next((word for word in BAD_GROUNDS if word in low), None)
    if bad:
        errors.append(f"'Grundlag' må ikke indeholde '{bad}' — skriv regnestykket i stedet")
        return
    if low in ("ingen ændring", "igen ændring"):
        return
    if not re.search(r"\d", text):
        errors.append(
            "'Grundlag' skal kunne genberegnes: et regnestykke med tal "
            "(fx '2 af 5 faser' eller '12,45 af 21,75 d') eller 'ingen ændring'"
        )


def is_number(value) -> bool:
    return isinstance(value, (int, float)) and not isinstance(value, bool)


def normalize_basis(text: str) -> str:
    """Sammenlignelig form: NBSP, decimaler og whitespace normaliseres."""
    text = (text or "").replace(NBSP, " ")
    text = re.sub(r"(?<=\d),(?=\d)", ".", text)
    return re.sub(r"\s+", " ", text).strip().lower()


def basis_head(text: str) -> tuple[float, float, str] | None:
    """'12,25 af 21,75 d (…)' -> (12.25, 21.75, 'd')."""
    match = BASIS_HEAD_RE.match(normalize_basis(text))
    if not match:
        return None
    return float(match.group(1)), float(match.group(2)), match.group(3).strip()


def basis_problem(claim: str, row_basis: str) -> str | None:
    """None når beskedens Grundlag kan læses ud af historik-rækkens basis."""
    claim_n, row_n = normalize_basis(claim), normalize_basis(row_basis)
    if claim_n == row_n:
        return None
    claim_head, row_head = basis_head(claim), basis_head(row_basis)
    if row_head is None:
        return f"historik-rækkens grundlag '{row_basis}' har ikke formen 'X af Y enhed'"
    if claim_head is None:
        return f"'{claim}' matcher ikke rækkens '{row_basis}'"
    if claim_head[:2] != row_head[:2]:
        return (
            f"grundlagets tal ({claim_head[0]:g} af {claim_head[1]:g}) matcher ikke "
            f"rækkens ({row_head[0]:g} af {row_head[1]:g})"
        )
    claim_base = re.sub(r"\([^)]*\)", "", claim_n).strip()
    row_base = re.sub(r"\([^)]*\)", "", row_n).strip()
    if claim_base != row_base:
        return f"'{claim_base}' matcher ikke rækkens '{row_base}'"
    for part in re.findall(r"\(([^)]*)\)", claim_n):
        if part not in row_n:
            return f"'(...)'-delen '{part}' findes ikke i rækkens grundlag"
    return None


def row_matches(row: dict, before: float | None, after: float, delta: float | None) -> bool:
    if not is_number(row.get("after")) or abs(float(row["after"]) - after) > HISTORY_TOLERANCE:
        return False
    if before is None:
        if row.get("before") is not None:
            return False
    elif not is_number(row.get("before")) or abs(float(row["before"]) - before) > HISTORY_TOLERANCE:
        return False
    if delta is None:
        return row.get("delta") is None
    return is_number(row.get("delta")) and abs(float(row["delta"]) - delta) <= HISTORY_TOLERANCE


def check_history(
    fields: dict[str, str],
    history: list[dict] | None,
    errors: list[str],
    relax: frozenset[str] = frozenset(),
) -> None:
    """Krydstjek beskedens tal mod historik-rækkerne, så et opdigtet grundlag ikke går igennem.

    `relax` er de regler der er verificeret for netop denne commit (se VERIFIED_LEGACY).
    "history" slår kun række-match fra; resten af beskeden valideres stadig.
    """
    if history is None or "history" in relax:
        return
    grundlag = (fields.get("grundlag") or "").strip()
    if not grundlag:
        return

    raw_before = (fields.get("før") or "").strip()
    raw_after = (fields.get("efter") or "").strip()
    raw_delta = (fields.get("delta") or "").strip()
    if raw_before not in DASHES and not PCT_RE.fullmatch(raw_before):
        return
    if not PCT_RE.fullmatch(raw_after):
        return
    if raw_delta not in DASHES and not DELTA_VALUE_RE.fullmatch(raw_delta):
        return

    before = None if raw_before in DASHES else as_number(raw_before)
    after = as_number(raw_after)
    delta = None if raw_delta in DASHES else as_number(raw_delta)
    if after is None or (before is None and delta is not None):
        return

    candidates = [row for row in history if row_matches(row, before, after, delta)]
    low = re.sub(r"\s+", " ", grundlag.replace(NBSP, " ")).strip().lower()
    if not candidates:
        # Et pas der ikke flytter noget kan ikke opfinde en procent — men et tal der bevæger sig skal stå i historikken.
        if low == "ingen ændring" and before is not None and abs(after - before) <= HISTORY_TOLERANCE:
            return
        errors.append(
            f"Før/Efter/Δ ({raw_before or '?'} → {raw_after}, Δ {raw_delta or '?'}) matcher ingen "
            "historik-række i projektstatus.json — ret tallene i JSON, kør --update og brug rækkens tal"
        )
        return
    if low == "ingen ændring" or "basis" in relax:
        return
    problems = []
    for row in candidates:
        problem = basis_problem(grundlag, str(row.get("basis") or ""))
        if problem is None:
            return
        problems.append(problem)
    unique = list(dict.fromkeys(problems))
    errors.append("Grundlag matcher ikke historik-rækken: " + "; ".join(unique))


def verified_legacy(sha: str) -> dict | None:
    """Register-posten (relax + reason) hvis commit'en står i det verificerede register.

    Opslaget kræver mindst 7 tegn, så en tilfældig kort streng ikke kan ramme en post.
    """
    key = (sha or "").strip().lower()
    if len(key) < 7:
        return None
    for known, entry in VERIFIED_LEGACY.items():
        if known.startswith(key) or key.startswith(known):
            return entry
    return None


def check_message(
    message: str,
    history: list[dict] | None = None,
    relax: frozenset[str] = frozenset(),
) -> tuple[bool, list[str], str]:
    """Returner (ok, fejl, undtagelsesgrund). `history` er rækkerne fra projektstatus.json,
    `relax` de regler der er verificeret for denne commit."""
    text = (message or "").strip()
    if not text:
        return True, [], "tom besked (committen blev afbrudt)"
    subject = text.splitlines()[0].strip().lower()
    for pattern in SKIP_SUBJECTS:
        if subject.startswith(pattern):
            return True, [], f"undtaget emne: {pattern.strip()}"

    fields = parse_fields(text)
    errors: list[str] = []
    for key, label in FIELDS:
        if not fields.get(key, "").strip():
            errors.append(f"mangler linjen '{label}:'")

    if not fields.get("modul", "").strip() and "modul" in fields:
        errors.append("'Modul' er tom — skriv modulet/opgaven der blev arbejdet på")

    before = check_pct(fields.get("før", ""), "Før", errors) if fields.get("før", "").strip() else None
    after = check_pct(fields.get("efter", ""), "Efter", errors) if fields.get("efter", "").strip() else None
    delta = check_delta(fields.get("delta", ""), errors) if fields.get("delta", "").strip() else None
    if fields.get("grundlag", "").strip():
        check_grounds(fields["grundlag"], errors)

    if before is None and delta is not None:
        errors.append("Δ kan ikke være et tal når 'Før' er en streg — første måling skriver begge som '—'")
    if before is not None and after is not None and delta is not None:
        expected = round(after - before, 4)
        if abs(delta - expected) > TOLERANCE:
            errors.append(f"Δ {delta:+g} passer ikke med {before:g} → {after:g} (Δ skal være {expected:+g})")

    check_history(fields, history, errors, relax)
    return (not errors), errors, ""


# --------------------------------------------------------------------------- fixtures

# Rækkerne fixtures krydstjekkes mod — de ligner projektstatus.json, men er faste, så
# fixture-svaret ikke ændrer sig når den virkelige historik vokser.
FIXTURE_HISTORY: list[dict] = [
    {
        "date": "2026-11-01",
        "project": "steamgears.eu — site-optimering",
        "module": "S0–S4 (baseline)",
        "before": None,
        "after": 3.6,
        "delta": None,
        "basis": "0,75 af 21,1 d (S0 0,6/2,6 · S1 0/4,5 · S2 0/6 · S3 0/3 · S4 0,15/5)",
    },
    {
        "date": "2026-11-03",
        "project": "steamgears.eu — site-optimering",
        "module": "daglig måling",
        "before": 55.4,
        "after": 56.3,
        "delta": 0.9,
        "basis": "12,25 af 21,75 d (S0 1,9/2,6 · S1 2,15/4,6 · S2 4,65/6 · S3 2,15/3,55 · S4 1,4/5)",
    },
    {
        "date": "2026-11-02",
        "project": "Steamgears Autopilot — plugin",
        "module": "daglig måling",
        "before": 30.0,
        "after": 34.1,
        "delta": 4.1,
        "basis": "1,0 af 11,35 d (P1.2 1,0/11,35)",
    },
    {
        "date": "2026-11-02",
        "project": "Steamgears Autopilot — plugin",
        "module": "daglig måling",
        "before": 29.7,
        "after": 30.0,
        "delta": 0.3,
        "basis": "7,3 af 24,35 d (P0 7,3/24,35)",
    },
    {
        "date": "2026-11-02",
        "project": "SEO-indholdsproduktion",
        "module": "ingen ændring",
        "before": 0,
        "after": 0,
        "delta": 0,
        "basis": "0 af 3 guides (Publicerede guides 0/3)",
    },
    {
        "date": "2026-11-03",
        "project": "Døde internlinks",
        "module": "ingen ændring",
        "before": 100,
        "after": 100,
        "delta": 0,
        "basis": "39 af 39 links",
    },
]

FIXTURES: list[tuple[str, str, bool]] = [
    (
        "fuld blok i tre linjer",
        "Ret navigationen\n\nModul: S2.3 navigation — nav-links\n"
        "Før: 55,4 %  →  Efter: 56,3 %  (Δ +0,9)\n"
        "Grundlag: 12,25 af 21,75 d (S2 4,65/6)\n\n🤖 Generated with Codebuff\n",
        True,
    ),
    (
        "opdigtet grundlag afvises",
        "Modul: S2.3 navigation — nav-links\n"
        "Før: 55,4 %  →  Efter: 56,3 %  (Δ +0,9)\n"
        "Grundlag: 12,45 af 21,75 d (S2 4,65/6)\n",
        False,
    ),
    (
        "før/efter uden historik-række afvises",
        "Modul: S1.1 — crawl\nFør: 10 %\nEfter: 20 %\nΔ: +10\nGrundlag: 1 af 2 d\n",
        False,
    ),
    (
        "opdigtet moduldel i grundlaget afvises",
        "Modul: S2.3 navigation — nav-links\n"
        "Før: 55,4 %\nEfter: 56,3 %\nΔ: +0,9\n"
        "Grundlag: 12,25 af 21,75 d (S9 9/9)\n",
        False,
    ),
    (
        "grundlag uden rod i historikken afvises",
        "Modul: S2.3 navigation — nav-links\n"
        "Før: 55,4 %\nEfter: 56,3 %\nΔ: +0,9\n"
        "Grundlag: 5 af 5 faser\n",
        False,
    ),
    (
        "ingen ændring kræver ingen historik-række",
        "Modul: tools — indeks\nFør: 42 %\nEfter: 42 %\nΔ: 0\nGrundlag: ingen ændring\n",
        True,
    ),
    (
        "felter på hver sin linje",
        "Modul: P1.2 påmindelse\n% før: 30 %\n% efter: 34,1 %\nDelta: +4,1\nGrundlag: 1,0 af 11,35 d\n",
        True,
    ),
    (
        "første måling med streg",
        "Modul: S0 — baseline\nFør: —\nEfter: 3,6 %\nΔ: —\nGrundlag: 0,75 af 21,1 d\n",
        True,
    ),
    (
        "ingen ændring",
        "Modul: rebrand — batch 4\nFør: 63 %\nEfter: 63 %\nΔ: 0\nGrundlag: ingen ændring\n",
        True,
    ),
    (
        "fed markdown i felterne",
        "**Modul:** docs — indeks\n**Før:** 0 %  →  **Efter:** 0 %  (Δ 0)\n**Grundlag:** 0 af 3 guides\n",
        True,
    ),
    (
        "decimaler med punktum",
        "Modul: plugin — P0.4\nFør: 29.7 %\nEfter: 30.0 %\nΔ: +0.3\nGrundlag: 7,3 af 24,35 d\n",
        True,
    ),
    (
        "plus uden fortegn på Δ",
        "Modul: links\nFør: 100 %\nEfter: 100 %\nΔ: 0\nGrundlag: 39 af 39 links\n",
        True,
    ),
    (
        "merge-commit undtages",
        "Merge branch 'main' of github.com:humle93/steamgears-site\n",
        True,
    ),
    (
        "revert undtages",
        'Revert "Skriv corset-guiden"\n\nThis reverts commit a07c743.\n',
        True,
    ),
    (
        "status-bottens commit undtages",
        "chore(status): daglig projektstatus 2026-11-03\n",
        True,
    ),
    (
        "rebrand-bottens commit undtages",
        "chore(rebrand): kørselsrapport for tildelingen\n",
        True,
    ),
    (
        "natlig auto-push undtages",
        "chore: natlig auto-push 2026-11-03\n\n🤖 Generated with Codebuff\n",
        True,
    ),
    ("tom besked undtages", "", True),
    (
        "mangler Modul",
        "Før: 55 %\nEfter: 56 %\nΔ: +1\nGrundlag: 1 af 5 faser\n",
        False,
    ),
    (
        "mangler Grundlag",
        "Modul: S2.3\nFør: 55 %\nEfter: 56 %\nΔ: +1\n",
        False,
    ),
    (
        "mangler Δ",
        "Modul: S2.3\nFør: 55 %\nEfter: 56 %\nGrundlag: 1 af 5 faser\n",
        False,
    ),
    (
        "Grundlag uden tal",
        "Modul: S2.3\nFør: 55 %\nEfter: 56 %\nΔ: +1\nGrundlag: arbejdet føles færdigt\n",
        False,
    ),
    (
        "Grundlag er TBD",
        "Modul: S2.3\nFør: 55 %\nEfter: 56 %\nΔ: +1\nGrundlag: TBD\n",
        False,
    ),
    (
        "Δ passer ikke med før/efter",
        "Modul: S2.3\nFør: 54 %\nEfter: 60 %\nΔ: +5\nGrundlag: 1 af 5 faser\n",
        False,
    ),
    (
        "Før er ikke et tal",
        "Modul: S2.3\nFør: næsten 55 %\nEfter: 56 %\nΔ: +1\nGrundlag: 1 af 5 faser\n",
        False,
    ),
    (
        "procent over 100",
        "Modul: S2.3\nFør: 90 %\nEfter: 120 %\nΔ: +30\nGrundlag: 1 af 5 faser\n",
        False,
    ),
    (
        "Efter er tom",
        "Modul: S2.3\nFør: 55 %\nEfter: \nΔ: +1\nGrundlag: 1 af 5 faser\n",
        False,
    ),
    (
        "Modul er tom",
        "Modul:\nFør: 55 %\nEfter: 56 %\nΔ: +1\nGrundlag: 1 af 5 faser\n",
        False,
    ),
    (
        "Δ som tal uden baseline",
        "Modul: S0\nFør: —\nEfter: 3,6 %\nΔ: +3,6\nGrundlag: 0,75 af 21,1 d\n",
        False,
    ),
    (
        "ingen af felterne",
        "Ship the 7.26.0 package and keep .secrets out of git\n",
        False,
    ),
]


# Register-fixtures: de kører mod deres egen historik, så de ikke flytter de faste fixtures.
RELAX_HISTORY: list[dict] = [
    {
        "date": "2026-11-03",
        "project": "Steamgears Autopilot — plugin",
        "module": "P1.18 UI",
        "before": 76.3,
        "after": 76.7,
        "delta": 0.4,
        "basis": "22,75 af 29,65 d (P0 4,35/4,7 · P1 11,2/15,2 · P2 4,5/7 · P3 2,7/2,75)",
    },
]

_MSG_BASIS_STYLE = (
    "Modul: P1.18 UI — nøgler på ét sted\nFør: 76,3 %\nEfter: 76,7 %\nΔ: +0,4\n"
    "Grundlag: P1.18 0,5 d af 29,65 d (P1 10,7 → 11,2 af 15,2 d)\n"
)
_MSG_NO_MODUL = "Før: 76,3 %\nEfter: 76,7 %\nΔ: +0,4\nGrundlag: 22,75 af 29,65 d\n"
_MSG_BAD_DELTA = "Modul: P1.18\nFør: 76,3 %\nEfter: 76,7 %\nΔ: +9\nGrundlag: 22,75 af 29,65 d\n"
_MSG_FOREIGN_NUMBERS = "Modul: P1.5c\nFør: 91,0 %\nEfter: 96,2 %\nΔ: +5,2\nGrundlag: 27,8 af 28,9 d\n"

RELAX_FIXTURES: list[tuple[str, str, frozenset[str], bool]] = [
    ("register: kun Grundlag-reglen lempes", _MSG_BASIS_STYLE, frozenset({"basis"}), True),
    ("register: samme besked uden lempelse er rød", _MSG_BASIS_STYLE, frozenset(), False),
    ("register: history-lempelse dækker ikke et manglende Modul", _MSG_NO_MODUL, frozenset({"history"}), False),
    ("register: history-lempelse dækker ikke et forkert Δ", _MSG_BAD_DELTA, frozenset({"history"}), False),
    ("register: basis-lempelse dækker ikke tal uden række", _MSG_FOREIGN_NUMBERS, frozenset({"basis"}), False),
    ("register: history-lempelse accepterer den registrerede form", _MSG_FOREIGN_NUMBERS, frozenset({"history"}), True),
]


def run_selftest() -> int:
    failed = []
    for name, message, expected in FIXTURES:
        ok, errors, _skip = check_message(message, FIXTURE_HISTORY)
        if ok != expected:
            failed.append(f"{name}: forventet {'OK' if expected else 'FEJL'}, fik {'OK' if ok else 'FEJL'}"
                          + (f" ({'; '.join(errors)})" if errors else ""))
    # Registret: kendt SHA (fuld som kort) giver en grund, ukendt giver ingenting, og en
    # forkortelse under 7 tegn må ikke ramme et register-opslag ved et tilfælde.
    for sha, expected in ((next(iter(VERIFIED_LEGACY)), True), (next(iter(VERIFIED_LEGACY))[:8], True),
                          ("0" * 40, False), (next(iter(VERIFIED_LEGACY))[:4], False)):
        got = verified_legacy(sha) is not None
        if got != expected:
            failed.append(f"register {sha!r}: forventet {'fundet' if expected else 'ikke fundet'}")

    # Registret må ikke kunne lempe mere end de regler der findes, og hver post skal have en grund.
    for sha, entry in VERIFIED_LEGACY.items():
        if not isinstance(entry, dict) or not str(entry.get("reason", "")).strip():
            failed.append(f"register {sha}: posten mangler en begrundelse")
            continue
        if len(sha) < 8:
            failed.append(f"register {sha}: SHA er kortere end 8 tegn")
        unknown = set(entry.get("relax", ())) - ALLOWED_RELAX
        if unknown:
            failed.append(f"register {sha}: ukendt lempelse {sorted(unknown)}")

    for name, message, relax, expected in RELAX_FIXTURES:
        ok, errors, _skip = check_message(message, RELAX_HISTORY, relax)
        if ok != expected:
            failed.append(f"{name}: forventet {'OK' if expected else 'FEJL'}, fik {'OK' if ok else 'FEJL'}"
                          + (f" ({'; '.join(errors)})" if errors else ""))
    for line in failed:
        print(f"[FEJL] {line}")
    if failed:
        print(f"[FEJL] {len(failed)} fejl i {len(FIXTURES)} fixtures + register")
        return 1
    print(f"[OK] {len(FIXTURES)} af {len(FIXTURES)} fixtures som forventet")
    print(f"[OK] {len(RELAX_FIXTURES)} register-fixtures: kun den verificerede regel lempes")
    print(f"[OK] register: {len(VERIFIED_LEGACY)} verificerede ældre commits")
    return 0


# --------------------------------------------------------------------------- kilder


def git(*args: str) -> subprocess.CompletedProcess:
    return subprocess.run(["git", *args], cwd=ROOT, capture_output=True, text=True)


def load_history(path: Path = STATE_PATH) -> list[dict] | None:
    """Historik-rækkerne fra projektstatus.json, eller None hvis filen ikke kan læses."""
    try:
        state = json.loads(path.read_text(encoding="utf-8"))
    except (OSError, ValueError):
        return None
    rows = state.get("history") if isinstance(state, dict) else None
    if not isinstance(rows, list):
        return None
    return [row for row in rows if isinstance(row, dict)]


def history_or_note() -> list[dict] | None:
    rows = load_history()
    if rows is None:
        print("[NOTE] projektstatus.json kunne ikke læses — historik-krydstjek er sprunget over")
    return rows


def message_from_commit(rev: str) -> str:
    result = git("log", "-1", "--format=%B", rev)
    if result.returncode != 0:
        raise Guard(f"kunne ikke læse commit '{rev}': {result.stderr.strip()[:200]}")
    return result.stdout


def commits_in_range(spec: str) -> list[tuple[str, str]]:
    """[(sha, besked)] for intervallet, nyeste sidst. Fejler intervallet (fx nuller på en ny
    branch), tjekkes kun tip-committen — så en push aldrig går igennem ustraffet."""
    result = git("log", "--format=%H%x1f%B%x1e", spec)
    if result.returncode != 0:
        tip = git("rev-parse", "--verify", spec.split("..")[-1] or "HEAD")
        if tip.returncode != 0:
            raise Guard(f"intervallet '{spec}' kunne ikke læses: {result.stderr.strip()[:200]}")
        print(f"[NOTE] intervallet '{spec}' kunne ikke læses — tjekker kun tip-committen")
        return [(tip.stdout.strip(), message_from_commit(tip.stdout.strip()))]
    rows = []
    for record in result.stdout.split("\x1e"):
        record = record.strip("\n")
        if not record:
            continue
        sha, _sep, body = record.partition("\x1f")
        rows.append((sha.strip(), body))
    return rows


def report(message: str, errors: list[str], label: str) -> None:
    print(f"[FEJL] {label}: commit-beskeden opfylder ikke procentreglen (AGENTS.md §1).")
    for error in errors:
        print(f"  - {error}")
    print("\nSkriv blokken i beskeden:\n")
    for line in EXAMPLE.splitlines():
        print(f"  {line}")
    print("\nRegler: hvert pas melder Modul / % før / % efter / Δ / Grundlag, og grundlaget skal")
    print("kunne genberegnes. Se AGENTS.md §1 (reglen) og §4 (værnet).")


def install_hook() -> int:
    path = git("rev-parse", "--git-path", "hooks")
    if path.returncode != 0:
        print("[FEJL] dette er ikke et git-repo")
        return 2
    hooks = Path(path.stdout.strip())
    if not hooks.is_absolute():
        hooks = ROOT / hooks
    hooks.mkdir(parents=True, exist_ok=True)
    target = hooks / "commit-msg"
    if target.exists() and target.read_text() != HOOK:
        backup = hooks / "commit-msg.bak"
        backup.write_text(target.read_text())
        print(f"[NOTE] den gamle hook er gemt som {backup.name}")
    target.write_text(HOOK)
    target.chmod(0o755)
    print(f"[OK] installeret: {target.relative_to(ROOT) if target.is_relative_to(ROOT) else target}")
    print("[OK] en commit uden Modul/Før/Efter/Δ/Grundlag afvises nu lokalt")
    return 0


# --------------------------------------------------------------------------- main


def main(argv: list[str] | None = None) -> int:
    parser = argparse.ArgumentParser(
        prog="commit_check.py",
        description="Værn: kræver Modul/% før/% efter/Δ/Grundlag i commit-beskeder (AGENTS.md §1).",
    )
    group = parser.add_mutually_exclusive_group()
    group.add_argument("--selftest", action="store_true", help="kør fixtures (før installation)")
    group.add_argument("--commit", metavar="REV", help="tjek én commit (standard: HEAD)")
    group.add_argument("--range", dest="range_spec", metavar="A..B", help="tjek alle commits i intervallet")
    group.add_argument("--message-file", metavar="FIL", help="tjek en beskedfil (bruges af commit-msg-hooken)")
    group.add_argument("--install", action="store_true", help="skriv .git/hooks/commit-msg")
    args = parser.parse_args(argv)

    try:
        if args.selftest:
            return run_selftest()

        if args.install:
            return install_hook()

        history = history_or_note()

        if args.message_file:
            try:
                message = Path(args.message_file).read_text()
            except OSError as exc:
                raise Guard(f"kunne ikke læse beskedfilen: {exc}")
            ok, errors, skip = check_message(message, history)
            if ok:
                if skip:
                    print(f"[OK] undtaget: {skip}")
                else:
                    print("[OK] commit-beskeden har procenterne")
                return 0
            report(message, errors, args.message_file)
            return 1

        if args.range_spec:
            rows = commits_in_range(args.range_spec)
            bad = 0
            for sha, body in rows:
                short = sha[:8]
                legacy = verified_legacy(sha)
                relax = frozenset(legacy["relax"]) if legacy else frozenset()
                ok, errors, skip = check_message(body, history, relax)
                if ok:
                    if legacy:
                        print(f"[OK] {short} register: {legacy['reason']}"
                              f" (lempet: {', '.join(RELAX_LABELS[r] for r in legacy['relax'])})")
                    else:
                        print(f"[OK] {short} {skip or 'har procenterne'}")
                    continue
                bad += 1
                report(body, errors, f"{short}")
            print(f"[{'OK' if bad == 0 else 'FEJL'}] {len(rows) - bad} af {len(rows)} commits i '{args.range_spec}'"
                  f" opfylder reglen")
            return 0 if bad == 0 else 1

        rev = args.commit or "HEAD"
        resolved = git("rev-parse", rev).stdout.strip()
        legacy = verified_legacy(resolved)
        relax = frozenset(legacy["relax"]) if legacy else frozenset()
        message = message_from_commit(rev)
        ok, errors, skip = check_message(message, history, relax)
        if ok:
            if legacy:
                print(f"[OK] {resolved[:8]} register: {legacy['reason']}"
                      f" (lempet: {', '.join(RELAX_LABELS[r] for r in legacy['relax'])})")
            else:
                print(f"[OK] {skip or 'commit-beskeden har procenterne'}")
            return 0
        report(message, errors, args.commit or "HEAD")
        return 1
    except Guard as exc:
        print(f"[FEJL] {exc}")
        return 2


if __name__ == "__main__":
    sys.exit(main())
