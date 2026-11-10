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
$stockInput = $_POST["stock_quantity"] ?? null;
$availabilityInput = $_POST["is_available"] ?? null;
if ($bookId === false || $bookId <= 0 || ($stockInput === null && $availabilityInput === null)) {
    http_response_code(400);
    exit("Thông tin trạng thái sách không hợp lệ.");
}

if ($stockInput !== null) {
    $stockQuantity = filter_var($stockInput, FILTER_VALIDATE_INT);
    if ($stockQuantity === false || $stockQuantity < 0 || $stockQuantity > 4294967295) {
        http_response_code(400);
        exit("Số lượng tồn kho không hợp lệ.");
    }
    if ($isAdmin) {
        $stmt = mysqli_prepare($conn, "UPDATE books SET stock_quantity = ?, is_available = IF(? > 0, 1, 0) WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "iii", $stockQuantity, $stockQuantity, $bookId);
    } else {
        $sellerId = (int)$_SESSION["user_id"];
        $stmt = mysqli_prepare($conn, "UPDATE books SET stock_quantity = ?, is_available = IF(? > 0, 1, 0) WHERE id = ? AND seller_id = ?");
        mysqli_stmt_bind_param($stmt, "iiii", $stockQuantity, $stockQuantity, $bookId, $sellerId);
    }
} else {
    $availability = filter_var($availabilityInput, FILTER_VALIDATE_INT);
    if (!in_array($availability, [0, 1], true)) {
        http_response_code(400);
        exit("Trạng thái sách không hợp lệ.");
    }
    if ($isAdmin) {
        $stmt = mysqli_prepare($conn, "UPDATE books SET is_available = ? WHERE id = ? AND (? = 0 OR stock_quantity > 0)");
        mysqli_stmt_bind_param($stmt, "iii", $availability, $bookId, $availability);
    } else {
        $sellerId = (int)$_SESSION["user_id"];
        $stmt = mysqli_prepare($conn, "UPDATE books SET is_available = ? WHERE id = ? AND seller_id = ? AND (? = 0 OR stock_quantity > 0)");
        mysqli_stmt_bind_param($stmt, "iiii", $availability, $bookId, $sellerId, $availability);
    }
}

if (!mysqli_stmt_execute($stmt)) {
    http_response_code(500);
    exit("Không thể cập nhật trạng thái sách.");
}
if (mysqli_stmt_affected_rows($stmt) === 0) {
    if ($isAdmin) {
        $check = mysqli_prepare($conn, "SELECT 1 FROM books WHERE id = ?");
        mysqli_stmt_bind_param($check, "i", $bookId);
    } else {
        $check = mysqli_prepare($conn, "SELECT 1 FROM books WHERE id = ? AND seller_id = ?");
        mysqli_stmt_bind_param($check, "ii", $bookId, $sellerId);
    }
    mysqli_stmt_execute($check);
    if (!mysqli_fetch_assoc(mysqli_stmt_get_result($check))) {
        http_response_code(404);
        exit("Không tìm thấy sách hoặc bạn không có quyền chỉnh sửa.");
    }
    if ($stockInput === null && $availability === 1) {
        http_response_code(409);
        exit("Hãy tăng tồn kho trước khi bật bán lại.");
    }
}

header("Location: " . ($isAdmin ? "admin/index.php#books" : "seller/index.php#books"));
exit;
