<?php
/**
 * admin/tab_backup.php
 * ====================
 * HTML Tab 5: Sao lưu & Import/Export.
 */
?>
<div class="tab-content <?= $active_tab === 'backup-tab' ? 'active' : '' ?>" id="backup-tab">
    <!-- Sub-tab 1: Import/Export CSV -->
    <div class="admin-card">
        <h3 style="border-bottom:1px solid rgba(0,0,0,0.06); padding-bottom:10px; margin-bottom:20px; color:var(--accent-blue);">📂 IMPORT / EXPORT DANH SÁCH THIẾT BỊ</h3>
        <p style="color:var(--text-secondary); font-size:0.92rem; line-height:1.5; margin-bottom:20px;">
            Sao lưu và trao đổi danh sách thiết bị nhanh chóng bằng định dạng file Excel (.xlsx) hoặc CSV.
        </p>
        
        <div class="flex-box">
            <!-- Export CSV Link -->
            <a href="admin.php?action=export_csv" class="btn-console" style="text-decoration:none; display:inline-flex; align-items:center; gap:8px;">
                📤 XUẤT FILE DANH SÁCH (EXPORT EXCEL)
            </a>
        </div>
        

        <div style="border-top:1px dashed #cbd5e1; padding-top:20px; margin-top:20px;">
            <h4 style="margin-bottom:10px; color:var(--text-primary);">📥 NHẬP DANH SÁCH THIẾT BỊ TỪ FILE EXCEL / CSV (IMPORT)</h4>
            <p style="color:var(--text-secondary); font-size:0.88rem; line-height:1.6; margin-bottom:15px;">
                Tải lên tệp CSV chứa danh sách thiết bị của bạn. 
                <br>
                Cấu trúc tệp CSV bắt buộc gồm các cột: <strong>DeviceName, ID, Nơi sử dụng, Năm sử dụng, Số lượng, Chất lượng còn lại, GV QUẢN LÝ</strong>.
                <br>
                <span style="color:var(--accent-blue); font-weight:600;">💡 Quy tắc thông minh:</span>
                <br>
                • <strong>Số lượng:</strong> Nếu lớn hơn 1 (ví dụ: 3), hệ thống sẽ tự động tách thành 3 bản ghi riêng biệt trong cơ sở dữ liệu và đánh số thứ tự như <code>Mã (No.1)</code>, <code>Mã (No.2)</code>, <code>Mã (No.3)</code>.
                <br>
                • <strong>Chất lượng:</strong> Nhập <code>Tốt</code> hoặc <code>Hư hỏng: [Chi tiết nội dung hư hỏng]</code>.
                <br>
                • <strong>GV QUẢN LÝ:</strong> Nhập họ tên giảng viên, hệ thống sẽ tự động khớp chính xác hoặc khớp tương đối với danh sách giảng viên trong CSDL.
            </p>
            
            <div style="margin-bottom:20px;">
                <a href="device_template.csv" download="device_template.csv" class="btn-console" style="background:#0284c7; border-color:#0284c7; text-decoration:none; display:inline-flex; align-items:center; gap:8px; padding:10px 20px; font-size:0.88rem; color:#fff;">
                    📥 TẢI FILE EXCEL / CSV MẪU (TEMPLATE)
                </a>
            </div>

            <form method="POST" action="admin.php" enctype="multipart/form-data">
                <input type="hidden" name="admin_action" value="import_csv">
                <div style="display:flex; gap:10px; flex-wrap:wrap; align-items:center;">
                    <input type="file" name="csv_file" accept=".csv" required style="padding:10px; background:#fff; border:1px solid #cbd5e1; height:auto; width:auto; max-width:300px;">
                    <button type="submit" class="btn-console" style="padding:12px 24px; font-size:0.95rem; margin:0;">📥 NHẬP FILE VÀO HỆ THỐNG</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Sub-tab 2: SQL DB Backup -->
    <div class="admin-card">
        <h3 style="border-bottom:1px solid rgba(0,0,0,0.06); padding-bottom:10px; margin-bottom:20px; color:var(--accent-blue);">💾 SAO LƯU CƠ SỞ DỮ LIỆU SQL</h3>
        <p style="color:var(--text-secondary); font-size:0.95rem; line-height:1.5; margin-bottom:20px;">
            Kết xuất toàn bộ cấu trúc bảng và toàn bộ dữ liệu (thiết bị, lịch sử sử dụng, TKB) ra tệp nén SQL để lưu trữ hoặc phục hồi khi cần thiết.
        </p>
        <button type="button" class="btn-console" onclick="location.href='admin.php?download_backup=1'" style="padding:14px 28px;">
            📥 TẢI VỀ BẢN DUMP (.SQL) NGAY
        </button>
    </div>

    <div class="admin-card">
        <h4 style="color:var(--text-primary); margin-bottom:10px; font-weight:700;">⚙ SAO LƯU ĐÁM MÂY TỰ ĐỘNG (TELEGRAM CLOUD)</h4>
        <p style="color:var(--text-secondary); font-size:0.92rem; line-height:1.5; margin-bottom:15px;">
            Hệ thống sẽ chạy ngầm một Cronjob lúc <strong>23:55 hàng ngày</strong> để kết xuất (dump) toàn bộ cơ sở dữ liệu PostgreSQL thành một tệp tin sao lưu an toàn và gửi tệp tin đó trực tiếp vào Telegram Group/Channel riêng tư của bạn thông qua Bot Telegram.
        </p>
        <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:10px; padding:15px; font-size:0.88rem; color:var(--accent-blue); font-family:monospace; line-height:1.6;">
            Cấu hình trong file <strong>.env</strong> ở thư mục cha:<br>
            • TELEGRAM_BOT_TOKEN = (Bot token nhận từ @BotFather)<br>
            • TELEGRAM_CHAT_ID = (Chat ID của nhóm/kênh nhận dữ liệu)
        </div>
    </div>
</div>
