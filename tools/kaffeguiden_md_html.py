#!/usr/bin/env python3
"""tools/kaffeguiden_md_html.py — Kaffeguiden-udkast (Markdown) til WordPress-HTML og metadata.

Bruges af tools/kaffeguiden_setup.sh, så serveren hverken skal have pandoc eller python-markdown.
Forstår kun det, udkastene bruger: overskrifter, afsnit, lister (punkt og tal), tabeller,
citatblokke, fed, kursiv og kode. Redaktørnoten øverst (kursiv-afsnittet før første `---`) og
meta-blokken nederst (efter sidste `---`) kommer ikke med i HTML'en. Meta bliver til JSON.

Brug:
  python3 tools/kaffeguiden_md_html.py <fil.md> --html     # skriver HTML til stdout
  python3 tools/kaffeguiden_md_html.py <fil.md> --meta     # {"title","slug","parent","meta_title",...}
  python3 tools/kaffeguiden_md_html.py --selftest
"""

from __future__ import annotations

import html
import json
import re
import sys

META_KEYS = {"Meta-titel": "meta_title", "Meta-beskrivelse": "meta_description", "Slug": "slug",
             "Fokus-søgeord": "focus_keyword"}


def inline(text: str) -> str:
    t = html.escape(text, quote=False)
    t = re.sub(r"`([^`]+)`", r"<code>\1</code>", t)
    t = re.sub(r"\*\*(.+?)\*\*", r"<strong>\1</strong>", t)
    t = re.sub(r"(?<![\w*])\*(?!\s)(.+?)(?<!\s)\*(?![\w*])", r"<em>\1</em>", t)
    # Placeholders stay visible on purpose: a draft with [[AFFILIATE ...]] must not be published by mistake.
    t = re.sub(r"\[\[(.+?)\]\]", r'<mark class="kg-todo">[[\1]]</mark>', t)
    return t


def split_parts(md: str) -> tuple[str, str, str]:
    """(editor note, body, meta). The file is: note, ---, body, ---, meta."""
    parts = re.split(r"^---\s*$", md, flags=re.M)
    if len(parts) >= 3:
        return parts[0], "---".join(parts[1:-1]), parts[-1]
    return "", md, ""


def body_html(body: str) -> str:
    out: list[str] = []
    lines = body.strip("\n").split("\n")
    i = 0
    while i < len(lines):
        line = lines[i]
        s = line.strip()
        if not s:
            i += 1
            continue
        m = re.match(r"^(#{2,4})\s+(.*)$", s)
        if m:
            n = len(m.group(1))
            out.append(f"<h{n}>{inline(m.group(2))}</h{n}>")
            i += 1
            continue
        if s.startswith("|"):
            rows = []
            while i < len(lines) and lines[i].strip().startswith("|"):
                rows.append([c.strip() for c in lines[i].strip().strip("|").split("|")])
                i += 1
            head, rest = rows[0], [r for r in rows[1:] if not all(re.fullmatch(r":?-+:?", c) for c in r)]
            t = ["<table><thead><tr>" + "".join(f"<th>{inline(c)}</th>" for c in head) + "</tr></thead><tbody>"]
            t += ["<tr>" + "".join(f"<td>{inline(c)}</td>" for c in r) + "</tr>" for r in rest]
            out.append("".join(t) + "</tbody></table>")
            continue
        if s.startswith(">"):
            buf = []
            while i < len(lines) and lines[i].strip().startswith(">"):
                buf.append(lines[i].strip()[1:].strip())
                i += 1
            out.append(f'<blockquote class="kg-note"><p>{inline(" ".join(buf))}</p></blockquote>')
            continue
        if re.match(r"^(-|\d+\.)\s+", s):
            ordered = bool(re.match(r"^\d+\.", s))
            items: list[str] = []
            while i < len(lines):
                cur = lines[i]
                m2 = re.match(r"^\s*(-|\d+\.)\s+(.*)$", cur)
                if m2 and (bool(re.match(r"\d", m2.group(1))) == ordered) and not cur.startswith("    "):
                    items.append(m2.group(2))
                elif cur.startswith("   ") and cur.strip() and items:
                    items[-1] += " " + cur.strip()
                else:
                    break
                i += 1
            tag = "ol" if ordered else "ul"
            out.append(f"<{tag}>" + "".join(f"<li>{inline(x)}</li>" for x in items) + f"</{tag}>")
            continue
        if s.startswith("```"):
            i += 1
            buf = []
            while i < len(lines) and not lines[i].strip().startswith("```"):
                buf.append(lines[i])
                i += 1
            i += 1
            out.append("<pre>" + html.escape("\n".join(buf)) + "</pre>")
            continue
        buf = [s]
        i += 1
        while i < len(lines) and lines[i].strip() and not re.match(r"^(#|\||>|-\s|\d+\.\s|```)", lines[i].strip()):
            buf.append(lines[i].strip())
            i += 1
        # FAQ style: "**Question?**\nAnswer" -> question in bold, then answer on its own line.
        if len(buf) > 1 and re.fullmatch(r"\*\*.+\*\*", buf[0]):
            out.append(f"<p>{inline(buf[0])}<br>{inline(' '.join(buf[1:]))}</p>")
        else:
            out.append(f"<p>{inline(' '.join(buf))}</p>")
    return "\n".join(out)


