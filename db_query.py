from psycopg2.extras import RealDictCursor
from db_core import get_db_connection

def get_all_semesters():
    """Lấy danh sách các học kỳ có trong hệ thống."""
    conn = get_db_connection()
    cursor = conn.cursor(cursor_factory=RealDictCursor)
    try:
        cursor.execute("SELECT * FROM hoc_ky_nam_hoc ORDER BY id_hocky_namhoc DESC")
        return cursor.fetchall()
    except Exception as e:
        print(f"[-] Lỗi truy vấn học kỳ: {e}")
        return []
    finally:
        cursor.close()
        conn.close()

def get_semester_dates(id_hocky_namhoc):
    """Lấy ngày bắt đầu và ngày kết thúc của một học kỳ."""
    conn = get_db_connection()
    cursor = conn.cursor(cursor_factory=RealDictCursor)
    try:
        cursor.execute(
            "SELECT ngay_bat_dau::text, ngay_ket_thuc::text FROM hoc_ky_nam_hoc WHERE id_hocky_namhoc = %s",
            (id_hocky_namhoc,)
        )
        return cursor.fetchone()
    except Exception as e:
        print(f"[-] Lỗi truy vấn ngày học kỳ {id_hocky_namhoc}: {e}")
        return None
    finally:
        cursor.close()
        conn.close()

def get_all_lecturers():
    """Lấy danh sách tất cả giảng viên khoa Cơ khí Động lực."""
    conn = get_db_connection()
    cursor = conn.cursor(cursor_factory=RealDictCursor)
    try:
        cursor.execute("SELECT * FROM giang_vien ORDER BY ho_ten_gv")
        return cursor.fetchall()
    except Exception as e:
        print(f"[-] Lỗi truy vấn giảng viên: {e}")
        return []
    finally:
        cursor.close()
        conn.close()

def get_schedule(id_hocky_namhoc, list_id_gv=None, start_date=None, end_date=None, keyword=None):
    """
    Truy vấn thời khóa biểu với bộ lọc nâng cao.
    - id_hocky_namhoc: Học kỳ bắt buộc
    - list_id_gv: Danh sách ID giảng viên (hoặc None nếu lấy tất cả)
    - start_date / end_date: Khoảng thời gian cụ thể (YYYY-MM-DD)
    - keyword: Tìm kiếm theo tên môn, phòng học, mã lớp
    """
    conn = get_db_connection()
    cursor = conn.cursor(cursor_factory=RealDictCursor)
    try:
        sql = """
            SELECT 
                lgd.id,
                lgd.id_giang_vien,
                gv.ho_ten_gv,
                lgd.id_lop_hoc_phan,
                lhp.ma_lop_hp,
                lhp.ten_hoc_phan,
                lgd.ten_thoi_gian_hoc,
                lgd.tg_bat_dau::text as tg_bat_dau,
                lgd.tg_ket_thuc::text as tg_ket_thuc,
                lgd.ten_phong,
                lgd.thu,
                lgd.tiet_bd,
                lgd.tiet_kt,
                lgd.so_sinh_vien,
                -- Gom nhóm các ngày dạy chi tiết thành mảng JSON để gửi về frontend
                COALESCE(
                    json_agg(
                        json_build_object('ngay', lgdct.ngay_day::text, 'tuan', lgdct.tuan)
                        ORDER BY lgdct.ngay_day
                    ) FILTER (WHERE lgdct.ngay_day IS NOT NULL), 
                    '[]'::json
                ) as chi_tiet_ngay
            FROM lich_giang_day lgd
            JOIN giang_vien gv ON lgd.id_giang_vien = gv.id_giang_vien
            JOIN lop_hoc_phan lhp ON lgd.id_lop_hoc_phan = lhp.id_lop_hoc_phan
            LEFT JOIN lich_giang_day_chi_tiet lgdct ON lgd.id = lgdct.id_lich_giang_day
            WHERE lgd.id_hocky_namhoc = %s
        """
        params = [id_hocky_namhoc]

        # 1. Lọc theo danh sách giảng viên
        if list_id_gv:
            sql += " AND lgd.id_giang_vien = ANY(%s)"
            params.append(list_id_gv)

        # 2. Lọc theo khoảng ngày (Nếu lọc chi tiết ngày)
        if start_date and end_date:
            sql += " AND lgdct.ngay_day BETWEEN %s AND %s"
            params.extend([start_date, end_date])
        elif start_date:
            sql += " AND lgdct.ngay_day >= %s"
            params.append(start_date)
        elif end_date:
            sql += " AND lgdct.ngay_day <= %s"
            params.append(end_date)

        # 3. Lọc theo từ khóa tìm kiếm
        if keyword:
            sql += " AND (lhp.ten_hoc_phan ILIKE %s OR lgd.ten_phong ILIKE %s OR lhp.ma_lop_hp ILIKE %s)"
            search_param = f"%{keyword}%"
            params.extend([search_param, search_param, search_param])

        # Gom nhóm theo lịch giảng dạy tổng quát
        sql += """
            GROUP BY lgd.id, gv.ho_ten_gv, lhp.ma_lop_hp, lhp.ten_hoc_phan
            ORDER BY lgd.thu, lgd.tg_bat_dau, gv.ho_ten_gv
        """

        cursor.execute(sql, params)
        return cursor.fetchall()
    except Exception as e:
        print(f"[-] Lỗi truy vấn thời khóa biểu: {e}")
        return []
    finally:
        cursor.close()
        conn.close()

