<?php
/**
 * admin/modal_quick_usage.php
 * ===========================
 * Pop-up thêm lượt sử dụng nhanh cho thiết bị tra cứu.
 * Thiết kế ô chọn ngày kiểu Lịch tháng (Calendar Grid Checkboxes).
 */
?>
<!-- MODAL THÊM LƯỢT SỬ DỤNG NHANH -->
<div class="modal" id="quickUsageModal">
    <!-- Overlay đóng modal khi click ra ngoài -->
    <div class="modal-overlay" onclick="closeQuickUsageModal()"></div>
    
    <div class="modal-content" style="max-width: 520px; width: 92%; padding: 22px 24px; position: relative; max-height: 90vh; overflow-y: auto;">
        <!-- Nút đóng modal -->
        <button type="button" class="modal-close" onclick="closeQuickUsageModal()">&times;</button>
        
        <!-- Tiêu đề modal -->
        <h2 class="modal-title" style="margin-bottom: 12px; font-size: 1.25rem; color: var(--accent-blue); font-weight: 700;">➕ Thêm lượt sử dụng nhanh</h2>
        <p style="color:var(--text-secondary); font-size:0.85rem; line-height:1.4; margin-bottom:15px; margin-top: -5px;">
            Đang thêm lượt sử dụng cho thiết bị: <strong id="quickUsageDeviceNameLabel" style="color: var(--text-primary);">[Đang nạp...]</strong>
        </p>
        
        <form method="POST" action="admin.php?tab=stats-tab" id="quickUsageForm">
            <input type="hidden" name="admin_action" value="add_quick_usage">
            <!-- Thiết bị được chọn tự động điền từ select box tra cứu bằng Javascript -->
            <input type="hidden" name="id_thiet_bi" id="quick_usage_device_id" value="">
            
            <!-- Giảng viên sử dụng -->
            <div style="margin-bottom: 12px;">
                <label for="quick_id_giang_vien" style="display:block; font-weight:600; font-size:0.82rem; margin-bottom:5px; color:var(--text-primary);">Giảng viên sử dụng:</label>
                <select id="quick_id_giang_vien" name="id_giang_vien" required onchange="updateQuickUsageEmail()" style="width:100%; height:38px; padding:6px 10px; border-radius:8px; border:1px solid #cbd5e1; font-weight:500; box-sizing:border-box; background:#fff; color:var(--text-primary); font-size:0.88rem;">
                    <option value="">-- Chọn giảng viên --</option>
                    <?php foreach ($lecturers as $gv): ?>
                        <option value="<?= $gv['id_giang_vien'] ?>" data-email="<?= htmlspecialchars($gv['email'] ?: '') ?>"><?= htmlspecialchars($gv['ho_ten_gv']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <!-- Email xác nhận -->
            <div style="margin-bottom: 12px;">
                <label for="quick_email_xac_nhan" style="display:block; font-weight:600; font-size:0.82rem; margin-bottom:5px; color:var(--text-primary);">Email xác nhận:</label>
                <input type="email" id="quick_email_xac_nhan" name="email_xac_nhan" required placeholder="nhap_email@vlute.edu.vn" style="width:100%; height:38px; padding:6px 10px; border-radius:8px; border:1px solid #cbd5e1; box-sizing:border-box; color:var(--text-primary); background:#fff; font-size:0.88rem;">
            </div>
            
            <!-- Tên học phần và Mã lớp học phần (Grid) -->
            <input type="hidden" name="ten_lop" id="quick_ten_lop_hidden" value="">
            <div style="margin-bottom: 12px; display: grid; grid-template-columns: 1.2fr 0.8fr; gap: 10px;">
                <div>
                    <label for="quick_ten_lop_goc" style="display:block; font-weight:600; font-size:0.82rem; margin-bottom:5px; color:var(--text-primary);">Tên học phần / Mục đích:</label>
                    <input type="text" id="quick_ten_lop_goc" required placeholder="Nhập tên học phần..." list="suggestedCoursesList" oninput="onQuickCourseNameChange()" style="width:100%; height:38px; padding:6px 10px; border-radius:8px; border:1px solid #cbd5e1; box-sizing:border-box; color:var(--text-primary); background:#fff; font-size:0.88rem;">
                    <datalist id="suggestedCoursesList">
                        <?php foreach ($suggest_courses as $course): ?>
                            <option value="<?= htmlspecialchars($course) ?>">
                        <?php endforeach; ?>
                    </datalist>
                </div>
                <div>
                    <label for="quick_ma_lop_hp" style="display:block; font-weight:600; font-size:0.82rem; margin-bottom:5px; color:var(--text-primary);">Mã lớp học phần:</label>
                    <input type="text" id="quick_ma_lop_hp" placeholder="Nhập mã lớp..." list="suggestedClassesList" style="width:100%; height:38px; padding:6px 10px; border-radius:8px; border:1px solid #cbd5e1; box-sizing:border-box; color:var(--text-primary); background:#fff; font-size:0.88rem;">
                    <datalist id="suggestedClassesList">
                        <!-- Sẽ điền bằng JS -->
                    </datalist>
                </div>
            </div>

            <!-- Đánh giá chất lượng -->
            <div style="margin-bottom: 12px;">
                <label for="quick_tinh_trang_chung" style="display:block; font-weight:600; font-size:0.82rem; margin-bottom:5px; color:var(--text-primary);">Đánh giá chất lượng bàn giao:</label>
                <input type="text" id="quick_tinh_trang_chung" name="tinh_trang_chung" placeholder="Ví dụ: Đầy đủ phụ kiện, hoạt động tốt" style="width:100%; height:38px; padding:6px 10px; border-radius:8px; border:1px solid #cbd5e1; box-sizing:border-box; color:var(--text-primary); background:#fff; font-size:0.88rem;">
            </div>

            <!-- Chọn Tháng -->
            <div style="margin-bottom: 12px;">
                <label for="quick_month_select" style="display:block; font-weight:600; font-size:0.82rem; margin-bottom:5px; color:var(--text-primary);">Chọn Tháng sử dụng:</label>
                <input type="month" id="quick_month_select" required onchange="generateQuickDaysGrid()" style="width:100%; height:38px; padding:6px 10px; border-radius:8px; border:1px solid #cbd5e1; box-sizing:border-box; color:var(--text-primary); background:#fff; font-size:0.88rem; font-weight: 600;">
            </div>

            <!-- Danh sách ngày dạng Ô Lịch -->
            <div style="margin-bottom: 15px;">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:6px;">
                    <label style="font-weight:600; font-size:0.82rem; color:var(--text-primary);">Tích chọn các ngày mượn thiết bị:</label>
                    <div style="display:flex; gap:8px;">
                        <button type="button" onclick="selectAllQuickDays(true)" style="background:none; border:none; color:var(--accent-blue); font-size:0.75rem; font-weight:700; cursor:pointer; padding:0;">Chọn tất cả</button>
                        <span style="color:#cbd5e1; font-size:0.75rem;">|</span>
                        <button type="button" onclick="selectAllQuickDays(false)" style="background:none; border:none; color:var(--text-secondary); font-size:0.75rem; font-weight:700; cursor:pointer; padding:0;">Bỏ chọn</button>
                    </div>
                </div>
                
                <div class="quick-day-grid" id="quick_days_grid">
                    <!-- Trình tạo bằng JavaScript -->
                </div>
            </div>

            <!-- Footer nút bấm đóng/lưu -->
            <div style="display:flex; justify-content:flex-end; gap:10px; border-top:1px solid rgba(0,0,0,0.06); padding-top:15px; margin-top:15px;">
                <button type="button" class="btn-console" style="background:#f1f5f9; border-color:#cbd5e1; color:var(--text-secondary); margin:0; padding:8px 16px; border-radius:10px; font-weight:600; font-size:0.9rem;" onclick="closeQuickUsageModal()">❌ HỦY</button>
                <button type="submit" class="btn-console" style="margin:0; background:var(--accent-blue); border-color:var(--accent-blue); color:#fff; font-weight:600; padding:8px 20px; border-radius:10px; font-size:0.9rem;">💾 LƯU THAY ĐỔI</button>
            </div>
        </form>
    </div>
</div>

<style>
.quick-day-grid {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    gap: 6px;
    margin-top: 6px;
    background: #f8fafc;
    border: 1px solid #cbd5e1;
    border-radius: 10px;
    padding: 10px;
}
.quick-day-box {
    position: relative;
    border: 1px solid #cbd5e1;
    border-radius: 6px;
    padding: 8px 4px;
    text-align: center;
    cursor: pointer;
    background: #fff;
    transition: all 0.15s ease;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    color: var(--text-primary);
    font-size: 0.85rem;
    user-select: none;
    min-height: 34px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.02);
}
.quick-day-box:hover {
    border-color: var(--accent-blue);
    background: #f0f9ff;
}
.quick-day-box.selected {
    background: var(--accent-blue) !important;
    border-color: var(--accent-blue) !important;
    color: #fff !important;
    box-shadow: 0 2px 6px rgba(2, 132, 199, 0.2);
}
</style>

