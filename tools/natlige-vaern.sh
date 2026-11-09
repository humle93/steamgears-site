#!/usr/bin/env bash
# tools/natlige-vaern.sh — alle repoets værn på ét sted.
#
#   bash tools/natlige-vaern.sh          # samme kørsel som den natlige CI-kørsel
#   PHP_BIN=php8.3 bash tools/natlige-vaern.sh
#
# Hvert værn kører i sit eget log-loop og afgøres på **exit-koden**, ikke på
# teksten: projektstatus' selftest skriver med vilje en "[FEJL]"-linje fra et
# negativt fixture og afslutter 0, så en grep-baseret dom ville tage fejl.
#
# Fejler ét eller flere værn, skrives en tabel og de fejlende linjer til
# $GITHUB_STEP_SUMMARY (når den er sat), hvert fejlende værn får en
# ::error-annotation, og scriptet afslutter 1 — så jobbet bliver rødt.
set -uo pipefail

cd "$(dirname "${BASH_SOURCE[0]}")/.." || exit 1

PHP_BIN="${PHP_BIN:-}"
if [ -z "$PHP_BIN" ]; then
  if command -v php >/dev/null 2>&1; then
    PHP_BIN="php"
  else
    PHP_BIN="tools/_deps/bin/php"
  fi
fi
if [ ! -x "$PHP_BIN" ] && ! command -v "$PHP_BIN" >/dev/null 2>&1; then
  echo "::error title=PHP mangler::Kunne ikke finde en php-binær (prøvede '$PHP_BIN'). Kør bash tools/setup-agent-tools.sh."
  exit 1
fi

LOG_DIR="${RUNNER_TEMP:-$(mktemp -d)}/natlige-vaern"
mkdir -p "$LOG_DIR"

NAMES=(); STATUS=(); LOGS=(); fail=0

record() { NAMES+=("$1"); STATUS+=("$2"); LOGS+=("$3"); [ "$2" -ne 0 ] && fail=1; return 0; }

check() { # navn, kommando...
  local name="$1"; shift
  local slug log code
  slug="$(printf '%s' "$name" | tr -cs '[:alnum:]' '-' | tr '[:upper:]' '[:lower:]' | sed 's/^-//;s/-$//')"
  log="$LOG_DIR/$slug.log"
  printf '▶ %s\n' "$name"
  "$@" >"$log" 2>&1
  code=$?
  if [ "$code" -eq 0 ]; then
    printf '  OK\n'
  else
    printf '  FEJL (exit %s)\n' "$code"
    tail -5 "$log" | sed 's/^/    /'
  fi
  record "$name" "$code" "$log"
}

lint_php() {
  local count
  count="$(find plugins wordpress -name '*.php' | wc -l | tr -d ' ')"
  printf '  linter %s PHP-filer med %s\n' "$count" "$("$PHP_BIN" -r 'echo PHP_VERSION;')"
  find plugins wordpress -name '*.php' -print0 | xargs -0 -n1 "$PHP_BIN" -l
}

