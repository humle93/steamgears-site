#!/usr/bin/env python3
"""Bro: grønne nicher fra niche_scan.py -> draft-rækker til autopilotens kø.

Laver de payloads som `POST /sgap/v1/queue` tager imod (se SGAP_REST::create_queue),
med `status: draft`, så intet går til Publer uden en godkendelse. Broen skriver
ingen overskrift, ingen brødtekst, intet billede og ingen URL selv: mangler der en
URL i linkfilen, bliver nichen sprunget over med en grund i stedet for at få en
opdigtet et destination.

Brug:
    python3 niche_bridge.py --input docs/library/niche-kandidater-2026-11-02.csv \
        --links nicher-links.csv --channels pinterest --plan

Kun stdlib. Samme input giver samme output.
"""

import argparse
import hashlib
import json
import re
import sys
from datetime import date
from pathlib import Path

import niche_scan

# Publer-kanaler som pluginnet kan sende til (SGAP_Publer::CHANNELS).
CHANNELS = [
    "facebook", "instagram", "pinterest", "linkedin", "twitter",
    "threads", "bluesky", "mastodon", "telegram", "google",
]

# Kanaler hvor Publer kræver et foto i payloaden (SGAP_Publer::NEEDS_PHOTO).
NEEDS_PHOTO = ["instagram", "pinterest"]

# Køens standarder for manuelt oprettede opslag (SGAP_Abilities::queue_post).
CONTENT_TYPE = "custom"
FORMAT = "post"

GREEN = "grøn"
URL_RE = re.compile(r"^https?://[^\s/$.?#].[^\s]*$", re.IGNORECASE)


class BridgeError(Exception):
    """Ugyldigt input til broen."""


def clean_key(value, default: str, max_length: int) -> str:
    """Spejler SGAP_Queue::key() (sanitize_key + længdegrænse)."""
    cleaned = re.sub(r"[^a-z0-9_\-]", "", str(value or "").lower())
    return cleaned[:max_length] or default


def fingerprint(channel: str, destination_url: str, title: str) -> str:
    """Spejler SGAP_Queue::add()'s duplikatnøgle: sha256(channel|url|title)."""
    return hashlib.sha256(f"{channel}|{destination_url}|{title}".encode("utf-8")).hexdigest()


def valid_url(url: str) -> bool:
    """Kun http(s) med et værtsnavn, som esc_url_raw(..., ['http','https']) i praksis."""
    return bool(isinstance(url, str) and URL_RE.match(url.strip()) and " " not in url.strip())


def title_for(niche: str) -> str:
    """Forslag til titel. Bevidst kedeligt: nichenavnet, ikke en overskrift vi fandt på."""
    name = str(niche).strip()
    return name[:1].upper() + name[1:] if name else ""


def normalise_name(name: str) -> str:
    return " ".join(str(name).split()).lower()


def load_links(path: Path) -> dict:
    """Linkfil: CSV med `niche,url` eller JSON med {"links": {"niche": "url"}} / liste."""
    path = Path(path)
    if not path.exists():
        raise BridgeError(f"Linkfilen findes ikke: {path}")
    text = path.read_text(encoding="utf-8")
    links = {}
    if path.suffix.lower() == ".json":
        try:
            data = json.loads(text)
        except json.JSONDecodeError as error:
            raise BridgeError(f"{path.name} er ikke gyldig JSON: {error}") from error
        if isinstance(data, dict):
            data = data.get("links", data)
        if isinstance(data, dict):
            pairs = data.items()
        elif isinstance(data, list):
            pairs = [(item.get("niche", ""), item.get("url", "")) for item in data if isinstance(item, dict)]
        else:
            raise BridgeError(f"{path.name}: forventede et objekt eller en liste af nicher.")
    else:
        import csv

        reader = csv.DictReader(text.splitlines())
        if not reader.fieldnames or "niche" not in [f.strip().lower() for f in reader.fieldnames]:
            raise BridgeError(f"{path.name}: CSV skal have kolonnerne niche,url.")
        pairs = []
        for row in reader:
            lowered = {str(k).strip().lower(): v for k, v in row.items() if k}
            pairs.append((lowered.get("niche", ""), lowered.get("url", "")))
    for niche, url in pairs:
        if str(niche).strip() == "":
            continue
        links[normalise_name(niche)] = str(url or "").strip()
    if not links:
        raise BridgeError(f"{path.name}: ingen nicher i linkfilen.")
    return links


def payload_for(niche: str, score: float, verdict: str, channel: str, destination_url: str) -> dict:
    """Præcis de felter SGAP_REST::create_queue læser. Intet mere, intet mindre."""
    return {
        "post_id": 0,
        "channel": channel,
        "angle": clean_key("niche", "angle_a", 20),
        "content_type": CONTENT_TYPE,
        "format": FORMAT,
        "destination_url": destination_url,
        "title": title_for(niche),
        "body": "",
        "image_url": "",
        "status": "draft",
        "scheduled_at": None,
    }


