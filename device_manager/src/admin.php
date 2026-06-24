<?php
/**
 * admin.php
 * =========
 * Bảng điều khiển quản trị (Admin Dashboard) của Hệ thống Quản lý Thiết bị.
 * Thiết kế tông sáng học thuật (Light Classic Academic Glassmorphism).
 * Tách nhỏ thành các module chức năng riêng biệt nằm trong thư mục admin/.
 */
require_once 'config.php';

// Bắt buộc đăng nhập
require_login();

// Xử lý mã PIN xác thực Admin
$pin_error = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'verify_admin_pin') {
    $entered_pin = $_POST['admin_pin'] ?? '';
    if ($entered_pin === ADMIN_PIN) {
        $_SESSION['admin_verified'] = true;
        header("Location: admin.php" . (isset($_GET['tab']) ? "?tab=" . urlencode($_GET['tab']) : ""));
        exit;
    } else {
        $pin_error = "Mã PIN không chính xác! Vui lòng kiểm tra lại.";
    }
}

// Nếu chưa xác thực PIN, hiển thị màn hình khóa PIN
if (!is_admin_verified()) {
    $ho_ten_gv = $_SESSION['ho_ten_gv'];
    ?>
    <!DOCTYPE html>
    <html lang="vi">
    <head>
        <meta charset="UTF-8">
        <title>Xác minh quyền quản trị - Quản lý Thiết bị</title>
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Outfit:wght@400;500;600;700;800&display=swap" rel="stylesheet">
        <link rel="stylesheet" href="style.css?v=<?= filemtime('style.css') ?>">
        <style>
            body {
                display: flex;
                justify-content: center;
                align-items: center;
                min-height: 100vh;
                background: linear-gradient(135deg, #f1f5f9 0%, #e2e8f0 100%);
                padding: 20px;
                font-family: 'Inter', sans-serif;
            }
            .pin-card {
                background: rgba(255, 255, 255, 0.7);
                backdrop-filter: blur(20px);
                -webkit-backdrop-filter: blur(20px);
                border: 1px solid rgba(255, 255, 255, 0.6);
                border-radius: 20px;
                max-width: 420px;
                width: 100%;
                padding: 40px 30px;
                box-shadow: 0 10px 30px rgba(0, 40, 80, 0.06);
                text-align: center;
            }
            .logo-box img {
                width: 80px;
                height: 80px;
                object-fit: cover;
                border-radius: 50%;
                border: 2px solid #0056b3;
                box-shadow: 0 4px 15px rgba(0, 86, 179, 0.15);
                margin-bottom: 20px;
            }
            .title-pin {
                font-family: 'Outfit', sans-serif;
                font-size: 1.5rem;
                font-weight: 700;
                color: #0056b3;
                margin-bottom: 10px;
            }
            .subtitle-pin {
                color: #475569;
                font-size: 0.92rem;
                margin-bottom: 25px;
                line-height: 1.5;
            }
            .pin-input-group {
                position: relative;
                margin-bottom: 20px;
            }
            .pin-input {
                width: 100%;
                height: 50px;
                padding: 10px 15px;
                border-radius: 10px;
                border: 1px solid #cbd5e1;
                font-size: 1.2rem;
                text-align: center;
                letter-spacing: 6px;
                font-weight: bold;
                background: #fff;
                outline: none;
                box-sizing: border-box;
                transition: border-color 0.3s;
            }
            .pin-input:focus {
                border-color: #0056b3;
                box-shadow: 0 0 0 3px rgba(0, 86, 179, 0.1);
            }
            .btn-verify {
                background: linear-gradient(135deg, #0056b3, #0077ee);
                color: #fff;
                border: none;
                padding: 14px;
                border-radius: 10px;
                width: 100%;
                font-size: 1rem;
                font-weight: 600;
                cursor: pointer;
                transition: all 0.3s ease;
                box-shadow: 0 4px 15px rgba(0, 86, 179, 0.2);
            }
            .btn-verify:hover {
                transform: translateY(-2px);
                box-shadow: 0 8px 20px rgba(0, 86, 179, 0.25);
            }
            .error-alert {
                background: rgba(239, 68, 68, 0.08);
                border: 1px solid rgba(239, 68, 68, 0.2);
                color: #b91c1c;
                padding: 12px;
                border-radius: 10px;
                margin-bottom: 20px;
                font-size: 0.88rem;
                font-weight: 500;
            }
            .nav-links {
                display: flex;
                justify-content: space-between;
                margin-top: 25px;
                font-size: 0.88rem;
            }
            .nav-link-item {
                color: #64748b;
                text-decoration: none;
                font-weight: 500;
                transition: color 0.2s;
            }
            .nav-link-item:hover {
                color: #0056b3;
            }
        </style>
    </head>
    <body>
        <div class="pin-card">
            <div class="logo-box">
                <img src="https://kyluc.vn/Userfiles/Upload/images/Download/2023/2/1/a86407f1e9c24fc486c9270169339758.jpg" alt="VLUTE Logo">
            </div>
            
            <h2 class="title-pin">Xác minh Quyền quản trị</h2>
            <p class="subtitle-pin">Chào thầy/cô <strong><?= htmlspecialchars($ho_ten_gv) ?></strong>. Đây là khu vực quản trị thiết bị nâng cao. Vui lòng nhập mã PIN bảo mật để tiếp tục.</p>
            
            <?php if (!empty($pin_error)): ?>
                <div class="error-alert">
                    ⚠️ <?= htmlspecialchars($pin_error) ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="admin.php<?= isset($_GET['tab']) ? '?tab=' . htmlspecialchars($_GET['tab']) : '' ?>">
                <input type="hidden" name="action" value="verify_admin_pin">
                <div class="pin-input-group">
                    <input type="password" name="admin_pin" class="pin-input" placeholder="••••••" maxlength="20" required autofocus autocomplete="off">
                </div>
                <button type="submit" class="btn-verify">🔓 XÁC MINH VÀ VÀO HỆ THỐNG</button>
            </form>
            
            <div class="nav-links">
                <a href="index.php" class="nav-link-item">🏠 Quay lại Trang chủ</a>
                <a href="logout.php" class="nav-link-item" style="color:#ef4444;">🚪 Đăng xuất</a>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}

$ho_ten_gv = $_SESSION['ho_ten_gv'];

$msg = "";
$error = "";

// Tạo thư mục uploads nếu chưa tồn tại
if (!file_exists('uploads')) {
    mkdir('uploads', 0777, true);
}

// 1. Xử lý xuất CSV (Chạy trước mọi output HTML)
require_once 'admin/admin_export_csv.php';

// 2. Xử lý dump CSDL SQL (Chạy trước mọi output HTML)
require_once 'admin/admin_backup_download.php';

// 3. Xử lý các hành động POST của admin
require_once 'admin/admin_actions.php';

// 4. Truy vấn dữ liệu cho giao diện
require_once 'admin/admin_queries.php';

$active_tab = isset($_GET['tab']) ? trim($_GET['tab']) : 'devices-tab';
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Trang quản trị - Quản lý Thiết bị</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Outfit:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
    <link rel="stylesheet" href="style.css?v=<?= filemtime('style.css') ?>">
    <?php include 'admin/admin_styles.php'; ?>
</head>
<body>

    <!-- Header Section -->
    <header>
        <div class="header-content">
            <h1>HỆ THỐNG QUẢN LÝ THIẾT BỊ - BẢNG ĐIỀU KHIỂN ADMIN</h1>
            <div class="user-info-bar">
                <span>👤 Quản trị viên: <strong><?= htmlspecialchars($ho_ten_gv) ?></strong></span>
                <a href="logout.php">🚪 Đăng xuất</a>
            </div>
        </div>
    </header>

    <!-- Main Container -->
    <div class="container">
        <!-- Navigation bar -->
        <div class="admin-nav">
            <h2>⚙ Cài đặt & Quản lý Hệ thống</h2>
            <a href="index.php" class="btn-nav-back">🔙 QUAY LẠI TRANG CHỦ</a>
        </div>

        <!-- Alert messages -->
        <?php if (!empty($msg)): ?>
            <div style="background:rgba(16,185,129,0.08); border:1px solid rgba(16,185,129,0.2); color:#047857; padding:15px; border-radius:12px; margin-bottom:20px; font-weight:600;">
                <?= $msg ?>
            </div>
        <?php endif; ?>
        <?php if (!empty($error)): ?>
            <div style="background:rgba(239,68,68,0.08); border:1px solid rgba(239,68,68,0.2); color:#b91c1c; padding:15px; border-radius:12px; margin-bottom:20px; font-weight:600;">
                ⚠️ <?= $error ?>
            </div>
        <?php endif; ?>

        <!-- Tabs Header Navigation -->
        <div class="tabs-header">
            <button type="button" class="tab-btn <?= $active_tab === 'devices-tab' ? 'active' : '' ?>" onclick="switchTab('devices-tab', this)">📦 THIẾT BỊ</button>
            <button type="button" class="tab-btn <?= $active_tab === 'logs-tab' ? 'active' : '' ?>" onclick="switchTab('logs-tab', this)">📋 NHẬT KÝ SỬ DỤNG</button>
            <button type="button" class="tab-btn <?= $active_tab === 'stats-tab' ? 'active' : '' ?>" onclick="switchTab('stats-tab', this)">📊 THỐNG KÊ & BÁO CÁO</button>
            <button type="button" class="tab-btn <?= $active_tab === 'crawler-tab' ? 'active' : '' ?>" onclick="switchTab('crawler-tab', this)">🔄 ĐỒNG BỘ THỜI KHÓA BIỂU</button>
            <button type="button" class="tab-btn <?= $active_tab === 'backup-tab' ? 'active' : '' ?>" onclick="switchTab('backup-tab', this)">💾 SAO LƯU & IMPORT/EXPORT</button>
            <button type="button" class="tab-btn <?= $active_tab === 'categories-tab' ? 'active' : '' ?>" onclick="switchTab('categories-tab', this)">📁 LOẠI THIẾT BỊ</button>
        </div>

        <!-- HTML Tabs Content -->
        <?php include 'admin/tab_devices.php'; ?>
        <?php include 'admin/tab_categories.php'; ?>
        <?php include 'admin/tab_logs.php'; ?>
        <?php include 'admin/tab_stats.php'; ?>
        <?php include 'admin/tab_crawler.php'; ?>
        <?php include 'admin/tab_backup.php'; ?>

    </div>

    <!-- Modals -->
    <?php include 'admin/modal_qr.php'; ?>
    <?php include 'admin/modal_history.php'; ?>
    <?php include 'admin/modal_edit.php'; ?>
    <?php include 'admin/modal_add.php'; ?>
    <?php include 'admin/modal_add_category.php'; ?>
    <?php include 'admin/modal_usage.php'; ?>
    <?php include 'admin/modal_quick_usage.php'; ?>

    <!-- JS Logic Scripts -->
    <?php include 'admin/admin_scripts.php'; ?>

    <!-- FLOATING NOTIFICATION -->
    <div class="floating-notif" id="floatingNotif">
        <span class="floating-notif-icon">🔔</span>
        <span id="floatingNotifText">Thông báo</span>
    </div>

    <!-- OVERLAY PHÓNG TO HÌNH ẢNH (LIGHTBOX) -->
    <div class="image-zoom-overlay" id="imageZoomOverlay" onclick="closeImageZoom()">
        <div class="image-zoom-close" onclick="closeImageZoom()">&times;</div>
        <img class="image-zoom-content" id="imageZoomContent" src="" alt="Zoomed Image" onclick="event.stopPropagation()">
    </div>
</body>
</html>
