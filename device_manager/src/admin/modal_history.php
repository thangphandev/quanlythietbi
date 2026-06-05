<?php
/**
 * admin/modal_history.php
 * =======================
 * Modal lịch sử sử dụng chi tiết của thiết bị.
 */
?>
<!-- MODAL LỊCH SỬ SỬ DỤNG CHI TIẾT CỦA THIẾT BỊ (DEVICE HISTORY MODAL) -->
<div class="modal" id="deviceHistoryModal">
    <div class="modal-overlay" onclick="closeDeviceHistoryModal()"></div>
    <div class="modal-content" style="max-width: 800px; width: 90%; padding: 25px;">
        <button type="button" class="modal-close" onclick="closeDeviceHistoryModal()">&times;</button>
        <h2 class="modal-title" id="historyDeviceName" style="margin-bottom: 5px;">📄 Lịch sử sử dụng thiết bị</h2>
        <div style="display: flex; gap: 15px; margin-bottom: 20px; font-size: 0.9rem; color: var(--text-secondary); flex-wrap: wrap; align-items: center; justify-content: space-between; width: 100%;">
            <div style="display: flex; gap: 15px; flex-wrap: wrap;">
                <span>Mã ID: <strong id="historyDeviceCode" style="color: var(--accent-blue);"></strong></span>
                <span>· Vị trí đặt: <strong id="historyDeviceLocation"></strong></span>
                <span>· Quản lý: <strong id="historyDeviceManager"></strong></span>
                <span>· Tổng mượn: <strong id="historyDeviceUses" style="color: var(--success-green);"></strong></span>
            </div>
            <button type="button" class="btn-console" id="btnExportDeviceHistory" style="padding: 6px 14px; font-size: 0.85rem; background: linear-gradient(135deg, #10b981, #059669); margin: 0; box-shadow: 0 4px 10px rgba(16, 185, 129, 0.15);">📤 Xuất Excel (CSV)</button>
        </div>
        
        <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
            <table class="history-table" style="width:100%; border-collapse: collapse;">
                <thead>
                    <tr style="position: sticky; top: 0; background: #f8fafc; z-index: 10;">
                        <th style="padding: 10px;">Ngày mượn</th>
                        <th style="padding: 10px;">Giáo viên mượn</th>
                        <th style="padding: 10px;">Lớp thực hành</th>
                        <th style="padding: 10px;">Tình trạng thiết bị</th>
                        <th style="padding: 10px;">Ghi chú bàn giao</th>
                    </tr>
                </thead>
                <tbody id="deviceHistoryTableBody">
                    <!-- Loaded dynamically via AJAX -->
                </tbody>
            </table>
        </div>
    </div>
</div>
