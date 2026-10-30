#!/bin/bash
cd "$(dirname "$0")"

if [ ! -d "venv" ]; then
    python3 -m venv venv
fi

source venv/bin/activate
pip install --upgrade pip > /dev/null 2>&1
pip install -r requirements.txt

# Kør self-update pipeline (git pull, syntax audit & rapport)
python3 app/self_update.py

echo ""
echo "=================================================="
echo " 🌐 DASHBOARD KLAR: http://127.0.0.1:8000"
echo " 👤 STANDARD BRUGER: humle91 (Adgangskode: buster)"
echo "=================================================="
echo ""

# Start uvicorn server
uvicorn app.main:app --host 127.0.0.1 --port 8000 --reload
