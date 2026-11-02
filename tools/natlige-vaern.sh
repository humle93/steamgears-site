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
  count="$(find plugins -name '*.php' | wc -l | tr -d ' ')"
  printf '  linter %s PHP-filer med %s\n' "$count" "$("$PHP_BIN" -r 'echo PHP_VERSION;')"
  find plugins -name '*.php' -print0 | xargs -0 -n1 "$PHP_BIN" -l
}

# Rækkefølgen er billig → dyr, så den første fejl ses hurtigt.
check "php -l (plugins)"                     lint_php
check "projektstatus --selftest"             python3 projektstatus.py --selftest
check "projektstatus --check"                python3 projektstatus.py --check
check "kampagne-indlæser --selftest"         python3 tools/load_campaign.py --selftest
check "plugin-smoke"                         "$PHP_BIN" plugins/tests/smoke.php
check "MCP REST-klient"                      python3 tools/test_mcp_rest.py
check "MCP OAuth"                            python3 tools/test_mcp_oauth.py
check "niche-scanner"                        python3 tools/test_niche_scanner.py

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
