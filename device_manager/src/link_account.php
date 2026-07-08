<?php
/**
 * link_account.php
 * ================
 * Cho phép giảng viên liên kết tài khoản Google mới đăng nhập với hồ sơ của mình trong hệ thống.
 */
require_once 'config.php';

// Kiểm tra xem có email và sub đang chờ liên kết không
if (!isset($_SESSION['pending_google_email']) || !isset($_SESSION['pending_google_sub'])) {
    header("Location: login.php");
    exit;
}

$pending_email = $_SESSION['pending_google_email'];
$pending_sub   = $_SESSION['pending_google_sub'];

// Kiểm tra bổ sung đuôi email
if (substr(strtolower($pending_email), -13) !== '@vlute.edu.vn') {
    die("<div style='font-family:sans-serif; text-align:center; margin-top:50px; color:#ef4444;'>
            <h2>❌ LIÊN KẾT BỊ TỪ CHỐI</h2>
            <p>Chỉ tài khoản email trường VLUTE (<strong>@vlute.edu.vn</strong>) mới được phép liên kết tài khoản!</p>
            <a href='login.php' style='color:#0056b3; font-weight:bold; text-decoration:none;'>Quay lại trang Đăng nhập</a>
         </div>");
}

// 1. Lấy danh sách các giảng viên chưa được liên kết tài khoản Google
$available_lecturers = [];
try {
    $stmt = $db->query("SELECT id_giang_vien, ho_ten_gv, ten_don_vi FROM giang_vien WHERE google_sub IS NULL ORDER BY ho_ten_gv ASC");
    $available_lecturers = $stmt->fetchAll();
} catch (PDOException $e) {
    die("❌ Lỗi truy vấn cơ sở dữ liệu: " . $e->getMessage());
}

$success_msg = "";
$error_msg = "";

// 2. Xử lý biểu mẫu liên kết tài khoản
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'link') {
    $selected_gv_id = intval($_POST['giang_vien_id']);
    
    if ($selected_gv_id > 0) {
        try {
            // Thực hiện liên kết tài khoản trong DB
            $stmt = $db->prepare("UPDATE giang_vien SET email = :email, google_sub = :google_sub WHERE id_giang_vien = :id_giang_vien AND google_sub IS NULL");
            $result = $stmt->execute([
                'email'         => $pending_email,
                'google_sub'    => $pending_sub,
                'id_giang_vien' => $selected_gv_id
            ]);
            
            if ($stmt->rowCount() > 0) {
                // Lấy thông tin giảng viên vừa liên kết để lưu session
                $gv_stmt = $db->prepare("SELECT ho_ten_gv FROM giang_vien WHERE id_giang_vien = :id");
                $gv_stmt->execute(['id' => $selected_gv_id]);
                $gv = $gv_stmt->fetch();
                
                // Thiết lập session đăng nhập chính thức
                $_SESSION['id_giang_vien'] = $selected_gv_id;
                $_SESSION['ho_ten_gv']     = $gv['ho_ten_gv'];
                $_SESSION['email']          = $pending_email;
                $_SESSION['is_demo']        = false;
                
                // Xóa các biến tạm
                unset($_SESSION['pending_google_email']);
                unset($_SESSION['pending_google_sub']);
                
                redirect_after_login();
            } else {
                $error_msg = "Không thể thực hiện liên kết. Tài khoản này có thể đã được liên kết trước đó!";
            }
        } catch (PDOException $e) {
            $error_msg = "Lỗi cơ sở dữ liệu khi thực hiện liên kết: " . $e->getMessage();
        }
    } else {
        $error_msg = "Vui lòng chọn tên giảng viên của bạn!";
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Liên kết tài khoản - Quản lý Thiết bị</title>
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
        }

        .link-card {
            background: rgba(255, 255, 255, 0.7);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.6);
            border-radius: 20px;
            max-width: 520px;
            width: 100%;
            padding: 40px 30px;
            box-shadow: 0 10px 30px rgba(0, 40, 80, 0.06);
            color: #0f172a;
        }

        .logo-box {
            text-align: center;
            margin-bottom: 25px;
        }

        .logo-box img {
            width: 80px;
            height: 80px;
            object-fit: cover;
            border-radius: 50%;
            border: 2px solid #0056b3;
            box-shadow: 0 4px 15px rgba(0, 86, 179, 0.15);
        }

        .title-link {
            font-family: 'Outfit', sans-serif;
            font-size: 1.6rem;
            font-weight: 700;
            text-align: center;
            margin-bottom: 10px;
            color: #0056b3;
        }

        .subtitle-link {
            color: #475569;
            font-size: 0.95rem;
            text-align: center;
            margin-bottom: 30px;
            line-height: 1.5;
            font-weight: 500;
        }

        .user-badge {
            background: rgba(0, 86, 179, 0.03);
            border: 1px dashed rgba(0, 86, 179, 0.2);
            border-radius: 12px;
            padding: 15px;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .user-badge-icon {
            background: #0056b3;
            color: #fff;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            flex-shrink: 0;
        }

        .user-badge-info {
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .user-badge-info span {
            display: block;
            font-size: 0.8rem;
            color: #64748b;
            font-weight: 500;
        }

        .user-badge-info strong {
            font-size: 1.05rem;
            color: #0056b3;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            font-size: 0.95rem;
            color: #0f172a;
        }

        .form-group select {
            width: 100%;
            padding: 14px;
            border-radius: 10px;
            border: 1px solid #cbd5e1;
            background: #fff;
            color: #0f172a;
            font-size: 1rem;
            outline: none;
            transition: border-color 0.3s;
        }

        .form-group select:focus {
            border-color: #0056b3;
        }

        .form-group select option {
            background: #fff;
            color: #0f172a;
            padding: 10px;
        }

        .btn-submit {
            background: linear-gradient(135deg, #0056b3, #0077ee);
            color: #fff;
            border: none;
            padding: 15px;
            border-radius: 10px;
            width: 100%;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(0, 86, 179, 0.2);
            margin-top: 10px;
        }

        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0, 86, 179, 0.3);
        }

        .cancel-link {
            display: block;
            text-align: center;
            margin-top: 20px;
            color: #64748b;
            text-decoration: none;
            font-size: 0.9rem;
            transition: color 0.2s;
            font-weight: 500;
        }

        .cancel-link:hover {
            color: #ff4d4d;
        }

        .error-alert {
            background: rgba(239, 68, 68, 0.08);
            border: 1px solid rgba(239, 68, 68, 0.2);
            color: #b91c1c;
            padding: 12px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-size: 0.9rem;
            font-weight: 500;
        }
    </style>
</head>
<body>

    <div class="link-card">
        <div class="logo-box">
            <img src="https://kyluc.vn/Userfiles/Upload/images/Download/2023/2/1/a86407f1e9c24fc486c9270169339758.jpg" alt="VLUTE Logo">
        </div>

        <h1 class="title-link">Liên kết tài khoản giảng viên</h1>
        <p class="subtitle-link">Đây là lần đầu tiên bạn đăng nhập bằng email này. Vui lòng chọn tên giảng viên của bạn dưới đây để liên kết.</p>

        <?php if (!empty($error_msg)): ?>
            <div class="error-alert">
                <?= htmlspecialchars($error_msg) ?>
            </div>
        <?php endif; ?>

        <!-- Huy hiệu tài khoản Google -->
        <div class="user-badge">
            <div class="user-badge-icon">✉</div>
            <div class="user-badge-info">
                <span>Tài khoản Google đăng nhập:</span>
                <strong><?= htmlspecialchars($pending_email) ?></strong>
            </div>
        </div>

        <form method="POST" action="link_account.php">
            <input type="hidden" name="action" value="link">
            
            <div class="form-group">
                <label for="giang_vien_id">Họ và tên giảng viên (Khoa CKĐL):</label>
                <select name="giang_vien_id" id="giang_vien_id" required>
                    <option value="" disabled selected>-- Chọn tên giảng viên trong danh sách --</option>
                    <?php foreach ($available_lecturers as $gv): ?>
                        <option value="<?= $gv['id_giang_vien'] ?>">
                            <?= htmlspecialchars($gv['ho_ten_gv']) ?> (<?= htmlspecialchars($gv['ten_don_vi']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <button type="submit" class="btn-submit">🔗 LIÊN KẾT TÀI KHOẢN VÀ ĐĂNG NHẬP</button>
        </form>

        <a href="logout.php" class="cancel-link">❌ Hủy bỏ & Đăng xuất</a>
    </div>

</body>
</html>
