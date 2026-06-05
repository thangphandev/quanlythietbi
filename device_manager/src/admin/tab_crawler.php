<?php
/**
 * admin/tab_crawler.php
 * ====================
 * HTML Tab 4: Đồng bộ TKB.
 */
?>
<div class="tab-content <?= $active_tab === 'crawler-tab' ? 'active' : '' ?>" id="crawler-tab">
    <h3>🔄 Tự động cào Thời khóa biểu Khoa CKĐL từ Portal</h3>
    <p style="color:var(--text-secondary); margin-bottom:15px; font-size:0.95rem; line-height:1.5;">
        Tính năng này sẽ khởi chạy ngầm script Python <code>crawler.py</code> để kết nối và cào dữ liệu thời khóa biểu thực tế từ trang đào tạo VLUTE của tất cả các giảng viên trong khoa về lưu trữ trong cơ sở dữ liệu PostgreSQL. Hệ thống sẽ tự động đối chiếu lịch học này để hiển thị mã lớp học tự động cho giáo viên.
    </p>    <!-- Bộ lọc Học kỳ cần cào -->
    <div style="background:rgba(255,255,255,0.5); border:1px solid var(--panel-border); border-radius:14px; padding:15px 20px; display:flex; gap:20px; align-items:flex-end; margin-bottom:20px; flex-wrap:wrap; box-sizing:border-box;">
        <div style="flex:1; min-width:280px; max-width:400px;">
            <label for="crawlerSemesterId" style="margin-top:0; font-size:0.85rem; color:var(--text-secondary); font-weight:600; display:block; margin-bottom:6px;">Chọn Học kỳ để tiến hành đồng bộ:</label>
            <select id="crawlerSemesterId" style="width:100%; height:40px; padding:0 12px; border-radius:8px; border:1px solid #cbd5e1; font-weight:600; background:#fff; font-size:0.92rem; box-sizing:border-box;">
                <?php if (empty($semesters)): ?>
                    <option value="89">Học kỳ 2 - 2025-2026 (Mặc định khởi tạo)</option>
                <?php else: ?>
                    <?php foreach ($semesters as $hk): ?>
                        <option value="<?= $hk['id_hocky_namhoc'] ?>">
                            <?= htmlspecialchars($hk['ten_hoc_ky']) ?> - <?= htmlspecialchars($hk['ten_nam_hoc']) ?>
                        </option>
                    <?php endforeach; ?>
                <?php endif; ?>
            </select>
        </div>
    </div>

    <div class="console-box" id="consoleOutput">Chưa có kết nối tiến trình cào...</div>

    <div class="console-controls">
        <button type="button" class="btn-console" id="btnStartCrawler">🔄 BẮT ĐẦU ĐỒNG BỘ (CÀO TKB)</button>
        <div class="status-indicator">
            <div class="indicator-dot" id="statusDot"></div>
            <span id="statusText">Trạng thái: Đang dừng</span>
        </div>
    </div>
</div>
