<?php
require_once __DIR__ . '/config.php';

// Nạp các lớp PHPMailer (nếu chưa dùng Composer)
require_once __DIR__ . '/libs/PHPMailer/Exception.php';
require_once __DIR__ . '/libs/PHPMailer/PHPMailer.php';
require_once __DIR__ . '/libs/PHPMailer/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

/**
 * Gửi email cảnh báo khi thiết bị bị hư hỏng
 *
 * @param string $managerEmail Email của giảng viên quản lý thiết bị
 * @param string $managerName Tên của giảng viên quản lý
 * @param array $deviceInfo Thông tin thiết bị (chứa mã, tên, vị trí)
 * @param string $damageDetail Chi tiết hư hỏng
 * @param string $reporterName Tên người báo cáo lỗi
 * @return bool Trạng thái gửi mail thành công hay thất bại
 */
function sendDeviceDamageAlert($managerEmail, $managerName, $deviceInfo, $damageDetail, $reporterName) {
    if (empty($managerEmail)) {
        return false;
    }

    $mail = new PHPMailer(true);

    try {
        // Cấu hình Server
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USER;
        $mail->Password   = SMTP_PASS;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = SMTP_PORT;

        $mail->CharSet    = 'UTF-8';

        // Người gửi, người nhận
        $mail->setFrom(SMTP_USER, SMTP_NAME);
        $mail->addAddress($managerEmail, $managerName);

        // Nội dung Email
        $mail->isHTML(true);
        $mail->Subject = "⚠️ [CẢNH BÁO] Thiết bị {$deviceInfo['ten_thiet_bi']} vừa được báo cáo HƯ HỎNG";
        
        $body = "
        <div style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>
            <h2 style='color: #d9534f;'>Cảnh báo Hư Hỏng Thiết Bị</h2>
            <p>Kính gửi thầy/cô <strong>{$managerName}</strong>,</p>
            <p>Hệ thống Quản lý Thiết bị vừa ghi nhận một báo cáo thay đổi trạng thái từ \"Hoạt động bình thường\" sang \"Hư hỏng\" đối với thiết bị do thầy/cô quản lý. Chi tiết sự cố như sau:</p>
            
            <table style='width: 100%; border-collapse: collapse; margin-top: 15px; margin-bottom: 15px;'>
                <tr>
                    <td style='padding: 8px; border: 1px solid #ddd; background-color: #f9f9f9; width: 150px;'><strong>Mã thiết bị:</strong></td>
                    <td style='padding: 8px; border: 1px solid #ddd;'>{$deviceInfo['ma_thiet_bi']}</td>
                </tr>
                <tr>
                    <td style='padding: 8px; border: 1px solid #ddd; background-color: #f9f9f9;'><strong>Tên thiết bị:</strong></td>
                    <td style='padding: 8px; border: 1px solid #ddd;'>{$deviceInfo['ten_thiet_bi']}</td>
                </tr>
                <tr>
                    <td style='padding: 8px; border: 1px solid #ddd; background-color: #f9f9f9;'><strong>Vị trí lưu trữ:</strong></td>
                    <td style='padding: 8px; border: 1px solid #ddd;'>{$deviceInfo['vi_tri']}</td>
                </tr>
            </table>

            <h3 style='color: #5bc0de;'>Thông tin báo cáo lỗi</h3>
            <ul>
                <li><strong>Tình trạng:</strong> Hư hỏng</li>
                <li><strong>Nội dung nhận xét:</strong> \"<em>{$damageDetail}</em>\"</li>
                <li><strong>Người báo cáo:</strong> {$reporterName}</li>
                <li><strong>Thời gian báo cáo:</strong> " . date('d/m/Y H:i:s') . "</li>
            </ul>

            <p>Vui lòng đăng nhập vào hệ thống để kiểm tra chi tiết và có phương án bảo trì/sửa chữa trong thời gian sớm nhất.</p>
            <br>
            <p>Trân trọng,<br><strong>Hệ thống Quản lý Thiết bị Khoa CKĐL</strong></p>
        </div>";

        $mail->Body = $body;

        $mail->send();
        return true;
    } catch (Exception $e) {
        // Cần log lại lỗi nếu không gửi được mail
        error_log("Không thể gửi email cảnh báo hư hỏng. Mailer Error: {$mail->ErrorInfo}");
        return false;
    }
}
