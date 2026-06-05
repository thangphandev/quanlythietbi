<?php
/**
 * admin/admin_backup_download.php
 * ===============================
 * Xử lý tạo file dump SQL (.sql) từ CSDL PostgreSQL để download.
 * Chạy trước mọi output HTML vì cần gửi header.
 * Nếu khớp điều kiện, sẽ exit() ngay sau khi xuất xong.
 */

if (isset($_GET['download_backup']) && $_GET['download_backup'] == 1) {
    require_login();
    
    $filename = 'backup_htql_' . date('Y_m_d_His') . '.sql';
    
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    echo "-- ==============================================================================\n";
    echo "-- BACKUP HỆ THỐNG QUẢN LÝ THIẾT BỊ & TKB - VLUTE\n";
    echo "-- Được tạo tự động vào lúc: " . date('d/m/Y H:i:s') . "\n";
    echo "-- ==============================================================================\n\n";
    
    $tables = [
        'hoc_ky_nam_hoc',
        'giang_vien',
        'lop_hoc_phan',
        'lich_giang_day',
        'lich_giang_day_chi_tiet',
        'thiet_bi',
        'phieu_muon',
        'chi_tiet_phieu_muon'
    ];
    
    foreach ($tables as $table) {
        echo "-- ----------------------------------------------------------------------\n";
        echo "-- CẤU TRÚC BẢNG: $table\n";
        echo "-- ----------------------------------------------------------------------\n";
        
        echo "DROP TABLE IF EXISTS \"$table\" CASCADE;\n\n";
        
        if ($table === 'hoc_ky_nam_hoc') {
            echo "CREATE TABLE \"hoc_ky_nam_hoc\" (\n  \"id_hocky_namhoc\" INT PRIMARY KEY,\n  \"ten_hoc_ky\" VARCHAR(50) NOT NULL,\n  \"ten_nam_hoc\" VARCHAR(50) NOT NULL,\n  \"ngay_bat_dau\" DATE,\n  \"ngay_ket_thuc\" DATE\n);\n\n";
        } elseif ($table === 'giang_vien') {
            echo "CREATE TABLE \"giang_vien\" (\n  \"id_giang_vien\" INT PRIMARY KEY,\n  \"ho_ten_gv\" VARCHAR(100) NOT NULL,\n  \"ten_don_vi\" VARCHAR(100) DEFAULT 'Khoa Cơ khí Động lực',\n  \"email\" VARCHAR(100),\n  \"google_sub\" VARCHAR(100),\n  \"created_at\" TIMESTAMPTZ DEFAULT NOW()\n);\n\n";
        } elseif ($table === 'lop_hoc_phan') {
            echo "CREATE TABLE \"lop_hoc_phan\" (\n  \"id_lop_hoc_phan\" INT PRIMARY KEY,\n  \"ma_lop_hp\" VARCHAR(100) NOT NULL,\n  \"ma_hoc_phan\" VARCHAR(50) NOT NULL,\n  \"ten_hoc_phan\" VARCHAR(255) NOT NULL,\n  \"lop_ly_thuyet\" INT DEFAULT 0,\n  \"tin_chi_lt\" INT DEFAULT 0,\n  \"tin_chi_th\" INT DEFAULT 0,\n  \"id_hoc_phan\" INT\n);\n\n";
        } elseif ($table === 'lich_giang_day') {
            echo "CREATE TABLE \"lich_giang_day\" (\n  \"id\" SERIAL PRIMARY KEY,\n  \"id_lop_hoc_phan\" INT NOT NULL,\n  \"id_giang_vien\" INT NOT NULL,\n  \"id_thoi_gian_hoc\" INT,\n  \"ten_thoi_gian_hoc\" VARCHAR(50),\n  \"tg_bat_dau\" TIME NOT NULL,\n  \"tg_ket_thuc\" TIME NOT NULL,\n  \"ten_phong\" VARCHAR(100),\n  \"thu\" VARCHAR(20) NOT NULL,\n  \"tiet_bd\" INT,\n  \"tiet_kt\" INT,\n  \"so_sinh_vien\" INT DEFAULT 0,\n  \"id_hocky_namhoc\" INT\n);\n\n";
        } elseif ($table === 'lich_giang_day_chi_tiet') {
            echo "CREATE TABLE \"lich_giang_day_chi_tiet\" (\n  \"id\" SERIAL PRIMARY KEY,\n  \"id_lich_giang_day\" INT NOT NULL,\n  \"ngay_day\" DATE NOT NULL,\n  \"tuan\" INT\n);\n\n";
        } elseif ($table === 'thiet_bi') {
            echo "CREATE TABLE \"thiet_bi\" (\n  \"id\" SERIAL PRIMARY KEY,\n  \"ma_thiet_bi\" VARCHAR(50) UNIQUE NOT NULL,\n  \"ten_thiet_bi\" VARCHAR(255) NOT NULL,\n  \"vi_tri\" VARCHAR(255) DEFAULT 'Phòng thiết bị',\n  \"nam_su_dung\" INT,\n  \"chat_luong\" VARCHAR(255) DEFAULT 'Tốt',\n  \"id_giang_vien_quan_ly\" INT,\n  \"hinh_anh\" VARCHAR(255) DEFAULT NULL\n);\n\n";
        } elseif ($table === 'phieu_muon') {
            echo "CREATE TABLE \"phieu_muon\" (\n  \"id\" SERIAL PRIMARY KEY,\n  \"ngay_muon\" TIMESTAMPTZ DEFAULT NOW(),\n  \"id_giang_vien\" INT,\n  \"ten_lop\" VARCHAR(100),\n  \"email_xac_nhan\" VARCHAR(100),\n  \"tinh_trang_chung\" TEXT,\n  \"trang_thai\" VARCHAR(50) DEFAULT 'Đang mượn',\n  \"created_at\" TIMESTAMPTZ DEFAULT NOW()\n);\n\n";
        } elseif ($table === 'chi_tiet_phieu_muon') {
            echo "CREATE TABLE \"chi_tiet_phieu_muon\" (\n  \"id\" SERIAL PRIMARY KEY,\n  \"id_phieu_muon\" INT NOT NULL,\n  \"id_thiet_bi\" INT NOT NULL,\n  \"so_luong\" INT DEFAULT 1,\n  \"tinh_trang\" VARCHAR(255) DEFAULT 'Tốt',\n  \"ghi_chu\" TEXT\n);\n\n";
        }
        
        echo "-- DỮ LIỆU BẢNG: $table\n";
        try {
            $rows = $db->query("SELECT * FROM \"$table\"")->fetchAll(PDO::FETCH_ASSOC);
            if (!empty($rows)) {
                foreach ($rows as $row) {
                    $keys = array_keys($row);
                    $escaped_keys = array_map(function($k) { return "\"$k\""; }, $keys);
                    $values = array_values($row);
                    $escaped_values = array_map(function($v) use ($db) {
                        if ($v === null) return 'NULL';
                        return $db->quote($v);
                    }, $values);
                    
                    echo "INSERT INTO \"$table\" (" . implode(', ', $escaped_keys) . ") VALUES (" . implode(', ', $escaped_values) . ");\n";
                }
            } else {
                echo "-- (Bảng trống)\n";
            }
        } catch (PDOException $e) {
            echo "-- Lỗi truy xuất dữ liệu: " . $e->getMessage() . "\n";
        }
        echo "\n\n";
    }
    
    echo "-- ==============================================================================\n";
    echo "-- KHỞI TẠO LAI CHỈ MỤC (INDEX)\n";
    echo "-- ==============================================================================\n";
    echo "CREATE INDEX IF NOT EXISTS idx_lich_chi_tiet_ngay ON lich_giang_day_chi_tiet(ngay_day);\n";
    echo "CREATE INDEX IF NOT EXISTS idx_lich_chi_tiet_parent ON lich_giang_day_chi_tiet(id_lich_giang_day);\n";
    echo "CREATE INDEX IF NOT EXISTS idx_lich_giang_day_gv ON lich_giang_day(id_giang_vien);\n";
    echo "CREATE INDEX IF NOT EXISTS idx_lich_giang_day_hk ON lich_giang_day(id_hocky_namhoc);\n";
    echo "CREATE INDEX IF NOT EXISTS idx_lop_hp_ma ON lop_hoc_phan(ma_lop_hp);\n";
    echo "CREATE INDEX IF NOT EXISTS idx_thiet_bi_ma ON thiet_bi(ma_thiet_bi);\n";
    echo "CREATE INDEX IF NOT EXISTS idx_phieu_muon_gv ON phieu_muon(id_giang_vien);\n";
    echo "CREATE INDEX IF NOT EXISTS idx_phieu_muon_ngay ON phieu_muon(ngay_muon DESC);\n";
    
    exit;
}
