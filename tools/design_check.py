#!/usr/bin/env python3
"""tools/design_check.py — værn for designsystemet i steamgears-site.

`css/sg-design-system.css` er det ene sted der bestemmer hvordan steamgears.eu ser ud.
Det er kun sandt så længe filen bliver ved med at være konsistent, og CSS fejler i stilhed:
en manglende klammer dræber resten af arket, en `outline: none` fjerner fokusringen, og en
farve der blev rettet «lidt» kan gå fra 9,5:1 til 3,8:1 uden at nogen ser det i en browser.

Dette værn kontrollerer fem ting på filen og én på loaderen:

  * **klammer** — `{` og `}` balancerer (kommentarer og strenge tælles ikke med).
  * **fokus** — ingen `outline: none`/`outline: 0`, og `:focus-visible` findes.
  * **selektorer** — de flader der *skal* være stylet: fokus, produktliste, skip-link,
    reduced-motion og forced-colors. Mangler en af dem, er laget ikke dækkende.
  * **kontrast** — tokens læses ud af `:root`, og hvert par i tabellen nedenfor skal både
    holde WCAG-kravet (4,5:1 tekst, 3:1 ikke-tekst) og stadig måle det dokumenterede tal.
    Det sidste fanger en «uskyldig» farveretning, som ellers først ville blive opdaget af
    en læser.
  * **variabler** — hver `var(--sg-…)` skal være defineret; ellers falder reglen tilbage
    til ingenting, og komponenten ser rigtig ud indtil nogen kigger.
  * **uafhængighed** — ingen `@import` og ingen `url(http…)`; arkets udseende må ikke
    afhænge af en tredjepart der kan svare 404.
  * **loaderen** — `wordpress/mu-plugins/sga-design-system.php` skal have `ABSPATH`-værn,
    kill-switch, `body_class`, `wp_footer` og pege på netop denne fil.

Kommandoer:

  python3 tools/design_check.py             # kontrollér filerne
  python3 tools/design_check.py --selftest  # 12 fixtures der skal fanges
  python3 tools/design_check.py --verbose   # vis hvert kontrastpar

Exitkoder: 0 = alt grønt, 1 = regelbrud, 2 = intern fejl eller fil mangler.
"""

from __future__ import annotations

import argparse
import re
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
CSS_PATH = ROOT / "css" / "sg-design-system.css"
LOADER_PATH = ROOT / "wordpress" / "mu-plugins" / "sga-design-system.php"

# Selektorer/flader laget ikke kan undvære. Bevidst korte strenge, så en omskrivning af
# reglen ikke vælter værnets dom — det er fladen der tælles, ikke formuleringen.
REQUIRED = (
    (":focus-visible", "fokusring på alle interaktive elementer"),
    (":where(a, button, input, select, textarea, summary", "fokusringen rammer også links og formularfelter"),
    (".woocommerce ul.products", "produktlisten er stylet (ellers står Woo's egen liste)"),
    (".single_add_to_cart_button", "køb-knappen er entydig på produktsiden"),
    (".sg-skip", "skip-link til hovedindholdet"),
    ("@media (max-width: 782px)", "reflow ved tablet/mobil"),
    ("@media (prefers-reduced-motion", "bevægelse respekteres"),
    ("@media (forced-colors", "Windows high contrast"),
    ("@media print", "udskrift"),
    ("body.sg-ui", "laget er afgrænset til body-klassen"),
)

# Dokumenterede kontrastpar. `min` er WCAG-kravet, `maal` det målte tal fra filens
# hovedkommentar. Tolerancen er 0,05, så en afrunding ikke giver støj, men en rettelse gør.
CONTRAST = (
    # id, forgrund, baggrund, krav, målt
    ("creme på blek", "sg-on-dark", "sg-ink", 4.5, 15.72),
    ("creme på panel", "sg-on-dark", "sg-surface", 4.5, 15.11),
    ("messing-tekst på panel", "sg-brass-text", "sg-surface", 4.5, 9.52),
    ("messing-tekst på blek", "sg-brass-text", "sg-ink", 4.5, 9.91),
    ("dæmpet tekst på panel", "sg-on-dark-muted", "sg-surface", 4.5, 9.38),
    ("blek på messing-knap", "sg-ink", "sg-brass", 4.5, 6.09),
    ("messing-linje på blek", "sg-brass", "sg-ink", 3.0, 6.09),
    ("fokusring på blek", "sg-focus", "sg-ink", 3.0, 12.73),
    # Bevidst under 3:1 — derfor den dobbelte ring: mørk inderring på messing-knappen.
    ("fokusring på messing", "sg-focus", "sg-brass", 0.0, 2.09),
)

