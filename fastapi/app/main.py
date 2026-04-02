from fastapi import FastAPI, UploadFile, File, HTTPException, BackgroundTasks
from fastapi.responses import FileResponse, JSONResponse
from fastapi.middleware.cors import CORSMiddleware
from pydantic import BaseModel
import uuid
import os
import shutil
from pathlib import Path
from typing import Dict, List, Optional
import asyncio
from datetime import datetime
import sys
from io import StringIO
from app.docx_parser import parse_docx_to_json
from app.db import save_json_to_mysql, init_tables
from prometheus_fastapi_instrumentator import Instrumentator

app = FastAPI(
    title="DOCX to JSON Parser API",
    description="API для конвертации DOCX тестов в JSON",
    version="1.0.0"
)
Instrumentator().instrument(app).expose(app)
app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)
MAX_FILE_SIZE = 25 * 1024 * 1024
MAX_QUEUE_SIZE = 10
if os.name == 'nt':
    UPLOAD_DIR = Path("uploads")
else:
    UPLOAD_DIR = Path("/tmp/docx_parser")
UPLOAD_DIR.mkdir(exist_ok=True)

@app.on_event("startup")
async def startup_event():
    try:
        init_tables()
        print("MySQL tables initialized")
    except Exception as e:
        print(f"MySQL unavailable at startup: {e}")

tasks_storage: Dict[str, dict] = {}
queue: List[str] = []
active_task: Optional[str] = None

class UploadResponse(BaseModel):
    task_id: str
    status: str
    position_in_queue: Optional[int] = None
    message: str

class StatusResponse(BaseModel):
    status: str
    progress: int
    logs: List[str]
    position_in_queue: Optional[int] = None
    total_in_queue: Optional[int] = None
    statistics: Optional[Dict] = None
    error: Optional[str] = None

class ErrorResponse(BaseModel):
    error: str
    message: str
    queue_size: Optional[int] = None


# === КЛЮЧЕВОЕ ИЗМЕНЕНИЕ: TeeStream дублирует логи в stderr для Loki ===
class TeeStream:
    """Пишет одновременно в StringIO (для API status) и в stderr (для Promtail → Loki)"""
    def __init__(self, capture, real_stream):
        self.capture = capture
        self.real_stream = real_stream

    def write(self, msg):
        self.capture.write(msg)
        self.real_stream.write(msg)
        self.real_stream.flush()

    def flush(self):
        self.capture.flush()
        self.real_stream.flush()


def capture_logs(func, *args, **kwargs):
    """Перехватывает print-логи для API И дублирует в stderr для Loki"""
    log_capture = StringIO()
    old_stdout = sys.stdout
    # TeeStream: логи идут и в capture (для /api/status), и в stderr (для Promtail → Loki)
    sys.stdout = TeeStream(log_capture, sys.stderr)
    try:
        result = func(*args, **kwargs)
        sys.stdout = old_stdout
        logs = log_capture.getvalue().split('\n')
        return result, [log for log in logs if log.strip()]
    except Exception as e:
        sys.stdout = old_stdout
        logs = log_capture.getvalue().split('\n')
        raise Exception(f"{str(e)}\n" + "\n".join(logs))


async def process_task(task_id: str):
    global active_task
    task = tasks_storage[task_id]
    task_dir = UPLOAD_DIR / task_id
    input_path = task_dir / "input.docx"
    output_path = task_dir / "output.json"
    try:
        task["status"] = "processing"
        task["progress"] = 10
        task["logs"].append(f"Started: {datetime.now().strftime('%H:%M:%S')}")
        task["progress"] = 30
        result, logs = await asyncio.to_thread(
            capture_logs, parse_docx_to_json, str(input_path), str(output_path)
        )
        task["logs"].extend(logs)
        task["progress"] = 90
        try:
            import json
            with open(str(output_path)) as f:
                json_data = json.load(f)
            stats = save_json_to_mysql(json_data)
            task["statistics"] = stats
        except Exception as db_err:
            task["logs"].append(f"DB save error: {db_err}")
            task["statistics"] = {"questions": len(result.get("questions", [])), "media": len(result.get("media", {})), "formulas": len(result.get("formulas", {}))}
        task["status"] = "completed"
        task["progress"] = 100
        task["logs"].append(f"Completed: {datetime.now().strftime('%H:%M:%S')}")
    except Exception as e:
        task["status"] = "failed"
        task["progress"] = 0
        task["error"] = str(e)
        task["logs"].append(f"Error: {str(e)}")
    finally:
        active_task = None
        await process_queue()

