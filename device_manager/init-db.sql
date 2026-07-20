-- ==============================================================================
-- FILE CƠ SỞ DỮ LIỆU - QUẢN LÝ THỜI KHÓA BIỂU & THIẾT BỊ BỘ MÔN Ô TÔ ĐIỆN
-- ==============================================================================

-- 1. Bảng Học kỳ năm học (Lưu thông tin học kỳ để lọc trên giao diện)
CREATE TABLE IF NOT EXISTS hoc_ky_nam_hoc (
    id_hocky_namhoc INT PRIMARY KEY,
    ten_hoc_ky VARCHAR(50) NOT NULL,    -- Ví dụ: "Học kỳ 2"
    ten_nam_hoc VARCHAR(50) NOT NULL,    -- Ví dụ: "2025-2026"
    ngay_bat_dau DATE,
    ngay_ket_thuc DATE
);

-- 2. Bảng Giảng viên
CREATE TABLE IF NOT EXISTS giang_vien (
    id_giang_vien INT PRIMARY KEY,       -- ID thực tế từ hệ thống VLUTE (VD: 212, 237)
    ho_ten_gv VARCHAR(100) NOT NULL,     -- Họ tên đầy đủ
    ten_don_vi VARCHAR(100) DEFAULT 'Khoa Cơ khí Động lực',
    email VARCHAR(100),                  -- Email giảng viên
    google_sub VARCHAR(100),             -- ID định danh Google OAuth khi liên kết
    created_at TIMESTAMPTZ DEFAULT NOW()
);

-- 3. Bảng Lớp học phần (Lưu thông tin chi tiết môn học / lớp học phần)
CREATE TABLE IF NOT EXISTS lop_hoc_phan (
    id_lop_hoc_phan INT PRIMARY KEY,     -- ID lớp học phần từ VLUTE (VD: 45008)
    ma_lop_hp VARCHAR(100) NOT NULL,     -- Mã lớp học phần (VD: "252a_OT1523_6_tructiep")
    ma_hoc_phan VARCHAR(50) NOT NULL,    -- Mã môn học (VD: "OT1523")
    ten_hoc_phan VARCHAR(255) NOT NULL,  -- Tên môn học (VD: "Thực tập ô tô Hybrid")
    lop_ly_thuyet INT DEFAULT 0,
    tin_chi_lt INT DEFAULT 0,
    tin_chi_th INT DEFAULT 0,
    id_hoc_phan INT
);

-- 4. Bảng Lịch giảng dạy (Thông tin tổng quát của các tiết học trong tuần)
CREATE TABLE IF NOT EXISTS lich_giang_day (
    id SERIAL PRIMARY KEY,
    id_lop_hoc_phan INT NOT NULL REFERENCES lop_hoc_phan(id_lop_hoc_phan) ON DELETE CASCADE,
    id_giang_vien INT NOT NULL REFERENCES giang_vien(id_giang_vien) ON DELETE CASCADE,
    id_thoi_gian_hoc INT,                -- ID ca/tiết học
    ten_thoi_gian_hoc VARCHAR(50),       -- Tên ca (Ví dụ: "Ca 1 - Ca 2")
    tg_bat_dau TIME NOT NULL,            -- Giờ bắt đầu (Ví dụ: "06:30:00")
    tg_ket_thuc TIME NOT NULL,           -- Giờ kết thúc (Ví dụ: "11:30:00")
    ten_phong VARCHAR(100),              -- Phòng học (Ví dụ: "Xưởng Động lực 2.1")
    thu VARCHAR(20) NOT NULL,            -- Thứ trong tuần (Ví dụ: "Thứ 2")
    tiet_bd INT,                         -- Tiết bắt đầu (Ví dụ: 1)
    tiet_kt INT,                         -- Tiết kết thúc (Ví dụ: 5)
    so_sinh_vien INT DEFAULT 0,
    id_hocky_namhoc INT REFERENCES hoc_ky_nam_hoc(id_hocky_namhoc) ON DELETE CASCADE
);

