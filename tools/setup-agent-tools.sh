#!/usr/bin/env bash
# setup-agent-tools.sh — genskaber de værktøjer en agent skal bruge i denne sandkasse.
#
#   bash tools/setup-agent-tools.sh
#
# Alt lægges i tools/_deps/ (gitignored), så der kræves ingen root og intet
# systeminstalleres. Kør scriptet efter en arkivering/genoprettelse af sandkassen,
# hvor tools/_deps er væk.
#
# Hvad der installeres:
#   1. fpdf2 + pypdf + pypdfium2  — PDF-rapporterne i reports/ og læsning af dem
#   2. PHP 8.5 (statisk binær)     — `php -l` på pluginnet, samme minor som produktion
#
# Bevidst IKKE installeret: WordPress/PHPUnit. Pluginnets smoke- og integrationstests
# kræver en kørende WordPress-installation, og dem kører CI (.github/workflows/plugin-ci.yml).
set -euo pipefail

cd "$(dirname "$0")/.."
ROOT="$PWD"
DEPS="$ROOT/tools/_deps"
mkdir -p "$DEPS/bin"

echo "== Python-biblioteker =="
python3 -m pip install --quiet --target "$DEPS" fpdf2 pypdf pypdfium2
PYTHONPATH="$DEPS" python3 - <<'PY'
import fpdf, pypdf, pypdfium2
print(f"  fpdf2 {fpdf.__version__} · pypdf {pypdf.__version__} · pypdfium2 ok")
PY

echo "== PHP (statisk binær, ingen root) =="
if [ -x "$DEPS/bin/php" ]; then
  echo "  findes allerede: $("$DEPS/bin/php" -v | head -1)"
else
  BASE="https://dl.static-php.dev/static-php-cli/bulk"
  # Én linje med vilje: en "-c"-streng må ikke ombrydes med indrykning, det giver IndentationError.
  ASSET=$(curl -sL "$BASE/?format=json" \
    | python3 -c 'import json,sys; names=[e["name"] for e in json.load(sys.stdin) if e["name"].startswith("php-") and e["name"].endswith("-cli-linux-x86_64.tar.gz")]; print(sorted(names, key=lambda n: [int(p) for p in n.split("-")[1].split(".")])[-1])')
  [ -n "$ASSET" ] || { echo "  kunne ikke finde en PHP-binær"; exit 1; }
  echo "  henter $ASSET"
  curl -sL "$BASE/$ASSET" -o /tmp/php-static.tgz
  tar -xzf /tmp/php-static.tgz -C "$DEPS/bin"
  chmod +x "$DEPS/bin/php"
  echo "  installeret: $("$DEPS/bin/php" -v | head -1)"
fi

echo "== Commit-msg-værn =="
# Kræver Modul / % før / % efter / Δ / Grundlag i commit-beskeden (AGENTS.md §1).
# Hooken ligger i .git/hooks/ og følger ikke med repoet, så den installeres her.
python3 tools/commit_check.py --install

# S5.9: samme sted ligger promote-gaten. Den afviser et push hvor version, zip,
# tag eller værn ikke holder — Free-planen kan ikke beskytte main på et privat repo.
python3 tools/release.py --install-hook

echo
echo "== Klar =="
echo "  PDF:  PYTHONPATH=tools/_deps python3 tools/project_reports.py"
echo "  PHP:  tools/_deps/bin/php -l plugins/steamgears-autopilot/steamgears-autopilot.php"
echo
echo "  Status-værnet kræver intet: python3 projektstatus.py --check"
echo "  Commit-værnet kræver intet: python3 tools/commit_check.py --selftest"
