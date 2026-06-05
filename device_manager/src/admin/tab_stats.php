<?php
/**
 * admin/tab_stats.php
 * ===================
 * HTML Tab 3: Thống kê & Báo cáo nâng cao.
 * Gồm 3 Sub-tabs: Thống kê top thiết bị, Đối chiếu giảng dạy, Tra cứu thiết bị.
 */
?>
<div class="tab-content <?= $active_tab === 'stats-tab' ? 'active' : '' ?>" id="stats-tab">
    <!-- Nút điều hướng các Sub-tabs thống kê -->
    <div style="display:flex; gap:12px; margin-bottom:20px; border-bottom:2px solid #cbd5e1; padding-bottom:12px; flex-wrap:wrap;">
        <button type="button" class="btn-console" style="margin:0; background:#f1f5f9; color:var(--text-primary); border-color:#cbd5e1; font-weight:600;" onclick="switchStatsSubTab('stats-top-section')">🏆 TOP THIẾT BỊ SỬ DỤNG NHIỀU</button>
        <button type="button" class="btn-console" style="margin:0; background:#f1f5f9; color:var(--text-primary); border-color:#cbd5e1; font-weight:600;" onclick="switchStatsSubTab('stats-inactive-section')">📊 ĐỐI CHIẾU GIẢNG DẠY</button>
        <button type="button" class="btn-console" style="margin:0; background:#f1f5f9; color:var(--text-primary); border-color:#cbd5e1; font-weight:600;" onclick="switchStatsSubTab('stats-lookup-section')">🔍 TRA CỨU LỊCH SỬ THIẾT BỊ</button>
    </div>

    <!-- SUB-TAB 1: TOP THIẾT BỊ SỬ DỤNG NHIỀU -->
    <div class="stats-sub-tab" id="stats-top-section" style="display:none;">
        <div class="admin-card">
            <h3 style="border-bottom:1px solid rgba(0,0,0,0.06); padding-bottom:10px; margin-bottom:20px; color:var(--accent-blue);">🏆 TOP 15 THIẾT BỊ ĐƯỢC SỬ DỤNG NHIỀU NHẤT</h3>
            <p style="color:var(--text-secondary); font-size:0.92rem; margin-bottom:20px;">
                Phân tích tần suất và số lượt đăng ký sử dụng của các thiết bị thực hành để có kế hoạch bảo trì hoặc đầu tư thêm trang thiết bị hiệu quả.
            </p>
            
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th style="width:60px; text-align:center;">Hạng</th>
                            <th>Thiết bị</th>
                            <th>Mã thiết bị</th>                    
                            <th style="text-align:center;">Tổng số lượng mượn</th>
                            <th>Lượt mượn gần nhất</th>
                        </tr>
                    </thead>
                    <tbody id="statsTopDevicesBody">
                        <!-- Chèn bằng AJAX -->
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- SUB-TAB 2: ĐỐI CHIẾU GIẢNG DẠY (INACTIVE TEACHERS) -->
    <div class="stats-sub-tab" id="stats-inactive-section" style="display:none;">
        <div class="admin-card">
            <h3 style="border-bottom:1px solid rgba(0,0,0,0.06); padding-bottom:10px; margin-bottom:20px; color:var(--accent-blue);">📊 ĐỐI CHIẾU THỰC HÀNH TKB VÀ PHIẾU MƯỢN THỰC TẾ</h3>
            <p style="color:var(--text-secondary); font-size:0.92rem; line-height:1.6; margin-bottom:20px;">
                So sánh số buổi giảng dạy thực hành được phân công trong Thời khóa biểu với số lần giáo viên đăng ký mượn thiết bị thực tế. 
                Giúp kiểm soát chính xác việc giáo viên có sử dụng thiết bị cho các tiết thực hành hay không.
            </p>
            
            <!-- Bộ lọc Học kỳ đối chiếu -->
            <div style="background:#f8fafc; border:1px solid #cbd5e1; border-radius:14px; padding:15px 20px; display:flex; gap:20px; align-items:flex-end; margin-bottom:20px; flex-wrap:wrap;">
                <div style="flex:1; min-width:280px; max-width:400px;">
                    <label for="statsInactiveHK" style="margin-top:0; font-size:0.85rem; color:var(--text-secondary); font-weight:600; display:block; margin-bottom:6px;">Chọn Học kỳ phân tích đối chiếu:</label>
                    <select id="statsInactiveHK" style="width:100%; height:40px; padding:0 12px; border-radius:8px; border:1px solid #cbd5e1; font-weight:600; background:#fff; font-size:0.92rem; box-sizing:border-box;" onchange="updateInactiveSemesterDates(this)">
                        <?php 
                        $has_selected_active = false;
                        $today = date('Y-m-d');
                        foreach ($semesters as $hk): 
                            // Tự động chọn học kỳ chứa ngày hôm nay làm mặc định
                            $is_current = ($today >= $hk['ngay_bat_dau'] && $today <= $hk['ngay_ket_thuc']);
                            $selected_attr = "";
                            if ($is_current && !$has_selected_active) {
                                $selected_attr = "selected";
                                $has_selected_active = true;
                            }
                            
                            $id = $hk['id_hocky_namhoc'];
                            $start = $hk['ngay_bat_dau'];
                            $end = $hk['ngay_ket_thuc'];
                            $name = htmlspecialchars($hk['ten_hoc_ky']) . " - " . htmlspecialchars($hk['ten_nam_hoc']);
                            
                            if ($id >= 86) {
                                // Tính toán trung điểm học kỳ
                                $ts_start = strtotime($start);
                                $ts_end = strtotime($end);
                                $diff = $ts_end - $ts_start;
                                $mid = $ts_start + round($diff / 2);
                                $mid_date = date('Y-m-d', $mid);
                                $mid_plus_1 = date('Y-m-d', strtotime($mid_date . ' + 1 day'));
                                ?>
                                <option value="<?= $id ?>" data-start="<?= $start ?>" data-end="<?= $end ?>" <?= $selected_attr ?>>
                                    <?= $name ?>
                                </option>
                                <option value="<?= $id ?>_a" data-start="<?= $start ?>" data-end="<?= $mid_date ?>">
                                    <?= $name ?> (Đợt A)
                                </option>
                                <option value="<?= $id ?>_b" data-start="<?= $mid_plus_1 ?>" data-end="<?= $end ?>">
                                    <?= $name ?> (Đợt B)
                                </option>
                                <?php
                            } else {
                                ?>
                                <option value="<?= $id ?>" data-start="<?= $start ?>" data-end="<?= $end ?>" <?= $selected_attr ?>>
                                    <?= $name ?>
                                </option>
                                <?php
                            }
                        endforeach; ?>
                    </select>
                </div>
                
                <div style="width:160px; min-width:140px;">
                    <label for="statsInactiveFrom" style="margin-top:0; font-size:0.85rem; color:var(--text-secondary); font-weight:600; display:block; margin-bottom:6px;">Từ ngày:</label>
                    <input type="date" id="statsInactiveFrom" lang="vi-VN" style="width:100%; height:40px; padding:0 10px; border-radius:8px; border:1px solid #cbd5e1; font-size:0.92rem; background:#fff; font-weight:600; color:var(--text-primary); box-sizing:border-box;" onchange="updateInactiveDateRangeText()">
                </div>
                
                <div style="width:160px; min-width:140px;">
                    <label for="statsInactiveTo" style="margin-top:0; font-size:0.85rem; color:var(--text-secondary); font-weight:600; display:block; margin-bottom:6px;">Đến ngày:</label>
                    <input type="date" id="statsInactiveTo" lang="vi-VN" style="width:100%; height:40px; padding:0 10px; border-radius:8px; border:1px solid #cbd5e1; font-size:0.92rem; background:#fff; font-weight:600; color:var(--text-primary); box-sizing:border-box;" onchange="updateInactiveDateRangeText()">
                </div>
                
                <span id="statsInactiveDateRangeText" style="display:none;"></span>
                
                <div>
                    <button type="button" class="btn-console" style="margin:0; height:40px; background:var(--accent-blue); color:#fff; border-color:var(--accent-blue); font-weight:600; padding:0 24px; border-radius:8px;" onclick="loadInactiveTeachers()">🔍 PHÂN TÍCH ĐỐI CHIẾU</button>
                </div>
            </div>

            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Họ và tên Giảng viên</th>
                            <th>Email liên hệ</th>
                            <th style="text-align:center;">Số buổi dạy TH (TKB)</th>
                            <th style="text-align:center;">Số lần mượn TB thực tế</th>
                            <th>Đánh giá trạng thái</th>
                        </tr>
                    </thead>
                    <tbody id="statsInactiveTeachersBody">
                        <!-- Chèn bằng AJAX -->
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- SUB-TAB 3: TRA CỨU CHI TIẾT TỪNG THIẾT BỊ -->
    <div class="stats-sub-tab" id="stats-lookup-section" style="display:none;">
        <div class="admin-card">
            <h3 style="border-bottom:1px solid rgba(0,0,0,0.06); padding-bottom:10px; margin-bottom:20px; color:var(--accent-blue);">🔍 TRA CỨU LỊCH SỬ & QUẢN LÝ THIẾT BỊ</h3>
            <p style="color:var(--text-secondary); font-size:0.92rem; margin-bottom:20px;">
                Chọn một thiết bị cụ thể dưới đây để truy xuất thông tin chi tiết và toàn bộ nhật ký mượn/bàn giao thiết bị từ trước đến nay.
            </p>
            
            <div style="margin-bottom:25px; max-width:500px;">
                <label for="statsLookupDeviceSelect" style="font-weight:600; color:var(--text-primary); margin-bottom:8px; display:block;">Chọn thiết bị cần tra cứu:</label>
                <select id="statsLookupDeviceSelect" style="width:100%; height:46px; padding:10px 15px; border-radius:10px; border:1px solid #cbd5e1; font-size:0.95rem; font-weight:600;" onchange="loadLookupDeviceHistory(this.value)">
                    <option value="">-- Chọn một thiết bị trong hệ thống --</option>
                    <?php foreach ($devices as $d): ?>
                        <option value="<?= $d['id'] ?>"><?= htmlspecialchars($d['ten_thiet_bi']) ?> [<?= htmlspecialchars($d['ma_thiet_bi']) ?>]</option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Container kết quả tra cứu -->
            <div id="statsLookupResultContainer" style="display:none; border-top:1px dashed #cbd5e1; padding-top:20px; margin-top:20px;">
                <!-- Grid thông tin thiết bị -->
                <div style="display:flex; gap:25px; flex-wrap:wrap; margin-bottom:25px;">
                    <!-- Ảnh preview -->
                    <div style="width:140px; height:140px; border-radius:12px; border:1px solid #cbd5e1; background:#f8fafc; overflow:hidden; display:flex; align-items:center; justify-content:center;">
                        <img id="lookupDeviceImg" src="" alt="Device Image" style="width:100%; height:100%; object-fit:cover; display:none;">
                        <div id="lookupDeviceNoImg" style="width:100%; height:100%; display:flex; align-items:center; justify-content:center; color:var(--text-secondary); font-size:0.85rem; font-weight:600;">No Image</div>
                    </div>
                    
                    <!-- Thông tin -->
                    <div style="flex:1; min-width:280px; display:grid; grid-template-columns:repeat(auto-fit, minmax(200px, 1fr)); gap:12px;">
                        <div>
                            <span style="font-size:0.8rem; color:var(--text-secondary); display:block;">Tên thiết bị:</span>
                            <strong id="lookupDeviceName" style="font-size:1.1rem; color:var(--text-primary);">...</strong>
                        </div>
                        <div>
                            <span style="font-size:0.8rem; color:var(--text-secondary); display:block;">Mã QR thiết bị:</span>
                            <code id="lookupDeviceCode" style="font-weight:bold; color:var(--accent-blue); font-size:0.95rem;">...</code>
                        </div>
                        <div>
                            <span style="font-size:0.8rem; color:var(--text-secondary); display:block;">Vị trí đặt:</span>
                            <span id="lookupDeviceLocation" style="font-weight:600; color:var(--text-primary);">...</span>
                        </div>
                        <div>
                            <span style="font-size:0.8rem; color:var(--text-secondary); display:block;">Giảng viên quản lý:</span>
                            <span id="lookupDeviceManager" style="font-weight:600; color:var(--text-primary);">...</span>
                        </div>
                        <div>
                            <span style="font-size:0.8rem; color:var(--text-secondary); display:block;">Tình trạng hiện tại:</span>
                            <span id="lookupDeviceQuality" class="status-good" style="padding:4px 8px; border-radius:12px; font-weight:600; font-size:0.85rem; display:inline-block;">...</span>
                        </div>
                    </div>
                </div>

                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px; flex-wrap:wrap; gap:10px;">
                    <h4 style="margin:0; color:var(--text-primary); font-weight:700;">📄 LỊCH SỬ BÀN GIAO & SỬ DỤNG CHI TIẾT</h4>
                    <button type="button" class="btn-console" id="btnExportLookupHistory" style="margin:0; background:#10b981; border-color:#10b981; color:#fff; font-weight:600; padding:10px 20px; display:inline-flex; align-items:center; gap:6px;">
                        📤 XUẤT LỊCH SỬ RA EXCEL (.XLS)
                    </button>
                </div>
               

                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th style="width:180px;">Thời gian mượn</th>
                                <th>Giáo viên sử dụng</th>
                                <th>Mã lớp / Mục đích</th>
                                <th style="width:200px;">Tình trạng khi trả</th>
                                <th>Ghi chú lúc trả</th>
                            </tr>
                        </thead>
                        <tbody id="statsLookupHistoryBody">
                            <!-- Chèn bằng AJAX -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    function updateInactiveSemesterDates(select) {
        if (!select) return;
        const selectedOption = select.options[select.selectedIndex];
        if (!selectedOption) return;
        
        const start = selectedOption.dataset.start;
        const end = selectedOption.dataset.end;
        
        document.getElementById("statsInactiveFrom").value = start;
        document.getElementById("statsInactiveTo").value = end;
        
        const rangeText = document.getElementById("statsInactiveDateRangeText");
        if (rangeText) {
            // Định dạng ngày Việt Nam hiển thị thân thiện (dd/mm/yyyy)
            const formatStart = start.split('-').reverse().join('/');
            const formatEnd = end.split('-').reverse().join('/');
            rangeText.innerText = `${formatStart} - ${formatEnd}`;
        }
    }

    function updateInactiveDateRangeText() {
        const start = document.getElementById("statsInactiveFrom").value;
        const end = document.getElementById("statsInactiveTo").value;
        const rangeText = document.getElementById("statsInactiveDateRangeText");
        if (rangeText && start && end) {
            const formatStart = start.split('-').reverse().join('/');
            const formatEnd = end.split('-').reverse().join('/');
            rangeText.innerText = `${formatStart} - ${formatEnd}`;
        }
    }
    
    // Tự động đồng bộ khoảng ngày khi nạp trang lần đầu
    document.addEventListener("DOMContentLoaded", function() {
        const select = document.getElementById("statsInactiveHK");
        if (select) {
            updateInactiveSemesterDates(select);
        }
    });
</script>
