<?php
/**
 * admin/admin_export_csv.php
 * =========================
 * Xử lý xuất danh sách thiết bị hoặc lịch sử sử dụng ra file Excel (.xlsx) chuẩn.
 * Sử dụng thư viện SimpleXLSXGen để ghi file Excel nhị phân thực tế, loại bỏ cảnh báo lỗi format.
 * Chạy trước mọi output HTML vì cần gửi header.
 * Nếu khớp điều kiện, sẽ exit() ngay sau khi xuất xong.
 */

require_once __DIR__ . '/SimpleXLSXGen.php';

// Các hàm bổ trợ để tạo style Excel qua tag XML đơn giản
function xl_header($text) {
    return '<style bgcolor="#1e40af" color="#ffffff" font-size="10" border="thin#cbd5e1"><center><b>' . htmlspecialchars($text) . '</b></center></style>';
}

function xl_title($text) {
    return '<style bgcolor="#f8fafc" color="#1e3a8a" font-size="14"><center><b>' . htmlspecialchars($text) . '</b></center></style>';
}

function xl_subtitle($text) {
    return '<style color="#475569" font-size="9"><center>' . htmlspecialchars($text) . '</center></style>';
}

function xl_cell($text, $align = 'left', $bold = false) {
    $bold_open = $bold ? '<b>' : '';
    $bold_close = $bold ? '</b>' : '';
    $align_tag = $align ? "<$align>" : '';
    return '<style font-size="10" border="thin#cbd5e1">' . $align_tag . $bold_open . htmlspecialchars($text) . $bold_close . '</style>';
}

function xl_info_label($text) {
    return '<style bgcolor="#f1f5f9" color="#334155" font-size="10" border="thin#cbd5e1"><b>' . htmlspecialchars($text) . '</b></style>';
}

function xl_info_value($text) {
    return '<style bgcolor="#ffffff" color="#000000" font-size="10" border="thin#cbd5e1">' . htmlspecialchars($text) . '</style>';
}

// 1. XUẤT DANH SÁCH THIẾT BỊ
if (isset($_GET['action']) && $_GET['action'] === 'export_csv') {
    $rows = [];
    
    // Dòng tiêu đề lớn
    $rows[] = [xl_title('DANH SÁCH THIẾT BỊ TRÊN HỆ THỐNG'), '', '', '', '', '', ''];
    // Dòng phụ đề
    $rows[] = [xl_subtitle('Ngày xuất báo cáo: ' . date('d/m/Y H:i:s')), '', '', '', '', '', ''];
    // Dòng trống cách biệt
    $rows[] = ['', '', '', '', '', '', ''];
    
    // Tiêu đề bảng
    $rows[] = [
        xl_header('Mã thiết bị'),
        xl_header('Tên thiết bị'),
        xl_header('Phân loại'),
        xl_header('Vị trí đặt'),
        xl_header('Năm sử dụng'),
        xl_header('Tình trạng chất lượng'),
        xl_header('Giáo viên quản lý')
    ];
    
    try {
        $stmt = $db->query("
            SELECT tb.ma_thiet_bi, tb.ten_thiet_bi, tb.vi_tri, tb.nam_su_dung, tb.chat_luong, l.ten_loai, gv.ho_ten_gv 
            FROM thiet_bi tb 
            LEFT JOIN loai l ON tb.id_loai = l.id_loai 
            LEFT JOIN giang_vien gv ON tb.id_giang_vien_quan_ly = gv.id_giang_vien
            ORDER BY tb.ten_thiet_bi ASC
        ");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $rows[] = [
                xl_cell($row['ma_thiet_bi'], 'center'),
                xl_cell($row['ten_thiet_bi'], 'left'),
                xl_cell($row['ten_loai'] ?: 'Chưa phân loại', 'left'),
                xl_cell($row['vi_tri'], 'left'),
                xl_cell($row['nam_su_dung'], 'center'),
                xl_cell($row['chat_luong'], 'left'),
                xl_cell($row['ho_ten_gv'] ?: 'Không có', 'left')
            ];
        }
    } catch (PDOException $e) {}
    
    $xlsx = \Shuchkin\SimpleXLSXGen::fromArray($rows, 'Danh sách thiết bị');
    $xlsx->mergeCells('A1:G1');
    $xlsx->mergeCells('A2:G2');
    
    // Đặt độ rộng các cột
    $xlsx->setColWidth('A', 15);
    $xlsx->setColWidth('B', 32);
    $xlsx->setColWidth('C', 18);
    $xlsx->setColWidth('D', 22);
    $xlsx->setColWidth('E', 12);
    $xlsx->setColWidth('F', 28);
    $xlsx->setColWidth('G', 24);
    
    $xlsx->downloadAs('danh_sach_thiet_bi_' . date('Ymd_His') . '.xlsx');
    exit;
}