def get_free_busy_lecturers(date_str, start_time_str, end_time_str, list_id_gv=None):
    """
    Trả về đồng thời danh sách giảng viên rảnh và bận trong khoảng thời gian cụ thể của 1 ngày.
    - date_str: Ngày kiểm tra (YYYY-MM-DD)
    - start_time_str: Giờ bắt đầu (HH:MM:SS hoặc HH:MM)
    - end_time_str: Giờ kết thúc (HH:MM:SS hoặc HH:MM)
    - list_id_gv: Danh sách ID giảng viên cần lọc (hoặc None nếu lấy tất cả)
    """
    conn = get_db_connection()
    cursor = conn.cursor(cursor_factory=RealDictCursor)
    try:
        # 1. Truy vấn giảng viên bận dạy
        sql_busy = """
            SELECT DISTINCT 
                gv.id_giang_vien, 
                gv.ho_ten_gv, 
                gv.ten_don_vi, 
                gv.email,
                lhp.ten_hoc_phan,
                lgd.ten_phong,
                lgd.ten_thoi_gian_hoc,
                lgd.tg_bat_dau::text as tg_bat_dau,
                lgd.tg_ket_thuc::text as tg_ket_thuc
            FROM lich_giang_day lgd
            JOIN giang_vien gv ON lgd.id_giang_vien = gv.id_giang_vien
            JOIN lop_hoc_phan lhp ON lgd.id_lop_hoc_phan = lhp.id_lop_hoc_phan
            JOIN lich_giang_day_chi_tiet lgdct ON lgd.id = lgdct.id_lich_giang_day
            WHERE lgdct.ngay_day = %s
              AND lgd.tg_bat_dau < %s 
              AND lgd.tg_ket_thuc > %s
        """
        params_busy = [date_str, end_time_str, start_time_str]
        if list_id_gv:
            sql_busy += " AND gv.id_giang_vien = ANY(%s)"
            params_busy.append(list_id_gv)
            
        sql_busy += " ORDER BY gv.ho_ten_gv"
        
        cursor.execute(sql_busy, params_busy)
        busy_lecturers = cursor.fetchall()

        # Lấy mảng ID giảng viên bận
        busy_ids = [row['id_giang_vien'] for row in busy_lecturers]

        # 2. Truy vấn giảng viên rảnh (Những ai không bận)
        if list_id_gv:
            if busy_ids:
                cursor.execute(
                    """
                    SELECT id_giang_vien, ho_ten_gv, ten_don_vi, email
                    FROM giang_vien
                    WHERE id_giang_vien = ANY(%s) AND id_giang_vien != ALL(%s)
                    ORDER BY ho_ten_gv
                    """,
                    (list_id_gv, busy_ids)
                )
            else:
                cursor.execute(
                    """
                    SELECT id_giang_vien, ho_ten_gv, ten_don_vi, email
                    FROM giang_vien
                    WHERE id_giang_vien = ANY(%s)
                    ORDER BY ho_ten_gv
                    """,
                    (list_id_gv,)
                )
        else:
            if busy_ids:
                cursor.execute(
                    """
                    SELECT id_giang_vien, ho_ten_gv, ten_don_vi, email
                    FROM giang_vien
                    WHERE id_giang_vien != ALL(%s)
                    ORDER BY ho_ten_gv
                    """,
                    (busy_ids,)
                )
            else:
                cursor.execute("SELECT id_giang_vien, ho_ten_gv, ten_don_vi, email FROM giang_vien ORDER BY ho_ten_gv")
            
        free_lecturers = cursor.fetchall()

        return {
            "date": date_str,
            "time_range": f"{start_time_str} - {end_time_str}",
            "busy": busy_lecturers,
            "free": free_lecturers
        }
    except Exception as e:
        print(f"[-] Lỗi truy vấn bận/rảnh: {e}")
        return {"busy": [], "free": []}
    finally:
        cursor.close()
        conn.close()

