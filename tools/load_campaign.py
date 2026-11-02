#!/usr/bin/env python3
"""load_campaign.py — værn og indlæser for en kampagne-kø til Steamgears Autopilot.

En kampagne er ikke et dokument her; den er de poster der ligger i pluginets kø med en
fælles plan. Derfor tjekker dette værktøj payloaden mod de samme regler som pluginnet selv
bruger, *før* noget sendes:

- `SGAP_Queue::add()` kræver `channel`, `title`, `destination_url`, og afviser et
  gentaget `channel|destination|title` som 409 (fingerprint).
- `SGAP_Quality` blokerer captions med >= 0,85 ord-overlap mod de seneste 40 pr. kanal,
  døde links, placeholder-hosts og manglende billede på Instagram/Pinterest.
- Køen frigiver højst `daily_cap` (8) poster i døgnet og intet i stille-timerne 23-07.

Kør:
    python3 tools/load_campaign.py docs/library/clockwork-punk-koe-2026-11-02.json
    python3 tools/load_campaign.py <fil> --post      # læg posterne i køen (kræver OAuth-token)
    python3 tools/load_campaign.py --selftest        # 10 fixtures af værnene

Uden `--post` er kørslen en tørkørsel: intet netværk, intet skrives.
"""

from __future__ import annotations

import argparse
import hashlib
import json
import os
import re
import sys
import urllib.error
import urllib.request
from datetime import datetime, timedelta
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
API = os.environ.get("SGAP_API_BASE") or "https://steamgears.eu/wp-json/sgap/v1"
SITE_HOST = (os.environ.get("SGAP_SITE_HOST") or "steamgears.eu").lower()
CHANNELS = ("facebook", "instagram", "pinterest")
NEEDS_IMAGE = ("instagram", "pinterest")
CAPTION_MAX = {"facebook": 63206, "instagram": 2200, "pinterest": 500}
TITLE_MAX = {"facebook": 200, "instagram": 125, "pinterest": 100}
MIN_WORDS = 8
SIMILARITY = 0.85
DAILY_CAP = 8
QUIET_HOURS = tuple(range(23, 24)) + tuple(range(0, 7))
REQUIRED = ("channel", "title", "destination_url")
ALLOWED = {"dag", "produkt", "channel", "angle", "content_type", "format", "title", "body",
           "destination_url", "image_url", "board", "scheduled_at", "status"}
POSTED = ("channel", "angle", "content_type", "format", "title", "body", "destination_url",
          "image_url", "scheduled_at", "status")


def normalize(text: str) -> str:
    """Samme normalisering som SGAP_Quality::normalize(): links væk, små bogstaver, kun ord."""
    text = re.sub(r"https?://\S+", " ", text.lower())
    text = re.sub(r"[^\w]+", " ", text, flags=re.UNICODE)
    return re.sub(r"\s+", " ", text).strip()


def tokens(text: str) -> list[str]:
    return sorted(set(normalize(text).split()))


def similarity(a: str, b: str) -> float:
    left, right = tokens(a), tokens(b)
    if not left or not right:
        return 0.0
    return round(len(set(left) & set(right)) / len(set(left) | set(right)), 3)


def fingerprint(item: dict) -> str:
    return hashlib.sha256(
        f"{item['channel']}|{item['destination_url']}|{item['title']}".encode()
    ).hexdigest()


def parse_when(raw: str) -> datetime | None:
    for shape in ("%Y-%m-%d %H:%M:%S", "%Y-%m-%dT%H:%M:%S", "%Y-%m-%dT%H:%M"):
        try:
            return datetime.strptime(raw, shape)
        except ValueError:
            continue
    return None


