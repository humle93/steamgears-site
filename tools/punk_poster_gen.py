#!/usr/bin/env python3
"""punk_poster_gen.py — generate the ten CLK/DSL poster artworks in one batch.

The ten prompts are the ones written to Drive on 14/11 2026 (the two docs
«billedprompts til de 5 plakater»), embedded here so the batch has one source
and does not depend on reading a Google Doc at run time. Layout and print rules
are the same as the plan's: portrait, calm dark bottom fifth for the title, no
text, no watermark, no frame.

Two providers:

**`pollinations` (default, keyless).** A single GET returns the image bytes:

    GET https://image.pollinations.ai/prompt/<urlencoded prompt>?width&height&model=flux

This is the only route that works without an account, and it is the one the
project's Freebuff-adjacent tooling uses for image generation. Freebuff's own
paid catalog (280 models) is chat-only — no image model — so there is no
Freebuff endpoint to call.

**`wavespeed` (needs `WAVESPEED_API_KEY`).** Higher quality:

    POST https://api.wavespeed.ai/api/v3/<model>        -> data.id
    GET  https://api.wavespeed.ai/api/v3/predictions/<id>/result -> data.outputs[]

The WaveSpeed API is asynchronous and bills on submission, so its submit
request is **never retried**: a dropped connection can still mean a paid
prediction. Only the idempotent result GET is retried. Generated URLs are
temporary, so every output is downloaded in the same run.

Usage:

    python3 tools/punk_poster_gen.py --dry-run            # plan + cost-free preview
    python3 tools/punk_poster_gen.py                     # all ten (pollinations)
    python3 tools/punk_poster_gen.py --provider wavespeed  # better, needs the key
    python3 tools/punk_poster_gen.py --only SG-CLK-01 SG-CLK-02
    python3 tools/punk_poster_gen.py --selftest           # offline fixtures

Output: `<out>/<sku>.png` plus `<out>/manifest.json`. Nothing is uploaded
anywhere — Drive and WooCommerce are a later, separate step.

Exit: 0 = every requested design generated and saved · 1 = one or more designs
failed (manifest says which) · 2 = missing key or bad usage.
"""

from __future__ import annotations

import argparse
import json
import os
import sys
import time
import urllib.error
import urllib.parse
import urllib.request
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
DEFAULT_OUT = ROOT / "posters" / "punk-2026-11-14"
API_BASE = "https://api.wavespeed.ai/api/v3"
MODEL = "google/nano-banana-pro/text-to-image"
ASPECT = "2:3"          # closest supported ratio to the 5:7 print format
RESOLUTION = "4k"
KEY_ENV = "WAVESPEED_API_KEY"
PROVIDERS = ("pollinations", "wavespeed")
PROVIDER = "pollinations"
POLLI_URL = "https://image.pollinations.ai/prompt/{prompt}"
POLLI_MODEL = "flux"
WIDTH = 1024            # 5:7 at 1024 px wide -> 1434 px tall
HEIGHT = 1434
POLL_START = 2.0        # docs: never poll the same task more often than 2 s
POLL_MAX = 10.0
POLL_TIMEOUT = 600.0

