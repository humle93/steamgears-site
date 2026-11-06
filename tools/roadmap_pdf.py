#!/usr/bin/env python3
"""roadmap_pdf.py — én PDF med roadmap, dokumentation og tid-til-100 %.

Kør:
    PYTHONPATH=tools/_deps python3 tools/roadmap_pdf.py
Ud:
    reports/roadmap-og-dokumentation-<dato>.pdf

Indhold: status og rest-tid (beregnet fra projektstatus.json), derefter de tre
roadmaps (site, plugin, Command Center) og Command Centers dokumentation, sidst
et indeks over docs/. Procenter og rest-tid tastes ikke ind — de beregnes.
"""

from __future__ import annotations

import json
import os
import re
from datetime import date
from pathlib import Path

from fpdf import FPDF

ROOT = Path(__file__).resolve().parent.parent
OUT = ROOT / "reports"
TODAY = date.today().isoformat()

NAVY = (31, 42, 55)
COPPER = (184, 115, 51)
GREY = (110, 118, 128)
RULE = (214, 219, 226)
INK = (28, 33, 40)
GREEN = (22, 120, 78)
AMBER = (176, 122, 12)

# Observeret takt 2/11 2026 (dev-dage pr. kalenderdag), udledt af projektstatus.json's historik:
# site 3,6 → 50,7 % på én dag = 9,5 af 21,1 d; plugin 0,45 → 3,45 d på én dag = 3,0 d.
PACE = {"site": 9.5, "plugin": 3.0, "rebrand": 8.5, "seo": 3.0}

DOCS = [
    ("Site — roadmap", ROOT / "docs" / "roadmap.md"),
    ("Plugin — Autopilot roadmap", ROOT / "plugins" / "steamgears-autopilot" / "ROADMAP.md"),
    ("Command Center — roadmap", ROOT / "docs" / "library" / "command-center" / "ROADMAP.md"),
    ("Command Center — arkitektur og sikkerhed", ROOT / "docs" / "library" / "command-center" / "Command_Center_Dokumentation.md"),
    ("Command Center — bruger- og systemmanual", ROOT / "docs" / "library" / "command-center" / "Command_Center_Bruger_og_Systemmanual.md"),
    ("Plugin-audit 2/11 2026", ROOT / "docs" / "library" / "plugin-audit-2026-11-02.md"),
]

FONT_DIRS = [
    "/usr/share/fonts",
    "/usr/local/share/fonts",
    "/opt/freestyle/python/lib/python3.12/site-packages/matplotlib/mpl-data/fonts/ttf",
    str(ROOT / "tools" / "_deps"),
]


def find_fonts() -> tuple[str | None, str | None]:
    pairs = [("DejaVuSans.ttf", "DejaVuSans-Bold.ttf"), ("LiberationSans-Regular.ttf", "LiberationSans-Bold.ttf")]
    for regular, bold in pairs:
        for base in FONT_DIRS:
            if not os.path.isdir(base):
                continue
            for root, _dirs, files in os.walk(base):
                if regular in files and bold in files:
                    return os.path.join(root, regular), os.path.join(root, bold)
    return None, None


REG, BOLD = find_fonts()


def num(value: float) -> str:
    text = f"{round(float(value), 2):.2f}".rstrip("0").rstrip(".")
    return (text or "0").replace(".", ",")


def status_rows() -> list[dict]:
    data = json.loads((ROOT / "projektstatus.json").read_text(encoding="utf-8"))
    rows = []
    for project in data.get("projects", []):
        modules = [m for m in project.get("modules", []) if not m.get("recurring")]
        if not modules:
            continue
        done = sum(float(m["done"]) for m in modules)
        total = sum(float(m["total"]) for m in modules)
        unit = modules[0].get("unit", "")
        pct = done / total * 100 if total else 0.0
        rest = total - done
        pace = PACE.get(project.get("id", ""), 0)
        eta = rest / pace if pace else None
        rows.append({
            "name": project.get("name", project.get("id")),
            "pct": pct,
            "done": done,
            "total": total,
            "rest": rest,
            "unit": unit,
            "eta": eta,
            "next": project.get("next") or "—",
            "blocked": project.get("blocked_by") or "—",
            "modules": modules,
        })
    return rows


