#!/usr/bin/env python3
"""Backup- og gendannelsesøvelse for steamgears.eu's database (S5.8).

Kører på serveren gennem Novamira-MCP: tager en logisk dump med ``mysqldump``,
gendanner den i **samme database under et scratch-prefix** (der findes ingen
rettighed til at oprette en ekstra database — målt 9/11: ``GRANT ALL`` gælder
kun ``steamgears_eu_db``), sammenligner rækkeantal for **hver** tabel i det
levende prefix, og rydder op igen.

Tre ting gjorde den naive version forkert, og de er alle håndteret her:

1. **Et gammelt WordPress-prefix ligger stadig i databasen** (målt 9/11:
   ``clk4219fc96b5_`` med 12 tabeller, 136 rækker i alt). En dump indeholder
   dem, så en gendannelse under et nyt prefix kolliderer med dem — derfor
   udelades de på dump-siden (``--ignore-table``) og røres aldrig.
2. **Visninger tælles som tabeller** i ``information_schema``. De skal droppes
   med ``DROP VIEW``; ``DROP TABLE`` fejler på dem, og så står de tilbage.
3. **Et prefiks-bytte ændrer også data** (fx ``{prefix}user_roles`` i options),
   så en gendannelse er et bevis på at dumpet kan afspilles — ikke en garanti
   for at siden ville virke urørt. Det står i rapporten.

Brug::

    python3 tools/wp_db_drill.py                     # kør mod live
    python3 tools/wp_db_drill.py --json              # kun JSON til stdout
    python3 tools/wp_db_drill.py --keep              # lad dumpet ligge på serveren

Kræver ``novamira``-CLI i PATH og et gyldigt credential (se docs/mcp-auth.md).
"""

from __future__ import annotations

import argparse
import base64
import json
import pathlib
import shutil
import subprocess
import sys
import time

ROOT = pathlib.Path(__file__).resolve().parent.parent
SITE = "steamgears.eu"
SCRATCH = "sgtest_"

