from fastapi import FastAPI, Request, Form, Depends, status, Response
from fastapi.responses import HTMLResponse, RedirectResponse
from fastapi.templating import Jinja2Templates
from app.jobs import init_db_and_jobs
from app.backup import run_hot_backup
from app.wp_bridge import WordPressBridge
import os
import sqlite3
from passlib.context import CryptContext

VERSION = "v8.3"
APP_TITLE = f"Command Center {VERSION} - Standard Bruger & Autopilot"

app = FastAPI(title=APP_TITLE, version="8.3")
templates = Jinja2Templates(directory="app/templates")
wp_bridge = WordPressBridge()
pwd_context = CryptContext(schemes=["bcrypt"], deprecated="auto")

DB_PATH = "command_center.db"

def get_db():
    conn = sqlite3.connect(DB_PATH)
    conn.row_factory = sqlite3.Row
    return conn

@app.on_event("startup")
def startup_event():
    print("\n" + "="*50)
    print(f" ⚡ STARTER {APP_TITLE.upper()}")
    print("="*50)
    init_db_and_jobs()
    print("[OK] Trådsikker SQLite WAL-jobkø initialiseret.")
    run_hot_backup()
    print("[OK] Off-site hot-backup valideret.")
    
    conn = get_db()
    cursor = conn.cursor()
    cursor.execute("""
        CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT UNIQUE NOT NULL,
            hashed_password TEXT NOT NULL
        )
    """)
    cursor.execute("""
        CREATE TABLE IF NOT EXISTS socialqueue (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            platform TEXT NOT NULL,
            title TEXT NOT NULL,
            message TEXT NOT NULL,
            media_url TEXT,
            board_or_page_id TEXT,
            status TEXT DEFAULT 'Klar i kø'
        )
    """)
    cursor.execute("SELECT COUNT(*) FROM users WHERE username = 'humle91'")
    if cursor.fetchone()[0] == 0:
        hashed_pw = pwd_context.hash("buster")
        cursor.execute("INSERT INTO users (username, hashed_password) VALUES (?, ?)", ("humle91", hashed_pw))
        conn.commit()
        print("[INFO] Bruger 'humle91' (cpaleadsis@gmail.com) oprettet i databasen.")
    conn.close()
    
    print(f"\n 🌐 DASHBOARD URL: http://127.0.0.1:8000")
    print(f" [{VERSION}] Systemet er fuldt operationelt!\n" + "="*50 + "\n")

@app.get("/login", response_class=HTMLResponse)
def login_page(request: Request):
    return templates.TemplateResponse("login.html", {"request": request, "error": None})

@app.post("/login")
def login_action(response: Response, username: str = Form(...), password: str = Form(...)):
    conn = get_db()
    cursor = conn.cursor()
    cursor.execute("SELECT hashed_password FROM users WHERE username = ?", (username,))
    user = cursor.fetchone()
    conn.close()
    if user and pwd_context.verify(password, user["hashed_password"]):
        resp = RedirectResponse(url="/", status_code=status.HTTP_303_SEE_OTHER)
        resp.set_cookie(key="session_user", value=username)
        return resp
    return templates.TemplateResponse("login.html", {"request": request, "error": "Forkert brugernavn eller adgangskode"})

@app.get("/register", response_class=HTMLResponse)
def register_page(request: Request):
    return templates.TemplateResponse("register.html", {"request": request, "error": None})

@app.post("/register")
def register_action(request: Request, username: str = Form(...), password: str = Form(...)):
    conn = get_db()
    cursor = conn.cursor()
    try:
        hashed_pw = pwd_context.hash(password)
        cursor.execute("INSERT INTO users (username, hashed_password) VALUES (?, ?)", (username, hashed_pw))
        conn.commit()
        conn.close()
        return RedirectResponse(url="/login", status_code=status.HTTP_303_SEE_OTHER)
    except sqlite3.IntegrityError:
        conn.close()
        return templates.TemplateResponse("register.html", {"request": request, "error": "Brugernavnet er allerede i brug"})

@app.get("/", response_class=HTMLResponse)
def dashboard(request: Request):
    user = request.cookies.get("session_user")
    if not user:
        return RedirectResponse(url="/login", status_code=status.HTTP_303_SEE_OTHER)
    conn = get_db()
    cursor = conn.cursor()
    cursor.execute("SELECT * FROM socialqueue ORDER BY id DESC")
    socialqueue = cursor.fetchall()
    conn.close()
    return templates.TemplateResponse("dashboard.html", {"request": request, "user": user, "socialqueue": socialqueue, "version": VERSION})

@app.get("/logout")
def logout():
    resp = RedirectResponse(url="/login", status_code=status.HTTP_303_SEE_OTHER)
    resp.delete_cookie(key="session_user")
    return resp
