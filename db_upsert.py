import re
import unicodedata
from db_core import get_db_connection

def generate_email_from_name(name):
    """Tự động tạo email từ tên giảng viên theo định dạng của trường VLUTE."""
    if not name:
        return None
    # 1. Chuyển chữ thường và loại bỏ khoảng trắng
    name = name.strip().lower()
    # 2. Xử lý ký tự tiếng Việt đặc biệt đ/Đ
    name = name.replace('đ', 'd').replace('Đ', 'd')
    # 3. Loại bỏ dấu
    nfkd_form = unicodedata.normalize('NFKD', name)
    name = "".join([c for c in nfkd_form if not unicodedata.combining(c)])
    # 4. Giữ lại chữ cái, số và khoảng trắng
    name = re.sub(r'[^a-z0-9\s]', '', name)
    parts = name.split()
    if not parts:
        return None
    first_name = parts[-1]
    initials = "".join([part[0] for part in parts[:-1]])
    return f"{first_name}{initials}@vlute.edu.vn"

def upsert_hoc_ky(id_hknh, ten_hoc_ky, ten_nam_hoc, ngay_bat_dau=None, ngay_ket_thuc=None):
    """Lưu hoặc cập nhật thông tin học kỳ."""
    conn = get_db_connection()
    cursor = conn.cursor()
    try:
        cursor.execute(
            """
            INSERT INTO hoc_ky_nam_hoc (id_hocky_namhoc, ten_hoc_ky, ten_nam_hoc, ngay_bat_dau, ngay_ket_thuc)
            VALUES (%s, %s, %s, %s, %s)
            ON CONFLICT (id_hocky_namhoc) DO UPDATE
            SET ten_hoc_ky = EXCLUDED.ten_hoc_ky, 
                ten_nam_hoc = EXCLUDED.ten_nam_hoc,
                ngay_bat_dau = COALESCE(EXCLUDED.ngay_bat_dau, hoc_ky_nam_hoc.ngay_bat_dau),
                ngay_ket_thuc = COALESCE(EXCLUDED.ngay_ket_thuc, hoc_ky_nam_hoc.ngay_ket_thuc)
            """,
            (id_hknh, ten_hoc_ky, ten_nam_hoc, ngay_bat_dau, ngay_ket_thuc)
        )
        conn.commit()
    except Exception as e:
        conn.rollback()
        print(f"[-] Lỗi lưu học kỳ {id_hknh}: {e}")
    finally:
        cursor.close()
        conn.close()


def upsert_giang_vien(id_giang_vien, ho_ten_gv, ten_don_vi, email=None):
    """Lưu hoặc cập nhật thông tin giảng viên."""
    if not email:
        email = generate_email_from_name(ho_ten_gv)
        
    conn = get_db_connection()
    cursor = conn.cursor()
    try:
        cursor.execute(
            """
            INSERT INTO giang_vien (id_giang_vien, ho_ten_gv, ten_don_vi, email)
            VALUES (%s, %s, %s, %s)
            ON CONFLICT (id_giang_vien) DO UPDATE
            SET ho_ten_gv = EXCLUDED.ho_ten_gv, 
                ten_don_vi = EXCLUDED.ten_don_vi,
                email = COALESCE(EXCLUDED.email, giang_vien.email)
            """,
            (id_giang_vien, ho_ten_gv, ten_don_vi, email)
        )
        conn.commit()
    except Exception as e:
        conn.rollback()
        print(f"[-] Lỗi lưu giảng viên {ho_ten_gv} (ID: {id_giang_vien}): {e}")
    finally:
        cursor.close()
        conn.close()

def upsert_lop_hoc_phan(id_lop_hoc_phan, ma_lop_hp, ma_hoc_phan, ten_hoc_phan, lop_ly_thuyet, tin_chi_lt, tin_chi_th, id_hoc_phan):
    """Lưu hoặc cập nhật thông tin lớp học phần."""
    conn = get_db_connection()
    cursor = conn.cursor()
    try:
        cursor.execute(
            """
            INSERT INTO lop_hoc_phan (id_lop_hoc_phan, ma_lop_hp, ma_hoc_phan, ten_hoc_phan, lop_ly_thuyet, tin_chi_lt, tin_chi_th, id_hoc_phan)
            VALUES (%s, %s, %s, %s, %s, %s, %s, %s)
            ON CONFLICT (id_lop_hoc_phan) DO UPDATE
            SET ma_lop_hp = EXCLUDED.ma_lop_hp,
                ma_hoc_phan = EXCLUDED.ma_hoc_phan,
                ten_hoc_phan = EXCLUDED.ten_hoc_phan,
                lop_ly_thuyet = EXCLUDED.lop_ly_thuyet,
                tin_chi_lt = EXCLUDED.tin_chi_lt,
                tin_chi_th = EXCLUDED.tin_chi_th,
                id_hoc_phan = EXCLUDED.id_hoc_phan
            """,
            (id_lop_hoc_phan, ma_lop_hp, ma_hoc_phan, ten_hoc_phan, lop_ly_thuyet, tin_chi_lt, tin_chi_th, id_hoc_phan)
        )
        conn.commit()
    except Exception as e:
        conn.rollback()
        print(f"[-] Lỗi lưu lớp học phần {ma_lop_hp}: {e}")
    finally:
        cursor.close()
        conn.close()