# Shell-scriptet der kører på serveren. Prefixer og database læses fra PHP-siden,
# så intet credential eller databasenavn står i dette script.
DRILL = r"""
#!/bin/bash
set -u
DIR="$(cd "$(dirname "$0")" && pwd)"
DB="$(cat "$DIR/dbname")"
PREFIX="$(cat "$DIR/prefix")"
LEGACY="$(cat "$DIR/legacy")"
CNF="$DIR/my.cnf"
SCRATCH="__SCRATCH__"
STATUS="$DIR/status.json"
stage() { printf '{"stage":"%s","t":%s}\n' "$1" "$(date +%s)" >> "$DIR/stages.jsonl"; }

# 1) Dump — uden de forældede tabeller, så en gendannelse ikke kan røre dem.
IGNORE=""
if [ -n "$LEGACY" ]; then
  for t in $LEGACY; do IGNORE="$IGNORE --ignore-table=$DB.$t"; done
fi
t0=$(date +%s); stage start
mysqldump --defaults-extra-file="$CNF" --single-transaction --quick --skip-lock-tables \
  --default-character-set=utf8mb4 --no-tablespaces --triggers $IGNORE "$DB" \
  2>"$DIR/dump.err" | gzip -9 > "$DIR/db.sql.gz"
DUMP_RC=${PIPESTATUS[0]}
t1=$(date +%s); stage dumped

BYTES=$(stat -c %s "$DIR/db.sql.gz")
SHA=$(sha256sum "$DIR/db.sql.gz" | cut -d' ' -f1)

# 2) Gendannelse under scratch-prefix (samme database — der er ingen ret til en ny).
gunzip -c "$DIR/db.sql.gz" | sed "s/${PREFIX}/${SCRATCH}/g" | mysql --defaults-extra-file="$CNF" "$DB" 2>"$DIR/restore.err"
REST_RC=${PIPESTATUS[2]}
t2=$(date +%s); stage restored

# 3) Rækkeparitet for HVER tabel i det levende prefix: én UNION ALL-spørgsel pr. side.
cat > "$DIR/union_orig.sql" <<SQL
SET SESSION group_concat_max_len=16777216;
SELECT GROUP_CONCAT(CONCAT("SELECT '", REPLACE(table_name,'${PREFIX}',''), "' k, COUNT(*) c FROM ", table_name) SEPARATOR ' UNION ALL ')
FROM information_schema.tables
WHERE table_schema=DATABASE() AND table_name LIKE '${PREFIX}%' AND table_type='BASE TABLE';
SQL
cat > "$DIR/union_scr.sql" <<SQL
SET SESSION group_concat_max_len=16777216;
SELECT GROUP_CONCAT(CONCAT("SELECT '", REPLACE(table_name,'${SCRATCH}',''), "' k, COUNT(*) c FROM ", table_name) SEPARATOR ' UNION ALL ')
FROM information_schema.tables
WHERE table_schema=DATABASE() AND table_name LIKE '${SCRATCH}%' AND table_type='BASE TABLE';
SQL
mysql --defaults-extra-file="$CNF" -N -B "$DB" < "$DIR/union_orig.sql" 2>>"$DIR/sql.err" | tail -1 > "$DIR/union_orig.out"
mysql --defaults-extra-file="$CNF" -N -B "$DB" < "$DIR/union_scr.sql" 2>>"$DIR/sql.err" | tail -1 > "$DIR/union_scr.out"
if [ -s "$DIR/union_orig.out" ]; then
  echo "$(cat "$DIR/union_orig.out")" > "$DIR/run_orig.sql"
  mysql --defaults-extra-file="$CNF" -N -B "$DB" < "$DIR/run_orig.sql" 2>>"$DIR/sql.err" | sort > "$DIR/orig.counts"
fi
if [ -s "$DIR/union_scr.out" ]; then
  echo "$(cat "$DIR/union_scr.out")" > "$DIR/run_scr.sql"
  mysql --defaults-extra-file="$CNF" -N -B "$DB" < "$DIR/run_scr.sql" 2>>"$DIR/sql.err" | sort > "$DIR/scratch.counts"
fi
TABLES_ORIG=$(wc -l < "$DIR/orig.counts" 2>/dev/null || echo 0)
TABLES_SCR=$(wc -l < "$DIR/scratch.counts" 2>/dev/null || echo 0)
MISMATCH=$(join -t "$(printf '\t')" "$DIR/orig.counts" "$DIR/scratch.counts" 2>/dev/null | awk -F'\t' '$2!=$3{n++} END{print n+0}')
MISSING=$(comm -23 <(cut -f1 "$DIR/orig.counts") <(cut -f1 "$DIR/scratch.counts") 2>/dev/null | wc -l)
t3=$(date +%s); stage counted

# 4) Oprydning: tabeller først, derefter visninger (DROP TABLE fejler på en visning).
mysql --defaults-extra-file="$CNF" -N -B "$DB" -e "SELECT CONCAT('DROP TABLE IF EXISTS \`', table_name, '\`;') FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name LIKE '${SCRATCH}%' AND table_type='BASE TABLE'" > "$DIR/drop.sql" 2>>"$DIR/sql.err"
[ -s "$DIR/drop.sql" ] && mysql --defaults-extra-file="$CNF" "$DB" < "$DIR/drop.sql" 2>"$DIR/drop.err"
DROPPED=$(grep -c 'DROP TABLE' "$DIR/drop.sql" 2>/dev/null || echo 0)
mysql --defaults-extra-file="$CNF" -N -B "$DB" -e "SELECT CONCAT('DROP VIEW IF EXISTS \`', table_name, '\`;') FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name LIKE '${SCRATCH}%' AND table_type='VIEW'" > "$DIR/dropview.sql" 2>>"$DIR/sql.err"
[ -s "$DIR/dropview.sql" ] && mysql --defaults-extra-file="$CNF" "$DB" < "$DIR/dropview.sql" 2>>"$DIR/drop.err"
VIEWS_DROPPED=$(grep -c 'DROP VIEW' "$DIR/dropview.sql" 2>/dev/null || echo 0)
LEFT=$(mysql --defaults-extra-file="$CNF" -N -B "$DB" -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name LIKE '${SCRATCH}%'" 2>>"$DIR/sql.err")
t4=$(date +%s); stage dropped

printf '{"dump_rc":%s,"restore_rc":%s,"bytes":%s,"sha256":"%s","dump_s":%s,"restore_s":%s,"parity_s":%s,"cleanup_s":%s,"tables_expected":%s,"tables_restored":%s,"mismatch":%s,"missing":%s,"dropped":%s,"views_dropped":%s,"left":%s}\n' \
  "$DUMP_RC" "$REST_RC" "$BYTES" "$SHA" "$((t1-t0))" "$((t2-t1))" "$((t3-t2))" "$((t4-t3))" \
  "$TABLES_ORIG" "$TABLES_SCR" "${MISMATCH:-0}" "${MISSING:-0}" "$DROPPED" "$VIEWS_DROPPED" "${LEFT:-0}" > "$STATUS"
stage done
"""