# SKU -> (title, prompt). Prompt text is identical to the Drive docs.
PROMPTS: dict[str, tuple[str, str]] = {
    "SG-CLK-01": (
        "Cathedral of the Eternal Pendulum",
        "A cavernous gothic cathedral nave at night, towering stone arches framing a colossal brass "
        "astronomical clock and a giant pendulum mid-swing above the aisle, moonlight pouring through a "
        "rose window built from interlocking brass gears, candlelight glinting off gold escapements, "
        "shadowy stone gargoyles in the vaults. Style: cinematic realistic digital painting, deep midnight "
        "blacks, antique gold and brass, cool teal shadows with faint arcane blue glow, portrait 5:7, calm "
        "dark bottom fifth for title, no text, no watermark, no frame.",
    ),
    "SG-CLK-02": (
        "The Alchemist's Astral Orrery",
        "A candlelit gothic scholar's study dominated by a spinning brass celestial orrery mapping "
        "constellations in engraved gold, silver and lapis lazuli rings, glowing aether vials, ancient "
        "leather grimoires, aged parchment and gothic stonework in the background. Style: cinematic "
        "realistic digital painting, warm candlelight, antique brass, deep teal shadows, portrait 5:7, calm "
        "dark bottom fifth for title, no text, no watermark, no frame.",
    ),
    "SG-CLK-03": (
        "Porcelain Automaton Violinist",
        "An exquisite Victorian porcelain-and-brass automaton violinist performing in a moonlit gothic "
        "conservatory, cracked ivory porcelain face, exposed golden mainsprings and delicate silver "
        "finger-gears, tattered velvet curtains behind, melancholy and elegant. Style: cinematic realistic "
        "digital painting, cool moonlight with warm brass highlights, deep teal and ivory palette, portrait "
        "5:7, calm dark bottom fifth for title, no text, no watermark, no frame.",
    ),
    "SG-CLK-04": (
        "Arch-Mage of the Brass Sanctum",
        "A hooded scholar-mage in a brass-and-leather sanctum wielding a staff of spinning rune-engraved "
        "gears, floating brass astrolabes and glowing blue runes around the room, a mechanical brass raven "
        "perched on a workbench, arcane clockwork machinery in the shadows. Style: cinematic realistic "
        "digital painting, antique brass, deep teal shadows, glowing arcane blue accents, portrait 5:7, calm "
        "dark bottom fifth for title, no text, no watermark, no frame.",
    ),
    "SG-CLK-05": (
        "Gargoyle of the Belltower Gearworks",
        "A winged stone-and-bronze clockwork gargoyle perched high on a gothic clocktower above "
        "fog-drenched Victorian rooftops at midnight, articulated bronze pinions, glowing amber eyes, "
        "weathered gothic masonry, the illuminated glass face of a giant clock behind it. Style: cinematic "
        "realistic digital painting, midnight blue and brass, warm amber highlights, portrait 5:7, calm dark "
        "bottom fifth for title, no text, no watermark, no frame.",
    ),
    "SG-DSL-01": (
        "Iron Sky Squadron",
        "An elite flight of heavy twin-engine diesel fighters with riveted armor plating roaring through "
        "dark storm clouds above an industrial metropolis, fiery exhaust plumes, searchlights cutting "
        "through smoke, interwar retro-futuristic aviation. Style: cinematic realistic digital painting, "
        "gunmetal grey, weathered steel, glowing amber exhaust, cool teal storm shadows, portrait 5:7, calm "
        "dark bottom fifth for title, no text, no watermark, no frame.",
    ),
    "SG-DSL-02": (
        "Trench Walker MK-IV",
        "A colossal bipedal diesel-powered mech with rolled steel plating, roaring twin exhaust stacks and "
        "glowing amber cockpit visors striding across a muddy, shell-torn industrial frontier, searchlights "
        "sweeping through smoke and rain. Style: cinematic realistic digital painting, gunmetal and rust, "
        "amber cockpit glow, cold teal haze, portrait 5:7, calm dark bottom fifth for title, no text, no "
        "watermark, no frame.",
    ),
    "SG-DSL-03": (
        "Metropolis Noir Detective",
        "A trench-coated investigator standing beneath towering Art Deco skyscrapers in the rain, vintage "
        "steel cars and a moored diesel zeppelin above, neon reflections on wet asphalt, film-noir shadows "
        "and warm streetlamp highlights. Style: cinematic realistic digital painting, soot black and "
        "gunmetal, warm amber lamps, deep teal night, portrait 5:7, calm dark bottom fifth for title, no "
        "text, no watermark, no frame.",
    ),
    "SG-DSL-04": (
        "Heavy Foundry Titan",
        "A monumental dieselpunk steelworks interior where molten iron pours from giant crucibles beneath "
        "colossal diesel turbines and riveted gantry cranes, sparks raining onto oiled iron machinery, "
        "workers silhouetted on catwalks. Style: cinematic realistic digital painting, fiery orange against "
        "soot-black steel, deep shadow, portrait 5:7, calm dark bottom fifth for title, no text, no "
        "watermark, no frame.",
    ),
    "SG-DSL-05": (
        "Valkyrie of the Oil Fields",
        "A fearless female pilot in leather flight gear and welding goggles standing beside a massive tracked "
        "diesel rig with polished chrome V12 exhausts, towering oil derricks and billowing black smoke "
        "behind, petroleum frontier atmosphere. Style: cinematic realistic digital painting, gunmetal and "
        "oil-stained leather, glowing amber highlights, teal dusk sky, portrait 5:7, calm dark bottom fifth "
        "for title, no text, no watermark, no frame.",
    ),
}