// 2. XUẤT LỊCH SỬ SỬ DỤNG CỦA TỪNG THIẾT BỊ CỤ THỂ
if (isset($_GET['action']) && $_GET['action'] === 'export_device_history' && isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $selected_hk = isset($_GET['hk_filter']) ? intval($_GET['hk_filter']) : 0;
    
    // Lấy thông tin thiết bị
    $tb_stmt = $db->prepare("SELECT * FROM thiet_bi WHERE id = :id");
    $tb_stmt->execute(['id' => $id]);
    $device = $tb_stmt->fetch();
    
    if ($device) {
        $rows = [];
        
        $where_clause = " WHERE ct.id_thiet_bi = :id ";
        $params = ['id' => $id];
        $hk_title = "Toàn bộ lịch sử";

        if ($selected_hk > 0) {
            $hk_stmt = $db->prepare("SELECT ten_hoc_ky, ten_nam_hoc, ngay_bat_dau, ngay_ket_thuc FROM hoc_ky_nam_hoc WHERE id_hocky_namhoc = :id_hk");
            $hk_stmt->execute(['id_hk' => $selected_hk]);
            $hk_row = $hk_stmt->fetch();
            if ($hk_row && $hk_row['ngay_bat_dau'] && $hk_row['ngay_ket_thuc']) {
                $where_clause .= " AND pm.ngay_muon >= :start_date AND pm.ngay_muon <= :end_date ";
                $params['start_date'] = $hk_row['ngay_bat_dau'] . ' 00:00:00';
                $params['end_date'] = $hk_row['ngay_ket_thuc'] . ' 23:59:59';
                $hk_title = $hk_row['ten_hoc_ky'] . ' - ' . $hk_row['ten_nam_hoc'];
            }
        }
        
        // Tiêu đề
        $rows[] = [xl_title('BÁO CÁO NHẬT KÝ LỊCH SỬ SỬ DỤNG THIẾT BỊ'), '', '', '', '', ''];
        // Dòng trống
        $rows[] = ['', '', '', '', '', ''];
        
        // Khối thông tin thiết bị
        $rows[] = [
            xl_info_label('Mã thiết bị:'),
            xl_info_value($device['ma_thiet_bi']),
            '', // ghép B-C
            xl_info_label('Tên thiết bị:'),
            xl_info_value($device['ten_thiet_bi']),
            ''  // ghép E-F
        ];
        $rows[] = [
            xl_info_label('Vị trí đặt:'),
            xl_info_value($device['vi_tri']),
            '',
            xl_info_label('Năm sử dụng:'),
            xl_info_value($device['nam_su_dung']),
            ''
        ];
        $rows[] = [
            xl_info_label('Tình trạng hiện tại:'),
            xl_info_value($device['chat_luong']),
            '',
            xl_info_label('Ngày xuất báo cáo:'),
            xl_info_value(date('d/m/Y H:i:s')),
            ''
        ];
        $rows[] = [
            xl_info_label('Học kỳ thống kê:'),
            xl_info_value($hk_title),
            '',
            '',
            '',
            ''
        ];
        
        // Dòng trống
        $rows[] = ['', '', '', '', '', ''];
        
        // Tiêu đề bảng
        $rows[] = [
            xl_header('TT'),
            xl_header('Tình trạng thiết bị'),
            xl_header('Thời gian sử dụng'),
            xl_header('Mã lớp / Mục đích'),
            xl_header('Người sử dụng'),
            xl_header('Email xác nhận')
        ];
        
        try {
            $stmt = $db->prepare("
                SELECT pm.ngay_muon, gv.ho_ten_gv AS ten_giang_vien, pm.email_xac_nhan, pm.ten_lop, ct.tinh_trang, ct.ghi_chu,
                       lhp.ten_hoc_phan
                FROM chi_tiet_phieu_muon ct
                JOIN phieu_muon pm ON ct.id_phieu_muon = pm.id
                LEFT JOIN giang_vien gv ON pm.id_giang_vien = gv.id_giang_vien
                LEFT JOIN lop_hoc_phan lhp ON pm.ten_lop = lhp.ma_lop_hp
                $where_clause
                ORDER BY pm.ngay_muon DESC
            ");
            $stmt->execute($params);
            $idx = 1;
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $status_text = $row['tinh_trang'] . (!empty($row['ghi_chu']) ? ' - ' . $row['ghi_chu'] : '');
                $display_lop = !empty($row['ten_hoc_phan']) ? $row['ten_hoc_phan'] . ' (' . $row['ten_lop'] . ')' : $row['ten_lop'];
                $rows[] = [
                    xl_cell($idx++, 'center'),
                    xl_cell($status_text, 'left'),
                    xl_cell(date('d/m/Y H:i', strtotime($row['ngay_muon'])), 'center'),
                    xl_cell($display_lop, 'left'),
                    xl_cell($row['ten_giang_vien'] ?: 'Chưa xác định', 'left'),
                    xl_cell($row['email_xac_nhan'] ?: '', 'left')
                ];
            }
        } catch (PDOException $e) {}
        
        $xlsx = \Shuchkin\SimpleXLSXGen::fromArray($rows, 'Nhật ký sử dụng');
        
        // Ghép các ô thông tin
        $xlsx->mergeCells('A1:F1'); // Title
        $xlsx->mergeCells('B3:C3'); // Mã thiết bị
        $xlsx->mergeCells('E3:F3'); // Tên thiết bị
        $xlsx->mergeCells('B4:C4'); // Vị trí
        $xlsx->mergeCells('E4:F4'); // Năm sử dụng
        $xlsx->mergeCells('B5:C5'); // Tình trạng
        $xlsx->mergeCells('E5:F5'); // Ngày xuất báo cáo
        $xlsx->mergeCells('B6:F6'); // Học kỳ thống kê
        
        // Độ rộng cột
        $xlsx->setColWidth('A', 8);
        $xlsx->setColWidth('B', 30);
        $xlsx->setColWidth('C', 18);
        $xlsx->setColWidth('D', 34);
        $xlsx->setColWidth('E', 24);
        $xlsx->setColWidth('F', 24);
        
        $xlsx->downloadAs('lich_su_thiet_bi_' . $device['ma_thiet_bi'] . '_' . date('Ymd_His') . '.xlsx');
        exit;
    }
}

