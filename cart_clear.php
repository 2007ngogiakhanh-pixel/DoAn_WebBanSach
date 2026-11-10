<?php
session_start();
require "db.php";
require "auth.php";
require "cart_storage.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: cart.php");
    exit;
}
if (!verify_csrf_token()) {
    http_response_code(403);
    exit("Yêu cầu không hợp lệ. Vui lòng tải lại trang và thử lại.");
}

$oldCart = normalize_cart($_SESSION["cart"] ?? []);
$_SESSION["cart"] = [];
if (!sync_session_cart($conn)) {
    $_SESSION["cart"] = $oldCart;
    http_response_code(500);
    exit("Không thể xóa giỏ hàng. Vui lòng thử lại.");
}
unset($_SESSION["cart_error"]);

header("Location: cart.php");
exit;