def novamira_bin() -> str:
    exe = shutil.which("novamira")
    if not exe:
        sys.exit("novamira-CLI findes ikke i PATH (se docs/mcp-auth.md)")
    return exe


def mcp(code: str, timeout: int = 180) -> dict:
    payload = ROOT / ".tmp"
    payload.mkdir(exist_ok=True)
    path = payload / "db-drill-call.json"
    path.write_text(json.dumps({"code": code}), encoding="utf-8")
    cmd = [novamira_bin(), "--site", SITE, "--json", "--yes",
           "run", "novamira/execute-php", "--input", f"@{path}"]
    out = subprocess.run(cmd, capture_output=True, text=True, timeout=timeout)
    raw = out.stdout.strip()
    start = raw.find("{")
    if start < 0:
        sys.exit(f"MCP svarede ikke med JSON: {out.stderr[-400:]}")
    data = json.loads(raw[start:])
    if not data.get("ok"):
        sys.exit(f"MCP-fejl: {json.dumps(data.get('error'), ensure_ascii=False)}")
    return data["data"]["return_value"]


def start() -> dict:
    php = (
        '$dir = sys_get_temp_dir()."/sgbk-".date("YmdHis");'
        '@mkdir($dir, 0700, true);'
        'file_put_contents($dir."/my.cnf", "[client]\\nuser=".DB_USER."\\npassword=".DB_PASSWORD."\\nhost=".DB_HOST."\\n");'
        'chmod($dir."/my.cnf", 0600);'
        'file_put_contents($dir."/dbname", DB_NAME);'
        'file_put_contents($dir."/prefix", $GLOBALS["wpdb"]->prefix);'
        # Forældede tabeller = alt der ikke bærer det levende WordPress-prefix.
        '$legacy=[]; foreach($GLOBALS["wpdb"]->get_col("SHOW TABLES") as $t){'
        '  if(strpos($t, $GLOBALS["wpdb"]->prefix)!==0) { $legacy[]=$t; } }'
        'file_put_contents($dir."/legacy", implode(" ", $legacy));'
        'file_put_contents($dir."/drill.sh", base64_decode(' + repr(base64.b64encode(DRILL.replace("__SCRATCH__", SCRATCH).encode()).decode()) + '));'
        'chmod($dir."/drill.sh", 0700);'
        '$pid = trim((string)shell_exec("nohup bash ".escapeshellarg($dir."/drill.sh")." > ".escapeshellarg($dir."/nohup.log")." 2>&1 & echo $!"));'
        'return ["dir"=>$dir,"pid"=>$pid,"prefix"=>$GLOBALS["wpdb"]->prefix,"legacy"=>count($legacy)];'
    )
    return mcp(php)


def poll(directory: str, pid: str, timeout: int = 240) -> dict:
    deadline = time.time() + timeout
    php = (
        '$d=' + json.dumps(directory) + ';'
        '$o=["done"=>file_exists($d."/status.json")];'
        'foreach(["status.json","stages.jsonl","dump.err","restore.err","sql.err","nohup.log"] as $f){'
        ' $p=$d."/".$f; $o[$f]=file_exists($p)?substr((string)file_get_contents($p),0,1200):null;}'
        'exec(' + json.dumps(f"ps -p {int(pid)} -o pid= 2>/dev/null") + ',$ps); $o["running"]=count($ps)>0;'
        'return $o;'
    )
    while time.time() < deadline:
        state = mcp(php)
        if state.get("done"):
            state["status"] = json.loads(state["status.json"].strip().splitlines()[-1])
            return state
        if not state.get("running") and not state.get("done"):
            state["error"] = "processen er stoppet uden status.json"
            return state
        time.sleep(4)
    return {"error": "timeout", "directory": directory}


HISTORY = ROOT / "reports" / "db-drill-history.json"