// 3. XUẤT NHẬT KÝ SỬ DỤNG THIẾT BỊ CHI TIẾT (CÓ LỌC THEO HỌC KỲ / THỜI GIAN)
if (isset($_GET['action']) && $_GET['action'] === 'export_usage_logs') {
    $selected_hk = isset($_GET['hk_filter']) ? intval($_GET['hk_filter']) : 0;
    $start_date_filter = isset($_GET['start_date']) ? trim($_GET['start_date']) : '';
    $end_date_filter = isset($_GET['end_date']) ? trim($_GET['end_date']) : '';
    
    $where_clause = "";
    $params = [];
    $hk_title = "Tất cả học kỳ";

    if ($selected_hk > 0) {
        $hk_stmt = $db->prepare("SELECT ten_hoc_ky, ten_nam_hoc, ngay_bat_dau, ngay_ket_thuc FROM hoc_ky_nam_hoc WHERE id_hocky_namhoc = :id");
        $hk_stmt->execute(['id' => $selected_hk]);
        $hk_row = $hk_stmt->fetch();
        if ($hk_row && $hk_row['ngay_bat_dau'] && $hk_row['ngay_ket_thuc']) {
            $where_clause = " WHERE pm.ngay_muon >= :start_date AND pm.ngay_muon <= :end_date ";
            $params['start_date'] = $hk_row['ngay_bat_dau'] . ' 00:00:00';
            $params['end_date'] = $hk_row['ngay_ket_thuc'] . ' 23:59:59';
            $hk_title = $hk_row['ten_hoc_ky'] . ' năm học ' . $hk_row['ten_nam_hoc'];
        }
    } elseif (!empty($start_date_filter) || !empty($end_date_filter)) {
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
        
        $formatStart = !empty($start_date_filter) ? date('d/m/Y', strtotime($start_date_filter)) : '...';
        $formatEnd = !empty($end_date_filter) ? date('d/m/Y', strtotime($end_date_filter)) : '...';
        $hk_title = "Từ ngày " . $formatStart . " đến ngày " . $formatEnd;
    }

    $rows = [];
    
    // Tiêu đề
    $rows[] = [xl_title('BÁO CÁO NHẬT KÝ SỬ DỤNG THIẾT BỊ CHI TIẾT'), '', '', '', '', ''];
    // Dòng trống
    $rows[] = ['', '', '', '', '', ''];
    
    // Khối thông tin lọc
    $rows[] = [
        xl_info_label('Thời gian thống kê:'),
        '', // ghép A-B
        xl_info_value($hk_title),
        '', // ghép C-F
        '',
        ''
    ];
    $rows[] = [
        xl_info_label('Ngày xuất báo cáo:'),
        '', // ghép A-B
        xl_info_value(date('d/m/Y H:i:s')),
        '', // ghép C-F
        '',
        ''
    ];
    
    // Dòng trống
    $rows[] = ['', '', '', '', '', ''];
    
    // Tiêu đề bảng
    $rows[] = [
        xl_header('Thời gian sử dụng'),
        xl_header('Giảng viên sử dụng'),
        xl_header('Email xác nhận'),
        xl_header('Mã lớp / Mục đích'),
        xl_header('Thiết bị sử dụng'),
        xl_header('Đánh giá chất lượng bàn giao')
    ];
    
    try {
        $sql = "
            SELECT pm.*, gv.ho_ten_gv AS ten_giang_vien, lhp.ten_hoc_phan
            FROM phieu_muon pm
            LEFT JOIN giang_vien gv ON pm.id_giang_vien = gv.id_giang_vien
            LEFT JOIN lop_hoc_phan lhp ON pm.ten_lop = lhp.ma_lop_hp
            $where_clause
            ORDER BY pm.ngay_muon DESC
        ";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            // Lấy danh sách thiết bị sử dụng
            $d_stmt = $db->prepare("
                SELECT ct.*, tb.ten_thiet_bi 
                FROM chi_tiet_phieu_muon ct 
                JOIN thiet_bi tb ON ct.id_thiet_bi = tb.id 
                WHERE ct.id_phieu_muon = :id_pm
            ");
            $d_stmt->execute(['id_pm' => $row['id']]);
            $items = $d_stmt->fetchAll();
            
            $devices_text_arr = [];
            foreach ($items as $item) {
                $devices_text_arr[] = $item['ten_thiet_bi'] . " (x" . $item['so_luong'] . ")";
            }
            $devices_text = implode(", ", $devices_text_arr);
            
            $display_lop = !empty($row['ten_hoc_phan']) ? $row['ten_hoc_phan'] . ' (' . $row['ten_lop'] . ')' : $row['ten_lop'];
            
            $rows[] = [
                xl_cell(date('d/m/Y H:i', strtotime($row['ngay_muon'])), 'center'),
                xl_cell($row['ten_giang_vien'] ?: 'Chưa xác định', 'left'),
                xl_cell($row['email_xac_nhan'], 'left'),
                xl_cell($display_lop, 'left'),
                xl_cell($devices_text, 'left'),
                xl_cell($row['tinh_trang_chung'], 'left')
            ];
        }
    } catch (PDOException $e) {}
    
    $xlsx = \Shuchkin\SimpleXLSXGen::fromArray($rows, 'Nhật ký chi tiết');
    
    // Ghép các ô thông tin
    $xlsx->mergeCells('A1:F1'); // Title
    $xlsx->mergeCells('A3:B3'); // Nhãn 1
    $xlsx->mergeCells('C3:F3'); // Giá trị 1
    $xlsx->mergeCells('A4:B4'); // Nhãn 2
    $xlsx->mergeCells('C4:F4'); // Giá trị 2
    
    // Độ rộng cột
    $xlsx->setColWidth('A', 18);
    $xlsx->setColWidth('B', 22);
    $xlsx->setColWidth('C', 24);
    $xlsx->setColWidth('D', 24);
    $xlsx->setColWidth('E', 32);
    $xlsx->setColWidth('F', 24);
    
    $xlsx->downloadAs('nhat_ky_su_dung_thiet_bi_' . date('Ymd_His') . '.xlsx');
    exit;
}
