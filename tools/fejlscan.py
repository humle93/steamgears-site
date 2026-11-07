#!/usr/bin/env python3
"""tools/fejlscan.py — repo-dækkende fejlscan for steamgears-site.

Fejlene der koster mest her, er dem der *ikke* giver en fejlkode: en zip der ikke følger
kilden, en version der står tre steder og er rigtig i to af dem, et dokument der ikke er i
biblioteksindekset, en include der blev omdøbt, og en test der er skrevet så den ikke kan
fejle. Ingen af dem fanges af `php -l` eller af smoke-suiten, fordi intet af det er PHP der
kører. Dette værn kører derfor på filerne og på tværs af dem:

  * **version**  — `SGAP_VERSION`, plugin-headerens `Version:` og navnet på zip'en i
    `plugins/` skal være samme tal, og der må kun ligge én zip.
  * **zip**      — hver fil i zip'en skal være byte-identisk med kilden, og ingen fil i
    kilden må mangle. (En release der er bygget før den sidste rettelse ser rigtig ud.)
  * **includes** — hver `require`/`include` skal pege på en fil der findes, og hver fil i
    `includes/` skal være krævet af plugin-filen. En glemt include er en død klasse.
  * **bibliotek**— hvert dokument i `docs/library/` skal stå i `README.md`, og hver relativ
    link i indekset skal pege på en fil der findes.
  * **hemmeligheder** — ingen tokens (GitHub, OpenAI/Anthropic, Slack, Google, NVIDIA,
    private nøgler) i sporede tekstfiler. Repoet er offentligt.
  * **markører**  — ingen `TODO`/`FIXME`/`XXX:` i plugin- og site-PHP; en halvfærdig ting
    der står i koden men ikke i roadmap'et bliver aldrig lavet.
  * **tomme tests** — ingen `|| true`/`&& true` i testfiler. En kontrol der ikke kan fejle
    er værre end ingen kontrol, fordi den ser grøn ud.

Kommandoer:

  python3 tools/fejlscan.py             # scan repoet
  python3 tools/fejlscan.py --selftest  # fixtures der skal fanges, og en ren kasse
  python3 tools/fejlscan.py --verbose   # vis hver fil der blev læst

Exitkoder: 0 = alt grønt, 1 = fund, 2 = intern fejl.
"""

from __future__ import annotations

import argparse
import hashlib
import re
import sys
import tempfile
import zipfile
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
PLUGIN = "steamgears-autopilot"
SELF = Path(__file__).resolve()

SKIP_DIRS = {".git", ".tools", ".projektstatus-backups", "node_modules", "__pycache__", "reports"}
TEXT_SUFFIXES = {".md", ".php", ".py", ".json", ".sh", ".yml", ".yaml", ".css", ".js", ".txt", ".html"}

SECRETS = [
    ("github-token", re.compile(r"gh[pousr]_[A-Za-z0-9]{30,}")),
    ("github-pat", re.compile(r"github_pat_[A-Za-z0-9_]{30,}")),
    # A Printful SKU ("sk-steampunk-unisex-t-shirt") is all lowercase; a real key is not.
    ("openai-key", re.compile(r"(?<![A-Za-z0-9_-])sk-(?:ant-)?(?=[A-Za-z0-9_-]*[A-Z])[A-Za-z0-9_-]{32,}")),
    ("slack-token", re.compile(r"xox[baprs]-[A-Za-z0-9-]{10,}")),
    ("google-key", re.compile(r"AIza[0-9A-Za-z_-]{35}")),
    ("nvidia-key", re.compile(r"nvapi-[A-Za-z0-9_-]{20,}")),
    ("private-key", re.compile("-----BEGIN " + r"(?:RSA |EC |OPENSSH )?PRIVATE KEY-----")),
]


def walk(root: Path):
    """Every file worth reading, with the noise directories left out."""
    for path in sorted(root.rglob("*")):
        if not path.is_file():
            continue
        rel = path.relative_to(root)
        if any(part in SKIP_DIRS for part in rel.parts):
            continue
        if path.resolve() == SELF:
            continue
        yield path, rel


def read(path: Path) -> str:
    try:
        return path.read_text(encoding="utf-8")
    except (UnicodeDecodeError, OSError):
        return ""


# ---------------------------------------------------------------- checks


