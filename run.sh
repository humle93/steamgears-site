#!/bin/bash
cd "$(dirname "$0")"
if [ ! -d "venv" ]; then python3 -m venv venv; fi
source venv/bin/activate
pip install -r requirements.txt > /dev/null 2>&1
python3 app/self_update.py

echo "🚀 Starter server midlertidigt for test-SFX..."
uvicorn app.main:app --host 127.0.0.1 --port 8000 &
SERVER_PID=$!
sleep 2
bash scripts/smoke_test.sh
kill $SERVER_PID 2>/dev/null
sleep 1

echo ""
echo "=================================================="
echo " 🌐 DASHBOARD KLAR: http://127.0.0.1:8000"
echo " 👤 STANDARD BRUGER: humle91 (Adgangskode: buster)"
echo "=================================================="
echo ""
uvicorn app.main:app --host 127.0.0.1 --port 8000 --reload
