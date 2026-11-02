#!/usr/bin/env python3
"""md_to_pdf.py — render et dokument fra docs/library som printbar A4-PDF.

Kør:
  PYTHONPATH=tools/_deps python3 tools/md_to_pdf.py <input.md> <output.pdf> [--kicker TEKST]

Understøtter det Markdown-subset dokumenterne i docs/library bruger: h1–h3, afsnit,
punktopstilling, tabeller, kodeblokke, citatblokke, horisontale linjer samt inline
**fed**, `kode` og [link](url). Layoutet følger husstilen fra tools/seo_audit_report.py
(navy/copper, A4, 18 mm margin).

Fontvalg: DejaVu hvis den findes, ellers Helvetica med en ASCII-erstatning, så
tankestreger og pile ikke bliver spørgsmålstegn.
"""

from __future__ import annotations

import os
import re
import sys
from pathlib import Path

from fpdf import FPDF

# ------------------------------------------------------------------ husstil
NAVY = (31, 42, 55)
COPPER = (184, 115, 51)
GREY = (110, 118, 128)
RULE = (214, 219, 226)
ZEBRA = (246, 248, 250)
INK = (28, 33, 40)
PALE = (247, 248, 250)

MARGIN_L = 18
MARGIN_R = 18
MARGIN_T = 18
MARGIN_B = 20
PAGE_W = 210
CONTENT_W = PAGE_W - MARGIN_L - MARGIN_R

BODY = 9.6
LEAD = 4.75
TABLE_SIZE = 8.0
TABLE_LEAD = 3.85
CODE_SIZE = 7.9
CODE_LEAD = 3.55

FONT_DIRS = ["/usr/share/fonts", "/usr/local/share/fonts",
             "/opt/freestyle/python/lib/python3.12/site-packages/matplotlib/mpl-data/fonts/ttf"]


def find_fonts():
    pairs = [("DejaVuSans.ttf", "DejaVuSans-Bold.ttf"),
             ("LiberationSans-Regular.ttf", "LiberationSans-Bold.ttf")]
    for reg, bold in pairs:
        for base in FONT_DIRS:
            if not os.path.isdir(base):
                continue
            for root, _d, files in os.walk(base):
                if reg in files and bold in files:
                    return os.path.join(root, reg), os.path.join(root, bold)
    return None, None


REG_FONT, BOLD_FONT = find_fonts()
UNICODE = REG_FONT is not None
FONT = "Body" if UNICODE else "Helvetica"
MONO = "Courier"

ASCII_MAP = {"\u2014": " - ", "\u2013": "-", "\u2192": "->", "\u00b7": "|", "\u2022": "-",
             "\u2019": "'", "\u201c": '"', "\u201d": '"', "\u2026": "...",
             "\u00d7": "x", "\u2265": ">=", "\u2264": "<=", "\u2248": "~", "\u00ab": '"',
             "\u00bb": '"', "\u258f": "|"}


MONO_MAP = {**ASCII_MAP, "\u2014": "--", "\u2013": "-", "\u00b7": "|", "\u2026": "...",
            "\u00ab": '"', "\u00bb": '"', "\u201e": '"', "\u2018": "'"}


def san(text) -> str:
    """Til Body-fonten: Unicode er fint med DejaVu, ellers ASCII-erstatning."""
    text = str(text)
    if UNICODE:
        return text
    for a, b in ASCII_MAP.items():
        text = text.replace(a, b)
    return text.encode("latin-1", "replace").decode("latin-1")


def san_mono(text) -> str:
    """Til Courier: kernefonten er latin-1, så tankestreger skal konverteres uanset DejaVu."""
    text = str(text)
    for a, b in MONO_MAP.items():
        text = text.replace(a, b)
    return text.encode("latin-1", "replace").decode("latin-1")


# ------------------------------------------------------------------ inline
INLINE_RE = re.compile(r"(\*\*.+?\*\*|`[^`]+`|\[[^\]]+\]\([^)]+\))")


def plain(text: str) -> str:
    """Markdown uden markeringssyntaks — til tabelceller og målinger."""
    text = re.sub(r"\[([^\]]+)\]\([^)]*\)", r"\1", text)
    return text.replace("**", "").replace("`", "").strip()