def drafts(ranked: list, links: dict, channels: list, include_yellow: bool = False,
           require_image: bool = False) -> tuple:
    """Bygger draft-payloads for de nicher der har en URL. Returnerer (drafts, skipped)."""
    allowed = [c for c in channels if c]
    unknown = [c for c in allowed if c not in CHANNELS]
    if unknown:
        raise BridgeError(f"Ukendte kanaler: {', '.join(unknown)}. Tilladte: {', '.join(CHANNELS)}")
    if not allowed:
        raise BridgeError("Vælg mindst én kanal.")
    wanted = [GREEN] if not include_yellow else [GREEN, "gul"]

    built, skipped, seen = [], [], set()
    for row in ranked:
        if row["verdict"] not in wanted:
            continue
        niche = row["niche"]
        url = links.get(normalise_name(niche), "")
        if not url:
            skipped.append({"niche": niche, "channel": "", "reason": "ingen URL i linkfilen"})
            continue
        if not valid_url(url):
            skipped.append({"niche": niche, "channel": "", "reason": f"ikke en http(s)-URL: {url}"})
            continue
        for channel in allowed:
            if require_image and channel in NEEDS_PHOTO:
                skipped.append({"niche": niche, "channel": channel,
                                "reason": f"{channel} kræver foto, og broen laver ingen billeder"})
                continue
            key = fingerprint(channel, url, title_for(niche))
            if key in seen:
                skipped.append({"niche": niche, "channel": channel,
                                "reason": "duplikat: samme kanal, URL og titel er allerede med"})
                continue
            seen.add(key)
            built.append({
                "payload": payload_for(niche, row["score"], row["verdict"], channel, url),
                "attribution": {"niche": niche, "score": row["score"], "verdict": row["verdict"]},
                "fingerprint": key,
            })
    return built, skipped


def _display(path: str) -> str:
    """Kort sti til planen: relativ når den ligger under cwd, ellers kun filnavnet."""
    candidate = Path(path).resolve()
    try:
        return str(candidate.relative_to(Path.cwd().resolve()))
    except ValueError:
        return candidate.name


def render_plan(built: list, skipped: list, source: str, links_path: str) -> str:
    source, links_path = _display(source), _display(links_path)
    lines = [
        "# Niche-bro — draft-rækker til godkendelse",
        "",
        f"Nichedata: `{source}` · links: `{links_path}`. Alt er `status: draft`; intet er sendt til Publer.",
        "",
    ]
    if built:
        lines += [
            "| # | Niche | Score | Kanal | Titel | Destination |",
            "| --- | --- | ---: | --- | --- | --- |",
        ]
        for index, item in enumerate(built, 1):
            row, attribute = item["payload"], item["attribution"]
            lines.append(
                f"| {index} | {attribute['niche']} | {attribute['score']:.1f}".replace(".", ",")
                + f" | {row['channel']} "
                f"| {row['title']} | {row['destination_url']} |"
            )
    else:
        lines.append("Ingen draft-rækker: ingen grøn niche havde en URL.")
    needs_photo = sorted({i["payload"]["channel"] for i in built} & set(NEEDS_PHOTO))
    if needs_photo:
        lines += ["", f"**Mangler foto:** {', '.join(needs_photo)} — Publer afviser opslaget uden et billede. "
                      "Brug `generate-image` med `queue_id` efter rækken er oprettet, eller kør med `--require-image` "
                      "for at holde dem ude af listen."]
    if skipped:
        lines += ["", "## Sprunget over", ""]
        for item in skipped:
            where = f" ({item['channel']})" if item["channel"] else ""
            lines.append(f"- **{item['niche']}**{where} — {item['reason']}")
    lines += [
        "",
        "## Hvad broen ikke gør",
        "",
        "- Skriver ikke titel, brødtekst eller billede. `body` er tom, så et menneske eller `write-article` fylder den ud.",
        "- Opretter intet i WordPress. Payloads skal POSTes til `/sgap/v1/queue` (eller læses ind af en PHP-bro).",
        "- Gemmer ikke attribution i databasen: `sgap_queue` har ingen `niche`/`score`-kolonner, så feltet findes kun i denne fils `attribution`.",
        "",
    ]
    return "\n".join(lines)


def build(args) -> tuple:
    ranked = niche_scan.scan(niche_scan.load(Path(args.input)))
    links = load_links(Path(args.links))
    built, skipped = drafts(
        ranked, links, [clean_key(c, "", 40) for c in args.channels.split(",") if c.strip()],
        include_yellow=args.include_yellow, require_image=args.require_image,
    )
    return built, skipped


def main(argv=None) -> int:
    parser = argparse.ArgumentParser(description="Grønne nicher -> draft-rækker til autopilotens kø.")
    parser.add_argument("--input", required=True, help="Niche-CSV/JSON som niche_scan.py læser.")
    parser.add_argument("--links", required=True, help="CSV (niche,url) eller JSON med niche -> URL.")
    parser.add_argument("--channels", default="pinterest", help=f"Komma separeret. Tilladte: {', '.join(CHANNELS)}")
    parser.add_argument("--include-yellow", action="store_true", help="Tag gule nicher med, ikke kun grønne.")
    parser.add_argument("--require-image", action="store_true", help="Spring kanaler over der kræver foto.")
    parser.add_argument("--out", help="Skriv payloads som JSON her.")
    parser.add_argument("--plan", nargs="?", const="", default=None, help="Skriv Markdown-plan (valgfri sti).")
    args = parser.parse_args(argv)

    try:
        built, skipped = build(args)
    except (BridgeError, niche_scan.RowError) as error:
        print(f"Fejl: {error}", file=sys.stderr)
        return 1

    plan = render_plan(built, skipped, args.input, args.links)
    if args.plan is not None:
        target = Path(args.plan) if args.plan else Path(f"docs/library/niche-bro-{date.today().isoformat()}.md")
        target.parent.mkdir(parents=True, exist_ok=True)
        target.write_text(plan + "\n", encoding="utf-8")
        print(f"[OK] plan skrevet: {target}", file=sys.stderr)
    if args.out:
        target = Path(args.out)
        target.parent.mkdir(parents=True, exist_ok=True)
        data = {"count": len(built), "drafts": built, "skipped": skipped}
        target.write_text(json.dumps(data, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")
        print(f"[OK] {len(built)} draft-payloads skrevet: {target}", file=sys.stderr)
    if args.plan is None and not args.out:
        print(plan)
    return 0


if __name__ == "__main__":
    sys.exit(main())