# Rækkefølgen er billig → dyr, så den første fejl ses hurtigt.
check "php -l (plugins)"                     lint_php
check "projektstatus --selftest"             python3 projektstatus.py --selftest
check "projektstatus --check"                python3 projektstatus.py --check
check "commit-besked --selftest"             python3 tools/commit_check.py --selftest
check "tilbudssammenligning --selftest"      python3 tools/tilbuds_sammenligning.py --selftest
check "tilbudssammenligning (eksempelark)"   python3 tools/tilbuds_sammenligning.py tools/fixtures/tilbud-eksempel.tsv --cpa 281
check "kampagne-indlæser --selftest"         python3 tools/load_campaign.py --selftest
check "plugin-smoke"                         "$PHP_BIN" plugins/tests/smoke.php
check "updater-cache"                       "$PHP_BIN" plugins/tests/updater_cache.php
check "MCP REST-klient"                      python3 tools/test_mcp_rest.py
check "MCP-klassificering"                   python3 tools/test_mcp_klassificer.py
check "MCP OAuth"                            python3 tools/test_mcp_oauth.py
check "MCP keep-alive"                       python3 tools/test_mcp_keepalive.py
check "live-tilstand (MCP + profil)"         python3 tools/test_live_state.py
check "live-kanaler (P1.5)"                python3 tools/test_live_channels.py
check "niche-scanner"                        python3 tools/test_niche_scanner.py
check "link-plan-selftest"                  python3 tools/link_plan_check.py --selftest
check "link-plan"                           python3 tools/link_plan_check.py
check "guide-copy-selftest"                  python3 tools/guide_check.py --selftest
check "guide-copy"                           python3 tools/guide_check.py
check "og-image-selftest"                    python3 tools/og_image_check.py --selftest
check "samtalepunkter-selftest"              python3 tools/samtalepunkter.py --selftest
check "designsystem-selftest"                python3 tools/design_check.py --selftest
check "designsystem"                         python3 tools/design_check.py
check "designsystem-loader"                  "$PHP_BIN" wordpress/tests/test-design-system.php
check "produkt-schema"                       "$PHP_BIN" tools/schema_filter_test.php
# S2.7: payload-trimmen kostede forsiden 7/11 2026, fordi is_woocommerce() blev kaldt
# uden at WooCommerce var indlaest. Vaernet kraever at fravaeret af Woo er en no-op.
check "payload-trim"                         "$PHP_BIN" tools/test_payload_trim.php
# S4.12: fortrydelsesoplysningerne i ordremailen — teksten er kundens durable medium,
# så en regression må ikke kunne snige sig ind uden at en test bliver rød.
check "fortrydelsesmail"                     "$PHP_BIN" tools/withdrawal_email_test.php
# S4.10: anmeldelses-anmodningen — én mail pr. ordre, 14 dage efter levering, ingen
# incitamenter. En regression her koster kunder, ikke en fejlkode, så den skal fanges her.
check "anmeldelsesmail"                      "$PHP_BIN" wordpress/tests/test-review-request.php
# S5.3–S5.6: hardningen retter seks ting der alle er usynlige når de fejler — en header
# der ikke sendes, giver ingen fejlkode. Testen kræver derfor hver regel for sig, offline.
check "hardning (S5)"                        "$PHP_BIN" wordpress/tests/test-hardening.php
# S5.10: hardningen ligger på sitet fra 9/11, så den udefra målte tilstand holdes op hver nat.
# Testen ovenfor beviser at reglerne er kodet rigtigt; denne måling beviser at de også når
# klienten gennem W3TCs sidecache — det var netop der de tre headere forsvandt.
check "S5-måling (live)"                     python3 tools/s5_measure.py --strict
# S5.9: promote-gaten. Preflighten er det eneste der står mellem et uheld og et
# release-tag, så dens 24 fixtures kører hver nat — de rammer ikke git-historikken.
check "release-gate (S5.9)"                  python3 tools/test_release.py
# S5.11: backup-øvelsen var et engangstal (kørt 9/11). Fristen og tallene holdes op hver
# nat: seneste kørsel må højst være 31 dage gammel, og den skal være grøn — 0
# rækkeafvigelser, 0 mangler, 0 rester og RTO under budget. Historikken i
# reports/db-drill-history.json gør RTO og paritet til en serie i stedet for ét tal.
check "backup-øvelse-selftest"               python3 tools/db_drill_check.py --selftest
check "backup-øvelse (frist + paritet)"      python3 tools/db_drill_check.py --strict
# Fejlsøgning som fast punkt (P3.6): konsistens på tværs af filer — version mod zip,
# zip mod kilde, includes, biblioteksindekset, tokens og tests der ikke kan fejle.
# Om-mærkningen (S4): titler/kollektioner/billedkrav måles udefra — selftesten er offline og fanger
# at familiekrav eller dommen driver, uden at CI nogensinde rammer netværket.
check "rebrand-audit-selftest"               python3 tools/rebrand_audit.py --selftest
# Tildelingen (batch 2) er ren funktion: kortet, overlap og idempotens testes offline.
check "rebrand-apply-selftest"               python3 tools/rebrand_apply.py --selftest
check "fejlscan-selftest"                    python3 tools/fejlscan.py --selftest
check "fejlscan"                             python3 tools/fejlscan.py
# De fire niche-payloads: økonomien regnes efter på ny, ejerfelterne tælles, og
# "ads: true" på en vare under 120 kr i break-even-CPA afvises. Driftstjekket fanger
# en håndredigeret payload, som ellers ville blive importeret som den var.
check "niche-payloads"                       python3 tools/deploy/niche_preflight.py --check