async def process_queue():
    global active_task
    if active_task is not None:
        return
    if not queue:
        return
    task_id = queue.pop(0)
    active_task = task_id
    for idx, tid in enumerate(queue):
        if tid in tasks_storage:
            tasks_storage[tid]["position_in_queue"] = idx + 1
    asyncio.create_task(process_task(task_id))

@app.post("/api/upload", response_model=UploadResponse, status_code=202)
async def upload_file(background_tasks: BackgroundTasks, file: UploadFile = File(...)):
    if not file.filename.endswith('.docx'):
        raise HTTPException(status_code=400, detail="Only DOCX files supported")
    file_size = 0
    chunk_size = 1024 * 1024
    temp_chunks = []
    while True:
        chunk = await file.read(chunk_size)
        if not chunk:
            break
        file_size += len(chunk)
        temp_chunks.append(chunk)
        if file_size > MAX_FILE_SIZE:
            raise HTTPException(status_code=413, detail=f"File too large. Max: {MAX_FILE_SIZE / (1024*1024):.0f} MB")
    if len(queue) >= MAX_QUEUE_SIZE:
        raise HTTPException(status_code=503, detail="Queue full. Try later.")
    task_id = str(uuid.uuid4())
    task_dir = UPLOAD_DIR / task_id
    task_dir.mkdir(parents=True, exist_ok=True)
    input_path = task_dir / "input.docx"
    with open(input_path, 'wb') as f:
        for chunk in temp_chunks:
            f.write(chunk)
    queue.append(task_id)
    position = len(queue)
    tasks_storage[task_id] = {
        "status": "pending",
        "progress": 0,
        "logs": [f"File uploaded: {file.filename}", f"Size: {file_size/1024:.2f} KB", f"Queue position: {position}"],
        "position_in_queue": position,
        "created_at": datetime.now().isoformat(),
        "filename": file.filename,
        "statistics": None,
        "error": None
    }
    background_tasks.add_task(process_queue)
    return UploadResponse(task_id=task_id, status="pending", position_in_queue=position, message=f"Queued at position: {position}")

@app.get("/api/status/{task_id}", response_model=StatusResponse)
async def get_status(task_id: str):
    if task_id not in tasks_storage:
        raise HTTPException(status_code=404, detail="Task not found")
    task = tasks_storage[task_id]
    position = None
    total = None
    if task["status"] == "pending" and task_id in queue:
        position = queue.index(task_id) + 1
        total = len(queue)
    return StatusResponse(status=task["status"], progress=task["progress"], logs=task["logs"], position_in_queue=position, total_in_queue=total, statistics=task["statistics"], error=task["error"])

@app.get("/api/download/{task_id}")
async def download_result(task_id: str):
    if task_id not in tasks_storage:
        raise HTTPException(status_code=404, detail="Task not found")
    task = tasks_storage[task_id]
    if task["status"] != "completed":
        raise HTTPException(status_code=400, detail=f"Not ready. Status: {task['status']}")
    task_dir = UPLOAD_DIR / task_id
    output_path = task_dir / "output.json"
    if not output_path.exists():
        raise HTTPException(status_code=404, detail="Result file not found")
    response = FileResponse(path=output_path, media_type="application/json", filename=f"questions_{task_id[:8]}.json")
    async def cleanup():
        await asyncio.sleep(1)
        try:
            shutil.rmtree(task_dir)
            del tasks_storage[task_id]
        except Exception as e:
            print(f"Cleanup error {task_id}: {e}")
    asyncio.create_task(cleanup())
    return response

@app.get("/api/health")
async def health_check():
    return {"status": "healthy", "active_tasks": 1 if active_task else 0, "queue_size": len(queue), "total_tasks": len(tasks_storage)}
@app.get("/api/error500")
def trigger_error():
    raise ValueError("Test critical error for monitoring")

@app.get("/")
async def root():
    return {"service": "DOCX to JSON Parser", "version": "1.0.0", "docs": "/docs", "endpoints": {"upload": "POST /api/upload", "status": "GET /api/status/{task_id}", "download": "GET /api/download/{task_id}", "health": "GET /api/health"}}

if __name__ == "__main__":
    import uvicorn
    uvicorn.run(app, host="0.0.0.0", port=8000)