def validate(payload: dict, now: datetime | None = None) -> dict:
    """Retur: {'ok', 'errors', 'warnings', 'items'}. Fejl stopper indlæsning; advarsler gør ikke."""
    now = now or datetime.now()
    errors: list[str] = []
    warnings: list[str] = []
    items = payload.get("items") if isinstance(payload, dict) else None
    if not isinstance(items, list) or not items:
        return {"ok": False, "errors": ["payloaden har ingen 'items'-liste"], "warnings": [], "items": []}

    seen: dict[str, int] = {}
    per_day: dict[str, int] = {}
    bodies: dict[str, list[tuple[int, str]]] = {channel: [] for channel in CHANNELS}

    for number, item in enumerate(items, start=1):
        where = f"post {number}"
        if not isinstance(item, dict):
            errors.append(f"{where}: er ikke et objekt")
            continue
        for field in REQUIRED:
            if not str(item.get(field) or "").strip():
                errors.append(f"{where}: mangler '{field}'")
        unknown = set(item) - ALLOWED
        if unknown:
            errors.append(f"{where}: ukendte felter {sorted(unknown)}")

        channel = str(item.get("channel") or "")
        if channel not in CHANNELS:
            errors.append(f"{where}: kanal '{channel}' er ikke en af {list(CHANNELS)}")
            continue

        title = str(item.get("title") or "").strip()
        if len(title) > TITLE_MAX[channel]:
            warnings.append(f"{where}: titlen er {len(title)} tegn; {channel} viser ca. {TITLE_MAX[channel]}")
        if title.lower() in {t.lower() for _, t in bodies[channel]}:
            errors.append(f"{where}: titlen er identisk med en tidligere post på {channel}")

        body = str(item.get("body") or "").strip()
        words = len(normalize(body).split())
        if words < MIN_WORDS:
            errors.append(f"{where}: captionen har {words} ord; kvalitetsgaten kræver mindst {MIN_WORDS}")
        if len(body) > CAPTION_MAX[channel]:
            errors.append(f"{where}: captionen er {len(body)} tegn; {channel} tillader {CAPTION_MAX[channel]}")

        destination = str(item.get("destination_url") or "")
        if not destination.startswith("https://"):
            errors.append(f"{where}: destination_url skal være https")
        elif SITE_HOST not in destination.split("/")[2].lower():
            errors.append(f"{where}: destination_url peger ikke på {SITE_HOST} — /go/-redirectet ville sende trafikken til forsiden")
        elif "example." in destination or "localhost" in destination:
            errors.append(f"{where}: destination_url er en placeholder")

        image = str(item.get("image_url") or "")
        if channel in NEEDS_IMAGE and not image:
            errors.append(f"{where}: {channel} kræver image_url")
        if image and not image.startswith("https://"):
            errors.append(f"{where}: image_url skal være https")

        if channel in CHANNELS:
            # SGAP_Quality::duplicate() sammenligner kun captionen — ikke titlen.
            bodies[channel].append((number, body))

        key = fingerprint({"channel": channel, "destination_url": destination, "title": title})
        if key in seen:
            errors.append(f"{where}: samme kanal+link+titel som post {seen[key]} (køen svarer 409)")
        else:
            seen[key] = number

        raw_when = str(item.get("scheduled_at") or "").strip()
        if raw_when:
            when = parse_when(raw_when)
            if when is None:
                errors.append(f"{where}: scheduled_at '{raw_when}' er ikke 'ÅÅÅÅ-MM-DD TT:MM:SS'")
            else:
                if when.hour in QUIET_HOURS:
                    errors.append(f"{where}: kl. {when:%H:%M} ligger i stille-timerne 23-07")
                if when < now:
                    errors.append(f"{where}: tidspunktet {when:%Y-%m-%d %H:%M} er passeret")
                per_day[when.strftime("%Y-%m-%d")] = per_day.get(when.strftime("%Y-%m-%d"), 0) + 1
        else:
            warnings.append(f"{where}: uden scheduled_at frigives posten når køen når den")

    for day, count in sorted(per_day.items()):
        if count > DAILY_CAP:
            errors.append(f"{day}: {count} poster samme dag; køen frigiver højst {DAILY_CAP}")

    for channel, entries in bodies.items():
        for i, (number, text) in enumerate(entries):
            for other_number, other in entries[:i]:
                score = similarity(text, other)
                if score >= SIMILARITY:
                    errors.append(f"post {number}: {score:.2f} ord-overlap med post {other_number} på {channel} "
                                  f"(gaten blokerer ved >= {SIMILARITY})")

    boards = {str(item.get("board") or "") for item in items if isinstance(item, dict)} - {""}
    if len(boards) > 1:
        warnings.append(f"posterne peger på {len(boards)} Pinterest-boards ({sorted(boards)}); pluginnet har ét "
                        f"board pr. publicering, så de øvrige skal flyttes manuelt")

    return {"ok": not errors, "errors": errors, "warnings": warnings, "items": items}


