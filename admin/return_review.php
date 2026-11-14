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

$requestId = filter_var($_POST["return_request_id"] ?? null, FILTER_VALIDATE_INT);
$action = $_POST["action"] ?? "";
$adminNoteInput = $_POST["admin_note"] ?? "";
$adminNote = is_string($adminNoteInput) ? trim($adminNoteInput) : "";
if ($requestId === false || $requestId <= 0 || !in_array($action, ["approve", "reject"], true)
    || mb_strlen($adminNote, "UTF-8") > 2000) {
    http_response_code(400);
    exit("Thông tin xử lý trả hàng không hợp lệ.");
}
if (!mysqli_begin_transaction($conn)) {
    http_response_code(500);
    exit("Không thể xử lý yêu cầu trả hàng.");
}

$requestStmt = mysqli_prepare($conn, "SELECT rr.order_id, rr.status AS return_status, o.status AS order_status FROM order_return_requests rr JOIN orders o ON o.id = rr.order_id WHERE rr.id = ? FOR UPDATE");
if (!$requestStmt) {
    mysqli_rollback($conn);
    http_response_code(500);
    exit("Không thể tải yêu cầu trả hàng.");
}
mysqli_stmt_bind_param($requestStmt, "i", $requestId);
$loaded = mysqli_stmt_execute($requestStmt);
$request = $loaded ? mysqli_fetch_assoc(mysqli_stmt_get_result($requestStmt)) : null;
if (!$request) {
    mysqli_rollback($conn);
    http_response_code(404);
    exit("Không tìm thấy yêu cầu trả hàng.");
}
if ($request["return_status"] !== "pending" || $request["order_status"] !== "return_requested") {
    mysqli_rollback($conn);
    http_response_code(409);
    exit("Yêu cầu trả hàng đã được xử lý hoặc đơn hàng không còn ở trạng thái chờ duyệt.");
}

if ($action === "approve") {
    $itemsStmt = mysqli_prepare($conn, "SELECT book_id, quantity FROM order_return_items WHERE return_request_id = ?");
    $restockStmt = mysqli_prepare($conn, "UPDATE books SET is_available = IF(stock_quantity = 0, 1, is_available), stock_quantity = stock_quantity + ? WHERE id = ?");
    if (!$itemsStmt || !$restockStmt) {
        mysqli_rollback($conn);
        http_response_code(500);
        exit("Không thể cập nhật tồn kho.");
    }
    mysqli_stmt_bind_param($itemsStmt, "i", $requestId);
    if (!mysqli_stmt_execute($itemsStmt)) {
        mysqli_rollback($conn);
        http_response_code(500);
        exit("Không thể tải sản phẩm trả hàng.");
    }
    $items = mysqli_stmt_get_result($itemsStmt);
    while ($item = mysqli_fetch_assoc($items)) {
        $bookId = (int)$item["book_id"];
        $quantity = (int)$item["quantity"];
        mysqli_stmt_bind_param($restockStmt, "ii", $quantity, $bookId);
        if (!mysqli_stmt_execute($restockStmt)) {
            mysqli_rollback($conn);
            http_response_code(500);
            exit("Không thể nhập sản phẩm trả hàng vào kho.");
        }
    }
    $requestStatus = "approved";
    $orderStatus = "returned";
} else {
    $requestStatus = "rejected";
    $orderStatus = "delivered";
}

$updateRequest = mysqli_prepare($conn, "UPDATE order_return_requests SET status = ?, admin_note = ?, reviewed_at = CURRENT_TIMESTAMP WHERE id = ? AND status = 'pending'");
$updateOrder = mysqli_prepare($conn, "UPDATE orders SET status = ? WHERE id = ? AND status = 'return_requested'");
if (!$updateRequest || !$updateOrder) {
    mysqli_rollback($conn);
    http_response_code(500);
    exit("Không thể lưu kết quả xử lý trả hàng.");
}
mysqli_stmt_bind_param($updateRequest, "ssi", $requestStatus, $adminNote, $requestId);
mysqli_stmt_bind_param($updateOrder, "si", $orderStatus, $request["order_id"]);
if (!mysqli_stmt_execute($updateRequest) || mysqli_stmt_affected_rows($updateRequest) !== 1
    || !mysqli_stmt_execute($updateOrder) || mysqli_stmt_affected_rows($updateOrder) !== 1
    || !mysqli_commit($conn)) {
    mysqli_rollback($conn);
    http_response_code(500);
    exit("Không thể lưu kết quả xử lý trả hàng.");
}

header("Location: index.php#orders");
exit;
