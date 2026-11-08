#!/usr/bin/env python3
"""tools/s5_measure.py — måler S5-kravene udefra, uden adgang til sitet.

Køreplanen for nye sites (docs/library/website-guide-2026-11-08.md §9) stiller 27
krav. Tretten af dem kan ses af enhver klient, og det er dem her. De øvrige kræver
MCP, hostpanel eller ejeren og står derfor ikke i tabellen.

    python3 tools/s5_measure.py                 # tabel + rapport, exit 0
    python3 tools/s5_measure.py --strict        # exit 1 hvis et krav fejler
    python3 tools/s5_measure.py --json          # maskinlæsbar

Brugen er den samme før og efter en ændring: før S5.3–S5.6 blev lagt på sitet,
skal de syv »mangler«-rækker skifte til »opfyldt«. Chrome-UA giver 454 fra
Simplys WAF (målt 8/11), så værktøjet sender curl-UA som standard.

Ingen tredjepartspakker: kun urllib, så det kan køre i CI uden installation.
"""

from __future__ import annotations

import argparse
import json
import re
import ssl
import sys
import urllib.error
import urllib.request
from dataclasses import dataclass, field
from datetime import date
from pathlib import Path

BASE = "https://steamgears.eu"
UA = "curl/8.5.0"
TIMEOUT = 30

STATUS_PASS = "opfyldt"
STATUS_FAIL = "mangler"
STATUS_UNKNOWN = "kan ikke afgøres"


class NoRedirect(urllib.request.HTTPRedirectHandler):
    """Vi vil se 301'erne, ikke følge dem."""

    def redirect_request(self, req, fp, code, msg, headers, newurl):
        return None


@dataclass
class Check:
    krav: str
    status: str
    maalt: str


@dataclass
class Result:
    checks: list[Check] = field(default_factory=list)
    raw: dict = field(default_factory=dict)

    def add(self, krav: str, status: str, maalt: str) -> None:
        self.checks.append(Check(krav, status, maalt))

    def count(self, status: str) -> int:
        return sum(1 for c in self.checks if c.status == status)


def fetch(url: str, method: str = "GET", data: bytes | None = None,
          headers: dict | None = None, follow: bool = False) -> tuple[int, dict, bytes]:
    """Hent en URL og returnér (status, hoveder, krop). Netværksfejl bliver status 0."""
    hdrs = {"User-Agent": UA, "Accept": "*/*"}
    if headers:
        hdrs.update(headers)
    handlers = [urllib.request.HTTPSHandler(context=ssl.create_default_context())]
    if not follow:
        handlers.append(NoRedirect())
    opener = urllib.request.build_opener(*handlers)
    req = urllib.request.Request(url, data=data, headers=hdrs, method=method)
    try:
        with opener.open(req, timeout=TIMEOUT) as resp:
            return resp.status, {k.lower(): v for k, v in resp.headers.items()}, resp.read(2_000_000)
    except urllib.error.HTTPError as exc:
        return exc.code, {k.lower(): v for k, v in (exc.headers or {}).items()}, b""
    except Exception as exc:  # DNS, TLS, timeout
        return 0, {"_fejl": str(exc)}, b""


def max_age(cache_control: str) -> int | None:
    match = re.search(r"max-age=(\d+)", cache_control or "")
    return int(match.group(1)) if match else None


