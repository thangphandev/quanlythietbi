<?php
/**
 * callback.php
 * ============
 * Xử lý phản hồi từ Google OAuth 2.0. Đổi mã code lấy Token và xác thực giảng viên.
 */
require_once 'config.php';

// 1. Kiểm tra mã lỗi hoặc từ chối từ người dùng
if (isset($_GET['error'])) {
    die("❌ Lỗi xác thực từ Google: " . htmlspecialchars($_GET['error']));
}

if (!isset($_GET['code']) || !isset($_GET['state'])) {
    header("Location: login.php");
    exit;
}

// 2. Kiểm tra CSRF State Token
if (empty($_GET['state']) || $_GET['state'] !== $_SESSION['oauth_state']) {
    die("❌ Lỗi bảo mật: Token bảo mật không khớp (CSRF Protection). Vui lòng thử lại!");
}

// Xóa state để tránh dùng lại
unset($_SESSION['oauth_state']);

$code = $_GET['code'];

// 3. Đổi authorization code lấy Access Token
$token_url = "https://oauth2.googleapis.com/token";
$post_data = [
    'code'          => $code,
    'client_id'     => GOOGLE_CLIENT_ID,
    'client_secret' => GOOGLE_CLIENT_SECRET,
    'redirect_uri'  => GOOGLE_REDIRECT_URI,
    'grant_type'    => 'authorization_code'
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $token_url);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post_data));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Tắt kiểm tra SSL trên local nếu cần
$response = curl_exec($ch);

if (curl_errno($ch)) {
    die("❌ Lỗi cURL khi đổi token: " . curl_error($ch));
}
curl_close($ch);

$token_data = json_decode($response, true);
if (isset($token_data['error'])) {
    die("❌ Lỗi từ Google Token API: " . htmlspecialchars($token_data['error_description'] ?? $token_data['error']));
}

$access_token = $token_data['access_token'];

// 4. Lấy thông tin tài khoản người dùng từ Google UserInfo API
$userinfo_url = "https://www.googleapis.com/oauth2/v3/userinfo";
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $userinfo_url);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Authorization: Bearer $access_token"
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
$user_info_response = curl_exec($ch);
curl_close($ch);

$user_profile = json_decode($user_info_response, true);
if (!$user_profile || !isset($user_profile['email'])) {
    die("❌ Không thể lấy thông tin email từ tài khoản Google của bạn!");
}

$google_email = $user_profile['email'];

// Chỉ cho phép email của trường VLUTE (@vlute.edu.vn)
if (substr(strtolower($google_email), -13) !== '@vlute.edu.vn') {
    die("<div style='font-family:sans-serif; text-align:center; margin-top:50px; color:#ef4444;'>
            <h2>❌ TRUY CẬP BỊ TỪ CHỐI</h2>
            <p>Chỉ tài khoản email trường VLUTE (<strong>@vlute.edu.vn</strong>) mới được truy cập hệ thống!</p>
            <a href='login.php' style='color:#0056b3; font-weight:bold; text-decoration:none;'>Quay lại trang Đăng nhập</a>
         </div>");
}

$google_sub   = $user_profile['sub']; // ID định danh duy nhất của Google

// 5. Truy vấn tìm giảng viên trong cơ sở dữ liệu
try {
    // Tìm giảng viên khớp email Google hoặc khớp google_sub đã liên kết trước đó
    $stmt = $db->prepare("SELECT id_giang_vien, ho_ten_gv, email FROM giang_vien WHERE email = :email OR google_sub = :google_sub");
    $stmt->execute([
        'email'      => $google_email,
        'google_sub' => $google_sub
    ]);
    $lecturer = $stmt->fetch();
    
    if ($lecturer) {
        // Cập nhật trường google_sub và email nếu chưa có
        $update_stmt = $db->prepare("UPDATE giang_vien SET google_sub = :google_sub, email = :email WHERE id_giang_vien = :id_giang_vien");
        $update_stmt->execute([
            'google_sub'    => $google_sub,
            'email'          => $google_email,
            'id_giang_vien' => $lecturer['id_giang_vien']
        ]);
        
        // Thiết lập session đăng nhập thành công
        $_SESSION['id_giang_vien'] = $lecturer['id_giang_vien'];
        $_SESSION['ho_ten_gv']     = $lecturer['ho_ten_gv'];
        $_SESSION['email']          = $google_email;
        $_SESSION['is_demo']        = false;
        
        // Chuyển hướng về trang chủ
        header("Location: index.php");
        exit;
    } else {
        // Nếu email mới chưa được liên kết với bất kỳ Giảng viên nào trong CSDL:
        // Lưu thông tin tạm thời vào Session và chuyển hướng đến trang liên kết tài khoản (link_account.php)
        $_SESSION['pending_google_email'] = $google_email;
        $_SESSION['pending_google_sub']   = $google_sub;
        
        header("Location: link_account.php");
        exit;
    }
} catch (PDOException $e) {
    die("❌ Lỗi cơ sở dữ liệu khi xác thực giảng viên: " . $e->getMessage());
}
