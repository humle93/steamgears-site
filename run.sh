#!/bin/bash
cd "$(dirname "$0")"
if [ ! -d "venv" ]; then
    python3 -m venv venv
fi
source venv/bin/activate
pip install --upgrade pip > /dev/null 2>&1
pip install -r requirements.txt
echo "⚡ Starter Command Center v8.0..."
uvicorn app.main:app --host 127.0.0.1 --port 8000 --reload