def segments(text: str):
    """Opdel i (tekst, stil) hvor stil er '', 'B', 'M' (kode), 'L' (link), 'S' (url)."""
    out = []
    for part in INLINE_RE.split(text):
        if not part:
            continue
        if part.startswith("**") and part.endswith("**") and len(part) > 4:
            out.append((part[2:-2], "B"))
        elif part.startswith("`") and part.endswith("`") and len(part) > 2:
            out.append((part[1:-1], "M"))
        elif part.startswith("[") and "](" in part:
            label, url = part[1:-1].split("](", 1)
            label = label.replace("`", "")
            out.append((label, "L"))
            if url.startswith("http"):
                out.append((f" [{url}]", "S"))
        else:
            out.append((part, ""))
    return out


# ------------------------------------------------------------------ parser
def parse(lines):
    blocks = []
    i = 0
    while i < len(lines):
        raw = lines[i]
        line = raw.rstrip()

        if not line.strip():
            i += 1
            continue

        if line.startswith("```"):
            i += 1
            body = []
            while i < len(lines) and not lines[i].startswith("```"):
                body.append(lines[i].rstrip())
                i += 1
            i += 1
            blocks.append(("code", body))
            continue

        if re.fullmatch(r"-{3,}|\*{3,}", line.strip()):
            blocks.append(("hr", None))
            i += 1
            continue

        m = re.match(r"^(#{1,3})\s+(.*)$", line)
        if m:
            blocks.append((f"h{len(m.group(1))}", m.group(2).strip()))
            i += 1
            continue

        if line.startswith(">"):
            body = []
            while i < len(lines) and lines[i].startswith(">"):
                body.append(lines[i].lstrip(">").strip())
                i += 1
            blocks.append(("quote", " ".join(b for b in body if b)))
            continue

        if line.lstrip().startswith("|") and line.rstrip().endswith("|"):
            rows = []
            while i < len(lines):
                cur = lines[i].rstrip()
                if not (cur.lstrip().startswith("|") and cur.rstrip().endswith("|")):
                    break
                cells = [c.strip() for c in cur.strip().strip("|").split("|")]
                if not all(re.fullmatch(r":?-{2,}:?", c) for c in cells if c) or any(c for c in cells if c):
                    if not all(re.fullmatch(r":?-{2,}:?", c) for c in cells):
                        rows.append(cells)
                i += 1
            if rows:
                blocks.append(("table", rows))
            continue

        if re.match(r"^\s*[-*]\s+", raw):
            items = []
            while i < len(lines):
                cur = lines[i]
                bm = re.match(r"^\s*[-*]\s+(.*)$", cur)
                if bm:
                    items.append(bm.group(1).strip())
                    i += 1
                elif cur.strip() and cur.startswith("  ") and items:
                    items[-1] += " " + cur.strip()
                    i += 1
                else:
                    break
            blocks.append(("ul", items))
            continue

        if re.match(r"^\s*\d+\.\s+", raw):
            items = []
            while i < len(lines):
                cur = lines[i]
                nm = re.match(r"^\s*(\d+)\.\s+(.*)$", cur)
                if nm:
                    items.append((nm.group(1), nm.group(2).strip()))
                    i += 1
                elif cur.strip() and cur.startswith("   ") and items:
                    num, txt = items[-1]
                    items[-1] = (num, txt + " " + cur.strip())
                    i += 1
                else:
                    break
            blocks.append(("ol", items))
            continue

        para = [line.strip()]
        i += 1
        while i < len(lines):
            nxt = lines[i]
            if not nxt.strip():
                break
            if re.match(r"^(#{1,3}\s|```|>\s|\s*[-*]\s|\s*\d+\.\s|\|)", nxt) or re.fullmatch(r"-{3,}", nxt.strip()):
                break
            para.append(nxt.strip())
            i += 1
        blocks.append(("p", " ".join(para)))

    return blocks


