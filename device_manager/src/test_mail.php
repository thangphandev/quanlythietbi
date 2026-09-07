<?php
/**
 * Công cụ kiểm tra (Debug) gửi mail và đọc biến môi trường
 * Lưu file này vào thư mục src/test_mail.php và truy cập từ trình duyệt để kiểm tra
 */
require_once 'config.php';
require_once 'mail_helper.php';
require_once 'libs/PHPMailer/Exception.php';
require_once 'libs/PHPMailer/PHPMailer.php';
require_once 'libs/PHPMailer/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

echo "<h1>Giao Diện Kiểm Tra Gửi Mail (Debug Mode)</h1>";
echo "<h3>1. Kiểm tra biến môi trường (.env) đang được hệ thống nạp:</h3>";
echo "<ul>";
echo "<li><strong>SMTP_HOST:</strong> " . (defined('SMTP_HOST') ? SMTP_HOST : 'CHƯA ĐỊNH NGHĨA') . "</li>";
echo "<li><strong>SMTP_PORT:</strong> " . (defined('SMTP_PORT') ? SMTP_PORT : 'CHƯA ĐỊNH NGHĨA') . "</li>";
echo "<li><strong>SMTP_USER:</strong> " . (defined('SMTP_USER') ? (empty(SMTP_USER) ? 'RỖNG (Lỗi ở đây!)' : SMTP_USER) : 'CHƯA ĐỊNH NGHĨA') . "</li>";
echo "<li><strong>SMTP_NAME:</strong> " . (defined('SMTP_NAME') ? SMTP_NAME : 'CHƯA ĐỊNH NGHĨA') . "</li>";
echo "<li><strong>ALLOW_DEMO:</strong> " . (ALLOW_DEMO ? 'TRUE' : 'FALSE') . "</li>";
echo "</ul>";

echo "<hr>";
echo "<h3>2. Thử nghiệm kết nối tới máy chủ Mail...</h3>";

if (empty(SMTP_USER)) {
    echo "<p style='color: red;'><strong>❌ DỪNG LẠI:</strong> SMTP_USER bị rỗng. Máy chủ Linux của bạn không đọc được tài khoản Gmail trong file .env. Vui lòng kiểm tra lại file .env như hướng dẫn!</p>";
} else {
    echo "<p>Đang tiến hành gửi email thử nghiệm (Test Email)...</p>";
    $mail = new PHPMailer(true);
    try {
        $mail->SMTPDebug = 2; // Bật chế độ hiển thị chi tiết lỗi kết nối (Debug Mode 2)
        $mail->Debugoutput = 'html';
        
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USER;
        $mail->Password   = SMTP_PASS;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = SMTP_PORT;
        $mail->CharSet    = 'UTF-8';

        $mail->setFrom(SMTP_USER, SMTP_NAME);
        $mail->addAddress(SMTP_USER, "Admin"); // Tự gửi cho chính mình để test

        $mail->isHTML(true);
        $mail->Subject = 'Test Email từ Máy Chủ Linux';
        $mail->Body    = 'Nếu bạn nhận được email này, tính năng gửi mail đã hoạt động hoàn hảo!';

        $mail->send();
        echo "<p style='color: green;'><strong>✅ THÀNH CÔNG:</strong> Email đã được gửi đi không có lỗi!</p>";
    } catch (Exception $e) {
        echo "<p style='color: red;'><strong>❌ THẤT BẠI:</strong> Email không thể gửi đi. Chi tiết lỗi:</p>";
        echo "<pre>{$mail->ErrorInfo}</pre>";
    }
}
