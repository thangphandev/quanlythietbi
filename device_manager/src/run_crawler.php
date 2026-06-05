<?php
/**
 * run_crawler.php
 * ===============
 * Kích hoạt và theo dõi trình cào thời khóa biểu tự động (crawler.py) ngầm.
 * Tránh tải lại trang hoặc timeout bằng cách chạy bất đồng bộ (background thread).
 */
require_once 'config.php';

// Chỉ cho phép admin đăng nhập truy cập và đã xác thực mã PIN
require_admin();

header('Content-Type: application/json');

$log_file = '/tmp/crawler.log';
$python_script = '/app_crawler/crawler.py';

// Hàm kiểm tra xem tiến trình crawler có đang chạy không
function is_crawler_running($script_path) {
    $cmd = "ps aux | grep " . escapeshellarg($script_path) . " | grep -v grep";
    $output = [];
    exec($cmd, $output);
    return count($output) > 0;
}

$action = $_GET['action'] ?? 'status';

if ($action === 'start') {
    // 1. Kiểm tra xem tiến trình đã chạy chưa
    if (is_crawler_running($python_script)) {
        echo json_encode([
            'status' => 'running',
            'msg'    => 'Trình cào TKB đang chạy ngầm, không cần kích hoạt lại!'
        ]);
        exit;
    }
    
    // 2. Kích hoạt trình cào ngầm trong linux
    // Chuyển thư mục làm việc sang /app_crawler để nạp đúng file .env và cookies_gv.pkl
    $id_hocky_namhoc = isset($_GET['id_hocky_namhoc']) ? intval($_GET['id_hocky_namhoc']) : 0;
    $arg = $id_hocky_namhoc > 0 ? " " . $id_hocky_namhoc : "";
    $cmd = "cd /app_crawler && python3 " . escapeshellarg($python_script) . $arg . " > " . escapeshellarg($log_file) . " 2>&1 &";
    exec($cmd);

    
    // Đợi 1 giây để tiến trình bắt đầu ghi log
    sleep(1);
    
    echo json_encode([
        'status' => 'started',
        'msg'    => '🚀 Tiến trình đồng bộ TKB khoa Cơ khí Động lực đã được kích hoạt ngầm thành công!'
    ]);
    exit;
}

if ($action === 'status') {
    $running = is_crawler_running($python_script);
    
    // Đọc log cuối để hiển thị tiến độ
    $log_content = "";
    if (file_exists($log_file)) {
        // Đọc 20 dòng cuối của file log
        $lines = array_slice(file($log_file), -20);
        $log_content = implode("", $lines);
    } else {
        $log_content = "Chưa có file log. Nhấn 'Đồng bộ' để bắt đầu cào...";
    }
    
    echo json_encode([
        'status'  => $running ? 'running' : 'idle',
        'running' => $running,
        'log'     => htmlspecialchars($log_content)
    ]);
    exit;
}

echo json_encode(['error' => 'Hành động không hợp lệ!']);
exit;
