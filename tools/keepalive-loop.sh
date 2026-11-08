#!/usr/bin/env bash
# keepalive-loop.sh — forny Novamira-adgangen løbende i sandkassen.
#
# Access-tokenet lever 3600 s, så en fornyelse hvert 58. minut (3480 s) holder det
# aldrig udløbet. Refresh-tokenet roteres ved hver fornyelse, og tools/mcp_keepalive.py
# gemmer det nye par, før det gamle er dødt.
#
# Brug:
#   bash tools/keepalive-loop.sh            # 3480 s mellem fornyelser
#   bash tools/keepalive-loop.sh 900        # kortere interval (test)
#
# Loggen ligger i .tools/keepalive-loop.log (gitignored).
#
# VIGTIGT: loopet lever kun, så længe sandkassen kører. Arkiveres eller genstartes
# worktreen, stopper processen, og adgangen dør når refresh-tokenets levetid løber ud.
# Start den igen med kommandoen ovenfor. En fornyelse der overlever arkivering kræver
# .github/workflows/novamira-keepalive.yml.
set -uo pipefail

INTERVAL="${1:-3480}"
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
LOG="$ROOT/.tools/keepalive-loop.log"
mkdir -p "$ROOT/.tools"

fails=0
echo $$ >"$ROOT/.tools/keepalive-loop.pid"
echo "$(date -u +%Y-%m-%dT%H:%M:%SZ) loop start, interval ${INTERVAL}s, pid $$" >>"$LOG"

while true; do
    if out="$(cd "$ROOT" && python3 tools/mcp_keepalive.py 2>&1)"; then
        echo "$(date -u +%Y-%m-%dT%H:%M:%SZ) $out" >>"$LOG"
        fails=0
    else
        fails=$((fails + 1))
        echo "$(date -u +%Y-%m-%dT%H:%M:%SZ) FEJL ($fails/5): $out" >>"$LOG"
        if [ "$fails" -ge 5 ]; then
            echo "$(date -u +%Y-%m-%dT%H:%M:%SZ) giver op efter 5 fejl — adgangen kræver en ny godkendelse (tools/mcp_oauth.py --start)" >>"$LOG"
            exit 1
        fi
        # Kortere ventetid ved fejl, så et netværksudfald ikke koster en hel time.
        sleep 300
        continue
    fi
    sleep "$INTERVAL"
done
