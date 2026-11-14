<?php
session_start();
require "auth.php";
require "db.php";
require_role(["customer", "seller"]);

$customerId = (int)$_SESSION["user_id"];
$orderInput = $_GET["order_code"] ?? "";
$orderCode = is_string($orderInput) ? trim($orderInput) : "";
$error = "";
if (!is_string($orderInput) || ($orderCode !== "" && !preg_match('/\A\d{10}\z/', $orderCode))) {
    $error = "Mã đơn phải gồm đúng 10 chữ số.";
}

if ($error !== "") {
    $orders = false;
} elseif ($orderCode === "") {
    $stmt = mysqli_prepare($conn, "SELECT o.id, o.order_code, o.status, o.created_at, o.total_price, o.payment_method, rr.status AS return_status, rr.admin_note AS return_admin_note, (SELECT GROUP_CONCAT(CONCAT(od.quantity, ' x ', b.title) SEPARATOR ', ') FROM order_details od JOIN books b ON b.id = od.book_id WHERE od.order_id = o.id) AS items FROM orders o LEFT JOIN order_return_requests rr ON rr.order_id = o.id WHERE o.customer_id = ? ORDER BY o.created_at DESC LIMIT 100");
    mysqli_stmt_bind_param($stmt, "i", $customerId);
} else {
    $stmt = mysqli_prepare($conn, "SELECT o.id, o.order_code, o.status, o.created_at, o.total_price, o.payment_method, rr.status AS return_status, rr.admin_note AS return_admin_note, (SELECT GROUP_CONCAT(CONCAT(od.quantity, ' x ', b.title) SEPARATOR ', ') FROM order_details od JOIN books b ON b.id = od.book_id WHERE od.order_id = o.id) AS items FROM orders o LEFT JOIN order_return_requests rr ON rr.order_id = o.id WHERE o.customer_id = ? AND o.order_code = ? ORDER BY o.created_at DESC");
    mysqli_stmt_bind_param($stmt, "is", $customerId, $orderCode);
}
if ($error === "") {
    mysqli_stmt_execute($stmt);
    $orders = mysqli_stmt_get_result($stmt);
}
?>
<?php include __DIR__ . '/views/my_orders.html';
