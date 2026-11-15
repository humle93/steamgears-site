#!/usr/bin/env python3
"""Backup- og gendannelsesøvelse kørt fra CI — uden om MCP (S5.12).

``tools/wp_db_drill.py`` kører øvelsen **på serveren** gennem Novamira-MCP. Det virker,
men gør øvelsen til en agenthandling: CI kan ikke udføre den, og keep-alive-kørslen
roterer refresh-tokenet, så en ekstra fornyelse i en anden workflow ville slå begge
kørsler ihjel. Denne fil gør den samme måling med **en almindelig MySQL-klient** over
TCP, så den 1. i måneden kan køre af sig selv på en GitHub-runner og committe rapporten.

Målingen er den samme som før, felt for felt:

1. **Dump** med ``mysqldump --single-transaction`` (gzip), uden de forældede tabeller —
   de bærer et andet prefix end det levende og må ikke kunne overskrives af en gendannelse.
2. **Gendannelse** i samme database under scratch-prefixet ``sgtest_`` (brugeren har kun
   rettigheder på én database — ``CREATE DATABASE`` giver ``ERROR 1044``), med prefixet
   byttet i dumpet. Tiden fra første byte til ``mysql`` afslutter er **RTO**.
3. **Rækkeparitet** for hver tabel i det levende prefix: ét ``COUNT(*)`` pr. tabel,
   samme tabelnavne på begge sider.
4. **Oprydning**: tabeller først, derefter visninger (``DROP TABLE`` fejler på en visning),
   og et hårdt krav om **0 rester**.

Adgangen kommer fra miljøvariabler, aldrig fra argumenter:

    DB_HOST  DB_NAME  DB_USER  DB_PASSWORD    (påkrævet)
    DB_PORT  (3306)   DB_PREFIX (clk857797e3d3_)

Passwordet skrives kun til en ``0600``-fil af typen ``--defaults-extra-file``; det står
aldrig i en argv, en log eller en rapport. Mangler variablerne, afsluttes med **exit 2**,
så workflowen kan springe over med en ``::notice`` i stedet for at fejle.

Brug::

    python3 tools/db_drill_ci.py --selftest    # offline, ingen database
    python3 tools/db_drill_ci.py --plan        # vis kommandoerne (password maskeret)
    python3 tools/db_drill_ci.py --probe       # kan vi nå databasen? (exit 3 = nej)
    python3 tools/db_drill_ci.py --ci          # kør øvelsen, skriv rapport + historik

Exit: ``0`` grøn · ``1`` øvelsen fejlede · ``2`` konfiguration mangler · ``3`` databasen
kan ikke nås (værtens allowlist, netværk eller credentials).
"""

from __future__ import annotations

import argparse
import gzip
import hashlib
import json
import os
import pathlib
import subprocess
import sys
import tempfile
import time

ROOT = pathlib.Path(__file__).resolve().parent.parent
HISTORY = ROOT / "reports" / "db-drill-history.json"
SCRATCH = "sgtest_"
DEFAULT_PORT = 3306
DEFAULT_PREFIX = "clk857797e3d3_"
REQUIRED = ("DB_HOST", "DB_NAME", "DB_USER", "DB_PASSWORD")


class DrillError(RuntimeError):
    """En måling der ikke kunne gennemføres — bærer stderr fra det Trin der fejlede."""


# ------------------------------------------------------------------ konfiguration

def config(env: dict | None = None) -> tuple[dict | None, list[str]]:
    """Læs og validér miljøvariablerne. Returnerer (config, problemer)."""
    env = os.environ if env is None else env
    missing = [name for name in REQUIRED if not str(env.get(name) or "").strip()]
    if missing:
        return None, [f"{name} mangler" for name in missing]

    problems: list[str] = []
    raw_port = str(env.get("DB_PORT") or DEFAULT_PORT).strip()
    try:
        port = int(raw_port)
        if not 0 < port < 65536:
            raise ValueError
    except ValueError:
        problems.append(f"DB_PORT er ikke en gyldig port: {raw_port!r}")
        port = DEFAULT_PORT

    prefix = str(env.get("DB_PREFIX") or DEFAULT_PREFIX).strip()
    if not prefix.endswith("_"):
        problems.append(f"DB_PREFIX skal slutte med '_': {prefix!r}")
    if prefix.startswith(SCRATCH):
        problems.append(f"DB_PREFIX må ikke være scratch-prefixet {SCRATCH!r}")

    for name in ("DB_PASSWORD", "DB_USER", "DB_HOST", "DB_NAME"):
        if "\n" in str(env.get(name) or "") or "\r" in str(env.get(name) or ""):
            problems.append(f"{name} må ikke indeholde linjeskift (det ville brække my.cnf)")

    cfg = {
        "host": str(env["DB_HOST"]).strip(),
        "port": port,
        "name": str(env["DB_NAME"]).strip(),
        "user": str(env["DB_USER"]).strip(),
        "password": str(env["DB_PASSWORD"]),
        "prefix": prefix,
    }
    return (None if problems else cfg), problems


