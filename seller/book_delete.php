<?php
session_start();
require "../auth.php";
require_role("seller");
require "../db.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: index.php");
    exit;
}
if (!verify_csrf_token()) {
    http_response_code(403);
    exit("Yêu cầu không hợp lệ. Vui lòng tải lại trang và thử lại.");
}

$id = (int)($_POST["id"] ?? 0);
$sellerId = (int)$_SESSION["user_id"];
$stmt = mysqli_prepare($conn, "SELECT image FROM books WHERE id = ? AND seller_id = ?");
mysqli_stmt_bind_param($stmt, "ii", $id, $sellerId);
mysqli_stmt_execute($stmt);
$book = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
if ($book) {
    $delete = mysqli_prepare($conn, "DELETE FROM books WHERE id = ? AND seller_id = ?");
    mysqli_stmt_bind_param($delete, "ii", $id, $sellerId);
    if (!mysqli_stmt_execute($delete)) {
        http_response_code(500);
        exit("Không thể xóa sách. Vui lòng thử lại.");
    }
    if ($book["image"] !== "default.jpg" && is_file(__DIR__ . "/../uploads/" . $book["image"])) {
        unlink(__DIR__ . "/../uploads/" . $book["image"]);
    }
}

header("Location: index.php");
exit;
