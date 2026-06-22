"""
crawler.py
==========
Mô-đun thu thập thời khóa biểu tự động từ VLUTE và lưu trữ vào PostgreSQL.
Hỗ trợ cơ chế cập nhật thông minh (UPSERT) và lọc bỏ các môn ngoài giờ.
"""

import sys
import os
import re
import html
import time
import pickle
import requests
import db_helper

sys.stdout.reconfigure(encoding="utf-8")

BASE_URL = "https://daotao.vlute.edu.vn"
COOKIE_FILE = "cookies_gv.pkl"

# Khai báo callback cập nhật tiến độ (dành cho Flask background thread)
PROGRESS_CALLBACK = None

# Đọc cấu hình từ file .env
ENV = db_helper.load_env()
USERNAME = ENV.get("VLUTE_USERNAME", "thangpm@vlute.edu.vn")
PASSWORD = ENV.get("VLUTE_PASSWORD", "Thang@***@08102003")

def _make_session() -> requests.Session:
    s = requests.Session()
    s.headers.update({
        "User-Agent":        "Mozilla/5.0 (Windows NT 10.0; Win64; x64)",
        "Accept":            "application/json",
        "X-Requested-With":  "XMLHttpRequest",
    })
    return s

def _load_cookies(session: requests.Session) -> bool:
    if os.path.exists(COOKIE_FILE):
        with open(COOKIE_FILE, "rb") as f:
            session.cookies.update(pickle.load(f))
        print(f"[*] Đã tải Cookies ← '{COOKIE_FILE}'")
        return True
    return False

def _save_cookies(session: requests.Session):
    with open(COOKIE_FILE, "wb") as f:
        pickle.dump(session.cookies, f)
    print(f"[*] Đã lưu Cookies → '{COOKIE_FILE}'")

def _login(session: requests.Session) -> bool:
    print("[*] Đang tiến hành đăng nhập SSO cổng đào tạo VLUTE...")
    try:
        res = session.get(f"{BASE_URL}/giang-vien/thong-tin-giang-vien", timeout=15)
        if res.status_code != 200:
            print(f"[-] Cổng đào tạo VLUTE trả về mã lỗi HTTP {res.status_code}. Máy chủ trường có thể đang bảo trì hoặc quá tải.")
            return False

        if "sso.vlute.edu.vn" not in res.url:
            print("[+] Đã có session hợp lệ!")
            return True

        m = re.search(r'action="([^"]+login-actions/authenticate[^"]+)"', res.text)
        if not m:
            m = re.search(r'"loginAction"\s*:\s*"([^"]+)"', res.text)
            
        if not m:
            print("[-] Không tìm thấy URL đăng nhập SSO trong trang phản hồi.")
            return False

        action_url = html.unescape(m.group(1)).replace('\\/', '/')
        resp = session.post(action_url, data={
            "credentialId": "",
            "username": USERNAME,
            "password": PASSWORD,
        }, timeout=15)

        if "sso.vlute.edu.vn" not in resp.url and resp.status_code == 200:
            print("[+] ĐĂNG NHẬP SSO THÀNH CÔNG!")
            _save_cookies(session)
            return True
        
        print("[-] Đăng nhập thất bại. Vui lòng kiểm tra tài khoản hoặc mật khẩu trong file .env.")
        return False
    except Exception as e:
        print(f"[-] Lỗi kết nối khi đăng nhập SSO: {e}")
        return False