def quote(value: str) -> str:
    """my.cnf-værdi i dobbelte citationstegn — en ``#`` eller et mellemrum må ikke brække linjen."""
    return '"' + value.replace("\\", "\\\\").replace('"', '\\"') + '"'


def cnf_text(cfg: dict) -> str:
    return (
        "[client]\n"
        f"host={quote(cfg['host'])}\n"
        f"port={cfg['port']}\n"
        f"user={quote(cfg['user'])}\n"
        f"password={quote(cfg['password'])}\n"
        "default-character-set=utf8mb4\n"
    )


def write_cnf(cfg: dict, path: pathlib.Path) -> pathlib.Path:
    path.write_text(cnf_text(cfg), encoding="utf-8")
    os.chmod(path, 0o600)
    return path


def client_argv(cnf: pathlib.Path, database: str | None = None) -> list[str]:
    """Kommandoen uden password i argv — passwordet læses fra ``--defaults-extra-file``."""
    argv = ["mysql", f"--defaults-extra-file={cnf}", "-N", "-B",
            "--default-character-set=utf8mb4"]
    if database:
        argv.append(database)
    return argv


def dump_argv(cnf: pathlib.Path, database: str, ignore: list[str]) -> list[str]:
    argv = ["mysqldump", f"--defaults-extra-file={cnf}", "--single-transaction", "--quick",
            "--skip-lock-tables", "--default-character-set=utf8mb4", "--no-tablespaces",
            "--triggers"]
    argv += [f"--ignore-table={database}.{table}" for table in ignore]
    argv.append(database)
    return argv


def plan(cfg: dict) -> list[str]:
    """De kommandoer øvelsen kører, med passwordet fjernet — til log og dokumentation."""
    cnf = pathlib.Path("<tmp>/my.cnf")
    lines = [f"[client] host={cfg['host']} port={cfg['port']} user={cfg['user']} password=***"]
    lines.append(" ".join(dump_argv(cnf, cfg["name"], [f"{cfg['prefix']}eksempel"])))
    lines.append(f"gunzip -c dump.sql.gz | sed s/{cfg['prefix']}/{SCRATCH}/g | "
                 + " ".join(client_argv(cnf, cfg["name"])))
    return lines


# ------------------------------------------------------------------ SQL

def sql(cnf: pathlib.Path, database: str, statement: str, timeout: int = 900) -> str:
    """Kør én sætning og returnér stdout. Fejl bærer stderr, så rapporten kan vise den."""
    try:
        done = subprocess.run(client_argv(cnf, database), input=statement,
                              capture_output=True, text=True, timeout=timeout)
    except FileNotFoundError as exc:
        raise DrillError(f"mysql-klienten findes ikke: {exc}") from exc
    except subprocess.TimeoutExpired as exc:
        raise DrillError(f"SQL timeout efter {timeout} s: {statement[:80]}…") from exc
    if done.returncode != 0:
        raise DrillError(done.stderr.strip()[:1200] or f"mysql afsluttede {done.returncode}")
    return done.stdout


def list_tables(cnf: pathlib.Path, database: str, like: str, kind: str | None = None) -> list[str]:
    where = f"table_schema=DATABASE() AND table_name LIKE '{like}'"
    if kind:
        where += f" AND table_type='{kind}'"
    out = sql(cnf, database,
              f"SELECT table_name FROM information_schema.tables WHERE {where} ORDER BY table_name")
    prefix = like.rstrip("%")
    return [line.strip() for line in out.splitlines()
            if line.strip() and line.strip().startswith(prefix)]


