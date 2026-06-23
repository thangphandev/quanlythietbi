import sys
import os
import psycopg2
import db_core

# Đảm bảo in tiếng Việt chuẩn trong console
try:
    sys.stdout.reconfigure(encoding='utf-8')
except Exception:
    pass

DRIVE_DATA = [
    ("Bàn nâng hạ pin di động PL-DT6, Haung-Yu", "https://drive.google.com/drive/folders/1S_AvTEKkZ_zg3Q_wwTYw_MAC7gzSC9Cg"),
    ("Bàn sửa chữa điện đa năng", "https://drive.google.com/drive/folders/1O5oF_n2iZrPM2JpRvvTLpYfdl7QEarEq"),
    ("Bảng điện tử", "https://drive.google.com/drive/folders/1ZCHKksMWamSZMq6utjuyke7JjD-87QNS"),
    ("Bộ thiết bị đào tạo tổng thành xe lai điện (xe Điện Hybrid)", "https://drive.google.com/drive/folders/1f7r-evTzBdgAOIMnT3CWdLlFnmufXJXC"),
    ("Cụm ắc quy cao áp Hybrid", "https://drive.google.com/drive/folders/1L_2PXj8WdxootX4E4r1WLcxbc-Ri03VJ"),
    ("Hệ thống module thực hành, thí nghiệm điện, điện tử trên ô tô (bộ)", "https://drive.google.com/drive/folders/1B-iZhwTthk-3S4OZDKl_u5CmHZRYhZaW"),
    ("Hệ thống truyền động điện trên ô tô điện: Nhãn hiệu Vinfast (Số 02)", "https://drive.google.com/drive/folders/1KpGwIsbkfiZq7ioZ9v4ruqvQDoeHsZug"),
    ("Hệ thống truyền động điện trên ô tô điện: Nhãn hiệu Vinfast (số 01)", "https://drive.google.com/drive/folders/1RdzSsRJlm8mvDz41Kk2dHZwM6GxtUxlZ"),
    ("Hộp số hybrid P112 (tháo lắp)", "https://drive.google.com/drive/folders/1ie4lih-CvlqXD2Vy-kaAWXfYXJ92aOab"),
    ("Hộp số hybrid P410 (tháo lắp)", "https://drive.google.com/drive/folders/1kRvdNQZBiasJr1jj-FD_S1jCPGCVPzjd"),
    ("Hộp số hybrid P410 (tháo lắp)", "https://drive.google.com/drive/folders/1UdHaHHj6czw_W-g3rPrwZnFIoIZay92W"),
    ("Kiểm tra/ nạp ắc quy", "https://drive.google.com/drive/folders/1dYldbeGKmK1HOsrR7kmp4NeFMSDJsEvf"),
    ("MH HT nâng hạ kính cửa sổ và lock cửa", "https://drive.google.com/drive/folders/1waMGOf0cg1Xk4bpInSJ2pBbUXBVnRWZg"),
    ("MH hệ thống điện tổng thành ô tô du lịch đời mới", "https://drive.google.com/drive/folders/1lQaht0HFOsooj6ZkGyS6CEl9XF3b79K2"),
    ("MH ĐC Hybrid + TP-KDMT1", "https://drive.google.com/drive/folders/1s2SYzAckV7okPJHoRVnTL91WMXb2dTgS"),
    ("MHHT chiếu sáng - tín hiệu Ôto đời mới", "https://drive.google.com/drive/folders/1v1K2Y12qBTjIQiKxYFKQHfRZOO_B24Rb"),
    ("Module HT giao tiếp CAN", "https://drive.google.com/drive/folders/1Nlkc8mrlS0RVk3Fm2MVmkCUB1NX5a6M_"),
    ("Module HT gạt nước-rửa kính", "https://drive.google.com/drive/folders/1W0N8iVQQntCszQj-O-OHXANe2kDrh1Yo"),
    ("Module HT kiểm tra theo dõi", "https://drive.google.com/drive/folders/17TdOaLgUtThcmDIj5V3vvfGx13O85u0X"),
    ("Module HT nâng -hạ cửa sổ điện", "https://drive.google.com/drive/folders/11rszIJRIp7PHYh0yAJ-IQ3YDJq753nCz"),
    ("Module HT phun dầu điện tử CDI", "https://drive.google.com/drive/folders/1Gz04G473lNwR4UkBafQ9zZg_gVxkUIZ_"),
    ("Module HT phun xăng-đánh lửa điện tử", "https://drive.google.com/drive/folders/1TZJTX6lcxhlzXGnp16clEoQDFLBiMZHi"),
    ("Module HT tín hiệu chiếu sáng Module tạo pan điện tử HT điều khiển (kết nối máy tính)", "https://drive.google.com/drive/folders/1rk1K-RT-66fonspax03Ipmr144SxSwAV"),
    ("Module HT đ.khiển bướm ga th.minh ETCSi và phối khí thông minh VVTi", "https://drive.google.com/drive/folders/1DW8zCg-shLfKhb_MP9XWpCVaoV6CvyNT"),
    ("Module HT đ.khiển ly hợp lốc lạnh và quạt điều hòa", "https://drive.google.com/drive/folders/1VB_0wZ1PNYV-LqxnnK9f4NzjJ__Fsr0S"),
    ("Module HT điều khiển gương điện", "https://drive.google.com/drive/folders/1VGBR-KrQOhqoSyIozuhVmOAF8uZ5bCn6"),
    ("Module tạo pan điện tử HT điều khiển (kết nối máy tính)", "https://drive.google.com/drive/folders/1DlUr_i1CCyGeizWEtzRqA8P7LcP-1oN-"),
    ("Máy chiếu và màn chiếu", "https://drive.google.com/drive/folders/1V2PKEeYYdYpz0epQ_An8H9fcpw2rz34-"),
    ("Máy tính lắp ráp", "https://drive.google.com/drive/folders/1PlZ5Swqs5gdb-otfaTYs-d0RLfRF7sJZ"),
    ("Máy đo độ cách điện GPT-9603 GW Intek", "https://drive.google.com/drive/folders/17JHCB54q1-PXbvtuRcZBX1Yzq_E100R-"),
    ("Móc cứu hộ cách điện (Sào cách điện cứu hộ)", "https://drive.google.com/drive/folders/1y0MrL9UlJjHMK8umhWLOQ-gjs-HUd0q4"),
    ("Mô hình giám sát pin cao áp: Giám sát pin, đo lường, phụ kiện", "https://drive.google.com/drive/folders/1VkAJpXDh46lwVNuCW1jRVt7OUlSUtN_k"),
    ("Mô hình hệ thống nâng hạ kính và khóa cửa ( Thầy Khải)", "https://drive.google.com/drive/folders/1pOcCLJelTkPdZQI2hFnhHbtmmPdeuDLq"),
    ("Mô hình hệ thống quản lý và cung cấp nguồn cao áp cho hệ động lực trên xe ô tô điện", "https://drive.google.com/drive/folders/1fx2cxG5jrHf2NFm1SWdOokVHI2Atoufb"),
    ("Mô hình hệ thống vận hành xe điện loại 4 chỗ", "https://drive.google.com/drive/folders/1xT9n9UqvQ2nFfs8SdeFjpngnfKz1MnFn"),
    ("Mô hình hệ thống điều khiển gương chiếu hậu ( Thầy Khải)", "https://drive.google.com/drive/folders/164RX45o2PG1Uzv_zz0f-WCb4iEgtYG3i"),
    ("Mô hình hệ thống điện thân xe ( Cô Huyền)", "https://drive.google.com/drive/folders/1aSqBdbADLmkPRo0jYvoIz0gkdGQt9MxP"),
    ("Mô hình hệ thống điện thân xe ( Cô Trúc)", "https://drive.google.com/drive/folders/1Xo6lXltOyndAfVF6278peSyYoc6b7kKk"),
    ("Mô hình hệ thống điện thân xe ( Thầy Hữu)", "https://drive.google.com/drive/folders/1PZ6LbLnNNAueNLXyyCFhc2uCxFkYXEJ3"),
    ("Mô hình hệ thống điện thân xe Ford (962)", "https://drive.google.com/drive/folders/1A27vhtoA3avp9FvRsYg-FpcKSFURsa5C"),
    ("Mô hình hệ thống điện điều khiển trên xe ô tô điện", "https://drive.google.com/drive/folders/1n7Vwbi4hbYzPOg436gJzOTN-0Ck-t5k3"),
    ("Mô hình nghiên cứu khoa học ( Cô My)", "https://drive.google.com/drive/folders/1jrXC8pNbgp1LE8TaHUomo05BCdoJRvxK"),
    ("Mô hình pin cao áp: Mô hình cell pin của hệ thống pin cao áp, máy điện, đo lường", "https://drive.google.com/drive/folders/1yXQj5ObF1h6gy-mrezzxDJOP3mMzAjI4"),
    ("Mô hình tháo lắp, đo kiểm, chuẩn đoán hệ thống truyền lực xe hybrid kiểu kết hợp", "https://drive.google.com/drive/folders/1gDLfr3rc8_YMA02k8f-tdRRggOe7KM5j"),
    ("Mô hình điện thân xe Ford EAU 963", "https://drive.google.com/drive/folders/1UQ5XN3V91i62ucaqQIB1hNpswQppMZW7"),
    ("Tay/Cờ lê cân lực cách điện 1/2\", 20-100Nm", "https://drive.google.com/drive/folders/1uVVWz6LT6tR0ZTMczdEftC248WmcS_Pi"),
    ("Tay/Cờ lê cân lực cách điện 3/8\", 5-25Nm", "https://drive.google.com/drive/folders/1XnMYytCYgJ4WqsySExAr7C0lLD0F4-b0"),
    ("Thiết bị Sạc và kiểm tra Chẩn đoán ắc quy kết hợp", "https://drive.google.com/drive/folders/1YwE8KFtWskonMh7d_xlILUdD3pTXIZt3"),
    ("Thiết bị chuẩn đoán HT ắc quy Hybrid (cho các Ôtô Hybrid dùng điện và xăng kết hợp)", "https://drive.google.com/drive/folders/1cC8Qj1EboIAuZw9t8JCj166jWuyE_P8o"),
    ("Tủ đựng dụng cụ 8 ngăn (Gồm 56 chi tiết cách điện)", "https://drive.google.com/drive/folders/1BUvUGhoCUtfQzCUzOphVOQR_cHY36bFL"),
    ("Xe Mitsubishi L300 xanh", "https://drive.google.com/drive/folders/1hnQvj3_RTnA5MPwkmaY87X_tAA9I35z2"),
    ("Xe Mitsubishi L300 xám", "https://drive.google.com/drive/folders/1mwXddDZ1sYNnUb6xPALEWMYNt_ajvwa_"),
    ("Xe Toyota Hiace 98A - 0158", "https://drive.google.com/drive/folders/1mBQtL2e66j_f808UdgoHIG8n6X2vorxR"),
    ("Xe Toyota Hiace 98A - 0266", "https://drive.google.com/drive/folders/1Xs4OlUpTlur3A4cg-UCTJRzL9UsdlQOk"),
    ("Xe ô tô 51A - 2968 (Corolla)", "https://drive.google.com/drive/folders/1d8xBTWjQ8ysJNX-cLnoVTS_xhZ2ayxj9"),
    ("Xe ô tô 64A - 0493 (Ford Escape)", "https://drive.google.com/drive/folders/1nnJO0oXWS68LKrBxQ7sCl0j64xwNBwGW")
]

