<?php
/**
 * login.php
 * =========
 * Trang đăng nhập hệ thống, tích hợp Google OAuth 2.0 và Chế độ Demo ngoại tuyến.
 */
require_once 'config.php';

// Nếu đã đăng nhập, tự động chuyển hướng về trang chủ
if (is_logged_in()) {
    header("Location: index.php");
    exit;
}

// 1. Tạo CSRF State Token cho Google OAuth
if (empty($_SESSION['oauth_state'])) {
    $_SESSION['oauth_state'] = bin2hex(random_bytes(16));
}

// Đường dẫn OAuth URL của Google
$google_oauth_url = "";
if (!empty(GOOGLE_CLIENT_ID)) {
    $params = [
        'response_type' => 'code',
        'client_id'     => GOOGLE_CLIENT_ID,
        'redirect_uri'  => GOOGLE_REDIRECT_URI,
        'scope'         => 'openid profile email',
        'state'         => $_SESSION['oauth_state'],
        'prompt'        => 'select_account'
    ];
    $google_oauth_url = "https://accounts.google.com/o/oauth2/v2/auth?" . http_build_query($params);
}

// 2. Lấy danh sách giảng viên từ cơ sở dữ liệu để phục vụ Chế độ Demo
$demo_lecturers = [];
if (ALLOW_DEMO) {
    try {
        $stmt = $db->query("SELECT id_giang_vien, ho_ten_gv, email FROM giang_vien ORDER BY ho_ten_gv ASC");
        $demo_lecturers = $stmt->fetchAll();
    } catch (PDOException $e) {
        // Nếu bảng chưa có dữ liệu, dùng danh sách tĩnh mặc định
        $demo_lecturers = [
            ['id_giang_vien' => 237, 'ho_ten_gv' => 'Phan Minh Thắng (Quản trị viên)', 'email' => 'thangpm@vlute.edu.vn']
        ];
    }
}

