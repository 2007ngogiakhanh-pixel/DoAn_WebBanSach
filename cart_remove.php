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

$id = filter_var($_POST["id"] ?? null, FILTER_VALIDATE_INT);
if ($id === false || $id <= 0) {
    http_response_code(400);
    exit("Sách cần xóa không hợp lệ.");
}

$cart = normalize_cart($_SESSION["cart"] ?? []);
$oldCart = $cart;
unset($cart[$id]);
$_SESSION["cart"] = $cart;
if (!sync_session_cart($conn)) {
    $_SESSION["cart"] = $oldCart;
    http_response_code(500);
    exit("Không thể cập nhật giỏ hàng. Vui lòng thử lại.");
}
unset($_SESSION["cart_error"]);
header("Location: cart.php");
exit;