def measure(base: str = BASE) -> Result:
    r = Result()
    host = base.split("//", 1)[-1]

    # 1-3: transport
    code, hdrs, _ = fetch(base.replace("https://", "http://", 1) + "/")
    r.add("http → https", STATUS_PASS if code == 301 and hdrs.get("location", "").startswith("https://") else STATUS_FAIL,
          f"{code} → {hdrs.get('location', '—')}")
    code, hdrs, _ = fetch(base + "/")
    hsts = hdrs.get("strict-transport-security", "")
    r.add("HSTS", STATUS_PASS if "max-age=" in hsts and "includeSubDomains" in hsts else STATUS_FAIL, hsts or "—")
    code, hdrs, _ = fetch(f"https://www.{host}/")
    r.add("www → apex", STATUS_PASS if code == 301 and hdrs.get("location", "").rstrip("/") == base else STATUS_FAIL,
          f"{code} → {hdrs.get('location', '—')}")

    # 4-6: identitet
    code, hdrs, body = fetch(base + "/")
    html = body.decode("utf-8", "replace")
    gen = re.search(r'<meta name="generator" content="([^"]+)"', html)
    wp_gen = bool(gen and "wordpress" in gen.group(1).lower())
    r.add("ingen WordPress-version i generator", STATUS_FAIL if wp_gen else STATUS_PASS,
          f"generator = {gen.group(1)}" if gen else "ingen generator-meta")

    code, _, _ = fetch(base + "/readme.html")
    r.add("readme.html utilgængelig", STATUS_PASS if code in (403, 404, 455) else STATUS_FAIL, str(code))

    code, _, body = fetch(base + "/wp-json/wp/v2/users?per_page=100")
    if code == 200:
        try:
            users = json.loads(body.decode("utf-8", "replace"))
            r.add("brugernavne ikke læsbare", STATUS_FAIL, f"200 med {len(users)} brugere")
        except json.JSONDecodeError:
            r.add("brugernavne ikke læsbare", STATUS_FAIL, "200 uden gyldig JSON")
    else:
        r.add("brugernavne ikke læsbare", STATUS_PASS, str(code))

    code, hdrs, _ = fetch(base + "/?author=1")
    loc = hdrs.get("location", "")
    if code in (301, 302) and "/author/" in loc:
        r.add("?author= ikke enumererbar", STATUS_FAIL, f"{code} → {loc}")
    else:
        r.add("?author= ikke enumererbar", STATUS_PASS, f"{code} → {loc or '—'}")

    code, _, body = fetch(base + "/author/humle91/")
    html = body.decode("utf-8", "replace")
    robots = re.search(r'<meta name="robots" content="([^"]*)"', html)
    noindex = bool(robots and "noindex" in robots.group(1))
    r.add("forfatterarkiv noindex", STATUS_PASS if noindex or code == 404 else STATUS_FAIL,
          f"{code}, robots = {robots.group(1) if robots else '—'}")

    # 7: XML-RPC
    xml = ('<?xml version="1.0"?><methodCall><methodName>system.listMethods</methodName>'
           '<params/></methodCall>').encode()
    code, _, body = fetch(base + "/xmlrpc.php", method="POST", data=xml,
                          headers={"Content-Type": "text/xml"})
    enabled = code == 200 and b"system.multicall" in body
    r.add("XML-RPC slået fra", STATUS_FAIL if enabled else STATUS_PASS,
          f"POST → {code}" + (" med system.multicall" if enabled else ""))

    # 8-12: headers på siderne hvor pengene er
    wanted = {
        "x-frame-options": "X-Frame-Options",
        "content-security-policy": "frame-ancestors",
        "permissions-policy": "Permissions-Policy",
        "referrer-policy": "strict-origin-when-cross-origin",
    }
    for path in ("/", "/shop/", "/cart/", "/checkout/"):
        code, hdrs, _ = fetch(base + path)
        missing = [label for key, label in wanted.items()
                   if (wanted[key] == "frame-ancestors" and "frame-ancestors" not in hdrs.get(key, ""))
                   or (wanted[key] != "frame-ancestors" and not hdrs.get(key))]
        r.add(f"sikkerhedsheaders på {path}",
              STATUS_PASS if not missing else STATUS_FAIL,
              "alle fire" if not missing else "mangler: " + ", ".join(missing))

    # 13-18: caching, indeksering og hygiejne
    code, hdrs, _ = fetch(base + "/cart/")
    cc = hdrs.get("cache-control", "")
    r.add("kurv og kasse ikke cachet", STATUS_PASS if "private" in cc and "no-cache" in cc else STATUS_FAIL, cc or "—")

    code, hdrs, body = fetch(base + "/wp-json/")
    age = max_age(hdrs.get("cache-control", ""))
    ok = age is not None and age <= 300
    r.add("REST-indekset caches kort", STATUS_PASS if ok else STATUS_FAIL,
          f"max-age={age}, {len(body)} bytes" if age is not None else f"max-age mangler, {len(body)} bytes")
    r.add("REST noindex", STATUS_PASS if "noindex" in hdrs.get("x-robots-tag", "") else STATUS_FAIL,
          hdrs.get("x-robots-tag", "—"))

    code, _, _ = fetch(base + "/findes-ikke-s5-123/")
    r.add("rigtig 404", STATUS_PASS if code == 404 else STATUS_FAIL, str(code))
    code, _, _ = fetch(base + "/wp-content/uploads/")
    r.add("kataloglistning blokeret", STATUS_PASS if code in (403, 404) else STATUS_FAIL, str(code))
    code, hdrs, _ = fetch(base + "/", headers={"Accept-Encoding": "br"})
    r.add("brotli", STATUS_PASS if hdrs.get("content-encoding") == "br" else STATUS_FAIL,
          hdrs.get("content-encoding", "—"))

    # 19: sandkassen — kan ikke afgøres udefra, men DNS-fejlen er et bevis i sig selv
    for sub in ("staging", "dev", "test"):
        code, hdrs, _ = fetch(f"https://{sub}.{host}/")
        if code == 0:
            r.add("sandkasse findes", STATUS_UNKNOWN, f"{sub}.{host}: ingen TLS-forbindelse ({hdrs.get('_fejl', '')[:60]})")
            break
    else:
        r.add("sandkasse findes", STATUS_UNKNOWN, "subdomæner svarer — undersøg om det er produktion")

    r.raw = {"base": base, "ua": UA, "maalingsdato": date.today().isoformat()}
    return r