# Ulven i fåreklæder: `!important` er tilladt, men hvert et af dem er en kamp med temaet.
# Over 30 betyder det at laget er begyndt at slås i stedet for at lægge sig ovenpå.
IMPORTANT_MAX = 30

TOLERANCE = 0.05


def lines_of(text: str) -> list[str]:
    return text.splitlines()


def line_no(text: str, pos: int) -> int:
    return text.count("\n", 0, pos) + 1


def strip_noise(css: str) -> str:
    """Fjerner kommentarer og strenge, så klammer og nøgleord tælles rigtigt."""
    css = re.sub(r"/\*.*?\*/", " ", css, flags=re.S)
    css = re.sub(r'"(?:\\.|[^"\\])*"', '""', css)
    return re.sub(r"'(?:\\.|[^'\\])*'", "''", css)


def check_braces(css: str) -> list[tuple[str, str]]:
    clean = strip_noise(css)
    depth = 0
    errors = []
    for i, ch in enumerate(clean):
        if ch == "{":
            depth += 1
        elif ch == "}":
            depth -= 1
            if depth < 0:
                errors.append(("brace", f"linje {line_no(css, i)}: '}}' uden en åbnende '{{'"))
                return errors
    if depth > 0:
        errors.append(("brace", f"{depth} '{{' blev aldrig lukket — resten af arket er dødt"))
    return errors


def check_outline(css: str) -> list[tuple[str, str]]:
    errors = []
    for m in re.finditer(r"outline\s*:\s*(none|0)\b", strip_noise(css)):
        errors.append(("outline", f"linje {line_no(css, m.start())}: outline fjernet uden erstatning"))
    if ":focus-visible" not in css:
        errors.append(("outline", "ingen :focus-visible — tastaturbrugere kan ikke se hvor de er"))
    return errors


def check_selectors(css: str) -> list[tuple[str, str]]:
    return [("selector", f"mangler '{needle}' ({why})") for needle, why in REQUIRED if needle not in css]


def check_remote(css: str) -> list[tuple[str, str]]:
    errors = []
    if "@import" in strip_noise(css):
        errors.append(("remote", "@import trækker et andet ark ind — arkets indhold skal stå her"))
    for m in re.finditer(r"url\(\s*['\"]?(https?:)?//", strip_noise(css), re.I):
        errors.append(("remote", f"linje {line_no(css, m.start())}: ekstern url() — må ikke afhænge af tredjepart"))
    return errors


def tokens(css: str) -> dict[str, str]:
    """Definitionerne i :root — første forekomst vinder, så senere tema-varianter ikke tæller."""
    found: dict[str, str] = {}
    for m in re.finditer(r"(--sg-[a-z0-9-]+)\s*:\s*([^;{}]+);", strip_noise(css)):
        found.setdefault(m.group(1), m.group(2).strip())
    return found


def check_vars(css: str) -> list[tuple[str, str]]:
    defined = {name[2:] for name in tokens(css)}
    used = set()
    for m in re.finditer(r"var\(\s*--(sg-[a-z0-9-]+)", strip_noise(css)):
        used.add(m.group(1))
    return [("var", f"--sg-{name} bruges men er ikke defineret") for name in sorted(used - defined)]


def check_important(css: str) -> list[tuple[str, str]]:
    count = len(re.findall(r"!\s*important", strip_noise(css), re.I))
    if count > IMPORTANT_MAX:
        return [("important", f"{count} !important (loft {IMPORTANT_MAX}) — laget slås med temaet i stedet for at ligge ovenpå")]
    return []


