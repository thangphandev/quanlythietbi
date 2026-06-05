#!/bin/bash
# ==============================================================================
# SCRIPT SAO LƯU CƠ SỞ DỮ LIỆU TỰ ĐỘNG - TELEGRAM CLOUD & LOCAL BACKUP
# ==============================================================================
# Hướng dẫn: Đặt Cronjob trên Linux chạy lúc 23:55 hàng ngày:
# 55 23 * * * /bin/bash /d/code/quanlythietbi/device_manager/backup.sh >> /d/code/quanlythietbi/device_manager/backups/cron.log 2>&1
# ==============================================================================

# 1. Định nghĩa các đường dẫn & biến thời gian
DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
DATE=$(date +"%Y_%m_%d_%H%M%S")
BACKUP_DIR="${DIR}/backups"
mkdir -p "${BACKUP_DIR}"

BACKUP_FILE="${BACKUP_DIR}/htql_backup_${DATE}.sql"

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

echo "[*] [${DATE}] Bắt đầu tiến trình sao lưu cơ sở dữ liệu..."

# 2. Thực hiện pg_dump xuất CSDL ra file SQL
# Nếu chạy trong Docker, ta có thể dùng lệnh docker exec
# Nếu chạy trên máy chủ đã cài sẵn postgres-client:
PGPASSWORD="${DB_PASSWORD}" pg_dump -h "${DB_HOST}" -p "${DB_PORT}" -U "${DB_USER}" -d "${DB_NAME}" -F p -f "${BACKUP_FILE}"

if [ $? -eq 0 ]; then
    echo "[+] Đã tạo tệp tin sao lưu cục bộ thành công: ${BACKUP_FILE}"
else
    # Thử phương án dự phòng thông qua Docker container
    echo "[!] Không tìm thấy pg_dump cục bộ hoặc lỗi kết nối. Đang thử qua Docker container 'device_manager_db'..."
    docker exec -i device_manager_db pg_dump -U "${DB_USER}" -d "${DB_NAME}" > "${BACKUP_FILE}" 2>/dev/null
    
    if [ $? -eq 0 ] && [ -s "${BACKUP_FILE}" ]; then
        echo "[+] Đã tạo tệp tin sao lưu thông qua Docker thành công!"
    else
        echo "[-] LỖI NGHIÊM TRỌNG: Không thể kết xuất cơ sở dữ liệu!"
        exit 1
    fi
fi

# Nén file backup để tiết kiệm dung lượng
gzip "${BACKUP_FILE}"
COMPRESSED_FILE="${BACKUP_FILE}.gz"
echo "[+] Đã nén tệp tin sao lưu thành công: ${COMPRESSED_FILE}"

# 3. Gửi file backup lên Telegram Cloud nếu được cấu hình
if [ ! -z "${TELEGRAM_BOT_TOKEN}" ] && [ ! -z "${TELEGRAM_CHAT_ID}" ]; then
    echo "[*] Đang gửi tệp tin sao lưu lên Telegram Cloud..."
    
    CAPTION="💾 *Bản sao lưu CSDL tự động*
 *Thời gian:* $(date +'%d/%m/%Y %H:%M:%S')
🖥 *Máy chủ:* Local Linux Server
📦 *Hệ thống:* Quản lý thiết bị VLUTE"

    RESPONSE=$(curl -s -X POST "https://api.telegram.org/bot${TELEGRAM_BOT_TOKEN}/sendDocument" \
        -F chat_id="${TELEGRAM_CHAT_ID}" \
        -F document=@"${COMPRESSED_FILE}" \
        -F caption="${CAPTION}" \
        -F parse_mode="Markdown")

    if echo "${RESPONSE}" | grep -q '"ok":true'; then
        echo "[+] Đã tải bản sao lưu lên Telegram Cloud thành công!"
    else
        echo "[-] Cảnh báo: Gửi file lên Telegram thất bại! Chi tiết phản hồi:"
        echo "${RESPONSE}"
    fi
else
    echo "[!] Bỏ qua bước sao lưu đám mây: TELEGRAM_BOT_TOKEN hoặc TELEGRAM_CHAT_ID chưa được định nghĩa trong file .env."
fi

# 4. Giữ lại bản sao lưu cục bộ trong vòng 7 ngày gần nhất (Xóa các file cũ hơn)
echo "[*] Dọn dẹp bản sao lưu cũ..."
find "${BACKUP_DIR}" -name "htql_backup_*.sql.gz" -mtime +7 -exec rm -f {} \;
echo "[+] Tiến trình sao lưu hoàn tất thành công!"
exit 0
