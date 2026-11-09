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

$userId = filter_var($_POST["id"] ?? null, FILTER_VALIDATE_INT);
if ($userId === false || $userId <= 0) {
    http_response_code(400);
    exit("Tài khoản không hợp lệ.");
}

$stmt = mysqli_prepare($conn, "SELECT role FROM users WHERE id = ?");
mysqli_stmt_bind_param($stmt, "i", $userId);
if (!mysqli_stmt_execute($stmt)) {
    http_response_code(500);
    exit("Không thể kiểm tra tài khoản.");
}
$user = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
if (!$user) {
    http_response_code(404);
    exit("Không tìm thấy tài khoản.");
}

if (!mysqli_begin_transaction($conn)) {
    http_response_code(500);
    exit("Không thể bắt đầu xóa tài khoản.");
}

$updated = true;
if ($user["role"] === "customer") {
    $unlinkOrders = mysqli_prepare($conn, "UPDATE orders SET customer_id = NULL WHERE customer_id = ?");
    mysqli_stmt_bind_param($unlinkOrders, "i", $userId);
    $updated = mysqli_stmt_execute($unlinkOrders);
} else {
    $rejectPending = mysqli_prepare($conn, "UPDATE books SET approval_status = 'rejected' WHERE seller_id = ? AND approval_status = 'pending'");
    mysqli_stmt_bind_param($rejectPending, "i", $userId);
    $updated = mysqli_stmt_execute($rejectPending);
}

if ($updated) {
    $delete = mysqli_prepare($conn, "DELETE FROM users WHERE id = ?");
    mysqli_stmt_bind_param($delete, "i", $userId);
    $updated = mysqli_stmt_execute($delete) && mysqli_stmt_affected_rows($delete) === 1;
}
if (!$updated || !mysqli_commit($conn)) {
    mysqli_rollback($conn);
    http_response_code(500);
    exit("Không thể xóa tài khoản. Lịch sử đơn hàng vẫn được giữ nguyên.");
}

header("Location: index.php#accounts");
exit;
