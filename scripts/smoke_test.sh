#!/bin/bash
# Command Center smoke-test (v8.4) - kører efter install eller self-update.
# Brug: bash scripts/smoke_test.sh

PORT="${CC_PORT:-8000}"
ok=1

echo "🔍 Kører smoke-test af Command Center v8.4..."

for p in /health /login; do
    code=$(curl -s -o /dev/null --max-time 5 -w "%{http_code}" "http://127.0.0.1:$PORT$p" 2>/dev/null) || code=000
    case "$code" in
        200) echo "  ✅ OK $p -> 200" ;;
        *) echo "  ❌ FAIL $p -> $code"; ok=0 ;;
    esac
done

if [ "$ok" = "1" ]; then
    echo "✨ Smoke-test fuldført med succes! Systemet er klar."
else
    echo "⚠️ Smoke-test fejlede - tjek evt. logfiler eller kør 'python3 app/self_update.py'"
fi

exit $ok
