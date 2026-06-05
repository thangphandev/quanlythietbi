<?php
/**
 * logout.php
 * ==========
 * Đăng xuất khỏi hệ thống và xóa session.
 */
require_once 'config.php';

// Xóa tất cả các biến Session
$_SESSION = array();

// Nếu muốn xóa sạch cookie session
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Hủy Session
session_destroy();

// Chuyển hướng về trang đăng nhập
header("Location: login.php");
exit;
