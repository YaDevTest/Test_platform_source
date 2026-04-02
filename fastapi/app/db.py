import mysql.connector
import os
import json
import base64
import subprocess
import tempfile
import shutil
import time

print("=== db.py ЗАГРУЖЕН — ЛОГИ С FLUSH ===", flush=True)

def convert_wmf_to_png(b64_wmf: str) -> tuple[str, str]:
    start = time.time()
    print(f"\n=== WMF_CONVERT START ===", flush=True)
    tmp_dir = tempfile.mkdtemp()
    wmf_path = os.path.join(tmp_dir, 'image.wmf')
    svg_path = os.path.join(tmp_dir, 'image.svg')
    png_path = os.path.join(tmp_dir, 'image.png')

    try:
        wmf_data = base64.b64decode(b64_wmf)
        with open(wmf_path, 'wb') as f:
            f.write(wmf_data)
        print(f"1. Декодировано | размер: {os.path.getsize(wmf_path)} байт", flush=True)

        print("2. Пробуем ImageMagick (Placeable WMF)...", flush=True)
        try:
            result = subprocess.run(
                ['convert', '-density', '200', '-background', 'white', '-flatten', '-alpha', 'off', wmf_path, png_path],
                timeout=30, capture_output=True
            )
            print(f"   STDOUT: {result.stdout.decode(errors='ignore')[:300]}", flush=True)
            print(f"   STDERR: {result.stderr.decode(errors='ignore')[:300]}", flush=True)
            if os.path.exists(png_path) and os.path.getsize(png_path) > 0:
                with open(png_path, 'rb') as f:
                    png_b64 = base64.b64encode(f.read()).decode('utf-8')
                print(f"✓ УСПЕХ (ImageMagick) | время: {time.time()-start:.2f} сек", flush=True)
                return png_b64, 'image/png'
        except Exception as e:
            print(f"   ❌ Метод 1 упал: {e}", flush=True)

        print("3. Пробуем wmf2svg + rsvg-convert...", flush=True)
        try:
            subprocess.run(['wmf2svg', wmf_path, svg_path], check=True, timeout=8, capture_output=True)
            subprocess.run(['rsvg-convert', '-d', '200', '-p', '200', '--background-color', 'white', '-o', png_path, svg_path], check=True, timeout=10, capture_output=True)
            if os.path.exists(png_path) and os.path.getsize(png_path) > 0:
                with open(png_path, 'rb') as f:
                    png_b64 = base64.b64encode(f.read()).decode('utf-8')
                print(f"✓ УСПЕХ (wmf2svg+rsvg) | время: {time.time()-start:.2f} сек", flush=True)
                return png_b64, 'image/png'
        except Exception as e:
            print(f"   ❌ Метод 2 упал: {e}", flush=True)

        print("4. Пробуем wmf2svg + convert...", flush=True)
        try:
            subprocess.run(['convert', '-density', '200', '-background', 'white', '-flatten', svg_path if os.path.exists(svg_path) else wmf_path, png_path], check=True, timeout=10, capture_output=True)
            if os.path.exists(png_path) and os.path.getsize(png_path) > 0:
                with open(png_path, 'rb') as f:
                    png_b64 = base64.b64encode(f.read()).decode('utf-8')
                print(f"✓ УСПЕХ (wmf2svg+convert) | время: {time.time()-start:.2f} сек", flush=True)
                return png_b64, 'image/png'
        except Exception as e:
            print(f"   ❌ Метод 3 упал: {e}", flush=True)

        print("5. Все методы провалились → создаём красную заглушку", flush=True)
        subprocess.run(['convert', '-size', '800x400', 'xc:white', '-fill', 'red', '-pointsize', '30', '-gravity', 'center', '-annotate', '+0+0', 'WMF BROKEN\n(conversion failed)', png_path], check=True, timeout=8)
        with open(png_path, 'rb') as f:
            png_b64 = base64.b64encode(f.read()).decode('utf-8')
        print(f"⚠️ FALLBACK PNG создан | время: {time.time()-start:.2f} сек", flush=True)
        return png_b64, 'image/png'

    except Exception as e:
        print(f"❌ КРИТИЧЕСКАЯ ОШИБКА: {e}", flush=True)
        return b64_wmf, 'image/x-wmf'
    finally:
        shutil.rmtree(tmp_dir, ignore_errors=True)
        print("=== WMF_CONVERT FINISH ===\n", flush=True)


def get_db_connection():
    return mysql.connector.connect(
        host=os.getenv("MYSQL_HOST", "mysql"),
        user=os.getenv("MYSQL_USER", "appuser"),
        password=os.getenv("MYSQL_PASSWORD"),
        database=os.getenv("MYSQL_DATABASE", "testplatform"),
        charset='utf8mb4',
        collation='utf8mb4_unicode_ci'
    )