def run_update():
    print("🔌 Đang kết nối tới cơ sở dữ liệu...")
    try:
        conn = db_core.get_db_connection()
        conn.autocommit = True
        cur = conn.cursor()
        print("✅ Kết nối CSDL thành công!")
    except Exception as e:
        print(f"❌ Không thể kết nối CSDL: {e}")
        return

    # Lấy toàn bộ danh sách thiết bị hiện tại trong CSDL để so khớp
    cur.execute("SELECT id, ma_thiet_bi, ten_thiet_bi FROM thiet_bi")
    db_devices = cur.fetchall() # Dạng list của tuple: (id, ma_thiet_bi, ten_thiet_bi)
    
    # Chuẩn hóa tên trong DB để so sánh
    # Đọc link và gộp danh sách theo tên
    grouped_links = {}
    for name, link in DRIVE_DATA:
        norm_name = name.strip().lower()
        if norm_name not in grouped_links:
            grouped_links[norm_name] = []
        # Tránh trùng lặp link cho cùng 1 tên
        if link not in grouped_links[norm_name]:
            grouped_links[norm_name].append(link)

    updated_count = 0
    matched_set = set()
    unmatched_names = []

    print("\n🚀 Bắt đầu cập nhật liên kết thư mục Google Drive...")

    # Duyệt qua các nhóm tên từ tập dữ liệu đầu vào
    for norm_name, links in grouped_links.items():
        # Tìm các thiết bị trong DB khớp tên
        matched_devices = []
        for dev_id, ma, ten in db_devices:
            # 1. Khớp chính xác hoàn toàn (không phân biệt hoa thường, khoảng trắng)
            if ten.strip().lower() == norm_name:
                matched_devices.append((dev_id, ma, ten))
            # 2. Khớp tương đối/phụ (chứa nhau hoặc bỏ qua chú thích nhỏ)
            elif (norm_name in ten.strip().lower()) or (ten.strip().lower() in norm_name):
                # Để tránh khớp nhầm các thiết bị quá khác nhau, kiểm tra độ dài tương đối
                if len(ten) > 10 and len(norm_name) > 10:
                    matched_devices.append((dev_id, ma, ten))

        if not matched_devices:
            # Tìm kiếm thử xem có trong danh sách thô gốc không
            # Thử khôi phục tên hiển thị gốc cho báo cáo
            orig_name = next(n for n, l in DRIVE_DATA if n.strip().lower() == norm_name)
            unmatched_names.append(orig_name)
            continue

        # Tiến hành cập nhật
        # Nếu có nhiều link cho cùng 1 tên (ví dụ: Hộp số hybrid P410)
        # Chúng ta sẽ phân bổ lần lượt cho các thiết bị tìm thấy
        for idx, (dev_id, ma, ten) in enumerate(matched_devices):
            # Chọn link tương ứng hoặc link đầu tiên nếu hết
            link_to_update = links[idx] if idx < len(links) else links[0]
            
            cur.execute(
                "UPDATE thiet_bi SET tai_lieu_link = %s, updated_at = NOW() WHERE id = %s",
                (link_to_update, dev_id)
            )
            updated_count += 1
            matched_set.add(norm_name)
            print(f"  [+] UPDATED ID {dev_id:<3} | Mã: {ma:<25} | Tên: {ten:<60} -> Link: ...{link_to_update[-15:]}")

    print("\n=======================================================")
    print("📊 BÁO CÁO KẾT QUẢ CẬP NHẬT:")
    print(f"  - Tổng số thiết bị đã được cập nhật link Drive: {updated_count} thiết bị")
    print(f"  - Số nhóm tên khớp thành công: {len(matched_set)} / {len(grouped_links)}")
    
    if unmatched_names:
        print(f"  - Các tên KHÔNG khớp được thiết bị nào trong CSDL ({len(unmatched_names)}):")
        for name in unmatched_names:
            print(f"      ❌ {name}")
    else:
        print("  - Tất cả các tên đều khớp thành công!")
    print("=======================================================")

    cur.close()
    conn.close()

if __name__ == "__main__":
    run_update()
