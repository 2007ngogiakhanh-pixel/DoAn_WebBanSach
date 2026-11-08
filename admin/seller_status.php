<?php
session_start();
if (!isset($_SESSION["admin"])) {
    header("Location: login.php");
    exit;
}
require "../db.php";
require "../auth.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: index.php");
    exit;
}
if (!verify_csrf_token()) {
    http_response_code(403);
    exit("Yêu cầu không hợp lệ. Vui lòng tải lại trang và thử lại.");
}

$id = (int)($_POST["id"] ?? 0);
$action = $_POST["action"] ?? "";
$status = $action === "approve" ? "active" : ($action === "reject" ? "rejected" : "");
if ($id <= 0 || $status === "") {
    http_response_code(400);
    exit("Yêu cầu không hợp lệ.");
}

$stmt = mysqli_prepare($conn, "UPDATE users SET status = ? WHERE id = ? AND role = 'seller' AND status = 'pending'");
mysqli_stmt_bind_param($stmt, "si", $status, $id);
if (!mysqli_stmt_execute($stmt)) {
    http_response_code(500);
    exit("Không thể cập nhật tài khoản người bán.");
}
header("Location: index.php");
exit;
