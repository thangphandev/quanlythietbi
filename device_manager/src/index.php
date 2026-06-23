<?php
/**
 * index.php
 * =========
 * Giao diện chính của Hệ thống Quản lý Thiết bị.
 * Thiết kế tông sáng học thuật (Light Classic Academic Glassmorphism).
 * Chia làm 2 luồng rõ ràng: Quét QR bằng Camera và Chọn thủ công có ô tìm kiếm.
 * Giỏ đồ hiển thị chi tiết ảnh, đếm số lượng, xem lịch sử sử dụng và đánh giá trực tiếp.
 */
require_once 'config.php';

// Bắt buộc đăng nhập
require_login();

$id_giang_vien = $_SESSION['id_giang_vien'];
$ho_ten_gv     = $_SESSION['ho_ten_gv'];
$email_gv      = $_SESSION['email'];

// ==============================================================================
// XỬ LÝ GỬI PHIẾU MƯỢN (SUBMIT FORM QUA AJAX)
// ==============================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'borrow') {
    header('Content-Type: application/json');
    
    $ten_lop = trim($_POST['tenLop'] ?: 'Nghiên cứu - khai thác');
    $selected_devices = $_POST['devices'] ?? []; // Array of device_id => quantity
    $conditions_data  = $_POST['conditions'] ?? []; // Array of device_id => [condition, detail]
    
    if (empty($selected_devices)) {
        echo json_encode(['error' => 'Vui lòng chọn ít nhất một thiết bị!']);
        exit;
    }
    
    try {
        $db->beginTransaction();
        
        $tinh_trang_chung_arr = [];
        $thiet_bi_text_arr = [];
        
        foreach ($selected_devices as $db_id => $qty) {
            $db_id = intval($db_id);
            $qty = intval($qty);
            
            // Lấy thông tin thiết bị
            $tb_stmt = $db->prepare("SELECT ten_thiet_bi FROM thiet_bi WHERE id = :id FOR UPDATE");
            $tb_stmt->execute(['id' => $db_id]);
            $device = $tb_stmt->fetch();
            
            if (!$device) {
                throw new Exception("Thiết bị ID $db_id không tồn tại!");
            }
            

            
            $thiet_bi_text_arr[] = "{$device['ten_thiet_bi']} (x{$qty})";
            
            // Xử lý tình trạng
            $cond = $conditions_data[$db_id]['condition'] ?? 'Tốt';
            $detail = trim($conditions_data[$db_id]['detail'] ?? '');
            
            $status_text = $cond;
            if (($cond === 'Hư hỏng' || $cond === 'Lỗi nhẹ') && !empty($detail)) {
                $status_text .= " ({$detail})";
            }
            $tinh_trang_chung_arr[] = "{$device['ten_thiet_bi']}: {$status_text}";
        }
        
        $tinh_trang_chung_text = implode(' | ', $tinh_trang_chung_arr) ?: 'Tốt';
        
        // 2. Thêm vào bảng phieu_muon
        $pm_stmt = $db->prepare("
            INSERT INTO phieu_muon (id_giang_vien, ten_lop, email_xac_nhan, tinh_trang_chung, trang_thai) 
            VALUES (:id_gv, :ten_lop, :email, :tinh_trang_chung, 'Đang mượn')
        ");
        $pm_stmt->execute([
            'id_gv'            => $id_giang_vien,
            'ten_lop'          => $ten_lop,
            'email'            => $email_gv,
            'tinh_trang_chung' => $tinh_trang_chung_text
        ]);
        
        $id_phieu_muon = $db->lastInsertId();
        
        // 3. Thêm chi tiết và cập nhật số lượng/chất lượng trong thiet_bi
        $ct_stmt = $db->prepare("
            INSERT INTO chi_tiet_phieu_muon (id_phieu_muon, id_thiet_bi, so_luong, tinh_trang, ghi_chu) 
            VALUES (:id_pm, :id_tb, :qty, :tinh_trang, :ghi_chu)
        ");
        
        $update_tb_stmt = $db->prepare("
            UPDATE thiet_bi 
            SET chat_luong = :chat_luong, 
                updated_at = NOW() 
            WHERE id = :id
        ");
        
        foreach ($selected_devices as $db_id => $qty) {
            $db_id = intval($db_id);
            $qty = intval($qty);
            
            $cond = $conditions_data[$db_id]['condition'] ?? 'Tốt';
            $detail = trim($conditions_data[$db_id]['detail'] ?? '');
            
            // Lưu chi tiết
            $ct_stmt->execute([
                'id_pm'      => $id_phieu_muon,
                'id_tb'      => $db_id,
                'qty'        => $qty,
                'tinh_trang' => $cond,
                'ghi_chu'    => $detail
            ]);
            
            // Xác định chuỗi chất lượng cập nhật
            $new_chat_luong = $cond;
            if (($cond === 'Hư hỏng' || $cond === 'Lỗi nhẹ') && !empty($detail)) {
                $new_chat_luong = "$cond: " . $detail;
            }
            
            // Cập nhật thiết bị
            $update_tb_stmt->execute([
                'chat_luong'  => $new_chat_luong,
                'id'          => $db_id
            ]);
        }
        
        $db->commit();
        
        $devices_msg = implode('<br>🔹 ', $thiet_bi_text_arr);
        echo json_encode([
            'success' => true,
            'msg' => "✅ Ghi phiếu thành công!<br><br><strong>Giáo viên:</strong> $ho_ten_gv<br><strong>Lớp:</strong> $ten_lop<br><strong>Thiết bị:</strong><br>🔹 $devices_msg"
        ]);
        exit;
    } catch (Exception $e) {
        $db->rollBack();
        echo json_encode(['error' => 'Thất bại: ' . $e->getMessage()]);
        exit;
    }
}

// ==============================================================================
// 1. NHẬN DIỆN LỚP HỌC HIỆN TẠI THEO THỜI KHÓA BIỂU
// ==============================================================================
$ma_lop_hp = "";
$ten_hoc_phan = "";
$class_badge_html = "";

try {
    // Xác định ngày truy vấn TKB
    $query_date_expr = "(CURRENT_TIMESTAMP AT TIME ZONE 'Asia/Ho_Chi_Minh')::date";
    $is_demo_active = false;
    
    // Nếu đang chạy tài khoản Demo và hôm nay không có lịch dạy thực tế nào,
    // ta tự động kích hoạt ngày giả định có lịch gần nhất để mô phỏng TKB sinh động cho người dùng kiểm thử!
    if (isset($_SESSION['is_demo']) && $_SESSION['is_demo'] === true) {
        // Kiểm tra nhanh xem hôm nay có lịch thật không
        $check_stmt = $db->prepare("
            SELECT 1 FROM lich_giang_day lgd
            JOIN lich_giang_day_chi_tiet lgdct ON lgd.id = lgdct.id_lich_giang_day
            WHERE lgd.id_giang_vien = :gv_id
              AND lgdct.ngay_day = (CURRENT_TIMESTAMP AT TIME ZONE 'Asia/Ho_Chi_Minh')::date
            LIMIT 1
        ");
        $check_stmt->execute(['gv_id' => $id_giang_vien]);
        if (!$check_stmt->fetch()) {
            // Tìm ngày dạy gần đây nhất có lịch của giảng viên này để mô phỏng TKB sinh động
            $demo_date_stmt = $db->prepare("
                SELECT lgdct.ngay_day 
                FROM lich_giang_day lgd
                JOIN lich_giang_day_chi_tiet lgdct ON lgd.id = lgdct.id_lich_giang_day
                WHERE lgd.id_giang_vien = :gv_id
                ORDER BY lgdct.ngay_day DESC
                LIMIT 1
            ");
            $demo_date_stmt->execute(['gv_id' => $id_giang_vien]);
            $demo_date_row = $demo_date_stmt->fetch();
            if ($demo_date_row) {
                $query_date_expr = "'" . $demo_date_row['ngay_day'] . "'::date";
            } else {
                $query_date_expr = "'2026-07-15'::date";
            }
            $is_demo_active = true;
        }
    }

    // 1. Lấy tất cả lịch dạy của giảng viên trong ngày hôm nay (múi giờ GMT+7)
    $t_stmt = $db->prepare("
        SELECT lhp.ma_lop_hp, lhp.ten_hoc_phan, lgd.tg_bat_dau, lgd.tg_ket_thuc, lgd.tiet_bd, lgd.tiet_kt, lgd.ten_phong
        FROM lich_giang_day lgd
        JOIN lich_giang_day_chi_tiet lgdct ON lgd.id = lgdct.id_lich_giang_day
        JOIN lop_hoc_phan lhp ON lgd.id_lop_hoc_phan = lhp.id_lop_hoc_phan
        WHERE lgd.id_giang_vien = :gv_id
          AND lgdct.ngay_day = $query_date_expr
        ORDER BY lgd.tg_bat_dau ASC
    ");
    $t_stmt->execute(['gv_id' => $id_giang_vien]);
    $todays_classes = $t_stmt->fetchAll();
    
    $selected_class = null;
    $status_label = "";
    
    if (!empty($todays_classes)) {
        // Lấy giờ hiện tại ở Việt Nam (định dạng HH:MM:SS)
        $tz = new DateTimeZone('Asia/Ho_Chi_Minh');
        $now_dt = new DateTime('now', $tz);
        $current_time = $now_dt->format('H:i:s');
        
        // Tìm lớp đang diễn ra trực tiếp theo thời gian buổi học
        foreach ($todays_classes as $c) {
            if ($current_time >= $c['tg_bat_dau'] && $current_time <= $c['tg_ket_thuc']) {
                $selected_class = $c;
                break;
            }
        }
    }
    
    if ($selected_class) {
        $ma_lop_hp = $selected_class['ma_lop_hp'];
        $ten_hoc_phan = $selected_class['ten_hoc_phan'];
        $tiet_str = "Tiết {$selected_class['tiet_bd']}-{$selected_class['tiet_kt']}";
        $phong_str = $selected_class['ten_phong'] ? " · Phòng {$selected_class['ten_phong']}" : "";
        $class_badge_html = '<span class="class-badge" style="background:rgba(16,185,129,0.08); border-color:rgba(16,185,129,0.25); color:#059669; font-weight:600;">' 
            . htmlspecialchars($ten_hoc_phan) . ' (' . htmlspecialchars($ma_lop_hp) . ') · ' 
            . htmlspecialchars($tiet_str) . htmlspecialchars($phong_str) 
            . '</span>';
    } else {
        $ma_lop_hp = "Nghiên cứu - khai thác";
        if (!empty($todays_classes)) {
            $class_badge_html = '<span class="class-badge" style="background:rgba(245,158,11,0.08); border-color:rgba(245,158,11,0.25); color:#b45309;">🔍 Không có lịch dạy ở thời điểm hiện tại</span>';
        } else {
            $class_badge_html = '<span class="class-badge" style="background:rgba(245,158,11,0.08); border-color:rgba(245,158,11,0.25); color:#b45309;">🔍 Không có lịch dạy hôm nay</span>';
        }
    }
} catch (PDOException $e) {
    $ma_lop_hp = "Nghiên cứu - khai thác";
    $class_badge_html = '<span class="class-badge" style="background:rgba(239,68,68,0.08); border-color:rgba(239,68,68,0.2); color:#b91c1c;">⚠️ Lỗi truy vấn thời khóa biểu</span>';
}

// ==============================================================================
// 2. LẤY DANH SÁCH THIẾT BỊ TỪ CSDL
// ==============================================================================
$devices = [];
$categories = [];
try {
    $stmt = $db->query("
        SELECT tb.*, gv.ho_ten_gv AS ten_gv_quan_ly, l.ten_loai, l.ma_mau 
        FROM thiet_bi tb 
        LEFT JOIN giang_vien gv ON tb.id_giang_vien_quan_ly = gv.id_giang_vien 
        LEFT JOIN loai l ON tb.id_loai = l.id_loai
        ORDER BY tb.ten_thiet_bi ASC
    ");
    $devices = $stmt->fetchAll();
    
    // Lấy danh sách phân loại
    $categories = $db->query("SELECT * FROM loai ORDER BY ten_loai ASC")->fetchAll();
} catch (PDOException $e) {
    die("Lỗi lấy danh sách thiết bị: " . $e->getMessage());
}

$scan_ma_thiet_bi = $_GET['scan'] ?? '';
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Hệ thống Quản lý Thiết bị - VLUTE</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Outfit:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
    <link rel="stylesheet" href="style.css?v=<?= filemtime('style.css') ?>">
    <style>
        .cl-item-damaged {
            background-color: rgba(239, 68, 68, 0.04) !important;
            border-left: 4px solid #ef4444 !important;
        }
        .cl-status-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 22px;
            height: 22px;
            border-radius: 50%;
            font-size: 0.77rem;
            user-select: none;
            transition: all 0.2s ease;
        }
        .cl-status-badge:hover {
            transform: scale(1.18);
        }
        .badge-status-good {
            background-color: rgba(16, 231, 70, 0.91);
            color: #10b981;
        }
        .badge-status-warning {
            background-color: rgba(245, 158, 11, 0.15);
            color: #d97706;
        }
        .badge-status-bad {
            background-color: rgba(239, 68, 68, 0.1);
            color: #ef4444;
        }
    </style>
</head>
<body>

    <!-- Header Section -->
    <header>
        <div class="header-content">
            <h1>HỆ THỐNG QUẢN LÝ THIẾT BỊ</h1>
            <div class="user-info-bar">
                <span>GV: <strong><?= htmlspecialchars($ho_ten_gv) ?></strong></span>
                <span>Email: <strong><?= htmlspecialchars($email_gv) ?></strong></span>
                <a href="logout.php">🚪 Đăng xuất</a>
            </div>
        </div>
    </header>

    <!-- Main Container -->
    <div class="container">
        
        <!-- Two Column Dashboard Layout -->
        <div class="two-column-layout">
            
            <!-- CỘT BÊN TRÁI: PHƯƠNG THỨC CHỌN THIẾT BỊ -->
            <div class="column-left admin-card" style="padding:20px; background:rgba(255,255,255,0.75);">
                <!-- Bộ chọn Chế độ mượn Premium -->
                <div class="borrow-mode-selector">
                    <div class="mode-card active" id="cardQRMode" onclick="switchBorrowMode('qr-method', this)">
                        <span class="mode-card-icon">📷</span>
                        <span class="mode-card-title">QUÉT MÃ QR</span>
                        <span class="mode-card-desc">Sử dụng camera quét QR trên thiết bị</span>
                    </div>
                    <div class="mode-card" id="cardManualMode" onclick="switchBorrowMode('manual-method', this)">
                        <span class="mode-card-icon">📋</span>
                        <span class="mode-card-title">CHỌN THỦ CÔNG</span>
                        <span class="mode-card-desc">Tìm kiếm và chọn thiết bị từ kho</span>
                    </div>
                </div>

                <!-- Phương pháp 1: Quét mã QR bằng Camera -->
                <div class="borrow-tab-content active" id="qr-method">
                    <div style="text-align:center; padding:15px 10px;">
                        <p style="color:var(--text-secondary); margin-bottom:20px; font-size:0.95rem; line-height:1.4;">
                            Đưa mã QR được dán trên thiết bị vào khung camera quét
                        </p>                        
                        <!-- Camera Container -->
                        <div id="reader" style="width: 100%; max-width: 420px; margin: 15px auto; display: none; border-radius: 16px; overflow: hidden; border: 2px solid var(--accent-blue);"></div>
                        <div class="qr-trigger-container">
                            <button type="button" class="btn-qr-scan" id="btnQRScan" style="max-width:280px; font-size:1rem; padding:12px 20px;">
                                📷 MỞ CAMERA QUÉT QR
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Phương pháp 2: Chọn thủ công từ kho (Có tìm kiếm & lọc loại) -->
                <div class="borrow-tab-content" id="manual-method">
                    <div class="search-container" style="display:flex; gap:10px; margin-bottom:12px;">
                        <input type="text" id="searchManual" class="search-input" placeholder="🔍 Tên hoặc mã thiết bị..." style="flex:2;">
                        <select id="searchCategory" class="search-input" style="flex:1; padding:0 8px; font-weight:500; color:var(--text-secondary);">
                            <option value="">Tất cả loại</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= htmlspecialchars(strtolower($cat['ten_loai'])) ?>"><?= htmlspecialchars($cat['ten_loai']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="cl-list" id="manualList">
                        <?php foreach ($devices as $d):
                            $status_icon = '✔️';
                            $status_cls = 'badge-status-good';
                            $item_damaged_cls = '';
                            $cl = strtolower($d['chat_luong']);
                            if (str_contains($cl, 'hư hỏng') || str_contains($cl, 'hỏng')) {
                                $status_icon = '❌';
                                $status_cls = 'badge-status-bad';
                                $item_damaged_cls = 'cl-item-damaged';
                            } elseif (str_contains($cl, 'lỗi') || str_contains($cl, 'yếu') || str_contains($cl, 'trì') || str_contains($cl, 'cảnh báo')) {
                                $status_icon = '⚠️';
                                $status_cls = 'badge-status-warning';
                            }
                        ?>
                        <div class="cl-item <?= $item_damaged_cls ?>" style="--cat-color: <?= htmlspecialchars($d['ma_mau'] ?: '#0284c7') ?>;" data-id="<?= $d['id'] ?>" data-ma="<?= htmlspecialchars(strtolower($d['ma_thiet_bi'])) ?>" data-ten="<?= htmlspecialchars(strtolower($d['ten_thiet_bi'])) ?>" data-category="<?= htmlspecialchars(strtolower($d['ten_loai'] ?: '')) ?>">
                            <?php if (!empty($d['hinh_anh'])): 
                                $thumb_file = 'uploads/thumb_' . $d['hinh_anh'];
                                $img_src = file_exists($thumb_file) ? $thumb_file : 'uploads/' . $d['hinh_anh'];
                            ?>
                                <img src="<?= htmlspecialchars($img_src) ?>" data-zoom="uploads/<?= htmlspecialchars($d['hinh_anh']) ?>" class="cl-thumb zoomable-thumb" alt="Device thumbnail">
                            <?php else: ?>
                                <div class="cl-thumb cl-no-img">📦</div>
                            <?php endif; ?>
                            <div class="cl-info" style="flex:1; min-width:0;">
                                <div class="cl-name-row" style="display:flex; align-items:center; justify-content:space-between; gap:4px; min-width:0; width:100%;">
                                    <span class="cl-name" id="name-<?= $d['id'] ?>" style="font-weight:600; color:var(--text-primary); font-size:0.95rem; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; flex:1; min-width:0; transition:all 0.2s;" title="<?= htmlspecialchars($d['ten_thiet_bi']) ?>">
                                        <?= htmlspecialchars($d['ten_thiet_bi']) ?>
                                    </span>
                                    <button type="button" class="cl-name-toggle" onclick="event.stopPropagation(); toggleDeviceName(<?= $d['id'] ?>)" style="background:rgba(2,132,199,0.06); border:none; color:var(--accent-blue); cursor:pointer; font-weight:bold; font-size:0.95rem; padding:0; border-radius:50%; display:none; align-items:center; justify-content:center; transition:all 0.2s ease; outline:none; height:18px; width:18px; flex-shrink:0; margin-left:6px; line-height:1;" id="toggle-<?= $d['id'] ?>">&rsaquo;</button>
                                </div>
                                <div class="cl-meta">
                                    <code class="cl-code"><?= htmlspecialchars($d['ma_thiet_bi']) ?></code>
                                    <span class="cl-right-meta">
                                        <span class="cl-year"><?= htmlspecialchars($d['nam_su_dung']) ?></span>
                                        <span class="cl-status-badge <?= $status_cls ?>" onclick="event.stopPropagation(); showNotification('Trạng thái: <strong><?= htmlspecialchars($d['chat_luong']) ?></strong>', '<?= $status_icon ?>')" title="Bấm xem trạng thái"><?= $status_icon ?></span>
                                    </span>
                                </div>
                            </div>
                            <div class="cl-actions" style="display:flex; flex-direction:column; gap:6px; flex-shrink:0;">
                                <button type="button" class="btn-mdi btn-mdi-detail" onclick="event.stopPropagation(); openDeviceModalByCode('<?= htmlspecialchars($d['ma_thiet_bi']) ?>')" title="Xem chi tiết" style="font-size:1.15rem; font-weight:bold;">ⓘ</button>
                                <button type="button" class="btn-mdi btn-mdi-add" onclick="event.stopPropagation(); addDeviceToCart(<?= $d['id'] ?>)" title="Mượn thiết bị">+</button>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

            </div>
            
        </div>

    </div>

    <!-- NÚT GIỎ HÀNG NỔI (FLOATING CART TRIGGER) - Đặt ở Body cấp cao nhất tránh lỗi Stacking Context của Backdrop-Filter -->
    <div class="floating-cart-trigger" id="floatingCartTrigger" onclick="openCartDrawer()">
        🛒
        <span class="floating-cart-badge" id="cartBadgeCount">0</span>
    </div>

    <!-- OVERLAY VÀ DRAWER GIỎ HÀNG -->
    <div class="cart-drawer-overlay" id="cartDrawerOverlay" onclick="closeCartDrawer()"></div>
    <div class="cart-drawer" id="cartDrawer">
        <div class="cart-drawer-header">
            <h3 class="cart-drawer-title">🛒 THIẾT BỊ ĐÃ CHỌN</h3>
            <div class="cart-drawer-close" onclick="closeCartDrawer()">&times;</div>
        </div>
        <div class="cart-drawer-body">
            <!-- Form mượn thiết bị nằm trọn vẹn trong Drawer để tối ưu di động -->
            <form id="deviceForm">
                <label style="margin-top:0;"><span style="color: red;">*</span>Tên giảng viên sử dụng:</label>
                <input type="text" id="tenGV" value="<?= htmlspecialchars($ho_ten_gv) ?>" readonly required style="height:48px; font-size:0.98rem; padding:5px 5px;">

                <div class="input-badge-container" style="margin-top:3px;">
                    <label><span style="color: red;">*</span>Mã lớp / Mục đích học tập:</label>
                    <?= $class_badge_html ?>
                    <input type="text" id="tenLop" value="<?= htmlspecialchars($ma_lop_hp) ?>" placeholder="Nhập lớp học hoặc Nghiên cứu - khai thác..." required style="height:48px; font-size:0.98rem; padding:5px 5px; margin-top:5px;">
                </div>

                <label style="margin-top: 5px; border-top: 1px dashed #cbd5e1; padding-top:5px;">Chi tiết thiết bị & Đánh giá chất lượng:</label>
                
                <!-- Giỏ chứa thiết bị đã chọn sẽ hiển thị linh động qua JS -->
                <div id="cartContainer" style="margin-top:10px; min-height:120px; display:flex; flex-direction:column; gap:12px;">
                    <p style="text-align:center; color:var(--text-muted); padding:10px 10px; font-style:italic;">Hãy quét mã QR hoặc chọn thủ công bên trái.</p>
                </div>

                <button type="submit" id="btnSubmit" style="padding:15px 20px; font-size:1.1rem; border-radius:12px; margin-top:25px;">📤 GỬI XÁC NHẬN SỬ DỤNG</button>
            </form>
        </div>
    </div>

    <!-- OVERLAY PHÓNG TO HÌNH ẢNH (LIGHTBOX) -->
    <div class="image-zoom-overlay" id="imageZoomOverlay" onclick="closeImageZoom()">
        <div class="image-zoom-close" onclick="event.stopPropagation(); closeImageZoom()">&times;</div>
        <img class="image-zoom-content" id="imageZoomContent" src="" alt="Zoomed Image" onclick="event.stopPropagation()">
        <!-- Thanh điều khiển zoom -->
        <div class="image-zoom-controls" onclick="event.stopPropagation()">
            <div class="image-zoom-btn" onclick="adjustImageZoom(-0.25)" title="Thu nhỏ">−</div>
            <span class="image-zoom-level" id="zoomLevelLabel">100%</span>
            <div class="image-zoom-btn" onclick="adjustImageZoom(0.25)" title="Phóng to">+</div>
            <div class="image-zoom-btn" style="font-size:0.75rem; width:38px; border-radius:10px;" onclick="resetImageZoom()" title="Đặt lại">1:1</div>
        </div>
    </div>

    <!-- Footer Section -->
    <footer style="padding: 15px 10px; font-size: 0.8rem; line-height: 1.4; color: var(--text-muted); text-align: center;">
        <p style="margin: 2px 0;"><strong>Hỗ trợ kỹ thuật:</strong> Phan Minh Thắng · SĐT: 0834 029 049 · Email: <a href="mailto:thangpm@vlute.edu.vn" style="color: var(--accent-blue);">thangpm@vlute.edu.vn</a></p>
        <p style="margin: 2px 0;">Hệ thống Quản lý Thiết bị v3.0.0</p>
        <p style="margin: 6px 0 0 0;"><a href="admin.php" style="font-weight: 600; text-decoration: none; color: var(--accent-blue);">⚙ VÀO TRANG QUẢN TRỊ (ADMIN PANEL)</a></p>
    </footer>

    <!-- ==============================================================================
         MODAL HIỂN THỊ THÔNG TIN CHI TIẾT THIẾT BỊ & LỊCH SỬ SỬ DỤNG TRƯỚC ĐÓ
         ============================================================================== -->
    <div class="modal" id="deviceModal">
        <div class="modal-overlay" id="modalOverlay"></div>
        <div class="modal-content" style="max-width:550px; padding:25px;">
            <button type="button" class="modal-close" id="modalClose">&times;</button>
            <h2 class="modal-title" id="m_ten_thiet_bi" style="font-size:1.35rem; margin-bottom:15px;">Tên thiết bị</h2>
            
            <!-- Ảnh thiết bị hiển thị sắc nét (Bấm vào để xem lớn full kích thước) -->
            <div style="text-align:center; margin-bottom: 3px;">
                <img id="m_hinh_anh" src="" class="zoomable-thumb" style="width:100%; max-height:220px; border-radius:12px; box-shadow:0 4px 15px rgba(0,0,0,0.08); border:1px solid #cbd5e1; display:none; object-fit:contain; background:#f8fafc; cursor:zoom-in; transition: transform 0.2s ease;">
            </div>
            
            <div class="info-grid" style="margin-bottom:5px; gap:10px;">
                <div class="info-item" style="padding:8px 5px;">
                    <span>Mã thiết bị</span>
                    <span id="m_ma_thiet_bi">TB000</span>
                </div>
                <div class="info-item" style="padding:8px 5px;">
                    <span>Phân loại</span>
                    <span id="m_loai">Chưa phân loại</span>
                </div>
                <div class="info-item" style="padding:8px 5px;">
                    <span>Vị trí đặt</span>
                    <span id="m_vi_tri">Phòng xưởng</span>
                </div>
                <div class="info-item" style="padding:8px 5px;">
                    <span>Năm đưa vào SD</span>
                    <span id="m_nam_su_dung">2023</span>
                </div>
                <div class="info-item" style="padding:8px 12px; grid-column: span 2;">
                    <span>Giảng viên phụ trách quản lý & Liên hệ</span>
                    <span id="m_gv_quan_ly" style="font-weight: 600; color: var(--accent-blue);">Chưa phân công</span>
                </div>
                <div class="info-item" style="padding:8px 12px; grid-column: span 2;">
                    <span>Tình trạng chất lượng hiện tại</span>
                    <strong id="m_chat_luong" style="font-size: 0.98rem; font-weight: 650; color: var(--success-green);">Tốt</strong>
                </div>
                
                <!-- Thư mục tài liệu (Google Drive) -->
                <div class="info-item" id="m_tai_lieu_container" style="padding:8px 12px; grid-column: span 2; display:none;">
                    <span>Thư mục giáo trình / tài liệu liên quan</span>
                    <a id="m_tai_lieu_link" href="" target="_blank" style="font-weight:600; color:#db2777; text-decoration:none; display:inline-flex; align-items:center; gap:6px;">
                        📁 Mở thư mục Google Drive tài liệu
                    </a>
                </div>
            </div>

            <!-- CỘT MỚI: LỊCH SỬ SỬ DỤNG TRƯỚC ĐÓ -->
            <h3 class="history-title">🕰 Lịch sử sử dụng gần nhất</h3>
            <div class="history-list" id="m_history_list">
                <!-- Sẽ chèn động qua JS -->
            </div>
        </div>
    </div>

    <!-- OVERLAY THÀNH CÔNG VỚI HIỆU ỨNG CHECKMARK HOẠT HỌA -->
    <div class="success-overlay" id="successOverlay">
        <div class="checkmark-circle">
            <svg viewBox="0 0 52 52">
                <path d="M14 27l8 8 16-16"/>
            </svg>
        </div>
        <div class="success-msg">
            <h2>Gửi xác nhận thành công!</h2>
            <p id="successText">Phiếu mượn thiết bị đã được lưu trữ cục bộ thành công.</p>
            <button type="button" class="btn-success-close" id="btnSuccessClose">👌 ĐỒNG Ý</button>
        </div>
    </div>

    <!-- FLOATING NOTIFICATION -->
    <div class="floating-notif" id="floatingNotif">
        <span class="floating-notif-icon">🔔</span>
        <span id="floatingNotifText">Thông báo nổi</span>
    </div>

    <!-- JS LOGIC CHO TRANG INDEX -->
    <script>
        // Can thiệp đồng bộ vào quá trình tạo thẻ video để chèn ngay lập tức các thuộc tính phát trực tiếp (playsinline) 
        // Điều này đặc biệt quan trọng đối với WebView iOS của Zalo/Facebook khi gọi camera quét mã
        const originalCreateElement = document.createElement;
        document.createElement = function(tagName, options) {
            const element = originalCreateElement.call(document, tagName, options);
            if (tagName && tagName.toLowerCase() === 'video') {
                element.setAttribute("playsinline", "true");
                element.setAttribute("webkit-playsinline", "true");
                element.setAttribute("playsInline", "true");
                element.playsInline = true;
                element.setAttribute("autoplay", "true");
                element.setAttribute("muted", "true");
            }
            return element;
        };

        const allDevices = <?= json_encode($devices) ?>;
        
        // Cart State: Lưu trữ các thiết bị đã chọn
        // Cấu trúc: { [device_id]: { quantity: X, condition: 'Tốt', detail: '' } }
        let cart = {};

        // Tự động phát hiện Zalo WebView để xử lý điều phối trình duyệt
        window.addEventListener("DOMContentLoaded", () => {
            const ua = navigator.userAgent || navigator.vendor || window.opera;
            const isZalo = ua.indexOf("Zalo") > -1 || ua.indexOf("ZaloWebView") > -1;
            const isAndroid = ua.toLowerCase().indexOf("android") > -1;
            
            if (isZalo) {
                if (isAndroid) {
                    // Cú pháp Intent chuyển hướng bắt buộc trên Android: Tự động đóng Zalo WebView và mở bằng Chrome/Trình duyệt hệ thống
                    const cleanUrl = window.location.href.replace("http://", "").replace("https://", "");
                    window.location.href = "intent://" + cleanUrl + "#Intent;scheme=https;end;";
                } else {
                    // Đối với iOS, do Apple chặn mọi lệnh ép mở ứng dụng ngoài từ WebView bên thứ ba, hiển thị banner hướng dẫn
                    const banner = document.getElementById("zaloWarningBanner");
                    if (banner) {
                        banner.style.display = "block";
                    }
                }
            }
        });

        // ==============================================================================
        // MOBILE-FIRST INTERFACES (DRAWER & LIGHTBOX ZOOM)
        // ==============================================================================
        // Flag to prevent double popstate/history back calls
        let isViewClosing = false;

        // Hàm cập nhật trạng thái khóa cuộn trang chính khi có bất kỳ modal/drawer nào mở đè lên
        function updateBodyScrollLock() {
            const isAnyActive = 
                (document.getElementById("cartDrawer") && document.getElementById("cartDrawer").classList.contains("active")) ||
                (document.getElementById("deviceModal") && document.getElementById("deviceModal").classList.contains("active")) ||
                (document.getElementById("imageZoomOverlay") && document.getElementById("imageZoomOverlay").classList.contains("active")) ||
                (document.getElementById("successOverlay") && document.getElementById("successOverlay").classList.contains("active"));
                
            if (isAnyActive) {
                document.documentElement.classList.add("body-scroll-lock");
                document.body.classList.add("body-scroll-lock");
            } else {
                document.documentElement.classList.remove("body-scroll-lock");
                document.body.classList.remove("body-scroll-lock");
            }
        }

        function openCartDrawer() {
            document.getElementById("cartDrawer").classList.add("active");
            document.getElementById("cartDrawerOverlay").classList.add("active");
            updateBodyScrollLock();
            history.pushState({ view: "cart" }, "");
        }

        function closeCartDrawer(isPopstate = false) {
            const drawer = document.getElementById("cartDrawer");
            if (drawer && drawer.classList.contains("active")) {
                drawer.classList.remove("active");
                document.getElementById("cartDrawerOverlay").classList.remove("active");
                updateBodyScrollLock();
                if (!isPopstate) {
                    isViewClosing = true;
                    history.back();
                }
            }
        }

        // ==============================================================================
        // LIGHTBOX PHÓNG TO ẢNH + ZOOM (WHEEL & PINCH)
        // ==============================================================================
        let _lbScale = 1;
        const LB_MIN = 0.25, LB_MAX = 5;

        function _applyLbZoom() {
            const img = document.getElementById("imageZoomContent");
            const label = document.getElementById("zoomLevelLabel");
            if (img) {
                img.style.transform = `scale(${_lbScale})`;
                img.style.transformOrigin = 'center center';
                img.style.transition = 'transform 0.15s ease';
            }
            if (label) label.textContent = Math.round(_lbScale * 100) + '%';
        }

        function adjustImageZoom(delta) {
            _lbScale = Math.min(LB_MAX, Math.max(LB_MIN, _lbScale + delta));
            _applyLbZoom();
        }

        function resetImageZoom() {
            _lbScale = 1;
            _applyLbZoom();
        }

        function zoomImage(src) {
            const overlay = document.getElementById("imageZoomOverlay");
            const content = document.getElementById("imageZoomContent");
            _lbScale = 1;
            content.src = src;
            content.style.transform = '';
            content.style.transition = '';
            if (document.getElementById("zoomLevelLabel")) document.getElementById("zoomLevelLabel").textContent = '100%';
            overlay.classList.add("active");
            updateBodyScrollLock();
            history.pushState({ view: "zoom" }, "");
        }

        function closeImageZoom(isPopstate = false) {
            const overlay = document.getElementById("imageZoomOverlay");
            if (overlay && overlay.classList.contains("active")) {
                overlay.classList.remove("active");
                _lbScale = 1;
                updateBodyScrollLock();
                if (!isPopstate) {
                    isViewClosing = true;
                    history.back();
                }
            }
        }

        // Mouse wheel zoom trong lightbox
        document.getElementById("imageZoomOverlay").addEventListener("wheel", function(e) {
            if (!this.classList.contains("active")) return;
            e.preventDefault();
            const delta = e.deltaY < 0 ? 0.15 : -0.15;
            adjustImageZoom(delta);
        }, { passive: false });

        // Pinch-to-zoom trên mobile
        (function() {
            let initDist = 0, initScale = 1;
            const overlay = document.getElementById("imageZoomOverlay");
            overlay.addEventListener("touchstart", function(e) {
                if (e.touches.length === 2) {
                    initDist = Math.hypot(
                        e.touches[0].clientX - e.touches[1].clientX,
                        e.touches[0].clientY - e.touches[1].clientY
                    );
                    initScale = _lbScale;
                }
            }, { passive: true });
            overlay.addEventListener("touchmove", function(e) {
                if (e.touches.length === 2) {
                    e.preventDefault();
                    const dist = Math.hypot(
                        e.touches[0].clientX - e.touches[1].clientX,
                        e.touches[0].clientY - e.touches[1].clientY
                    );
                    _lbScale = Math.min(LB_MAX, Math.max(LB_MIN, initScale * (dist / initDist)));
                    _applyLbZoom();
                }
            }, { passive: false });
        })();


        function closeDeviceModal(isPopstate = false) {
            const modal = document.getElementById("deviceModal");
            if (modal && modal.classList.contains("active")) {
                modal.classList.remove("active");
                updateBodyScrollLock();
                if (!isPopstate) {
                    isViewClosing = true;
                    history.back();
                }
            }
        }

        // Close Lightbox on Escape key
        window.addEventListener("keydown", function(e) {
            if (e.key === "Escape") {
                closeImageZoom();
                closeCartDrawer();
                closeDeviceModal();
            }
        });

        // Intercept native Back button on mobile devices (Single Page App UX)
        window.addEventListener("popstate", function(event) {
            if (isViewClosing) {
                isViewClosing = false;
                return;
            }
            
            // Close any open views
            const overlay = document.getElementById("imageZoomOverlay");
            if (overlay && overlay.classList.contains("active")) {
                closeImageZoom(true);
                return;
            }
            
            const modal = document.getElementById("deviceModal");
            if (modal && modal.classList.contains("active")) {
                closeDeviceModal(true);
                return;
            }
            
            const drawer = document.getElementById("cartDrawer");
            if (drawer && drawer.classList.contains("active")) {
                closeCartDrawer(true);
                return;
            }
        });

        // Global click listener for zoomable thumbnails (delegation)
        document.addEventListener("click", function(e) {
            const thumb = e.target.closest(".zoomable-thumb");
            if (thumb && thumb.tagName === "IMG") {
                e.stopPropagation();
                const zoomSrc = thumb.dataset.zoom || thumb.src;
                if (zoomSrc) {
                    zoomImage(zoomSrc);
                }
            }
        });

        // ==============================================================================
        // TABS VÀ TÌM KIẾM
        // ==============================================================================
        // ==============================================================================
        // BỘ CHỌN CHẾ ĐỘ MƯỢN (MODE SWITCHER)
        // ==============================================================================
        function switchBorrowMode(methodId, card) {
            document.querySelectorAll(".borrow-tab-content").forEach(tab => {
                tab.classList.remove("active");
            });
            document.querySelectorAll(".mode-card").forEach(c => {
                c.classList.remove("active");
            });
            
            document.getElementById(methodId).classList.add("active");
            card.classList.add("active");
            
            // Dừng camera nếu chuyển tab
            if (methodId !== 'qr-method') {
                stopScanner();
            }
            
            // Tự động kiểm tra hiển thị nút toggle tên dài khi chuyển sang tab chọn thủ công
            if (methodId === 'manual-method') {
                setTimeout(updateNameToggles, 100);
            }
        }

        // Tìm kiếm và lọc theo phân loại thủ công
        function filterManualList() {
            const query = (document.getElementById("searchManual")?.value || "").toLowerCase().trim();
            const categoryQ = (document.getElementById("searchCategory")?.value || "").toLowerCase().trim();
            
            document.querySelectorAll("#manualList .cl-item").forEach(item => {
                const ten = item.dataset.ten || "";
                const ma  = (item.dataset.ma || "").toLowerCase();
                const category = (item.dataset.category || "").toLowerCase();
                
                const matchSearch = !query || ten.includes(query) || ma.includes(query);
                const matchCategory = !categoryQ || category === categoryQ;
                
                item.style.display = (matchSearch && matchCategory) ? "" : "none";
            });
            updateNameToggles();
        }

        document.getElementById("searchManual").addEventListener("input", filterManualList);
        document.getElementById("searchCategory").addEventListener("change", filterManualList);

        // Ẩn hiện phần nội dung tên thiết bị khi bấm nút mũi tên
        function toggleDeviceName(id) {
            const nameSpan = document.getElementById(`name-${id}`);
            const toggleBtn = document.getElementById(`toggle-${id}`);
            if (!nameSpan) return;
            
            if (nameSpan.style.whiteSpace === "nowrap" || nameSpan.style.whiteSpace === "") {
                nameSpan.style.whiteSpace = "normal";
                nameSpan.style.wordBreak = "break-word";
                if (toggleBtn) {
                    toggleBtn.style.transform = "rotate(90deg)";
                }
            } else {
                nameSpan.style.whiteSpace = "nowrap";
                nameSpan.style.wordBreak = "normal";
                if (toggleBtn) {
                    toggleBtn.style.transform = "rotate(0deg)";
                }
            }
        }

        // Kiểm tra xem phần tử tên thiết bị nào bị tràn văn bản thì mới hiển thị nút toggle
        function updateNameToggles() {
            document.querySelectorAll("#manualList .cl-item").forEach(item => {
                const id = item.dataset.id;
                const span = document.getElementById(`name-${id}`);
                const btn = document.getElementById(`toggle-${id}`);
                if (span && btn) {
                    if (span.style.whiteSpace === "normal") {
                        btn.style.display = "inline-flex";
                        return;
                    }
                    // Kiểm tra tràn văn bản thực tế dựa vào kích thước hiển thị
                    const isOverflowing = span.scrollWidth > span.clientWidth;
                    if (isOverflowing) {
                        btn.style.display = "inline-flex";
                    } else {
                        btn.style.display = "none";
                    }
                }
            });
        }

        // Đăng ký sự kiện tự động cập nhật khi tải trang hoặc thay đổi kích thước màn hình
        window.addEventListener("DOMContentLoaded", () => {
            setTimeout(updateNameToggles, 150);
        });
        window.addEventListener("resize", updateNameToggles);

        // ==============================================================================
        // THÔNG BÁO NỔI
        // ==============================================================================
        function showNotification(text, icon = "🔔") {
            const notif = document.getElementById("floatingNotif");
            document.getElementById("floatingNotifText").innerHTML = text;
            notif.querySelector(".floating-notif-icon").innerHTML = icon;
            notif.classList.add("active");
            
            setTimeout(() => {
                notif.classList.remove("active");
            }, 3500);
        }

        // ==============================================================================
        // ĐIỀU KHIỂN MODAL & LỊCH SỬ SỬ DỤNG
        // ==============================================================================
        const modal = document.getElementById("deviceModal");
        
        function openDeviceModalByCode(code) {
            fetch(`api.php?action=get_device&ma_thiet_bi=${encodeURIComponent(code)}`)
                .then(res => res.json())
                .then(data => {
                    if (data.error) {
                        showNotification(data.error, "⚠️");
                        return;
                    }
                    
                    document.getElementById("m_ten_thiet_bi").innerText = data.ten_thiet_bi;
                    document.getElementById("m_ma_thiet_bi").innerText = data.ma_thiet_bi;
                    document.getElementById("m_vi_tri").innerText = data.vi_tri || "Chưa rõ";
                    document.getElementById("m_nam_su_dung").innerText = data.nam_su_dung || "Chưa rõ";
                    document.getElementById("m_loai").innerText = data.ten_loai || "Chưa phân loại";
                    
                    // Nạp tình trạng chất lượng mới nhất
                    const statusEl = document.getElementById("m_chat_luong");
                    if (statusEl) {
                        statusEl.innerText = data.chat_luong || "Tốt";
                        const cl = (data.chat_luong || "").toLowerCase();
                        if (cl.includes("hư hỏng") || cl.includes("hỏng")) {
                            statusEl.style.color = "#ef4444"; // Đỏ
                        } else if (cl.includes("lỗi") || cl.includes("yếu") || cl.includes("trì") || cl.includes("cảnh báo")) {
                            statusEl.style.color = "#d97706"; // Vàng
                        } else {
                            statusEl.style.color = "#10b981"; // Xanh lá
                        }
                    }
                    
                    // Nạp thông tin giảng viên quản lý chi tiết kèm email và SĐT
                    if (data.ten_gv_quan_ly) {
                        let contactInfo = data.ten_gv_quan_ly;
                        if (data.email_gv_quan_ly) contactInfo += `\n✉ Email: ${data.email_gv_quan_ly}`;
                        if (data.sdt_gv_quan_ly) contactInfo += `\n📞 SĐT: ${data.sdt_gv_quan_ly}`;
                        document.getElementById("m_gv_quan_ly").style.whiteSpace = "pre-line";
                        document.getElementById("m_gv_quan_ly").innerText = contactInfo;
                    } else {
                        document.getElementById("m_gv_quan_ly").innerText = "Chưa phân công";
                    }
                    
                    // Nạp ảnh thiết bị
                    const mImg = document.getElementById("m_hinh_anh");
                    if (data.hinh_anh) {
                        mImg.src = "uploads/thumb_" + data.hinh_anh;
                        mImg.dataset.zoom = "uploads/" + data.hinh_anh;
                        mImg.onerror = function() {
                            this.src = "uploads/" + data.hinh_anh;
                            this.onerror = null;
                        };
                        mImg.style.display = "inline-block";
                    } else {
                        mImg.src = "";
                        mImg.dataset.zoom = "";
                        mImg.style.display = "none";
                    }
                    
                    // Nạp đường dẫn tài liệu Google Drive
                    const docContainer = document.getElementById("m_tai_lieu_container");
                    const docLink = document.getElementById("m_tai_lieu_link");
                    if (docContainer && docLink) {
                        if (data.tai_lieu_link && data.tai_lieu_link.trim() !== '') {
                            docLink.href = data.tai_lieu_link.trim();
                            docContainer.style.display = "block";
                        } else {
                            docLink.href = "#";
                            docContainer.style.display = "none";
                        }
                    }
                    
                    // Nạp danh sách lịch sử sử dụng trước đó
                    const historyList = document.getElementById("m_history_list");
                    historyList.innerHTML = "";
                    
                    if (data.history && data.history.length > 0) {
                        const timeline = document.createElement("div");
                        timeline.className = "history-timeline";
                        
                        data.history.forEach(h => {
                            const card = document.createElement("div");
                            card.className = "history-card";
                            
                            // Định dạng ngày
                            const dateStr = new Date(h.ngay_muon).toLocaleString('vi-VN', {
                                hour: '2-digit', 
                                minute: '2-digit', 
                                day: '2-digit', 
                                month: '2-digit', 
                                year: 'numeric'
                            });
                            
                            let comment = h.ghi_chu ? ` (${h.ghi_chu})` : '';
                            
                            let statusClass = 'badge-status-good';
                            let statusIcon = '🟢';
                            const tLower = h.tinh_trang.toLowerCase();
                            if (tLower.includes('hư hỏng') || tLower.includes('hỏng')) {
                                statusClass = 'badge-status-bad';
                                statusIcon = '🔴';
                            } else if (tLower.includes('lỗi') || tLower.includes('yếu') || tLower.includes('trì')) {
                                statusClass = 'badge-status-warning';
                                statusIcon = '🟡';
                            }
                            
                            card.innerHTML = `
                                <div class="history-card-header">
                                    <span class="history-card-user">👤 ${h.ten_giang_vien}</span>
                                    <span class="history-card-date"> ${dateStr}</span>
                                </div>
                                <div class="history-card-body">
                                    Lớp sử dụng: <strong class="history-card-purpose">${h.ten_lop}</strong><br>
                                    Tình trạng bàn giao: <span class="badge-status ${statusClass}" style="padding: 2px 6px; font-size: 0.72rem; margin-top: 4px;">${statusIcon} ${h.tinh_trang}${comment}</span>
                                </div>
                            `;
                            timeline.appendChild(card);
                        });
                        historyList.appendChild(timeline);
                    } else {
                        historyList.innerHTML = `<div class="empty-history">✨ Thiết bị chưa từng được sử dụng trước đây.</div>`;
                    }
                    
                    modal.classList.add("active");
                    updateBodyScrollLock();
                    history.pushState({ view: "modal" }, "");
                })
                .catch(err => {
                    showNotification("Không thể tải thông tin chi tiết!", "❌");
                });
        }

        document.getElementById("modalClose").addEventListener("click", () => closeDeviceModal());
        document.getElementById("modalOverlay").addEventListener("click", () => closeDeviceModal());

        // ==============================================================================
        // QUẢN LÝ THIẾT BỊ TRONG GIỎ ĐỒ (CART)
        // ==============================================================================
        
        function updateCardDeviceMeta(select) {
            const card = select.closest('.cl-item');
            const selectedOption = select.options[select.selectedIndex];
            
            const dbId = selectedOption.value;
            const code = selectedOption.dataset.code;
            const quality = selectedOption.dataset.quality;
            const statusCls = selectedOption.dataset.statusCls;
            const statusIcon = selectedOption.dataset.statusIcon;
            
            // Cập nhật dataset ID của card đại diện
            card.dataset.id = dbId;
            
            // Cập nhật mã hiển thị
            const codeEl = card.querySelector('.cl-code');
            if (codeEl) codeEl.innerText = code;
            
            // Cập nhật badge và icon trạng thái
            const statusBadge = card.querySelector('.cl-status-badge');
            if (statusBadge) {
                statusBadge.className = 'cl-status-badge ' + statusCls;
                statusBadge.innerHTML = statusIcon;
                statusBadge.setAttribute('onclick', `event.stopPropagation(); showNotification('Trạng thái mới nhất: <strong>${quality}</strong>', '${statusIcon}')`);
            }
        }

        function addDeviceFromCard(card) {
            const dbId = card.dataset.id;
            addDeviceToCart(dbId);
        }

        // Thêm hoặc Xóa thiết bị khỏi giỏ đồ
        function addDeviceToCart(dbId) {
            const device = allDevices.find(d => parseInt(d.id) === parseInt(dbId));
            if (!device) return;
            
            if (!cart[dbId]) {
                // Phân tích trạng thái hiện tại của thiết bị để làm mặc định cho bàn giao
                let initialCondition = 'Tốt';
                let initialDetail = '';
                const chatLuong = device.chat_luong || 'Tốt';
                const clLower = chatLuong.toLowerCase();
                
                if (clLower.includes('hư hỏng') || clLower.includes('hỏng')) {
                    initialCondition = 'Hư hỏng';
                    if (chatLuong.includes(':')) {
                        initialDetail = chatLuong.split(':').slice(1).join(':').trim();
                    } else {
                        initialDetail = chatLuong;
                    }
                } else if (clLower.includes('lỗi') || clLower.includes('yếu') || clLower.includes('trì') || clLower.includes('cảnh báo')) {
                    initialCondition = 'Lỗi nhẹ';
                    if (chatLuong.includes(':')) {
                        initialDetail = chatLuong.split(':').slice(1).join(':').trim();
                    } else {
                        initialDetail = chatLuong;
                    }
                }

                cart[dbId] = {
                    quantity: 1,
                    condition: initialCondition,
                    detail: initialDetail
                };
                showNotification(`Đã thêm <strong>${device.ten_thiet_bi}</strong>`);
                renderCart();
            } else {
                removeDeviceFromCart(dbId);
            }
        }

        // Xóa thiết bị khỏi giỏ đồ
        function removeDeviceFromCart(dbId) {
            if (cart[dbId]) {
                delete cart[dbId];
                showNotification(`Đã gỡ thiết bị khỏi giỏ đồ!`, "🗑");
                renderCart();
            }
        }

        // Tăng giảm số lượng trực tiếp trong giỏ
        function updateCartQty(dbId, delta) {
            const device = allDevices.find(d => parseInt(d.id) === parseInt(dbId));
            if (!device || !cart[dbId]) return;

            let newQty = cart[dbId].quantity + delta;
            if (newQty < 1) return;
            cart[dbId].quantity = newQty;
            renderCart();
        }

        // Đọc giá trị ghi chú và tình trạng trực tiếp trong giỏ
        function saveCartInputs() {
            document.querySelectorAll("#cartContainer .sdc").forEach(card => {
                const dbId = card.dataset.id;
                const condSelect = card.querySelector(".rate-select");
                const condDetail = card.querySelector(".sdc-textarea, .rate-detail");
                if (cart[dbId]) {
                    if (condSelect) cart[dbId].condition = condSelect.value;
                    if (condDetail) cart[dbId].detail = condDetail.value;
                }
            });
        }

        // Render giỏ đồ thiết bị đã chọn
        function renderCart() {
            saveCartInputs();
            const container = document.getElementById("cartContainer");
            const badge = document.getElementById("cartCountBadge");
            container.innerHTML = "";

            const cartKeys = Object.keys(cart);
            if (badge) badge.innerText = `${cartKeys.length} thiết bị`;

            const floatBadge = document.getElementById("cartBadgeCount");
            if (floatBadge) floatBadge.innerText = cartKeys.length;

            // Cập nhật active trên danh sách chọn thủ công
            document.querySelectorAll("#manualList .cl-item").forEach(item => {
                const id = item.dataset.id;
                const btnAdd = item.querySelector(".btn-mdi-add");
                if (cart[id]) {
                    item.classList.add("active");
                    if (btnAdd) { btnAdd.innerHTML = `✕`; btnAdd.classList.add("btn-mdi-selected"); btnAdd.title = "Xóa khỏi giỏ"; }
                } else {
                    item.classList.remove("active");
                    if (btnAdd) { btnAdd.innerHTML = `+`; btnAdd.classList.remove("btn-mdi-selected"); btnAdd.style.background = ""; btnAdd.title = "Thêm vào giỏ"; }
                }
            });

            if (cartKeys.length === 0) {
                container.innerHTML = `<p style="text-align:center; color:var(--text-muted); padding:30px 10px; font-style:italic;">Hãy quét mã QR hoặc chọn thủ công bên trái.</p>`;
                return;
            }

            cartKeys.forEach(dbId => {
                const device = allDevices.find(d => parseInt(d.id) === parseInt(dbId));
                if (!device) return;
                const itemState = cart[dbId];

                // Trạng thái thiết bị
                let statusIcon = '✔️';
                let statusCls  = 'badge-status-good';
                let borderColor = '#10b981';
                const cl = (device.chat_luong || '').toLowerCase();
                if (cl.includes('hư hỏng') || cl.includes('hỏng')) {
                    statusIcon = '❌'; statusCls = 'badge-status-bad'; borderColor = '#ef4444';
                } else if (cl.includes('lỗi') || cl.includes('yếu') || cl.includes('trì')) {
                    statusIcon = '⚠️'; statusCls = 'badge-status-warning'; borderColor = '#f59e0b';
                }

                const isDamaged = itemState.condition === 'Hư hỏng' || itemState.condition === 'Lỗi nhẹ';

                const thumbHtml = device.hinh_anh
                    ? `<img src="uploads/thumb_${device.hinh_anh}" data-zoom="uploads/${device.hinh_anh}" onerror="this.onerror=null; this.src='uploads/${device.hinh_anh}';" class="sdc-thumb zoomable-thumb" alt="${device.ten_thiet_bi}">`
                    : `<div class="sdc-thumb sdc-thumb-noimg">📦</div>`;

                const condLabel = itemState.condition === 'Hư hỏng' ? '🔴 Hư hỏng'
                                : itemState.condition === 'Lỗi nhẹ'  ? '🟡 Lỗi nhẹ'
                                : '🟢 Tốt';

                const wrapper = document.createElement('div');
                wrapper.innerHTML = `
                <div class="sdc" data-id="${dbId}" style="--sdc-accent:${borderColor}">
                    <!-- Hàng trên: ảnh + thông tin + nút -->
                    <div class="sdc-top">
                        ${thumbHtml}
                        <div class="sdc-body">
                            <div class="sdc-name">${device.ten_thiet_bi}</div>
                            <div class="sdc-sub">
                                <code class="sdc-code">${device.ma_thiet_bi}</code>
                                <span class="sdc-year">${device.nam_su_dung || ''}</span>
                                <span class="cl-status-badge ${statusCls}" title="${device.chat_luong}">${statusIcon}</span>
                            </div>
                        </div>
                        <div class="sdc-btns">
                            <button type="button" class="sdc-btn sdc-btn-info" onclick="openDeviceModalByCode('${device.ma_thiet_bi}')" title="Xem chi tiết">ⓘ</button>
                            <button type="button" class="sdc-btn sdc-btn-remove" onclick="removeDeviceFromCart(${dbId})" title="Xóa">&times;</button>
                        </div>
                    </div>
                    <!-- Hàng dưới: tình trạng bàn giao -->
                    <div class="sdc-review">
                        <span class="sdc-review-label">⚙️ Bàn giao:</span>
                        <select class="sdc-select rate-select" onchange="toggleCardReviewText(${dbId}, this.value)" required>
                            <option value="Tốt"    ${itemState.condition === 'Tốt'    ? 'selected' : ''}>🟢 Tốt</option>
                            <option value="Lỗi nhẹ" ${itemState.condition === 'Lỗi nhẹ' ? 'selected' : ''}>🟡 Lỗi nhẹ</option>
                            <option value="Hư hỏng" ${itemState.condition === 'Hư hỏng' ? 'selected' : ''}>🔴 Hư hỏng</option>
                        </select>
                        <textarea class="sdc-textarea rate-detail" placeholder="Mô tả lỗi/hỏng..." style="display:${isDamaged ? 'block' : 'none'}">${itemState.detail}</textarea>
                    </div>
                </div>`;

                container.appendChild(wrapper.firstElementChild);
            });
        }

        // Toggle textarea ghi chú hỏng ngay trong giỏ đồ
        function toggleCardReviewText(dbId, val) {
            const card = document.querySelector(`.sdc[data-id="${dbId}"]`);
            if (!card) return;
            const textarea = card.querySelector(".sdc-textarea");
            if (val === 'Hư hỏng' || val === 'Lỗi nhẹ') {
                textarea.style.display = "block";
                textarea.setAttribute("required", "required");
                textarea.focus();
            } else {
                textarea.style.display = "none";
                textarea.removeAttribute("required");
                textarea.value = "";
            }
            saveCartInputs();
        }


        // ==============================================================================
        // QUÉT MÃ QR BẰNG CAMERA TRÌNH DUYỆT (TÍCH HỢP TRỰC TIẾP)
        // ==============================================================================
        let html5QrCode = null;
        const qrButton = document.getElementById("btnQRScan");
        const readerDiv = document.getElementById("reader");

        qrButton.addEventListener("click", () => {
            if (readerDiv.style.display === "none") {
                readerDiv.style.display = "block";
                qrButton.innerHTML = "❌ ĐÓNG CAMERA QUÉT";
                qrButton.style.background = "linear-gradient(135deg, var(--error-red), #b91c1c)";
                qrButton.style.color = "#fff";
                
                let lastScannedCode = "";
                let lastScannedTime = 0;

                const qrCodeSuccessCallback = (decodedText, decodedResult) => {
                    let ma_thiet_bi = decodedText;
                    try {
                        const url = new URL(decodedText);
                        const params = new URLSearchParams(url.search);
                        if (params.has('scan')) {
                            ma_thiet_bi = params.get('scan');
                        }
                    } catch(e) {}
                    
                    // Throttling: bỏ qua nếu quét trùng mã trong vòng 2 giây
                    const now = Date.now();
                    if (ma_thiet_bi === lastScannedCode && (now - lastScannedTime) < 2000) {
                        return; 
                    }
                    
                    lastScannedCode = ma_thiet_bi;
                    lastScannedTime = now;
                    
                    // Quét liên tục: KHÔNG dừng camera, chỉ xử lý thiết bị vừa quét
                    processScannedDevice(ma_thiet_bi);
                };
                
                html5QrCode = new Html5Qrcode("reader");
                const config = { fps: 10, qrbox: { width: 250, height: 250 } };
                
                // Khởi chạy quét camera
                html5QrCode.start({ facingMode: "environment" }, config, qrCodeSuccessCallback)
                    .then(() => {
                        // Thiết lập thuộc tính khi stream bắt đầu
                        const video = readerDiv.querySelector("video");
                        if (video) {
                            video.setAttribute("playsinline", "true");
                            video.setAttribute("webkit-playsinline", "true");
                            video.setAttribute("playsInline", "true");
                            video.playsInline = true;
                        }
                    })
                    .catch(err => {
                        showNotification("Không thể mở camera. Vui lòng cấp quyền camera!", "❌");
                        stopScanner();
                    });

                // Sử dụng interval quét nhanh để gắn ngay lập tức thuộc tính playsinline khi thẻ video được khởi tạo
                let inlineInterval = setInterval(() => {
                    const video = readerDiv.querySelector("video");
                    if (video) {
                        video.setAttribute("playsinline", "true");
                        video.setAttribute("webkit-playsinline", "true");
                        video.setAttribute("playsInline", "true");
                        video.playsInline = true;
                        clearInterval(inlineInterval);
                    }
                }, 50);
                setTimeout(() => clearInterval(inlineInterval), 5000);
            } else {
                stopScanner();
            }
        });

        function stopScanner() {
            if (html5QrCode) {
                html5QrCode.stop().then(() => {
                    html5QrCode = null;
                }).catch(() => {});
            }
            readerDiv.style.display = "none";
            qrButton.innerHTML = "📷 MỞ CAMERA QUÉT QR";
            qrButton.style.background = "linear-gradient(135deg, var(--accent-blue), #0099ff)";
            qrButton.style.color = "#fff";
        }

        function processScannedDevice(identifier) {
            let url = `api.php?action=get_device`;
            if (/^\d+$/.test(identifier)) {
                url += `&id=${encodeURIComponent(identifier)}`;
            } else {
                url += `&ma_thiet_bi=${encodeURIComponent(identifier)}`;
            }
            
            fetch(url)
                .then(res => res.json())
                .then(data => {
                    if (data.error) {
                        showNotification(data.error, "⚠️");
                        return;
                    }
                    
                    const dbId = data.id;
                    
                    // Nếu thiết bị đã nằm trong giỏ đồ, thông báo và bỏ qua, tránh việc toggle gỡ khỏi giỏ
                    if (cart[dbId]) {
                        showNotification(`ℹ️ <strong>${data.ten_thiet_bi}</strong> đã có sẵn trong giỏ đồ!`, "ℹ️");
                        return;
                    }
                    
                    // Thêm vào giỏ đồ
                    addDeviceToCart(dbId);
                    
                    // Hiển thị thông báo nhanh, KHÔNG mở cart drawer để camera tiếp tục quét
                    showNotification(`✅ Đã thêm: <strong>${data.ten_thiet_bi}</strong>`, "📦");
                })
                .catch(err => {
                    showNotification("Lỗi kết nối khi quét!", "❌");
                });
        }

        // ==============================================================================
        // XỬ LÝ LỒNG GHÉP QUÉT TRỰC TIẾP TỪ URL (scan=TB001)
        // ==============================================================================
        const initialScanCode = "<?= htmlspecialchars($scan_ma_thiet_bi) ?>";
        if (initialScanCode !== "") {
            window.addEventListener("DOMContentLoaded", () => {
                setTimeout(() => {
                    processScannedDevice(initialScanCode);
                }, 800);
            });
        }

        // ==============================================================================
        // XỬ LÝ GỬI FORM CHÍNH (BIỂU MẪU MƯỢN THIẾT BỊ BẰNG AJAX)
        // ==============================================================================
        const form = document.getElementById("deviceForm");
        const successOverlay = document.getElementById("successOverlay");

        form.addEventListener("submit", function(e) {
            e.preventDefault();
            
            const cartKeys = Object.keys(cart);
            if (cartKeys.length === 0) {
                alert("Giỏ đồ đang trống! Vui lòng chọn ít nhất một thiết bị.");
                return;
            }
            
            saveCartInputs(); // Đảm bảo lưu thông tin review mới nhất
            
            const formData = new FormData();
            formData.append("action", "borrow");
            formData.append("tenLop", document.getElementById("tenLop").value.trim());
            
            cartKeys.forEach((dbId) => {
                formData.append(`devices[${dbId}]`, cart[dbId].quantity);
                formData.append(`conditions[${dbId}][condition]`, cart[dbId].condition);
                formData.append(`conditions[${dbId}][detail]`, cart[dbId].detail);
            });

            const btnSubmit = document.getElementById("btnSubmit");
            btnSubmit.disabled = true;
            btnSubmit.innerHTML = "⏳ ĐANG LƯU DỮ LIỆU PHIẾU MƯỢN...";

            fetch("index.php", {
                method: "POST",
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    document.getElementById("successText").innerHTML = data.msg;
                    
                    // Ẩn modal/drawer giỏ hàng thiết bị đã chọn
                    document.getElementById("cartDrawer").classList.remove("active");
                    document.getElementById("cartDrawerOverlay").classList.remove("active");
                    
                    // Hiển thị màn hình thành công
                    successOverlay.classList.add("active");
                    updateBodyScrollLock();
                    
                    form.reset();
                    cart = {};
                    renderCart();
                } else {
                    alert("Lỗi: " + data.error);
                }
                
                btnSubmit.disabled = false;
                btnSubmit.innerHTML = "📤 GỬI XÁC NHẬN SỬ DỤNG";
            })
            .catch(err => {
                alert("Lỗi kết nối máy chủ khi gửi phiếu!");
                btnSubmit.disabled = false;
                btnSubmit.innerHTML = "📤 GỬI XÁC NHẬN SỬ DỤNG";
            });
        });

        document.getElementById("btnSuccessClose").addEventListener("click", () => {
            successOverlay.classList.remove("active");
            updateBodyScrollLock();
            window.location.reload();
        });
    </script>
</body>
</html>