def meta(md: str) -> dict:
    _, _, tail = split_parts(md)
    data: dict = {}
    for k, v in re.findall(r"\*\*([^*:(]+?)(?:\s*\([^)]*\))?:\*\*\s*(.+)", tail):
        if k.strip() in META_KEYS:
            data[META_KEYS[k.strip()]] = v.strip()
    m = re.search(r"^#\s+(.+)$", md, flags=re.M)
    data["title"] = m.group(1).strip() if m else ""
    slug = data.get("slug", "").strip("/")
    data["parent"], _, data["slug"] = slug.rpartition("/")
    return data


def to_html(md: str) -> str:
    _, body, _ = split_parts(md)
    return body_html(body)


def selftest() -> int:
    sample = ("# Titel\n\n*Note til redaktøren.*\n\n---\n\n> **Affiliate:** mærkning\n\n## Afsnit\n\nTekst med **fed** og "
              "*kursiv* og [[AFFILIATE: x]].\n\n- a\n- b\n\n1. en\n2. to\n   fortsat\n\n| A | B |\n| --- | --- |\n| 1 | 2 |\n\n"
              "**Spørgsmål?**\nSvar.\n\n---\n\n**Meta-titel (40 tegn):** Min titel\n**Slug:** /guides/test/\n"
              "**Fokus-søgeord:** test\n")
    h = to_html(sample)
    m = meta(sample)
    checks = {
        "note og meta er ikke i HTML": "redaktøren" not in h and "Min titel" not in h,
        "h2": "<h2>Afsnit</h2>" in h,
        "fed/kursiv": "<strong>fed</strong>" in h and "<em>kursiv</em>" in h,
        "pladsholder synlig": 'class="kg-todo">[[AFFILIATE: x]]' in h,
        "lister": "<ul><li>a</li><li>b</li></ul>" in h and "<li>to fortsat</li>" in h,
        "tabel": "<th>A</th>" in h and "<td>2</td>" in h and "---" not in h,
        "citat": "<blockquote" in h,
        "faq": "<strong>Spørgsmål?</strong><br>Svar." in h,
        "meta": m == {"meta_title": "Min titel", "slug": "test", "parent": "guides", "focus_keyword": "test", "title": "Titel"},
        "escape": "&lt;b&gt;" in body_html("Tekst <b> her"),
    }
    bad = [k for k, ok in checks.items() if not ok]
    for k, ok in checks.items():
        print(("[OK] " if ok else "[FAIL] ") + k)
    return 1 if bad else 0


def main(argv: list[str]) -> int:
    if argv[1:2] == ["--selftest"]:
        return selftest()
    if len(argv) != 3 or argv[2] not in ("--html", "--meta"):
        print(__doc__, file=sys.stderr)
        return 2
    md = open(argv[1], encoding="utf-8").read()
    print(to_html(md) if argv[2] == "--html" else json.dumps(meta(md), ensure_ascii=False))
    return 0


if __name__ == "__main__":
    sys.exit(main(sys.argv))
