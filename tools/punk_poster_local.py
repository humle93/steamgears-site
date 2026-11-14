#!/usr/bin/env python3
"""punk_poster_local.py — generate the 19 poster motifs locally with SD-Turbo on CPU.

Source of the motifs: the Drive document «Steamgears – ChatGPT-prompter (19 motiver)»
(`12OrcA_LaeU3FCmUSIsQz0ig1jmxGGjI_V5EEMyr6nwA`) plus the layout rules in «LÆS MIG»
(`1Go_JkhRSgDlGtY-AIwLWvTU3fnRWPYzfg0IBw_UHl9Y`). The prompts below are compressed to
the motif's core because SD-Turbo's text encoder truncates at 77 CLIP tokens; nothing
in the subject is dropped.

Everything runs offline after the first model download
(`stabilityai/sd-turbo`, ~2.5 GB) in a local venv:

    python3 -m venv .venv
    .venv/bin/pip install torch --index-url https://download.pytorch.org/whl/cpu
    .venv/bin/pip install diffusers transformers accelerate safetensors

Usage:

    .venv/bin/python tools/punk_poster_local.py r1:201 r1:202 d1:101
    .venv/bin/python tools/punk_poster_local.py --list

Output: `posters/punk-2026-11-14/raw/<kode>-<seed>.png` at 512×768.
One image takes ~44 s on two CPU cores. The six first motifs (D1–D3, C1–C3) were made
with this same pipeline and the same seeds 101/102/103/104.
"""

from __future__ import annotations

import argparse
import sys
import time
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
OUT = ROOT / "posters" / "punk-2026-11-14" / "raw"
MODEL = "stabilityai/sd-turbo"
WIDTH, HEIGHT = 512, 768
STEPS = 1

PROMPTS = {
    # Airship Chronicles
    "r1": "woman airship pirate captain at the wheel of a steampunk airship, dark red leather coat, "
          "tricorn hat with goggles, auburn hair, brass and copper, clouds at sunset, cinematic digital painting",
    "r2": "woman airship captain with auburn hair and dark red coat leaning over a map table in a brass "
          "cabin, brass sextant, oil lamps, porthole with clouds, warm interior light, cinematic digital painting",
    "r3": "woman airship pirate captain swinging on a rope between two steampunk airships above sunset clouds, "
          "cutlass raised, grappling hooks, smoke and sparks, cinematic digital painting, no text, no signature",
    "r3b": "woman airship captain leaping across a breaking wooden plank between two airships in a stormy "
           "sunset sky, cutlass and pistol, copper balloon envelopes, cannon smoke, cinematic digital painting",
    "r4": "woman airship engineer with leather apron and welding goggles in a steam-filled engine room, "
          "brass gears and pressure valves, furnace glow, cinematic digital painting",
    "r5": "floating sky harbour at dawn, wooden docks on chains beneath a giant balloon, moored steampunk "
          "airships, lighthouse with a brass lamp, pastel clouds below, cinematic digital painting",
    # Realistiske udgaver
    "k1": "copper steampunk airship with a brass gondola gliding over a Victorian city at dusk, chimneys, "
          "clock tower, huge setting sun, birds, cinematic digital painting",
    "k2": "mechanical owl of riveted copper and brass perched on a brass pipe, glowing amber lens eyes, "
          "clockwork visible behind a glass window in its chest, dark teal background, studio light, "
          "cinematic digital painting",
    "k3": "anatomical human heart of copper plates and brass pipes with clockwork visible through a cut-away "
          "window, museum specimen on cream paper, soft even light, realistic scientific illustration",
    "k4": "elegant portrait of a beautiful woman steampunk airship captain in profile, tall top hat with brass "
          "goggles and a red feather, high lace collar, brass choker, oval brass cameo frame, dark damask background, "
          "painterly, no text, no signature",
    "k5": "view from below of an art deco skyscraper at night with a large zeppelin moored at its spire, "
          "brass and gold ornament, searchlight beams, black and gold palette, cinematic digital painting, "
          "no signage, no text, no letters",
    "k6": "single rose with petals of hammered copper and a brass gear at its centre, brass stem with rivets "
          "and two patinated green metal leaves, botanical plate on cream paper, soft light",
    "k7": "vintage circus tent with red and cream stripes under a starry sky, clockwork acrobat on a trapeze "
          "through the open entrance, glowing lanterns, mechanical copper elephant outside, cinematic",
    # Dieselpunkt og clockwork punk
    "d1": "Art deco metropolis at night, huge riveted diesel zeppelin docked at a skyscraper mooring mast, "
          "searchlight beams, rain-slick streets, vintage cars, smoke, gold and black palette, cinematic digital painting",
    "d2": "woman aviator in brown leather jacket, flying helmet and goggles standing beside a silver propeller "
          "airplane on an airfield at dawn, hangars behind, heroic, oil painting, brush strokes, no text",
    "d3": "Colossal riveted diesel walking machine striding through an industrial harbour at sunset, black "
          "smoke, cranes, cargo ships, tiny workers, gunmetal rust and brass, dramatic low angle, cinematic digital painting",
    "c1": "clockwork automaton figure of carved walnut wood and polished brass standing on a workbench in a "
          "Renaissance inventor workshop, visible gears and springs, parchment drawings, astrolabes, candlelight, "
          "cinematic digital painting, no text",
    "c2": "Wooden clockwork ornithopter with canvas wings and brass gears flying over a Renaissance hilltop "
          "city, terracotta roofs, bell towers and cypress trees, golden hour, cinematic digital painting",
    "c3": "Elegant female clockwork automaton, porcelain face, brass joints, deep green velvet Renaissance gown, "
          "standing in a cabinet of curiosities among globes and astrolabes, candlelight, cinematic digital painting",
}