def get_common_free_sessions(date_str, list_id_gv=None):
    """
    Trả về ma trận rảnh bận của các giảng viên (hoặc tất cả) theo 3 ca:
    - Sáng: 06:30 - 11:50
    - Chiều: 12:30 - 17:50
    - Tối: 18:00 - 21:00
    """
    conn = get_db_connection()
    cursor = conn.cursor(cursor_factory=RealDictCursor)
    try:
        if list_id_gv:
            cursor.execute(
                "SELECT id_giang_vien, ho_ten_gv, ten_don_vi FROM giang_vien WHERE id_giang_vien = ANY(%s) ORDER BY ho_ten_gv",
                (list_id_gv,)
            )
        else:
            cursor.execute("SELECT id_giang_vien, ho_ten_gv, ten_don_vi FROM giang_vien ORDER BY ho_ten_gv")
        lecturers = cursor.fetchall()
        
        if not lecturers:
            return {"lecturers": [], "common_free": {"morning": False, "afternoon": False, "evening": False}}
            
        lecturer_ids = [gv['id_giang_vien'] for gv in lecturers]
        
        # 1. Truy vấn các ca dạy bận của giảng viên trong ngày kiểm tra
        sql_schedules = """
            SELECT 
                lgd.id_giang_vien,
                lgd.tg_bat_dau::text as tg_bat_dau,
                lgd.tg_ket_thuc::text as tg_ket_thuc,
                lhp.ten_hoc_phan,
                lgd.ten_phong
            FROM lich_giang_day lgd
            JOIN lop_hoc_phan lhp ON lgd.id_lop_hoc_phan = lhp.id_lop_hoc_phan
            JOIN lich_giang_day_chi_tiet lgdct ON lgd.id = lgdct.id_lich_giang_day
            WHERE lgdct.ngay_day = %s
              AND lgd.id_giang_vien = ANY(%s)
        """
        cursor.execute(sql_schedules, (date_str, lecturer_ids))
        schedule_rows = cursor.fetchall()
        
        # Nhóm lịch bận theo id_giang_vien
        busy_by_gv = {gv_id: [] for gv_id in lecturer_ids}
        for row in schedule_rows:
            busy_by_gv[row['id_giang_vien']].append(row)
            
        # Khung giờ các ca dạy học chính thức
        # Sáng (06:30-11:50), Chiều (12:30-17:50), Tối (18:00-21:00)
        cai_sang_start = "06:30:00"
        cai_sang_end = "11:50:00"
        cai_chieu_start = "12:30:00"
        cai_chieu_end = "17:50:00"
        cai_toi_start = "18:00:00"
        cai_toi_end = "21:00:00"
        
        def is_overlapping(start1, end1, start2, end2):
            return start1 < end2 and start2 < end1
            
        matrix = []
        common_morning = True
        common_afternoon = True
        common_evening = True
        
        for gv in lecturers:
            gv_id = gv['id_giang_vien']
            gv_busy = busy_by_gv[gv_id]
            
            # Khởi tạo trạng thái rảnh ban đầu
            morning_free = True
            afternoon_free = True
            evening_free = True
            
            morning_busy_detail = None
            afternoon_busy_detail = None
            evening_busy_detail = None
            
            for b in gv_busy:
                b_start = b['tg_bat_dau']
                b_end = b['tg_ket_thuc']
                
                # Check ca sáng
                if is_overlapping(b_start, b_end, cai_sang_start, cai_sang_end):
                    morning_free = False
                    morning_busy_detail = {"subject": b['ten_hoc_phan'], "room": b['ten_phong'], "time": f"{b_start[:5]}-{b_end[:5]}"}
                # Check ca chiều
                if is_overlapping(b_start, b_end, cai_chieu_start, cai_chieu_end):
                    afternoon_free = False
                    afternoon_busy_detail = {"subject": b['ten_hoc_phan'], "room": b['ten_phong'], "time": f"{b_start[:5]}-{b_end[:5]}"}
                # Check ca tối
                if is_overlapping(b_start, b_end, cai_toi_start, cai_toi_end):
                    evening_free = False
                    evening_busy_detail = {"subject": b['ten_hoc_phan'], "room": b['ten_phong'], "time": f"{b_start[:5]}-{b_end[:5]}"}
                    
            if not morning_free: common_morning = False
            if not afternoon_free: common_afternoon = False
            if not evening_free: common_evening = False
            
            matrix.append({
                "id_giang_vien": gv_id,
                "ho_ten_gv": gv['ho_ten_gv'],
                "ten_don_vi": gv['ten_don_vi'],
                "morning": {"free": morning_free, "busy_detail": morning_busy_detail},
                "afternoon": {"free": afternoon_free, "busy_detail": afternoon_busy_detail},
                "evening": {"free": evening_free, "busy_detail": evening_busy_detail}
            })
            
        return {
            "lecturers": matrix,
            "common_free": {
                "morning": common_morning if len(lecturers) > 0 else False,
                "afternoon": common_afternoon if len(lecturers) > 0 else False,
                "evening": common_evening if len(lecturers) > 0 else False
            }
        }
    except Exception as e:
        print(f"[-] Lỗi phân tích buổi rảnh chung: {e}")
        return {"lecturers": [], "common_free": {"morning": False, "afternoon": False, "evening": False}}
    finally:
        cursor.close()
        conn.close()