def legacy_tables(all_tables: list[str], prefix: str) -> list[str]:
    """Alt der ikke bærer det levende WordPress-prefix — det må ikke dumpes eller røres."""
    return sorted(table for table in all_tables if not table.startswith(prefix))


def counts(cnf: pathlib.Path, database: str, tables: list[str], strip_prefix: str) -> dict[str, int]:
    """Ét ``COUNT(*)`` pr. tabel i én sætning, nøglet på tabelnavnet uden prefix."""
    if not tables:
        return {}
    parts = []
    for table in tables:
        key = table[len(strip_prefix):] if table.startswith(strip_prefix) else table
        key = key.replace("\\", "\\\\").replace("'", "''")
        parts.append(f"SELECT '{key}', COUNT(*) FROM `{table.replace('`', '')}`")
    out = sql(cnf, database, " UNION ALL ".join(parts))
    rows: dict[str, int] = {}
    for line in out.splitlines():
        if "\t" not in line:
            continue
        key, value = line.split("\t", 1)
        try:
            rows[key] = int(value)
        except ValueError:
            continue
    return rows


def parity(original: dict[str, int], restored: dict[str, int]) -> dict[str, int]:
    """Rækkeparitet: afvigelser, manglende tabeller og tabeller der ikke skulle være der."""
    common = original.keys() & restored.keys()
    return {
        "mismatch": sum(1 for key in common if original[key] != restored[key]),
        "missing": len(original.keys() - restored.keys()),
        "extra": len(restored.keys() - original.keys()),
    }


# ------------------------------------------------------------------ målingen

def take_dump(cnf: pathlib.Path, cfg: dict, ignore: list[str], work: pathlib.Path) -> dict:
    """Dump til gzip på disken. Tiden måles omkring mysqldump, ikke omkring gzip."""
    target = work / "db.sql.gz"
    started = time.time()
    try:
        with open(target, "wb") as raw, gzip.GzipFile(fileobj=raw, mode="wb", compresslevel=9) as gz:
            done = subprocess.run(dump_argv(cnf, cfg["name"], ignore), stdout=subprocess.PIPE,
                                  stderr=subprocess.PIPE, timeout=1800)
            gz.write(done.stdout)
    except FileNotFoundError as exc:
        raise DrillError(f"mysqldump findes ikke: {exc}") from exc
    except subprocess.TimeoutExpired as exc:
        raise DrillError("mysqldump timeout efter 1800 s") from exc
    # Én decimal: en gendannelse på under et halvt sekund må ikke blive rapporteret som
    # "0 s" — dommen kræver RTO > 0, og et lille fixture beviste at int-afrunding gjorde
    # en hurtig kørsel rød som om den aldrig var målt.
    seconds = round(time.time() - started, 1)
    if done.returncode != 0:
        raise DrillError(done.stderr.decode("utf-8", "replace").strip()[:1200]
                         or f"mysqldump afsluttede {done.returncode}")
    payload = target.read_bytes()
    return {"path": target, "bytes": len(payload),
            "sha256": hashlib.sha256(payload).hexdigest(), "seconds": seconds,
            "stderr": done.stderr.decode("utf-8", "replace").strip()[:1200]}


def restore(cnf: pathlib.Path, cfg: dict, dump: pathlib.Path) -> dict:
    """Spil dumpet af i scratch-prefixet — tiden her er RTO."""
    started = time.time()
    script = gzip.decompress(dump.read_bytes()).replace(cfg["prefix"].encode(), SCRATCH.encode())
    try:
        done = subprocess.run(client_argv(cnf, cfg["name"]), input=script,
                              capture_output=True, timeout=1800)
    except subprocess.TimeoutExpired as exc:
        raise DrillError("gendannelsen timede ud efter 1800 s") from exc
    # Én decimal: en gendannelse på under et halvt sekund må ikke blive rapporteret som
    # "0 s" — dommen kræver RTO > 0, og et lille fixture beviste at int-afrunding gjorde
    # en hurtig kørsel rød som om den aldrig var målt.
    seconds = round(time.time() - started, 1)
    if done.returncode != 0:
        raise DrillError(done.stderr.decode("utf-8", "replace").strip()[:1200]
                         or f"mysql afsluttede {done.returncode}")
    return {"seconds": seconds, "stderr": done.stderr.decode("utf-8", "replace").strip()[:1200]}


