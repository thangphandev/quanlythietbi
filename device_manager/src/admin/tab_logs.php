<?php
/**
 * admin/tab_logs.php
 * ==================
 * HTML Tab 2: Nhật ký phiếu sử dụng thiết bị chi tiết.
 */
?>
<div class="tab-content <?= $active_tab === 'logs-tab' ? 'active' : '' ?>" id="logs-tab">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px; flex-wrap:wrap; gap:10px;">
        <h3 style="margin:0;">NHẬT KÝ SỬ DỤNG THIẾT BỊ CHI TIẾT</h3>
        <button type="button" class="btn-console" onclick="openAddUsageModal()" style="margin:0; background: linear-gradient(135deg, var(--accent-blue), #0284c7); padding: 8px 16px; border-radius: 10px; font-weight: 700; font-size: 0.9rem; display: flex; align-items: center; gap: 6px; box-shadow: 0 4px 12px rgba(2, 132, 199, 0.15);">
            ➕ THÊM LƯỢT SỬ DỤNG MỚI
        </button>
    </div>
    
    <!-- Bộ lọc Nhật ký sử dụng -->
    <div class="admin-card" style="margin-bottom: 25px; padding: 18px 22px; background: rgba(255,255,255,0.7); backdrop-filter: blur(10px); border-radius: 16px; border: 1px solid rgba(255,255,255,0.8); box-shadow: 0 4px 20px rgba(0,0,0,0.03);">
        <h4 style="margin-top:0; margin-bottom:12px; color:var(--text-primary); font-size:1.02rem; font-weight:700; display:flex; align-items:center; gap:8px;">🔍 BỘ LỌC THỜI GIAN & HỌC KỲ</h4>
        <form method="GET" action="admin.php" style="display:flex; gap:15px; align-items:flex-end; flex-wrap:wrap;">
            <!-- Cần giữ lại Tab hiện tại khi Submit Form -->
            <input type="hidden" name="tab" value="logs-tab">
            
            <div style="flex:1; min-width:240px;">
                <label for="hk_filter" style="margin-top:0; font-size:0.88rem; color:var(--text-secondary); font-weight:600; margin-bottom:6px;">Chọn Học kỳ / Năm học:</label>
                <select name="hk_filter" id="hk_filter" style="width:100%; height:42px; padding:8px 12px; border-radius:10px; border:1px solid #cbd5e1; font-weight:500; color:var(--text-primary); background:#fff;" onchange="this.form.submit()">
                    <option value="0">-- Tất cả học kỳ --</option>
                    <?php foreach ($semesters as $sem): ?>
                        <option value="<?= $sem['id_hocky_namhoc'] ?>" <?= $selected_hk === $sem['id_hocky_namhoc'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($sem['ten_hoc_ky']) ?> - <?= htmlspecialchars($sem['ten_nam_hoc']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div style="width:160px;">
                <label for="start_date" style="margin-top:0; font-size:0.88rem; color:var(--text-secondary); font-weight:600; margin-bottom:6px;">Từ ngày:</label>
                <input type="date" name="start_date" id="start_date" lang="vi-VN" value="<?= htmlspecialchars($start_date_filter) ?>" style="width:100%; height:42px; padding:8px 12px; border-radius:10px; border:1px solid #cbd5e1; color:var(--text-primary); background:#fff;">
            </div>
            
            <div style="width:160px;">
                <label for="end_date" style="margin-top:0; font-size:0.88rem; color:var(--text-secondary); font-weight:600; margin-bottom:6px;">Đến ngày:</label>
                <input type="date" name="end_date" id="end_date" lang="vi-VN" value="<?= htmlspecialchars($end_date_filter) ?>" style="width:100%; height:42px; padding:8px 12px; border-radius:10px; border:1px solid #cbd5e1; color:var(--text-primary); background:#fff;">
            </div>
            <div style="width:160px;">
            </div>
            <div style="display:flex; gap:10px; flex-wrap:wrap; align-items:center;">
                <button type="submit" class="btn-console" style="margin:0; height:42px; padding:0 20px; font-size:0.92rem; background:var(--accent-blue); color:#fff; border-color:var(--accent-blue); font-weight:600; border-radius:10px; display:inline-flex; align-items:center; justify-content:center; gap:6px; white-space:nowrap; flex-shrink:0; cursor:pointer;">🔍 Lọc</button>
                <a href="admin.php?tab=logs-tab" class="btn-console" style="margin:0; height:42px; padding:0 18px; font-size:0.92rem; background:#f1f5f9; color:var(--text-secondary); border-color:#cbd5e1; display:inline-flex; align-items:center; justify-content:center; gap:6px; text-decoration:none; border-radius:10px; font-weight:600; white-space:nowrap; flex-shrink:0;">🔄 Xoá bộ lọc</a>
                
                <!-- Xuất báo cáo Excel -->
                <a href="admin.php?action=export_usage_logs&hk_filter=<?= $selected_hk ?>&start_date=<?= urlencode($start_date_filter) ?>&end_date=<?= urlencode($end_date_filter) ?>" class="btn-console" style="margin:0; height:42px; padding:0 22px; font-size:0.92rem; background:#10b981; color:#fff; border-color:#10b981; display:inline-flex; align-items:center; justify-content:center; gap:6px; text-decoration:none; border-radius:10px; font-weight:600; white-space:nowrap; flex-shrink:0;">
                    📤 Xuất báo cáo (Excel)
                </a>
            </div>
        </form>
       
    </div>
    
        <table>
            <thead>
                <tr>
                    <th>Thời gian sử dụng</th>
                    <th>Giảng viên sử dụng</th>
                    <th>Mã lớp / Mục đích</th>
                    <th>Thiết bị sử dụng</th>
                    <th>Đánh giá chất lượng bàn giao</th>
                    <th style="width: 140px; text-align: center;">Hành động</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($history)): ?>
                    <tr>
                        <td colspan="6" style="text-align:center; color:var(--text-muted);">Chưa có lịch sử giao dịch sử dụng thiết bị nào!</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($history as $h): ?>
                        <tr>
                            <td><?= date('d/m/Y H:i', strtotime($h['ngay_muon'])) ?></td>
                            <td style="font-weight:500; color:var(--text-primary);">
                                <?= htmlspecialchars($h['ten_giang_vien'] ?: 'Chưa xác định') ?><br>
                                <small style="color:var(--text-muted);"><?= htmlspecialchars($h['email_xac_nhan']) ?></small>
                            </td>
                            <td style="font-weight:bold; color:var(--accent-blue);"><?= htmlspecialchars($h['ten_lop']) ?></td>
                            <td>
                                <?php 
                                $row_devices = [];
                                try {
                                    $d_stmt = $db->prepare("
                                        SELECT ct.*, tb.ten_thiet_bi 
                                        FROM chi_tiet_phieu_muon ct 
                                        JOIN thiet_bi tb ON ct.id_thiet_bi = tb.id 
                                        WHERE ct.id_phieu_muon = :id_pm
                                    ");
                                    $d_stmt->execute(['id_pm' => $h['id']]);
                                    $items = $d_stmt->fetchAll();
                                    foreach ($items as $item) {
                                        echo "• " . htmlspecialchars($item['ten_thiet_bi']) . "<br>";
                                        $row_devices[] = intval($item['id_thiet_bi']);
                                    }
                                } catch (PDOException $e) {
                                    echo "Lỗi truy xuất";
                                }
                                ?>
                            </td>
                            <td>
                                <span style="font-size:0.88rem; font-style:italic;">
                                    <?= htmlspecialchars($h['tinh_trang_chung']) ?>
                                </span>
                            </td>
                            <td>
                                <div style="display: flex; gap: 6px; justify-content: center; flex-wrap: wrap;">
                                    <button type="button" class="btn-table-action btn-edit" style="padding: 5px 9px; font-size: 0.8rem;" 
                                            onclick="openEditUsageModal(<?= htmlspecialchars(json_encode([
                                                'id' => intval($h['id']),
                                                'ngay_muon' => date('Y-m-d\TH:i', strtotime($h['ngay_muon'])),
                                                'id_giang_vien' => intval($h['id_giang_vien']),
                                                'email_xac_nhan' => $h['email_xac_nhan'],
                                                'ten_lop' => $h['ten_lop'],
                                                'tinh_trang_chung' => $h['tinh_trang_chung'],
                                                'thiet_bi' => $row_devices
                                            ]), ENT_QUOTES, 'UTF-8') ?>)">✏️ Sửa</button>
                                    <form method="POST" action="admin.php?tab=logs-tab" style="display:inline;" onsubmit="return confirm('Bạn có chắc chắn muốn xóa lượt sử dụng này không?');">
                                        <input type="hidden" name="admin_action" value="delete_usage">
                                        <input type="hidden" name="id" value="<?= $h['id'] ?>">
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
