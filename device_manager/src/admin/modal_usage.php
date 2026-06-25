<?php
/**
 * admin/modal_usage.php
 * =====================
 * Giao diện Pop-up thêm mới hoặc chỉnh sửa lượt sử dụng thiết bị (Modal Add/Edit Usage).
 * Thiết kế tinh tế, tương thích responsive 100%.
 */
?>
<!-- MODAL THÊM/SỬA LƯỢT SỬ DỤNG -->
<div class="modal" id="usageModal">
    <!-- Overlay đóng modal khi click ra ngoài -->
    <div class="modal-overlay" onclick="closeUsageModal()"></div>
    
    <div class="modal-content" style="max-width: 580px; width: 92%; padding: 22px 24px; position: relative;">
        <!-- Nút đóng modal -->
        <button type="button" class="modal-close" onclick="closeUsageModal()">&times;</button>
        
        <!-- Tiêu đề modal -->
        <h2 class="modal-title" id="usageModalTitle" style="margin-bottom: 15px; font-size: 1.3rem;">➕ Thêm lượt sử dụng thiết bị mới</h2>
        
        <form method="POST" action="admin.php?tab=logs-tab" id="usageForm">
            <input type="hidden" name="admin_action" id="usage_admin_action" value="add_usage">
            <input type="hidden" name="id" id="usage_id" value="">
            
            <!-- Thời gian sử dụng -->
            <div style="margin-bottom: 14px;">
                <label for="usage_ngay_muon" style="display:block; font-weight:600; font-size:0.88rem; margin-bottom:6px; color:var(--text-primary);">Thời gian sử dụng:</label>
                <input type="datetime-local" id="usage_ngay_muon" name="ngay_muon" required style="width:100%; height:40px; padding:8px 12px; border-radius:10px; border:1px solid #cbd5e1; box-sizing:border-box; color:var(--text-primary); background:#fff; font-size:0.9rem;">
            </div>
            
            <!-- Giảng viên sử dụng -->
            <div style="margin-bottom: 14px;">
                <label for="usage_id_giang_vien" style="display:block; font-weight:600; font-size:0.88rem; margin-bottom:6px; color:var(--text-primary);">Giảng viên sử dụng:</label>
                <select id="usage_id_giang_vien" name="id_giang_vien" required onchange="updateUsageEmail()" style="width:100%; height:40px; padding:8px 12px; border-radius:10px; border:1px solid #cbd5e1; font-weight:500; box-sizing:border-box; background:#fff; color:var(--text-primary); font-size:0.9rem;">
                    <option value="">-- Chọn giảng viên sử dụng --</option>
                    <?php foreach ($lecturers as $gv): ?>
                        <option value="<?= $gv['id_giang_vien'] ?>" data-email="<?= htmlspecialchars($gv['email'] ?: '') ?>"><?= htmlspecialchars($gv['ho_ten_gv']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <!-- Email xác nhận -->
            <div style="margin-bottom: 14px;">
                <label for="usage_email_xac_nhan" style="display:block; font-weight:600; font-size:0.88rem; margin-bottom:6px; color:var(--text-primary);">Email xác nhận:</label>
                <input type="email" id="usage_email_xac_nhan" name="email_xac_nhan" required placeholder="nhap_email@vlute.edu.vn" style="width:100%; height:40px; padding:8px 12px; border-radius:10px; border:1px solid #cbd5e1; box-sizing:border-box; color:var(--text-primary); background:#fff; font-size:0.9rem;">
            </div>
            
            <!-- Mã lớp / Học phần / Mục đích -->
            <div style="margin-bottom: 14px;">
                <label for="usage_ten_lop" style="display:block; font-weight:600; font-size:0.88rem; margin-bottom:6px; color:var(--text-primary);">Tên học phần hoặc mục đích sử dụng:</label>
                <input type="text" id="usage_ten_lop" name="ten_lop" required placeholder="Ví dụ: Thực hành cấu tạo ô tô Hybrid hoặc Nghiên cứu khoa học" style="width:100%; height:40px; padding:8px 12px; border-radius:10px; border:1px solid #cbd5e1; box-sizing:border-box; color:var(--text-primary); background:#fff; font-size:0.9rem;">
            </div>

            <!-- Đánh giá Tình trạng bàn giao -->
            <div style="margin-bottom: 14px;">
                <label for="usage_tinh_trang_chung" style="display:block; font-weight:600; font-size:0.88rem; margin-bottom:6px; color:var(--text-primary);">Đánh giá Tình trạng bàn giao:</label>
                <input type="text" id="usage_tinh_trang_chung" name="tinh_trang_chung" placeholder="Ví dụ: Thiết bị hoạt động bình thường, đầy đủ giắc cắm" style="width:100%; height:40px; padding:8px 12px; border-radius:10px; border:1px solid #cbd5e1; box-sizing:border-box; color:var(--text-primary); background:#fff; font-size:0.9rem;">
            </div>

            <!-- Danh sách thiết bị (Checklist cuộn) -->
            <div style="margin-bottom: 15px;">
                <label style="display:block; font-weight:600; font-size:0.88rem; margin-bottom:6px; color:var(--text-primary);">Chọn thiết bị sử dụng (Tích chọn ít nhất 1):</label>
                <div style="max-height: 180px; overflow-y: auto; border: 1px solid #cbd5e1; border-radius: 10px; padding: 12px; background: #fff; box-shadow: inset 0 1px 3px rgba(0,0,0,0.02);">
                    <?php
                    try {
                        $all_devices_stmt = $db->query("SELECT id, ma_thiet_bi, ten_thiet_bi FROM thiet_bi ORDER BY ten_thiet_bi ASC");
                        $all_devices = $all_devices_stmt->fetchAll(PDO::FETCH_ASSOC);
                        if (empty($all_devices)):
                        ?>
                            <div style="color:var(--text-muted); font-size:0.85rem; text-align:center; padding:10px;">Không có thiết bị nào trong kho</div>
                        <?php
                        else:
                            foreach ($all_devices as $dev):
                            ?>
                                <div style="display:flex; align-items:flex-start; gap:8px; margin-bottom:10px; border-bottom:1px solid #f1f5f9; padding-bottom:8px;">
                                    <input type="checkbox" name="thiet_bi[]" value="<?= $dev['id'] ?>" class="usage-device-cb" id="use_dev_<?= $dev['id'] ?>" style="width:18px; height:18px; margin-top:1px; cursor:pointer;">
                                    <label for="use_dev_<?= $dev['id'] ?>" style="font-size:0.85rem; cursor:pointer; color:var(--text-primary); line-height:1.4;">
                                        <strong>[<?= htmlspecialchars($dev['ma_thiet_bi']) ?>]</strong> <?= htmlspecialchars($dev['ten_thiet_bi']) ?>
                                    </label>
                                </div>
                            <?php
                            endforeach;
                        endif;
                    } catch (PDOException $e) {
                        echo "<span style='color:red; font-size:0.85rem;'>Lỗi tải danh sách thiết bị: " . htmlspecialchars($e->getMessage()) . "</span>";
                    }
                    ?>
                </div>
            </div>

            <!-- Footer nút bấm đóng/lưu -->
            <div style="display:flex; justify-content:flex-end; gap:10px; border-top:1px solid rgba(0,0,0,0.06); padding-top:15px; margin-top:5px;">
                <button type="button" class="btn-console" style="background:#f1f5f9; border-color:#cbd5e1; color:var(--text-secondary); margin:0; padding:8px 16px; border-radius:10px; font-weight:600; font-size:0.9rem;" onclick="closeUsageModal()">❌ HỦY</button>
                <button type="submit" class="btn-console" style="margin:0; background:var(--accent-blue); border-color:var(--accent-blue); color:#fff; font-weight:600; padding:8px 20px; border-radius:10px; font-size:0.9rem;">💾 LƯU THAY ĐỔI</button>
            </div>
        </form>
    </div>
</div>
