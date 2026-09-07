<?php
/**
 * api.php
 * =======
 * AJAX API phục vụ quét QR Code, cập nhật trạng thái, lịch sử và thống kê thiết bị.
 */
require_once 'config.php';

// Chỉ cho phép người dùng đã đăng nhập sử dụng API
if (!is_logged_in()) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Chưa đăng nhập!']);
    exit;
}

header('Content-Type: application/json');

$action = $_GET['action'] ?? 'get_device';

// ============================================================
// GET DEVICE — Lấy thông tin + lịch sử thiết bị theo mã QR
// ============================================================
if ($action === 'get_device') {
    $device_id   = intval($_GET['id'] ?? 0);
    $ma_thiet_bi = $_GET['ma_thiet_bi'] ?? '';
    
    if ($device_id <= 0 && empty($ma_thiet_bi)) { 
        echo json_encode(['error' => 'Thiếu thông tin nhận diện thiết bị (ID hoặc Mã)!']); 
        exit; 
    }
    
    try {
        if ($device_id > 0) {
            $stmt = $db->prepare("
                SELECT tb.*, 
                       gv.ho_ten_gv AS ten_gv_quan_ly,
                       gv.email AS email_gv_quan_ly,
                       gv.so_dien_thoai AS sdt_gv_quan_ly,
                       l.ten_loai,
                       l.ma_mau
                FROM thiet_bi tb 
                LEFT JOIN giang_vien gv ON tb.id_giang_vien_quan_ly = gv.id_giang_vien 
                LEFT JOIN loai l ON tb.id_loai = l.id_loai
                WHERE tb.id = :id
            ");
            $stmt->execute(['id' => $device_id]);
        } else {
            $stmt = $db->prepare("
                SELECT tb.*, 
                       gv.ho_ten_gv AS ten_gv_quan_ly,
                       gv.email AS email_gv_quan_ly,
                       gv.so_dien_thoai AS sdt_gv_quan_ly,
                       l.ten_loai,
                       l.ma_mau
                FROM thiet_bi tb 
                LEFT JOIN giang_vien gv ON tb.id_giang_vien_quan_ly = gv.id_giang_vien 
                LEFT JOIN loai l ON tb.id_loai = l.id_loai
                WHERE tb.ma_thiet_bi = :ma
            ");
            $stmt->execute(['ma' => $ma_thiet_bi]);
        }
        $device = $stmt->fetch();
        if ($device) {
            $h_stmt = $db->prepare("
                SELECT pm.ngay_muon, pm.trang_thai, gv.ho_ten_gv AS ten_giang_vien, pm.ten_lop, ct.so_luong, ct.tinh_trang, ct.ghi_chu,
                       lhp.ten_hoc_phan
                FROM chi_tiet_phieu_muon ct
                JOIN phieu_muon pm ON ct.id_phieu_muon = pm.id
                LEFT JOIN giang_vien gv ON pm.id_giang_vien = gv.id_giang_vien
                LEFT JOIN lop_hoc_phan lhp ON pm.ten_lop = lhp.ma_lop_hp
                WHERE ct.id_thiet_bi = :id_tb
                ORDER BY pm.ngay_muon DESC
                LIMIT 10
            ");
            $h_stmt->execute(['id_tb' => $device['id']]);
            $history = $h_stmt->fetchAll();
            foreach ($history as &$row) {
                if (!empty($row['ten_hoc_phan'])) {
                    $row['ten_lop'] = $row['ten_hoc_phan'] . ' (' . $row['ten_lop'] . ')';
                }
            }
            $device['history'] = $history;
            echo json_encode($device);
        } else {
            echo json_encode(['error' => 'Không tìm thấy thiết bị này trong hệ thống!']);
        }
    } catch (PDOException $e) {
        echo json_encode(['error' => 'Lỗi CSDL: ' . $e->getMessage()]);
    }
    exit;
}

// ============================================================
// UPDATE QUALITY — Cập nhật Tình trạng thiết bị
// ============================================================
if ($action === 'update_quality') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') { echo json_encode(['error' => 'Phương thức không hợp lệ!']); exit; }
    $id        = intval($_POST['id'] ?? 0);
    $condition = $_POST['condition'] ?? 'Tốt';
    $detail    = trim($_POST['detail'] ?? '');
    if ($id <= 0) { echo json_encode(['error' => 'ID không hợp lệ!']); exit; }
    $chat_luong_text = $condition;
    if ($condition === 'Hư hỏng' && !empty($detail)) $chat_luong_text = "Hư hỏng: " . $detail;
    try {
        $db->prepare("UPDATE thiet_bi SET chat_luong = :chat_luong, updated_at = NOW() WHERE id = :id")
           ->execute(['chat_luong' => $chat_luong_text, 'id' => $id]);

        if ($condition === 'Hư hỏng') {
            require_once __DIR__ . '/mail_helper.php';
            $stmt = $db->prepare("
                SELECT tb.ma_thiet_bi, tb.ten_thiet_bi, tb.vi_tri, gv.email, gv.ho_ten_gv 
                FROM thiet_bi tb 
                LEFT JOIN giang_vien gv ON tb.id_giang_vien_quan_ly = gv.id_giang_vien 
                WHERE tb.id = :id
            ");
            $stmt->execute(['id' => $id]);
            $deviceInfo = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($deviceInfo && !empty($deviceInfo['email'])) {
                sendDeviceDamageAlert(
                    $deviceInfo['email'], 
                    $deviceInfo['ho_ten_gv'], 
                    $deviceInfo, 
                    $detail ?: 'Hư hỏng', 
                    'Quản trị viên (Admin)'
                );
            }
        }

        echo json_encode(['success' => true, 'msg' => 'Cập nhật tình trạng thành công!', 'new_chat_luong' => $chat_luong_text]);
    } catch (PDOException $e) {
        echo json_encode(['error' => 'Lỗi CSDL: ' . $e->getMessage()]);
    }
    exit;
}

// ============================================================
// DEVICE HISTORY — Lịch sử đầy đủ của 1 thiết bị (dùng cho admin modal)
// ============================================================
if ($action === 'device_history') {
    $device_id = intval($_GET['id'] ?? 0);
    if ($device_id <= 0) { echo json_encode(['error' => 'ID thiết bị không hợp lệ!']); exit; }
    try {
        $d_stmt = $db->prepare("
            SELECT tb.*, 
                   gv.ho_ten_gv AS ten_gv_quan_ly,
                   gv.email AS email_gv_quan_ly,
                   gv.so_dien_thoai AS sdt_gv_quan_ly
            FROM thiet_bi tb
            LEFT JOIN giang_vien gv ON tb.id_giang_vien_quan_ly = gv.id_giang_vien
            WHERE tb.id = :id
        ");
        $d_stmt->execute(['id' => $device_id]);
        $device = $d_stmt->fetch();
        if (!$device) { echo json_encode(['error' => 'Không tìm thấy thiết bị!']); exit; }

        $h_stmt = $db->prepare("
            SELECT pm.id AS id_phieu, pm.ngay_muon, pm.trang_thai, pm.ten_lop,
                   gv.ho_ten_gv AS ten_giang_vien, gv.email,
                   ct.so_luong, ct.tinh_trang, ct.ghi_chu,
                   lhp.ten_hoc_phan
            FROM chi_tiet_phieu_muon ct
            JOIN phieu_muon pm ON ct.id_phieu_muon = pm.id
            LEFT JOIN giang_vien gv ON pm.id_giang_vien = gv.id_giang_vien
            LEFT JOIN lop_hoc_phan lhp ON pm.ten_lop = lhp.ma_lop_hp
            WHERE ct.id_thiet_bi = :id
            ORDER BY pm.ngay_muon DESC
        ");
        $h_stmt->execute(['id' => $device_id]);
        $history = $h_stmt->fetchAll();
        foreach ($history as &$row) {
            if (!empty($row['ten_hoc_phan'])) {
                $row['ten_lop'] = $row['ten_hoc_phan'] . ' (' . $row['ten_lop'] . ')';
            }
        }

        echo json_encode([
            'device'     => $device,
            'history'    => $history,
            'total_uses' => count($history),
            'total_qty'  => array_sum(array_column($history, 'so_luong')),
        ]);
    } catch (PDOException $e) {
        echo json_encode(['error' => 'Lỗi CSDL: ' . $e->getMessage()]);
    }
    exit;
}

// ============================================================
// STATS TOP DEVICES — Top thiết bị được mượn nhiều nhất
// ============================================================
if ($action === 'stats_top_devices') {
    try {
        $stmt = $db->query("
            SELECT tb.id, tb.ma_thiet_bi, tb.ten_thiet_bi, tb.chat_luong, tb.hinh_anh,
                   COUNT(ct.id) AS so_luot_muon,
                   COALESCE(SUM(ct.so_luong), 0) AS tong_so_luong_muon,
                   MAX(pm.ngay_muon) AS lan_muon_gan_nhat
            FROM thiet_bi tb
            LEFT JOIN chi_tiet_phieu_muon ct ON tb.id = ct.id_thiet_bi
            LEFT JOIN phieu_muon pm ON ct.id_phieu_muon = pm.id
            GROUP BY tb.id, tb.ma_thiet_bi, tb.ten_thiet_bi, tb.chat_luong, tb.hinh_anh
            ORDER BY so_luot_muon DESC, tong_so_luong_muon DESC
            LIMIT 20
        ");
        echo json_encode(['data' => $stmt->fetchAll()]);
    } catch (PDOException $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}

// ============================================================
// STATS INACTIVE TEACHERS — GV có lịch TH nhưng không mượn TB
// ============================================================
if ($action === 'stats_inactive_teachers') {
    $hk_param = $_GET['id_hocky_namhoc'] ?? '';
    $id_hocky_namhoc = intval($hk_param);
    $sub_semester = '';
    if (strpos($hk_param, '_a') !== false) {
        $sub_semester = 'a';
    } elseif (strpos($hk_param, '_b') !== false) {
        $sub_semester = 'b';
    }
    
    // 1. Truy vấn ngày bắt đầu/kết thúc chính thức của Học kỳ từ CSDL
    $hk_row = null;
    if ($id_hocky_namhoc > 0) {
        $hk_stmt = $db->prepare("SELECT * FROM hoc_ky_nam_hoc WHERE id_hocky_namhoc = :id");
        $hk_stmt->execute(['id' => $id_hocky_namhoc]);
        $hk_row = $hk_stmt->fetch();
    }
    
    // Nếu chưa có học kỳ hoặc không khớp, tự động lấy học kỳ hiện tại
    if (!$hk_row) {
        $today = date('Y-m-d');
        $hk_stmt = $db->prepare("
            SELECT * FROM hoc_ky_nam_hoc 
            WHERE ngay_bat_dau <= :today1 AND ngay_ket_thuc >= :today2 
            LIMIT 1
        ");
        $hk_stmt->execute(['today1' => $today, 'today2' => $today]);
        $hk_row = $hk_stmt->fetch();
    }
    
    // Nếu vẫn không có, lấy học kỳ mới nhất
    if (!$hk_row) {
        $hk_row = $db->query("SELECT * FROM hoc_ky_nam_hoc ORDER BY ngay_bat_dau DESC LIMIT 1")->fetch();
    }
    
    if (!$hk_row) {
        echo json_encode(['error' => 'Không tìm thấy học kỳ hợp lệ trong CSDL!']);
        exit;
    }
    
    // Nhận ngày tùy chỉnh từ Client nếu có
    $custom_from = $_GET['from'] ?? '';
    $custom_to = $_GET['to'] ?? '';
    
    $from = $hk_row['ngay_bat_dau'];
    $to = $hk_row['ngay_ket_thuc'];
    $id_hocky_namhoc = intval($hk_row['id_hocky_namhoc']);
    
    // Xác định điều kiện lọc đợt học kỳ phụ (a/b)
    $sub_sem_cond = "";
    if (($sub_semester === 'a' || $sub_semester === 'b') && $id_hocky_namhoc >= 86) {
        $ts_start = strtotime($from);
        $ts_end = strtotime($to);
        $diff = $ts_end - $ts_start;
        $mid = $ts_start + round($diff / 2);
        $mid_date = date('Y-m-d', $mid);
        
        if ($sub_semester === 'a') {
            $to = $mid_date;
            $sub_sem_cond = " AND LOWER(substring(lhp.ma_lop_hp, 4, 1)) = 'a' ";
        } else {
            $from = date('Y-m-d', strtotime($mid_date . ' + 1 day'));
            $sub_sem_cond = " AND LOWER(substring(lhp.ma_lop_hp, 4, 1)) = 'b' ";
        }
    }
    
    // Ưu tiên sử dụng ngày tùy chỉnh do client gửi lên
    if (!empty($custom_from) && !empty($custom_to)) {
        $from = $custom_from;
        $to = $custom_to;
    }
    
    try {
        $stmt = $db->prepare("
            SELECT gv.id_giang_vien, gv.ho_ten_gv, gv.email,
                   COUNT(lgdct.id) AS so_buoi_day,
                   COALESCE(pm_count.so_lan_muon, 0) AS so_lan_muon_tb
            FROM giang_vien gv
            JOIN lich_giang_day lgd ON lgd.id_giang_vien = gv.id_giang_vien
            JOIN lop_hoc_phan lhp ON lgd.id_lop_hoc_phan = lhp.id_lop_hoc_phan
            JOIN lich_giang_day_chi_tiet lgdct ON lgdct.id_lich_giang_day = lgd.id
            LEFT JOIN (
                SELECT id_giang_vien, COUNT(*) AS so_lan_muon
                FROM phieu_muon
                WHERE ngay_muon::date BETWEEN :from1 AND :to1
                GROUP BY id_giang_vien
            ) pm_count ON pm_count.id_giang_vien = gv.id_giang_vien
            WHERE (lhp.tin_chi_th > 0 OR lhp.ten_hoc_phan ILIKE '%Thực tập%' OR lhp.ten_hoc_phan ILIKE '%Thí nghiệm%' OR lhp.ten_hoc_phan ILIKE '%Thực hành%')
              AND lgd.id_hocky_namhoc = :hk
              $sub_sem_cond
              AND lgdct.ngay_day BETWEEN :from2 AND :to2
            GROUP BY gv.id_giang_vien, gv.ho_ten_gv, gv.email, pm_count.so_lan_muon
            ORDER BY so_lan_muon_tb ASC, so_buoi_day DESC
        ");
        $stmt->execute([
            'from1' => $from,
            'to1'   => $to,
            'from2' => $from,
            'to2'   => $to,
            'hk'    => $id_hocky_namhoc
        ]);
        echo json_encode([
            'data' => $stmt->fetchAll(), 
            'from' => $from, 
            'to'   => $to,
            'id_hocky_namhoc' => $hk_param
        ]);
    } catch (PDOException $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}


echo json_encode(['error' => 'Hành động không hợp lệ!']);
exit;