def get_common_free_slots_range(start_date_str, end_date_str, duration_hours, list_id_gv=None):
    """
    Tìm tất cả các khoảng thời gian rảnh chung của tập hợp giảng viên đã chọn
    có độ dài lớn hơn hoặc bằng duration_hours trong khoảng ngày từ start_date đến end_date.
    Tìm kiếm độc lập trong 2 ca chuẩn hành chính từ 7h sáng đến 17h chiều để tránh giao giữa các giờ nghỉ.
    """
    from datetime import datetime, timedelta
    
    start_dt = datetime.strptime(start_date_str, "%Y-%m-%d")
    end_dt = datetime.strptime(end_date_str, "%Y-%m-%d")
    
    duration_minutes = int(duration_hours * 60)
    
    conn = get_db_connection()
    cursor = conn.cursor(cursor_factory=RealDictCursor)
    
    try:
        # 1. Lấy danh sách giảng viên được chọn hoặc tất cả
        if list_id_gv:
            cursor.execute(
                "SELECT id_giang_vien, ho_ten_gv, ten_don_vi FROM giang_vien WHERE id_giang_vien = ANY(%s) ORDER BY ho_ten_gv",
                (list_id_gv,)
            )
        else:
            cursor.execute("SELECT id_giang_vien, ho_ten_gv, ten_don_vi FROM giang_vien ORDER BY ho_ten_gv")
        lecturers = cursor.fetchall()
        
        if not lecturers:
            return {"slots": [], "total_matches": 0, "message": "Không có giảng viên phù hợp."}
            
        lecturer_ids = [gv['id_giang_vien'] for gv in lecturers]
        
        # 2. Lấy toàn bộ lịch dạy chi tiết trong khoảng ngày cụ thể của các giảng viên này
        sql_schedules = """
            SELECT 
                lgd.id_giang_vien,
                lgdct.ngay_day::text as ngay_day,
                lgd.tg_bat_dau::text as tg_bat_dau,
                lgd.tg_ket_thuc::text as tg_ket_thuc,
                lhp.ten_hoc_phan,
                lgd.ten_phong
            FROM lich_giang_day lgd
            JOIN lop_hoc_phan lhp ON lgd.id_lop_hoc_phan = lhp.id_lop_hoc_phan
            JOIN lich_giang_day_chi_tiet lgdct ON lgd.id = lgdct.id_lich_giang_day
            WHERE lgdct.ngay_day >= %s AND lgdct.ngay_day <= %s
              AND lgd.id_giang_vien = ANY(%s)
        """
        cursor.execute(sql_schedules, (start_date_str, end_date_str, lecturer_ids))
        schedule_rows = cursor.fetchall()
        
        # Nhóm lịch bận theo ngày
        busy_by_date = {}
        curr_dt = start_dt
        while curr_dt <= end_dt:
            busy_by_date[curr_dt.strftime("%Y-%m-%d")] = []
            curr_dt += timedelta(days=1)
            
        for row in schedule_rows:
            date_str = row['ngay_day']
            if date_str in busy_by_date:
                try:
                    start_t = datetime.strptime(row['tg_bat_dau'], "%H:%M:%S").time()
                    end_t = datetime.strptime(row['tg_ket_thuc'], "%H:%M:%S").time()
                except ValueError:
                    start_t = datetime.strptime(row['tg_bat_dau'][:5], "%H:%M").time()
                    end_t = datetime.strptime(row['tg_ket_thuc'][:5], "%H:%M").time()
                    
                start_min = start_t.hour * 60 + start_t.minute
                end_min = end_t.hour * 60 + end_t.minute
                busy_by_date[date_str].append({
                    "start": start_min,
                    "end": end_min,
                    "lecturer_name": next((gv['ho_ten_gv'] for gv in lecturers if gv['id_giang_vien'] == row['id_giang_vien']), "Giảng viên"),
                    "subject": row['ten_hoc_phan'],
                    "room": row['ten_phong']
                })
                
        # Định nghĩa các ca dạy chuẩn học đường từ 7h sáng tới 17h chiều
        sessions = [
            {"name": "Sáng", "start": 420, "end": 710},       # 07:00 - 11:50
            {"name": "Chiều", "start": 750, "end": 1020}      # 12:30 - 17:00
        ]
        
        DAYS_MAP = ["Thứ Hai", "Thứ Ba", "Thứ Tư", "Thứ Năm", "Thứ Sáu", "Thứ Bảy", "Chủ Nhật"]
        matched_slots = []
        
        # Tìm kiếm trên từng ngày một
        curr_dt = start_dt
        while curr_dt <= end_dt:
            date_str = curr_dt.strftime("%Y-%m-%d")
            day_busy = busy_by_date[date_str]
            day_of_week = DAYS_MAP[curr_dt.weekday()]
            
            for session in sessions:
                s_start = session["start"]
                s_end = session["end"]
                
                # Cắt các lịch dạy bận chồng chéo với ca học này
                session_busy = []
                for b in day_busy:
                    overlap_start = max(s_start, b["start"])
                    overlap_end = min(s_end, b["end"])
                    if overlap_start < overlap_end:
                        session_busy.append((overlap_start, overlap_end))
                        
                # Merge các khoảng bận
                session_busy.sort(key=lambda x: x[0])
                merged_busy = []
                for start, end in session_busy:
                    if not merged_busy:
                        merged_busy.append([start, end])
                    else:
                        last = merged_busy[-1]
                        if start < last[1]:
                            last[1] = max(last[1], end)
                        else:
                            merged_busy.append([start, end])
                            
                # Tính toán khoảng trống (rảnh)
                free_intervals = []
                curr_ptr = s_start
                for start, end in merged_busy:
                    if start > curr_ptr:
                        free_intervals.append((curr_ptr, start))
                    curr_ptr = max(curr_ptr, end)
                if curr_ptr < s_end:
                    free_intervals.append((curr_ptr, s_end))
                    
                # Kiểm tra xem có khoảng rảnh nào đủ độ dài yêu cầu
                for f_start, f_end in free_intervals:
                    slot_dur = f_end - f_start
                    if slot_dur >= duration_minutes:
                        start_h = f_start // 60
                        start_m = f_start % 60
                        end_h = f_end // 60
                        end_m = f_end % 60
                        
                        matched_slots.append({
                            "date": date_str,
                            "day_of_week": day_of_week,
                            "session_name": session["name"],
                            "start_time": f"{start_h:02d}:{start_m:02d}",
                            "end_time": f"{end_h:02d}:{end_m:02d}",
                            "duration_hours": round(slot_dur / 60, 2)
                        })
                        
            curr_dt += timedelta(days=1)
            
        # Sắp xếp lịch rảnh theo Ngày, sau đó theo ca
        matched_slots.sort(key=lambda x: (x["date"], x["session_name"] == "Chiều"))
        
        return {
            "slots": matched_slots,
            "total_matches": len(matched_slots),
            "duration_requested": duration_hours,
            "start_date": start_date_str,
            "end_date": end_date_str,
            "lecturers_count": len(lecturers)
        }
    except Exception as e:
        print(f"[-] Lỗi phân tích buổi rảnh chung nâng cao: {e}")
        return {"slots": [], "total_matches": 0, "error": str(e)}
    finally:
        cursor.close()
        conn.close()