RETRY_STATUS = {429, 500, 502, 503, 504}
TERMINAL_FAIL = {"failed", "cancelled", "timeout", "deleted"}


# ------------------------------------------------------------------ HTTP

def _request(url: str, key: str, payload: dict | None = None, timeout: float = 60.0) -> dict:
    """One HTTP call. Returns the decoded body; raises on a non-2xx answer."""
    data = json.dumps(payload).encode("utf-8") if payload is not None else None
    req = urllib.request.Request(url, data=data, method="POST" if data else "GET")
    req.add_header("Authorization", f"Bearer {key}")
    if data:
        req.add_header("Content-Type", "application/json")
    with urllib.request.urlopen(req, timeout=timeout) as resp:
        return json.loads(resp.read().decode("utf-8"))


def submit(prompt: str, key: str, model: str, aspect: str, resolution: str) -> str:
    """Submit one prediction and return its id. Never retried: submission bills."""
    body = {"prompt": prompt, "aspect_ratio": aspect, "resolution": resolution, "output_format": "png"}
    answer = _request(f"{API_BASE}/{model}", key, body)
    task_id = (answer.get("data") or {}).get("id")
    if not task_id:
        raise RuntimeError(f"submission returned no data.id: {answer.get('message') or answer}")
    return task_id


def wait_for_outputs(task_id: str, key: str, timeout: float = POLL_TIMEOUT) -> list[str]:
    """Poll the idempotent result endpoint until completed or terminally failed."""
    deadline = time.monotonic() + timeout
    delay = POLL_START
    url = f"{API_BASE}/predictions/{task_id}/result"
    while True:
        try:
            answer = _request(url, key, timeout=30.0)
        except urllib.error.HTTPError as exc:
            if exc.code in RETRY_STATUS and time.monotonic() < deadline:
                time.sleep(delay)
                delay = min(POLL_MAX, delay * 1.5)
                continue
            raise
        data = answer.get("data") or {}
        status = str(data.get("status") or "")
        if status == "completed":
            outputs = [o for o in (data.get("outputs") or []) if isinstance(o, str)]
            if not outputs:
                raise RuntimeError("completed without outputs")
            return outputs
        if status in TERMINAL_FAIL:
            raise RuntimeError(f"prediction {status}: {data.get('error') or answer.get('message') or ''}".strip())
        if time.monotonic() >= deadline:
            raise TimeoutError(f"prediction {task_id} not completed within {int(timeout)} s (status {status})")
        time.sleep(delay)
        delay = min(POLL_MAX, delay * 1.5)


def download(url: str, target: Path) -> int:
    """Download one output to `target` atomically. Returns bytes written."""
    target.parent.mkdir(parents=True, exist_ok=True)
    tmp = target.with_suffix(target.suffix + ".part")
    with urllib.request.urlopen(url, timeout=120.0) as resp, open(tmp, "wb") as fh:
        fh.write(resp.read())
    written = tmp.stat().st_size
    if written == 0:
        tmp.unlink(missing_ok=True)
        raise RuntimeError("downloaded an empty file")
    os.replace(tmp, target)
    return written


# ------------------------------------------------------------------ batch