def srgb_to_luminance(hex_value: str) -> float:
    value = hex_value.strip().lstrip("#")
    if len(value) == 3:
        value = "".join(ch * 2 for ch in value)
    if len(value) != 6:
        raise ValueError(f"ikke en hex-farve: {hex_value!r}")
    channels = [int(value[i : i + 2], 16) / 255 for i in (0, 2, 4)]
    linear = [c / 12.92 if c <= 0.03928 else ((c + 0.055) / 1.055) ** 2.4 for c in channels]
    return 0.2126 * linear[0] + 0.7152 * linear[1] + 0.0722 * linear[2]


def contrast_ratio(fg: str, bg: str) -> float:
    a, b = srgb_to_luminance(fg), srgb_to_luminance(bg)
    hi, lo = max(a, b), min(a, b)
    return (hi + 0.05) / (lo + 0.05)


def check_contrast(css: str) -> list[tuple[str, str]]:
    defined = tokens(css)
    errors = []
    for label, fg_name, bg_name, minimum, measured in CONTRAST:
        fg, bg = defined.get(f"--{fg_name}"), defined.get(f"--{bg_name}")
        if not fg or not bg:
            errors.append(("contrast", f"{label}: token mangler (--{fg_name} eller --{bg_name})"))
            continue
        try:
            value = contrast_ratio(fg, bg)
        except ValueError as exc:
            errors.append(("contrast", f"{label}: {exc}"))
            continue
        if value + 0.001 < minimum:
            errors.append(("contrast", f"{label}: {value:.2f}:1 under kravet {minimum:.1f}:1"))
        if abs(value - measured) > TOLERANCE:
            errors.append(
                ("contrast", f"{label}: måler {value:.2f}:1, men filen dokumenterer {measured:.2f}:1")
            )
    return errors


def check_loader(loader: str) -> list[tuple[str, str]]:
    if not loader.strip():
        return [("loader", f"{LOADER_PATH.relative_to(ROOT)} mangler")]
    errors = []
    required = (
        ("defined( 'ABSPATH' )", "ABSPATH-værn"),
        ("sga_design_enabled", "kill-switch"),
        ("body_class", "'sg-ui' på body"),
        ("wp_footer", "printes efter temaets styles"),
        ("sg-design-system.css", "peger på css/sg-design-system.css"),
        ("sga_design_css", "læser filen ét sted fra"),
    )
    for needle, why in required:
        if needle not in loader:
            errors.append(("loader", f"loaderen mangler {why} ('{needle}')"))
    return errors


def run(css: str, loader: str | None = None) -> list[tuple[str, str]]:
    errors: list[tuple[str, str]] = []
    errors += check_braces(css)
    errors += check_outline(css)
    errors += check_selectors(css)
    errors += check_remote(css)
    errors += check_vars(css)
    errors += check_contrast(css)
    errors += check_important(css)
    if loader is not None:
        errors += check_loader(loader)
    return errors


FIXTURES = (
    # navn, css, loader, forventet kode der SKAL fanges
    ("ubalanceret klammer", ":root { --sg-ink: #14110E;\nbody { color: red; }", None, "brace"),
    ("outline fjernet", "a:focus-visible { outline: none; }", None, "outline"),
    ("ingen fokusring", "a:hover { color: red; }", None, "outline"),
    ("mangler produktliste", "", None, "selector"),
    ("ekstern url", ".x { background: url(https://cdn.example/x.png); }", None, "remote"),
    ("importeret ark", '@import url("x.css");', None, "remote"),
    ("udefineret variabel", ":root { --sg-ink: #14110E; }\na { color: var(--sg-fejl); }", None, "var"),
    ("for lidt !important", ":root { --sg-a: #fff; }\n" + "\n".join(".x { color: red !important; }" for _ in range(IMPORTANT_MAX + 1)), None, "important"),
    ("kontrast under krav", ":root { --sg-ink: #777777; --sg-on-dark: #888888; }", None, "contrast"),
    ("kontrast ændret i stilhed", ":root { --sg-ink: #14110E; --sg-on-dark: #F2EAD9; --sg-brass-text: #C0A070; }", None, "contrast"),
    ("loader uden værn", ":root { --sg-ink: #14110E; }", "<?php echo 'hej';", "loader"),
)

