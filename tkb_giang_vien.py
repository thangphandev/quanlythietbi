"""
tkb_giang_vien.py
=================
Module tra cứu Thời Khóa Biểu giảng viên - VLUTE (Dùng API lấy theo từng ID GV)
--------------------------------------------------
Cung cấp các hàm:
  1. get_tkb_gv_by_id(session, id_giang_vien, id_hocky_namhoc)
  2. get_tkb_many_gv_by_ids(session, list_id_gv, id_hocky_namhoc, dict_gv_info)
  3. get_danh_sach_gv_khoa(session, id_hocky_namhoc, id_don_vi, ngay_bd, ngay_kt) 
        -> Lấy danh sách GV từ API tra-cuu-tkb-dv
  4. get_tkb_khoa(session, id_don_vi, id_hocky_namhoc, ngay_bd, ngay_kt)
"""

import sys
import json
import pickle
import os
import re
import html
import time
import requests

sys.stdout.reconfigure(encoding="utf-8")

BASE_URL   = "https://daotao.vlute.edu.vn"
COOKIE_FILE = "cookies_gv.pkl"

USERNAME = "thangpm@vlute.edu.vn"
PASSWORD = "Thang@***@08102003"   # Thay *** bằng mật khẩu thật

# ==============================================================================
# QUẢN LÝ SESSION & ĐĂNG NHẬP
# ==============================================================================

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
    print("[*] Đang đăng nhập SSO...")
    try:
        res = session.get(f"{BASE_URL}/giang-vien/thong-tin-giang-vien", timeout=15)
        if res.status_code != 200:
            print(f"[-] Cổng đào tạo VLUTE trả về mã lỗi HTTP {res.status_code}. Máy chủ trường có thể đang bảo trì hoặc quá tải.")
            return False

        if "sso.vlute.edu.vn" not in res.url:
            print("[+] Đã đăng nhập sẵn!")
            return True

        m = re.search(r'action="([^"]+login-actions/authenticate[^"]+)"', res.text)
        if not m:
            print("[-] Không tìm thấy URL đăng nhập SSO trong trang phản hồi.")
            return False

        action_url = html.unescape(m.group(1))
        resp = session.post(action_url, data={
            "credentialId": "",
            "username": USERNAME,
            "password": PASSWORD,
        }, timeout=15)

        if "sso.vlute.edu.vn" not in resp.url and resp.status_code == 200:
            print("[+] ĐĂNG NHẬP THÀNH CÔNG!")
            _save_cookies(session)
            return True
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
                print("[+] Session hiện tại vẫn hợp lệ.")
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
        except Exception as e:
            print(f"[-] Cảnh báo: Khởi tạo context giảng viên thất bại sau đăng nhập mới ({e}).")
            return s
    return None

# ==============================================================================
# HÀM LẤY DANH SÁCH GIẢNG VIÊN TRONG KHOA TỪ API TRA CỨU
# ==============================================================================

def get_danh_sach_gv_khoa(
    session: requests.Session, 
    id_hocky_namhoc: int, 
    id_don_vi: int, 
    ngay_bd: str, 
    ngay_kt: str
) -> list[dict]:
    """
    Lấy danh sách giảng viên trong 1 khoa bằng cách gọi API tra-cuu-tkb-dv.
    Trả về mảng dạng: [{"id_giang_vien": 123, "ho_ten_gv": "Nguyen Van A", "ten_don_vi": "..."}, ...]
    """
    url = f"{BASE_URL}/api/giang-vien/tien-ich/tra-cuu-tkb-dv"
    params = {
        "id_hocky_namhoc": id_hocky_namhoc,
        "ngay_bd": ngay_bd,
        "ngay_kt": ngay_kt,
        "arr_don_vi[]": id_don_vi
    }
    
    res = session.get(url, params=params)
    if res.status_code != 200:
        return []
        
    try:
        data = res.json()
    except Exception:
        return []
        
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
                    "ten_don_vi": item.get("ten_don_vi", "")
                }
                
    return list(gv_dict.values())

# ==============================================================================
# CÁC HÀM LẤY TKB BẰNG API MỚI (Lấy theo từng ID)
# ==============================================================================