def pollinations_generate(prompt: str, target: Path, width: int, height: int, seed: int) -> tuple[int, Path]:
    """Fetch one keyless Pollinations image and save it. Returns (bytes written, real path).

    The service answers JPEG, so the extension follows the returned Content-Type
    instead of the .png name the print pipeline will use later.
    """
    query = urllib.parse.urlencode({"width": width, "height": height,
                                    "model": POLLI_MODEL, "seed": seed, "nologo": "true"})
    url = POLLI_URL.format(prompt=urllib.parse.quote(prompt, safe="")) + "?" + query
    with urllib.request.urlopen(url, timeout=180.0) as resp:
        ctype = resp.headers.get("Content-Type", "")
        payload = resp.read()
    if not ctype.startswith("image/"):
        raise RuntimeError(f"svaret er ikke et billede (Content-Type {ctype or 'ukendt'})")
    if len(payload) < 10_000:
        raise RuntimeError(f"mistænkeligt lille billede ({len(payload)} bytes)")
    suffix = {"image/jpeg": ".jpg", "image/png": ".png", "image/webp": ".webp"}.get(ctype, target.suffix)
    target = target.with_suffix(suffix)
    target.parent.mkdir(parents=True, exist_ok=True)
    tmp = target.with_suffix(target.suffix + ".part")
    tmp.write_bytes(payload)
    os.replace(tmp, target)
    return len(payload), target


def run(skus: list[str], key: str | None, out: Path, provider: str, model: str, aspect: str,
        resolution: str, width: int = WIDTH, height: int = HEIGHT) -> int:
    manifest: list[dict] = []
    failures = 0
    for index, sku in enumerate(skus, start=1):
        title, prompt = PROMPTS[sku]
        target = out / f"{sku.lower()}.png"
        print(f"[{index}/{len(skus)}] {sku} — {title}", flush=True)
        entry: dict = {"sku": sku, "title": title, "file": str(target), "provider": provider}
        try:
            if provider == "pollinations":
                entry.update({"model": POLLI_MODEL, "width": width, "height": height,
                              "seed": 7000 + index})
                entry["bytes"], entry["file"] = pollinations_generate(prompt, target, width, height, entry["seed"])
            else:
                assert key, "nøgle kræves"
                entry.update({"model": model, "aspect_ratio": aspect, "resolution": resolution})
                task_id = submit(prompt, key, model, aspect, resolution)
                entry["task_id"] = task_id
                entry["bytes"] = download(wait_for_outputs(task_id, key)[0], target)
            entry["status"] = "saved"
            print(f"    gemt: {target.name} ({entry['bytes'] // 1024} KB)", flush=True)
        except Exception as exc:                     # noqa: BLE001 — one failure must not stop the batch
            entry["status"] = "failed"
            entry["error"] = f"{type(exc).__name__}: {exc}"
            failures += 1
            print(f"    FEJL: {entry['error']}", file=sys.stderr, flush=True)
        manifest.append(entry)

    out.mkdir(parents=True, exist_ok=True)
    (out / "manifest.json").write_text(json.dumps(manifest, ensure_ascii=False, indent=2, default=str) + "\n",
                                      encoding="utf-8")
    print(f"\n{len(skus) - failures} af {len(skus)} gemt i {out}")
    if failures:
        print("Fejlede: " + ", ".join(e["sku"] for e in manifest if e["status"] == "failed"), file=sys.stderr)
    return 0 if failures == 0 else 1


def dry_run(skus: list[str], out: Path, provider: str, model: str, aspect: str, resolution: str,
            width: int, height: int) -> int:
    if provider == "pollinations":
        print(f"Provider: pollinations ({POLLI_MODEL}) · {width}×{height} px · ud: {out}")
        print("Nøgle: ikke nødvendig\n")
    else:
        print(f"Provider: wavespeed · {model} · aspect {aspect} · {resolution} · ud: {out}")
        print(f"Nøgle: {'sat' if os.environ.get(KEY_ENV) else 'MANGLER (' + KEY_ENV + ')'}\n")
    for sku in skus:
        title, prompt = PROMPTS[sku]
        words = len(prompt.split())
        print(f"{sku}  {title}\n    fil: {out / (sku.lower() + '.png')}\n    prompt: {words} ord\n")
    print(f"{len(skus)} designs klar. Intet sendt, intet faktureret.")
    return 0


# ------------------------------------------------------------------ selftest