GOOD_CSS = """
:root { --sg-ink: #14110E; --sg-on-dark: #F2EAD9; --sg-brass: #B08D57; --sg-brass-text: #D8B87A;
        --sg-surface: #1B1510; --sg-on-dark-muted: #C9B99A; --sg-focus: #F0D27C; }
body.sg-ui { color: var(--sg-on-dark); background: var(--sg-ink); }
::where(a, button, input, select, textarea, summary):focus-visible { outline: 2px solid var(--sg-focus); }
.woocommerce ul.products { display: grid; }
.single_add_to_cart_button { background: var(--sg-brass); }
.sg-skip { position: absolute; }
@media (max-width: 782px) { .woocommerce ul.products { grid-template-columns: 1fr; } }
@media (prefers-reduced-motion: reduce) { * { transition-duration: .01ms; } }
@media (forced-colors: active) { a { outline: 3px solid Highlight; } }
@media print { .sg-skip { display: none; } }
"""

GOOD_LOADER = """
if ( ! defined( 'ABSPATH' ) ) { exit; }
function sga_design_enabled(): bool { return true; }
function sga_design_css(): string { return (string) @file_get_contents( __DIR__ . '/sg-design-system.css' ); }
add_filter( 'body_class', fn( $c ) => $c );
add_action( 'wp_footer', 'sga_design_css', 999 );
"""


def selftest() -> int:
    print(f"design_check --selftest: {len(FIXTURES)} fixtures")
    failures = 0
    for name, css, loader, expected in FIXTURES:
        codes = {code for code, _ in run(css, loader)}
        if expected in codes:
            print(f"  ✓ {name} — fanget som '{expected}'")
        else:
            print(f"  ✗ {name} — forventede '{expected}', fik {sorted(codes) or 'intet'}")
            failures += 1

    # Kontrollen skal også kunne sige OK: ellers er den bare en mur der altid er rød.
    codes = sorted({code for code, _ in run(GOOD_CSS, GOOD_LOADER)})
    if codes:
        print(f"  ✗ gyldigt fixture afvist: {codes}")
        failures += 1
    else:
        print("  ✓ gyldigt fixture godkendt")

    print(f"{'OK' if not failures else 'FEJL'}: {len(FIXTURES) + 1 - failures} af {len(FIXTURES) + 1} fixtures som forventet")
    return 0 if not failures else 1


def main() -> int:
    parser = argparse.ArgumentParser(description="Værn for css/sg-design-system.css og dets loader.")
    parser.add_argument("--selftest", action="store_true", help="kør fixtures i stedet for filerne")
    parser.add_argument("--verbose", action="store_true", help="vis hvert kontrastpar")
    args = parser.parse_args()

    if args.selftest:
        return selftest()

    if not CSS_PATH.is_file():
        print(f"FAIL: {CSS_PATH.relative_to(ROOT)} findes ikke")
        return 2

    css = CSS_PATH.read_text(encoding="utf-8")
    loader = LOADER_PATH.read_text(encoding="utf-8") if LOADER_PATH.is_file() else ""

    if args.verbose:
        defined = tokens(css)
        for label, fg_name, bg_name, minimum, measured in CONTRAST:
            fg, bg = defined.get(f"--{fg_name}", ""), defined.get(f"--{bg_name}", "")
            if fg and bg:
                print(f"  {label:26s} {contrast_ratio(fg, bg):5.2f}:1  (krav {minimum:.1f}, dokumenteret {measured:.2f})")

    errors = run(css, loader)
    lines = len(lines_of(css))
    print(f"design_check: {CSS_PATH.relative_to(ROOT)} ({lines} linjer), {len(REQUIRED)} påkrævede flader, "
          f"{len(CONTRAST)} kontrastpar, {len(tokens(css))} tokens")

    if errors:
        for code, message in errors:
            print(f"FAIL [{code}] {message}")
        print(f"{len(errors)} regelbrud")
        return 1

    print("OK: klammer, fokus, flader, variabler, kontrast og loader er som aftalt")
    return 0


if __name__ == "__main__":
    try:
        sys.exit(main())
    except KeyboardInterrupt:
        sys.exit(2)