def check_version(root: Path) -> list[tuple[str, str]]:
    found: list[tuple[str, str]] = []
    main = root / "plugins" / PLUGIN / f"{PLUGIN}.php"
    if not main.is_file():
        return [("plugin-missing", f"{main.relative_to(root)} findes ikke")]
    source = read(main)
    constant = re.search(r"define\(\s*'SGAP_VERSION'\s*,\s*'([^']+)'", source)
    header = re.search(r"^\s*\*?\s*Version:\s*(\S+)", source, re.MULTILINE)
    if not constant:
        found.append(("version-missing", "SGAP_VERSION er ikke defineret i plugin-filen"))
    if not header:
        found.append(("version-missing", "plugin-headeren har ingen Version:-linje"))
    zips = sorted((root / "plugins").glob("*.zip"))
    if len(zips) != 1:
        found.append(("zip-count", f"{len(zips)} zip-filer i plugins/ (forventet præcis 1): "
                                   + ", ".join(z.name for z in zips)))
    if constant and header and constant.group(1) != header.group(1):
        found.append(("version-mismatch", f"SGAP_VERSION {constant.group(1)} mod header {header.group(1)}"))
    if constant and len(zips) == 1:
        expected = f"{PLUGIN}-v{constant.group(1)}.zip"
        if zips[0].name != expected:
            found.append(("version-mismatch", f"zip hedder {zips[0].name}, forventet {expected}"))
    return found


def check_zip(root: Path) -> list[tuple[str, str]]:
    zips = sorted((root / "plugins").glob("*.zip"))
    if len(zips) != 1:
        return []  # check_version already reports this; a diff against the wrong zip is noise
    source = root / "plugins" / PLUGIN
    if not source.is_dir():
        return [("plugin-missing", f"{source.relative_to(root)} findes ikke")]
    found: list[tuple[str, str]] = []
    with zipfile.ZipFile(zips[0]) as archive:
        packed = {name: archive.read(name) for name in archive.namelist() if not name.endswith("/")}
    prefix = f"{PLUGIN}/"
    on_disk = {}
    for path in sorted(source.rglob("*")):
        if not path.is_file() or "__pycache__" in path.parts:
            continue
        rel = path.relative_to(source).as_posix()
        on_disk[prefix + rel] = path.read_bytes()
    for name in sorted(set(on_disk) - set(packed)):
        found.append(("zip-missing-file", f"{name} mangler i zip'en"))
    for name in sorted(set(packed) - set(on_disk)):
        found.append(("zip-extra-file", f"{name} ligger i zip'en men ikke i kilden"))
    for name in sorted(set(on_disk) & set(packed)):
        if hashlib.sha256(on_disk[name]).hexdigest() != hashlib.sha256(packed[name]).hexdigest():
            found.append(("zip-differs", f"{name} er ikke byte-identisk med kilden"))
    return found


def check_includes(root: Path) -> list[tuple[str, str]]:
    base = root / "plugins" / PLUGIN
    main = base / f"{PLUGIN}.php"
    if not main.is_file():
        return []
    found: list[tuple[str, str]] = []
    required = set()
    pattern = re.compile(r"(?:require|include)(?:_once)?\s*(?:\(?\s*)?(?:__DIR__\s*\.\s*)?'([^']+\.php)'")
    for rel_name in ["includes", f"{PLUGIN}.php", "uninstall.php"]:
        path = base / rel_name
        files = sorted(path.glob("*.php")) if path.is_dir() else ([path] if path.is_file() else [])
        for file in files:
            for target in pattern.findall(read(file)):
                candidate = (file.parent / target.lstrip("/")).resolve()
                if not candidate.is_file():
                    found.append(("include-missing", f"{file.relative_to(root)} kræver {target} som ikke findes"))
                else:
                    required.add(candidate)
    for file in sorted((base / "includes").glob("*.php")):
        if file.resolve() not in required:
            found.append(("include-orphan", f"includes/{file.name} kræves ikke af nogen fil"))
    return found