// 3. Xử lý Đăng nhập chế độ Demo (Developer Bypass)
$error_msg = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'demo_login') {
    if (!ALLOW_DEMO) {
        die("Chế độ đăng nhập Demo hiện đang bị tắt trên môi trường này.");
    }
    
    $selected_gv_id = intval($_POST['demo_giang_vien']);
    
    // Tìm thông tin GV
    $found_gv = null;
    foreach ($demo_lecturers as $gv) {
        if (intval($gv['id_giang_vien']) === $selected_gv_id) {
            $found_gv = $gv;
            break;
        }
    }
    
    if ($found_gv) {
        // Đảm bảo email demo luôn có đuôi @vlute.edu.vn
        $demo_email = $found_gv['email'];
        if (empty($demo_email) || substr(strtolower($demo_email), -13) !== '@vlute.edu.vn') {
            // Chuyển đổi tên sang không dấu để làm email mock
            $no_vietnamese = preg_replace('/[đĐ]/u', 'd', $found_gv['ho_ten_gv']);
            $normalized = iconv('UTF-8', 'ASCII//TRANSLIT', $no_vietnamese);
            $clean_name = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $normalized));
            $demo_email = (!empty($clean_name) ? $clean_name : 'giangvien') . '@vlute.edu.vn';
        }
        
        $_SESSION['id_giang_vien'] = $found_gv['id_giang_vien'];
        $_SESSION['ho_ten_gv']     = $found_gv['ho_ten_gv'];
        $_SESSION['email']          = $demo_email;
        $_SESSION['is_demo']        = true;
        
        header("Location: index.php");
        exit;
    } else {
        $error_msg = "Không tìm thấy giảng viên đã chọn!";
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Đăng nhập - Hệ thống Quản lý Thiết bị</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- Google Fonts Inter & Outfit -->
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
        }
        
        .login-card {
            background: rgba(255, 255, 255, 0.7);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.6);
            border-radius: 20px;
            max-width: 480px;
            width: 100%;
            padding: 40px 30px;
            box-shadow: 0 10px 30px rgba(0, 40, 80, 0.06);
            text-align: center;
            transform: translateY(0);
            transition: all 0.3s ease;
            color: #0f172a;
        }
        
        .login-card:hover {
            box-shadow: 0 15px 35px rgba(0, 86, 179, 0.12);
            border-color: rgba(0, 86, 179, 0.2);
        }

        .logo-container img {
            width: 90px;
            height: 90px;
            object-fit: cover;
            border-radius: 50%;
            border: 3px solid #0056b3;
            box-shadow: 0 4px 15px rgba(0, 86, 179, 0.15);
            margin-bottom: 20px;
        }

        .title-system {
            font-family: 'Outfit', sans-serif;
            font-size: 1.8rem;
            color: #0056b3;
            margin-bottom: 10px;
            font-weight: 700;
            letter-spacing: -0.5px;
            line-height: 1.3;
        }

        .subtitle-system {
            color: #475569;
            font-size: 0.95rem;
            margin-bottom: 35px;
            font-weight: 500;
        }

        .btn-google {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            background: #fff;
            color: #1f1f1f;
            border: 1px solid #cbd5e1;
            padding: 16px 24px;
            border-radius: 12px;
            font-size: 1.05rem;
            font-weight: 600;
            width: 100%;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            text-decoration: none;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
        }

        .btn-google:hover {
            background: #f8fafc;
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(0, 86, 179, 0.15);
            border-color: #0056b3;
        }

        .btn-google svg {
            width: 24px;
            height: 24px;
        }

        .divider {
            display: flex;
            align-items: center;
            text-align: center;
            color: #64748b;
            margin: 30px 0;
            font-size: 0.85rem;
            font-weight: 500;
        }

        .divider::before, .divider::after {
            content: '';
            flex: 1;
            border-bottom: 1px solid #cbd5e1;
        }

        .divider:not(:empty)::before {
            margin-right: .5em;
        }

        .divider:not(:empty)::after {
            margin-left: .5em;
        }

        .demo-box {
            background: rgba(0, 86, 179, 0.03);
            border: 1px solid rgba(0, 86, 179, 0.12);
            border-radius: 12px;
            padding: 20px;
            text-align: left;
        }

        .demo-box h3 {
            font-family: 'Outfit', sans-serif;
            color: #0056b3;
            font-size: 1.05rem;
            margin-top: 0;
            margin-bottom: 10px;
            font-weight: 700;
        }

        .demo-box select {
            width: 100%;
            padding: 12px;
            border-radius: 8px;
            border: 1px solid #cbd5e1;
            background: #fff;
            color: #0f172a;
            font-size: 0.95rem;
            margin-bottom: 12px;
            outline: none;
        }

        .demo-box select option {
            background: #fff;
            color: #0f172a;
        }

        .btn-demo {
            background: linear-gradient(135deg, #0056b3, #0077ee);
            color: #fff;
            border: none;
            padding: 12px;
            border-radius: 8px;
            width: 100%;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .btn-demo:hover {
            filter: brightness(1.05);
            box-shadow: 0 4px 12px rgba(0, 86, 179, 0.2);
        }

        .error-alert {
            background: rgba(239, 68, 68, 0.08);
            border: 1px solid rgba(239, 68, 68, 0.2);
            color: #b91c1c;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 0.9rem;
            font-weight: 500;
        }

        .oauth-warning {
            background: rgba(245, 158, 11, 0.08);
            border: 1px solid rgba(245, 158, 11, 0.25);
            color: #b45309;
            padding: 12px;
            border-radius: 8px;
            font-size: 0.85rem;
            margin-bottom: 20px;
            text-align: left;
            line-height: 1.4;
            font-weight: 500;
        }
    </style>
</head>
<body>

    <div class="login-card">
        <div class="logo-container">
            <img src="https://kyluc.vn/Userfiles/Upload/images/Download/2023/2/1/a86407f1e9c24fc486c9270169339758.jpg" alt="VLUTE Logo">
        </div>
        
        <h1 class="title-system">QUẢN LÝ THIẾT BỊ</h1>
        <p class="subtitle-system">BỘ MÔN Ô TÔ ĐIỆN (KHOA CKĐL) — VLUTE</p>

        <?php if (!empty($error_msg)): ?>
            <div class="error-alert">
                <?= htmlspecialchars($error_msg) ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($google_oauth_url)): ?>
            <!-- Nút đăng nhập Google chính thức -->
            <a href="<?= $google_oauth_url ?>" class="btn-google">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24">
                    <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                    <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                    <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.06H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.94l2.85-2.22c-.62-.62-1.07-1.37-1.22-2.63z"/>
                    <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.06l3.66 2.84c.87-2.6 3.3-4.52 6.16-4.52z"/>
                </svg>
                Đăng nhập bằng Google Workspace
            </a>
        <?php else: ?>
            <div class="oauth-warning">
                <strong>⚠️ Google OAuth chưa cấu hình:</strong><br>
                Hãy bổ sung <code>GOOGLE_CLIENT_ID</code> và <code>GOOGLE_CLIENT_SECRET</code> trong file <code>.env</code> để kích hoạt đăng nhập Google. Hiện tại bạn có thể dùng Chế độ Demo bên dưới.
            </div>
        <?php endif; ?>

        <?php if (ALLOW_DEMO): ?>
            <!-- Dòng phân cách -->
            <div class="divider">HOẶC DÙNG NGOẠI TUYẾN</div>

            <!-- Hộp đăng nhập Demo -->
            <div class="demo-box">
                <h3>Chế độ kiểm thử (Demo Mode)</h3>
                <form method="POST" action="login.php">
                    <input type="hidden" name="action" value="demo_login">
                    <label for="demo_giang_vien" style="display:none;">Chọn giảng viên:</label>
                    <select name="demo_giang_vien" id="demo_giang_vien">
                        <?php foreach ($demo_lecturers as $gv): ?>
                            <option value="<?= $gv['id_giang_vien'] ?>">
                                <?= htmlspecialchars($gv['ho_ten_gv']) ?> (ID: <?= $gv['id_giang_vien'] ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="btn-demo">🚀 Đăng nhập nhanh (Bypass OAuth)</button>
                </form>
            </div>
        <?php endif; ?>

    </div>

    <!-- Tự động phát hiện Zalo WebView để xử lý điều phối trình duyệt ngoài ngay từ màn hình đăng nhập -->
    <script>
        window.addEventListener("DOMContentLoaded", () => {
            const ua = navigator.userAgent || navigator.vendor || window.opera;
            const isZalo = ua.indexOf("Zalo") > -1 || ua.indexOf("ZaloWebView") > -1;
            const isAndroid = ua.toLowerCase().indexOf("android") > -1;
            
            if (isZalo) {
                if (isAndroid) {
                    // Tự động đóng Zalo WebView và ép hệ thống mở bằng Google Chrome/trình duyệt mặc định
                    const cleanUrl = window.location.href.replace("http://", "").replace("https://", "");
                    window.location.href = "intent://" + cleanUrl + "#Intent;scheme=https;end;";
                } else {
                    // Trên iOS, do Apple cấm mở Safari tự động, hiển thị Banner cảnh báo Glassmorphic hướng dẫn
                    const banner = document.getElementById("zaloWarningBanner");
                    if (banner) {
                        banner.style.display = "block";
                    }
                }
            }
        });
    </script>

</body>
</html>
