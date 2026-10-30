from fastapi import FastAPI, status, Response
from app.jobs import init_db_and_jobs
from app.backup import run_hot_backup
from app.wp_bridge import WordPressBridge
import os

app = FastAPI(title="Command Center v8.0", version="8.0")
wp_bridge = WordPressBridge()

@app.on_event("startup")
def startup_event():
    init_db_and_jobs()
    run_hot_backup()

@app.get("/health")
def health_check(response: Response):
    db_ok = os.path.exists("command_center.db")
    wp_ok = wp_bridge.check_health()
    
    status_str = "healthy" if (db_ok and wp_ok) else "degraded"
    if not db_ok:
        response.status_code = status.HTTP_503_SERVICE_UNAVAILABLE
        
    return {
        "status": status_str,
        "database": "ok" if db_ok else "missing",
        "wordpress_bridge": "ok" if wp_ok else "offline"
    }

@app.get("/")
def root():
    return {"message": "⚡ Command Center v8.0 kører stabilt i Termux"}