def clear_schedule_for_gv_hk(id_giang_vien, id_hocky_namhoc):
    """Xóa thời khóa biểu của 1 giảng viên trong 1 học kỳ cụ thể trước khi lưu mới (tránh trùng lịch cũ khi có thay đổi)."""
    conn = get_db_connection()
    cursor = conn.cursor()
    try:
        # Xóa cascade sẽ tự động xóa trong bảng lich_giang_day_chi_tiet
        cursor.execute(
            "DELETE FROM lich_giang_day WHERE id_giang_vien = %s AND id_hocky_namhoc = %s",
            (id_giang_vien, id_hocky_namhoc)
        )
        conn.commit()
    except Exception as e:
        conn.rollback()
        print(f"[-] Lỗi dọn lịch cũ của GV {id_giang_vien} học kỳ {id_hocky_namhoc}: {e}")
    finally:
        cursor.close()
        conn.close()

def clear_schedule_for_semester(id_hocky_namhoc):
    """Xóa toàn bộ thời khóa biểu của một học kỳ cụ thể trước khi cào mới."""
    conn = get_db_connection()
    cursor = conn.cursor()
    try:
        # 1. Xóa cascade sẽ tự động xóa trong bảng lich_giang_day_chi_tiet
        cursor.execute(
            "DELETE FROM lich_giang_day WHERE id_hocky_namhoc = %s",
            (id_hocky_namhoc,)
        )
        # 2. Xóa các lớp học phần mồ côi (không còn lịch dạy nào tham chiếu đến ở tất cả các học kỳ)
        cursor.execute(
            "DELETE FROM lop_hoc_phan WHERE id_lop_hoc_phan NOT IN (SELECT DISTINCT id_lop_hoc_phan FROM lich_giang_day)"
        )
        conn.commit()
        print(f"[+] Đã dọn sạch toàn bộ lịch cũ và học phần mồ côi của học kỳ {id_hocky_namhoc}!")

    except Exception as e:
        conn.rollback()
        print(f"[-] Lỗi dọn lịch cũ của học kỳ {id_hocky_namhoc}: {e}")
    finally:
        cursor.close()
        conn.close()


def insert_lich_giang_day(tiet, id_giang_vien, id_hocky_namhoc):
    """Lưu lịch giảng dạy tổng quát và chi tiết theo từng ngày."""
    conn = get_db_connection()
    cursor = conn.cursor()
    try:
        id_lop_hoc_phan = tiet.get("id_lop_hoc_phan")
        id_thoi_gian_hoc = tiet.get("id_thoi_gian_hoc")
        ten_thoi_gian_hoc = tiet.get("ten_thoi_gian_hoc")
        tg_bat_dau = tiet.get("tg_bat_dau")
        tg_ket_thuc = tiet.get("tg_ket_thuc")
        ten_phong = tiet.get("ten_phong")
        thu = tiet.get("thu")
        if thu:
            thu = thu.strip()
            if thu.lower() == "chủ nhật":
                thu = "Chủ Nhật"
        tiet_bd = tiet.get("tiet_bd")
        tiet_kt = tiet.get("tiet_kt")
        so_sinh_vien = tiet.get("so_sinh_vien", 0)

        # 1. Chèn lịch tổng quát
        cursor.execute(
            """
            INSERT INTO lich_giang_day (
                id_lop_hoc_phan, id_giang_vien, id_thoi_gian_hoc, ten_thoi_gian_hoc,
                tg_bat_dau, tg_ket_thuc, ten_phong, thu, tiet_bd, tiet_kt, so_sinh_vien, id_hocky_namhoc
            ) VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s)
            RETURNING id
            """,
            (id_lop_hoc_phan, id_giang_vien, id_thoi_gian_hoc, ten_thoi_gian_hoc,
             tg_bat_dau, tg_ket_thuc, ten_phong, thu, tiet_bd, tiet_kt, so_sinh_vien, id_hocky_namhoc)
        )
        id_lich = cursor.fetchone()[0]

        # 2. Chèn các ngày chi tiết từ danh sách weeks
        weeks = tiet.get("weeks", [])
        for w in weeks:
            ngay_day = w.get("day")
            tuan = w.get("week")
            if ngay_day:
                cursor.execute(
                    """
                    INSERT INTO lich_giang_day_chi_tiet (id_lich_giang_day, ngay_day, tuan)
                    VALUES (%s, %s, %s)
                    """,
                    (id_lich, ngay_day, tuan)
                )
        
        conn.commit()
    except Exception as e:
        conn.rollback()
        print(f"[-] Lỗi lưu tiết học: {e}")
    finally:
        cursor.close()
        conn.close()