# ------------------------------------------------------------------ PDF
class Doc(FPDF):
    def __init__(self, title: str, kicker: str):
        super().__init__(orientation="P", unit="mm", format="A4")
        self.doc_title = title
        self.kicker = kicker
        self.set_margins(MARGIN_L, MARGIN_T, MARGIN_R)
        self.set_auto_page_break(auto=True, margin=MARGIN_B)
        if UNICODE:
            self.add_font("Body", "", REG_FONT)
            self.add_font("Body", "B", BOLD_FONT)
        self.set_title(title)
        self.set_author("Buffy (Codebuff)")
        self.set_creator("tools/md_to_pdf.py")
        self.alias_nb_pages()

    def footer(self):
        self.set_y(-14)
        self.set_draw_color(*RULE)
        self.set_line_width(0.2)
        self.line(MARGIN_L, self.get_y(), PAGE_W - MARGIN_R, self.get_y())
        self.set_y(-11)
        self.set_font(FONT, "", 7.4)
        self.set_text_color(*GREY)
        self.cell(CONTENT_W / 2, 5, san(self.doc_title), align="L")
        self.cell(CONTENT_W / 2, 5, san(f"side {self.page_no()}/{{nb}}"), align="R")


def render(md_path: Path, pdf_path: Path, kicker: str):
    lines = md_path.read_text(encoding="utf-8").splitlines()
    blocks = parse(lines)

    title = next((txt for kind, txt in blocks if kind == "h1"), md_path.stem)
    doc = Doc(title, kicker)
    doc.add_page()

    left = MARGIN_L
    first_h1 = True

    def write_runs(text, indent=0.0):
        """Skriver inline-runs med hængende indrykning og egen ordvis ombrydning.

        fpdf2's write() bryder et ord midt over når skriften skiftes undervejs (fx **fed**
        inde i en sætning) og mister mellemrum mellem to runs. Her styres x/y selv, så et
        ord kun flyttes — aldrig deles, medmindre det er bredere end hele spalten.
        """
        origin = left + indent
        x_max = doc.w - MARGIN_R
        state = {"x": origin, "y": doc.get_y()}
        pending = [False]  # er der et mellemrum der venter på næste ord?

        def newline():
            state["x"] = origin
            state["y"] += LEAD
            if state["y"] + LEAD > doc.h - MARGIN_B:
                doc.add_page()
                state["y"] = MARGIN_T

        def put(fragment):
            width = doc.get_string_width(fragment)
            doc.set_xy(state["x"], state["y"])
            doc.cell(width, LEAD, fragment)
            state["x"] += width

        for chunk, style in segments(text):
            if style == "B":
                doc.set_font(FONT, "B", BODY)
                doc.set_text_color(*INK)
                chunk = chunk.replace("`", "")
            elif style == "M":
                doc.set_font(MONO, "", CODE_SIZE + 0.4)
                doc.set_text_color(*NAVY)
            elif style == "L":
                doc.set_font(FONT, "", BODY)
                doc.set_text_color(*COPPER)
            elif style == "S":
                doc.set_font(FONT, "", 6.9)
                doc.set_text_color(*GREY)
            else:
                doc.set_font(FONT, "", BODY)
                doc.set_text_color(*INK)
            parts = re.split(r"(\s+)", san_mono(chunk) if style == "M" else san(chunk))
            for token in parts:
                if not token:
                    continue
                if token.isspace():
                    # Mellemrum hører til det følgende ord — også når ordet ligger i næste
                    # run, ellers smelter to runs sammen ("kø-payloadenclockwork-punk…").
                    pending[0] = True
                    continue
                at_start = state["x"] <= origin + 0.01
                unit = token if (at_start or not pending[0]) else " " + token
                pending[0] = False
                if state["x"] + doc.get_string_width(unit) > x_max and not at_start:
                    newline()
                    unit = token
                while state["x"] + doc.get_string_width(unit) > x_max and len(unit) > 1:
                    cut = 1
                    while cut < len(unit) and state["x"] + doc.get_string_width(unit[:cut + 1]) <= x_max:
                        cut += 1
                    put(unit[:cut])
                    unit = unit[cut:]
                    newline()
                put(unit)
        doc.set_xy(left, state["y"] + LEAD + 0.9)

    for kind, value in blocks:
        if kind == "h1":
            parts = value.split(" — ", 1)
            heading = parts[0]
            subtitle = parts[1] if len(parts) > 1 else ""
            if not first_h1:
                doc.add_page()
            first_h1 = False
            doc.set_font(FONT, "B", 8.2)
            doc.set_text_color(*COPPER)
            doc.cell(0, 5, san(kicker.upper()), align="L", new_x="LMARGIN", new_y="NEXT")
            doc.ln(2)
            doc.set_font(FONT, "B", 19)
            doc.set_text_color(*NAVY)
            doc.multi_cell(CONTENT_W, 8.6, san(heading), align="L", new_x="LMARGIN", new_y="NEXT")
            if subtitle:
                doc.set_font(FONT, "", 10.2)
                doc.set_text_color(*GREY)
                doc.multi_cell(CONTENT_W, 5.2, san(subtitle), align="L", new_x="LMARGIN", new_y="NEXT")
            doc.ln(3)

        elif kind == "h2":
            doc.ln(2.5)
            if doc.get_y() > 297 - MARGIN_B - 26:
                doc.add_page()
            doc.set_font(FONT, "B", 12.4)
            doc.set_text_color(*NAVY)
            y = doc.get_y()
            doc.multi_cell(CONTENT_W, 6.2, san(value), align="L", new_x="LMARGIN", new_y="NEXT")
            doc.set_draw_color(*COPPER)
            doc.set_line_width(0.55)
            doc.line(left, y + 6.6, left + 24, y + 6.6)
            doc.ln(3.4)

        elif kind == "h3":
            doc.ln(1.6)
            if doc.get_y() > 297 - MARGIN_B - 20:
                doc.add_page()
            doc.set_font(FONT, "B", 10.3)
            doc.set_text_color(*INK)
            doc.multi_cell(CONTENT_W, 5.4, san(value), align="L", new_x="LMARGIN", new_y="NEXT")
            doc.ln(1.4)

        elif kind == "p":
            write_runs(value, indent=0.0)
            doc.ln(0.7)

        elif kind == "ul":
            for item in value:
                doc.set_font(FONT, "B", BODY)
                doc.set_text_color(*COPPER)
                doc.set_xy(left, doc.get_y())
                doc.cell(4.4, LEAD, san("\u2022") if UNICODE else "-")
                write_runs(item, indent=4.8)
            doc.ln(1.6)

        elif kind == "ol":
            for num, item in value:
                doc.set_font(FONT, "B", BODY)
                doc.set_text_color(*COPPER)
                doc.set_xy(left, doc.get_y())
                doc.cell(5.6, LEAD, san(f"{num}."))
                write_runs(item, indent=6.0)
            doc.ln(1.6)

        elif kind == "quote":
            doc.ln(0.8)
            y = doc.get_y()
            doc.set_font(FONT, "", 8.7)
            doc.set_text_color(*GREY)
            doc.set_x(left + 4)
            doc.multi_cell(CONTENT_W - 4, 4.3, san(plain(value)), align="L", new_x="LMARGIN", new_y="NEXT")
            doc.set_draw_color(*COPPER)
            doc.set_line_width(0.7)
            doc.line(left, y, left, doc.get_y() - 1)
            doc.ln(3)

        elif kind == "code":
            doc.ln(0.8)
            height = len(value) * CODE_LEAD + 4
            if doc.get_y() + height > 297 - MARGIN_B:
                doc.add_page()
            y = doc.get_y()
            doc.set_fill_color(*PALE)
            doc.rect(left, y, CONTENT_W, height, style="F")
            doc.set_draw_color(*RULE)
            doc.set_line_width(0.15)
            doc.rect(left, y, CONTENT_W, height, style="D")
            doc.set_xy(left + 3, y + 2)
            doc.set_font(MONO, "", CODE_SIZE)
            doc.set_text_color(*INK)
            for code_line in value:
                doc.multi_cell(CONTENT_W - 6, CODE_LEAD, san_mono(code_line), align="L",
                               new_x="LMARGIN", new_y="NEXT")
                doc.set_x(left + 3)
            doc.set_y(y + height + 3.5)

        elif kind == "table":
            rows = value
            header = [plain(c) for c in rows[0]]
            body_rows = [[plain(c) for c in r] for r in rows[1:]]
            cols = len(header)
            weights = []
            floors = []
            for ci in range(cols):
                cells = [header[ci]] + [r[ci] for r in body_rows if ci < len(r)]
                longest = max([len(c) for c in cells] + [4])
                weights.append(min(longest, 62))
                # Et ord må ikke brydes: kolonnen skal kunne rumme det længste token
                longest_token = max((len(tok) for c in cells for tok in re.split(r"\s+", c) if tok), default=4)
                floors.append(min(longest_token * 1.62 + 2.6, CONTENT_W * 0.42))

            if sum(floors) >= CONTENT_W:
                scale = CONTENT_W / sum(floors)
                widths = [f * scale for f in floors]
            else:
                slack = CONTENT_W - sum(floors)
                total = sum(weights)
                widths = [f + slack * w / total for f, w in zip(floors, weights)]

            def row_height(cells, bold=False):
                doc.set_font(FONT, "B" if bold else "", TABLE_SIZE)
                heights = [doc.multi_cell(w - 2.4, TABLE_LEAD, san(c), dry_run=True, output="HEIGHT")
                           if w - 2.4 > 4 else TABLE_LEAD for w, c in zip(widths, cells)]
                return max(heights) + 2.2

            def draw_row(cells, bold=False, fill=None, color=INK):
                nonlocal_y = doc.get_y()
                h = row_height(cells, bold)
                if nonlocal_y + h > 297 - MARGIN_B:
                    doc.add_page()
                    nonlocal_y = doc.get_y()
                if fill:
                    doc.set_fill_color(*fill)
                    doc.rect(left, nonlocal_y, CONTENT_W, h, style="F")
                x = left
                doc.set_draw_color(*RULE)
                doc.set_line_width(0.12)
                for w, cell in zip(widths, cells):
                    doc.set_xy(x, nonlocal_y + 1.1)
                    doc.set_font(FONT, "B" if bold else "", TABLE_SIZE)
                    doc.set_text_color(*color)
                    doc.multi_cell(w - 2.4, TABLE_LEAD, san(cell), align="L",
                                   new_x="RIGHT", new_y="TOP")
                    x += w
                doc.set_draw_color(*RULE)
                doc.line(left, nonlocal_y + h, left + CONTENT_W, nonlocal_y + h)
                doc.set_xy(left, nonlocal_y + h)

            doc.ln(1)
            draw_row(header, bold=True, fill=NAVY, color=(255, 255, 255))
            for n, row in enumerate(body_rows):
                draw_row(row, fill=ZEBRA if n % 2 else None)
            doc.ln(4)

        elif kind == "hr":
            doc.ln(2)
            doc.set_draw_color(*RULE)
            doc.set_line_width(0.25)
            doc.line(left, doc.get_y(), left + CONTENT_W, doc.get_y())
            doc.ln(4)

    pdf_path.parent.mkdir(parents=True, exist_ok=True)
    doc.output(str(pdf_path))
    return doc.page_no()


def main(argv):
    args = [a for a in argv[1:] if not a.startswith("--")]
    kicker = "steamgears.eu — dokument"
    if "--kicker" in argv:
        kicker = argv[argv.index("--kicker") + 1]
        args = [a for a in args if a != kicker]
    if len(args) < 2:
        print(__doc__)
        return 2
    md_path = Path(args[0])
    pdf_path = Path(args[1])
    pages = render(md_path, pdf_path, kicker)
    size_kb = pdf_path.stat().st_size / 1024
    print(f"PDF:  {pdf_path.relative_to(Path.cwd()) if pdf_path.is_relative_to(Path.cwd()) else pdf_path}  "
          f"{size_kb:.0f} KB · {pages} sider · font: {'DejaVu' if UNICODE else 'Helvetica (ASCII)'}")
    return 0


if __name__ == "__main__":
    sys.exit(main(sys.argv))
