<?php
session_start();
require "../auth.php";
require "../db.php";

$isAdmin = isset($_SESSION["admin"]);
$isSeller = ($_SESSION["user_role"] ?? null) === "seller";
if ($isSeller) {
    require_role("seller");
}
if (!$isAdmin && !$isSeller) {
    http_response_code(403);
    exit("Bạn không có quyền cập nhật trạng thái đơn hàng.");
}
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
    "pending_confirmation" => ["confirmed", "cancelled"],
    "confirmed" => ["preparing", "cancelled"],
    "preparing" => ["shipping", "cancelled"],
    "shipping" => ["delivered", "cancelled"],
];
if (!mysqli_begin_transaction($conn)) {
    http_response_code(500);
    exit("Không thể cập nhật trạng thái đơn hàng.");
}

$select = mysqli_prepare($conn, "SELECT status FROM orders WHERE id = ? FOR UPDATE");
if (!$select) {
    mysqli_rollback($conn);
    http_response_code(500);
    exit("Không thể tải đơn hàng.");
}
mysqli_stmt_bind_param($select, "i", $orderId);
$selected = mysqli_stmt_execute($select);
$order = $selected ? mysqli_fetch_assoc(mysqli_stmt_get_result($select)) : null;
if (!$order) {
    mysqli_rollback($conn);
    http_response_code(404);
    exit("Không tìm thấy đơn hàng.");
}

if ($isSeller) {
    $sellerId = (int)$_SESSION["user_id"];
    $ownership = mysqli_prepare($conn, "SELECT 1 FROM order_details od JOIN books b ON b.id = od.book_id WHERE od.order_id = ? AND b.seller_id = ? LIMIT 1");
    if (!$ownership) {
        mysqli_rollback($conn);
        http_response_code(500);
        exit("Không thể xác minh quyền cập nhật đơn hàng.");
    }
    mysqli_stmt_bind_param($ownership, "ii", $orderId, $sellerId);
    if (!mysqli_stmt_execute($ownership) || !mysqli_fetch_assoc(mysqli_stmt_get_result($ownership))) {
        mysqli_rollback($conn);
        http_response_code(403);
        exit("Bạn không có quyền cập nhật đơn hàng này.");
    }
}

$sellerTransitions = [
    "pending_confirmation" => ["confirmed"],
    "confirmed" => ["preparing"],
    "preparing" => ["shipping"],
    "shipping" => ["delivered"],
];
$allowedTransitions = $isAdmin ? $transitions : $sellerTransitions;
if (!isset($allowedTransitions[$order["status"]])
    || !in_array($newStatus, $allowedTransitions[$order["status"]], true)) {
    mysqli_rollback($conn);
    http_response_code(409);
    exit("Không thể chuyển đơn sang trạng thái đã chọn.");
}

if ($newStatus === "cancelled") {
    $details = mysqli_prepare($conn, "SELECT book_id, quantity FROM order_details WHERE order_id = ?");
    if (!$details) {
        mysqli_rollback($conn);
        http_response_code(500);
        exit("Không thể tải sách trong đơn hàng.");
    }
    mysqli_stmt_bind_param($details, "i", $orderId);
    if (!mysqli_stmt_execute($details)) {
        mysqli_rollback($conn);
        http_response_code(500);
        exit("Không thể khôi phục tồn kho của đơn hàng.");
    }
    $items = mysqli_stmt_get_result($details);
    $restock = mysqli_prepare($conn, "UPDATE books SET is_available = IF(stock_quantity = 0, 1, is_available), stock_quantity = stock_quantity + ? WHERE id = ?");
    if (!$restock) {
        mysqli_rollback($conn);
        http_response_code(500);
        exit("Không thể khôi phục tồn kho của đơn hàng.");
    }
    while ($item = mysqli_fetch_assoc($items)) {
        $bookId = (int)$item["book_id"];
        $quantity = (int)$item["quantity"];
        mysqli_stmt_bind_param($restock, "ii", $quantity, $bookId);
        if (!mysqli_stmt_execute($restock)) {
            mysqli_rollback($conn);
            http_response_code(500);
            exit("Không thể khôi phục tồn kho của đơn hàng.");
        }
    }
}

$update = mysqli_prepare($conn, "UPDATE orders SET status = ? WHERE id = ?");
if (!$update) {
    mysqli_rollback($conn);
    http_response_code(500);
    exit("Không thể cập nhật trạng thái đơn hàng.");
}
mysqli_stmt_bind_param($update, "si", $newStatus, $orderId);
if (!mysqli_stmt_execute($update) || !mysqli_commit($conn)) {
    mysqli_rollback($conn);
    http_response_code(500);
    exit("Không thể lưu trạng thái đơn hàng.");
}

$redirect = $isAdmin ? "../admin/index.php" : "../seller/index.php";
if ($orderCode !== "") {
    $redirect .= "?order_code=" . urlencode($orderCode);
}
header("Location: " . $redirect . "#orders");
exit;