<script>
    const SUGGEST_CLASSES_MAP = <?php echo json_encode($suggest_classes_map ?? []); ?>;

    function onQuickCourseNameChange() {
        const courseName = document.getElementById("quick_ten_lop_goc").value.trim();
        const datalist = document.getElementById("suggestedClassesList");
        if (!datalist) return;
        
        // Xóa các option cũ
        datalist.innerHTML = "";
        
        if (!courseName) return;
        
        // Tìm các mã lớp học phần tương ứng
        const matchedClasses = SUGGEST_CLASSES_MAP.filter(item => 
            item.ten_hoc_phan.toLowerCase() === courseName.toLowerCase()
        );
        
        // Điền các option mới
        matchedClasses.forEach(item => {
            const option = document.createElement("option");
            option.value = item.ma_lop_hp;
            datalist.appendChild(option);
        });
    }

    // Khi người dùng bấm submit, kết hợp hai trường lại thành ten_lop trước khi gửi lên máy chủ
    document.addEventListener("DOMContentLoaded", function() {
        const form = document.getElementById("quickUsageForm");
        if (form) {
            form.addEventListener("submit", function(e) {
                const courseName = document.getElementById("quick_ten_lop_goc").value.trim();
                const classCode = document.getElementById("quick_ma_lop_hp").value.trim();
                const hiddenInput = document.getElementById("quick_ten_lop_hidden");
                
                if (hiddenInput) {
                    if (courseName && classCode) {
                        hiddenInput.value = `${courseName} (${classCode})`;
                    } else {
                        hiddenInput.value = courseName || classCode;
                    }
                }
            });
        }
    });
</script>
