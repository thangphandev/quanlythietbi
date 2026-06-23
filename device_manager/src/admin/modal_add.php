<?php
/**
 * admin/modal_add.php
 * ===================
 * Giao diện Pop-up thêm mới thiết bị (Modal Add New Device).
 * Thiết kế sang trọng, tối giản, cân đối và tương thích responsive 100%.
 */
?>
<!-- MODAL THÊM THIẾT BỊ MỚI -->
<div class="modal" id="addDeviceModal">
    <!-- Overlay đóng modal khi click ra ngoài -->
    <div class="modal-overlay" onclick="closeAddModal()"></div>
    
    <div class="modal-content" style="max-width: 540px; width: 92%; padding: 22px 24px; position: relative;">
        <!-- Nút đóng modal chuẩn hệ thống -->
        <button type="button" class="modal-close" onclick="closeAddModal()">&times;</button>
        
        <!-- Tiêu đề modal chuẩn hệ thống -->
        <h2 class="modal-title" style="margin-bottom: 15px; font-size: 1.3rem;">➕ Thêm mới thiết bị</h2>
        
        <form method="POST" action="admin.php" enctype="multipart/form-data">
            <input type="hidden" name="admin_action" value="add_device">
            
            <!-- Mã thiết bị (QR Code) -->
            <div style="margin-bottom: 14px;">
                <label for="add_ma_thiet_bi" style="display:block; font-weight:600; font-size:0.88rem; margin-bottom:6px; color:var(--text-primary);">Mã thiết bị (QR Code):</label>
                <input type="text" id="add_ma_thiet_bi" name="ma_thiet_bi" placeholder="VD: TB007..." required style="width:100%; height:40px; padding:8px 12px; border-radius:10px; border:1px solid #cbd5e1; box-sizing:border-box; color:var(--text-primary); background:#fff; font-size:0.9rem;">
            </div>
            
            <!-- Tên thiết bị -->
            <div style="margin-bottom: 14px;">
                <label for="add_ten_thiet_bi" style="display:block; font-weight:600; font-size:0.88rem; margin-bottom:6px; color:var(--text-primary);">Tên thiết bị:</label>
                <input type="text" id="add_ten_thiet_bi" name="ten_thiet_bi" placeholder="VD: Máy đo dòng rò acquy..." required style="width:100%; height:40px; padding:8px 12px; border-radius:10px; border:1px solid #cbd5e1; box-sizing:border-box; color:var(--text-primary); background:#fff; font-size:0.9rem;">
            </div>

            <!-- Vị trí và Năm sử dụng xếp song song gọn gàng -->
            <div style="display:grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 14px;">
                <div>
                    <label for="add_vi_tri" style="display:block; font-weight:600; font-size:0.88rem; margin-bottom:6px; color:var(--text-primary);">Vị trí đặt:</label>
                    <input type="text" id="add_vi_tri" name="vi_tri" placeholder="VD: Tủ thiết bị A - Xưởng ô tô điện..." style="width:100%; height:40px; padding:8px 12px; border-radius:10px; border:1px solid #cbd5e1; box-sizing:border-box; color:var(--text-primary); background:#fff; font-size:0.9rem;">
                </div>
                <div>
                    <label for="add_nam_su_dung" style="display:block; font-weight:600; font-size:0.88rem; margin-bottom:6px; color:var(--text-primary);">Năm bắt đầu sử dụng:</label>
                    <input type="text" id="add_nam_su_dung" name="nam_su_dung" value="<?= date('Y') ?>" style="width:100%; height:40px; padding:8px 12px; border-radius:10px; border:1px solid #cbd5e1; box-sizing:border-box; color:var(--text-primary); background:#fff; font-size:0.9rem;">
                </div>
            </div>

            <!-- Loại thiết bị -->
            <div style="margin-bottom: 14px;">
                <label for="add_id_loai" style="display:block; font-weight:600; font-size:0.88rem; margin-bottom:6px; color:var(--text-primary);">Loại thiết bị:</label>
                <select id="add_id_loai" name="id_loai" style="width:100%; height:40px; padding:8px 12px; border-radius:10px; border:1px solid #cbd5e1; font-weight:500; box-sizing:border-box; background:#fff; color:var(--text-primary); font-size:0.9rem;">
                    <option value="">-- Chọn loại thiết bị --</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= $cat['id_loai'] ?>"><?= htmlspecialchars($cat['ten_loai']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Giảng viên phụ trách -->
            <div style="margin-bottom: 14px;">
                <label for="add_id_giang_vien_quan_ly" style="display:block; font-weight:600; font-size:0.88rem; margin-bottom:6px; color:var(--text-primary);">Giảng viên phụ trách quản lý:</label>
                <select id="add_id_giang_vien_quan_ly" name="id_giang_vien_quan_ly" style="width:100%; height:40px; padding:8px 12px; border-radius:10px; border:1px solid #cbd5e1; font-weight:500; box-sizing:border-box; background:#fff; color:var(--text-primary); font-size:0.9rem;">
                    <option value="">-- Chọn giảng viên phụ trách --</option>
                    <?php foreach ($lecturers as $gv): ?>
                        <option value="<?= $gv['id_giang_vien'] ?>"><?= htmlspecialchars($gv['ho_ten_gv']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Link Google Drive thư mục tài liệu -->
            <div style="margin-bottom: 14px;">
                <label for="add_tai_lieu_link" style="display:block; font-weight:600; font-size:0.88rem; margin-bottom:6px; color:var(--text-primary);">Đường dẫn thư mục tài liệu (Google Drive):</label>
                <input type="url" id="add_tai_lieu_link" name="tai_lieu_link" placeholder="https://drive.google.com/drive/folders/..." style="width:100%; height:40px; padding:8px 12px; border-radius:10px; border:1px solid #cbd5e1; box-sizing:border-box; color:var(--text-primary); background:#fff; font-size:0.9rem;">
            </div>

            <!-- Tình trạng chất lượng & Tải lên hình ảnh xếp song song -->
            <div style="display:grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 18px;">
                <div>
                    <label for="add_chat_luong" style="display:block; font-weight:600; font-size:0.88rem; margin-bottom:6px; color:var(--text-primary);">Tình trạng thiết bị:</label>
                    <input type="text" id="add_chat_luong" name="chat_luong" value="Tốt" style="width:100%; height:40px; padding:8px 12px; border-radius:10px; border:1px solid #cbd5e1; box-sizing:border-box; color:var(--text-primary); background:#fff; font-size:0.9rem;">
                </div>
                <div>
                    <label for="add_hinh_anh" style="display:block; font-weight:600; font-size:0.88rem; margin-bottom:6px; color:var(--text-primary);">Hình ảnh thiết bị:</label>
                    <input type="file" id="add_hinh_anh" name="hinh_anh" accept="image/*" style="width:100%; padding:6px; background:#fff; border:1px solid #cbd5e1; border-radius:10px; height:auto; font-size:0.82rem; box-sizing:border-box; color:var(--text-primary);">
                </div>
            </div>

            <!-- Footer nút bấm đóng/lưu -->
            <div style="display:flex; justify-content:flex-end; gap:10px; border-top:1px solid rgba(0,0,0,0.06); padding-top:15px; margin-top:5px;">
                <button type="button" class="btn-console" style="background:#f1f5f9; border-color:#cbd5e1; color:var(--text-secondary); margin:0; padding:8px 16px; border-radius:10px; font-weight:600; font-size:0.9rem;" onclick="closeAddModal()">❌ HỦY</button>
                <button type="submit" class="btn-console" style="margin:0; background:var(--accent-blue); border-color:var(--accent-blue); color:#fff; font-weight:600; padding:8px 20px; border-radius:10px; font-size:0.9rem;">💾 LƯU THIẾT BỊ MỚI</button>
            </div>
        </form>
    </div>
</div>
