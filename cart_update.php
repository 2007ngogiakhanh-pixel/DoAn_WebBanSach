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

$postedQuantities = $_POST["qty"] ?? [];
if (!is_array($postedQuantities)) {
    http_response_code(400);
    exit("Danh sách số lượng không hợp lệ.");
}

$cart = normalize_cart($_SESSION["cart"] ?? []);
$nextCart = $cart;
foreach ($postedQuantities as $rawId => $rawQuantity) {
    $id = filter_var($rawId, FILTER_VALIDATE_INT);
    $quantity = filter_var($rawQuantity, FILTER_VALIDATE_INT);
    if ($id === false || $id <= 0 || $quantity === false) {
        http_response_code(400);
        exit("Số lượng cập nhật không hợp lệ.");
    }
    if (!array_key_exists($id, $cart)) {
        continue;
    }
    if ($quantity <= 0) {
        unset($nextCart[$id]);
        continue;
    }

    $stmt = mysqli_prepare($conn, "SELECT stock_quantity, is_available, seller_id, approval_status FROM books WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);
    $book = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    if (!$book || !(bool)$book["is_available"] || ($book["seller_id"] !== null && $book["approval_status"] !== "approved")
        || $quantity > (int)$book["stock_quantity"]) {
        $_SESSION["cart_error"] = "Số lượng cập nhật vượt quá tồn kho hoặc sách không còn bán.";
        header("Location: cart.php");
        exit;
    }
    $nextCart[$id] = $quantity;
}

$_SESSION["cart"] = $nextCart;
if (!sync_session_cart($conn)) {
    $_SESSION["cart"] = $cart;
    http_response_code(500);
    exit("Không thể lưu thay đổi giỏ hàng. Vui lòng thử lại.");
}
unset($_SESSION["cart_error"]);
header("Location: cart.php");
exit;