def summarize(payload: dict, result: dict) -> None:
    items = result["items"]
    days = [str(item.get("scheduled_at") or "")[:10] for item in items]
    channels: dict[str, int] = {}
    angles: dict[str, int] = {}
    for item in items:
        channels[str(item.get("channel"))] = channels.get(str(item.get("channel")), 0) + 1
        angles[str(item.get("angle") or "angle_a")] = angles.get(str(item.get("angle") or "angle_a"), 0) + 1
    span = f"{min(days)} → {max(days)}" if any(days) else "uden dato"
    print(f"Kampagne: {payload.get('navn') or payload.get('campaign')}")
    print(f"Poster: {len(items)} · {span} · kanaler {channels} · vinkler {angles}")
    for item in items:
        print(f"  {str(item.get('scheduled_at') or '     -         '):19} {item.get('channel'):9} "
              f"{item.get('angle'):8} {item.get('title')[:52]}")
    for warning in result["warnings"]:
        print(f"[ADVARSEL] {warning}")
    for error in result["errors"]:
        print(f"[FEJL] {error}")
    print("OK — intet sendt (tørkørsel). Brug --post for at lægge posterne i køen."
          if result["ok"] else f"{len(result['errors'])} fejl — intet blev sendt.")


def token() -> str | None:
    sys.path.insert(0, str(Path(__file__).resolve().parent))
    try:
        import mcp_oauth
    except Exception:  # pragma: no cover - kun når filen er flyttet
        return os.environ.get("MCP_ACCESS_TOKEN") or None
    return mcp_oauth.access_token() or os.environ.get("MCP_ACCESS_TOKEN") or None


def post_item(item: dict, bearer: str) -> tuple[int, dict | str]:
    body = {key: item[key] for key in POSTED if item.get(key) not in (None, "")}
    request = urllib.request.Request(
        f"{API}/queue",
        data=json.dumps(body).encode(),
        method="POST",
        headers={"Content-Type": "application/json", "Accept": "application/json",
                 "Authorization": f"Bearer {bearer}", "User-Agent": "steamgears-campaign/1.0"},
    )
    try:
        with urllib.request.urlopen(request, timeout=40) as response:
            raw = response.read().decode("utf-8", "replace")
            try:
                return response.status, json.loads(raw)
            except ValueError:
                return response.status, raw
    except urllib.error.HTTPError as exc:
        raw = exc.read().decode("utf-8", "replace")
        try:
            return exc.code, json.loads(raw)
        except ValueError:
            return exc.code, raw
    except urllib.error.URLError as exc:
        return 0, f"nåede ikke {API}: {exc.reason}"


def push(items: list[dict], bearer: str) -> int:
    placed, skipped, failed = 0, 0, 0
    for item in items:
        status, body = post_item(item, bearer)
        title = str(item.get("title"))[:48]
        if status in (200, 201) and isinstance(body, dict) and body.get("id"):
            placed += 1
            print(f"[KØ] #{body['id']} {title} → {body.get('utm_url') or body.get('tracking_url') or ''}")
        elif status == 409:
            skipped += 1
            print(f"[FINDES] {title} — samme kanal+link+titel er allerede i køen")
        else:
            failed += 1
            detail = body.get("message") if isinstance(body, dict) else body
            print(f"[FEJL] {title} — HTTP {status}: {str(detail)[:200]}")
            break
    print(f"Sat i kø: {placed} · fandtes allerede: {skipped} · fejlede: {failed}")
    return 0 if failed == 0 else 1