def get_session() -> requests.Session | None:
    s = _make_session()
    has_cookies = _load_cookies(s)
    
    is_valid = False
    if has_cookies:
        try:
            probe = s.get(f"{BASE_URL}/api/giang-vien/tien-ich/init-tra-tkb-dv", timeout=10)
            if probe.status_code == 200 and "sso.vlute.edu.vn" not in probe.url:
                probe.json() # Kiểm tra JSON hợp lệ
                print("[+] Session hiện tại vẫn còn hiệu lực.")
                is_valid = True
        except Exception as e:
            print(f"[!] Lỗi khi kiểm tra session cũ: {e}")
            
    if is_valid:
        return s

    print("[!] Session hết hạn hoặc không hợp lệ. Đang tự động dọn dẹp cookie lỗi và đăng nhập mới...")
    s.cookies.clear()
    if os.path.exists(COOKIE_FILE):
        try:
            os.remove(COOKIE_FILE)
            print(f"[*] Đã xóa file cookies không hợp lệ: '{COOKIE_FILE}'")
        except Exception as e:
            print(f"[-] Không thể xóa file cookies: {e}")
    
    if _login(s):
        try:
            init_res = s.get(f"{BASE_URL}/api/giang-vien/tien-ich/init-tra-tkb-dv", timeout=10)
            if init_res.status_code == 200:
                init_res.json()
                print("[+] Khởi tạo context giảng viên thành công.")
                return s
            else:
                print(f"[-] API init-tra-tkb-dv trả về status code {init_res.status_code} sau đăng nhập mới.")
        except Exception as e:
            print(f"[-] Cảnh báo: Khởi tạo context giảng viên thất bại sau đăng nhập mới ({e}).")
            return s
    return None

# ==============================================================================
# HÀM LỌC BỎ CÁC MÔN HỌC NGOÀI GIỜ (VHVL, KHÓA LUẬN, TIỂU LUẬN)
# ==============================================================================

def should_skip_subject(ten_hoc_phan: str, ma_lop_hp: str, thu: str = "", ngoai_gio: int = 0) -> bool:
    """
    Bỏ qua các môn VHVL, khóa luận và tiểu luận theo đúng yêu cầu nghiệp vụ.
    VHVL thường có tên chứa "vừa học vừa làm" hoặc mã lớp có chứa "vhvl".
    Khóa luận/Tiểu luận tốt nghiệp thường chứa các từ khóa khóa luận, tiểu luận, tốt nghiệp.
    Bỏ qua lịch ngoài giờ hoặc có cờ ngoài giờ.
    """
    name_lower = (ten_hoc_phan or "").lower()
    code_lower = (ma_lop_hp or "").lower()
    thu_lower = (thu or "").strip().lower()
    
    # 1. Kiểm tra Vừa học vừa làm
    if "vừa học vừa làm" in name_lower or "vhvl" in name_lower or "vhvl" in code_lower:
        return True
        
    # 2. Kiểm tra Khóa luận / Tiểu luận tốt nghiệp
    if "khóa luận" in name_lower or "khoa luan" in name_lower:
        return True
    if "tiểu luận" in name_lower or "tieu luan" in name_lower:
        return True
    if "tốt nghiệp" in name_lower or "tot nghiep" in name_lower:
        return True
        
    # 3. Kiểm tra nhãn lớp ngoài giờ
    if "ngoaigio" in code_lower or "ngoài giờ" in name_lower or "ngoai gio" in name_lower:
        return True
        
    # 4. Kiểm tra thứ hoặc cờ ngoài giờ từ API
    if thu_lower == "ngoài giờ" or ngoai_gio == 1:
        return True
        
    return False

# ==============================================================================
# CÁC HÀM CRAWL DỮ LIỆU VÀ NẠP VÀO POSTGRESQL
# ==============================================================================

def sync_semesters(session: requests.Session) -> list[dict]:
    """Đồng bộ danh sách Học kỳ từ API init-tra-tkb-dv về PostgreSQL."""
    print("[*] Đang tải danh sách Học kỳ từ API init-tra-tkb-dv...")
    url = f"{BASE_URL}/api/giang-vien/tien-ich/init-tra-tkb-dv"
    res = session.get(url)
    if res.status_code != 200:
        print(f"[-] Không thể lấy danh sách học kỳ. Status: {res.status_code}")
        return []
    
    try:
        init_data = res.json()
        inner_data = init_data.get("data", {})
        ds_hknh = inner_data.get("ds_hknh", {})
        semesters = ds_hknh.get("data", []) if isinstance(ds_hknh, dict) else []
        
        print(f"[+] Tìm thấy {len(semesters)} học kỳ. Đang đồng bộ vào database...")
        for hk in semesters:
            id_hk = hk.get("id_hocky_namhoc")
            ten_hk = hk.get("ten_hoc_ky")
            ten_nh = hk.get("ten_nam_hoc")
            ngay_bd = hk.get("ngay_bat_dau")
            ngay_kt = hk.get("ngay_ket_thuc")
            if id_hk and ten_hk and ten_nh:
                db_helper.upsert_hoc_ky(id_hk, ten_hk, ten_nh, ngay_bd, ngay_kt)
        return semesters
    except Exception as e:
        print(f"[-] Lỗi đồng bộ học kỳ từ init-tra-tkb-dv: {e}")
        return []

