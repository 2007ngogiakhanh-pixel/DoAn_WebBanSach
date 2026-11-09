<?php
session_start();
require "auth.php";
require "db.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    exit("Phương thức không được hỗ trợ.");
}

$isAdmin = isset($_SESSION["admin"]);
$isSeller = ($_SESSION["user_role"] ?? null) === "seller";
if ($isSeller) {
    require_role("seller");
}
if (!$isAdmin && !$isSeller) {
    http_response_code(403);
    exit("Bạn không có quyền cập nhật trạng thái sách.");
}
if (!verify_csrf_token()) {
    http_response_code(403);
    exit("Yêu cầu không hợp lệ. Vui lòng tải lại trang và thử lại.");
}

$bookId = filter_var($_POST["book_id"] ?? null, FILTER_VALIDATE_INT);
$availability = filter_var($_POST["is_available"] ?? null, FILTER_VALIDATE_INT);
if ($bookId === false || $bookId <= 0 || !in_array($availability, [0, 1], true)) {
    http_response_code(400);
    exit("Thông tin trạng thái sách không hợp lệ.");
}

if ($isAdmin) {
    $stmt = mysqli_prepare($conn, "UPDATE books SET is_available = ? WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "ii", $availability, $bookId);
} else {
    $sellerId = (int)$_SESSION["user_id"];
    $stmt = mysqli_prepare($conn, "UPDATE books SET is_available = ? WHERE id = ? AND seller_id = ?");
    mysqli_stmt_bind_param($stmt, "iii", $availability, $bookId, $sellerId);
}

if (!mysqli_stmt_execute($stmt)) {
    http_response_code(500);
    exit("Không thể cập nhật trạng thái sách.");
}
if (mysqli_stmt_affected_rows($stmt) !== 1) {
    http_response_code(404);
    exit("Không tìm thấy sách hoặc bạn không có quyền chỉnh sửa.");
}

header("Location: " . ($isAdmin ? "admin/index.php#books" : "seller/index.php#books"));
exit;
