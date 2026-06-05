import sys
from db_core import get_db_connection
from db_upsert import generate_email_from_name

def main():
    print("[*] Đang kết nối cơ sở dữ liệu để đồng bộ email giảng viên...")
    try:
        conn = get_db_connection()
        cur = conn.cursor()
        
        # Lấy danh sách giảng viên
        cur.execute("SELECT id_giang_vien, ho_ten_gv, email FROM giang_vien")
        rows = cur.fetchall()
        
        print(f"[*] Tìm thấy {len(rows)} giảng viên trên hệ thống. Bắt đầu chuẩn hóa...")
        updated_count = 0
        
        for row in rows:
            gv_id, name, current_email = row
            new_email = generate_email_from_name(name)
            
            if new_email and current_email != new_email:
                cur.execute(
                    "UPDATE giang_vien SET email = %s WHERE id_giang_vien = %s",
                    (new_email, gv_id)
                )
                print(f"  [+] Cập nhật ID {gv_id} ({name}): {current_email or 'Trống'} -> {new_email}")
                updated_count += 1
                
        conn.commit()
        cur.close()
        conn.close()
        print(f"[+] HOÀN THÀNH: Đã chuẩn hóa và cập nhật email cho {updated_count} giảng viên thành công!")
        
    except Exception as e:
        print(f"[-] Lỗi trong quá trình cập nhật email: {e}")

if __name__ == "__main__":
    main()
