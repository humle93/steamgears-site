import sqlite3
import os
from datetime import datetime

def run_hot_backup():
    os.makedirs("backups", exist_ok=True)
    backup_filename = f"backups/cc_backup_{datetime.now().strftime('%Y%m%d_%H%M%S')}.db"
    try:
        src = sqlite3.connect("command_center.db")
        dst = sqlite3.connect(backup_filename)
        src.backup(dst)
        dst.close()
        src.close()
        if os.path.exists(backup_filename) and os.path.getsize(backup_filename) > 0:
            return True
    except Exception as e:
        print(f"[BACKUP FEJL]: {e}")
    return False
