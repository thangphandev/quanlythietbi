<?php
/**
 * admin/admin_scripts.php
 * =======================
 * Toàn bộ JavaScript logic cho trang Admin Panel (tabs, stats, modals, crawler, zoom).
 */
?>
<!-- JS LOGIC CHO TRANG ADMIN PANEL -->
<script>
    // ==============================================================================
    // THÔNG BÁO NỔI (FLOATING NOTIFICATION)
    // ==============================================================================
    function showNotification(text, icon = "🔔") {
        const notif = document.getElementById("floatingNotif");
        if (!notif) return;
        document.getElementById("floatingNotifText").innerHTML = text;
        notif.querySelector(".floating-notif-icon").innerHTML = icon;
        notif.classList.add("active");
        setTimeout(() => {
            notif.classList.remove("active");
        }, 3500);
    }

    // Hàm đóng mở danh sách thiết bị con trong nhóm (giữ lại để tương thích)
    function toggleSubDevices(groupId, btn) {
        const row = document.getElementById('sub-devices-' + groupId);
        if (!row) return;
        const count = btn.innerText.match(/\d+/)[0];
        if (row.style.display === 'none') {
            row.style.display = 'table-row';
            btn.innerHTML = '📂 Ẩn danh sách (' + count + ')';
            btn.style.background = 'rgba(2, 132, 199, 0.2)';
            btn.style.color = '#025a87';
        } else {
            row.style.display = 'none';
            btn.innerHTML = '📂 Xem danh sách (' + count + ')';
            btn.style.background = 'rgba(2, 132, 199, 0.08)';
            btn.style.color = '#0284c7';
        }
    }

    // ==============================================================================
    // TÌM KIẾM & LỌC THIẾT BỊ (CLIENT-SIDE LIVE FILTER)
    // ==============================================================================
    function filterDevicesTable() {
        const nameQ    = (document.getElementById('filterName')?.value    || '').toLowerCase().trim();
        const mgrQ     = (document.getElementById('filterManager')?.value || '').toLowerCase().trim();
        const catQ     = (document.getElementById('filterCategory')?.value || '').toLowerCase().trim();
        const yearQ    = (document.getElementById('filterYear')?.value     || '').trim();
        const qualityQ = (document.getElementById('filterQuality')?.value  || '').toLowerCase().trim();

        const rows = document.querySelectorAll('#devicesTableBody .device-row');
        let visibleCount = 0;

        rows.forEach(row => {
            const name     = row.dataset.name     || '';
            const manager  = row.dataset.manager  || '';
            const category = row.dataset.category || '';
            const year     = row.dataset.year     || '';
            const quality  = row.dataset.quality  || '';

            const matchName     = !nameQ    || name.includes(nameQ);
            const matchManager  = !mgrQ     || manager.includes(mgrQ);
            const matchCategory = !catQ     || category === catQ;
            const matchYear     = !yearQ    || year.includes(yearQ);
            let   matchQuality  = true;
            if (qualityQ === 'tốt')  matchQuality = quality.startsWith('tốt');
            else if (qualityQ === 'hỏng') matchQuality = quality.includes('hỏng') || quality.includes('hư');
            else if (qualityQ === 'lỗi')  matchQuality = quality.includes('lỗi') || quality.includes('yếu') || quality.includes('cảnh báo');

            const visible = matchName && matchManager && matchCategory && matchYear && matchQuality;
            row.style.display = visible ? '' : 'none';
            if (visible) visibleCount++;
        });

        const countEl = document.getElementById('deviceFilterCount');
        if (countEl) countEl.textContent = visibleCount;

        // Bỏ chọn tất cả khi lọc để tránh nhầm lẫn
        updateBulkActionsState();
    }

    function clearDeviceFilters() {
        ['filterName','filterManager','filterYear'].forEach(id => {
            const el = document.getElementById(id);
            if (el) el.value = '';
        });
        const sel = document.getElementById('filterQuality');
        if (sel) sel.value = '';
        const catSel = document.getElementById('filterCategory');
        if (catSel) catSel.value = '';
        filterDevicesTable();
    }

    // Chuyển đổi tab hiển thị
    function switchTab(tabId, btn) {
        document.querySelectorAll(".tab-content").forEach(tab => {
            tab.classList.remove("active");
        });
        document.querySelectorAll(".tab-btn").forEach(b => {
            b.classList.remove("active");
        });
        
        document.getElementById(tabId).classList.add("active");
        btn.classList.add("active");
        
        if (tabId === 'crawler-tab') {
            pollCrawlerStatus();
            if (!crawlerInterval) {
                crawlerInterval = setInterval(pollCrawlerStatus, 2000);
            }
        } else {
            if (crawlerInterval) {
                clearInterval(crawlerInterval);
                crawlerInterval = null;
            }
        }
        
        if (tabId === 'stats-tab') {
            switchStatsSubTab('stats-top-section');
        }
    }

    // ==============================================================================
    // ĐIỀU KHIỂN SUB-TAB THỐNG KÊ
    // ==============================================================================
    function switchStatsSubTab(subTabId) {
        document.querySelectorAll(".stats-sub-tab").forEach(st => st.style.display = "none");
        document.getElementById(subTabId).style.display = "block";
        
        if (subTabId === 'stats-top-section') {
            loadTopDevices();
        } else if (subTabId === 'stats-inactive-section') {
            loadInactiveTeachers();
        }
    }

    // 1. Thống kê top thiết bị
    function loadTopDevices() {
        const body = document.getElementById("statsTopDevicesBody");
        body.innerHTML = `<tr><td colspan="6" style="text-align: center; color: var(--text-muted);">⏳ Đang tải dữ liệu...</td></tr>`;
        
        fetch('api.php?action=stats_top_devices')
            .then(res => res.json())
            .then(res => {
                if (res.error) {
                    body.innerHTML = `<tr><td colspan="6" style="text-align: center; color: #ef4444;">❌ Lỗi: ${res.error}</td></tr>`;
                    return;
                }
                const data = res.data;
                if (data.length === 0) {
                    body.innerHTML = `<tr><td colspan="6" style="text-align: center; color: var(--text-muted);">Không tìm thấy thiết bị nào đã được sử dụng.</td></tr>`;
                    return;
                }
                let html = '';
                data.forEach((row, idx) => {
                    const imgHtml = row.hinh_anh 
                        ? `<img src="uploads/${row.hinh_anh}" class="thietbi-hinh" style="width:70px; height:70px; object-fit:cover; border-radius:6px; margin-right:10px;">` 
                        : `<div class="thietbi-hinh" style="width:70px; height:70px; display:inline-flex; align-items:center; justify-content:center; background:#f1f5f9; border-radius:6px; margin-right:10px; font-size:0.75rem; color:#94a3b8;">No img</div>`;
                    
                    const timeStr = row.lan_muon_gan_nhat 
                        ? new Date(row.lan_muon_gan_nhat).toLocaleDateString('vi-VN', {hour: '2-digit', minute: '2-digit'}) 
                        : 'Chưa dùng';
                        
                    let medal = idx + 1;
                    if (idx === 0) medal = '🥇';
                    else if (idx === 1) medal = '🥈';
                    else if (idx === 2) medal = '🥉';
                    
                    html += `
                        <tr>
                            <td style="text-align: center; font-weight: bold; font-size: 1.1rem;">${medal}</td>
                            <td>
                                <div style="display: flex; align-items: center;">
                                    ${imgHtml}
                                    <span style="font-weight: 600;">${row.ten_thiet_bi}</span>
                                </div>
                            </td>
                            <td><code style="font-weight: bold; color: var(--accent-blue);">${row.ma_thiet_bi}</code></td>
                            <td style="text-align: center; font-weight: bold;">${row.tong_so_luong_muon}</td>
                            <td style="font-size: 0.85rem; color: var(--text-secondary);">${timeStr}</td>
                        </tr>
                    `;
                });
                body.innerHTML = html;
            })
            .catch(err => {
                body.innerHTML = `<tr><td colspan="6" style="text-align: center; color: #ef4444;">❌ Lỗi kết nối API!</td></tr>`;
            });
    }

    // 2. Giáo viên không mượn thiết bị
    function loadInactiveTeachers() {
        const hkSelect = document.getElementById("statsInactiveHK");
        const hkId = hkSelect ? hkSelect.value : "";
        const fromVal = document.getElementById("statsInactiveFrom").value;
        const toVal = document.getElementById("statsInactiveTo").value;
        const body = document.getElementById("statsInactiveTeachersBody");
        body.innerHTML = `<tr><td colspan="5" style="text-align: center; color: var(--text-muted);">⏳ Đang phân tích đối chiếu...</td></tr>`;
        
        fetch(`api.php?action=stats_inactive_teachers&from=${fromVal}&to=${toVal}&id_hocky_namhoc=${hkId}`)
            .then(res => res.json())

            .then(res => {
                if (res.error) {
                    body.innerHTML = `<tr><td colspan="5" style="text-align: center; color: #ef4444;">❌ Lỗi: ${res.error}</td></tr>`;
                    return;
                }
                const data = res.data;
                if (data.length === 0) {
                    body.innerHTML = `<tr><td colspan="5" style="text-align: center; color: var(--text-muted);">Không có giảng viên nào dạy thực hành trong khoảng thời gian này.</td></tr>`;
                    return;
                }
                let html = '';
                data.forEach(row => {
                    let badge = '';
                    const m = parseInt(row.so_lan_muon_tb);
                    const d = parseInt(row.so_buoi_day);
                    
                    if (m === 0 && d > 0) {
                        badge = `<span class="badge-status badge-delete" style="background: rgba(239, 68, 68, 0.1); color: #ef4444; border-color: rgba(239, 68, 68, 0.25);">🚨 Cảnh báo: Chưa mượn thiết bị (${d} buổi TH - 0 mượn)</span>`;
                    } else if (m < d / 2 && d > 0) {
                        badge = `<span class="badge-status badge-borrowing" style="background: rgba(245, 158, 11, 0.1); color: #d97706; border-color: rgba(245, 158, 11, 0.25);">⚠️ Khá: Tần suất thấp (${m}/${d} buổi)</span>`;
                    } else {
                        badge = `<span class="badge-status badge-returned" style="background: rgba(16, 185, 129, 0.1); color: #10b981; border-color: rgba(16, 185, 129, 0.25);">🟢 Tốt (Hoàn thành đầy đủ)</span>`;
                    }
                    
                    html += `
                        <tr>
                            <td style="font-weight: 600; color: var(--text-primary);">${row.ho_ten_gv}</td>
                            <td style="font-family: monospace; font-size: 0.85rem;">${row.email}</td>
                            <td style="text-align: center; font-weight: bold; color: var(--accent-blue);">${d}</td>
                            <td style="text-align: center; font-weight: bold; color: ${m === 0 ? '#ef4444' : 'var(--text-primary)'};">${m}</td>
                            <td>${badge}</td>
                        </tr>
                    `;
                });
                body.innerHTML = html;
            })
            .catch(err => {
                body.innerHTML = `<tr><td colspan="5" style="text-align: center; color: #ef4444;">❌ Lỗi kết nối API!</td></tr>`;
            });
    }

    // 3. Tra cứu lịch sử sử dụng từng thiết bị
    function loadLookupDeviceHistory(id) {
        if (!id) {
            document.getElementById("statsLookupResultContainer").style.display = "none";
            return;
        }
        
        const body = document.getElementById("statsLookupHistoryBody");
        body.innerHTML = `<tr><td colspan="5" style="text-align: center; color: var(--text-muted);">⏳ Đang truy xuất lịch sử...</td></tr>`;
        document.getElementById("statsLookupResultContainer").style.display = "block";
        
        fetch(`api.php?action=device_history&id=${id}`)
            .then(res => res.json())
            .then(res => {
                if (res.error) {
                    alert("Lỗi: " + res.error);
                    return;
                }
                
                const device = res.device;
                const history = res.history;
                
                // Render device info
                document.getElementById("lookupDeviceName").innerText = device.ten_thiet_bi;
                document.getElementById("lookupDeviceCode").innerText = device.ma_thiet_bi;
                document.getElementById("lookupDeviceLocation").innerText = device.vi_tri || 'Phòng thiết bị';
                document.getElementById("lookupDeviceManager").innerText = device.ten_gv_quan_ly || 'Chưa phân công';
                document.getElementById("lookupDeviceQuality").innerText = device.chat_luong || 'Tốt';
                
                // Bind export excel button
                document.getElementById("btnExportLookupHistory").onclick = () => {
                    window.location.href = 'admin.php?action=export_device_history&id=' + id;
                };

                const img = document.getElementById("lookupDeviceImg");
                const noImg = document.getElementById("lookupDeviceNoImg");
                if (device.hinh_anh) {
                    img.src = "uploads/" + device.hinh_anh;
                    img.style.display = "block";
                    noImg.style.display = "none";
                } else {
                    img.style.display = "none";
                    noImg.style.display = "flex";
                }
                
                if (history.length === 0) {
                    body.innerHTML = `<tr><td colspan="5" style="text-align: center; color: var(--text-muted);">Thiết bị chưa từng được sử dụng.</td></tr>`;
                    return;
                }
                
                let html = '';
                history.forEach(row => {
                    const dateStr = new Date(row.ngay_muon).toLocaleDateString('vi-VN', {hour: '2-digit', minute: '2-digit'});
                        
                    html += `
                        <tr>
                            <td style="font-size: 0.85rem; color: var(--text-secondary);">${dateStr}</td>
                            <td style="font-weight: 600;">${row.ten_giang_vien || 'Chưa rõ'}</td>
                            <td style="font-weight: bold; color: var(--accent-blue); font-size: 0.85rem;">${row.ten_lop}</td>
                            <td><span style="font-size:0.82rem; font-weight:600;">${row.tinh_trang}</span></td>
                            <td style="font-size: 0.82rem; font-style: italic;">${row.ghi_chu || ''}</td>
                        </tr>
                    `;
                });
                body.innerHTML = html;
            })
            .catch(err => {
                body.innerHTML = `<tr><td colspan="5" style="text-align: center; color: #ef4444;">❌ Lỗi kết nối API!</td></tr>`;
            });
    }

    // Hàm cập nhật trạng thái khóa cuộn trang chính khi có bất kỳ modal/overlay nào mở đè lên trong trang admin
    function updateBodyScrollLock() {
        const isAnyActive = 
            (document.getElementById("qrModal") && document.getElementById("qrModal").classList.contains("active")) ||
            (document.getElementById("deviceHistoryModal") && document.getElementById("deviceHistoryModal").classList.contains("active")) ||
            (document.getElementById("editDeviceModal") && document.getElementById("editDeviceModal").classList.contains("active")) ||
            (document.getElementById("imageZoomOverlay") && document.getElementById("imageZoomOverlay").classList.contains("active"));
            
        if (isAnyActive) {
            document.documentElement.classList.add("body-scroll-lock");
            document.body.classList.add("body-scroll-lock");
        } else {
            document.documentElement.classList.remove("body-scroll-lock");
            document.body.classList.remove("body-scroll-lock");
        }
    }

    // ==============================================================================
    // ĐIỀU KHIỂN MODAL LỊCH SỬ THIẾT BỊ
    // ==============================================================================
    function openDeviceHistoryModal(id) {
        const modal = document.getElementById("deviceHistoryModal");
        const body = document.getElementById("deviceHistoryTableBody");
        body.innerHTML = `<tr><td colspan="5" style="text-align: center; color: var(--text-muted);">⏳ Đang tải lịch sử...</td></tr>`;
        
        modal.classList.add("active");
        updateBodyScrollLock();
        history.pushState({ view: "history" }, "");
        
        fetch(`api.php?action=device_history&id=${id}`)
            .then(res => res.json())
            .then(res => {
                if (res.error) {
                    alert("Lỗi: " + res.error);
                    closeDeviceHistoryModal();
                    return;
                }
                const device = res.device;
                const history = res.history;
                
                document.getElementById("historyDeviceName").innerText = "📄 Lịch sử sử dụng: " + device.ten_thiet_bi;
                document.getElementById("historyDeviceCode").innerText = device.ma_thiet_bi;
                document.getElementById("historyDeviceLocation").innerText = device.vi_tri || 'Phòng thiết bị';
                document.getElementById("historyDeviceManager").innerText = device.ten_gv_quan_ly || 'Chưa phân công';
                document.getElementById("historyDeviceUses").innerText = res.total_uses + " lượt";
                
                // Bind export excel button
                document.getElementById("btnExportDeviceHistory").onclick = () => {
                    window.location.href = 'admin.php?action=export_device_history&id=' + id;
                };

                if (history.length === 0) {
                    body.innerHTML = `<tr><td colspan="5" style="text-align: center; color: var(--text-muted);">Thiết bị chưa từng phát sinh giao dịch sử dụng.</td></tr>`;
                    return;
                }
                
                let html = '';
                history.forEach(row => {
                    const dateStr = new Date(row.ngay_muon).toLocaleDateString('vi-VN', {hour: '2-digit', minute: '2-digit'});
                    let statusColor = 'var(--success-green)';
                    const tLower = (row.tinh_trang || '').toLowerCase();
                    if (tLower.includes('hư hỏng') || tLower.includes('hỏng')) {
                        statusColor = 'var(--error-red)';
                    } else if (tLower.includes('lỗi') || tLower.includes('yếu') || tLower.includes('trì') || tLower.includes('cảnh báo')) {
                        statusColor = 'var(--warning-yellow)';
                    }
                    
                    html += `
                        <tr>
                            <td style="padding: 10px; font-size: 0.85rem; color: var(--text-secondary);">${dateStr}</td>
                            <td style="padding: 10px; font-weight: 600;">
                                ${row.ten_giang_vien || 'Chưa rõ'}<br>
                                <small style="color:var(--text-muted); font-weight:normal;">${row.email || ''}</small>
                            </td>
                            <td style="padding: 10px; font-weight: bold; color: var(--accent-blue);">${row.ten_lop}</td>
                            <td style="padding: 10px;"><span style="color:${statusColor}; font-weight:600;">${row.tinh_trang}</span></td>
                            <td style="padding: 10px; font-size: 0.82rem; font-style: italic;">${row.ghi_chu || ''}</td>
                        </tr>
                    `;
                });
                body.innerHTML = html;
            })
            .catch(err => {
                body.innerHTML = `<tr><td colspan="5" style="text-align: center; color: #ef4444;">❌ Lỗi kết nối API!</td></tr>`;
            });
    }

    function closeDeviceHistoryModal(isPopstate = false) {
        const modal = document.getElementById("deviceHistoryModal");
        if (modal && modal.classList.contains("active")) {
            modal.classList.remove("active");
            updateBodyScrollLock();
            if (!isPopstate) {
                isViewClosing = true;
                history.back();
            }
        }
    }

    // ==============================================================================
    // ĐIỀU KHIỂN MODAL XEM & IN/TẢI QR CODE
    // ==============================================================================
    let qrCodeGenerator = null;

    function openQRModal(id, ma_thiet_bi, ten_thiet_bi, gv_quan_ly) {
        const modal = document.getElementById("qrModal");
        modal.dataset.id = id;
        document.getElementById("qrDeviceName").innerText = ten_thiet_bi;
        document.getElementById("qrDeviceCode").innerText = ma_thiet_bi;
        document.getElementById("qrDeviceManager").innerText = gv_quan_ly || "Chưa phân công";
        
        const container = document.getElementById("qrcode_canvas");
        container.innerHTML = ''; // Clear previous QR
        
        // Đường dẫn quét tuyệt đối trỏ tới index.php?scan=... (lấy bằng ID thiết bị)
        const scanUrl = window.location.origin + window.location.pathname.replace('admin.php', 'index.php') + '?scan=' + encodeURIComponent(id);
        
        // Khởi tạo mã QR
        qrCodeGenerator = new QRCode(container, {
            text: scanUrl,
            width: 200,
            height: 200,
            colorDark : "#000000",
            colorLight : "#ffffff",
            correctLevel : QRCode.CorrectLevel.H
        });
        
        modal.classList.add("active");
        updateBodyScrollLock();
        history.pushState({ view: "qr" }, "");
    }

    function closeQRModal(isPopstate = false) {
        const modal = document.getElementById("qrModal");
        if (modal && modal.classList.contains("active")) {
            modal.classList.remove("active");
            updateBodyScrollLock();
            if (!isPopstate) {
                isViewClosing = true;
                history.back();
            }
        }
    }

    // Hàm phụ vẽ nhãn QR Code cực kỳ đẹp bằng HTML5 Canvas theo thiết kế của user
    function generateLabelCanvas(id, code, name, manager, callback) {
    const canvas = document.createElement('canvas');
    const ctx = canvas.getContext('2d');
    
    // Thiết lập font chữ ban đầu để đo đạc chính xác
    const fontTitle = 'bold 18px Inter, Arial, sans-serif';
    const fontLabel = 'bold 15px Inter, Arial, sans-serif';
    const fontValue = '500 15px Inter, Arial, sans-serif';
    const fontCode = 'bold 15px Inter, Arial, sans-serif';
    
    // Đo chiều cao của phần văn bản tự động xuống dòng
    function measureWrappedTextHeight(context, text, maxWidth, lineHeight) {
        const words = text.split(' ');
        let line = '';
        let linesCount = 1;
        
        for (let n = 0; n < words.length; n++) {
            let testLine = line + words[n] + ' ';
            let metrics = context.measureText(testLine);
            let testWidth = metrics.width;
            if (testWidth > maxWidth && n > 0) {
                line = words[n] + ' ';
                linesCount++;
            } else {
                let wordMetrics = context.measureText(words[n]);
                if (wordMetrics.width > maxWidth) {
                    if (line.trim() !== '') {
                        linesCount++;
                        line = '';
                    }
                    let word = words[n];
                    let charLine = '';
                    for (let c = 0; c < word.length; c++) {
                        let testCharLine = charLine + word[c];
                        let testCharWidth = context.measureText(testCharLine).width;
                        if (testCharWidth > maxWidth) {
                            linesCount++;
                            charLine = word[c];
                        } else {
                            charLine = testCharLine;
                        }
                    }
                    line = charLine + ' ';
                } else {
                    line = testLine;
                }
            }
        }
        return linesCount * lineHeight;
    }

    ctx.font = fontValue;
    const nameHeight = measureWrappedTextHeight(ctx, name, 255, 20);
    
    const nameY = 105;
    const codeY = nameY + nameHeight - 20 + 24;
    
    ctx.font = fontCode;
    const codeHeight = measureWrappedTextHeight(ctx, code, 255, 20);
    
    const codeEndY = codeY + codeHeight - 20;
    const managerY = codeEndY + 24;
    
    const qrSize = 240;
    const qrY = managerY + 20;
    const padding = 15;
    
    // 1. Độ phân giải cao cho bản in đẹp: 400x[Dynamic Height]
    canvas.width = 400;
    canvas.height = Math.max(480, qrY + qrSize + padding + 10);
    
    // Khôi phục lại context sau khi thay đổi kích thước canvas
    ctx.fillStyle = '#ffffff';
    ctx.fillRect(0, 0, canvas.width, canvas.height);
    
    // 2. Vẽ viền nét đứt bo tròn góc (Dashed rounded border)
    ctx.strokeStyle = '#000000';
    ctx.lineWidth = 2;
    ctx.setLineDash([8, 6]); // Tạo nét đứt
    
    const radius = 15;
    const x = padding;
    const y = padding;
    const w = canvas.width - 2 * padding;
    const h = canvas.height - 2 * padding;
    
    ctx.beginPath();
    ctx.moveTo(x + radius, y);
    ctx.lineTo(x + w - radius, y);
    ctx.quadraticCurveTo(x + w, y, x + w, y + radius);
    ctx.lineTo(x + w, y + h - radius);
    ctx.quadraticCurveTo(x + w, y + h, x + w - radius, y + h);
    ctx.lineTo(x + radius, y + h);
    ctx.quadraticCurveTo(x, y + h, x, y + h - radius);
    ctx.lineTo(x, y + radius);
    ctx.quadraticCurveTo(x, y, x + radius, y);
    ctx.closePath();
    ctx.stroke();
    
    // Khôi phục lại nét liền để vẽ chữ
    ctx.setLineDash([]);
    
    // 3. Vẽ Tiêu đề chính
    ctx.fillStyle = '#000000';
    ctx.font = fontTitle;
    ctx.textAlign = 'center';
    ctx.fillText('VLUTE - BỘ MÔN Ô TÔ ĐIỆN', canvas.width / 2, 52);
    
    // Vẽ đường kẻ dày ngăn cách tiêu đề
    ctx.lineWidth = 3;
    ctx.beginPath();
    ctx.moveTo(40, 68);
    ctx.lineTo(canvas.width - 40, 68);
    ctx.stroke();
    
    // 4. Vẽ các trường thông tin bên trái
    ctx.textAlign = 'left';
    
    // Hàm phụ tự động xuống dòng khi văn bản quá dài
    function wrapText(context, text, x, y, maxWidth, lineHeight) {
        const words = text.split(' ');
        let line = '';
        let currentY = y;
        
        for (let n = 0; n < words.length; n++) {
            let testLine = line + words[n] + ' ';
            let metrics = context.measureText(testLine);
            let testWidth = metrics.width;
            if (testWidth > maxWidth && n > 0) {
                context.fillText(line.trim(), x, currentY);
                line = words[n] + ' ';
                currentY += lineHeight;
            } else {
                let wordMetrics = context.measureText(words[n]);
                if (wordMetrics.width > maxWidth) {
                    if (line.trim() !== '') {
                        context.fillText(line.trim(), x, currentY);
                        currentY += lineHeight;
                        line = '';
                    }
                    let word = words[n];
                    let charLine = '';
                    for (let c = 0; c < word.length; c++) {
                        let testCharLine = charLine + word[c];
                        let testCharWidth = context.measureText(testCharLine).width;
                        if (testCharWidth > maxWidth) {
                            context.fillText(charLine, x, currentY);
                            charLine = word[c];
                            currentY += lineHeight;
                        } else {
                            charLine = testCharLine;
                        }
                    }
                    line = charLine + ' ';
                } else {
                    line = testLine;
                }
            }
        }
        context.fillText(line.trim(), x, currentY);
        return currentY;
    }
    
    // Thiết bị: [Tên thiết bị]
    ctx.fillStyle = '#000000';
    ctx.font = fontLabel;
    ctx.fillText('Thiết bị:', 40, nameY);
    ctx.font = fontValue;
    wrapText(ctx, name, 105, nameY, 255, 20);
    
    // Mã số: [Mã thiết bị]
    ctx.fillStyle = '#000000';
    ctx.font = fontLabel;
    ctx.fillText('Mã số:', 40, codeY);
    ctx.font = fontCode;
    wrapText(ctx, code, 105, codeY, 255, 20);
    
    // Người quản lý: [Tên giảng viên]
    ctx.fillStyle = '#000000';
    ctx.font = fontLabel;
    ctx.fillText('Người quản lý:', 40, managerY);
    ctx.font = fontValue;
    ctx.fillText(manager || 'Chưa phân công', 150, managerY);
    
    // 5. Tạo mã QR Code tạm để lấy ảnh chèn vào Canvas
    const tempContainer = document.createElement('div');
    const scanUrl = window.location.origin + window.location.pathname.replace('admin.php', 'index.php') + '?scan=' + encodeURIComponent(id);
    
    new QRCode(tempContainer, {
        text: scanUrl,
        width: qrSize,
        height: qrSize,
        colorDark : "#000000",
        colorLight : "#ffffff",
        correctLevel : QRCode.CorrectLevel.H
    });
    
    // Trích xuất hình ảnh QR và vẽ vào canvas
    setTimeout(() => {
        const qrCanvas = tempContainer.querySelector('canvas');
        const qrImg = tempContainer.querySelector('img');
        
        const drawImageAndCallback = (imgSource) => {
            // --- THAY ĐỔI 2: Tính toán lại vị trí căn giữa (X) và tọa độ Y theo kích thước mới ---
            const qrX = (canvas.width - qrSize) / 2;
            const qrY = managerY + 20; // Khoảng cách từ dòng "Người quản lý" xuống QR (giảm bớt từ 30 xuống 20 cho đỡ cấn viền dưới)
            
            ctx.drawImage(imgSource, qrX, qrY, qrSize, qrSize);
            callback(canvas);
        };
        
        if (qrImg && qrImg.src && qrImg.src.startsWith('data:image')) {
            const img = new Image();
            img.onload = function() {
                drawImageAndCallback(img);
            };
            img.src = qrImg.src;
        } else if (qrCanvas) {
            drawImageAndCallback(qrCanvas);
        } else {
            callback(canvas);
        }
    }, 120);
}

    // Tải xuống nhãn QR đơn lẻ có viền và thông tin
    function downloadQRCode() {
        const modal = document.getElementById("qrModal");
        const id = modal.dataset.id;
        const name = document.getElementById("qrDeviceName").innerText;
        const ma = document.getElementById("qrDeviceCode").innerText;
        const manager = document.getElementById("qrDeviceManager").innerText;
        
        showNotification("⏳ Đang chuẩn bị nhãn thiết bị...", "🖼️");
        
        generateLabelCanvas(id, ma, name, manager, function(canvas) {
            const dataUrl = canvas.toDataURL("image/png");
            const link = document.createElement("a");
            link.href = dataUrl;
            link.download = `QR_Label_${ma}.png`;
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            showNotification("Đã tải xuống nhãn thành công!", "✔️");
        });
    }

    // In nhãn QR đơn lẻ có viền và thông tin xếp trên QR
    function printQRCode() {
        const container = document.getElementById("qrcode_canvas");
        const img = container.querySelector("img");
        const canvas = container.querySelector("canvas");
        const name = document.getElementById("qrDeviceName").innerText;
        const ma = document.getElementById("qrDeviceCode").innerText;
        const manager = document.getElementById("qrDeviceManager").innerText;
        
        let imgHtml = '';
        if (img && img.src) {
            imgHtml = `<img src="${img.src}" style="width:200px; height:200px; margin-top:15px;">`;
        } else if (canvas) {
            imgHtml = `<img src="${canvas.toDataURL("image/png")}" style="width:200px; height:200px; margin-top:15px;">`;
        } else {
            alert("Không tìm thấy hình ảnh QR để in!");
            return;
        }
        
        const printWindow = window.open('', '_blank', 'width=600,height=600');
        printWindow.document.write(`
            <html>
            <head>
                <title>In mã QR - ${ma}</title>
                <style>
                    body {
                        font-family: 'Inter', sans-serif;
                        text-align: center;
                        padding: 40px;
                    }
                    .label-box {
                        border: 2px dashed #000;
                        padding: 30px;
                        display: inline-block;
                        border-radius: 10px;
                        text-align: left;
                        max-width: 320px;
                    }
                    h2 {
                        margin: 0 0 15px 0;
                        font-size: 1.3rem;
                        text-align: center;
                        border-bottom: 2px solid #000;
                        padding-bottom: 8px;
                    }
                    .info-line {
                        margin-bottom: 6px;
                        font-size: 1rem;
                        word-break: break-all;
                        overflow-wrap: break-word;
                    }
                    .qr-container {
                        text-align: center;
                    }
                </style>
            </head>
            <body>
                <div class="label-box">
                    <h2>VLUTE - BỘ MÔN Ô TÔ ĐIỆN</h2>
                    <div class="info-line"><strong>Thiết bị:</strong> ${name}</div>
                    <div class="info-line"><strong>Mã số:</strong> <code style="font-family: monospace; font-weight: bold; background: #eaeaea; padding: 2px 5px; border-radius: 3px; word-break: break-all; overflow-wrap: break-word; white-space: normal;">${ma}</code></div>
                    <div class="info-line"><strong>Người quản lý:</strong> ${manager}</div>
                    <div class="qr-container">
                        ${imgHtml}
                    </div>
                </div>
                <script>
                    window.onload = function() {
                        window.print();
                        setTimeout(function() { window.close(); }, 500);
                    };
                <\/script>
            </body>
            </html>
        `);
        printWindow.document.close();
    }

    // ==============================================================================
    // XỬ LÝ CHỌN VÀ THAO TÁC HÀNG LOẠT (BULK ACTIONS LOGIC)
    // ==============================================================================
    function toggleSelectAllDevices(masterCheckbox) {
        const checkboxes = document.querySelectorAll('.device-checkbox');
        checkboxes.forEach(cb => {
            cb.checked = masterCheckbox.checked;
        });
        
        const groupCheckboxes = document.querySelectorAll('.group-checkbox');
        groupCheckboxes.forEach(gcb => {
            gcb.checked = masterCheckbox.checked;
        });
        
        updateBulkActionsState();
    }

    function toggleSelectGroup(groupId, groupCheckbox) {
        const checkboxes = document.querySelectorAll('.sub-device-checkbox-' + groupId);
        checkboxes.forEach(cb => {
            cb.checked = groupCheckbox.checked;
        });
        
        updateBulkActionsState();
    }

    function updateBulkActionsState() {
        const checkedCount = document.querySelectorAll('.device-checkbox:checked').length;
        const bulkBar = document.getElementById('bulkActionsBar');
        const selectedCountEl = document.getElementById('selectedCount');
        
        if (bulkBar && selectedCountEl) {
            if (checkedCount > 0) {
                bulkBar.style.display = 'flex';
                selectedCountEl.innerText = checkedCount;
            } else {
                bulkBar.style.display = 'none';
            }
        }
    }

    // Tải hàng loạt nhãn QR Code rồi tự động nén thành 1 tệp ZIP duy nhất
    function bulkDownloadQRCodes() {
        const checkedBoxes = document.querySelectorAll('.device-checkbox:checked');
        if (checkedBoxes.length === 0) return;
        
        const zip = new JSZip();
        let index = 0;
        
        showNotification("⏳ Đang khởi tạo tệp nén hàng loạt...", "📦");
        
        function processNext() {
            if (index >= checkedBoxes.length) {
                // Đã xử lý vẽ tất cả, tiến hành đóng gói nén ZIP
                showNotification("⚙️ Đang đóng gói tệp nén ZIP...", "📦");
                
                zip.generateAsync({ type: "blob" }).then(function(content) {
                    const link = document.createElement("a");
                    link.href = URL.createObjectURL(content);
                    link.download = `Danh_Sach_Nhan_QR_VLUTE.zip`;
                    document.body.appendChild(link);
                    link.click();
                    document.body.removeChild(link);
                    showNotification("🎉 Tải xuống tệp ZIP nhãn QR thành công!", "✔️");
                });
                return;
            }
            
            const box = checkedBoxes[index];
            const id = box.value;
            const code = box.dataset.code;
            const name = box.dataset.name;
            const manager = box.dataset.manager;
            
            showNotification(`🎨 Đang vẽ nhãn: ${code} (${index + 1}/${checkedBoxes.length})...`, "🖼️");
            
            generateLabelCanvas(id, code, name, manager, function(canvas) {
                // Lấy ảnh PNG Base64
                const dataUrl = canvas.toDataURL("image/png");
                const base64Data = dataUrl.split(',')[1];
                
                // Thêm vào file nén ZIP
                zip.file(`Nhan_QR_${code}.png`, base64Data, { base64: true });
                
                index++;
                setTimeout(processNext, 150); // Giãn cách nhẹ 150ms để vẽ mượt mà
            });
        }
        
        processNext();
    }

    // Tải hàng loạt lịch sử sử dụng của các thiết bị đã chọn và nén thành tệp ZIP duy nhất
    function bulkDownloadHistory() {
        const checkedBoxes = document.querySelectorAll('.device-checkbox:checked');
        if (checkedBoxes.length === 0) return;
        
        const zip = new JSZip();
        let index = 0;
        
        showNotification("⏳ Đang chuẩn bị tải lịch sử thiết bị...", "📦");
        
        function processNext() {
            if (index >= checkedBoxes.length) {
                showNotification("⚙️ Đang đóng gói tệp nén ZIP lịch sử...", "📦");
                
                zip.generateAsync({ type: "blob" }).then(function(content) {
                    const link = document.createElement("a");
                    link.href = URL.createObjectURL(content);
                    link.download = `Lich_Su_Su_Dung_Thiet_Bi_${new Date().toISOString().slice(0,10).replace(/-/g,'')}.zip`;
                    document.body.appendChild(link);
                    link.click();
                    document.body.removeChild(link);
                    showNotification("🎉 Tải xuống tệp ZIP lịch sử thành công!", "✔️");
                });
                return;
            }
            
            const box = checkedBoxes[index];
            const id = box.value;
            const code = box.dataset.code;
            
            showNotification(`📥 Đang tải lịch sử: ${code} (${index + 1}/${checkedBoxes.length})...`, "📊");
            
            fetch(`admin.php?action=export_device_history&id=${id}`)
                .then(res => {
                    if (!res.ok) throw new Error("Lỗi mạng");
                    return res.text();
                })
                .then(htmlContent => {
                    zip.file(`lich_su_thiet_bi_${code}.xls`, htmlContent);
                    index++;
                    setTimeout(processNext, 100);
                })
                .catch(err => {
                    console.error("Lỗi khi tải lịch sử thiết bị ID " + id, err);
                    index++;
                    setTimeout(processNext, 100);
                });
        }
        
        processNext();
    }

    function bulkDeleteDevices() {
        const checkedBoxes = document.querySelectorAll('.device-checkbox:checked');
        if (checkedBoxes.length === 0) return;
        
        if (confirm('Bạn có chắc chắn muốn xóa hàng loạt ' + checkedBoxes.length + ' thiết bị đã chọn? Lịch sử sử dụng của các thiết bị này (nếu có) cũng sẽ bị ảnh hưởng.')) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'admin.php';
            
            const actionInput = document.createElement('input');
            actionInput.type = 'hidden';
            actionInput.name = 'admin_action';
            actionInput.value = 'delete_devices_bulk';
            form.appendChild(actionInput);
            
            checkedBoxes.forEach(box => {
                const idInput = document.createElement('input');
                idInput.type = 'hidden';
                idInput.name = 'ids[]';
                idInput.value = box.value;
                form.appendChild(idInput);
            });
            
            document.body.appendChild(form);
            form.submit();
        }
    }

    // ==============================================================================
    // ĐIỀU KHIỂN MODAL SỬA THIẾT BỊ (EDIT DEVICE)
    // ==============================================================================
    const editModal = document.getElementById("editDeviceModal");
    
    function openEditModal(device) {
        document.getElementById("edit_id").value = device.id;
        document.getElementById("edit_ma_thiet_bi").value = device.ma_thiet_bi;
        document.getElementById("edit_ten_thiet_bi").value = device.ten_thiet_bi;
        document.getElementById("edit_vi_tri").value = device.vi_tri || '';
        document.getElementById("edit_nam_su_dung").value = device.nam_su_dung || '';
        document.getElementById("edit_id_giang_vien_quan_ly").value = device.id_giang_vien_quan_ly || '';
        document.getElementById("edit_id_loai").value = device.id_loai || '';
        document.getElementById("edit_chat_luong").value = device.chat_luong || 'Tốt';
        document.getElementById("edit_tai_lieu_link").value = device.tai_lieu_link || '';
        
        // Preview ảnh hiện tại
        const previewContainer = document.getElementById("edit_image_preview_container");
        const previewImg = document.getElementById("edit_image_preview");
        
        if (device.hinh_anh) {
            previewImg.src = "uploads/" + device.hinh_anh;
            previewContainer.style.display = "block";
        } else {
            previewImg.src = "";
            previewContainer.style.display = "none";
        }
        
        editModal.classList.add("active");
        updateBodyScrollLock();
        history.pushState({ view: "edit" }, "");
    }
    
    function closeEditModal(isPopstate = false) {
        if (editModal && editModal.classList.contains("active")) {
            editModal.classList.remove("active");
            updateBodyScrollLock();
            if (!isPopstate) {
                isViewClosing = true;
                history.back();
            }
        }
    }
    
    const editModalClose = document.getElementById("editModalClose");
    if (editModalClose) editModalClose.addEventListener("click", () => closeEditModal());
    const editModalOverlay = document.getElementById("editModalOverlay");
    if (editModalOverlay) editModalOverlay.addEventListener("click", () => closeEditModal());

    // ==============================================================================
    // ĐIỀU KHIỂN MODAL THÊM THIẾT BỊ MỚI (ADD DEVICE)
    // ==============================================================================
    const addModal = document.getElementById("addDeviceModal");
    
    function openAddModal() {
        if (addModal) {
            // Reset form fields to original empty/default state before opening
            const form = addModal.querySelector("form");
            if (form) {
                form.reset();
                // Set default values explicitly
                const yearInput = document.getElementById("add_nam_su_dung");
                if (yearInput) yearInput.value = new Date().getFullYear();
                const qualityInput = document.getElementById("add_chat_luong");
                if (qualityInput) qualityInput.value = "Tốt";
            }
            addModal.classList.add("active");
            updateBodyScrollLock();
            history.pushState({ view: "add" }, "");
        }
    }
    
    function closeAddModal(isPopstate = false) {
        if (addModal && addModal.classList.contains("active")) {
            addModal.classList.remove("active");
            updateBodyScrollLock();
            if (!isPopstate) {
                isViewClosing = true;
                history.back();
            }
        }
    }

    // ==============================================================================
    // XỬ LÝ INTEGRATION CRAWLER THỜI GIAN THỰC
    // ==============================================================================
    let crawlerInterval = null;
    const consoleOutput = document.getElementById("consoleOutput");
    const btnStart = document.getElementById("btnStartCrawler");
    const statusDot = document.getElementById("statusDot");
    const statusText = document.getElementById("statusText");

    function pollCrawlerStatus() {
        fetch('run_crawler.php?action=status')
            .then(res => res.json())
            .then(data => {
                consoleOutput.innerHTML = data.log || "Đang kết nối...";
                consoleOutput.scrollTop = consoleOutput.scrollHeight;
                
                if (data.status === 'running') {
                    statusDot.className = "indicator-dot active";
                    statusText.innerHTML = "Trạng thái: Đang đồng bộ dữ liệu TKB...";
                    btnStart.disabled = true;
                    btnStart.innerHTML = "⏳ ĐANG ĐỒNG BỘ NGẦM...";
                    btnStart.style.opacity = "0.6";
                } else {
                    statusDot.className = "indicator-dot";
                    statusText.innerHTML = "Trạng thái: Đang dừng / Rảnh";
                    btnStart.disabled = false;
                    btnStart.innerHTML = "🔄 BẮT ĐẦU ĐỒNG BỘ (CÀO TKB)";
                    btnStart.style.opacity = "1";
                }
            })
            .catch(err => {
                consoleOutput.innerHTML = "❌ Lỗi kết nối máy chủ!";
            });
    }

    btnStart.addEventListener("click", () => {
        const semSelect = document.getElementById("crawlerSemesterId");
        const semesterId = semSelect ? semSelect.value : "";
        const semesterText = (semSelect && semSelect.selectedIndex >= 0) ? semSelect.options[semSelect.selectedIndex].text : "mặc định (Học kỳ 2 2025-2026)";
        
        if (confirm(`Tiến trình cào TKB học kỳ [${semesterText}] khoa CKĐL sẽ bắt đầu. Có thể mất khoảng 1-2 phút. Bạn có muốn tiếp tục không?`)) {
            consoleOutput.innerHTML = "🚀 Đang kích hoạt tiến trình cào ngầm cho học kỳ " + semesterText + "...";
            fetch(`run_crawler.php?action=start&id_hocky_namhoc=${semesterId}`)
                .then(res => res.json())
                .then(data => {
                    alert(data.msg);
                    pollCrawlerStatus();
                })
                .catch(err => {
                    alert("Lỗi khi kích hoạt tiến trình cào!");
                });
        }
    });


    // ==============================================================================
    // LIGHTBOX IMAGE ZOOM HANDLERS & HISTORY SPA INTERCEPTORS
    // ==============================================================================
    // Flag to prevent double popstate/history back calls
    let isViewClosing = false;

    function zoomImage(src) {
        const overlay = document.getElementById("imageZoomOverlay");
        const content = document.getElementById("imageZoomContent");
        content.src = src;
        overlay.classList.add("active");
        updateBodyScrollLock();
        history.pushState({ view: "zoom" }, "");
    }

    function closeImageZoom(isPopstate = false) {
        const overlay = document.getElementById("imageZoomOverlay");
        if (overlay && overlay.classList.contains("active")) {
            overlay.classList.remove("active");
            updateBodyScrollLock();
            if (!isPopstate) {
                isViewClosing = true;
                history.back();
            }
        }
    }

    // Close on Escape key
    window.addEventListener("keydown", function(e) {
        if (e.key === "Escape") {
            closeImageZoom();
            closeDeviceHistoryModal();
            closeQRModal();
            closeEditModal();
            closeAddModal();
        }
    });

    // Intercept native Back button on mobile devices (Single Page App UX)
    window.addEventListener("popstate", function(event) {
        if (isViewClosing) {
            isViewClosing = false;
            return;
        }
        
        // Close any open views
        const overlay = document.getElementById("imageZoomOverlay");
        if (overlay && overlay.classList.contains("active")) {
            closeImageZoom(true);
            return;
        }
        
        const qrModal = document.getElementById("qrModal");
        if (qrModal && qrModal.classList.contains("active")) {
            closeQRModal(true);
            return;
        }
        
        const historyModal = document.getElementById("deviceHistoryModal");
        if (historyModal && historyModal.classList.contains("active")) {
            closeDeviceHistoryModal(true);
            return;
        }
        
        const editModal = document.getElementById("editDeviceModal");
        if (editModal && editModal.classList.contains("active")) {
            closeEditModal(true);
            return;
        }
        
        const addModal = document.getElementById("addDeviceModal");
        if (addModal && addModal.classList.contains("active")) {
            closeAddModal(true);
            return;
        }

        const addCatModal = document.getElementById("addCategoryModal");
        if (addCatModal && addCatModal.classList.contains("active")) {
            closeAddCategoryModal(true);
            return;
        }

        const editCatModal = document.getElementById("editCategoryModal");
        if (editCatModal && editCatModal.classList.contains("active")) {
            closeEditCategoryModal(true);
            return;
        }
    });

    // Global click listener for zoomable thumbnails (delegation)
    document.addEventListener("click", function(e) {
        const thumb = e.target.closest(".zoomable-thumb");
        if (thumb && thumb.tagName === "IMG") {
            e.stopPropagation();
            const zoomSrc = thumb.dataset.zoom || thumb.src;
            if (zoomSrc) {
                zoomImage(zoomSrc);
            }
        }
    });

    // ==============================================================================
    // ĐIỀU KHIỂN MODALS QUẢN LÝ THỂ LOẠI (CATEGORIES MODALS)
    // ==============================================================================
    const addCatModal = document.getElementById("addCategoryModal");
    const editCatModal = document.getElementById("editCategoryModal");

    function openAddCategoryModal() {
        if (addCatModal) {
            document.getElementById("add_ten_loai").value = "";
            const colorInput = document.getElementById("add_ma_mau");
            if (colorInput) colorInput.value = "#3b82f6";
            addCatModal.classList.add("active");
            updateBodyScrollLock();
            history.pushState({ view: "add_category" }, "");
        }
    }

    function closeAddCategoryModal(isPopstate = false) {
        if (addCatModal && addCatModal.classList.contains("active")) {
            addCatModal.classList.remove("active");
            updateBodyScrollLock();
            if (!isPopstate) {
                isViewClosing = true;
                history.back();
            }
        }
    }

    function openEditCategoryModal(cat) {
        if (editCatModal) {
            document.getElementById("edit_cat_id").value = cat.id_loai;
            document.getElementById("edit_cat_name").value = cat.ten_loai;
            const colorInput = document.getElementById("edit_cat_color");
            if (colorInput) colorInput.value = cat.ma_mau || "#3b82f6";
            editCatModal.classList.add("active");
            updateBodyScrollLock();
            history.pushState({ view: "edit_category" }, "");
        }
    }

    function closeEditCategoryModal(isPopstate = false) {
        if (editCatModal && editCatModal.classList.contains("active")) {
            editCatModal.classList.remove("active");
            updateBodyScrollLock();
            if (!isPopstate) {
                isViewClosing = true;
                history.back();
            }
        }
    }
</script>