def cleanup(cnf: pathlib.Path, cfg: dict) -> dict:
    """Drop tabeller, derefter visninger, og kræv at intet står tilbage."""
    started = time.time()
    tables = list_tables(cnf, cfg["name"], SCRATCH + "%", "BASE TABLE")
    for table in tables:
        sql(cnf, cfg["name"], f"DROP TABLE IF EXISTS `{table}`")
    views = list_tables(cnf, cfg["name"], SCRATCH + "%", "VIEW")
    for view in views:
        sql(cnf, cfg["name"], f"DROP VIEW IF EXISTS `{view}`")
    left = list_tables(cnf, cfg["name"], SCRATCH + "%")
    return {"seconds": int(round(time.time() - started)), "dropped": len(tables),
            "views_dropped": len(views), "left": len(left),
            "left_names": left[:10]}


def probe(cnf: pathlib.Path, cfg: dict) -> dict:
    """Kan vi nå databasen, og stemmer prefixet? Bruges som eget trin før øvelsen."""
    version = sql(cnf, cfg["name"], "SELECT VERSION(), @@hostname", timeout=60).strip()
    all_tables = list_tables(cnf, cfg["name"], "%")
    live = [t for t in all_tables if t.startswith(cfg["prefix"])]
    views = list_tables(cnf, cfg["name"], cfg["prefix"] + "%", "VIEW")
    return {"version": version.replace("\t", " · "), "tables": len(live),
            "views": len(views), "legacy": len(legacy_tables(all_tables, cfg["prefix"])),
            "left": len(list_tables(cnf, cfg["name"], SCRATCH + "%"))}


def build_report(cfg: dict, measured: dict) -> dict:
    """Rapporten har samme felter som MCP-vejen, så ``db_drill_check.py`` kan læse den uændret."""
    st = measured
    errors = {key: value for key, value in st.get("errors", {}).items() if value}
    report = {
        "dato": time.strftime("%Y-%m-%d"),
        "tidspunkt": time.strftime("%H:%M"),
        "kilde": "ci",
        "database": cfg["name"],
        "prefix": cfg["prefix"],
        "legacy_tables_excluded": st.get("legacy", 0),
        "dump_bytes": st.get("dump_bytes", 0),
        "dump_sha256": st.get("dump_sha256", ""),
        "dump_seconds": st.get("dump_seconds", 0),
        "restore_seconds": st.get("restore_seconds", 0),
        "parity_seconds": st.get("parity_seconds", 0),
        "cleanup_seconds": st.get("cleanup_seconds", 0),
        "rpo_hours": 0,
        "rto_seconds": st.get("restore_seconds", 0),
        "tables_expected": st.get("tables_expected", 0),
        "tables_restored": st.get("tables_restored", 0),
        "rows_total": st.get("rows_total", 0),
        "row_mismatches": st.get("row_mismatches", 0),
        "missing_tables": st.get("missing_tables", 0),
        "extra_tables": st.get("extra_tables", 0),
        "dropped_tables": st.get("dropped_tables", 0),
        "dropped_views": st.get("dropped_views", 0),
        "left_behind": st.get("left_behind", 0),
        "errors": errors,
    }
    report["ok"] = (
        report["dump_bytes"] > 0
        and report["tables_expected"] > 0
        and report["rows_total"] > 0  # en grøn måling af ingenting findes ikke
        and report["tables_restored"] == report["tables_expected"]
        and report["row_mismatches"] == 0
        and report["missing_tables"] == 0
        and report["extra_tables"] == 0
        and report["left_behind"] == 0
        and report["rto_seconds"] > 0
    )
    return report