FIXTURES = [
    ("gyldig post", {"channel": "pinterest", "title": "Brass moth print", "body": "A moth with brass wings, printed to order for the wall.",
                     "destination_url": f"https://{SITE_HOST}/x/", "image_url": f"https://{SITE_HOST}/a.jpg",
                     "scheduled_at": "2030-01-01 12:00:00"}, True),
    ("mangler titel", {"channel": "pinterest", "title": "", "body": "A moth with brass wings, printed to order for the wall.",
                       "destination_url": f"https://{SITE_HOST}/x/", "image_url": f"https://{SITE_HOST}/a.jpg"}, False),
    ("ukendt kanal", {"channel": "tiktok", "title": "T", "body": "A moth with brass wings, printed to order for the wall.",
                      "destination_url": f"https://{SITE_HOST}/x/"}, False),
    ("http-link", {"channel": "facebook", "title": "T", "body": "A moth with brass wings, printed to order for the wall.",
                   "destination_url": f"http://{SITE_HOST}/x/"}, False),
    ("fremmed vaert", {"channel": "facebook", "title": "T", "body": "A moth with brass wings, printed to order for the wall.",
                       "destination_url": "https://etsy.com/listing/1"}, False),
    ("pinterest uden billede", {"channel": "pinterest", "title": "T", "body": "A moth with brass wings, printed to order for the wall.",
                                "destination_url": f"https://{SITE_HOST}/x/"}, False),
    ("for kort caption", {"channel": "facebook", "title": "T", "body": "New drop!", "destination_url": f"https://{SITE_HOST}/x/"}, False),
    ("stille-time", {"channel": "facebook", "title": "T", "body": "A moth with brass wings, printed to order for the wall.",
                     "destination_url": f"https://{SITE_HOST}/x/", "scheduled_at": "2030-01-01 02:30:00"}, False),
    ("passeret tid", {"channel": "facebook", "title": "T", "body": "A moth with brass wings, printed to order for the wall.",
                      "destination_url": f"https://{SITE_HOST}/x/", "scheduled_at": "2020-01-01 12:00:00"}, False),
    ("ukendt felt", {"channel": "facebook", "title": "T", "body": "A moth with brass wings, printed to order for the wall.",
                     "destination_url": f"https://{SITE_HOST}/x/", "hashtag": "#x"}, False),
]


def selftest() -> int:
    failures = 0
    for label, item, expected in FIXTURES:
        result = validate({"items": [item]}, now=datetime(2029, 1, 1))
        ok = result["ok"] if expected else not result["ok"]
        print(f"[{'OK' if ok else 'FEJL'}] {label}")
        failures += 0 if ok else 1

    base = {"channel": "facebook", "body": "A moth with brass wings, printed to order for the wall.",
            "destination_url": f"https://{SITE_HOST}/x/"}
    duplicate = {"items": [dict(base, title="T"), dict(base, title="T")]}
    if validate(duplicate, now=datetime(2029, 1, 1))["ok"]:
        print("[FEJL] to identiske poster skal afvises (fingerprint)")
        failures += 1
    else:
        print("[OK] to identiske poster afvises (fingerprint)")

    near = {"items": [dict(base, title="A", body="A clockwork rose behind glass on the back of your phone case today"),
                      dict(base, title="B", body="A clockwork rose behind glass on the back of my phone case today")]}
    if validate(near, now=datetime(2029, 1, 1))["ok"]:
        print("[FEJL] næsten-identiske captions skal afvises")
        failures += 1
    else:
        print("[OK] næsten-identiske captions afvises")

    capped = {"items": [dict(base, title=f"T{i}", body=f"Caption number {i} about brass moths and printed wall art pieces",
                             scheduled_at="2030-01-01 12:00:00") for i in range(DAILY_CAP + 1)]}
    if validate(capped, now=datetime(2029, 1, 1))["ok"]:
        print("[FEJL] flere poster end daily_cap samme dag skal afvises")
        failures += 1
    else:
        print("[OK] dagligt loft håndhæves")

    print(f"\n{'Alle fixtures bestod' if not failures else f'{failures} fixtures fejlede'}")
    return 0 if not failures else 1


def main() -> int:
    parser = argparse.ArgumentParser(description="Værn og indlæser for en kampagne-kø.")
    parser.add_argument("file", nargs="?", help="kampagne-JSON")
    parser.add_argument("--post", action="store_true", help="læg posterne i køen via REST")
    parser.add_argument("--selftest", action="store_true", help="kør fixtures")
    args = parser.parse_args()

    if args.selftest:
        return selftest()
    if not args.file:
        parser.print_help()
        return 1

    path = Path(args.file)
    if not path.exists():
        print(f"Filen {path} findes ikke.")
        return 1
    try:
        payload = json.loads(path.read_text(encoding="utf-8"))
    except ValueError as exc:
        print(f"{path} er ikke gyldig JSON: {exc}")
        return 1

    result = validate(payload)
    summarize(payload, result)
    if not result["ok"] or not args.post:
        return 0 if result["ok"] else 1

    bearer = token()
    if not bearer:
        print("Ingen credential — kør tools/mcp_oauth.py --start, eller sæt MCP_ACCESS_TOKEN.")
        return 1
    return push(result["items"], bearer)


if __name__ == "__main__":
    raise SystemExit(main())
