#!/usr/bin/env python3
"""mcp_klassificer.py — kategori og risikoklasse for hvert MCP-værktøj.

Læser en `tools/list`-eksport (JSON) og skriver CSV med én linje pr. værktøj:
`navn,kategori,risiko,grundlag,notat`. Kører uden netværk og uden credential —
eksporten er input, ikke et kald.

Kør:
    python3 tools/mcp_klassificer.py --input tools/fixtures/mcp-tools-list.json
    python3 tools/mcp_klassificer.py --input eksport.json --csv docs/library/mcp-kategorier.csv
    python3 tools/mcp_klassificer.py < eksport.json          # CSV til stdout, resumé til stderr
    python3 tools/test_mcp_klassificer.py                    # offline selftest

Reglerne står i `docs/library/mcp-kategorier-2026-11-05.md` §3–§5, og rækkefølgen er
bindende: **overstyring → annotation → navn (kategori) → udsagnsord (risiko) → ukendt**.
Et værktøj reglerne ikke kan afgøre havner i `ukendt` med vilje; `--strict` gør det til
exit 1, så en ukendt kategori ikke kan snige sig ind i en åben profil.

Eksporten kan være et JSON-RPC-svar (`{"result": {"tools": [...]}}`), et objekt med
`tools`, en bar liste af værktøjer eller en liste af sådanne sider. Er der et
`nextCursor` uden en næste side i filen, siges det i resuméet: så er listen ufuldstændig,
og et kort værktøjstal er et sideantal, ikke en rettighed.
"""

from __future__ import annotations

import csv
import json
import re
import sys
from pathlib import Path

CATEGORIES = ("content", "media", "commerce", "seo", "social", "ai", "ops", "system")
RISK_LEVELS = ("R0", "R1", "R2", "R3")
UNKNOWN = "ukendt"

# Præfikser der kun siger hvem der ejede abilityen — ikke hvad den gør. De fjernes før
# kategori-reglerne kører, så `steamgears-autopilot_generate-image` bliver media og ikke social.
PREFIXES = ("steamgears-tools_", "steamgears-autopilot_", "steamgears_autopilot_", "woocommerce_")

# Håndafgjorte værktøjer: navn → (kategori, risiko, begrundelse). Slås op på det fulde navn
# først og derefter på navnet uden præfiks, så én linje dækker begge former.
OVERRIDES: dict[str, tuple[str, str, str]] = {
    # AI Engine — indbyggede værktøjer hvor navnet ikke afgør det
    "wp_update_option": ("ops", "R3", "ændrer en vilkårlig option — plan §4 sætter den til R3"),
    "wp_write_blocks": ("content", "R2", "skriver blokke ind i en eksisterende post"),
    "wp_open_template": ("content", "R2", "åbner skabelon i editor; antaget skrivning, ikke læsning"),
    "wp_preview_link": ("content", "R0", "genererer et preview-link, ændrer intet"),
    "wp_upload_request": ("media", "R1", "giver en upload-adresse; filen kommer først senere"),
    "wp_site_maintenance": ("ops", "R2", "sætter hele sitet i vedligeholdelse — reversibelt"),
    "wp_get_rendered": ("content", "R0", "læser den renderede side"),
    "mwai_vision": ("ai", "R0", "AI Engine: læsende, men koster kredit pr. kald"),
    "mwai_image": ("ai", "R1", "genererer et nyt billede — koster kredit"),
    # Vores egne værktøjer (steamgears-tools)
    "set-plugin-state": ("ops", "R3", "aktiverer/deaktiverer et plugin"),
    "update-option": ("ops", "R3", "ændrer en vilkårlig option i allowlisten"),
    "write-article": ("social", "R1", "skriver en kladde; publicerer ikke"),
    "call-ability": ("system", "R3", "bro: kan kalde enhver ability, også sletninger"),
    "mcp-adapter/execute-ability": ("system", "R3", "bro: eksekverer enhver ability, også sletninger"),
    # Autopilot-abilities (steamgears-autopilot)
    "check-post": ("social", "R0", "kvalitetsgate før kø"),
    "check-publer": ("social", "R0", "læser Publer-forbindelsen"),
    "check-keys": ("ops", "R2", "prøver nøgler mod eksterne API'er"),
    "generate": ("social", "R1", "genererer kladder"),
    "generate-image": ("media", "R1", "genererer et billede til mediebiblioteket"),
    "rescue-images": ("media", "R1", "kopierer eksterne billeder ind i biblioteket"),
    "meta-audit": ("seo", "R0", "læser titler og metabeskrivelser"),
    "keyword-research": ("seo", "R2", "eksternt API-kald; gemmer historik"),
    "optimize-seo": ("seo", "R2", "skriver SEO-felter på en side"),
    "optimize-site-seo": ("seo", "R2", "skriver SEO-felter på mange sider"),
    "error-log": ("ops", "R0", "læser PHP-errorloggen"),
    "self-check": ("ops", "R2", "kører selvtjek; antaget skrivende"),
    "set-mode": ("ops", "R2", "slår autopiloten til eller fra"),
    "status": ("social", "R0", "autopilotens status"),
    "enable-full-access": ("ops", "R3", "ændrer sitets adgangsflade"),
    "manage-plugin": ("ops", "R3", "installerer, aktiverer eller opdaterer et plugin"),
    "manage-queue": ("social", "R3", "kan slette kø-poster"),
    "update": ("ops", "R3", "plugin-opdatering installerer kode"),
    "review-draft": ("social", "R2", "godkender eller afviser en kladde"),
    "publish": ("social", "R2", "publicerer køen"),
    "undo": ("system", "R2", "ruller en ændring tilbage"),
}

