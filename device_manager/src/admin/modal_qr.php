<?php
/**
 * admin/modal_qr.php
 * ==================
 * Modal hiển thị mã QR Code thiết bị.
 */
?>
<!-- MODAL HIỂN THỊ MÃ QR THIẾT BỊ (DEVICE QR CODE MODAL) -->
<div class="modal" id="qrModal">
    <div class="modal-overlay" onclick="closeQRModal()"></div>
    <div class="modal-content" style="max-width: 420px; text-align: center; padding: 25px;">
        <button type="button" class="modal-close" onclick="closeQRModal()">&times;</button>
        <h2 class="modal-title" style="margin-bottom: 15px;">📱 Mã QR Thiết bị</h2>
        
        <!-- Thông tin văn bản hiển thị đầu tiên -->
        <div style="background: rgba(2, 132, 199, 0.03); border: 1px solid rgba(2, 132, 199, 0.1); border-radius: 12px; padding: 15px; margin-bottom: 20px; text-align: left;">
            <div style="margin-bottom: 8px; font-size: 0.92rem; line-height: 1.4;">
                <span style="color: var(--text-muted); font-weight: 500;">Tên thiết bị:</span> 
                <strong id="qrDeviceName" style="color: var(--text-primary); font-size: 0.95rem;"></strong>
            </div>
            <div style="margin-bottom: 8px; font-size: 0.92rem; line-height: 1.4;">
                <span style="color: var(--text-muted); font-weight: 500;">Mã thiết bị:</span> 
                <code id="qrDeviceCode" style="font-weight: 700; color: var(--accent-blue); background: #e0f2fe; padding: 2px 6px; border-radius: 4px; font-family: monospace;"></code>
            </div>
            <div style="font-size: 0.92rem; line-height: 1.4;">
                <span style="color: var(--text-muted); font-weight: 500;">Người quản lý:</span> 
                <strong id="qrDeviceManager" style="color: var(--text-primary); font-size: 0.95rem;">-</strong>
            </div>
        </div>
        
        <!-- Rồi mới tới mã QR -->
        <div style="background: #fff; padding: 20px; border-radius: 16px; display: inline-block; box-shadow: 0 4px 20px rgba(0,0,0,0.06); margin-bottom: 20px; border: 1px solid #e2e8f0;">
            <div id="qrcode_canvas" style="width: 200px; height: 200px; margin: 0 auto;"></div>
        </div>
        
        <div style="display: flex; gap: 10px; justify-content: center;">
            <button type="button" class="btn-console" onclick="downloadQRCode()" style="padding: 10px 20px; font-size: 0.9rem; background: linear-gradient(135deg, #0d9488, #14b8a6); box-shadow: 0 4px 10px rgba(13, 148, 136, 0.2); height: auto; line-height: 1.2;">📥 Tải QR (.PNG)</button>
            <button type="button" class="btn-console" onclick="printQRCode()" style="padding: 10px 20px; font-size: 0.9rem; background: linear-gradient(135deg, #2563eb, #3b82f6); box-shadow: 0 4px 10px rgba(37, 99, 235, 0.2); height: auto; line-height: 1.2;">🖨️ In QR Label</button>
        </div>
    </div>
</div>
