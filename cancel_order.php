<?php
session_start();
require "auth.php";
require "db.php";
require_role(["customer", "seller"]);

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    exit("Phương thức không được hỗ trợ.");
}
if (!verify_csrf_token()) {
    http_response_code(403);
    exit("Yêu cầu không hợp lệ. Vui lòng tải lại trang và thử lại.");
}

$orderId = filter_var($_POST["order_id"] ?? null, FILTER_VALIDATE_INT);
$customerId = (int)$_SESSION["user_id"];
if ($orderId === false || $orderId <= 0) {
    http_response_code(400);
    exit("Mã đơn hàng không hợp lệ.");
}
if (!mysqli_begin_transaction($conn)) {
    http_response_code(500);
    exit("Không thể hủy đơn hàng.");
}

$orderStmt = mysqli_prepare($conn, "SELECT status FROM orders WHERE id = ? AND customer_id = ? FOR UPDATE");
if (!$orderStmt) {
    mysqli_rollback($conn);
    http_response_code(500);
    exit("Không thể tải đơn hàng.");
}
mysqli_stmt_bind_param($orderStmt, "ii", $orderId, $customerId);
$found = mysqli_stmt_execute($orderStmt);
$order = $found ? mysqli_fetch_assoc(mysqli_stmt_get_result($orderStmt)) : null;
if (!$order) {
    mysqli_rollback($conn);
    http_response_code(404);
    exit("Không tìm thấy đơn hàng.");
}

if (!in_array($order["status"], ["confirmed", "preparing", "shipping"], true)) {
    mysqli_rollback($conn);
    http_response_code(409);
    exit("Chỉ có thể gửi yêu cầu hủy đơn đã xác nhận và chưa hoàn tất giao hàng.");
}

$update = mysqli_prepare($conn, "UPDATE orders SET status = 'cancellation_requested' WHERE id = ? AND status = ?");
if (!$update) {
    mysqli_rollback($conn);
    http_response_code(500);
    exit("Không thể gửi yêu cầu hủy đơn.");
}
mysqli_stmt_bind_param($update, "is", $orderId, $order["status"]);
if (!mysqli_stmt_execute($update) || mysqli_stmt_affected_rows($update) !== 1 || !mysqli_commit($conn)) {
    mysqli_rollback($conn);
    http_response_code(500);
    exit("Không thể lưu yêu cầu hủy đơn.");
}

header("Location: my_orders.php");
exit;