def get_tkb_gv_by_id(session: requests.Session, id_giang_vien: int | str, id_hocky_namhoc: int | None = None) -> list[dict]:
    """
    Lấy TKB của 1 giảng viên cụ thể bằng API lấy theo ID GV.
    """
    url = f"{BASE_URL}/api/giang-vien/giang-day/lich-giang-day/thoi-khoa-bieu"
    params = {"id_giang_vien": id_giang_vien}
    if id_hocky_namhoc:
        params["id_hocky_namhoc"] = id_hocky_namhoc
        
    res = session.get(url, params=params)
    if res.status_code != 200:
        return []
    
    try:
        data = res.json()
        inner_data = data.get("data", {}) if isinstance(data, dict) else data
        if isinstance(inner_data, dict):
            tkb_list = inner_data.get("flattened_tkb") or inner_data.get("tkb") or inner_data.get("lich") or []
            return tkb_list if isinstance(tkb_list, list) else []
        elif isinstance(inner_data, list):
            return inner_data
    except Exception:
        pass
    
    return []

def get_tkb_many_gv_by_ids(session: requests.Session, list_id_gv: list[int | str], id_hocky_namhoc: int | None = None, dict_gv_info: dict = None) -> dict[str, list[dict]]:
    """
    Đầu vào mảng id_giang_vien. Tìm lần lượt từng GV trong mảng.
    Returns: dict { id_gv (str) : [danh sách tiết học] }
    """
    result = {}
    print(f"[*] Đang lấy TKB cho {len(list_id_gv)} giảng viên...")
    for idx, gv_id in enumerate(list_id_gv, 1):
        tkb = get_tkb_gv_by_id(session, gv_id, id_hocky_namhoc)
        result[str(gv_id)] = tkb
        
        ten_gv = "Không xác định"
        if dict_gv_info and str(gv_id) in dict_gv_info:
            ten_gv = dict_gv_info[str(gv_id)].get("ho_ten", ten_gv)
        elif tkb and "ho_ten_gv" in tkb[0]:
            ten_gv = tkb[0]["ho_ten_gv"]
            
        print(f"    - [{idx}/{len(list_id_gv)}] GV {ten_gv} (ID: {gv_id}): {len(tkb)} tiết")
        time.sleep(0.5)  # Delay để không bị server block (tăng từ 0.2s lên 1.5s bảo vệ máy chủ trường)
    return result

def get_tkb_khoa(
    session: requests.Session, 
    id_don_vi: int, 
    id_hocky_namhoc: int, 
    ngay_bd: str, 
    ngay_kt: str
) -> dict[str, list[dict]]:
    """
    1. Lấy danh sách GV trong khoa bằng API tra-cuu-tkb-dv
    2. Lặp qua danh sách lấy được, lấy TKB cho từng GV
    """
    print(f"[*] Lấy danh sách GV cho đơn vị ID {id_don_vi} từ API...")
    ds_gv = get_danh_sach_gv_khoa(session, id_hocky_namhoc, id_don_vi, ngay_bd, ngay_kt)
    print(f"    -> Đã tìm thấy {len(ds_gv)} giảng viên có trong dữ liệu trả về.")
    
    if not ds_gv:
        return {}
        
    list_id_gv = [gv["id_giang_vien"] for gv in ds_gv]
    dict_gv_info = {str(gv["id_giang_vien"]): {"ho_ten": gv["ho_ten_gv"]} for gv in ds_gv}
    
    return get_tkb_many_gv_by_ids(session, list_id_gv, id_hocky_namhoc, dict_gv_info)

# ==============================================================================
# HÀM LƯU KẾT QUẢ RA FILE TXT
# ==============================================================================

def _format_tiet(tiet: dict) -> str:
    """Định dạng 1 bản ghi tiết học thành dòng text dễ đọc."""
    thu       = tiet.get("thu", "")
    ca        = tiet.get("ten_thoi_gian_hoc", "")
    tg_bd     = tiet.get("tg_bat_dau", "")[:5]
    tg_kt     = tiet.get("tg_ket_thuc", "")[:5]
    phong     = tiet.get("ten_phong", "")
    mon       = tiet.get("ten_hoc_phan", "")
    so_sv     = tiet.get("so_sinh_vien", "")
    lop_hp    = tiet.get("ma_lop_hp", "")
    return f"  {thu:<12} {ca:<12} {tg_bd}-{tg_kt}  Phòng: {phong:<20} Môn: {mon} | Lớp: {lop_hp} | SV: {so_sv}"