# Kategori-regler i prioriteret rækkefølge; første hit vinder. Der matches på
# understregede navnegrænser, så »post« ikke rammer »determine«.
CATEGORY_HINTS: tuple[tuple[str, tuple[str, ...]], ...] = (
    ("system", ("mcp_ping", "mcp_list_changes", "mcp_undo_change", "mcp_adapter", "abilit",
                "change_log", "approval")),
    ("ai", ("mwai",)),
    ("seo", ("seo", "rank_math", "redirect", "sitemap", "indexnow", "canonical", "robots", "keyword")),
    ("commerce", ("product", "order", "coupon", "stock", "variation", "woocommerce", "shop")),
    ("social", ("queue", "publish", "draft", "article", "social", "autopilot")),
    ("ops", ("plugin", "theme", "cron", "cache", "option", "setting", "rewrite", "user", "key",
             "health", "diagnostic", "maintenance", "access", "info", "mode")),
    ("media", ("media", "attachment", "image", "featured")),
    ("content", ("post", "page", "block", "term", "taxonom", "menu", "comment", "widget", "template",
                 "pattern", "shortcode", "revision", "note", "acf", "form", "style", "render")),
)

# Risiko-udsagnsord i prioriteret rækkefølge: det farligste ord i navnet vinder.
RISK_VERBS: tuple[tuple[str, tuple[str, ...]], ...] = (
    ("R3", ("delete", "remove", "uninstall", "install", "activate", "deactivate", "drop", "reset",
            "truncate", "wipe", "revoke")),
    ("R2", ("update", "set", "edit", "alter", "trash", "flush", "run", "publish", "queue", "save",
            "restore", "moderate", "apply", "modify", "submit", "retry", "reschedule", "optimize",
            "manage", "purge", "undo", "sync", "refresh")),
    ("R1", ("create", "add", "insert", "upload", "generate", "write", "duplicate", "copy")),
    ("R0", ("get", "list", "count", "audit", "status", "ping", "read", "check", "research", "preview",
            "view", "info")),
)


def strip_prefix(name: str) -> str:
    """Fjern ejer-præfikset, så reglerne ser hvad værktøjet gør og ikke hvem der ejede det."""
    lowered = (name or "").strip().lower()
    for prefix in PREFIXES:
        if lowered.startswith(prefix):
            return lowered[len(prefix):]
    return lowered


