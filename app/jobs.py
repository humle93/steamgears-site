import sqlite3
import os

DB_PATH = "command_center.db"

def init_db_and_jobs():
    os.makedirs(os.path.dirname(DB_PATH) if os.path.dirname(DB_PATH) else '.', exist_ok=True)
    conn = sqlite3.connect(DB_PATH)
    cursor = conn.cursor()
    cursor.execute("PRAGMA journal_mode=WAL;")
    cursor.execute("PRAGMA synchronous=NORMAL;")
    cursor.execute("PRAGMA busy_timeout=5000;")
    cursor.execute("""
        CREATE TABLE IF NOT EXISTS job_queue (
            job_id TEXT PRIMARY KEY,
            type TEXT NOT NULL,
            payload TEXT,
            status TEXT NOT NULL,
            attempts INTEGER DEFAULT 0,
            next_run_at TIMESTAMP,
            last_error TEXT,
            created_at TIMESTAMP,
            started_at TIMESTAMP,
            finished_at TIMESTAMP
        )
    """)
    cursor.execute("UPDATE job_queue SET status = 'queued' WHERE status = 'running'")
    conn.commit()
    conn.close()
