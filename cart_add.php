<?php
// cart_add.php - Thêm sách vào giỏ hàng
session_start();
require "db.php";
require "auth.php";
require "cart_storage.php";

if ($_SERVER["REQUEST_METHOD"] != "POST") {
    header("Location: index.php");
    exit;
}
if (!verify_csrf_token()) {
    http_response_code(403);
    exit("Yêu cầu không hợp lệ. Vui lòng tải lại trang và thử lại.");
}

$id = filter_var($_POST["id"] ?? null, FILTER_VALIDATE_INT);
$qty = filter_var($_POST["qty"] ?? null, FILTER_VALIDATE_INT);
if ($id === false || $id <= 0 || $qty === false || $qty < 1) {
    http_response_code(400);
    exit("Số lượng sách không hợp lệ.");
}

$stmt = mysqli_prepare($conn, "SELECT stock_quantity FROM books WHERE id = ? AND is_available = 1 AND stock_quantity > 0 AND (seller_id IS NULL OR approval_status = 'approved')");
if ($stmt === false) {
    http_response_code(500);
    exit("Không thể kiểm tra sách. Hãy xác nhận đã chạy migration database mới nhất.");
}
mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);
$book = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
if (!$book) {
    $_SESSION["cart_error"] = "Sách không tồn tại, đang chờ duyệt hoặc đã hết hàng.";
    header("Location: cart.php");
    exit;
}

$oldCart = normalize_cart($_SESSION["cart"] ?? []);
$newQuantity = ($oldCart[$id] ?? 0) + $qty;
if ($newQuantity > (int)$book["stock_quantity"]) {
    $_SESSION["cart_error"] = "Số lượng vượt quá tồn kho hiện có.";
    header("Location: cart.php");
    exit;
}

$_SESSION["cart"] = $oldCart;
$_SESSION["cart"][$id] = $newQuantity;
if (!sync_session_cart($conn)) {
    $_SESSION["cart"] = $oldCart;
    http_response_code(500);
    exit("Không thể lưu giỏ hàng. Vui lòng thử lại.");
}
unset($_SESSION["cart_error"]);

header("Location: cart.php");
exit;