def save_tkb_to_file(tkb_data: dict[str, list[dict]], file_path: str, dict_gv_info: dict = None):
    """
    Lưu kết quả TKB vào file txt.
    tkb_data: dict { id_gv(str) : [danh sách tiết học] }
    """
    with open(file_path, "w", encoding="utf-8") as f:
        tong_gv   = len(tkb_data)
        tong_tiet = sum(len(v) for v in tkb_data.values())
        f.write(f"KẾT QUẢ THỜI KHÓA BIỂU GIẢNG VIÊN\n")
        f.write(f"Tổng: {tong_gv} giảng viên | {tong_tiet} tiết học\n")
        f.write("=" * 80 + "\n\n")

        for gv_id, tiet_list in tkb_data.items():
            ten_gv = "Không xác định"
            if dict_gv_info and str(gv_id) in dict_gv_info:
                ten_gv = dict_gv_info[str(gv_id)].get("ho_ten", ten_gv)
            elif tiet_list and "ho_ten_gv" in tiet_list[0]:
                ten_gv = tiet_list[0]["ho_ten_gv"]
            
            ten_dv = ""
            if tiet_list and "ten_don_vi" in tiet_list[0]:
                ten_dv = tiet_list[0]["ten_don_vi"]

            f.write(f"GV: {ten_gv} (ID: {gv_id})")
            if ten_dv:
                f.write(f" | Khoa: {ten_dv}")
            f.write(f" | {len(tiet_list)} tiết\n")
            f.write("-" * 80 + "\n")

            if tiet_list:
                for tiet in tiet_list:
                    f.write(_format_tiet(tiet) + "\n")
            else:
                f.write("  (Không có lịch trong khoảng thời gian này)\n")
            f.write("\n")

    print(f"[+] Đã lưu kết quả vào file: '{file_path}'")

# ==============================================================================
# DEMO / USAGE
# ==============================================================================

if __name__ == "__main__":
    print("=" * 60)
    print("  TRA CỨU THỜI KHÓA BIỂU GIẢNG VIÊN - PHIÊN BẢN MỚI")
    print("=" * 60)

    session = get_session()
    if session is None:
        sys.exit(1)

    print("-" * 60)
    ID_HOCKY = 89
    NGAY_BD = "2026-01-26"
    NGAY_KT = "2026-07-12"
    
    # -------------------------------------------------------------
    # DEMO 1: NHẬP VÀO MẢNG ID GIẢNG VIÊN ĐỂ LẤY LẦN LƯỢT
    # -------------------------------------------------------------
    ARR_ID_GV = [212, 148, 207]  # Ví dụ nhập mảng ID
    print(f"\n[+] GỌI HÀM get_tkb_many_gv_by_ids(session, {ARR_ID_GV}, {ID_HOCKY})")
    tkb_many = get_tkb_many_gv_by_ids(session, ARR_ID_GV, ID_HOCKY)
    save_tkb_to_file(tkb_many, "tkb_nhieu_gv.txt")

    # -------------------------------------------------------------
    # DEMO 2: LẤY DANH SÁCH GIÁO VIÊN TRONG 1 KHOA (bằng API tra-cuu-tkb-dv)
    # -------------------------------------------------------------
    ID_DON_VI = 2
    print(f"\n[+] GỌI HÀM get_danh_sach_gv_khoa(session, {ID_HOCKY}, {ID_DON_VI}, '{NGAY_BD}', '{NGAY_KT}')")
    ds_khoa = get_danh_sach_gv_khoa(session, ID_HOCKY, ID_DON_VI, NGAY_BD, NGAY_KT)
    if ds_khoa:
        print("    Kết quả danh sách (hiển thị 5 người đầu tiên):")
        print(json.dumps(ds_khoa[:5], indent=2, ensure_ascii=False))
        # Lưu danh sách GV khoa ra file
        with open("danh_sach_gv_khoa.txt", "w", encoding="utf-8") as f:
            f.write(f"DANH SÁCH GIẢNG VIÊN - Đơn vị ID {ID_DON_VI} | Tổng: {len(ds_khoa)} người\n")
            f.write("=" * 60 + "\n")
            for gv in ds_khoa:
                f.write(f"  ID: {gv['id_giang_vien']:<6} | Họ tên: {gv['ho_ten_gv']}\n")
        print("[+] Đã lưu danh sách GV vào file: 'danh_sach_gv_khoa.txt'")

    # -------------------------------------------------------------
    # DEMO 3: LẤY TKB TOÀN KHOA TỰ ĐỘNG (KẾT HỢP CẢ 2 HÀM TRÊN)
    # -------------------------------------------------------------
    print(f"\n[+] GỌI HÀM get_tkb_khoa(session, {ID_DON_VI}, {ID_HOCKY}, '{NGAY_BD}', '{NGAY_KT}')")
    tkb_khoa = get_tkb_khoa(session, ID_DON_VI, ID_HOCKY, NGAY_BD, NGAY_KT)
    tong_so_tiet = sum(len(t) for t in tkb_khoa.values())
    print(f"\n[*] Tổng kết Khoa: Đã tải được {tong_so_tiet} tiết học của {len(tkb_khoa)} giảng viên!")
    save_tkb_to_file(tkb_khoa, f"tkb_khoa_{ID_DON_VI}.txt")