def clean(text: str) -> str:
    # Bevar inline-kode, så pladsholdere som `/go/<id>/` og `<script>` ikke
    # forveksles med HTML-tags og forsvinder fra PDF'en.
    code: list[str] = []

    def stash(match: re.Match[str]) -> str:
        code.append(match.group(1))
        return f"\x00{len(code) - 1}\x00"

    text = re.sub(r"`([^`]*)`", stash, text)
    text = re.sub(r"!\[[^\]]*\]\([^)]*\)", "", text)
    text = re.sub(r"\[([^\]]+)\]\([^)]*\)", r"\1", text)
    text = text.replace("**", "").replace("__", "")
    text = re.sub(r"<[^>]+>", "", text)
    text = re.sub(r"\x00(\d+)\x00", lambda m: code[int(m.group(1))], text)
    # DejaVu har ikke emoji: fjern symbolblokkene, så PDF'en ikke advarer pr. side
    text = re.sub(r"[\U0001F000-\U0001FAFF\u2600-\u27BF\uFE0F\u2B00-\u2BFF]", "", text)
    return text.replace("→", "->").replace("·", "·").strip()


class Report(FPDF):
    def __init__(self) -> None:
        super().__init__(format="A4")
        self.set_auto_page_break(True, margin=16)
        self.set_margins(16, 16, 16)
        if REG:
            self.add_font("body", "", REG)
            self.add_font("body", "B", BOLD)
            self.body = "body"
        else:
            self.body = "Helvetica"
        self.set_font(self.body, "", 9.5)

    def footer(self) -> None:
        if self.page_no() == 1:
            return
        self.set_y(-12)
        self.set_font(self.body, "", 7.5)
        self.set_text_color(*GREY)
        self.cell(0, 5, f"Steamgears · roadmap og dokumentation · {TODAY}", align="L")
        self.cell(0, 5, str(self.page_no()), align="R")

    # ---- byggeklodser -------------------------------------------------
    def mc(self, width: float, height: float, text: str, **kwargs) -> None:
        """multi_cell der altid fortsætter ved venstre margin (ellers vandrer x mod højre)."""
        if width == 0:
            self.set_x(self.l_margin)
        self.multi_cell(width, height, text, new_x="LMARGIN", new_y="NEXT", **kwargs)

    def h1(self, text: str, new_page: bool = True) -> None:
        if new_page:
            self.add_page()
        self.set_font(self.body, "B", 19)
        self.set_text_color(*NAVY)
        self.mc(0, 9, clean(text))
        self.set_draw_color(*COPPER)
        self.set_line_width(0.6)
        y = self.get_y() + 2
        self.line(16, y, 194, y)
        self.ln(5)

    def h2(self, text: str) -> None:
        self.ln(2)
        self.set_font(self.body, "B", 12.5)
        self.set_text_color(*NAVY)
        self.mc(0, 6.4, clean(text))
        self.ln(1)

    def h3(self, text: str) -> None:
        self.ln(1)
        self.set_font(self.body, "B", 10.5)
        self.set_text_color(*COPPER)
        self.mc(0, 5.6, clean(text))

    def para(self, text: str, size: float = 9.5, indent: float = 0) -> None:
        self.set_font(self.body, "", size)
        self.set_text_color(*INK)
        if indent:
            self.set_x(16 + indent)
        self.mc(194 - 16 - indent, 4.9, clean(text))

    def _header(self, headers: list[str], widths: list[float], size: float) -> None:
        self.set_font(self.body, "B", size)
        self.set_fill_color(*NAVY)
        self.set_text_color(255, 255, 255)
        x0, y0 = self.get_x(), self.get_y()
        for head, width in zip(headers, widths):
            self.set_xy(x0, y0)
            self.multi_cell(width, 5.2, clean(head), border=0, fill=True, max_line_height=5.2)
            x0 += width
        self.set_xy(16, y0 + 5.2)
        self.set_text_color(*INK)
        self.set_font(self.body, "", size)

    def table(self, headers: list[str], rows: list[list[str]], widths: list[float], size: float = 8.5) -> None:
        self._header(headers, widths, size)
        for index, row in enumerate(rows):
            heights = [
                len(self.multi_cell(width, 4.4, clean(str(value)), split_only=True)) * 4.4
                for value, width in zip(row, widths)
            ]
            height = max(heights)
            if self.get_y() + height > 268:
                self.add_page()
                self._header(headers, widths, size)
            fill = index % 2 == 1
            if fill:
                self.set_fill_color(*RULE)
            x0, y0 = self.get_x(), self.get_y()
            for value, width in zip(row, widths):
                self.set_xy(x0, y0)
                self.multi_cell(width, 4.4, clean(str(value)), border=0, fill=fill, max_line_height=4.4)
                x0 += width
            self.set_xy(16, y0 + height)
            self.set_draw_color(*RULE)
            self.line(16, self.get_y(), 194, self.get_y())
        self.ln(1.5)

    # ---- markdown -----------------------------------------------------
    def markdown(self, text: str) -> None:
        in_code = False
        for raw in text.splitlines():
            line = raw.rstrip()
            if line.startswith("```"):
                in_code = not in_code
                continue
            if in_code:
                if line.strip():
                    self.para(line, size=8, indent=3)
                continue
            if not line.strip():
                self.ln(1.6)
                continue
            if line.startswith("<!--"):
                continue
            stripped = line.lstrip("#")
            level = len(line) - len(stripped)
            if level >= 1 and line.startswith("#"):
                text_value = stripped.strip()
                if level == 1:
                    self.h1(text_value, new_page=False)
                elif level == 2:
                    self.h2(text_value)
                else:
                    self.h3(text_value)
                continue
            if line.startswith("|"):
                cells = [c.strip() for c in line.strip("|").split("|")]
                if all(set(c) <= set("-: ") for c in cells):
                    continue
                self.set_font(self.body, "", 8.2)
                self.set_text_color(*INK)
                self.mc(178, 4.4, clean(" · ".join(c for c in cells if c)))
                continue
            if re.match(r"^\s*[-*+]\s", line):
                self.para("• " + re.sub(r"^\s*[-*+]\s+", "", line), indent=3)
                continue
            if re.match(r"^\s*\d+\.\s", line):
                self.para(line.strip(), indent=3)
                continue
            if line.startswith(">"):
                self.para(line.lstrip("> ").strip(), size=9, indent=3)
                continue
            self.para(line)