def render_table(result: Result) -> str:
    lines = ["| Krav | Målt | Status |", "| --- | --- | --- |"]
    for c in result.checks:
        lines.append(f"| {c.krav} | {c.maalt} | {c.status} |")
    return "\n".join(lines)


def render_report(result: Result) -> str:
    total = len(result.checks)
    return (
        "# S5-måling — køreplanen for nye sites\n\n"
        f"**Målt:** {result.raw['maalingsdato']} · **Mod:** {result.raw['base']} · "
        f"**UA:** `{result.raw['ua']}`\n\n"
        f"**Resultat:** {result.count(STATUS_PASS)} af {total} opfyldt · "
        f"{result.count(STATUS_FAIL)} mangler · {result.count(STATUS_UNKNOWN)} kan ikke afgøres\n\n"
        f"{render_table(result)}\n\n"
        "Kørt med `python3 tools/s5_measure.py`. Rækkerne er de samme som i\n"
        "[library/site-mod-koereplan-2026-11-08.md](../docs/library/site-mod-koereplan-2026-11-08.md), "
        "så en ny måling kan lægges ved siden af den gamle.\n"
    )


def main() -> int:
    parser = argparse.ArgumentParser(description="Mål S5-kravene udefra.")
    parser.add_argument("--base", default=BASE)
    parser.add_argument("--json", action="store_true")
    parser.add_argument("--strict", action="store_true", help="exit 1 hvis et krav mangler")
    parser.add_argument("--report", default=None, help="sti til Markdown-rapport")
    args = parser.parse_args()

    result = measure(args.base)

    if args.json:
        print(json.dumps({
            "base": result.raw["base"],
            "dato": result.raw["maalingsdato"],
            "checks": [c.__dict__ for c in result.checks],
            "opfyldt": result.count(STATUS_PASS),
            "mangler": result.count(STATUS_FAIL),
            "kan_ikke_afgoeres": result.count(STATUS_UNKNOWN),
        }, ensure_ascii=False, indent=2))
    else:
        print(f"S5-måling · {result.raw['base']} · {result.raw['maalingsdato']}")
        for c in result.checks:
            mark = {STATUS_PASS: "✓", STATUS_FAIL: "✗", STATUS_UNKNOWN: "?"}[c.status]
            print(f"  {mark} {c.krav:<38} {c.maalt}")
        print(f"\n{result.count(STATUS_PASS)} opfyldt · {result.count(STATUS_FAIL)} mangler · "
              f"{result.count(STATUS_UNKNOWN)} kan ikke afgøres")

    report = args.report or f"reports/s5-status-{result.raw['maalingsdato']}.md"
    path = Path(report)
    path.parent.mkdir(parents=True, exist_ok=True)
    path.write_text(render_report(result), encoding="utf-8")
    if not args.json:
        print(f"rapport: {path}")

    return 1 if (args.strict and result.count(STATUS_FAIL)) else 0


if __name__ == "__main__":
    sys.exit(main())
