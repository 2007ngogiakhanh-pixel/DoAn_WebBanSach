<?php
session_start();
if (!isset($_SESSION["admin"])) {
    header("Location: login.php");
    exit;
}
require "../auth.php";
require "../db.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    exit("Phương thức không được hỗ trợ.");
}
if (!verify_csrf_token()) {
    http_response_code(403);
    exit("Yêu cầu không hợp lệ. Vui lòng tải lại trang và thử lại.");
}

$orderId = filter_var($_POST["order_id"] ?? null, FILTER_VALIDATE_INT);
$newStatus = $_POST["status"] ?? "";
$orderCode = $_POST["order_code"] ?? "";
if ($orderId === false || $orderId <= 0 || !is_string($newStatus)) {
    http_response_code(400);
    exit("Thông tin trạng thái đơn hàng không hợp lệ.");
}
if (!is_string($orderCode) || ($orderCode !== "" && !preg_match('/\A\d{10}\z/', $orderCode))) {
    http_response_code(400);
    exit("Mã đơn không hợp lệ.");
}

$transitions = [
    "pending_confirmation" => ["shipping", "cancelled"],
    "shipping" => ["delivered", "cancelled"],
];
if (!mysqli_begin_transaction($conn)) {
    http_response_code(500);
    exit("Không thể cập nhật trạng thái đơn hàng.");
}

$select = mysqli_prepare($conn, "SELECT status FROM orders WHERE id = ? FOR UPDATE");
mysqli_stmt_bind_param($select, "i", $orderId);
$selected = mysqli_stmt_execute($select);
$order = $selected ? mysqli_fetch_assoc(mysqli_stmt_get_result($select)) : null;
$canTransition = $order
    && isset($transitions[$order["status"]])
    && in_array($newStatus, $transitions[$order["status"]], true);
if (!$canTransition) {
    mysqli_rollback($conn);
    http_response_code($order ? 409 : 404);
    exit($order
        ? "Không thể chuyển đơn sang trạng thái đã chọn."
        : "Không tìm thấy đơn hàng.");
}

$update = mysqli_prepare($conn, "UPDATE orders SET status = ? WHERE id = ?");
mysqli_stmt_bind_param($update, "si", $newStatus, $orderId);
if (!mysqli_stmt_execute($update) || !mysqli_commit($conn)) {
    mysqli_rollback($conn);
    http_response_code(500);
    exit("Không thể lưu trạng thái đơn hàng.");
}

$redirect = "../admin/index.php";
if ($orderCode !== "") {
    $redirect .= "?order_code=" . urlencode($orderCode);
}
header("Location: " . $redirect . "#orders");
exit;