def generate(specs: list[str], out: Path = OUT) -> int:
    import torch
    from diffusers import AutoPipelineForText2Image

    torch.set_num_threads(2)
    pipe = AutoPipelineForText2Image.from_pretrained(
        MODEL, dtype=torch.float32, safety_checker=None, requires_safety_checker=False)
    pipe.set_progress_bar_config(disable=True)

    failures = 0
    for spec in specs:
        try:
            code, seed = spec.split(":")
            if code not in PROMPTS:
                raise KeyError(f"ukendt kode {code}")
            started = time.time()
            image = pipe(prompt=PROMPTS[code], height=HEIGHT, width=WIDTH, num_inference_steps=STEPS,
                         guidance_scale=0.0, generator=torch.Generator().manual_seed(int(seed))).images[0]
            out.mkdir(parents=True, exist_ok=True)
            path = out / f"{code}-{seed}.png"
            image.save(path)
            print(f"{spec} {round(time.time() - started, 1)}s -> {path.relative_to(ROOT)}", flush=True)
        except Exception as exc:                     # noqa: BLE001 — one failure must not stop the batch
            failures += 1
            print(f"{spec} FEJL: {type(exc).__name__}: {exc}", file=sys.stderr, flush=True)
    return 0 if failures == 0 else 1


def main(argv: list[str] | None = None) -> int:
    parser = argparse.ArgumentParser(description="Generér plakatmotiver lokalt med SD-Turbo.")
    parser.add_argument("specs", nargs="*", metavar="KODE:SEED", help="fx r1:201 k2:202")
    parser.add_argument("--out", type=Path, default=OUT)
    parser.add_argument("--list", action="store_true", help="vis motiverne")
    args = parser.parse_args(argv)
    if args.list:
        for code, text in PROMPTS.items():
            print(f"{code:4} {text[:90]}…")
        return 0
    if not args.specs:
        parser.error("angiv mindst én KODE:SEED (eller --list)")
    return generate(args.specs, args.out)


if __name__ == "__main__":
    sys.exit(main())
