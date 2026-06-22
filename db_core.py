import os
import sys
import psycopg2
from psycopg2.extras import RealDictCursor

# Đảm bảo tiếng Việt không bị lỗi console trên Windows
try:
    sys.stdout.reconfigure(encoding='utf-8')
except Exception:
    pass

def load_env():
    """Đọc cấu hình từ file .env thủ công để tránh phụ thuộc thư viện ngoài."""
    env = {}
    if os.path.exists(".env"):
        with open(".env", "r", encoding="utf-8") as f:
            for line in f:
                line = line.strip()
                if line and not line.startswith("#") and "=" in line:
                    k, v = line.split("=", 1)
                    env[k.strip()] = v.strip()
    return env

ENV = load_env()
DB_HOST = os.getenv("DB_HOST") or ENV.get("DB_HOST", "localhost")
DB_PORT = int(os.getenv("DB_PORT") or ENV.get("DB_PORT", "5432"))

# Tự động điều chỉnh cấu hình nếu chạy bên trong môi trường Docker
if os.path.exists("/.dockerenv"):
    if DB_HOST in ["localhost", "127.0.0.1"]:
        DB_HOST = "db"
    DB_PORT = 5432


DB_USER = os.getenv("DB_USER") or ENV.get("DB_USER", "postgres")
DB_PASSWORD = os.getenv("DB_PASSWORD") or ENV.get("DB_PASSWORD", "postgres")
DB_NAME = os.getenv("DB_NAME") or ENV.get("DB_NAME", "qldt_tkb")


def get_db_connection(check_db_exists=False):
    """
    Kết nối tới PostgreSQL.
    Nếu check_db_exists=True, kết nối tới database mặc định 'postgres' để kiểm tra và tự tạo database đích.
    """
    if check_db_exists:
        return psycopg2.connect(
            host=DB_HOST,
            port=DB_PORT,
            user=DB_USER,
            password=DB_PASSWORD,
            database="postgres"
        )
    else:
        return psycopg2.connect(
            host=DB_HOST,
            port=DB_PORT,
            user=DB_USER,
            password=DB_PASSWORD,
            database=DB_NAME
        )

def init_database():
    """Tự động tạo Database nếu chưa tồn tại và khởi tạo các bảng từ file schema.sql."""
    # 1. Tạo Database nếu chưa có
    try:
        conn = get_db_connection(check_db_exists=True)
        conn.autocommit = True
        cursor = conn.cursor()
        
        cursor.execute(f"SELECT 1 FROM pg_catalog.pg_database WHERE datname = '{DB_NAME}'")
        exists = cursor.fetchone()
        
        if not exists:
            print(f"[*] Cơ sở dữ liệu '{DB_NAME}' chưa tồn tại. Đang tiến hành tạo mới...")
            cursor.execute(f"CREATE DATABASE {DB_NAME}")
            print(f"[+] Đã tạo thành công cơ sở dữ liệu '{DB_NAME}'!")
            
        cursor.close()
        conn.close()
    except Exception as e:
        print(f"[-] Lỗi kiểm tra / tạo cơ sở dữ liệu: {e}")

    # 2. Khởi tạo schema từ file schema.sql
    try:
        conn = get_db_connection()
        conn.autocommit = True
        cursor = conn.cursor()
        
        # Đọc schema.sql hoặc init-db.sql từ các đường dẫn khả dụng
        schema_file = None
        possible_paths = [
            "schema.sql",
            "device_manager/init-db.sql",
            "/app_crawler/device_manager/init-db.sql"
        ]
        for path in possible_paths:
            if os.path.exists(path):
                schema_file = path
                break

        if schema_file:
            print(f"[*] Đang thực thi cấu trúc từ file '{schema_file}'...")
            with open(schema_file, "r", encoding="utf-8") as f:
                schema_sql = f.read()
            cursor.execute(schema_sql)
            
            # Cập nhật schema (migration tự động) nếu đã có bảng học kỳ cũ
            print("[*] Kiểm tra và tự động nâng cấp cấu trúc bảng hoc_ky_nam_hoc...")
            cursor.execute("""
                ALTER TABLE hoc_ky_nam_hoc 
                ADD COLUMN IF NOT EXISTS ngay_bat_dau DATE,
                ADD COLUMN IF NOT EXISTS ngay_ket_thuc DATE;
            """)
            
            # Chuẩn hóa Thứ: chuyển "Chủ nhật" thành "Chủ Nhật" để đồng bộ hiển thị
            print("[*] Chuẩn hóa dữ liệu ngày Thứ trong lịch giảng dạy...")
            cursor.execute("""
                UPDATE lich_giang_day SET thu = 'Chủ Nhật' WHERE thu = 'Chủ nhật';
            """)
            
            print("[+] Khởi tạo cấu trúc và nâng cấp các bảng thành công!")
        else:
            print("[-] Không tìm thấy tệp 'schema.sql' hoặc 'init-db.sql' nào để khởi tạo cấu trúc.")
            
        cursor.close()
        conn.close()
    except Exception as e:
        print(f"[-] Lỗi thực thi schema: {e}")
