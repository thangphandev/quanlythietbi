<?php
/**
 * admin/tab_devices.php
 * =====================
 * HTML Tab 1: Quản lý danh sách thiết bị (CRUD - Thêm / Sửa / Xóa + Upload ảnh).
 * Mỗi thiết bị là 1 dòng riêng biệt, không gộp nhóm.
 */
?>
<div class="tab-content <?= $active_tab === 'devices-tab' ? 'active' : '' ?>" id="devices-tab">

    <!-- Tiêu đề và điều khiển -->
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px; flex-wrap:wrap; gap:10px;">
        <h3 style="margin:0;">DANH SÁCH THIẾT BỊ</h3>
        <button type="button" class="btn-console" onclick="openAddModal()" style="margin:0; background: linear-gradient(135deg, var(--accent-blue), #0284c7); padding: 8px 16px; border-radius: 10px; font-weight: 700; font-size: 0.9rem; display: flex; align-items: center; gap: 6px; box-shadow: 0 4px 12px rgba(2, 132, 199, 0.15);">
            ➕ THÊM THIẾT BỊ MỚI
        </button>
    </div>

    <!-- THANH TÌM KIẾM & LỌC THIẾT BỊ -->
    <div style="background:rgba(255,255,255,0.9); border:1px solid rgba(2,132,199,0.12); border-radius:14px; padding:16px 20px; margin-bottom:16px; display:flex; flex-wrap:wrap; gap:12px; align-items:flex-end; box-shadow:0 2px 10px rgba(0,0,0,0.04);">
        <div style="flex:2; min-width:160px;">
            <label style="font-size:0.78rem; font-weight:600; color:var(--text-muted); display:block; margin-bottom:5px;">🔍 Tên thiết bị</label>
            <input type="text" id="filterName" placeholder="Nhập tên thiết bị..." oninput="filterDevicesTable()"
                style="width:100%; height:38px; padding:0 12px; border:1px solid #cbd5e1; border-radius:9px; font-size:0.88rem; background:#f8fafc; box-sizing:border-box;">
        </div>
        <div style="flex:2; min-width:160px;">
            <label style="font-size:0.78rem; font-weight:600; color:var(--text-muted); display:block; margin-bottom:5px;">👤 Người quản lý</label>
            <input type="text" id="filterManager" placeholder="Tên giảng viên..." oninput="filterDevicesTable()"
                style="width:100%; height:38px; padding:0 12px; border:1px solid #cbd5e1; border-radius:9px; font-size:0.88rem; background:#f8fafc; box-sizing:border-box;">
        </div>
        <div style="flex:2; min-width:160px;">
            <label style="font-size:0.78rem; font-weight:600; color:var(--text-muted); display:block; margin-bottom:5px;">📁 Loại thiết bị</label>
            <select id="filterCategory" onchange="filterDevicesTable()"
                style="width:100%; height:38px; padding:0 10px; border:1px solid #cbd5e1; border-radius:9px; font-size:0.88rem; background:#f8fafc; box-sizing:border-box;">
                <option value="">Tất cả loại</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?= htmlspecialchars(strtolower($cat['ten_loai'])) ?>"><?= htmlspecialchars($cat['ten_loai']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div style="flex:1; min-width:110px;">
            <label style="font-size:0.78rem; font-weight:600; color:var(--text-muted); display:block; margin-bottom:5px;">📅 Năm sử dụng</label>
            <input type="text" id="filterYear" placeholder="VD: 2023" oninput="filterDevicesTable()"
                style="width:100%; height:38px; padding:0 12px; border:1px solid #cbd5e1; border-radius:9px; font-size:0.88rem; background:#f8fafc; box-sizing:border-box;">
        </div>
        <div style="flex:1; min-width:130px;">
            <label style="font-size:0.78rem; font-weight:600; color:var(--text-muted); display:block; margin-bottom:5px;">⚙️ Chất lượng</label>
            <select id="filterQuality" onchange="filterDevicesTable()"
                style="width:100%; height:38px; padding:0 10px; border:1px solid #cbd5e1; border-radius:9px; font-size:0.88rem; background:#f8fafc; box-sizing:border-box;">
                <option value="">Tất cả</option>
                <option value="tốt">Tốt</option>
                <option value="hỏng">Hư hỏng</option>
                <option value="lỗi">Lỗi / Cảnh báo</option>
            </select>
        </div>
        <div>
            <button type="button" onclick="clearDeviceFilters()" style="height:38px; padding:0 16px; border-radius:9px; border:1px solid #cbd5e1; background:#f1f5f9; color:var(--text-secondary); font-size:0.85rem; font-weight:600; cursor:pointer; white-space:nowrap;">🔄 Xoá lọc</button>
        </div>
        <div style="font-size:0.82rem; color:var(--text-muted); align-self:center; white-space:nowrap;">
            Hiển thị: <strong id="deviceFilterCount"><?= count($devices) ?></strong> / <?= count($devices) ?> thiết bị
        </div>
    </div>

    <!-- Thanh tác vụ hàng loạt (Bulk Actions Bar) -->
    <div class="bulk-actions-bar" id="bulkActionsBar" style="display: none; background: rgba(255, 255, 255, 0.95); border: 1px solid rgba(2, 132, 199, 0.2); border-radius: 12px; padding: 12px 20px; margin-bottom: 15px; align-items: center; justify-content: space-between; gap: 15px; box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05); backdrop-filter: blur(8px);">
        <div style="font-weight: 600; color: var(--text-primary);">
            📦 Đã chọn: <span id="selectedCount" style="color: var(--accent-blue); font-weight: 800;">0</span> thiết bị
        </div>
        <div style="display: flex; gap: 10px; flex-wrap: wrap;">
            <button type="button" class="btn-console" onclick="bulkDownloadQRCodes()" style="background: linear-gradient(135deg, #0d9488, #14b8a6); padding: 8px 16px; font-size: 0.85rem; height: auto; margin: 0; line-height: 1.2;">📥 Tải QR</button>
            <button type="button" class="btn-console" onclick="bulkDownloadHistory()" style="background: linear-gradient(135deg, #3b82f6, #2563eb); padding: 8px 16px; font-size: 0.85rem; height: auto; margin: 0; line-height: 1.2;">📊 Tải lịch sử (.xlsx)</button>
            <button type="button" class="btn-console" onclick="bulkDeleteDevices()" style="background: linear-gradient(135deg, #ef4444, #dc2626); padding: 8px 16px; font-size: 0.85rem; height: auto; margin: 0; line-height: 1.2;">❌ Xóa</button>
        </div>
    </div>

    <!-- Bảng thiết bị -->
    <div class="table-responsive">
        <table id="devicesTable">
            <thead>
                <tr>
                    <th style="width: 40px; text-align: center;"><input type="checkbox" id="selectAllDevices" onclick="toggleSelectAllDevices(this)" style="width: 18px; height: 18px; cursor: pointer;"></th>
                    <th>Thiết bị (DeviceName)</th>
                    <th>ID / Mã thiết bị</th>
                    <th>Phân loại</th>
                    <th>Nơi sử dụng</th>
                    <th>Năm sử dụng</th>
                    <th>Chất lượng còn lại</th>
                    <th>GV Quản lý</th>
                    <th>Hành động</th>
                </tr>
            </thead>
            <tbody id="devicesTableBody">
                <?php if (empty($devices)): ?>
                    <tr>
                        <td colspan="9" style="text-align:center; color:var(--text-muted);">Không có thiết bị nào trong hệ thống!</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($devices as $d): ?>
                    <?php
                        $cls = str_starts_with($d['chat_luong'], 'Tốt') ? 'status-good' : 'status-bad';
                    ?>
                    <tr class="device-row" id="device-row-<?= $d['id'] ?>"
                        data-name="<?= htmlspecialchars(strtolower($d['ten_thiet_bi'])) ?>"
                        data-manager="<?= htmlspecialchars(strtolower($d['ten_gv_quan_ly'] ?: '')) ?>"
                        data-category="<?= htmlspecialchars(strtolower($d['ten_loai'] ?: '')) ?>"
                        data-year="<?= htmlspecialchars($d['nam_su_dung']) ?>"
                        data-quality="<?= htmlspecialchars(strtolower($d['chat_luong'])) ?>">
                        <td style="text-align: center;">
                            <input type="checkbox" class="device-checkbox"
                                value="<?= $d['id'] ?>"
                                data-code="<?= htmlspecialchars($d['ma_thiet_bi']) ?>"
                                data-name="<?= htmlspecialchars($d['ten_thiet_bi']) ?>"
                                data-manager="<?= htmlspecialchars($d['ten_gv_quan_ly'] ?: 'Chưa phân công') ?>"
                                onclick="updateBulkActionsState()"
                                style="width: 18px; height: 18px; cursor: pointer;">
                        </td>
                        <td>
                            <div style="display: flex; align-items: center; gap: 12px;">
                                <?php if (!empty($d['hinh_anh'])): 
                                    $thumb_file = 'uploads/thumb_' . $d['hinh_anh'];
                                    $img_src = file_exists($thumb_file) ? $thumb_file : 'uploads/' . $d['hinh_anh'];
                                ?>
                                    <img src="<?= htmlspecialchars($img_src) ?>" data-zoom="uploads/<?= htmlspecialchars($d['hinh_anh']) ?>" class="thietbi-hinh zoomable-thumb" alt="Device" style="width:70px; height:70px; object-fit:cover; border-radius:8px; cursor:zoom-in;">
                                <?php else: ?>
                                    <div class="thietbi-hinh" style="width:70px; height:70px; display:flex; align-items:center; justify-content:center; background:#f1f5f9; color:var(--text-muted); font-size:0.75rem; border-radius:8px;">No img</div>
                                <?php endif; ?>
                                <span style="font-weight: 600; color: var(--text-primary); font-size: 0.95rem;"><?= htmlspecialchars($d['ten_thiet_bi']) ?></span>
                            </div>
                        </td>
                        <td style="font-weight:bold; color:var(--accent-blue); font-size:0.9rem;"><code><?= htmlspecialchars($d['ma_thiet_bi']) ?></code></td>
                        <td>
                            <?php if (!empty($d['ten_loai'])): ?>
                                <span style="font-size:0.8rem; font-weight:600; padding:4px 10px; border-radius:8px; display:inline-block;
                                             background: color-mix(in srgb, <?= htmlspecialchars($d['ma_mau'] ?: '#0284c7') ?> 10%, rgba(255,255,255,0.75)); 
                                             color: color-mix(in srgb, <?= htmlspecialchars($d['ma_mau'] ?: '#0284c7') ?> 85%, #000); 
                                             border: 1px solid color-mix(in srgb, <?= htmlspecialchars($d['ma_mau'] ?: '#0284c7') ?> 25%, transparent);">
                                    <?= htmlspecialchars($d['ten_loai']) ?>
                                </span>
                            <?php else: ?>
                                <span style="font-size:0.8rem; font-weight:500; padding:4px 10px; border-radius:8px; display:inline-block; background:#f1f5f9; color:var(--text-muted); border:1px solid #e2e8f0;">
                                    Chưa phân loại
                                </span>
                            <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($d['vi_tri']) ?></td>
                        <td style="font-family: monospace; font-weight: 600;"><?= $d['nam_su_dung'] ?></td>
                        <td>
                            <span class="<?= $cls ?>" style="font-size: 0.85rem; padding: 4px 8px; border-radius: 12px; font-weight: 600;"><?= htmlspecialchars($d['chat_luong']) ?></span>
                        </td>
                        <td style="font-weight:500; color:var(--text-secondary);"><?= htmlspecialchars($d['ten_gv_quan_ly'] ?: 'Chưa phân công') ?></td>
                        <td>
                            <div style="display: flex; gap: 4px; flex-wrap: wrap;">
                                <button type="button" class="btn-table-action" style="background: rgba(13, 148, 136, 0.08); color: #0d9488; border: 1px solid rgba(13, 148, 136, 0.15); padding: 5px 9px; font-size: 0.8rem;" onclick="openQRModal(<?= $d['id'] ?>, '<?= htmlspecialchars($d['ma_thiet_bi']) ?>', '<?= htmlspecialchars($d['ten_thiet_bi']) ?>', '<?= htmlspecialchars($d['ten_gv_quan_ly'] ?: 'Chưa phân công') ?>')">📱 QR</button>
                                <button type="button" class="btn-table-action" style="background: rgba(124, 58, 237, 0.08); color: #7c3aed; border: 1px solid rgba(124, 58, 237, 0.15); padding: 5px 9px; font-size: 0.8rem;" onclick="openDeviceHistoryModal(<?= $d['id'] ?>)">📄 Lịch sử</button>
                                <button type="button" class="btn-table-action btn-edit" style="padding: 5px 9px; font-size: 0.8rem;" data-device='<?= htmlspecialchars(json_encode($d), ENT_QUOTES, 'UTF-8') ?>' onclick="openEditModal(JSON.parse(this.getAttribute('data-device')))">✏️ Sửa</button>
                                <form method="POST" action="admin.php" style="display:inline;" onsubmit="return confirm('Bạn có thực sự muốn xóa thiết bị này không?');">
                                    <input type="hidden" name="admin_action" value="delete_device">
                                    <input type="hidden" name="id" value="<?= $d['id'] ?>">
                                    <button type="submit" class="btn-table-action btn-delete" style="padding: 5px 9px; font-size: 0.8rem;">❌ Xóa</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
