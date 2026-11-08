<?php
session_start();
if (!isset($_SESSION["admin"])) {
    header("Location: login.php");
    exit;
}
require "../auth.php";
require "../db.php";
require "../book_helpers.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: index.php");
    exit;
}
if (!verify_csrf_token()) {
    http_response_code(403);
    exit("Yêu cầu không hợp lệ. Vui lòng tải lại trang và thử lại.");
}

$bookId = (int)($_POST["id"] ?? 0);
$action = $_POST["action"] ?? "";
if ($bookId <= 0 || !in_array($action, ["approve", "reject"], true)) {
    http_response_code(400);
    exit("Yêu cầu không hợp lệ.");
}

$stmt = mysqli_prepare($conn, "SELECT image, pending_image FROM books WHERE id = ? AND seller_id IS NOT NULL AND approval_status = 'pending'");
mysqli_stmt_bind_param($stmt, "i", $bookId);
mysqli_stmt_execute($stmt);
$book = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
if (!$book) {
    http_response_code(404);
    exit("Không tìm thấy yêu cầu đăng sách đang chờ duyệt.");
}

if ($action === "reject") {
    $update = mysqli_prepare($conn, "UPDATE books SET approval_status = 'rejected' WHERE id = ? AND approval_status = 'pending'");
    mysqli_stmt_bind_param($update, "i", $bookId);
    if (!mysqli_stmt_execute($update)) {
        http_response_code(500);
        exit("Không thể từ chối yêu cầu đăng sách.");
    }
    header("Location: index.php");
    exit;
}

$pendingImage = $book["pending_image"];
$imageMoved = false;
if ($pendingImage !== null && $pendingImage !== "") {
    if (!promote_pending_book_image($pendingImage)) {
        http_response_code(500);
        exit("Không thể chuyển ảnh đã duyệt vào thư mục uploads.");
    }
    $imageMoved = true;
}

mysqli_begin_transaction($conn);
if ($pendingImage !== null && $pendingImage !== "") {
    $update = mysqli_prepare($conn, "UPDATE books SET image = pending_image, pending_image = NULL, approval_status = 'approved' WHERE id = ? AND approval_status = 'pending'");
} else {
    $update = mysqli_prepare($conn, "UPDATE books SET approval_status = 'approved' WHERE id = ? AND approval_status = 'pending'");
}
mysqli_stmt_bind_param($update, "i", $bookId);
$updated = mysqli_stmt_execute($update) && mysqli_stmt_affected_rows($update) === 1;
if (!$updated || !mysqli_commit($conn)) {
    mysqli_rollback($conn);
    if ($imageMoved) {
        $source = __DIR__ . "/../uploads/" . $pendingImage;
        $destination = __DIR__ . "/../pending_uploads/" . $pendingImage;
        if (is_file($source) && !file_exists($destination)) {
            rename($source, $destination);
        }
    }
    http_response_code(500);
    exit("Không thể duyệt sách. Vui lòng thử lại.");
}

if ($imageMoved && $book["image"] !== "default.jpg" && $book["image"] !== $pendingImage) {
    $oldImage = __DIR__ . "/../uploads/" . basename($book["image"]);
    if (is_file($oldImage)) {
        unlink($oldImage);
    }
}
header("Location: index.php");
exit;
