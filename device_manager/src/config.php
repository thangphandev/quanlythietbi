<?php
/**
 * config.php
 * ==========
 * Cấu hình kết nối cơ sở dữ liệu PostgreSQL và cấu hình Google OAuth.
 */

// Thiết lập múi giờ Việt Nam mặc định cho PHP toàn hệ thống
date_default_timezone_set('Asia/Ho_Chi_Minh');

// Bắt đầu Session nếu chưa được bật
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Hàm nạp tệp tin .env thủ công (đọc trực tiếp từ thư mục cha /app_crawler/.env)
function load_env_file($path) {
    if (file_exists($path)) {
        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line) || str_starts_with($line, '#')) continue;
            if (strpos($line, '=') !== false) {
                list($name, $value) = explode('=', $line, 2);
                $name = trim($name);
                $value = trim($value);
                // Loại bỏ dấu nháy kép hoặc nháy đơn nếu có
                $value = trim($value, "\"'");
                // Đưa vào môi trường
                putenv("$name=$value");
                $_ENV[$name] = $value;
                $_SERVER[$name] = $value;
            }
        }
    }
}
load_env_file('/app_crawler/.env');

// 1. Đọc các tham số cấu hình từ biến môi trường (Docker)
$db_host = getenv('DB_HOST') ?: 'localhost';

// Tự động chuyển hướng 'localhost' sang dịch vụ container 'db' nếu đang chạy bên trong môi trường Docker
if (file_exists('/.dockerenv') && ($db_host === 'localhost' || $db_host === '127.0.0.1')) {
    $db_host = 'db';
}

define('DB_HOST', $db_host);

$db_port = getenv('DB_PORT') ?: '5432';
if (file_exists('/.dockerenv')) {
    $db_port = '5432'; // Bắt buộc dùng cổng nội bộ 5432 khi chạy bên trong Docker
}
define('DB_PORT', $db_port);
define('DB_NAME', getenv('DB_NAME') ?: 'htql');
define('DB_USER', getenv('DB_USER') ?: 'postgres');
define('DB_PASSWORD', getenv('DB_PASSWORD') ?: '12345');

define('GOOGLE_CLIENT_ID', getenv('GOOGLE_CLIENT_ID') ?: '');
define('GOOGLE_CLIENT_SECRET', getenv('GOOGLE_CLIENT_SECRET') ?: '');
define('GOOGLE_REDIRECT_URI', getenv('GOOGLE_REDIRECT_URI') ?: 'http://localhost:8080/callback.php');
define('ADMIN_PIN', getenv('ADMIN_PIN') ?: '123456');

// Cấu hình cho phép đăng nhập Chế độ Demo (true để bật khi kiểm thử, false để tắt khi chạy thực tế)
$allow_demo_val = getenv('ALLOW_DEMO') !== false ? getenv('ALLOW_DEMO') : 'false';
define('ALLOW_DEMO', in_array(strtolower($allow_demo_val), ['true', '1', 'yes', 'on'], true));

// 2. Kết nối Cơ sở dữ liệu với chế độ Retry (phòng trường hợp DB khởi động chậm trong Docker)
$db = null;
$max_retries = 3;
$retry_count = 0;