def sync_lecturers_and_schedules(session: requests.Session, id_hocky_namhoc: int):
    """
    1. Lấy danh sách giảng viên trong khoa Cơ khí Động lực (ID đơn vị: 2).
    2. Đồng bộ danh sách giảng viên vào database.
    3. Crawl thời khóa biểu từng giảng viên, thực hiện bộ lọc và lưu vào DB.
    """
    print(f"\n[*] Đang chuẩn bị dọn sạch thời khóa biểu cũ của học kỳ ID {id_hocky_namhoc}...")
    db_helper.clear_schedule_for_semester(id_hocky_namhoc)

    # Lấy ngày bắt đầu và kết thúc của học kỳ từ DB hoặc API
    NGAY_BD = "2026-01-01"
    NGAY_KT = "2026-08-30"
    
    dates = db_helper.get_semester_dates(id_hocky_namhoc)
    if dates and dates.get("ngay_bat_dau") and dates.get("ngay_ket_thuc"):
        NGAY_BD = dates["ngay_bat_dau"]
        NGAY_KT = dates["ngay_ket_thuc"]
        print(f"[+] Sử dụng ngày bắt đầu/kết thúc từ CSDL: {NGAY_BD} -> {NGAY_KT}")
    else:
        # Nếu chưa lưu trong DB, truy vấn qua API init-tra-tkb-dv để lấy trực tiếp
        try:
            print("[*] Đang truy vấn API init-tra-tkb-dv để lấy khoảng thời gian học kỳ...")
            init_res = session.get(f"{BASE_URL}/api/giang-vien/tien-ich/init-tra-tkb-dv")
            if init_res.status_code == 200:
                init_data = init_res.json()
                inner_data = init_data.get("data", {})
                
                # Kiểm tra current_hknh
                current_hk = inner_data.get("current_hknh", {})
                if current_hk and current_hk.get("id_hocky_namhoc") == id_hocky_namhoc:
                    NGAY_BD = current_hk.get("ngay_bat_dau")
                    NGAY_KT = current_hk.get("ngay_ket_thuc")
                else:
                    # Kiểm tra trong ds_hknh
                    ds_hk = inner_data.get("ds_hknh", {})
                    sem_list = ds_hk.get("data", []) if isinstance(ds_hk, dict) else []
                    for sem in sem_list:
                        if sem.get("id_hocky_namhoc") == id_hocky_namhoc:
                            NGAY_BD = sem.get("ngay_bat_dau")
                            NGAY_KT = sem.get("ngay_ket_thuc")
                            break
            print(f"[+] Lấy động ngày từ API thành công: {NGAY_BD} -> {NGAY_KT}")
        except Exception as e:
            print(f"[-] Cảnh báo: Lỗi khi tự động lấy ngày học kỳ từ API ({e}). Sử dụng khoảng thời gian mặc định.")
            
    ID_DON_VI = 2 # Khoa Cơ khí Động lực
    
    print(f"\n[*] Đang lấy danh sách giảng viên khoa CKDL (Đơn vị ID: {ID_DON_VI})...")
    url_gv = f"{BASE_URL}/api/giang-vien/tien-ich/tra-cuu-tkb-dv"
    params = {
        "id_hocky_namhoc": id_hocky_namhoc,
        "ngay_bd": NGAY_BD,
        "ngay_kt": NGAY_KT,
        "arr_don_vi[]": ID_DON_VI
    }
    
    lecturers = []
    try:
        res = session.get(url_gv, params=params)
        if res.status_code == 200:
            data = res.json()
            gv_dict = {}
            inner_data = data.get("data", {}) if isinstance(data, dict) else data
            tkb_data = inner_data.get("flattened_tkb") or inner_data.get("tkb") or inner_data
            
            if isinstance(tkb_data, dict):
                for khoa_name, tkb_list in tkb_data.items():
                    if isinstance(tkb_list, list):
                        for item in tkb_list:
                            gv_id = item.get("id_giang_vien")
                            if gv_id and gv_id not in gv_dict:
                                gv_dict[gv_id] = {
                                    "id_giang_vien": gv_id,
                                    "ho_ten_gv": item.get("ho_ten_gv", "Không xác định"),
                                    "ten_don_vi": item.get("ten_don_vi", khoa_name)
                                }
            elif isinstance(tkb_data, list):
                for item in tkb_data:
                    gv_id = item.get("id_giang_vien")
                    if gv_id and gv_id not in gv_dict:
                        gv_dict[gv_id] = {
                            "id_giang_vien": gv_id,
                            "ho_ten_gv": item.get("ho_ten_gv", "Không xác định"),
                            "ten_don_vi": item.get("ten_don_vi", "Khoa Cơ khí Động lực")
                        }
            lecturers = list(gv_dict.values())
        else:
            print(f"[-] Lỗi API danh sách giảng viên: Status {res.status_code}. Sẽ khôi phục từ tệp cục bộ.")
    except Exception as e:
        print(f"[-] Lỗi khi xử lý phản hồi API giảng viên ({e}). Sẽ khôi phục từ tệp cục bộ.")
        
    if not lecturers:
        print("[*] Đang tải danh sách giảng viên từ tệp cục bộ 'danh_sach_gv_khoa.txt'...")
        if os.path.exists("danh_sach_gv_khoa.txt"):
            with open("danh_sach_gv_khoa.txt", "r", encoding="utf-8") as f:
                for line in f:
                    m = re.search(r'ID:\s*(\d+)\s*\|\s*Họ tên:\s*(.+)', line)
                    if m:
                        gv_id = int(m.group(1))
                        gv_name = m.group(2).strip()
                        lecturers.append({
                            "id_giang_vien": gv_id,
                            "ho_ten_gv": gv_name,
                            "ten_don_vi": "Khoa Cơ khí Động lực"
                        })
        else:
            print("[-] Không tìm thấy tệp 'danh_sach_gv_khoa.txt' để khôi phục danh sách giảng viên.")
            
    total_gv = len(lecturers)
    print(f"[+] Tìm thấy {total_gv} giảng viên trong khoa Cơ khí Động lực.")
    
    # 2. Đồng bộ giảng viên vào database
    for gv in lecturers:
        db_helper.upsert_giang_vien(gv["id_giang_vien"], gv["ho_ten_gv"], gv["ten_don_vi"])
    print("[+] Đồng bộ thông tin danh sách giảng viên thành công!")
    
    # 3. Lặp qua từng giảng viên để lấy thời khóa biểu chi tiết
    print(f"\n[*] Bắt đầu crawl thời khóa biểu cho {total_gv} giảng viên học kỳ ID {id_hocky_namhoc}...")
    url_tkb = f"{BASE_URL}/api/giang-vien/giang-day/lich-giang-day/thoi-khoa-bieu"
    
    count_tiet_inserted = 0
    count_tiet_skipped = 0
    
    for idx, gv in enumerate(lecturers, 1):
        gv_id = gv["id_giang_vien"]
        gv_name = gv["ho_ten_gv"]
        
        print(f"  [{idx}/{total_gv}] Đang tải TKB GV {gv_name} (ID: {gv_id})...")
        if PROGRESS_CALLBACK:
            PROGRESS_CALLBACK(idx, total_gv, f"Đang đồng bộ TKB GV {gv_name} ({idx}/{total_gv})...")
        
        try:
            res_tkb = session.get(url_tkb, params={"id_giang_vien": gv_id, "id_hocky_namhoc": id_hocky_namhoc})
            if res_tkb.status_code != 200:
                print(f"    [-] Lỗi tải TKB. Status: {res_tkb.status_code}")
                continue
                
            tkb_res = res_tkb.json()
            inner_tkb = tkb_res.get("data", {}) if isinstance(tkb_res, dict) else tkb_res
            tkb_list = []
            if isinstance(inner_tkb, dict):
                tkb_list = inner_tkb.get("flattened_tkb") or inner_tkb.get("tkb") or inner_tkb.get("lich") or []
            elif isinstance(inner_tkb, list):
                tkb_list = inner_tkb
                
            # Xóa lịch cũ của GV này trong học kỳ để nạp lịch mới sạch sẽ
            db_helper.clear_schedule_for_gv_hk(gv_id, id_hocky_namhoc)
            
            inserted_for_gv = 0
            skipped_for_gv = 0
            
            for tiet in tkb_list:
                ten_hoc_phan = tiet.get("ten_hoc_phan", "")
                ma_lop_hp = tiet.get("ma_lop_hp", "")
                
                # Bộ lọc yêu cầu
                if should_skip_subject(ten_hoc_phan, ma_lop_hp, tiet.get("thu", ""), tiet.get("ngoai_gio", 0)):
                    skipped_for_gv += 1
                    count_tiet_skipped += 1
                    continue
                
                # Upsert Lớp học phần trước (Vì lịch giảng dạy tham chiếu đến)
                db_helper.upsert_lop_hoc_phan(
                    id_lop_hoc_phan=tiet.get("id_lop_hoc_phan"),
                    ma_lop_hp=ma_lop_hp,
                    ma_hoc_phan=tiet.get("ma_hoc_phan", ""),
                    ten_hoc_phan=ten_hoc_phan,
                    lop_ly_thuyet=tiet.get("lop_ly_thuyet", 0),
                    tin_chi_lt=tiet.get("tin_chi_lt", 0),
                    tin_chi_th=tiet.get("tin_chi_th", 0),
                    id_hoc_phan=tiet.get("id_hoc_phan")
                )
                
                # Chèn lịch giảng dạy (Tổng quát và các ngày cụ thể)
                db_helper.insert_lich_giang_day(tiet, gv_id, id_hocky_namhoc)
                inserted_for_gv += 1
                count_tiet_inserted += 1
                
            print(f"    -> Đã nạp {inserted_for_gv} tiết học thực tế (Bỏ qua {skipped_for_gv} tiết ngoài giờ/VHVL/Khóa luận/Tiểu luận)")
            
            # Tránh spam server quá nhanh
            time.sleep(0.2)
            
        except Exception as e:
            print(f"    [-] Lỗi khi xử lý GV {gv_name}: {e}")
            
    print(f"\n[+] HOÀN THÀNH CRAWL HỌC KỲ {id_hocky_namhoc}!")
    print(f"    - Tổng số giảng viên đã đồng bộ: {total_gv}")
    print(f"    - Tổng số tiết học đã nạp vào PostgreSQL: {count_tiet_inserted}")
    print(f"    - Tổng số tiết học đã bỏ qua do không đúng yêu cầu: {count_tiet_skipped}")

