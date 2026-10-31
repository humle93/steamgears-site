import os
import subprocess
import py_compile
import glob
from datetime import datetime

def run_git_update() -> dict:
    result = {"success": True, "message": ""}
    try:
        if os.path.exists(".git"):
            res = subprocess.run(["git", "pull", "origin", "main"], capture_output=True, text=True, timeout=30)
            if res.returncode == 0:
                result["message"] = "Git pull gennemført succesfuldt."
            else:
                result["success"] = False
                result["message"] = f"Git pull fejl: {res.stderr.strip()}"
        else:
            result["message"] = "Ikke et git-repository."
    except Exception as e:
        result["success"] = False
        result["message"] = f"Fejl: {str(e)}"
    return result

def audit_codebase() -> dict:
    report = {
        "timestamp": datetime.now().strftime("%Y-%m-%d %H:%M:%S"),
        "files_checked": 0,
        "errors_found": [],
        "status": "PASS"
    }
    for filepath in glob.glob("**/*.py", recursive=True):
        if "venv" in filepath or "backups" in filepath:
            continue
        report["files_checked"] += 1
        try:
            py_compile.compile(filepath, doraise=True)
        except Exception as e:
            report["errors_found"].append({"file": filepath, "error": str(e)})
            report["status"] = "FAIL"
    return report

if __name__ == "__main__":
    run_git_update()
    audit_codebase()