def flatten(name: str) -> str:
    """`wp_get_post_terms` → `_wp_get_post_terms_`, så flerords-hints kan matches på ordgrænse."""
    return "_" + re.sub(r"[^a-z0-9]+", "_", name.lower()).strip("_") + "_"


def has_hint(flat: str, hint: str) -> bool:
    """Match et hint på hele ord — ikke på et bogstav-prefix.

    Uden ordgrænsen ramte `mode` inde i `moderate`, så `wp_moderate_comments` blev ops, og
    verbet `set` ramte `settings`, så `get-settings` blev R2. Både ental og flertal tælles med
    (`post`/`posts`/`postes`, `taxonom`/`taxonomies`); flerords-hints kræver understregning omkring sig.
    """
    hint = hint.lower()
    if "_" in hint:
        return ("_" + hint) in flat
    for word in flat.strip("_").split("_"):
        if not word:
            continue
        if word == hint or word in (hint + "s", hint + "es", hint + "ies"):
            return True
    return False


def category_for(flat: str) -> str | None:
    for category, hints in CATEGORY_HINTS:
        if any(has_hint(flat, hint) for hint in hints):
            return category
    return None


def risk_for(flat: str) -> str | None:
    for risk, verbs in RISK_VERBS:
        if any(has_hint(flat, verb) for verb in verbs):
            return risk
    return None


def classify_tool(tool: dict) -> dict:
    """Kategori, risiko og grundlag for ét MCP-værktøj.

    Rækkefølgen er bindende: overstyring, derefter `annotations` (readOnly/destructive), derefter
    navnet. Annotationen kan afgøre risikoen alene; kan den kun sige »skriver«, bruges den som
    faldskærm når navnet ikke bærer et udsagnsord — R2 frem for at gætte R1.
    """
    name = (tool.get("name") or "").strip()
    bare = strip_prefix(name)
    flat = flatten(bare)
    annotations = tool.get("annotations") or {}

    override = OVERRIDES.get(name.lower()) or OVERRIDES.get(bare)
    if override:
        category, risk, reason = override
        return {"navn": name, "kategori": category, "risiko": risk, "grundlag": "overstyring",
                "notat": reason}

    readonly = annotations.get("readOnlyHint") if isinstance(annotations.get("readOnlyHint"), bool) else None
    destructive = annotations.get("destructiveHint") is True

    category = category_for(flat)
    if destructive:
        risk, basis = "R3", "annotation (destructiveHint)"
    elif readonly is True:
        risk, basis = "R0", "annotation (readOnlyHint)"
    else:
        verb_risk = risk_for(flat)
        if verb_risk:
            risk, basis = verb_risk, "udsagnsord"
        elif readonly is False:
            risk, basis = "R2", "annotation (skriver, ukendt udsagnsord)"
        else:
            risk, basis = None, "ingen regel"

    if category is None:
        category = UNKNOWN
    if risk is None:
        risk = UNKNOWN
    if category == UNKNOWN or risk == UNKNOWN:
        basis = "ukendt" if basis == "ingen regel" else basis

    return {"navn": name, "kategori": category, "risiko": risk, "grundlag": basis,
            "notat": "" if UNKNOWN not in (category, risk) else "mangler en linje i overstyringstabellen"}


def load_tools(payload) -> tuple[list[dict], dict]:
    """Læs en `tools/list`-eksport i de former den optræder i, og sig om listen er komplet."""
    meta = {"sider": 0, "naeste_cursor": False, "form": ""}

    def one(node) -> list[dict]:
        if isinstance(node, dict):
            if isinstance(node.get("result"), dict):
                meta["sider"] += 1
                if node["result"].get("nextCursor"):
                    meta["naeste_cursor"] = True
                meta["form"] = meta["form"] or "JSON-RPC"
                return node["result"].get("tools") or []
            if "tools" in node:
                meta["sider"] += 1
                if node.get("nextCursor"):
                    meta["naeste_cursor"] = True
                meta["form"] = meta["form"] or "objekt med tools"
                return node.get("tools") or []
        return []

    if isinstance(payload, list):
        if payload and all(isinstance(item, dict) and item.get("name") for item in payload):
            meta["form"] = meta["form"] or "liste af værktøjer"
            meta["sider"] = 1
            return payload, meta
        tools: list[dict] = []
        for page in payload:
            tools.extend(one(page))
        meta["form"] = meta["form"] or "liste af sider"
        return tools, meta

    tools = one(payload)
    if not tools and not meta["form"]:
        raise ValueError("ukendt JSON-form: forventede tools/list, {'tools': [...]} eller en liste")
    return tools, meta


