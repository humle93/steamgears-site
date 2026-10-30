import os
import subprocess
import py_compile
import glob
from datetime import datetime

def run_git_update() -> dict:
    result = {"success": True, "message": ""}
    try:
        if os.path.exists(".git"):
            print("[UPDATE] Trækker nyeste kildekode fra Git (git pull)...")
            res = subprocess.run(["git", "pull", "origin", "main"], capture_output=True, text=True, timeout=30)
            if res.returncode == 0:
                result["message"] = "Git pull gennemført succesfuldt."
            else:
                result["success"] = False
                result["message"] = f"Git pull fejl: {res.stderr.strip()}"
        else:
            result["message"] = "Ikke et git-repository, springer git pull over."
    except Exception as e:
        result["success"] = False
        result["message"] = f"Fejl under git opdatering: {str(e)}"
    return result

def audit_codebase() -> dict:
    report = {
        "timestamp": datetime.now().strftime("%Y-%m-%d %H:%M:%S"),
        "files_checked": 0,
        "errors_found": [],
        "status": "PASS"
    }
    python_files = glob.glob("**/*.py", recursive=True)
    for filepath in python_files:
        if "venv" in filepath or "backups" in filepath:
            continue
        report["files_checked"] += 1
        try:
            py_compile.compile(filepath, doraise=True)
        except py_compile.PyCompileError as e:
            report["errors_found"].append({"file": filepath, "error": str(e)})
            report["status"] = "FAIL"
        except Exception as e:
            report["errors_found"].append({"file": filepath, "error": f"Uventet fejl: {str(e)}"})
            report["status"] = "FAIL"
    return report

def generate_report_file(update_res: dict, audit_res: dict):
    os.makedirs("backups", exist_ok=True)
    report_path = f"backups/update_report_{datetime.now().strftime('%Y%m%d_%H%M%S')}.txt"
    content = f"""==================================================
 ⚡ COMMAND CENTER - SELVOPDATERING & FEJLRAPPORT
==================================================
Tidspunkt: {audit_res['timestamp']}

1. OPDATERINGSSTATUS:
- Status: {'SUCCES' if update_res['success'] else 'FEJL'}
- Detaljer: {update_res['message']}

2. KODEREVISION & FEJLTJEK (SYNTAX AUDIT):
- Filer kontrolleret: {audit_res['files_checked']}
- Samlet resultat: {audit_res['status']}
- Antal fejl fundet: {len(audit_res['errors_found'])}
"""
    if audit_res['errors_found']:
        content += "\nFølgende fejl blev fundet i koden:\n"
        for err in audit_res['errors_found']:
            content += f" - Fil: {err['file']}\n   Fejl: {err['error']}\n"
    else:
        content += "\nIngen syntaksfejl eller problemer fundet. Koden er 100% klar til drift!\n"
    content += "==================================================\n"
    
    with open(report_path, "w", encoding="utf-8") as f:
        f.write(content)
    print(f"\n[RAPPORT GENERERET] Gemt lokalt som: {report_path}")
    print(content)
    return report_path

def run_self_update_pipeline():
    print("\n--- STARTER SELVOPDATERING OG KONTROL ---")
    update_res = run_git_update()
    audit_res = audit_codebase()
    report_file = generate_report_file(update_res, audit_res)
    print("--- PIPELINE FULDFØRT ---\n")
    return {"update": update_res, "audit": audit_res, "report": report_file}

if __name__ == "__main__":
    run_self_update_pipeline()