while ($retry_count < $max_retries) {
    try {
        $dsn = "pgsql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME;
        $db = new PDO($dsn, DB_USER, DB_PASSWORD, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        // Đồng bộ múi giờ PostgreSQL session sang GMT+7 Việt Nam
        $db->exec("SET TIME ZONE 'Asia/Ho_Chi_Minh'");
        break; // Kết nối thành công, thoát vòng lặp
    } catch (PDOException $e) {
        $retry_count++;
        if ($retry_count >= $max_retries) {
            die("❌ Không thể kết nối đến cơ sở dữ liệu sau $max_retries lần thử. Lỗi: " . $e->getMessage());
        }
        sleep(2); // Đợi 2 giây trước khi thử lại
    }
}

// Tự động nâng cấp CSDL: Thêm cột hình ảnh vào bảng thiết bị và số điện thoại vào bảng giảng viên
try {
    // Tạo bảng loại nếu chưa tồn tại
    $db->query("CREATE TABLE IF NOT EXISTS loai (
        id_loai SERIAL PRIMARY KEY,
        ten_loai VARCHAR(100) NOT NULL UNIQUE,
        ma_mau VARCHAR(20) DEFAULT '#0284c7'
    )");
    
    // Đảm bảo cột ma_mau tồn tại
    $db->query("ALTER TABLE loai ADD COLUMN IF NOT EXISTS ma_mau VARCHAR(20) DEFAULT '#0284c7'");
    
    // Thêm cột id_loai vào bảng thiet_bi
    $db->query("ALTER TABLE thiet_bi ADD COLUMN IF NOT EXISTS id_loai INT REFERENCES loai(id_loai) ON DELETE SET NULL");
    
    // Nạp các phân loại mặc định nếu bảng loai trống
    $count = $db->query("SELECT COUNT(*) FROM loai")->fetchColumn();
    if ($count == 0) {
        $db->query("INSERT INTO loai (ten_loai, ma_mau) VALUES 
            ('Mô hình dạy học', '#3b82f6'), 
            ('Thiết bị chẩn đoán', '#10b981'), 
            ('Thiết bị đo kiểm', '#f59e0b'), 
            ('Công cụ cầm tay', '#8b5cf6')
        ");
    } else {
        // Cập nhật màu sắc cho các phân loại mặc định nếu chúng chưa có màu đặc trưng (hoặc dùng mặc định cũ)
        $db->query("UPDATE loai SET ma_mau = '#3b82f6' WHERE ten_loai = 'Mô hình dạy học' AND (ma_mau IS NULL OR ma_mau = '#0284c7')");
        $db->query("UPDATE loai SET ma_mau = '#10b981' WHERE ten_loai = 'Thiết bị chẩn đoán' AND (ma_mau IS NULL OR ma_mau = '#0284c7')");
        $db->query("UPDATE loai SET ma_mau = '#f59e0b' WHERE ten_loai = 'Thiết bị đo kiểm' AND (ma_mau IS NULL OR ma_mau = '#0284c7')");
        $db->query("UPDATE loai SET ma_mau = '#8b5cf6' WHERE ten_loai = 'Công cụ cầm tay' AND (ma_mau IS NULL OR ma_mau = '#0284c7')");
    }

    // Tăng giới hạn độ dài mã thiết bị lên 100 ký tự
    $db->query("ALTER TABLE thiet_bi ALTER COLUMN ma_thiet_bi TYPE VARCHAR(100)");

    $db->query("ALTER TABLE thiet_bi ADD COLUMN IF NOT EXISTS hinh_anh VARCHAR(255) DEFAULT NULL");
    $db->query("ALTER TABLE giang_vien ADD COLUMN IF NOT EXISTS so_dien_thoai VARCHAR(50) DEFAULT NULL");
    
    // Cập nhật số điện thoại mẫu
    $db->query("UPDATE giang_vien SET so_dien_thoai = '0834 029 049' WHERE id_giang_vien = 237 AND so_dien_thoai IS NULL");
    $db->query("UPDATE giang_vien SET so_dien_thoai = '0987 654 321' WHERE id_giang_vien = 101 AND so_dien_thoai IS NULL");
    $db->query("UPDATE giang_vien SET so_dien_thoai = '0912 345 678' WHERE id_giang_vien = 102 AND so_dien_thoai IS NULL");
    
    // Bỏ qua cột số lượng thiết bị, điều chỉnh CSDL tự động
    $db->query("ALTER TABLE thiet_bi DROP COLUMN IF EXISTS so_luong_tong");
    $db->query("ALTER TABLE thiet_bi DROP COLUMN IF EXISTS so_luong_kha_dung");
} catch (PDOException $e) {
    // Bỏ qua nếu cột đã tồn tại hoặc có lỗi nhỏ
}

/**
 * Hàm kiểm tra xem người dùng đã đăng nhập chưa
 */
function is_logged_in() {
    global $db;
    if (!isset($_SESSION['id_giang_vien']) || empty($_SESSION['id_giang_vien'])) {
        return false;
    }
    
    // Kiểm tra đuôi email @vlute.edu.vn
    $email = $_SESSION['email'] ?? '';
    if (substr(strtolower($email), -13) !== '@vlute.edu.vn') {
        return false;
    }
    
    // Kiểm tra giảng viên tồn tại trong CSDL
    try {
        $stmt = $db->prepare("SELECT COUNT(*) FROM giang_vien WHERE id_giang_vien = :id");
        $stmt->execute(['id' => $_SESSION['id_giang_vien']]);
        if (intval($stmt->fetchColumn()) === 0) {
            return false;
        }
    } catch (PDOException $e) {
        return false;
    }
    
    return true;
}

/**
 * Hàm yêu cầu đăng nhập (nếu chưa đăng nhập thì redirect về login.php)
 */
function require_login() {
    if (!is_logged_in()) {
        header("Location: login.php");
        exit;
    }
}

/**
 * Kiểm tra xem người dùng đã nhập mã PIN xác thực quyền admin chưa
 */
function is_admin_verified() {
    return isset($_SESSION['admin_verified']) && $_SESSION['admin_verified'] === true;
}

/**
 * Yêu cầu quyền admin (yêu cầu đăng nhập + xác thực mã PIN)
 */
function require_admin() {
    require_login();
    if (!is_admin_verified()) {
        header("Location: admin.php");
        exit;
    }
}
