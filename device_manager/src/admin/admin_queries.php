<?php
/**
 * admin/admin_queries.php
 * =======================
 * Truy vấn dữ liệu cần thiết cho trang admin:
 * - Danh sách giảng viên
 * - Danh sách thiết bị
 * - Lịch sử phiếu mượn
 */

// 1. Lấy danh sách giảng viên
$lecturers = [];
try {
    $lecturers = $db->query("SELECT id_giang_vien, ho_ten_gv FROM giang_vien ORDER BY ho_ten_gv ASC")->fetchAll();
} catch (PDOException $e) {}

// 2. Lấy danh sách thiết bị
$devices = [];
try {
    $devices = $db->query("
        SELECT tb.*, gv.ho_ten_gv AS ten_gv_quan_ly, l.ten_loai, l.ma_mau 
        FROM thiet_bi tb 
        LEFT JOIN giang_vien gv ON tb.id_giang_vien_quan_ly = gv.id_giang_vien 
        LEFT JOIN loai l ON tb.id_loai = l.id_loai
        ORDER BY tb.ten_thiet_bi ASC
    ")->fetchAll();
} catch (PDOException $e) {}

// 2b. Lấy danh sách phân loại thiết bị
$categories = [];
try {
    $categories = $db->query("SELECT * FROM loai ORDER BY ten_loai ASC")->fetchAll();
} catch (PDOException $e) {}

// 3. Lấy danh sách Học kỳ phục vụ bộ lọc thời gian
$semesters = [];
try {
    $semesters = $db->query("SELECT * FROM hoc_ky_nam_hoc ORDER BY ngay_bat_dau DESC")->fetchAll();
} catch (PDOException $e) {}

// 4. Lấy lịch sử phiếu mượn thiết bị (Có lọc theo Học kỳ / Thời gian)
$selected_hk = isset($_GET['hk_filter']) ? intval($_GET['hk_filter']) : -1;
$start_date_filter = isset($_GET['start_date']) ? trim($_GET['start_date']) : '';
$end_date_filter = isset($_GET['end_date']) ? trim($_GET['end_date']) : '';

// Nếu mới truy cập lần đầu (không truyền hk_filter và không tự chọn ngày)
if ($selected_hk === -1 && empty($start_date_filter) && empty($end_date_filter)) {
    $current_date = date('Y-m-d');
    try {
        // Tìm học kỳ chứa ngày hiện tại
        $curr_stmt = $db->prepare("
            SELECT id_hocky_namhoc 
            FROM hoc_ky_nam_hoc 
            WHERE ngay_bat_dau <= :curr1 AND ngay_ket_thuc >= :curr2 
            LIMIT 1
        ");
        $curr_stmt->execute(['curr1' => $current_date, 'curr2' => $current_date]);
        $curr_row = $curr_stmt->fetch();
        if ($curr_row) {
            $selected_hk = intval($curr_row['id_hocky_namhoc']);
        } else {
            // Nếu không trùng học kỳ nào, lấy học kỳ mới nhất
            $latest_stmt = $db->query("SELECT id_hocky_namhoc FROM hoc_ky_nam_hoc ORDER BY ngay_bat_dau DESC LIMIT 1");
            $latest_row = $latest_stmt->fetch();
            if ($latest_row) {
                $selected_hk = intval($latest_row['id_hocky_namhoc']);
            } else {
                $selected_hk = 0;
            }
        }
    } catch (PDOException $e) {
        $selected_hk = 0;
    }
} elseif ($selected_hk === -1) {
    $selected_hk = 0;
}

$where_clause = "";
$params = [];

if ($selected_hk > 0) {
    // Lọc theo Học kỳ
    $hk_stmt = $db->prepare("SELECT ngay_bat_dau, ngay_ket_thuc FROM hoc_ky_nam_hoc WHERE id_hocky_namhoc = :id");
    $hk_stmt->execute(['id' => $selected_hk]);
    $hk_row = $hk_stmt->fetch();
    if ($hk_row && $hk_row['ngay_bat_dau'] && $hk_row['ngay_ket_thuc']) {
        $where_clause = " WHERE pm.ngay_muon >= :start_date AND pm.ngay_muon <= :end_date ";
        $params['start_date'] = $hk_row['ngay_bat_dau'] . ' 00:00:00';
        $params['end_date'] = $hk_row['ngay_ket_thuc'] . ' 23:59:59';
        
        // Tự động điền ngày vào input date để đồng bộ giao diện
        $start_date_filter = $hk_row['ngay_bat_dau'];
        $end_date_filter = $hk_row['ngay_ket_thuc'];
    }
} elseif (!empty($start_date_filter) || !empty($end_date_filter)) {
    // Lọc theo ngày tùy chọn
    $conds = [];
    if (!empty($start_date_filter)) {
        $conds[] = "pm.ngay_muon >= :start_date";
        $params['start_date'] = $start_date_filter . ' 00:00:00';
    }
    if (!empty($end_date_filter)) {
        $conds[] = "pm.ngay_muon <= :end_date";
        $params['end_date'] = $end_date_filter . ' 23:59:59';
    }
    $where_clause = " WHERE " . implode(" AND ", $conds);
}

$history = [];
try {
    $sql = "
        SELECT pm.*, gv.ho_ten_gv AS ten_giang_vien 
        FROM phieu_muon pm
        LEFT JOIN giang_vien gv ON pm.id_giang_vien = gv.id_giang_vien
        $where_clause
        ORDER BY pm.ngay_muon DESC
    ";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $history = $stmt->fetchAll();
} catch (PDOException $e) {}