def run(cfg: dict, work: pathlib.Path) -> dict:
    """Hele øvelsen. Kaster ``DrillError`` hvis et trin ikke kan gennemføres."""
    cnf = write_cnf(cfg, work / "my.cnf")
    measured: dict = {"errors": {}}

    all_tables = list_tables(cnf, cfg["name"], "%")
    live_tables = [table for table in all_tables if table.startswith(cfg["prefix"])]
    if not live_tables:
        raise DrillError(f"ingen tabeller med prefix {cfg['prefix']!r} — er DB_PREFIX rigtig?")
    legacy = legacy_tables(all_tables, cfg["prefix"])
    measured["legacy"] = len(legacy)

    dump = take_dump(cnf, cfg, legacy, work)
    measured.update({"dump_bytes": dump["bytes"], "dump_sha256": dump["sha256"],
                     "dump_seconds": dump["seconds"]})
    if dump["stderr"]:
        measured["errors"]["mysqldump"] = dump["stderr"]

    try:
        restored = restore(cnf, cfg, dump["path"])
        measured["restore_seconds"] = restored["seconds"]
        if restored["stderr"]:
            measured["errors"]["gendannelse"] = restored["stderr"]

        started = time.time()
        original_counts = counts(cnf, cfg["name"], live_tables, cfg["prefix"])
        scratch_tables = list_tables(cnf, cfg["name"], SCRATCH + "%", "BASE TABLE")
        scratch_counts = counts(cnf, cfg["name"], scratch_tables, SCRATCH)
        verdict = parity(original_counts, scratch_counts)
        measured.update({"parity_seconds": int(round(time.time() - started)),
                         "tables_expected": len(original_counts),
                         "tables_restored": len(scratch_counts),
                         "rows_total": sum(original_counts.values()),
                         "row_mismatches": verdict["mismatch"],
                         "missing_tables": verdict["missing"],
                         "extra_tables": verdict["extra"]})
    except DrillError as exc:
        # En halv gendannelse må ikke blive liggende: ryd scratch-prefixet, og meld det
        # hvis oprydningen heller ikke kan gennemføres. Fejlen kastes videre uanset hvad.
        try:
            swept = cleanup(cnf, cfg)
            measured.update({"cleanup_seconds": swept["seconds"],
                             "dropped_tables": swept["dropped"],
                             "dropped_views": swept["views_dropped"],
                             "left_behind": swept["left"]})
        except DrillError:
            measured["left_behind"] = None
        raise DrillError(f"{exc} (scratch ryddet efter fejlen)") from exc

    swept = cleanup(cnf, cfg)
    measured.update({"cleanup_seconds": swept["seconds"], "dropped_tables": swept["dropped"],
                     "dropped_views": swept["views_dropped"], "left_behind": swept["left"]})
    if swept["left"]:
        measured["errors"]["oprydning"] = f"{swept['left']} tilbage: {', '.join(swept['left_names'])}"
    return build_report(cfg, measured)


# ------------------------------------------------------------------ selftest

