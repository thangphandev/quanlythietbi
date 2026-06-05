<?php
/**
 * admin/modal_add_category.php
 * ============================
 * Giao diện Pop-up thêm mới loại thiết bị.
 * Thiết kế sang trọng, tối giản, cân đối và tương thích responsive 100%.
 */
?>
<!-- MODAL THÊM LOẠI THIẾT BỊ MỚI -->
<div class="modal" id="addCategoryModal">
    <!-- Overlay đóng modal khi click ra ngoài -->
    <div class="modal-overlay" onclick="closeAddCategoryModal()"></div>
    
    <div class="modal-content" style="max-width: 460px; width: 92%; padding: 22px 24px; position: relative;">
        <!-- Nút đóng modal chuẩn hệ thống -->
        <button type="button" class="modal-close" onclick="closeAddCategoryModal()">&times;</button>
        
        <!-- Tiêu đề modal chuẩn hệ thống -->
        <h2 class="modal-title" style="margin-bottom: 18px; font-size: 1.3rem;">📁 Thêm loại thiết bị mới</h2>
        
        <form method="POST" action="admin.php?tab=categories-tab">
            <input type="hidden" name="admin_action" value="add_category">
            
            <!-- Tên loại thiết bị -->
            <div style="margin-bottom: 16px;">
                <label for="add_ten_loai" style="display:block; font-weight:600; font-size:0.88rem; margin-bottom:6px; color:var(--text-primary);">Tên loại thiết bị:</label>
                <input type="text" id="add_ten_loai" name="ten_loai" placeholder="VD: Thiết bị đo quang, Mô hình mô phỏng..." required style="width:100%; height:40px; padding:8px 12px; border-radius:10px; border:1px solid #cbd5e1; box-sizing:border-box; color:var(--text-primary); background:#fff; font-size:0.9rem;">
            </div>
            
            <!-- Màu sắc đặc trưng -->
            <div style="margin-bottom: 20px;">
                <label for="add_ma_mau" style="display:block; font-weight:600; font-size:0.88rem; margin-bottom:6px; color:var(--text-primary);">Màu sắc đặc trưng:</label>
                <div style="display:flex; align-items:center; gap:10px;">
                    <input type="color" id="add_ma_mau" name="ma_mau" value="#3b82f6" style="width:50px; height:40px; border:1px solid #cbd5e1; border-radius:10px; cursor:pointer; background:none; padding:0; box-sizing:border-box;">
                    <span style="font-size:0.85rem; color:var(--text-secondary);">Màu sắc dùng để nhận diện thiết bị thuộc loại này trên danh sách.</span>
                </div>
            </div>
            
            <!-- Footer nút bấm đóng/lưu -->
            <div style="display:flex; justify-content:flex-end; gap:10px; border-top:1px solid rgba(0,0,0,0.06); padding-top:15px; margin-top:5px;">
                <button type="button" class="btn-console" style="background:#f1f5f9; border-color:#cbd5e1; color:var(--text-secondary); margin:0; padding:8px 16px; border-radius:10px; font-weight:600; font-size:0.9rem;" onclick="closeAddCategoryModal()">❌ HỦY</button>
                <button type="submit" class="btn-console" style="margin:0; background:var(--accent-blue); border-color:var(--accent-blue); color:#fff; font-weight:600; padding:8px 20px; border-radius:10px; font-size:0.9rem;">💾 LƯU THÊM MỚI</button>
            </div>
        </form>
    </div>
</div>