def run_sync_all(id_hocky_namhoc=None):
    """Hàm chạy đồng bộ tổng thể."""
    # Khởi tạo DB nếu chưa có
    db_helper.init_database()
    
    session = get_session()
    if not session:
        print("[-] Không thể tạo phiên đăng nhập. Hủy tiến trình crawl.")
        return False
        
    # 1. Đồng bộ học kỳ
    semesters = sync_semesters(session)
    if not semesters:
        print("[-] Không có dữ liệu học kỳ nào.")
        return False
        
    # Chọn học kỳ để crawl (Nếu không truyền vào, mặc định chọn học kỳ đầu tiên/mới nhất)
    target_hk = id_hocky_namhoc
    if not target_hk:
        target_hk = semesters[0].get("id_hocky_namhoc") or semesters[0].get("id")
        
    print(f"\n[*] Lựa chọn Học kỳ để tải Thời khóa biểu: ID = {target_hk}")
    
    # 2. Đồng bộ giảng viên và thời khóa biểu
    sync_lecturers_and_schedules(session, target_hk)
    return True

if __name__ == "__main__":
    # Mặc định học kỳ 89 là Học kỳ 2 năm học 2025-2026 của trường VLUTE
    target_semester = 89
    if len(sys.argv) > 1:
        try:
            target_semester = int(sys.argv[1])
            print(f"[+] Nhận học kỳ chỉ định từ đối số CLI: ID = {target_semester}")
        except ValueError:
            print(f"[-] Đối số học kỳ không hợp lệ '{sys.argv[1]}'. Sử dụng học kỳ mặc định: ID = {target_semester}")
            
    run_sync_all(target_semester)

