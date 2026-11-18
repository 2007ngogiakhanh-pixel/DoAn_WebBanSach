<?php
session_start();
require "auth.php";
require "db.php";
require_role("customer");

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    exit("Phương thức không được hỗ trợ.");
}
if (!verify_csrf_token()) {
    http_response_code(403);
    exit("Yêu cầu không hợp lệ. Vui lòng tải lại trang và thử lại.");
}

$orderId = filter_var($_POST["order_id"] ?? null, FILTER_VALIDATE_INT);
$bookId = filter_var($_POST["book_id"] ?? null, FILTER_VALIDATE_INT);
$rating = filter_var($_POST["rating"] ?? null, FILTER_VALIDATE_INT);
$commentInput = $_POST["comment"] ?? "";
$comment = is_string($commentInput) ? trim($commentInput) : "";
$customerId = (int)$_SESSION["user_id"];

if ($orderId === false || $orderId <= 0 || $bookId === false || $bookId <= 0) {
    http_response_code(400);
    exit("Thông tin đơn hàng hoặc sách không hợp lệ.");
}
if ($rating === false || $rating < 1 || $rating > 5
    || $comment === "" || mb_strlen($comment, "UTF-8") > 2000) {
    $_SESSION["review_notice"] = "Vui lòng chọn 1 đến 5 sao và nhập nhận xét tối đa 2.000 ký tự.";
    header("Location: my_orders.php");
    exit;
}

$purchase = mysqli_prepare(
    $conn,
    "SELECT 1
     FROM orders o
     JOIN order_details od ON od.order_id = o.id
     WHERE o.id = ? AND o.customer_id = ? AND o.status = 'delivered'
       AND od.book_id = ? LIMIT 1"
);
if (!$purchase) {
    http_response_code(500);
    exit("Không thể xác minh đơn hàng để gửi đánh giá.");
}
mysqli_stmt_bind_param($purchase, "iii", $orderId, $customerId, $bookId);
if (!mysqli_stmt_execute($purchase)) {
    http_response_code(500);
    exit("Không thể xác minh đơn hàng để gửi đánh giá.");
}
if (!mysqli_fetch_assoc(mysqli_stmt_get_result($purchase))) {
    http_response_code(409);
    exit("Chỉ có thể đánh giá sách trong đơn hàng đã nhận.");
}

$reviewerName = $_SESSION["user_name"];
$saveReview = mysqli_prepare(
    $conn,
    "INSERT INTO book_reviews (book_id, customer_id, reviewer_name, rating, comment)
     VALUES (?, ?, ?, ?, ?)
     ON DUPLICATE KEY UPDATE reviewer_name = VALUES(reviewer_name),
       rating = VALUES(rating), comment = VALUES(comment), created_at = CURRENT_TIMESTAMP"
);
if (!$saveReview) {
    http_response_code(500);
    exit("Không thể lưu đánh giá. Vui lòng thử lại.");
}
mysqli_stmt_bind_param($saveReview, "iisis", $bookId, $customerId, $reviewerName, $rating, $comment);
if (!mysqli_stmt_execute($saveReview)) {
    http_response_code(500);
    exit("Không thể lưu đánh giá. Vui lòng thử lại.");
}

$_SESSION["review_notice"] = "Đã lưu đánh giá của bạn.";
header("Location: my_orders.php");
exit;
