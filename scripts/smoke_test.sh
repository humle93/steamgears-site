#!/bin/bash
PORT="${CC_PORT:-8000}"
ok=1
echo "🔍 Kører test-SFX (Smoke-test) for Command Center v8.4..."
for p in /health /login; do
    code=$(curl -s -o /dev/null --max-time 5 -w "%{http_code}" "http://127.0.0.1:$PORT$p" 2>/dev/null) || code=000
    if [ "$code" = "200" ]; then
        echo "  ✅ OK $p -> 200"
    else
        echo "  ❌ FAIL $p -> $code"
        ok=0
    fi
done
exit $ok
