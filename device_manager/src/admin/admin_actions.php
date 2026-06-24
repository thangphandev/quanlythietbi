<?php
/**
 * admin/admin_actions.php
 * =======================
 * Xử lý tất cả các hành động quản trị (POST requests):
 * - Thêm thiết bị mới
 * - Sửa thông tin thiết bị
 * - Xóa thiết bị
 * - Reset số lượng khả dụng
 * - Thu hồi (trả thiết bị)
 * - Nhập danh sách thiết bị từ CSV
 */

/**
 * Tạo ảnh nhỏ (thumbnail) và nén dung lượng duy trì tỉ lệ
 */
function create_thumbnail($src, $dest, $max_w = 200, $max_h = 200) {
    // Kiểm tra xem thư viện GD có được cài đặt không
    if (!function_exists('imagecreatetruecolor')) {
        return false;
    }
    
    list($orig_w, $orig_h, $type) = getimagesize($src);
    if (!$orig_w || !$orig_h) return false;
    
    $ratio = min($max_w / $orig_w, $max_h / $orig_h);
    if ($ratio >= 1) {
        $new_w = $orig_w;
        $new_h = $orig_h;
    } else {
        $new_w = round($orig_w * $ratio);
        $new_h = round($orig_h * $ratio);
    }
    
    $new_img = imagecreatetruecolor($new_w, $new_h);
    
    $source = null;
    switch ($type) {
        case IMAGETYPE_JPEG:
            if (function_exists('imagecreatefromjpeg')) {
                $source = @imagecreatefromjpeg($src);
            }
            break;
        case IMAGETYPE_PNG:
            if (function_exists('imagecreatefrompng')) {
                $source = @imagecreatefrompng($src);
                imagealphablending($new_img, false);
                imagesavealpha($new_img, true);
            }
            break;
        case IMAGETYPE_WEBP:
            if (function_exists('imagecreatefromwebp')) {
                $source = @imagecreatefromwebp($src);
            }
            break;
        case IMAGETYPE_GIF:
            if (function_exists('imagecreatefromgif')) {
                $source = @imagecreatefromgif($src);
            }
            break;
    }
    
    if (!$source) {
        imagedestroy($new_img);
        return false;
    }
    
    imagecopyresampled($new_img, $source, 0, 0, 0, 0, $new_w, $new_h, $orig_w, $orig_h);
    
    $success = false;
    switch ($type) {
        case IMAGETYPE_JPEG:
            if (function_exists('imagejpeg')) {
                $success = imagejpeg($new_img, $dest, 75);
            }
            break;
        case IMAGETYPE_PNG:
            if (function_exists('imagepng')) {
                $success = imagepng($new_img, $dest, 6);
            }
            break;
        case IMAGETYPE_WEBP:
            if (function_exists('imagewebp')) {
                $success = imagewebp($new_img, $dest, 75);
            }
            break;
        case IMAGETYPE_GIF:
            if (function_exists('imagegif')) {
                $success = imagegif($new_img, $dest);
            }
            break;
    }
    
    imagedestroy($new_img);
    imagedestroy($source);
    
    return $success;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['admin_action'])) {
    $action = $_POST['admin_action'];
    
    // 1. THÊM THIẾT BỊ MỚI
    if ($action === 'add_device') {
        $ma = trim($_POST['ma_thiet_bi']);
        $ten = trim($_POST['ten_thiet_bi']);
        $vi_tri = trim($_POST['vi_tri'] ?: 'Phòng thiết bị');
        $nam = intval($_POST['nam_su_dung'] ?: date('Y'));
        $chat_luong = trim($_POST['chat_luong'] ?: 'Tốt');
        $gv_quan_ly = intval($_POST['id_giang_vien_quan_ly']) ?: null;
        $id_loai = intval($_POST['id_loai']) ?: null;
        $tai_lieu_link = trim($_POST['tai_lieu_link'] ?? '');
        
        // Xử lý upload ảnh
        $hinh_anh = null;
        if (isset($_FILES['hinh_anh']) && $_FILES['hinh_anh']['error'] === UPLOAD_ERR_OK) {
            $ext = pathinfo($_FILES['hinh_anh']['name'], PATHINFO_EXTENSION);
            $new_name = 'device_' . uniqid() . '.' . $ext;
            if (move_uploaded_file($_FILES['hinh_anh']['tmp_name'], 'uploads/' . $new_name)) {
                $hinh_anh = $new_name;
                // Tạo ảnh nhỏ (thumbnail) kích thước tối đa 200x200
                create_thumbnail('uploads/' . $new_name, 'uploads/thumb_' . $new_name, 400, 400);
            }
        }
        
        if (empty($ma) || empty($ten)) {
            $error = "Mã thiết bị và Tên thiết bị không được để trống!";
        } else {
            try {
                $stmt = $db->prepare("
                    INSERT INTO thiet_bi (ma_thiet_bi, ten_thiet_bi, vi_tri, nam_su_dung, chat_luong, id_giang_vien_quan_ly, hinh_anh, id_loai, tai_lieu_link)
                    VALUES (:ma, :ten, :vi_tri, :nam, :chat_luong, :gv, :hinh_anh, :id_loai, :tai_lieu_link)
                ");
                $stmt->execute([
                    'ma' => $ma, 'ten' => $ten, 'vi_tri' => $vi_tri, 'nam' => $nam, 'chat_luong' => $chat_luong, 'gv' => $gv_quan_ly, 'hinh_anh' => $hinh_anh, 'id_loai' => $id_loai, 'tai_lieu_link' => $tai_lieu_link
                ]);
                $msg = "🎉 Đã thêm thiết bị mới thành công!";
            } catch (PDOException $e) {
                $error = "Lỗi thêm thiết bị (Mã có thể đã tồn tại): " . $e->getMessage();
            }
        }
    }
    
    // 2. SỬA THÔNG TIN THIẾT BỊ
    if ($action === 'edit_device') {
        $id = intval($_POST['id']);
        $ma = trim($_POST['ma_thiet_bi']);
        $ten = trim($_POST['ten_thiet_bi']);
        $vi_tri = trim($_POST['vi_tri'] ?: 'Phòng thiết bị');
        $nam = intval($_POST['nam_su_dung']);
        $chat_luong = trim($_POST['chat_luong']);
        $gv_quan_ly = intval($_POST['id_giang_vien_quan_ly']) ?: null;
        $id_loai = intval($_POST['id_loai']) ?: null;
        $tai_lieu_link = trim($_POST['tai_lieu_link'] ?? '');
        
        if (empty($ma) || empty($ten)) {
            $error = "Mã thiết bị và Tên thiết bị không được để trống!";
            if (isset($_POST['ajax'])) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => $error]);
                exit;
            }
        } else {
            try {
                // Xử lý upload ảnh mới
                $image_sql = "";
                $params = [
                    'ma' => $ma, 'ten' => $ten, 'vi_tri' => $vi_tri, 'nam' => $nam, 'chat_luong' => $chat_luong, 'gv' => $gv_quan_ly, 'id_loai' => $id_loai, 'tai_lieu_link' => $tai_lieu_link, 'id' => $id
                ];
                
                if (isset($_FILES['hinh_anh']) && $_FILES['hinh_anh']['error'] === UPLOAD_ERR_OK) {
                    $ext = pathinfo($_FILES['hinh_anh']['name'], PATHINFO_EXTENSION);
                    $new_name = 'device_' . uniqid() . '.' . $ext;
                    if (move_uploaded_file($_FILES['hinh_anh']['tmp_name'], 'uploads/' . $new_name)) {
                        $image_sql = ", hinh_anh = :hinh_anh";
                        $params['hinh_anh'] = $new_name;
                        // Tạo ảnh nhỏ (thumbnail) kích thước tối đa 200x200
                        create_thumbnail('uploads/' . $new_name, 'uploads/thumb_' . $new_name, 200, 200);
                    }
                }
                
                $stmt = $db->prepare("
                    UPDATE thiet_bi 
                    SET ma_thiet_bi = :ma, ten_thiet_bi = :ten, vi_tri = :vi_tri, nam_su_dung = :nam, chat_luong = :chat_luong, id_giang_vien_quan_ly = :gv, id_loai = :id_loai, tai_lieu_link = :tai_lieu_link $image_sql 
                    WHERE id = :id
                ");
                $stmt->execute($params);
                
                if (isset($_POST['ajax'])) {
                    // Truy vấn lại thông tin thiết bị đã cập nhật kèm thông tin liên kết loại và giảng viên
                    $query = "
                        SELECT t.*, l.ten_loai, l.ma_mau, gv.ho_ten_gv as ten_gv_quan_ly
                        FROM thiet_bi t
                        LEFT JOIN loai l ON t.id_loai = l.id_loai
                        LEFT JOIN giang_vien gv ON t.id_giang_vien_quan_ly = gv.id_giang_vien
                        WHERE t.id = :id
                    ";
                    $qStmt = $db->prepare($query);
                    $qStmt->execute(['id' => $id]);
                    $updated = $qStmt->fetch(PDO::FETCH_ASSOC);

                    header('Content-Type: application/json');
                    echo json_encode([
                        'success' => true,
                        'message' => 'Cập nhật thiết bị thành công!',
                        'data' => $updated
                    ]);
                    exit;
                }
            
            } catch (PDOException $e) {
                $error = "Lỗi khi cập nhật thiết bị: " . $e->getMessage();
                if (isset($_POST['ajax'])) {
                    header('Content-Type: application/json');
                    echo json_encode(['success' => false, 'message' => $error]);
                    exit;
                }
            }
        }
    }
    
    // 3. XÓA THIẾT BỊ
    if ($action === 'delete_device') {
        $id = intval($_POST['id']);
        try {
            $stmt = $db->prepare("DELETE FROM thiet_bi WHERE id = :id");
            $stmt->execute(['id' => $id]);
            $msg = "🗑 Đã xóa thiết bị thành công!";
        } catch (PDOException $e) {
            $error = "Không thể xóa thiết bị này vì đã phát sinh lịch sử sử dụng!";
        }
    }

    // 3.2 XÓA HÀNG LOẠT THIẾT BỊ
    if ($action === 'delete_devices_bulk') {
        $ids = $_POST['ids'] ?? [];
        if (!empty($ids) && is_array($ids)) {
            $ids = array_map('intval', $ids);
            try {
                $placeholders = implode(',', array_fill(0, count($ids), '?'));
                $stmt = $db->prepare("DELETE FROM thiet_bi WHERE id IN ($placeholders)");
                $stmt->execute($ids);
                $msg = "🗑 Đã xóa hàng loạt " . count($ids) . " thiết bị thành công!";
            } catch (PDOException $e) {
                $error = "Một số thiết bị không thể xóa vì đã phát sinh lịch sử sử dụng!";
            }
        } else {
            $error = "Vui lòng chọn ít nhất một thiết bị để xóa!";
        }
    }
    
    // 4. RESET SỐ LƯỢNG KHẢ DỤNG (Bỏ qua vì không dùng số lượng)
    if ($action === 'reset_all_availability') {
        $msg = "🔄 Tính năng đặt lại số lượng đã được tắt do hệ thống không còn theo dõi số lượng.";
    }
    
    // 5. THÊM LƯỢT SỬ DỤNG THIẾT BỊ
    if ($action === 'add_usage') {
        $id_giang_vien = intval($_POST['id_giang_vien']) ?: null;
        $ngay_muon = !empty($_POST['ngay_muon']) ? date('Y-m-d H:i:s', strtotime($_POST['ngay_muon'])) : date('Y-m-d H:i:s');
        $ten_lop = trim($_POST['ten_lop']);
        $email_xac_nhan = trim($_POST['email_xac_nhan']);
        $tinh_trang_chung = trim($_POST['tinh_trang_chung']);
        $selected_devices = $_POST['thiet_bi'] ?? [];
        
        if (empty($id_giang_vien) || empty($ten_lop) || empty($selected_devices)) {
            $error = "Vui lòng chọn giảng viên, điền tên học phần/mục đích và chọn ít nhất 1 thiết bị!";
        } else {
            try {
                $db->beginTransaction();
                
                // Lưu thông tin phiếu sử dụng
                $stmt = $db->prepare("
                    INSERT INTO phieu_muon (ngay_muon, id_giang_vien, ten_lop, email_xac_nhan, tinh_trang_chung, trang_thai) 
                    VALUES (:ngay_muon, :id_giang_vien, :ten_lop, :email_xac_nhan, :tinh_trang_chung, 'Đang mượn')
                ");
                $stmt->execute([
                    'ngay_muon' => $ngay_muon,
                    'id_giang_vien' => $id_giang_vien,
                    'ten_lop' => $ten_lop,
                    'email_xac_nhan' => $email_xac_nhan,
                    'tinh_trang_chung' => $tinh_trang_chung
                ]);
                $id_phieu = $db->lastInsertId();
                
                // Lưu danh sách thiết bị đi kèm
                $item_stmt = $db->prepare("
                    INSERT INTO chi_tiet_phieu_muon (id_phieu_muon, id_thiet_bi, so_luong, tinh_trang) 
                    VALUES (:id_phieu_muon, :id_thiet_bi, 1, 'Tốt')
                ");
                foreach ($selected_devices as $id_tb) {
                    $item_stmt->execute([
                        'id_phieu_muon' => $id_phieu,
                        'id_thiet_bi' => intval($id_tb)
                    ]);
                }
                
                $db->commit();
                $msg = "🎉 Đã tạo lượt sử dụng mới thành công!";
            } catch (Exception $e) {
                $db->rollBack();
                $error = "Lỗi khi thêm lượt sử dụng: " . $e->getMessage();
            }
        }
    }
    
    // 5d. THÊM LƯỢT SỬ DỤNG NHANH CHO 1 THIẾT BỊ NHOÀI NGÀY
    if ($action === 'add_quick_usage') {
        $id_thiet_bi = intval($_POST['id_thiet_bi']);
        $id_giang_vien = intval($_POST['id_giang_vien']) ?: null;
        $email_xac_nhan = trim($_POST['email_xac_nhan'] ?? '');
        $ten_lop = trim($_POST['ten_lop'] ?? '');
        $tinh_trang_chung = trim($_POST['tinh_trang_chung'] ?? '');
        $ngay_su_dung = $_POST['ngay_su_dung'] ?? [];
        
        if (empty($id_thiet_bi) || empty($id_giang_vien) || empty($ten_lop) || empty($ngay_su_dung)) {
            $error = "Vui lòng nhập đầy đủ thông tin giảng viên, tên học phần và chọn ít nhất một ngày sử dụng!";
        } else {
            try {
                $db->beginTransaction();
                
                foreach ($ngay_su_dung as $date) {
                    $time_part = date('H:i:s');
                    $ngay_muon = $date . ' ' . $time_part;
                    
                    // Lưu thông tin phiếu sử dụng
                    $stmt = $db->prepare("
                        INSERT INTO phieu_muon (ngay_muon, id_giang_vien, ten_lop, email_xac_nhan, tinh_trang_chung, trang_thai) 
                        VALUES (:ngay_muon, :id_giang_vien, :ten_lop, :email_xac_nhan, :tinh_trang_chung, 'Đang mượn')
                    ");
                    $stmt->execute([
                        'ngay_muon' => $ngay_muon,
                        'id_giang_vien' => $id_giang_vien,
                        'ten_lop' => $ten_lop,
                        'email_xac_nhan' => $email_xac_nhan,
                        'tinh_trang_chung' => $tinh_trang_chung
                    ]);
                    $id_phieu = $db->lastInsertId();
                    
                    // Lưu thông tin chi tiết thiết bị đi kèm
                    $item_stmt = $db->prepare("
                        INSERT INTO chi_tiet_phieu_muon (id_phieu_muon, id_thiet_bi, so_luong, tinh_trang) 
                        VALUES (:id_phieu_muon, :id_thiet_bi, 1, :tinh_trang)
                    ");
                    $item_stmt->execute([
                        'id_phieu_muon' => $id_phieu,
                        'id_thiet_bi' => $id_thiet_bi,
                        'tinh_trang' => !empty($tinh_trang_chung) ? $tinh_trang_chung : 'Tốt'
                    ]);
                }
                
                $db->commit();
                $msg = "🎉 Đã tạo nhanh " . count($ngay_su_dung) . " lượt sử dụng thiết bị thành công!";
            } catch (Exception $e) {
                $db->rollBack();
                $error = "Lỗi khi thêm nhanh lượt sử dụng: " . $e->getMessage();
            }
        }
    }
    
    // 5b. SỬA LƯỢT SỬ DỤNG THIẾT BỊ
    if ($action === 'edit_usage') {
        $id = intval($_POST['id']);
        $id_giang_vien = intval($_POST['id_giang_vien']) ?: null;
        $ngay_muon = !empty($_POST['ngay_muon']) ? date('Y-m-d H:i:s', strtotime($_POST['ngay_muon'])) : date('Y-m-d H:i:s');
        $ten_lop = trim($_POST['ten_lop']);
        $email_xac_nhan = trim($_POST['email_xac_nhan']);
        $tinh_trang_chung = trim($_POST['tinh_trang_chung']);
        $selected_devices = $_POST['thiet_bi'] ?? [];
        
        if (empty($id) || empty($id_giang_vien) || empty($ten_lop) || empty($selected_devices)) {
            $error = "Vui lòng chọn giảng viên, điền tên học phần/mục đích và chọn ít nhất 1 thiết bị!";
        } else {
            try {
                $db->beginTransaction();
                
                // Cập nhật thông tin phiếu sử dụng
                $stmt = $db->prepare("
                    UPDATE phieu_muon 
                    SET ngay_muon = :ngay_muon, id_giang_vien = :id_giang_vien, ten_lop = :ten_lop, email_xac_nhan = :email_xac_nhan, tinh_trang_chung = :tinh_trang_chung 
                    WHERE id = :id
                ");
                $stmt->execute([
                    'ngay_muon' => $ngay_muon,
                    'id_giang_vien' => $id_giang_vien,
                    'ten_lop' => $ten_lop,
                    'email_xac_nhan' => $email_xac_nhan,
                    'tinh_trang_chung' => $tinh_trang_chung,
                    'id' => $id
                ]);
                
                // Xóa chi tiết cũ
                $del_stmt = $db->prepare("DELETE FROM chi_tiet_phieu_muon WHERE id_phieu_muon = :id_phieu_muon");
                $del_stmt->execute(['id_phieu_muon' => $id]);
                
                // Ghi lại chi tiết mới
                $item_stmt = $db->prepare("
                    INSERT INTO chi_tiet_phieu_muon (id_phieu_muon, id_thiet_bi, so_luong, tinh_trang) 
                    VALUES (:id_phieu_muon, :id_thiet_bi, 1, 'Tốt')
                ");
                foreach ($selected_devices as $id_tb) {
                    $item_stmt->execute([
                        'id_phieu_muon' => $id,
                        'id_thiet_bi' => intval($id_tb)
                    ]);
                }
                
                $db->commit();
                $msg = "🎉 Đã cập nhật lượt sử dụng thành công!";
            } catch (Exception $e) {
                $db->rollBack();
                $error = "Lỗi khi cập nhật lượt sử dụng: " . $e->getMessage();
            }
        }
    }

    // 5c. XÓA LƯỢT SỬ DỤNG THIẾT BỊ
    if ($action === 'delete_usage') {
        $id = intval($_POST['id']);
        try {
            $db->beginTransaction();
            
            // Xóa chi tiết trước
            $del_stmt = $db->prepare("DELETE FROM chi_tiet_phieu_muon WHERE id_phieu_muon = :id_phieu_muon");
            $del_stmt->execute(['id_phieu_muon' => $id]);
            
            // Xóa phiếu sử dụng
            $stmt = $db->prepare("DELETE FROM phieu_muon WHERE id = :id");
            $stmt->execute(['id' => $id]);
            
            $db->commit();
            $msg = "🗑️ Đã xóa lượt sử dụng thành công!";
        } catch (Exception $e) {
            $db->rollBack();
            $error = "Lỗi khi xóa lượt sử dụng: " . $e->getMessage();
        }
    }
    
    // 6. NHẬP DANH SÁCH THIẾT BỊ TỪ CSV (IMPORT CSV)
    if ($action === 'import_csv') {
        if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['csv_file']['tmp_name'];
            
            // Đọc nội dung file thô
            $file_content = file_get_contents($file);
            
            // 1. PHÁT HIỆN FILE EXCEL THỰC TẾ (.xlsx / zip)
            if (str_starts_with($file_content, "PK\x03\x04")) {
                $error = "⚠️ Tệp tin tải lên không phải định dạng CSV! Có vẻ bạn đã chọn nhầm tệp Excel thực tế (.xlsx). Vui lòng mở tệp đó trên Excel, chọn 'Save As' (Lưu dưới dạng) và chọn định dạng 'CSV (Comma delimited) (*.csv)' rồi tải lên lại.";
            } else {
                // 2. CHUẨN HÓA MÃ HÓA SANG UTF-8 NẾU CẦN
                if (!mb_check_encoding($file_content, 'UTF-8')) {
                    $detected = mb_detect_encoding($file_content, ['UTF-8', 'UTF-16LE', 'UTF-16BE', 'CP1258', 'CP1252', 'ISO-8859-1', 'ASCII'], true);
                    if ($detected && $detected !== 'UTF-8') {
                        $file_content = mb_convert_encoding($file_content, 'UTF-8', $detected);
                    } else {
                        $file_content = mb_convert_encoding($file_content, 'UTF-8', 'CP1258, CP1252, ISO-8859-1');
                    }
                    // Ghi đè lại nội dung đã chuẩn hóa UTF-8 vào file tạm
                    file_put_contents($file, $file_content);
                }
                
                // Đọc file CSV
                if (($handle = fopen($file, "r")) !== FALSE) {
                    // Kiểm tra BOM và bỏ qua
                    $bom = fread($handle, 3);
                    if ($bom !== "\xEF\xBB\xBF") {
                        rewind($handle);
                    }
                    
                    // Đọc hàng tiêu đề và kiểm tra cấu trúc
                    $headers = fgetcsv($handle, 1000, ",");
                    
                    $import_count = 0;
                    $update_count = 0;
                    
                    while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                        if (count($data) >= 2) {
                            $ten = trim($data[0]);
                            $ma = trim($data[1]);
                            $vi_tri = trim($data[2] ?? '') ?: 'Phòng thiết bị';
                            $nam = intval($data[3] ?? 0) ?: date('Y');
                            $qty = intval($data[4] ?? 1) ?: 1;
                            $chat_luong = trim($data[5] ?? 'Tốt') ?: 'Tốt';
                            $gv_name = trim($data[6] ?? '');
                            
                            if (empty($ma) || empty($ten)) continue;
                            
                            // Tìm giảng viên ID bằng tên
                            $gv_id = null;
                            if (!empty($gv_name)) {
                                // Khớp chính xác (không phân biệt hoa thường)
                                $gv_stmt = $db->prepare("SELECT id_giang_vien FROM giang_vien WHERE LOWER(TRIM(ho_ten_gv)) = LOWER(TRIM(:name)) LIMIT 1");
                                $gv_stmt->execute(['name' => $gv_name]);
                                $gv_row = $gv_stmt->fetch();
                                if ($gv_row) {
                                    $gv_id = intval($gv_row['id_giang_vien']);
                                } else {
                                    // Khớp tương đối
                                    $gv_stmt2 = $db->prepare("SELECT id_giang_vien FROM giang_vien WHERE LOWER(ho_ten_gv) LIKE LOWER(:name) LIMIT 1");
                                    $gv_stmt2->execute(['name' => '%' . $gv_name . '%']);
                                    $gv_row2 = $gv_stmt2->fetch();
                                    if ($gv_row2) {
                                        $gv_id = intval($gv_row2['id_giang_vien']);
                                    }
                                }
                            }
                            
                            if ($qty < 1) $qty = 1;
                            
                            for ($i = 1; $i <= $qty; $i++) {
                                // Tạo mã thiết bị cụ thể (TB-001 (No.1), TB-001 (No.2),...)
                                if ($qty > 1) {
                                    $ma_cu_the = "{$ma} (No.{$i})";
                                } else {
                                    $ma_cu_the = $ma;
                                }
                                
                                try {
                                    // Kiểm tra xem đã có thiết bị chưa
                                    $chk = $db->prepare("SELECT id FROM thiet_bi WHERE ma_thiet_bi = :ma");
                                    $chk->execute(['ma' => $ma_cu_the]);
                                    $exists = $chk->fetch();
                                    
                                    if ($exists) {
                                        $stmt = $db->prepare("
                                            UPDATE thiet_bi 
                                            SET ten_thiet_bi = :ten, vi_tri = :vi_tri, nam_su_dung = :nam, chat_luong = :chat_luong, id_giang_vien_quan_ly = :gv, updated_at = NOW()
                                            WHERE ma_thiet_bi = :ma
                                        ");
                                        $stmt->execute([
                                            'ten' => $ten, 'vi_tri' => $vi_tri, 'nam' => $nam, 'chat_luong' => $chat_luong, 'gv' => $gv_id, 'ma' => $ma_cu_the
                                        ]);
                                        $update_count++;
                                    } else {
                                        $stmt = $db->prepare("
                                            INSERT INTO thiet_bi (ma_thiet_bi, ten_thiet_bi, vi_tri, nam_su_dung, chat_luong, id_giang_vien_quan_ly)
                                            VALUES (:ma, :ten, :vi_tri, :nam, :chat_luong, :gv)
                                        ");
                                        $stmt->execute([
                                            'ma' => $ma_cu_the, 'ten' => $ten, 'vi_tri' => $vi_tri, 'nam' => $nam, 'chat_luong' => $chat_luong, 'gv' => $gv_id
                                        ]);
                                        $import_count++;
                                    }
                                } catch (PDOException $e) {}
                            }
                        }
                    }
                    fclose($handle);
                } else {
                    $error = "Không thể mở tệp tin CSV!";
                }
            }
        } else {
            $error = "Vui lòng chọn tệp tin CSV hợp lệ!";
        }
    }

    // 7. THÊM LOẠI THIẾT BỊ MỚI
    if ($action === 'add_category') {
        $ten_loai = trim($_POST['ten_loai']);
        $ma_mau = trim($_POST['ma_mau'] ?? '#3b82f6');
        if (empty($ten_loai)) {
            $error = "Tên loại thiết bị không được để trống!";
        } else {
            try {
                $stmt = $db->prepare("INSERT INTO loai (ten_loai, ma_mau) VALUES (:ten, :mau)");
                $stmt->execute(['ten' => $ten_loai, 'mau' => $ma_mau]);
                $msg = "🎉 Đã thêm loại thiết bị mới thành công!";
            } catch (PDOException $e) {
                $error = "Lỗi khi thêm loại thiết bị (Có thể tên loại đã tồn tại): " . $e->getMessage();
            }
        }
    }

    // 8. CHỈNH SỬA LOẠI THIẾT BỊ
    if ($action === 'edit_category') {
        $id_loai = intval($_POST['id_loai']);
        $ten_loai = trim($_POST['ten_loai']);
        $ma_mau = trim($_POST['ma_mau'] ?? '#3b82f6');
        if (empty($ten_loai) || $id_loai <= 0) {
            $error = "Dữ liệu chỉnh sửa loại không hợp lệ!";
        } else {
            try {
                $stmt = $db->prepare("UPDATE loai SET ten_loai = :ten, ma_mau = :mau WHERE id_loai = :id");
                $stmt->execute(['ten' => $ten_loai, 'mau' => $ma_mau, 'id' => $id_loai]);
                $msg = "🎉 Đã cập nhật loại thiết bị thành công!";
            } catch (PDOException $e) {
                $error = "Lỗi khi cập nhật loại thiết bị (Có thể tên loại đã trùng): " . $e->getMessage();
            }
        }
    }

    // 9. XÓA LOẠI THIẾT BỊ
    if ($action === 'delete_category') {
        $id_loai = intval($_POST['id_loai']);
        if ($id_loai <= 0) {
            $error = "ID loại thiết bị không hợp lệ!";
        } else {
            try {
                $stmt = $db->prepare("DELETE FROM loai WHERE id_loai = :id");
                $stmt->execute(['id' => $id_loai]);
                $msg = "🗑 Đã xóa loại thiết bị thành công!";
            } catch (PDOException $e) {
                $error = "Lỗi khi xóa loại thiết bị: " . $e->getMessage();
            }
        }
    }
}
