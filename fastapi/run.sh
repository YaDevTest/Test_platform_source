#!/bin/bash
cd "$(dirname "$0")"
source venv/bin/activate
pkill -9 -f uvicorn 2>/dev/null || true
sleep 1
lsof -ti:8000 | xargs kill -9 2>/dev/null || true
uvicorn app.main:app --host 0.0.0.0 --port 8000 --reload
