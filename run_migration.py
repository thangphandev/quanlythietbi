"""
run_migration.py — Chạy migration tạo bảng TKB trong Docker container.
Usage: docker exec -i zlapi_app python /app/run_migration.py
"""
import os
import sys
from sqlalchemy import create_engine, text

DB_URL = os.getenv(
    "DATABASE_URL",
    "postgresql+psycopg2://postgres:123456@host.docker.internal:5432/postgres"
)
engine = create_engine(DB_URL, pool_pre_ping=True)

STATEMENTS = [
    # gv_zalo_mapping
    """
    CREATE TABLE IF NOT EXISTS public.gv_zalo_mapping (
        id                  SERIAL PRIMARY KEY,
        id_giang_vien       INTEGER NOT NULL UNIQUE,
        ho_ten_gv           VARCHAR(100),
        zalo_thread_id      VARCHAR(100),
        bot_phone           VARCHAR(20),
        is_active           BOOLEAN DEFAULT TRUE,
        note                TEXT,
        created_at          TIMESTAMPTZ DEFAULT NOW(),
        updated_at          TIMESTAMPTZ DEFAULT NOW()
    )
    """,
    "CREATE INDEX IF NOT EXISTS idx_gv_zalo_mapping_gv ON public.gv_zalo_mapping(id_giang_vien)",

    # reminder_events
    """
    CREATE TABLE IF NOT EXISTS public.reminder_events (
        id              SERIAL PRIMARY KEY,
        title           VARCHAR(255) NOT NULL,
        description     TEXT,
        event_date      DATE,
        event_time      TIME DEFAULT '06:00:00',
        recurrence      VARCHAR(20) DEFAULT 'once',
        target_type     VARCHAR(20) DEFAULT 'all',
        id_giang_vien   INTEGER,
        message_template TEXT,
        is_active       BOOLEAN DEFAULT TRUE,
        created_by      VARCHAR(100),
        created_at      TIMESTAMPTZ DEFAULT NOW(),
        updated_at      TIMESTAMPTZ DEFAULT NOW()
    )
    """,
    "CREATE INDEX IF NOT EXISTS idx_reminder_events_date ON public.reminder_events(event_date)",
    "CREATE INDEX IF NOT EXISTS idx_reminder_events_gv ON public.reminder_events(id_giang_vien)",

    # reminder_logs
    """
    CREATE TABLE IF NOT EXISTS public.reminder_logs (
        id              SERIAL PRIMARY KEY,
        log_date        DATE NOT NULL DEFAULT CURRENT_DATE,
        id_giang_vien   INTEGER,
        ho_ten_gv       VARCHAR(100),
        zalo_thread_id  VARCHAR(100),
        bot_phone       VARCHAR(20),
        message_type    VARCHAR(50) DEFAULT 'tkb',
        message_content TEXT,
        status          VARCHAR(20) DEFAULT 'sent',
        error_msg       TEXT,
        event_id        INTEGER,
        sent_at         TIMESTAMPTZ DEFAULT NOW()
    )
    """,
    "CREATE INDEX IF NOT EXISTS idx_reminder_logs_date ON public.reminder_logs(log_date)",
    "CREATE INDEX IF NOT EXISTS idx_reminder_logs_gv ON public.reminder_logs(id_giang_vien)",
    "CREATE INDEX IF NOT EXISTS idx_reminder_logs_sent_at ON public.reminder_logs(sent_at DESC)",

    # reminder_config
    """
    CREATE TABLE IF NOT EXISTS public.reminder_config (
        key         VARCHAR(100) PRIMARY KEY,
        value       TEXT,
        description TEXT,
        updated_at  TIMESTAMPTZ DEFAULT NOW()
    )
    """,

    # Default config values
    """
    INSERT INTO public.reminder_config (key, value, description) VALUES
        ('reminder_time', '06:00', 'Giờ gửi nhắc nhở TKB hàng ngày (HH:MM)'),
        ('high_floor_threshold', '4', 'Tầng >= N thì cảnh báo đi sớm'),
        ('early_minutes', '15', 'Số phút cần đến sớm hơn nếu phòng tầng cao'),
        ('default_bot_phone', '', 'Số điện thoại bot mặc định để gửi (để trống = lấy bot đầu tiên online)')
    ON CONFLICT (key) DO NOTHING
    """,
]

print("🚀 Running TKB migration...")
ok = 0
skip = 0
with engine.begin() as conn:
    for stmt in STATEMENTS:
        s = stmt.strip()
        if not s:
            continue
        try:
            conn.execute(text(s))
            preview = s[:60].replace('\n', ' ')
            print(f"  ✅ {preview}...")
            ok += 1
        except Exception as e:
            print(f"  ⚠️  SKIP: {str(e)[:80]}")
            skip += 1

print(f"\n✅ Migration done! {ok} OK, {skip} skipped")
