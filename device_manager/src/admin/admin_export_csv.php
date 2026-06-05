<?php
/**
 * admin/admin_export_csv.php
 * =========================
 * Xử lý xuất danh sách thiết bị hoặc lịch sử sử dụng ra file Excel (.xls) có định dạng CSS.
 * Chạy trước mọi output HTML vì cần gửi header.
 * Nếu khớp điều kiện, sẽ exit() ngay sau khi xuất xong.
 */

// 1. XUẤT DANH SÁCH THIẾT BỊ
if (isset($_GET['action']) && $_GET['action'] === 'export_csv') {
    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename="danh_sach_thiet_bi_' . date('Ymd_His') . '.xls"');
    
    // Xuất BOM UTF-8 để Microsoft Excel nhận diện đúng phông chữ
    echo "\xEF\xBB\xBF";
    ?>
    <html xmlns:o="urn:schemas-microsoft-com:office:office"
          xmlns:x="urn:schemas-microsoft-com:office:excel"
          xmlns="http://www.w3.org/TR/REC-html40">
    <head>
        <meta http-equiv="Content-type" content="text/html;charset=utf-8" />
        <!--[if gte mso 9]>
        <xml>
            <x:ExcelWorkbook>
                <x:ExcelWorksheets>
                    <x:ExcelWorksheet>
                        <x:Name>Danh sách thiết bị</x:Name>
                        <x:WorksheetOptions>
                            <x:DisplayGridlines/>
                        </x:WorksheetOptions>
                    </x:ExcelWorksheet>
                </x:ExcelWorksheets>
            </x:ExcelWorkbook>
        </xml>
        <![endif]-->
        <style>
            table { border-collapse: collapse; }
            th, td { border: 0.5pt solid #cbd5e1; font-family: "Segoe UI", Arial, sans-serif; font-size: 10pt; vertical-align: middle; padding: 8px 10px; }
            .title { font-size: 15pt; font-weight: bold; text-align: center; background-color: #f8fafc; color: #1e3a8a; height: 40px; border: none; }
            .subtitle { font-size: 10pt; text-align: center; color: #475569; height: 25px; border: none; }
            .header-row { height: 32px; }
            .header-cell { font-weight: bold; background-color: #1e40af; color: #ffffff; text-align: center; }
            .data-cell { white-space: normal; word-wrap: break-word; }
            .text-center { text-align: center; }
            .text-left { text-align: left; }
        </style>
    </head>
    <body>
    <table>
        <tr>
            <td colspan="6" class="title">DANH SÁCH THIẾT BỊ TRÊN HỆ THỐNG</td>
        </tr>
        <tr>
            <td colspan="6" class="subtitle">Ngày xuất báo cáo: <?= date('d/m/Y H:i:s') ?></td>
        </tr>
        <tr>
            <td colspan="6" style="border:none; height:10px;"></td>
        </tr>
        <tr class="header-row">
            <th style="width: 130px;" class="header-cell">Mã thiết bị</th>
            <th style="width: 280px;" class="header-cell">Tên thiết bị</th>
            <th style="width: 160px;" class="header-cell">Phân loại</th>
            <th style="width: 200px;" class="header-cell">Vị trí đặt</th>
            <th style="width: 110px;" class="header-cell">Năm sử dụng</th>
            <th style="width: 250px;" class="header-cell">Tình trạng chất lượng</th>
        </tr>
        <?php
        try {
            $stmt = $db->query("
                SELECT tb.ma_thiet_bi, tb.ten_thiet_bi, tb.vi_tri, tb.nam_su_dung, tb.chat_luong, l.ten_loai 
                FROM thiet_bi tb 
                LEFT JOIN loai l ON tb.id_loai = l.id_loai 
                ORDER BY tb.ten_thiet_bi ASC
            ");
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                ?>
                <tr>
                    <td class="data-cell text-center" style="mso-number-format:'\@';"><?= htmlspecialchars($row['ma_thiet_bi']) ?></td>
                    <td class="data-cell text-left"><?= htmlspecialchars($row['ten_thiet_bi']) ?></td>
                    <td class="data-cell text-left"><?= htmlspecialchars($row['ten_loai'] ?: 'Chưa phân loại') ?></td>
                    <td class="data-cell text-left"><?= htmlspecialchars($row['vi_tri']) ?></td>
                    <td class="data-cell text-center"><?= htmlspecialchars($row['nam_su_dung']) ?></td>
                    <td class="data-cell text-left"><?= htmlspecialchars($row['chat_luong']) ?></td>
                </tr>
                <?php
            }
        } catch (PDOException $e) {}
        ?>
    </table>
    </body>
    </html>
    <?php
    exit;
}

// 2. XUẤT LỊCH SỬ SỬ DỤNG CỦA TỪNG THIẾT BỊ CỤ THỂ
if (isset($_GET['action']) && $_GET['action'] === 'export_device_history' && isset($_GET['id'])) {
    $id = intval($_GET['id']);
    
    // Lấy thông tin thiết bị
    $tb_stmt = $db->prepare("SELECT * FROM thiet_bi WHERE id = :id");
    $tb_stmt->execute(['id' => $id]);
    $device = $tb_stmt->fetch();
    
    if ($device) {
        header('Content-Type: application/vnd.ms-excel; charset=utf-8');
        header('Content-Disposition: attachment; filename="lich_su_thiet_bi_' . $device['ma_thiet_bi'] . '_' . date('Ymd_His') . '.xls"');
        
        echo "\xEF\xBB\xBF";
        ?>
        <html xmlns:o="urn:schemas-microsoft-com:office:office"
              xmlns:x="urn:schemas-microsoft-com:office:excel"
              xmlns="http://www.w3.org/TR/REC-html40">
        <head>
            <meta http-equiv="Content-type" content="text/html;charset=utf-8" />
            <!--[if gte mso 9]>
            <xml>
                <x:ExcelWorkbook>
                    <x:ExcelWorksheets>
                        <x:ExcelWorksheet>
                            <x:Name>Nhật ký sử dụng</x:Name>
                            <x:WorksheetOptions>
                                <x:DisplayGridlines/>
                            </x:WorksheetOptions>
                        </x:ExcelWorksheet>
                    </x:ExcelWorksheets>
                </x:ExcelWorkbook>
            </xml>
            <![endif]-->
            <style>
                table { border-collapse: collapse; }
                th, td { border: 0.5pt solid #cbd5e1; font-family: "Segoe UI", "Times New Roman", sans-serif; font-size: 10pt; vertical-align: middle; padding: 8px 10px; }
                .title { font-size: 15pt; font-weight: bold; text-align: center; background-color: #f8fafc; color: #1e3a8a; height: 40px; border: none; }
                .info-label { font-weight: bold; background-color: #f1f5f9; color: #334155; text-align: left; width: 180px; }
                .info-value { text-align: left; background-color: #ffffff; }
                .header-row { height: 32px; }
                .header-cell { font-weight: bold; background-color: #1e40af; color: #ffffff; text-align: center; }
                .data-cell { white-space: normal; word-wrap: break-word; }
                .text-center { text-align: center; }
                .text-left { text-align: left; }
            </style>
        </head>
        <body>
        <table>
            <tr>
                <td colspan="6" class="title">BÁO CÁO NHẬT KÝ LỊCH SỬ SỬ DỤNG THIẾT BỊ</td>
            </tr>
            <tr>
                <td colspan="6" style="border:none; height:10px;"></td>
            </tr>
            <!-- Thống kê thông tin thiết bị ở đầu file -->
            <tr>
                <td class="info-label">Mã thiết bị:</td>
                <td class="info-value" colspan="2" style="mso-number-format:'\@';"><?= htmlspecialchars($device['ma_thiet_bi']) ?></td>
                <td class="info-label">Tên thiết bị:</td>
                <td class="info-value" colspan="2"><?= htmlspecialchars($device['ten_thiet_bi']) ?></td>
            </tr>
            <tr>
                <td class="info-label">Vị trí đặt:</td>
                <td class="info-value" colspan="2"><?= htmlspecialchars($device['vi_tri']) ?></td>
                <td class="info-label">Năm sử dụng:</td>
                <td class="info-value" colspan="2"><?= htmlspecialchars($device['nam_su_dung']) ?></td>
            </tr>
            <tr>
                <td class="info-label">Tình trạng hiện tại:</td>
                <td class="info-value" colspan="2"><?= htmlspecialchars($device['chat_luong']) ?></td>
                <td class="info-label">Ngày xuất báo cáo:</td>
                <td class="info-value" colspan="2"><?= date('d/m/Y H:i:s') ?></td>
            </tr>
            <tr>
                <td colspan="6" style="border:none; height:15px;"></td>
            </tr>
            <tr class="header-row">
                <th style="width: 35px;" class="header-cell">TT</th>
                <th style="width: 210px;" class="header-cell">Tình trạng thiết bị</th>
                <th style="width: 140px;" class="header-cell">Thời gian sử dụng</th>
                <th style="width: 150px;" class="header-cell">Mã lớp</th>
                <th style="width: 180px;" class="header-cell">Người sử dụng</th>            
                <th style="width: 220px;" class="header-cell">Email xác nhận</th>
            </tr>
            <?php
            try {
                $stmt = $db->prepare("
                    SELECT pm.ngay_muon, gv.ho_ten_gv AS ten_giang_vien, pm.email_xac_nhan, pm.ten_lop, ct.tinh_trang, ct.ghi_chu
                    FROM chi_tiet_phieu_muon ct
                    JOIN phieu_muon pm ON ct.id_phieu_muon = pm.id
                    LEFT JOIN giang_vien gv ON pm.id_giang_vien = gv.id_giang_vien
                    WHERE ct.id_thiet_bi = :id
                    ORDER BY pm.ngay_muon DESC
                ");
                $stmt->execute(['id' => $id]);
                $idx = 1;
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    ?>
                    <tr>
                        <td class="data-cell text-center"><?= $idx++ ?></td>
                        <td class="data-cell text-left"><?= htmlspecialchars($row['tinh_trang'] . (!empty($row['ghi_chu']) ? ' - ' . $row['ghi_chu'] : '')) ?></td>
                        <td class="data-cell text-center"><?= date('d/m/Y H:i', strtotime($row['ngay_muon'])) ?></td>
                        <td class="data-cell text-center" style="mso-number-format:'\@';"><?= htmlspecialchars($row['ten_lop']) ?></td>
                        <td class="data-cell text-left"><?= htmlspecialchars($row['ten_giang_vien'] ?: 'Chưa xác định') ?></td>
                        <td class="data-cell text-left"><?= htmlspecialchars($row['email_xac_nhan'] ?: '') ?></td>
                    </tr>
                    <?php
                }
            } catch (PDOException $e) {}
            ?>
        </table>
        </body>
        </html>
        <?php
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

    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename="nhat_ky_su_dung_thiet_bi_' . date('Ymd_His') . '.xls"');
    
    echo "\xEF\xBB\xBF";
    ?>
    <html xmlns:o="urn:schemas-microsoft-com:office:office"
          xmlns:x="urn:schemas-microsoft-com:office:excel"
          xmlns="http://www.w3.org/TR/REC-html40">
    <head>
        <meta http-equiv="Content-type" content="text/html;charset=utf-8" />
        <!--[if gte mso 9]>
        <xml>
            <x:ExcelWorkbook>
                <x:ExcelWorksheets>
                    <x:Name>Nhật ký sử dụng chi tiết</x:Name>
                    <x:WorksheetOptions>
                        <x:DisplayGridlines/>
                    </x:WorksheetOptions>
                </x:ExcelWorksheet>
            </x:ExcelWorksheets>
        </x:ExcelWorkbook>
    </xml>
    <![endif]-->
    <style>
        table { border-collapse: collapse; }
        th, td { border: 0.5pt solid #cbd5e1; font-family: "Segoe UI", Arial, sans-serif; font-size: 10pt; vertical-align: middle; padding: 8px 10px; }
        .title { font-size: 15pt; font-weight: bold; text-align: center; background-color: #f8fafc; color: #1e3a8a; height: 40px; border: none; }
        .info-label { font-weight: bold; background-color: #f1f5f9; color: #334155; text-align: left; width: 140px; }
        .info-value { text-align: left; background-color: #ffffff; }
        .header-row { height: 32px; }
        .header-cell { font-weight: bold; background-color: #1e40af; color: #ffffff; text-align: center; }
        .data-cell { white-space: normal; word-wrap: break-word; }
        .text-center { text-align: center; }
        .text-left { text-align: left; }
    </style>
    </head>
    <body>
    <table>
        <tr>
            <td colspan="6" class="title">BÁO CÁO NHẬT KÝ SỬ DỤNG THIẾT BỊ CHI TIẾT</td>
        </tr>
        <tr>
            <td colspan="6" style="border:none; height:20px;"></td>
        </tr>
        <tr>
            <td class="info-label" colspan="2">Thời gian thống kê:</td>
            <td class="info-value" colspan="4"><?= htmlspecialchars($hk_title) ?></td>
        </tr>
        <tr>
            <td class="info-label" colspan="2">Ngày xuất báo cáo:</td>
            <td class="info-value" colspan="4"><?= date('d/m/Y H:i:s') ?></td>
        </tr>
        <tr>
            <td colspan="6" style="border:none; height:20px;"></td>
        </tr>
        <tr class="header-row">
            <th style="width: 140px;" class="header-cell">Thời gian sử dụng</th>
            <th style="width: 180px;" class="header-cell">Giảng viên sử dụng</th>
            <th style="width: 220px;" class="header-cell">Email xác nhận</th>
            <th style="width: 150px;" class="header-cell">Mã lớp / Mục đích</th>
            <th style="width: 280px;" class="header-cell">Thiết bị sử dụng</th>
            <th style="width: 220px;" class="header-cell">Đánh giá chất lượng bàn giao</th>
        </tr>
        <?php
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
            
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                // Lấy danh sách thiết bị
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
                
                ?>
                <tr>
                    <td class="data-cell text-center"><?= date('d/m/Y H:i', strtotime($row['ngay_muon'])) ?></td>
                    <td class="data-cell text-left"><?= htmlspecialchars($row['ten_giang_vien'] ?: 'Chưa xác định') ?></td>
                    <td class="data-cell text-left"><?= htmlspecialchars($row['email_xac_nhan']) ?></td>
                    <td class="data-cell text-center" style="mso-number-format:'\@';"><?= htmlspecialchars($row['ten_lop']) ?></td>
                    <td class="data-cell text-left"><?= htmlspecialchars($devices_text) ?></td>
                    <td class="data-cell text-left"><?= htmlspecialchars($row['tinh_trang_chung']) ?></td>
                </tr>
                <?php
            }
        } catch (PDOException $e) {}
        ?>
    </table>
    </body>
    </html>
    <?php
    exit;
}