-- 5. Bảng Lịch giảng dạy chi tiết theo Ngày (Được bóc tách từ danh sách tuần/ngày thực tế)
CREATE TABLE IF NOT EXISTS lich_giang_day_chi_tiet (
    id SERIAL PRIMARY KEY,
    id_lich_giang_day INT NOT NULL REFERENCES lich_giang_day(id) ON DELETE CASCADE,
    ngay_day DATE NOT NULL,              -- Ngày học cụ thể (Ví dụ: "2026-03-20")
    tuan INT                             -- Tuần số mấy
);

-- ==============================================================================
-- CÁC BẢNG QUẢN LÝ THIẾT BỊ MỚI
-- ==============================================================================

-- 5b. Bảng Loại thiết bị
CREATE TABLE IF NOT EXISTS loai (
    id_loai SERIAL PRIMARY KEY,
    ten_loai VARCHAR(100) NOT NULL UNIQUE,
    ma_mau VARCHAR(20) DEFAULT '#0284c7'
);

-- 6. Bảng Thiết bị
CREATE TABLE IF NOT EXISTS thiet_bi (
    id SERIAL PRIMARY KEY,
    ma_thiet_bi VARCHAR(100) UNIQUE NOT NULL,       -- Mã thiết bị dùng cho QR Code (VD: TB-001, TB-002)
    ten_thiet_bi VARCHAR(255) NOT NULL,            -- Tên thiết bị
    vi_tri VARCHAR(255) DEFAULT 'Phòng thiết bị',   -- Vị trí đặt thiết bị (Ví dụ: Xưởng Động lực)
    nam_su_dung INT,                               -- Năm đưa vào sử dụng (Ví dụ: 2022)
    chat_luong VARCHAR(255) DEFAULT 'Tốt',         -- Trạng thái/Tình trạng (Ví dụ: Tốt, Hư hỏng: Lỗi màn hình)
    id_giang_vien_quan_ly INT REFERENCES giang_vien(id_giang_vien) ON DELETE SET NULL, -- Giảng viên phụ trách
    id_loai INT REFERENCES loai(id_loai) ON DELETE SET NULL, -- Phân loại thiết bị
    tai_lieu_link TEXT DEFAULT '',                 -- Đường dẫn thư mục tài liệu Google Drive
    created_at TIMESTAMPTZ DEFAULT NOW(),
    updated_at TIMESTAMPTZ DEFAULT NOW()
);

-- 7. Bảng Phiếu mượn sử dụng thiết bị
CREATE TABLE IF NOT EXISTS phieu_muon (
    id SERIAL PRIMARY KEY,
    ngay_muon TIMESTAMPTZ DEFAULT NOW(),
    id_giang_vien INT REFERENCES giang_vien(id_giang_vien) ON DELETE SET NULL, -- Người mượn
    ten_lop VARCHAR(100),                          -- Tên lớp sử dụng (Mã lớp hoặc "Nghiên cứu - khai thác")
    email_xac_nhan VARCHAR(100),                   -- Email người mượn xác nhận đăng nhập
    tinh_trang_chung TEXT,                         -- Tình trạng chung ghi nhận lúc mượn
    trang_thai VARCHAR(50) DEFAULT 'Đang mượn',    -- Trạng thái: 'Đang mượn', 'Đã trả', 'Lỗi/Hỏng'
    created_at TIMESTAMPTZ DEFAULT NOW()
);

-- 8. Bảng Chi tiết phiếu mượn sử dụng
CREATE TABLE IF NOT EXISTS chi_tiet_phieu_muon (
    id SERIAL PRIMARY KEY,
    id_phieu_muon INT NOT NULL REFERENCES phieu_muon(id) ON DELETE CASCADE,
    id_thiet_bi INT NOT NULL REFERENCES thiet_bi(id) ON DELETE CASCADE,
    so_luong INT DEFAULT 1,                        -- Số lượng mượn cụ thể
    tinh_trang VARCHAR(255) DEFAULT 'Tốt',         -- Tình trạng lúc trả/sử dụng (Ví dụ: Tốt, Hư hỏng: mất phụ kiện)
    ghi_chu TEXT
);