def selftest() -> int:
    """Offline fixtures: konfiguration, my.cnf, argv, legacy, paritet og dommen."""
    failures: list[str] = []
    ran = 0

    def check(name: str, condition: bool) -> None:
        nonlocal ran
        ran += 1
        if not condition:
            failures.append(name)

    base = {"DB_HOST": "mysql58.unoeuro.com", "DB_NAME": "steamgears_eu_db",
            "DB_USER": "u", "DB_PASSWORD": "p"}

    cfg, problems = config({})
    check("tomme variabler giver problemer", cfg is None and len(problems) == 4)
    cfg, problems = config({"DB_HOST": "h", "DB_NAME": "d", "DB_USER": "u"})
    check("manglende password nævnes", cfg is None and problems == ["DB_PASSWORD mangler"])

    cfg, problems = config(base)
    check("standardport og -prefix", cfg is not None and cfg["port"] == 3306
          and cfg["prefix"] == DEFAULT_PREFIX and not problems)
    cfg, problems = config(dict(base, DB_PORT="3307", DB_PREFIX="wp_"))
    check("port og prefix læses", cfg["port"] == 3307 and cfg["prefix"] == "wp_")
    cfg, problems = config(dict(base, DB_PORT="halvfems"))
    check("ugyldig port afvises", cfg is None and any("DB_PORT" in p for p in problems))
    cfg, problems = config(dict(base, DB_PREFIX="udenunderstreg"))
    check("prefix uden _ afvises", cfg is None and any("DB_PREFIX" in p for p in problems))
    cfg, problems = config(dict(base, DB_PREFIX=SCRATCH + "x"))
    check("scratch som prefix afvises", cfg is None and any("scratch" in p for p in problems))
    cfg, problems = config(dict(base, DB_PASSWORD="to\nlinjer"))
    check("linjeskift i password afvises", cfg is None and any("linjeskift" in p for p in problems))

    # my.cnf: en # eller et citationstegn i passwordet må ikke brække filen
    tricky = config(dict(base, DB_PASSWORD='p#a"s\\s'))[0]
    text = cnf_text(tricky)
    check("my.cnf starter med [client]", text.startswith("[client]\n"))
    check("password står citeret og escapes", 'password="p#a\\"s\\\\s"' in text)
    check("my.cnf har én linje pr. felt", len(text.rstrip("\n").splitlines()) == 6)

    # argv: passwordet må aldrig stå i en argv
    with tempfile.TemporaryDirectory() as tmp:
        cnf = write_cnf(tricky, pathlib.Path(tmp) / "my.cnf")
        check("my.cnf er 0600", (cnf.stat().st_mode & 0o777) == 0o600)
        argv = client_argv(cnf, "db") + dump_argv(cnf, "db", ["db.clk4219fc96b5_options"])
        check("argv har ingen password-værdi", not any(tricky["password"] in part for part in argv))
        check("argv peger på cnf-filen", any("--defaults-extra-file=" in part for part in argv))
        check("dump udelader legacy-tabel",
              "--ignore-table=db.clk4219fc96b5_options" in dump_argv(cnf, "db", ["clk4219fc96b5_options"]))
        lines = plan(config(base)[0])
        check("plan masker password", all("p#a" not in line for line in lines) and "***" in lines[0])

    # legacy: alt uden det levende prefix
    tables = ["clk857797e3d3_posts", "clk857797e3d3_options", "clk4219fc96b5_options"]
    check("legacy findes", legacy_tables(tables, "clk857797e3d3_") == ["clk4219fc96b5_options"])
    check("ingen legacy når alt matcher", legacy_tables(tables[:2], "clk857797e3d3_") == [])

    # paritet
    check("identisk er 0/0/0", parity({"a": 1, "b": 2}, {"a": 1, "b": 2})
          == {"mismatch": 0, "missing": 0, "extra": 0})
    check("én afvigelse tælles", parity({"a": 1}, {"a": 2})["mismatch"] == 1)
    check("manglende tabel tælles", parity({"a": 1, "b": 2}, {"a": 1})["missing"] == 1)
    check("uventet tabel tælles", parity({"a": 1}, {"a": 1, "z": 9})["extra"] == 1)

    # dommen
    good = {"legacy": 12, "dump_bytes": 3335364, "dump_sha256": "x" * 64, "dump_seconds": 4,
            "restore_seconds": 6, "parity_seconds": 5, "cleanup_seconds": 2,
            "tables_expected": 269, "tables_restored": 269, "row_mismatches": 0,
            "missing_tables": 0, "extra_tables": 0, "dropped_tables": 269, "dropped_views": 4,
            "left_behind": 0, "rows_total": 3335364, "errors": {}}
    report = build_report(config(base)[0], good)
    check("en fuld måling er grøn", report["ok"] is True and report["rto_seconds"] == 6)
    check("rapporten har MCP-vejens felter", {"dato", "dump_bytes", "restore_seconds", "rto_seconds",
                                              "tables_expected", "row_mismatches", "missing_tables",
                                              "left_behind", "ok"} <= set(report))
    for name, change in (("afvigelse gør rød", {"row_mismatches": 1}),
                         ("manglende tabel gør rød", {"missing_tables": 1}),
                         ("rester gør rød", {"left_behind": 1}),
                         ("uventet tabel gør rød", {"extra_tables": 1}),
                         ("tomt dump gør rød", {"dump_bytes": 0}),
                         ("nul tabeller gør rød", {"tables_expected": 0}),
                         ("manglende gendannelse gør rød", {"tables_restored": 0}),
                         ("nul rækker gør rød", {"rows_total": 0}),
                         ("nul RTO gør rød", {"restore_seconds": 0})):
        check(name, build_report(config(base)[0], dict(good, **change))["ok"] is False)

    # Hele kæden med falske SQL-kald: rækkefølgen legacy → dump → gendannelse → paritet →
    # oprydning skal give en grøn rapport, og en afvigelse eller rest skal gøre den rød.
    # Det er den samme ``run()`` som CI kalder, så en sammenkoblingsfejl fanges uden en database.
    live = [DEFAULT_PREFIX + "posts", DEFAULT_PREFIX + "options"]
    fakes = {
        "write_cnf": lambda cfg, path: path,
        "take_dump": lambda cnf, cfg, ignore, work: {
            "path": work / "db.sql.gz", "bytes": 3_335_364, "sha256": "b" * 64,
            "seconds": 4, "stderr": "", "ignore": ignore},
        "restore": lambda cnf, cfg, dump: {"seconds": 6, "stderr": ""},
        "counts": lambda cnf, db, tables, strip: {t[len(strip):]: 7 for t in tables},
        "cleanup": lambda cnf, cfg: {"seconds": 2, "dropped": 2, "views_dropped": 1,
                                     "left": 0, "left_names": []},
    }
    scenes = {
        "grøn": (lambda like: (live + ["clk4219fc96b5_options"]) if like == "%" else
                 (["sgtest_posts", "sgtest_options"] if like.startswith(SCRATCH) else []), 0, True),
        "afvigelse": (lambda like: live if like == "%" else
                      (["sgtest_posts", "sgtest_options"] if like.startswith(SCRATCH) else []), 1, False),
        "rest": (lambda like: live if like == "%" else
                 (["sgtest_posts", "sgtest_options"] if like.startswith(SCRATCH) else []), 2, False),
    }

    def pipeline(listing, mismatched: int, leftover: int = 0) -> dict:
        pipe_cfg = config(base)[0]
        real = {name: globals()[name] for name in list(fakes) + ["list_tables"]}
        try:
            globals().update(fakes)
            globals()["list_tables"] = lambda cnf, db, like, kind=None: listing(like)
            if leftover:
                globals()["cleanup"] = lambda cnf, cfg: {
                    "seconds": 2, "dropped": 2, "views_dropped": 1,
                    "left": leftover, "left_names": ["sgtest_posts"]}
            if mismatched:
                globals()["counts"] = (lambda cnf, db, tables, strip:
                                       {t[len(strip):]: (7 if strip == pipe_cfg["prefix"] else 8)
                                        for t in tables})
            with tempfile.TemporaryDirectory() as tmp:
                return run(pipe_cfg, pathlib.Path(tmp))
        finally:
            globals().update(real)

    healthy = pipeline(scenes["grøn"][0], scenes["grøn"][1])
    check("kæden giver en grøn rapport", healthy["ok"] is True)
    check("legacy-tabellen blev udeladt af dumpet", healthy["legacy_tables_excluded"] == 1)
    check("kæden tæller to tabeller på begge sider", healthy["tables_expected"] == 2
          and healthy["tables_restored"] == 2)
    check("kæden bruger gendannelsestiden som RTO", healthy["rto_seconds"] == 6)
    check("kæden summer rækkerne", healthy["rows_total"] == 14)
    # Sub-sekund RTO: fixturet beviste at int-afrunding gjorde en hurtig kørsel rød
    hurtig = build_report(config(base)[0], dict(good, restore_seconds=0.4))
    check("0,4 s RTO er stadig en måling", hurtig["ok"] is True and hurtig["rto_seconds"] == 0.4)
    check("kæden rydder op", healthy["dropped_tables"] == 2 and healthy["left_behind"] == 0)
    check("en afvigelse gør kæden rød", pipeline(scenes["afvigelse"][0], 1)["ok"] is False)
    check("en afvigelse tælles", pipeline(scenes["afvigelse"][0], 1)["row_mismatches"] == 2)
    check("en rest gør kæden rød", pipeline(scenes["rest"][0], 0, leftover=2)["ok"] is False)
    check("resten tælles", pipeline(scenes["rest"][0], 0, leftover=2)["left_behind"] == 2)

    # Et forkert DB_PREFIX må ikke give en tom, grøn måling
    real_list = globals()["list_tables"]
    try:
        globals()["list_tables"] = lambda cnf, db, like, kind=None: ["clk4219fc96b5_options"]
        try:
            run(config(base)[0], pathlib.Path(tempfile.mkdtemp()))
            check("forkert prefix fejler", False)
        except DrillError as exc:
            check("forkert prefix fejler", "DB_PREFIX" in str(exc))
    finally:
        globals()["list_tables"] = real_list

    for failure in failures:
        print("FAIL", failure)
    if failures:
        return 1
    print(f"OK — {ran} fixtures: konfiguration, my.cnf, argv, legacy, paritet, dommen og hele kæden")
    return 0