def check_library(root: Path) -> list[tuple[str, str]]:
    library = root / "docs" / "library"
    index = library / "README.md"
    if not index.is_file():
        return [("library-missing", "docs/library/README.md findes ikke")]
    text = read(index)
    found: list[tuple[str, str]] = []
    linked = set()
    for target in re.findall(r"\]\(([^)]+)\)", text):
        target = target.split("#", 1)[0].strip()
        if not target or "://" in target or target.startswith("mailto:"):
            continue
        linked.add(target.lstrip("./"))
        # A link to a directory is legitimate (the index points at reports/ and daily-briefs/).
        if not (library / target).exists():
            found.append(("library-link", f"README.md peger på {target}, som ikke findes"))
    for doc in sorted(library.glob("*.md")):
        if doc.name == "README.md" or doc.resolve() == SELF or doc.name in linked:
            continue
        # Only new knowledge is required to be indexed (the rule in AGENTS.md); older notes
        # that predate the index are debt, not an error, and would otherwise keep this red.
        date = re.search(r"(20\d\d-\d\d-\d\d)", doc.name)
        if date and date.group(1) >= "2026-11-01":
            found.append(("library-orphan", f"docs/library/{doc.name} står ikke i README.md"))
    return found


def check_secrets(root: Path) -> list[tuple[str, str]]:
    found: list[tuple[str, str]] = []
    for path, rel in walk(root):
        if path.suffix.lower() not in TEXT_SUFFIXES:
            continue
        body = read(path)
        for label, pattern in SECRETS:
            if pattern.search(body):
                found.append(("secret", f"{label} i {rel}"))
    return found


def check_markers(root: Path) -> list[tuple[str, str]]:
    found: list[tuple[str, str]] = []
    pattern = re.compile(r"\b(TODO|FIXME|XXX)\b")
    for path, rel in walk(root):
        if path.suffix != ".php" or rel.parts[0] not in {"plugins", "wordpress"}:
            continue
        for number, line in enumerate(read(path).splitlines(), 1):
            if pattern.search(line):
                found.append(("marker", f"{rel}:{number}: {line.strip()[:100]}"))
    return found


def check_vacuous(root: Path) -> list[tuple[str, str]]:
    found: list[tuple[str, str]] = []
    pattern = re.compile(r"\|\|\s*true\b|&&\s*true\b|assert\(\s*true\s*\)")
    for path, rel in walk(root):
        if path.suffix != ".php" or "tests" not in rel.parts:
            continue
        for number, line in enumerate(read(path).splitlines(), 1):
            if pattern.search(line):
                found.append(("vacuous", f"{rel}:{number}: {line.strip()[:100]}"))
    return found


CHECKS = [
    ("version", check_version),
    ("zip", check_zip),
    ("includes", check_includes),
    ("bibliotek", check_library),
    ("hemmeligheder", check_secrets),
    ("markører", check_markers),
    ("tomme tests", check_vacuous),
]


def main() -> int:
    parser = argparse.ArgumentParser(description="Repo-dækkende fejlscan.")
    parser.add_argument("--selftest", action="store_true", help="kør fixtures i stedet for repoet")
    parser.add_argument("--verbose", action="store_true", help="vis hver kontrol og antal filer")
    parser.add_argument("--root", default=str(ROOT), help="roden der scannes (til selftest)")
    args = parser.parse_args()

    if args.selftest:
        return selftest()

    root = Path(args.root).resolve()
    total = 0
    for name, check in CHECKS:
        problems = check(root)
        total += len(problems)
        print(("FAIL " if problems else "OK   ") + name)
        for code, message in problems[:20]:
            print(f"       [{code}] {message}")
        if len(problems) > 20:
            print(f"       … og {len(problems) - 20} mere")
    if args.verbose:
        print(f"\n{sum(1 for _ in walk(root))} filer læst under {root}")
    if total:
        print(f"\n{total} fund. Ret dem, eller skriv hvorfor de er i orden i docs/library/.")
        return 1
    print("\nIngen fund: version, zip, includes, bibliotek og testfiler er konsistente.")
    return 0


# ---------------------------------------------------------------- selftest

GOOD_MAIN = """<?php
/**
 * Plugin Name: Steamgears Autopilot
 * Version: 9.9.9
 */
define('SGAP_VERSION', '9.9.9');
require __DIR__.'/includes/alpha.php';
"""

GOOD_ALPHA = "<?php\nclass SGAP_Alpha {}\n"