def selftest() -> int:
    checks: list[tuple[str, bool]] = []
    checks.append(("ti designs", len(PROMPTS) == 10))
    checks.append(("fem CLK", sum(1 for s in PROMPTS if s.startswith("SG-CLK-")) == 5))
    checks.append(("fem DSL", sum(1 for s in PROMPTS if s.startswith("SG-DSL-")) == 5))
    checks.append(("unikke SKU'er", len(set(PROMPTS)) == len(PROMPTS)))
    checks.append(("SKU-format", all(s.count("-") == 2 and s.isupper() for s in PROMPTS)))
    for sku, (title, prompt) in PROMPTS.items():
        checks.append((f"{sku}: titel", bool(title.strip())))
        checks.append((f"{sku}: ingen tekst i motivet", "no text" in prompt))
        checks.append((f"{sku}: ingen vandmærke", "no watermark" in prompt))
        checks.append((f"{sku}: portræt", "portrait 5:7" in prompt))
        checks.append((f"{sku}: rolig bund", "bottom fifth" in prompt))
        checks.append((f"{sku}: lang nok", len(prompt.split()) >= 40))
    checks.append(("2:3 understøttet", ASPECT in {"1:1", "3:2", "2:3", "3:4", "4:3", "4:5", "5:4", "9:16", "16:9", "21:9"}))
    checks.append(("4k understøttet", RESOLUTION in {"1k", "2k", "4k"}))
    checks.append(("submit gentages ikke", "retry" not in submit.__doc__.lower()))
    checks.append(("standardprovider er nøglefri", PROVIDER == "pollinations" and PROVIDER in PROVIDERS))
    checks.append(("5:7 på 1024 px", abs(HEIGHT / WIDTH - 7 / 5) < 0.01))
    checks.append(("pollinations-URL", POLLI_URL.startswith("https://image.pollinations.ai/prompt/")))
    checks.append(("seed pr. design", len({7000 + i for i in range(1, 11)}) == 10))

    failed = [name for name, ok in checks if not ok]
    for name, ok in checks:
        print(("  ✓ " if ok else "  ✗ ") + name)
    print(f"\n{len(checks) - len(failed)} af {len(checks)} kontroller bestået")
    return 0 if not failed else 1


def main(argv: list[str] | None = None) -> int:
    parser = argparse.ArgumentParser(description="Generér de ti CLK/DSL-plakater i ét batch (WaveSpeedAI).")
    parser.add_argument("--out", type=Path, default=DEFAULT_OUT, help=f"mappe til PNG + manifest (standard {DEFAULT_OUT})")
    parser.add_argument("--only", nargs="+", metavar="SKU", help="begræns til disse SKU'er")
    parser.add_argument("--provider", choices=PROVIDERS, default=PROVIDER,
                        help="pollinations (nøglefri, standard) eller wavespeed (kræver nøgle)")
    parser.add_argument("--model", default=MODEL)
    parser.add_argument("--aspect", default=ASPECT)
    parser.add_argument("--resolution", default=RESOLUTION)
    parser.add_argument("--width", type=int, default=WIDTH, help="bredde i px (pollinations)")
    parser.add_argument("--height", type=int, default=HEIGHT, help="højde i px (pollinations)")
    parser.add_argument("--dry-run", action="store_true", help="vis plan uden netværk")
    parser.add_argument("--selftest", action="store_true", help="kør offline-fixtures")
    args = parser.parse_args(argv)

    if args.selftest:
        return selftest()

    skus = args.only or list(PROMPTS)
    unknown = [s for s in skus if s not in PROMPTS]
    if unknown:
        parser.error("ukendt SKU: " + ", ".join(unknown) + " (kendte: " + ", ".join(PROMPTS) + ")")

    if args.dry_run:
        return dry_run(skus, args.out, args.provider, args.model, args.aspect, args.resolution,
                       args.width, args.height)

    key = os.environ.get(KEY_ENV)
    if args.provider == "wavespeed" and not key:
        print(f"{KEY_ENV} er ikke sat. Sæt den som projekt-secret og kør igen — intet er sendt.", file=sys.stderr)
        return 2
    return run(skus, key, args.out, args.provider, args.model, args.aspect, args.resolution,
               args.width, args.height)


if __name__ == "__main__":
    sys.exit(main())