def init_tables():
    conn = get_db_connection()
    cursor = conn.cursor()
    cursor.execute("""CREATE TABLE IF NOT EXISTS tests (id INT AUTO_INCREMENT PRIMARY KEY, title VARCHAR(500) NOT NULL, description TEXT, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4""")
    cursor.execute("""CREATE TABLE IF NOT EXISTS questions (id INT AUTO_INCREMENT PRIMARY KEY, test_id INT NOT NULL, question_text TEXT NOT NULL, position INT NOT NULL, FOREIGN KEY (test_id) REFERENCES tests(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4""")
    cursor.execute("""CREATE TABLE IF NOT EXISTS answers (id INT AUTO_INCREMENT PRIMARY KEY, question_id INT NOT NULL, answer_text TEXT NOT NULL, is_correct TINYINT NOT NULL DEFAULT 0, option_label VARCHAR(10), position INT NOT NULL, FOREIGN KEY (question_id) REFERENCES questions(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4""")
    cursor.execute("""CREATE TABLE IF NOT EXISTS question_media (id INT AUTO_INCREMENT PRIMARY KEY, question_id INT NOT NULL, marker VARCHAR(50) NOT NULL, file_name VARCHAR(255), base64_data LONGTEXT, mime_type VARCHAR(100), original_format VARCHAR(20), FOREIGN KEY (question_id) REFERENCES questions(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4""")
    cursor.execute("""CREATE TABLE IF NOT EXISTS question_formulas (id INT AUTO_INCREMENT PRIMARY KEY, question_id INT NOT NULL, marker VARCHAR(50) NOT NULL, latex TEXT, mathml TEXT, formula_preview VARCHAR(200), formula_type VARCHAR(20) DEFAULT 'latex', FOREIGN KEY (question_id) REFERENCES questions(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4""")
    conn.commit()
    cursor.close()
    conn.close()
    print("✓ Таблицы MySQL готовы")


def save_json_to_mysql(parsed_json: dict, test_title: str = "Imported Test") -> dict:
    conn = get_db_connection()
    cursor = conn.cursor()
    try:
        cursor.execute("INSERT INTO tests (title, description) VALUES (%s, %s)", (test_title, f"Вопросов: {len(parsed_json.get('questions', []))}"))
        test_id = cursor.lastrowid
        questions = parsed_json.get("questions", [])
        labels = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H']
        total_answers = total_media = total_formulas = 0
        for pos, q in enumerate(questions, 1):
            cursor.execute("INSERT INTO questions (test_id, question_text, position) VALUES (%s, %s, %s)", (test_id, q.get("question", ""), pos))
            question_id = cursor.lastrowid
            correct_indices = set(q.get("correct_indices", []))
            for idx, answer_text in enumerate(q.get("answers", [])):
                is_correct = 1 if idx in correct_indices else 0
                label = labels[idx] if idx < len(labels) else str(idx)
                cursor.execute("INSERT INTO answers (question_id, answer_text, is_correct, option_label, position) VALUES (%s, %s, %s, %s, %s)", (question_id, answer_text, is_correct, label, idx + 1))
                total_answers += 1
            for marker, media_info in q.get("media", {}).items():
                b64 = media_info.get("base64", "")
                mime = media_info.get("mime_type", "")
                orig = media_info.get("original_format", "")
                if mime == "image/x-wmf" or media_info.get("file_name", "").endswith(".wmf"):
                    b64, mime = convert_wmf_to_png(b64)
                    orig = "wmf"
                cursor.execute("INSERT INTO question_media (question_id, marker, file_name, base64_data, mime_type, original_format) VALUES (%s, %s, %s, %s, %s, %s)", (question_id, marker, media_info.get("file_name", "").replace(".wmf", ".png"), b64, mime, orig))
                total_media += 1
            for marker, formula_info in q.get("formulas", {}).items():
                cursor.execute("INSERT INTO question_formulas (question_id, marker, latex, mathml, formula_preview, formula_type) VALUES (%s, %s, %s, %s, %s, %s)", (question_id, marker, formula_info.get("latex", ""), formula_info.get("mathml", ""), formula_info.get("formula_preview", ""), formula_info.get("type", "latex")))
                total_formulas += 1
        conn.commit()
        stats = {"test_id": test_id, "questions": len(questions), "answers": total_answers, "media": total_media, "formulas": total_formulas}
        print(f"✓ Сохранено в MySQL: {stats}")
        return stats
    except Exception as e:
        conn.rollback()
        print(f"❌ Ошибка MySQL: {e}")
        raise
    finally:
        cursor.close()
        conn.close()
