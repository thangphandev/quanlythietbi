<?php
/**
 * admin/tab_categories.php
 * =======================
 * HTML Tab: Quản lý danh sách loại thiết bị (Phân loại) bằng Modal.
 */
?>
<div class="tab-content <?= $active_tab === 'categories-tab' ? 'active' : '' ?>" id="categories-tab">

    <!-- Tiêu đề và nút mở modal thêm mới -->
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; flex-wrap:wrap; gap:10px;">
        <h3 style="margin:0;">DANH SÁCH LOẠI THIẾT BỊ</h3>
        <button type="button" class="btn-console" onclick="openAddCategoryModal()" style="margin:0; background: linear-gradient(135deg, var(--accent-blue), #0284c7); padding: 8px 16px; border-radius: 10px; font-weight: 700; font-size: 0.9rem; display: flex; align-items: center; gap: 6px; box-shadow: 0 4px 12px rgba(2, 132, 199, 0.15);">
            ➕ THÊM LOẠI MỚI
        </button>
    </div>

    <!-- BẢNG DANH SÁCH PHÂN LOẠI -->
    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th style="width: 80px; text-align: center;">ID</th>
                    <th>Tên loại thiết bị</th>
                    <th style="width: 160px; text-align: center;">Màu đặc trưng</th>
                    <th style="width: 180px; text-align: center;">Số thiết bị thuộc loại</th>
                    <th style="width: 200px; text-align: center;">Hành động</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($categories)): ?>
                    <tr>
                        <td colspan="5" style="text-align:center; color:var(--text-muted); padding: 20px;">Chưa có loại thiết bị nào trong hệ thống!</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($categories as $cat): 
                        // Đếm số thiết bị thuộc loại này
                        $device_count = 0;
                        try {
                            $count_stmt = $db->prepare("SELECT COUNT(*) FROM thiet_bi WHERE id_loai = :id_loai");
                            $count_stmt->execute(['id_loai' => $cat['id_loai']]);
                            $device_count = intval($count_stmt->fetchColumn());
                        } catch (PDOException $e) {}
                    ?>
                    <tr>
                        <td style="text-align: center; font-weight: bold; color: var(--text-muted);"><?= $cat['id_loai'] ?></td>
                        <td>
                            <span style="font-weight: 600; color: var(--text-primary); font-size: 0.95rem;"><?= htmlspecialchars($cat['ten_loai']) ?></span>
                        </td>
                        <td style="text-align: center;">
                            <div style="display: inline-flex; align-items: center; gap: 8px; background: rgba(255,255,255,0.4); border: 1px solid #e2e8f0; padding: 4px 10px; border-radius: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.02);">
                                <span style="display: inline-block; width: 14px; height: 14px; border-radius: 50%; background-color: <?= htmlspecialchars($cat['ma_mau'] ?: '#0284c7') ?>; border: 1px solid rgba(0,0,0,0.1);"></span>
                                <code style="font-size: 0.85rem; font-family: monospace; font-weight: 600; color: var(--text-secondary);"><?= htmlspecialchars(strtoupper($cat['ma_mau'] ?: '#0284c7')) ?></code>
                            </div>
                        </td>
                        <td style="text-align: center; font-family: monospace; font-weight: 700; color: var(--accent-blue);">
                            <?= $device_count ?>
                        </td>
                        <td style="text-align: center;">
                            <div style="display: flex; gap: 6px; justify-content: center;">
                                <button type="button" class="btn-table-action btn-edit" style="padding: 5px 12px; font-size: 0.8rem;" onclick="openEditCategoryModal(<?= htmlspecialchars(json_encode($cat)) ?>)">✏️ Sửa</button>
                                
                                <form method="POST" action="admin.php?tab=categories-tab" style="display:inline; margin:0;" onsubmit="return confirm('Bạn có chắc chắn muốn xóa loại này? Các thiết bị thuộc loại này sẽ được đưa về trạng thái Chưa phân loại.');">
                                    <input type="hidden" name="admin_action" value="delete_category">
                                    <input type="hidden" name="id_loai" value="<?= $cat['id_loai'] ?>">
                                    <button type="submit" class="btn-table-action btn-delete" style="padding: 5px 12px; font-size: 0.8rem;">❌ Xóa</button>
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
