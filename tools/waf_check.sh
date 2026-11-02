#!/usr/bin/env bash
# waf_check.sh — viser hvad Simplys WAF svarer pr. user-agent mod steamgears.eu.
#
# Brug:   bash tools/waf_check.sh [URL]
# Exit:   0 = neutral klient får 200 (sitet er tilgængeligt)
#         1 = neutral klient blokeres (454/455/429 — undersøg, se docs/mcp-auth.md)
#         2 = serverfejl (5xx) — ikke WAF
#
# Vigtigt: 455 for Googlebot/bingbot-UA fra denne maskine er FORVENTET og er ikke en fejl.
# Simply hvidlister verificerede tjenester på IP, og denne IP tilhører ikke Google.
# Se docs/library/waf-diagnose-2026-11-02.md.

set -uo pipefail

URL="${1:-https://steamgears.eu/}"
PACE="${PACE:-1.5}"

# Format: navn|user-agent (tom UA = curl's egen)
UAS=(
  "neutral|"
  "chrome|Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0 Safari/537.36"
  "googlebot|Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)"
  "bingbot|Mozilla/5.0 (compatible; bingbot/2.1; +http://www.bing.com/bingbot.htm)"
  "applebot|Mozilla/5.0 (compatible; Applebot/0.1; +http://www.apple.com/go/applebot)"
  "gptbot|Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko); compatible; GPTBot/1.2; +https://openai.com/gptbot"
)

printf '%-10s %-6s %s\n' "UA" "STATUS" "BETYDNING"
printf '%-10s %-6s %s\n' "--" "------" "---------"

neutral_status=""
for entry in "${UAS[@]}"; do
  name="${entry%%|*}"
  ua="${entry#*|}"
  if [ -n "$ua" ]; then
    status=$(curl -s -o /dev/null -w '%{http_code}' --max-time 20 -A "$ua" "$URL")
  else
    status=$(curl -s -o /dev/null -w '%{http_code}' --max-time 20 "$URL")
  fi

  case "$status" in
    200) note="igennem" ;;
    454) note="JS-challenge (browser-fingeraftryk mangler)" ;;
    455) note="WAF-regel — forventet for spoofet bot-UA" ;;
    429) note="rate limit — vent og prøv igen" ;;
    5*)  note="serverfejl — ikke WAF" ;;
    *)   note="uventet" ;;
  esac

  printf '%-10s %-6s %s\n' "$name" "$status" "$note"
  [ "$name" = "neutral" ] && neutral_status="$status"
  sleep "$PACE"
done

echo
case "$neutral_status" in
  200) echo "OK: neutral klient får 200 — sitet er tilgængeligt for crawl og verifikation."; exit 0 ;;
  5*)  echo "FEJL: $URL svarer $neutral_status — serverfejl, ikke WAF. Tjek WordPress/host."; exit 2 ;;
  *)   echo "BLOKERET: neutral klient får $neutral_status. Se docs/mcp-auth.md → WAF-afsnittet."; exit 1 ;;
esac
