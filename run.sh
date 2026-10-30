#!/usr/bin/env bash
set -euo pipefail

APP_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
VENV_DIR="$APP_DIR/venv"
PORT="${CC_PORT:-8000}"

echo "=== Starter Command Center ==="

# Tjek .env-format uden at udskrive hemmeligheder
if [ -f "$APP_DIR/.env" ]; then
  while IFS= read -r line || [ -n "$line" ]; do
    [[ "$line" =~ ^[[:space:]]*$ ]] && continue
    [[ "$line" =~ ^# ]] && continue
    if ! [[ "$line" =~ ^[A-Za-z_][A-Za-z0-9_]*=.*$ ]]; then
      echo "FEJL: Ugyldig linje i .env:"
      echo "$line" | sed -E 's/=.*/=<skjult>/'
      echo "Ret linjen til formatet: NAVN="værdi""
      exit 1
    fi
  done < "$APP_DIR/.env"
  set -a
  source "$APP_DIR/.env"
  set +a
fi

# Opret venv hvis nødvendigt
if [ ! -d "$VENV_DIR" ]; then
  python3 -m venv "$VENV_DIR"
fi

# shellcheck disable=SC1091
source "$VENV_DIR/bin/activate"
python -m pip install --upgrade pip >/dev/null
pip install -r "$APP_DIR/requirements.txt" >/dev/null

# Tjek om port 8000 allerede er optaget
if ss -ltn 2>/dev/null | grep -q ":$PORT "; then
  echo "FEJL: Port $PORT er allerede i brug."
  echo "Find processen med: ss -ltnp | grep :$PORT"
  echo "Stop den eksisterende UVicorn med: pkill -f 'uvicorn'"
  exit 1
fi

echo "=== Starter UVicorn på port $PORT ==="
cd "$APP_DIR/app"
exec uvicorn main:app --host 0.0.0.0 --port "$PORT"