def main() -> None:
    rows = status_rows()
    OUT.mkdir(parents=True, exist_ok=True)
    pdf = Report()

    # ---------------------------------------------------------- forside
    pdf.add_page()
    pdf.ln(30)
    pdf.set_font(pdf.body, "B", 30)
    pdf.set_text_color(*NAVY)
    pdf.mc(0, 13, "Steamgears")
    pdf.set_font(pdf.body, "B", 20)
    pdf.set_text_color(*COPPER)
    pdf.mc(0, 10, "Roadmap, dokumentation og tid til 100 %")
    pdf.ln(4)
    pdf.set_draw_color(*COPPER)
    pdf.set_line_width(0.8)
    pdf.line(16, pdf.get_y(), 90, pdf.get_y())
    pdf.ln(8)
    pdf.set_font(pdf.body, "", 11)
    pdf.set_text_color(*INK)
    pdf.mc(0, 6, f"Udarbejdet {TODAY} · alle tal beregnet fra projektstatus.json og roadmap-filerne i repoet.")
    pdf.ln(2)
    pdf.mc(0, 6, "Kilder: docs/roadmap.md · plugins/steamgears-autopilot/ROADMAP.md · "
                 "docs/library/command-center/ (hentet fra humle93/command-center)")

    # ---------------------------------------------------------- status
    pdf.h1("1. Status og tid til 100 %")
    pdf.para("Procenterne er beregnet som gennemført divideret med estimeret total. Rest-tiden er "
             "total minus gennemført, omregnet til kalenderdage med den takt der blev målt 2/11 2026 "
             "(site 9,5 dev-dage pr. dag, plugin 3,0, rebrand 8,5 timer pr. dag). Blokerede dele kan "
             "ikke tælles med, og de står i kolonnen Blokeret.")
    pdf.ln(2)
    table_rows = []
    for row in rows:
        eta = "—" if row["eta"] is None else f"{num(row['eta'])} dag"
        table_rows.append([
            row["name"],
            f"{num(row['pct'])} %",
            f"{num(row['rest'])} af {num(row['total'])} {row['unit']}",
            eta,
        ])
    pdf.table(["Projekt", "Status", "Rest", "Til 100 % (takt)"], table_rows, [58, 20, 48, 52])

    pdf.ln(3)
    pdf.h2("Hvad der mangler, projekt for projekt")
    for row in rows:
        pdf.h3(f"{row['name']} — {num(row['pct'])} %, rest {num(row['rest'])} {row['unit']}")
        pdf.para("Næste: " + row["next"])
        pdf.para("Blokeret af: " + row["blocked"])
        for module in row["modules"]:
            rest = float(module["total"]) - float(module["done"])
            if rest <= 0:
                continue
            pdf.para(f"• {module['name']}: {num(rest)} af {num(module['total'])} {module['unit']} tilbage",
                     size=9, indent=3)

    pdf.ln(3)
    pdf.h2("Samlet svar på »hvor lang tid til 100 %?«")
    total_rest = sum(r["rest"] for r in rows if r["unit"] == "d")
    pdf.para(f"Ren arbejdsmængde: {num(total_rest)} udviklerdage tilbage på tværs af site og plugin, "
             f"plus {num(5)} timer mockups i rebrand og 3 SEO-guides.")
    pdf.para("Med den målte takt er sitet færdigt i løbet af 1–2 dage og pluginnet i 6–7 dage, hvis "
             "P1 og P2 arbejdes uden stop — men P1/P2 er ikke påbegyndt, så de tal er planlægnings-tal, "
             "ikke målinger. Realistisk planlægningshorisont: site 2 dage, plugin 8–10 dage, rebrand "
             "1 dag (når Printify-adgang er der), SEO-guides 1 dag. Blokeringerne hos host, Printify og "
             "Redirection kan ikke arbejdes væk herfra og skal trækkes ud af regnestykket.")

    # ---------------------------------------------------------- roadmaps
    for title, path in DOCS:
        text = path.read_text(encoding="utf-8") if path.exists() else f"_(mangler: {path})_"
        pdf.h1(title)
        pdf.markdown(text)

    # ---------------------------------------------------------- indeks
    pdf.h1("Dokumentationsindeks")
    index_rows = []
    for path in sorted((ROOT / "docs").rglob("*.md")):
        rel = path.relative_to(ROOT)
        first_heading = ""
        for line in path.read_text(encoding="utf-8", errors="replace").splitlines():
            if line.startswith("#"):
                first_heading = clean(line.lstrip("#").strip())
                break
        index_rows.append([str(rel), first_heading[:70]])
    pdf.para(f"{len(index_rows)} Markdown-dokumenter i docs/. Kommandocentralens egne dokumenter ligger "
             f"under docs/library/command-center/ og er kopieret fra Command Center-repoet.")
    pdf.ln(1)
    pdf.table(["Fil", "Første overskrift"], index_rows, [78, 100], size=7.5)

    target = OUT / f"roadmap-og-dokumentation-{TODAY}.pdf"
    pdf.output(str(target))
    print(json.dumps({"pdf": str(target.relative_to(ROOT)), "pages": pdf.page_no(), "bytes": target.stat().st_size}))


if __name__ == "__main__":
    main()