# Rendereren fejlede 2/11 på to måder der ikke gav en fejlkode: et sidebrud midt i et afsnit
# skrev resten af dokumentet uden for papiret (niche-vurderingen blev 20 sider), og glyffer
# som ✅ forsvandt lydløst. Værnet tjekker derfor sideantal og at der ikke advares om glyffer.
render_pdf() {
  local out; out="$(mktemp -d)"
  PYTHONPATH=tools/_deps python3 - "$out" <<'PY'
import re, subprocess, sys, pathlib
out = pathlib.Path(sys.argv[1])
cases = {"niche-vurdering-2026-11-01": 6, "navigation-spec-2026-11-02": 5, "mcp-vaerktoejer-2026-11-02": 9}
fejl = []
for name, maks in cases.items():
    pdf = out / f"{name}.pdf"
    p = subprocess.run([sys.executable, "tools/md_to_pdf.py", f"docs/library/{name}.md", str(pdf)],
                       capture_output=True, text=True)
    if p.returncode != 0:
        fejl.append(f"{name}: rendereren fejlede ({p.returncode}): {p.stderr.strip()[:200]}")
        continue
    if "advarsel" in p.stdout:
        fejl.append(f"{name}: {p.stdout.strip().splitlines()[-1]}")
    sys.path.insert(0, "tools/_deps")
    import pypdfium2 as pdfium
    sider = len(pdfium.PdfDocument(str(pdf)))
    if sider > maks:
        fejl.append(f"{name}: {sider} sider (forventet højst {maks}) — sidebrud eller tomme sider?")
    tekst = "".join(pdfium.PdfDocument(str(pdf))[i].get_textpage().get_text_range() for i in range(sider))
    if re.search(r"(?<![\w*])\*[A-Za-zÆØÅæøå]", tekst):
        fejl.append(f"{name}: stjerner står stadig som kursiv i teksten")
for f in fejl:
    print("FAIL", f)
sys.exit(1 if fejl else 0)
PY
}

check "PDF-renderer"                         render_pdf

passed=0
for i in "${!NAMES[@]}"; do [ "${STATUS[$i]}" -eq 0 ] && passed=$((passed + 1)); done
total="${#NAMES[@]}"

{
  printf '## Natlige værn: %s af %s grønne\n\n' "$passed" "$total"
  printf '| Værn | Status |\n| --- | --- |\n'
  for i in "${!NAMES[@]}"; do
    if [ "${STATUS[$i]}" -eq 0 ]; then
      printf '| %s | ✅ |\n' "${NAMES[$i]}"
    else
      printf '| %s | ❌ exit %s |\n' "${NAMES[$i]}" "${STATUS[$i]}"
    fi
  done
  if [ "$fail" -ne 0 ]; then
    printf '\n### Fejlende linjer\n\n'
    for i in "${!NAMES[@]}"; do
      [ "${STATUS[$i]}" -eq 0 ] && continue
      printf '**%s**\n\n```\n' "${NAMES[$i]}"
      grep -E '^(FAIL|\[FEJL\])|Parse error|Traceback \(|AssertionError' "${LOGS[$i]}" | head -10
      printf '```\n\n'
    done
  fi
} >>"${GITHUB_STEP_SUMMARY:-$LOG_DIR/summary.md}"

if [ "$fail" -ne 0 ]; then
  for i in "${!NAMES[@]}"; do
    [ "${STATUS[$i]}" -eq 0 ] && continue
    echo "::error title=${NAMES[$i]} fejlede::exit ${STATUS[$i]} — se loggen og job-summaryen."
  done
  printf '\n%s af %s værn fejlede. Logfiler: %s\n' "$((total - passed))" "$total" "$LOG_DIR"
  exit 1
fi

printf '\nAlle %s værn grønne.\n' "$total"