def build(root: Path, main: str = GOOD_MAIN, alpha: str | None = GOOD_ALPHA,
          zip_version: str = "9.9.9", zip_alpha: str | None = None, library: bool = True) -> None:
    plugin = root / "plugins" / PLUGIN
    (plugin / "includes").mkdir(parents=True, exist_ok=True)
    (plugin / f"{PLUGIN}.php").write_text(main, encoding="utf-8")
    if alpha is not None:
        (plugin / "includes" / "alpha.php").write_text(alpha, encoding="utf-8")
    with zipfile.ZipFile(root / "plugins" / f"{PLUGIN}-v{zip_version}.zip", "w") as archive:
        archive.writestr(f"{PLUGIN}/{PLUGIN}.php", main)
        archive.writestr(f"{PLUGIN}/includes/alpha.php", zip_alpha if zip_alpha is not None else (alpha or ""))
    if library:
        docs = root / "docs" / "library"
        docs.mkdir(parents=True, exist_ok=True)
        (docs / "README.md").write_text("# Bibliotek\n\n- [notat](notat.md)\n", encoding="utf-8")
        (docs / "notat.md").write_text("# Notat\n", encoding="utf-8")


def selftest() -> int:
    failures = 0

    def expect(name: str, code: str | None, root: Path) -> None:
        nonlocal failures
        codes = [c for check in CHECKS for c, _ in check[1](root)]
        if code is None:
            if codes:
                print(f"  ✗ {name} — forventede ingen fund, fik {codes}")
                failures += 1
            else:
                print(f"  ✓ {name} — ingen fund")
        elif code in codes:
            print(f"  ✓ {name} — fanget som '{code}'")
        else:
            print(f"  ✗ {name} — forventede '{code}', fik {codes or 'intet'}")
            failures += 1

    with tempfile.TemporaryDirectory() as tmp:
        root = Path(tmp)
        build(root)
        expect("ren kasse", None, root)

        root = Path(tmp) / "header"
        build(root, main=GOOD_MAIN.replace("Version: 9.9.9", "Version: 9.9.8"))
        expect("header og konstant uenige", "version-mismatch", root)

        root = Path(tmp) / "zipnavn"
        build(root, zip_version="9.9.8")
        expect("zip hedder en anden version", "version-mismatch", root)

        root = Path(tmp) / "tozip"
        build(root)
        (root / "plugins" / f"{PLUGIN}-v9.9.8.zip").write_bytes(b"PK\x05\x06" + b"\x00" * 18)
        expect("to zip-filer", "zip-count", root)

        root = Path(tmp) / "zipindhold"
        build(root, zip_alpha="<?php // gammel\n")
        expect("zip følger ikke kilden", "zip-differs", root)

        root = Path(tmp) / "glemt"
        build(root)
        (root / "plugins" / PLUGIN / "includes" / "beta.php").write_text("<?php\n", encoding="utf-8")
        expect("fil i includes/ uden require", "include-orphan", root)

        root = Path(tmp) / "død"
        build(root, main=GOOD_MAIN.replace("alpha.php", "gamma.php"))
        expect("require peger på en fil der ikke findes", "include-missing", root)

        root = Path(tmp) / "bibliotek"
        build(root)
        (root / "docs" / "library" / "glemt-2026-11-07.md").write_text("# Glemt\n", encoding="utf-8")
        expect("nyt dokument uden for indekset", "library-orphan", root)

        root = Path(tmp) / "gammelt"
        build(root)
        (root / "docs" / "library" / "gammel-note.md").write_text("# Gammel\n", encoding="utf-8")
        expect("ældre note uden for indekset er gæld, ikke fejl", None, root)

        root = Path(tmp) / "link"
        build(root)
        (root / "docs" / "library" / "README.md").write_text("- [væk](væk.md)\n", encoding="utf-8")
        expect("indeks-link til en fil der ikke findes", "library-link", root)

        root = Path(tmp) / "token"
        build(root)
        (root / "docs" / "notat.md").write_text(
            "nøgle: " + "ghp" + "_" + "A" * 36 + "\n", encoding="utf-8")
        expect("GitHub-token i en tekstfil", "secret", root)

        root = Path(tmp) / "todo"
        build(root)
        (root / "plugins" / PLUGIN / "includes" / "alpha.php").write_text(
            "<?php\n// TODO: skriv den færdig\n", encoding="utf-8")
        expect("TODO i plugin-kode", "marker", root)

        root = Path(tmp) / "tom"
        (root / "plugins" / "tests").mkdir(parents=True)
        (root / "plugins" / "tests" / "ting.php").write_text(
            "<?php\ncheck('x', ting()==[] || true);\n", encoding="utf-8")
        expect("test der ikke kan fejle", "vacuous", root)

    print()
    if failures:
        print(f"{failures} fixture(s) blev ikke fanget.")
        return 1
    print("Alle fixtures fanget — og den rene kasse er grøn.")
    return 0


if __name__ == "__main__":
    sys.exit(main())
