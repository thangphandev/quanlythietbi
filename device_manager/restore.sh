#!/bin/bash
# ==============================================================================
# SCRIPT KHÔI PHỤC CƠ SỞ DỮ LIỆU TỰ ĐỘNG - POSTGRESQL RESTORE
# ==============================================================================
# Hướng dẫn: Chạy script và truyền đường dẫn tệp sao lưu (.sql.gz) cần khôi phục:
# ./restore.sh backups/htql_backup_YYYY_MM_DD_HHMMSS.sql.gz
# ==============================================================================

DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
BACKUP_FILE="$1"

# Đọc cấu hình từ file .env ở thư mục cha nếu có
ENV_FILE="${DIR}/../.env"
if [ -f "${ENV_FILE}" ]; then
    export $(grep -v '^#' "${ENV_FILE}" | xargs)
fi

# Gán giá trị mặc định cho kết nối DB
DB_HOST=${DB_HOST:-"localhost"}
DB_PORT=${DB_PORT:-"5432"}
DB_USER=${DB_USER:-"postgres"}
DB_PASSWORD=${DB_PASSWORD:-"12345"}
DB_NAME=${DB_NAME:-"htql"}

# 1. Kiểm tra tham số truyền vào
if [ -z "${BACKUP_FILE}" ]; then
    echo "[!] Vui lòng cung cấp đường dẫn đến tệp sao lưu cần khôi phục."
    echo "    Ví dụ: ./restore.sh backups/htql_backup_xxxx_xx_xx.sql.gz"
    echo ""
    echo "[*] Các tệp sao lưu hiện có trong thư mục backups/:"
    ls -lh "${DIR}/backups"/*.sql.gz 2>/dev/null
    exit 1
fi

if [ ! -f "${BACKUP_FILE}" ]; then
    echo "[-] Lỗi: Không tìm thấy tệp tin sao lưu tại đường dẫn: ${BACKUP_FILE}"
    exit 1
fi

echo "[*] Tệp sao lưu được chọn: ${BACKUP_FILE}"
echo "[*] Đang chuẩn bị khôi phục cơ sở dữ liệu '${DB_NAME}'..."

# 2. Giải nén tệp sao lưu ra một tệp SQL tạm thời
TEMP_SQL="${DIR}/backups/temp_restore.sql"
if [[ "${BACKUP_FILE}" == *.gz ]]; then
    echo "[*] Giải nén tệp sao lưu..."
    gunzip -c "${BACKUP_FILE}" > "${TEMP_SQL}"
else
    cp "${BACKUP_FILE}" "${TEMP_SQL}"
fi

# 3. Tiến hành dọn dẹp Schema cũ để tránh trùng lặp dữ liệu (Clean slate)
echo "[*] Đang làm sạch cơ sở dữ liệu hiện tại (Dọn dẹp schema public)..."

CLEAN_SQL="DROP SCHEMA public CASCADE; CREATE SCHEMA public; GRANT ALL ON SCHEMA public TO public;"

# Thực thi lệnh làm sạch schema
if command -v psql &> /dev/null; then
    PGPASSWORD="${DB_PASSWORD}" psql -h "${DB_HOST}" -p "${DB_PORT}" -U "${DB_USER}" -d "${DB_NAME}" -c "${CLEAN_SQL}" &>/dev/null
else
    docker exec -i device_manager_db psql -U "${DB_USER}" -d "${DB_NAME}" -c "${CLEAN_SQL}" &>/dev/null
fi

# 4. Thực thi phục hồi cơ sở dữ liệu từ file SQL tạm thời
echo "[*] Đang nạp dữ liệu từ tệp sao lưu vào PostgreSQL..."

RESTORE_SUCCESS=0

if command -v psql &> /dev/null; then
    PGPASSWORD="${DB_PASSWORD}" psql -h "${DB_HOST}" -p "${DB_PORT}" -U "${DB_USER}" -d "${DB_NAME}" -f "${TEMP_SQL}" &>/dev/null
    if [ $? -eq 0 ]; then
        RESTORE_SUCCESS=1
    fi
else
    # Thử phương án dự phòng thông qua Docker container
    docker exec -i device_manager_db psql -U "${DB_USER}" -d "${DB_NAME}" < "${TEMP_SQL}" &>/dev/null
    if [ $? -eq 0 ]; then
        RESTORE_SUCCESS=1
    fi
fi

# Dọn dẹp file SQL tạm thời
rm -f "${TEMP_SQL}"

# 5. Kết quả khôi phục
if [ ${RESTORE_SUCCESS} -eq 1 ]; then
    echo "[+] THÀNH CÔNG: Cơ sở dữ liệu đã được khôi phục về trạng thái của bản sao lưu."
    exit 0
else
    echo "[-] THẤT BẠI: Quá trình khôi phục cơ sở dữ liệu gặp lỗi. Vui lòng kiểm tra lại kết nối PostgreSQL."
    exit 1
fi
