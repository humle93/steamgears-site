#!/bin/bash
cd "$(dirname "$0")"
if [ ! -d "venv" ]; then
    python3 -m venv venv
fi
source venv/bin/activate
python3 app/self_update.py
echo "⚡ Starter Command Center..."
uvicorn app.main:app --host 127.0.0.1 --port 8000 --reload
