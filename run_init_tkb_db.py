import os
import sys
import psycopg2

DB_URL = os.getenv(
    "DATABASE_URL",
    "postgresql://postgres:12345@host.docker.internal:5432/zalo_personal"
)

# Chuyển đổi định dạng SQLAlchemy sang psycopg2 thuần nếu cần
if DB_URL.startswith("postgresql+psycopg2://"):
    DB_URL = DB_URL.replace("postgresql+psycopg2://", "postgresql://")

DUMP_PATH = "/app/tkb_dump.sql"

if not os.path.exists(DUMP_PATH):
    print(f"❌ Dump file not found at {DUMP_PATH}!")
    sys.exit(1)

print(f"🔌 Connecting to database (DSN: {DB_URL}) to import TKB tables...")
try:
    conn = psycopg2.connect(DB_URL)
    conn.autocommit = True
    cur = conn.cursor()
    print("✅ Connected successfully!")
    
    print(f"📖 Reading and parsing TKB dump file: {DUMP_PATH}...")
    with open(DUMP_PATH, "r", encoding="utf-8") as f:
        content = f.read()
        
    lines = content.splitlines()
    clean_lines = []
    for line in lines:
        if line.strip().startswith("\\"):
            continue
        if "OWNER TO" in line:
            continue
        clean_lines.append(line)
        
    statements = []
    current_statement = []
    
    for line in clean_lines:
        trimmed = line.strip()
        if not trimmed:
            continue
        if trimmed.startswith("--"):
            continue
        current_statement.append(line)
        if trimmed.endswith(";"):
            statements.append("\n".join(current_statement))
            current_statement = []
            
    if current_statement:
        stmt = "\n".join(current_statement).strip()
        if stmt:
            statements.append(stmt)
            
    print(f"🚀 Executing {len(statements)} SQL commands one by one...")
    success_count = 0
    skip_count = 0
    
    for i, stmt in enumerate(statements):
        stmt = stmt.strip()
        if not stmt:
            continue
        try:
            cur.execute(stmt)
            success_count += 1
            preview = stmt.replace('\n', ' ')[:60]
            print(f"  [{i+1}/{len(statements)}] ✅ SUCCESS: {preview}...")
        except Exception as stmt_err:
            skip_count += 1
            preview = stmt.replace('\n', ' ')[:60]
            print(f"  [{i+1}/{len(statements)}] ⚠️ SKIP (Error: {str(stmt_err).strip()}): {preview}...")
            
    print(f"🎉 TKB Dump Import finished! {success_count} success, {skip_count} skipped.")
    
    # Kiểm tra xem các bảng đã có chưa
    cur.execute("SELECT table_name FROM information_schema.tables WHERE table_schema='public'")
    tables = [r[0] for r in cur.fetchall()]
    print("📊 Current database tables:", tables)
    
    cur.close()
    conn.close()
except Exception as e:
    print(f"❌ Error during import: {e}")
