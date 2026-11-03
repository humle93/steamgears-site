#!/usr/bin/env python3
"""
Render the 18 punk designs as a review sheet.

concepts/punk-gallery/designs.json is the single source of truth — the gallery
and the prompt pack are both generated from it, so a title changed in one place
cannot drift out of sync in another.

The SVG in each card is an ABSTRACT LAYOUT SKETCH, not the artwork: it shows
composition, value structure and palette only. It exists so the direction can be
approved before anyone spends image credits.

    python3 tools/designs/build_gallery.py            # write gallery + prompt pack
    python3 tools/designs/build_gallery.py --check    # verify data, write nothing
"""

from __future__ import annotations

import argparse
import html
import json
import os
import re
import sys

ROOT = os.path.dirname(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
SRC = os.path.join(ROOT, "concepts", "punk-gallery", "designs.json")
OUT_HTML = os.path.join(ROOT, "concepts", "punk-gallery", "index.html")
OUT_PROMPTS = os.path.join(ROOT, "docs", "library", "punk-prompts-2026-11-02.md")

GENRES = {
    "clockpunk": {
        "da": "Clockpunk",
        "rule": "Før-damp. Træ, messing, fjedre og escapement. Renæssance og barok, ikke victoriansk.",
        "fail": "Fejlgrebet: at male tandhjul om og kalde det clockpunk. Kataloget har allerede tre tandhjulsmandalaer.",
        "slug": "clockpunk",
    },
    "dieselpunk": {
        "da": "Dieselpunk",
        "rule": "Mellemkrigstid. Flyvemaskiner, støbejern, nitter, Art Deco-geometri, flade farveflader.",
        "fail": "Fejlgrebet: 1930'erne uden grænser. Intet uniforms-mærke, intet flag, ingen rigtig nation.",
        "slug": "dieselpunk",
    },
    "teslapunk": {
        "da": "Teslapunk",
        "rule": "Elektricitet som teknologi. Spoler, buer, glas, kobber, violet-hvidt lys i mørke.",
        "fail": "Fejlgrebet: at tegne glød. Glød kan ikke trykkes — det skal oversættes til flad farve.",
        "slug": "teslapunk",
    },
}

REQUIRED = ("id", "genre", "title", "concept", "product", "palette",
            "composition", "prompt", "negative", "print_note", "keyword", "why")

TEXT_EXCLUSION = re.compile(
    r"no (readable |other |unreadable )?text|no lettering|no letters", re.IGNORECASE)


def load() -> dict:
    with open(SRC, encoding="utf-8") as fh:
        return json.load(fh)


def check(data: dict) -> list:
    problems = []
    seen = set()
    for d in data["designs"]:
        missing = [k for k in REQUIRED if not d.get(k)]
        if missing:
            problems.append(f"{d.get('id','?')}: missing {', '.join(missing)}")
        if d["id"] in seen:
            problems.append(f"{d['id']}: duplicate id")
        seen.add(d["id"])
        if d["genre"] not in GENRES:
            problems.append(f"{d['id']}: unknown genre {d['genre']}")
        if len(d["palette"]) != 4:
            problems.append(f"{d['id']}: palette needs 4 colours")
        # every prompt must explicitly exclude lettering: AI-generated type is
        # always garbage, so typography is added in layout afterwards instead.
        # "no readable text" and "no other text" are equivalent exclusions.
        if not TEXT_EXCLUSION.search(d["prompt"]):
            problems.append(f"{d['id']}: prompt does not exclude text")
    return problems


# ---------------------------------------------------------------- sketch svg

def sketch(d: dict, i: int) -> str:
    """Abstract composition sketch: palette + structure only."""
    bg, metal, light, accent = d["palette"]
    g = d["genre"]
    parts = [f'<rect width="400" height="500" fill="{bg}"/>']
    s = (i * 37) % 23  # deterministic variation so cards are not identical

    if g == "clockpunk":
        # wheel left, spring right, off-centre — mirrors the brief's asymmetry
        parts.append(f'<circle cx="{168+s//3}" cy="250" r="104" fill="none" stroke="{metal}" stroke-width="3"/>')
        parts.append(f'<circle cx="{168+s//3}" cy="250" r="86" fill="none" stroke="{metal}" stroke-width="1"/>')
        for k in range(12):
            a = k * 30
            parts.append(
                f'<line x1="{168+s//3}" y1="250" x2="{168+s//3}" y2="158" stroke="{metal}" '
                f'stroke-width="1" transform="rotate({a} {168+s//3} 250)"/>')
        parts.append(f'<circle cx="{168+s//3}" cy="250" r="16" fill="{accent}"/>')
        parts.append(f'<path d="M300 190 q40 60 8 118 q-34 58 12 92" fill="none" stroke="{light}" stroke-width="2"/>')
        for k in range(6):
            y = 210 + k * 26
            parts.append(f'<line x1="{300+k*2}" y1="{y}" x2="372" y2="{y+8}" stroke="{light}" stroke-width="1" opacity="0.55"/>')

    elif g == "dieselpunk":
        # stacked deco bands, diagonal subject, empty lower third for type
        parts.append(f'<rect y="0" width="400" height="150" fill="{metal}" opacity="0.35"/>')
        parts.append(f'<rect y="150" width="400" height="10" fill="{metal}"/>')
        parts.append(f'<rect y="168" width="400" height="4" fill="{metal}" opacity="0.6"/>')
        parts.append(f'<circle cx="{300-s//2}" cy="120" r="66" fill="{light}" opacity="0.85"/>')
        parts.append(f'<circle cx="{300-s//2}" cy="120" r="78" fill="none" stroke="{accent}" stroke-width="1.5"/>')
        parts.append(
            f'<path d="M20 300 L{120+s//2} 190 L{240+s//2} 250 L170 300 Z" fill="{accent}" opacity="0.9"/>')
        parts.append(f'<path d="M0 330 H400" stroke="{metal}" stroke-width="2"/>')
        parts.append(f'<path d="M0 344 H400" stroke="{metal}" stroke-width="1" opacity="0.5"/>')
        for k in range(4):
            parts.append(f'<rect x="{28+k*8}" y="392" width="{120-k*20}" height="9" fill="{light}" opacity="{0.5-k*0.1:.1f}"/>')

    else:  # teslapunk
        cx = 200
        parts.append(f'<rect x="{cx-30}" y="180" width="60" height="250" fill="{metal}" opacity="0.55"/>')
        for k in range(9):
            y = 196 + k * 26
            parts.append(f'<path d="M{cx-30} {y} H{cx+30}" stroke="{light}" stroke-width="1.2" opacity="0.7"/>')
        parts.append(f'<ellipse cx="{cx}" cy="176" rx="44" ry="16" fill="none" stroke="{metal}" stroke-width="3"/>')
        for k in range(7):
            tip_x = 40 + k * 55
            parts.append(
                f'<path d="M{cx} 170 C{cx} {110-k*6}, {tip_x} {96+k*4}, {tip_x} {40+k*10}" '
                f'fill="none" stroke="{light}" stroke-width="{1.4 if k%2 else 2.1}" opacity="0.85"/>')
            parts.append(f'<circle cx="{tip_x}" cy="{40+k*10}" r="3" fill="{accent}"/>')

    parts.append(f'<text x="16" y="482" fill="{light}" opacity="0.5" font-size="11" '
                 f'font-family="ui-monospace, monospace" letter-spacing="2">LAYOUTSKITSE</text>')
    return "".join(parts)


# ---------------------------------------------------------------- html

def render(data: dict) -> str:
    designs = data["designs"]
    cards = []
    for i, d in enumerate(designs):
        chips = "".join(
            f'<span class="chip"><i style="background:{c}"></i>{c}</span>' for c in d["palette"])
        cards.append(f"""
      <article class="card" id="{d['id']}">
        <div class="sketch"><svg viewBox="0 0 400 500" role="img" aria-label="Layoutskitse: {html.escape(d['title'])}">{sketch(d, i)}</svg></div>
        <div class="body">
          <div class="toprow"><span class="id">{d['id']}</span><span class="kw">{html.escape(d['keyword'])}</span></div>
          <h3>{html.escape(d['title'])}</h3>
          <p class="concept">{html.escape(d['concept'])}</p>
          <p class="meta"><b>Produkt</b> {html.escape(d['product'])}</p>
          <p class="meta"><b>Komposition</b> {html.escape(d['composition'])}</p>
          <div class="chips">{chips}</div>
          <p class="why">{html.escape(d['why'])}</p>
          <details><summary>Prompt</summary><pre>{html.escape(d['prompt'])}</pre>
            <pre class="neg">{html.escape(d['negative'])}</pre></details>
          <p class="note"><b>Tryk</b> {html.escape(d['print_note'])}</p>
        </div>
      </article>""")

    sections = []
    for key, meta in GENRES.items():
        group = [c for c, d in zip(cards, designs) if d["genre"] == key]
        sections.append(f"""
    <section>
      <header class="genre">
        <h2>{meta['da']}</h2>
        <p>{html.escape(meta['rule'])}</p>
        <p class="fail">{html.escape(meta['fail'])}</p>
      </header>
      <div class="grid">{''.join(group)}</div>
    </section>""")

    rules = "".join(f"<li>{html.escape(r)}</li>" for r in data["house_rules"])

    return f"""<!doctype html>
<html lang="da">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Punk-grener — 18 designs · steamgears</title>
<style>
  :root {{
    --bg: #12100e; --panel: #1a1714; --line: #2e2823; --ink: #e6ded0;
    --dim: #a2968a; --brass: #b08d57;
  }}
  * {{ box-sizing: border-box; }}
  body {{ margin: 0; background: var(--bg); color: var(--ink);
    font: 16px/1.6 Georgia, 'Times New Roman', serif; }}
  .wrap {{ max-width: 1240px; margin: 0 auto; padding: 56px 28px 96px; }}
  h1 {{ font-size: clamp(30px, 4vw, 46px); line-height: 1.15; margin: 0 0 12px;
    font-weight: 400; letter-spacing: -0.01em; }}
  .lede {{ color: var(--dim); max-width: 62ch; margin: 0 0 28px; }}
  .rules {{ border: 1px solid var(--line); background: var(--panel); padding: 20px 26px;
    margin: 0 0 56px; }}
  .rules h3 {{ margin: 0 0 10px; font-size: 13px; letter-spacing: .16em;
    text-transform: uppercase; color: var(--brass); font-family: ui-monospace, monospace; }}
  .rules ol {{ margin: 0; padding-left: 20px; color: var(--dim); font-size: 15px; }}
  .rules li {{ margin: 6px 0; }}
  header.genre {{ border-top: 1px solid var(--line); padding-top: 26px; margin: 0 0 26px; }}
  header.genre h2 {{ margin: 0 0 6px; font-size: 26px; font-weight: 400; letter-spacing: .02em; }}
  header.genre p {{ margin: 0; color: var(--dim); max-width: 70ch; font-size: 15px; }}
  header.genre .fail {{ color: #c98b6b; margin-top: 6px; }}
  section {{ margin-bottom: 64px; }}
  .grid {{ display: grid; gap: 22px; grid-template-columns: repeat(auto-fill, minmax(340px, 1fr)); }}
  .card {{ border: 1px solid var(--line); background: var(--panel); display: flex; flex-direction: column; }}
  .sketch {{ border-bottom: 1px solid var(--line); background: #0c0b0a; }}
  .sketch svg {{ display: block; width: 100%; height: auto; }}
  .body {{ padding: 20px 22px 24px; display: flex; flex-direction: column; gap: 10px; }}
  .toprow {{ display: flex; justify-content: space-between; align-items: baseline; gap: 10px;
    font-family: ui-monospace, monospace; font-size: 11px; letter-spacing: .1em; }}
  .id {{ color: var(--brass); }}
  .kw {{ color: #6f6559; text-transform: uppercase; text-align: right; }}
  h3 {{ margin: 0; font-size: 22px; font-weight: 400; }}
  .concept {{ margin: 0; color: #cfc5b6; font-size: 15px; }}
  .meta {{ margin: 0; font-size: 13.5px; color: var(--dim); }}
  .meta b {{ color: var(--brass); font-weight: 400; font-family: ui-monospace, monospace;
    font-size: 11px; letter-spacing: .1em; text-transform: uppercase; margin-right: 6px; }}
  .chips {{ display: flex; flex-wrap: wrap; gap: 8px; margin: 4px 0 2px; }}
  .chip {{ display: inline-flex; align-items: center; gap: 6px; font-family: ui-monospace, monospace;
    font-size: 11px; color: var(--dim); }}
  .chip i {{ width: 13px; height: 13px; display: block; border: 1px solid #00000066; }}
  .why {{ margin: 0; font-size: 14px; color: #b9ae9f; font-style: italic;
    border-left: 2px solid var(--line); padding-left: 12px; }}
  details {{ border-top: 1px solid var(--line); padding-top: 10px; }}
  summary {{ cursor: pointer; font-family: ui-monospace, monospace; font-size: 11px;
    letter-spacing: .14em; text-transform: uppercase; color: var(--brass); }}
  pre {{ white-space: pre-wrap; word-break: break-word; font: 12.5px/1.55 ui-monospace, monospace;
    color: #cfc5b6; background: #0c0b0a; border: 1px solid var(--line); padding: 12px;
    margin: 10px 0 0; }}
  pre.neg {{ color: #9a8478; }}
  .note {{ margin: 0; font-size: 13px; color: var(--dim); }}
  .note b {{ color: var(--brass); font-weight: 400; font-family: ui-monospace, monospace;
    font-size: 11px; letter-spacing: .1em; text-transform: uppercase; margin-right: 6px; }}
  footer {{ border-top: 1px solid var(--line); padding-top: 20px; color: #6f6559; font-size: 13px; }}
</style>
</head>
<body>
<div class="wrap">
  <h1>{len(designs)} nye designs — clockpunk, dieselpunk, teslapunk</h1>
  <p class="lede">Retningsark, ikke færdige billeder. Hver rude viser komposition, værdistruktur og palette
  som en abstrakt skitse, så retningen kan godkendes før nogen bruger billedcredits.
  Prompterne ligger under hvert kort og i <code>docs/library/punk-prompts-2026-11-02.md</code>.</p>
  <div class="rules">
    <h3>Husregler — de seks ting der gør dem bedre end de 39 eksisterende</h3>
    <ol>{rules}</ol>
  </div>
{''.join(sections)}
  <footer>Genereret fra <code>concepts/punk-gallery/designs.json</code> ·
  <code>python3 tools/designs/build_gallery.py</code></footer>
</div>
</body>
</html>
"""


def render_prompts(data: dict) -> str:
    out = ["# Prompt-pack — clockpunk, dieselpunk, teslapunk",
           "",
           "18 designs, genereret fra `concepts/punk-gallery/designs.json`. Ret data, kør "
           "`python3 tools/designs/build_gallery.py`, og denne fil skrives igen — rediger den ikke i hånden.",
           "",
           "**Sådan bruges prompterne:** generér uden tekst, læg typografi på i layout bagefter. "
           "Kør hvert motiv 4–8 gange og vælg på thumbnail-aftryk, ikke på fuld størrelse — "
           "det er sådan kunden ser det i annoncen.",
           "",
           "---",
           ""]
    for key, meta in GENRES.items():
        out += [f"## {meta['da']}", "", f"{meta['rule']}", "", f"*{meta['fail']}*", ""]
        for d in data["designs"]:
            if d["genre"] != key:
                continue
            out += [f"### {d['id']} — {d['title']}", "",
                    d["concept"], "",
                    f"- **Produkt:** {d['product']}",
                    f"- **Nøgleord:** {d['keyword']}",
                    f"- **Komposition:** {d['composition']}",
                    f"- **Palette:** {' · '.join(d['palette'])}",
                    f"- **Tryk:** {d['print_note']}",
                    f"- **Hvorfor den sælger:** {d['why']}",
                    "",
                    "```text", d["prompt"], "```", "",
                    "Negativ:", "",
                    "```text", d["negative"], "```", ""]
    return "\n".join(out).rstrip() + "\n"


def main() -> None:
    ap = argparse.ArgumentParser()
    ap.add_argument("--check", action="store_true", help="validate data and write nothing")
    args = ap.parse_args()

    data = load()
    problems = check(data)
    for p in problems:
        print("problem:", p, file=sys.stderr)
    if problems:
        sys.exit(1)

    counts = {k: sum(1 for d in data["designs"] if d["genre"] == k) for k in GENRES}
    print("designs:", len(data["designs"]), "·", ", ".join(f"{k} {v}" for k, v in counts.items()))
    if args.check:
        print("check ok — nothing written")
        return

    with open(OUT_HTML, "w", encoding="utf-8") as fh:
        fh.write(render(data))
    with open(OUT_PROMPTS, "w", encoding="utf-8") as fh:
        fh.write(render_prompts(data))
    print("wrote", os.path.relpath(OUT_HTML, ROOT))
    print("wrote", os.path.relpath(OUT_PROMPTS, ROOT))


if __name__ == "__main__":
    main()