-- ==============================================================================
-- KHỞI TẠO CÁC CHỈ MỤC (INDEX) ĐỂ TỐI ƯU TRUY VẤN
-- ==============================================================================
CREATE INDEX IF NOT EXISTS idx_lich_chi_tiet_ngay ON lich_giang_day_chi_tiet(ngay_day);
CREATE INDEX IF NOT EXISTS idx_lich_chi_tiet_parent ON lich_giang_day_chi_tiet(id_lich_giang_day);
CREATE INDEX IF NOT EXISTS idx_lich_giang_day_gv ON lich_giang_day(id_giang_vien);
CREATE INDEX IF NOT EXISTS idx_lich_giang_day_hk ON lich_giang_day(id_hocky_namhoc);
CREATE INDEX IF NOT EXISTS idx_lop_hp_ma ON lop_hoc_phan(ma_lop_hp);
CREATE INDEX IF NOT EXISTS idx_thiet_bi_ma ON thiet_bi(ma_thiet_bi);
CREATE INDEX IF NOT EXISTS idx_phieu_muon_gv ON phieu_muon(id_giang_vien);
CREATE INDEX IF NOT EXISTS idx_phieu_muon_ngay ON phieu_muon(ngay_muon DESC);

-- ==============================================================================
-- SEED DATA MẪU (DỮ LIỆU ĐỂ KIỂM THỬ)
-- ==============================================================================

-- Thêm giảng viên quản lý mặc định để tránh lỗi khóa ngoại
INSERT INTO giang_vien (id_giang_vien, ho_ten_gv, ten_don_vi, email) VALUES
(237, 'Phan Minh Thắng', 'Bộ môn Ô tô điện - Khoa CKĐL', 'thangpm@vlute.edu.vn'),
(101, 'Nguyễn Văn A', 'Bộ môn Ô tô điện - Khoa CKĐL', 'vana@vlute.edu.vn'),
(102, 'Trần Thị B', 'Bộ môn Ô tô điện - Khoa CKĐL', 'thib@vlute.edu.vn')
ON CONFLICT (id_giang_vien) DO UPDATE
SET ho_ten_gv = EXCLUDED.ho_ten_gv, email = EXCLUDED.email;

-- Thêm phân loại mặc định
INSERT INTO loai (id_loai, ten_loai, ma_mau) VALUES
(1, 'Mô hình dạy học', '#3b82f6'),
(2, 'Thiết bị chẩn đoán', '#10b981'),
(3, 'Thiết bị đo kiểm', '#f59e0b'),
(4, 'Công cụ cầm tay', '#8b5cf6')
ON CONFLICT (id_loai) DO UPDATE SET ten_loai = EXCLUDED.ten_loai, ma_mau = EXCLUDED.ma_mau;

-- Đồng bộ lại sequence của id_loai sau khi chèn cứng ID
SELECT setval('loai_id_loai_seq', COALESCE((SELECT MAX(id_loai) FROM loai), 1));

-- Thêm thiết bị mẫu
INSERT INTO thiet_bi (ma_thiet_bi, ten_thiet_bi, vi_tri, nam_su_dung, chat_luong, id_giang_vien_quan_ly, id_loai) VALUES
('TB001', 'Mô hình hệ thống truyền động xe Hybrid Toyota Prius', 'Phòng thực hành Ô tô điện', 2021, 'Tốt', 237, 1),
('TB002', 'Máy chẩn đoán đa năng Launch X431 Pro V', 'Phòng thiết bị kỹ thuật', 2022, 'Tốt', 237, 2),
('TB003', 'Dao động ký số cầm tay Automotive Oscilloscope Hantek', 'Tủ thiết bị đo kiểm', 2023, 'Tốt', 237, 3),
('TB004', 'Thiết bị kiểm tra dung lượng bình acquy cao áp EV', 'Phòng thực hành Ô tô điện', 2024, 'Tốt', 237, 3),
('TB005', 'Bộ sạc nhanh xe điện di động AC 22kW', 'Nhà xe trung tâm thực hành', 2023, 'Tốt', 101, 4),
('TB006', 'Mô hình cắt động cơ điện xoay chiều đồng bộ nam châm vĩnh cửu PMSM', 'Xưởng Động lực', 2022, 'Hư hỏng: Hỏng cảm biến góc quay rotor', 102, 1)
ON CONFLICT (ma_thiet_bi) DO NOTHING;