def rows_for(tools: list[dict]) -> list[dict]:
    rows = [classify_tool(tool) for tool in tools if (tool.get("name") or "").strip()]
    order = {category: index for index, category in enumerate((*CATEGORIES, UNKNOWN))}
    return sorted(rows, key=lambda row: (order.get(row["kategori"], len(order)), row["navn"].lower()))


def summarise(rows: list[dict], meta: dict) -> dict:
    by_category: dict[str, int] = {}
    by_risk: dict[str, int] = {}
    for row in rows:
        by_category[row["kategori"]] = by_category.get(row["kategori"], 0) + 1
        by_risk[row["risiko"]] = by_risk.get(row["risiko"], 0) + 1
    return {
        "vaerktoejer": len(rows),
        "kategorier": {key: by_category[key] for key in (*CATEGORIES, UNKNOWN) if key in by_category},
        "risiko": {key: by_risk[key] for key in (*RISK_LEVELS, UNKNOWN) if key in by_risk},
        "ukendte": [row["navn"] for row in rows if UNKNOWN in (row["kategori"], row["risiko"])],
        "form": meta.get("form"),
        "sider": meta.get("sider"),
        "ufuldstaendig": bool(meta.get("naeste_cursor")),
    }


def write_csv(rows: list[dict], target: Path | None) -> None:
    handle = target.open("w", newline="", encoding="utf-8") if target else sys.stdout
    try:
        writer = csv.writer(handle)
        writer.writerow(["navn", "kategori", "risiko", "grundlag", "notat"])
        for row in rows:
            writer.writerow([row["navn"], row["kategori"], row["risiko"], row["grundlag"], row["notat"]])
    finally:
        if target:
            handle.close()


def main(argv: list[str]) -> int:
    args = list(argv)
    strict = "--strict" in args
    source: Path | None = None
    target: Path | None = None
    for flag, setter in (("--input", "source"), ("--csv", "target")):
        if flag in args:
            at = args.index(flag)
            if at + 1 >= len(args):
                print(f"{flag} kræver en sti", file=sys.stderr)
                return 2
            path = Path(args[at + 1])
            if setter == "source":
                source = path
            else:
                target = path

    try:
        raw = source.read_text(encoding="utf-8") if source else sys.stdin.read()
    except OSError as exc:
        print(f"kunne ikke læse eksporten: {exc}", file=sys.stderr)
        return 2
    if not raw.strip():
        print("ingen JSON på stdin — brug --input <fil>", file=sys.stderr)
        return 2
    try:
        payload = json.loads(raw)
    except ValueError as exc:
        print(f"kunne ikke læse JSON: {exc}", file=sys.stderr)
        return 2

    try:
        tools, meta = load_tools(payload)
    except ValueError as exc:
        print(str(exc), file=sys.stderr)
        return 2
    if not tools:
        print("eksporten indeholder ingen værktøjer", file=sys.stderr)
        return 2

    rows = rows_for(tools)
    summary = summarise(rows, meta)
    write_csv(rows, target)
    if target:
        print(f"Skrev {summary['vaerktoejer']} værktøjer til {target}", file=sys.stderr)
    print(json.dumps(summary, ensure_ascii=False, indent=1), file=sys.stderr)
    if summary["ufuldstaendig"]:
        print("ADVARSEL: eksporten har et nextCursor — listen er kun første side", file=sys.stderr)
    if summary["ukendte"] and strict:
        return 1
    return 0


if __name__ == "__main__":
    sys.exit(main(sys.argv[1:]))