def append_history(report: dict, path: pathlib.Path | None = None, keep: int = 120) -> None:
    """Én række pr. kørsel, så RTO og paritet bliver en serie — også to kørsler samme dag.

    Dagsfilen ``reports/db-drill-<dato>.json`` overskrives ved en ny kørsel samme dag;
    historikken gør det ikke, og ``tools/db_drill_check.py`` læser den først.
    """
    path = path or HISTORY
    rows: list = []
    if path.exists():
        try:
            loaded = json.loads(path.read_text(encoding="utf-8"))
            rows = loaded if isinstance(loaded, list) else []
        except ValueError:
            rows = []  # en korrupt historik må ikke koste dagens måling
    rows.append({key: report.get(key) for key in (
        "dato", "database", "legacy_tables_excluded", "dump_bytes", "restore_seconds", "rto_seconds",
        "tables_expected", "row_mismatches", "missing_tables", "left_behind", "ok")})
    rows[-1]["tidspunkt"] = time.strftime("%H:%M")
    path.parent.mkdir(parents=True, exist_ok=True)
    path.write_text(json.dumps(rows[-keep:], ensure_ascii=False, indent=1) + "\n", encoding="utf-8")


def main() -> int:
    ap = argparse.ArgumentParser(description="Backup- og gendannelsesøvelse (S5.8)")
    ap.add_argument("--json", action="store_true", help="kun JSON til stdout")
    ap.add_argument("--keep", action="store_true", help="lad dumpet ligge på serveren")
    ap.add_argument("--out", default=f"reports/db-drill-{time.strftime('%Y-%m-%d')}.json")
    args = ap.parse_args()

    started = start()
    if not args.json:
        print(f"kører i {started['dir']} (pid {started['pid']}, prefix {started['prefix']}, "
              f"{started['legacy']} forældede tabeller udeladt)")
    state = poll(started["dir"], started["pid"])
    if "error" in state:
        print(json.dumps(state, ensure_ascii=False, indent=1))
        return 1

    st = state["status"]
    report = {
        "dato": time.strftime("%Y-%m-%d"),
        "database": "steamgears_eu_db",
        "prefix": started["prefix"],
        "legacy_tables_excluded": started["legacy"],
        "dump_bytes": st["bytes"],
        "dump_sha256": st["sha256"],
        "dump_seconds": st["dump_s"],
        "restore_seconds": st["restore_s"],
        "parity_seconds": st["parity_s"],
        "cleanup_seconds": st["cleanup_s"],
        "rpo_hours": 0,
        "rto_seconds": st["restore_s"],
        "tables_expected": st["tables_expected"],
        "tables_restored": st["tables_restored"],
        "row_mismatches": st["mismatch"],
        "missing_tables": st["missing"],
        "dropped_tables": st["dropped"],
        "dropped_views": st["views_dropped"],
        "left_behind": st["left"],
        "errors": {k: v for k, v in state.items()
                   if k.endswith(".err") and v},
        "ok": (st["dump_rc"] == 0 and st["restore_rc"] == 0
               and st["tables_expected"] > 0
               and st["mismatch"] == 0 and st["missing"] == 0 and st["left"] == 0),
    }
    if not args.keep:
        mcp('$d=' + json.dumps(started["dir"]) + '; exec("rm -rf ".escapeshellarg($d));'
            ' return ["removed"=>!is_dir($d)];')

    out_path = ROOT / args.out
    out_path.parent.mkdir(parents=True, exist_ok=True)
    out_path.write_text(json.dumps(report, ensure_ascii=False, indent=1) + "\n", encoding="utf-8")
    append_history(report)

    if args.json:
        print(json.dumps(report, ensure_ascii=False, indent=1))
    else:
        print(f"dump: {report['dump_bytes']} bytes på {report['dump_seconds']} s "
              f"(sha256 {report['dump_sha256'][:12]}…)")
        print(f"gendannelse: {report['restore_seconds']} s · paritet: "
              f"{report['tables_expected']} tabeller, {report['row_mismatches']} afvigelser, "
              f"{report['missing_tables']} mangler")
        print(f"oprydning: {report['dropped_tables']} tabeller + {report['dropped_views']} visninger, "
              f"{report['left_behind']} tilbage")
        print(f"{'OK' if report['ok'] else 'FEJL'} — rapport: {args.out}")
    return 0 if report["ok"] else 1


if __name__ == "__main__":
    raise SystemExit(main())