# ------------------------------------------------------------------ CLI

def main() -> int:
    ap = argparse.ArgumentParser(description="Backup- og gendannelsesøvelse fra CI (S5.12)")
    ap.add_argument("--selftest", action="store_true", help="offline fixtures, ingen database")
    ap.add_argument("--plan", action="store_true", help="vis kommandoerne med password maskeret")
    ap.add_argument("--probe", action="store_true", help="kan databasen nås, og stemmer prefixet?")
    ap.add_argument("--ci", action="store_true", help="kør øvelsen og skriv rapport + historik")
    ap.add_argument("--json", action="store_true", help="kun JSON til stdout")
    ap.add_argument("--out", default=f"reports/db-drill-{time.strftime('%Y-%m-%d')}.json")
    ap.add_argument("--reports", default=str(ROOT / "reports"))
    args = ap.parse_args()

    if args.selftest:
        return selftest()

    cfg, problems = config()
    if cfg is None:
        message = "konfiguration mangler: " + "; ".join(problems)
        print(message if args.json else f"SPRINGER OVER — {message}", file=sys.stderr)
        return 2

    if args.plan:
        print("\n".join(plan(cfg)))
        return 0

    if args.probe:
        with tempfile.TemporaryDirectory() as tmp:
            cnf = write_cnf(cfg, pathlib.Path(tmp) / "my.cnf")
            try:
                found = probe(cnf, cfg)
            except DrillError as exc:
                print(f"KAN IKKE NÅ DATABASEN — {exc}", file=sys.stderr)
                return 3
        print(f"nået: {found['version']} · {found['tables']} tabeller + {found['views']} visninger "
              f"under {cfg['prefix']} · {found['legacy']} forældede · {found['left']} scratch-rester")
        return 0

    if not args.ci:
        ap.error("vælg --selftest, --plan, --probe eller --ci")

    out_path = pathlib.Path(args.out)
    if not out_path.is_absolute():
        out_path = ROOT / out_path
    out_path.parent.mkdir(parents=True, exist_ok=True)

    with tempfile.TemporaryDirectory() as tmp:
        work = pathlib.Path(tmp)
        try:
            report = run(cfg, work)
        except DrillError as exc:
            report = {"dato": time.strftime("%Y-%m-%d"), "tidspunkt": time.strftime("%H:%M"),
                      "kilde": "ci", "database": cfg["name"], "prefix": cfg["prefix"],
                      "errors": {"oevelse": str(exc)}, "ok": False,
                      "dump_bytes": 0, "tables_expected": 0, "tables_restored": 0,
                      "rows_total": 0,
                      "row_mismatches": 0, "missing_tables": 0, "extra_tables": 0,
                      "left_behind": 0, "restore_seconds": 0, "rto_seconds": 0}

    out_path.write_text(json.dumps(report, ensure_ascii=False, indent=1) + "\n", encoding="utf-8")
    sys.path.insert(0, str(ROOT / "tools"))
    from wp_db_drill import append_history  # samme historik som MCP-vejen, én række pr. kørsel
    append_history(report, pathlib.Path(args.reports) / "db-drill-history.json")

    if args.json:
        print(json.dumps(report, ensure_ascii=False, indent=1))
    else:
        print(f"dump: {report['dump_bytes']} bytes på {report.get('dump_seconds', '?')} s "
              f"(sha256 {str(report.get('dump_sha256'))[:12]}…)")
        print(f"gendannelse: {report['rto_seconds']} s · paritet: {report['tables_expected']} tabeller, "
              f"{report['row_mismatches']} afvigelser, {report['missing_tables']} mangler, "
              f"{report['extra_tables']} uventede")
        print(f"oprydning: {report.get('dropped_tables', 0)} tabeller + "
              f"{report.get('dropped_views', 0)} visninger, {report['left_behind']} tilbage")
        for name, detail in report.get("errors", {}).items():
            print(f"  FEJL {name}: {detail}")
        print(f"{'OK' if report['ok'] else 'FEJL'} — rapport: {args.out}")
    return 0 if report["ok"] else 1


if __name__ == "__main__":
    raise SystemExit(main())
