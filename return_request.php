<?php
session_start();
require "auth.php";
require "db.php";
require_role(["customer", "seller"]);

if ($_SERVER["REQUEST_METHOD"] !== "POST" && $_SERVER["REQUEST_METHOD"] !== "GET") {
    http_response_code(405);
    exit("Phương thức không được hỗ trợ.");
}

$orderId = filter_var($_POST["order_id"] ?? $_GET["order_id"] ?? null, FILTER_VALIDATE_INT);
$customerId = (int)$_SESSION["user_id"];
if ($orderId === false || $orderId <= 0) {
    http_response_code(400);
    exit("Mã đơn hàng không hợp lệ.");
}

$orderStmt = mysqli_prepare($conn, "SELECT id, order_code, status FROM orders WHERE id = ? AND customer_id = ?");
if (!$orderStmt) {
    http_response_code(500);
    exit("Không thể tải đơn hàng.");
}
mysqli_stmt_bind_param($orderStmt, "ii", $orderId, $customerId);
if (!mysqli_stmt_execute($orderStmt)) {
    http_response_code(500);
    exit("Không thể tải đơn hàng.");
}
$order = mysqli_fetch_assoc(mysqli_stmt_get_result($orderStmt));
if (!$order) {
    http_response_code(404);
    exit("Không tìm thấy đơn hàng.");
}

$error = "";
$reason = "";
$itemCondition = "";
$requestedItems = [];
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!verify_csrf_token()) {
        http_response_code(403);
        exit("Yêu cầu không hợp lệ. Vui lòng tải lại trang và thử lại.");
    }
    $reasonInput = $_POST["reason"] ?? "";
    $conditionInput = $_POST["item_condition"] ?? "";
    $reason = is_string($reasonInput) ? trim($reasonInput) : "";
    $itemCondition = is_string($conditionInput) ? trim($conditionInput) : "";
    $itemInput = $_POST["items"] ?? [];
    if (is_array($itemInput)) {
        foreach ($itemInput as $bookIdInput => $quantityInput) {
            if (!is_scalar($bookIdInput) || !is_scalar($quantityInput)) {
                $error = "Số lượng trả không hợp lệ.";
                break;
            }
            $bookId = filter_var($bookIdInput, FILTER_VALIDATE_INT);
            $quantity = filter_var($quantityInput, FILTER_VALIDATE_INT);
            if ($bookId !== false && $bookId > 0 && $quantity !== false && $quantity > 0) {
                $requestedItems[$bookId] = $quantity;
            } elseif ($quantityInput !== "0" && $quantityInput !== "") {
                $error = "Số lượng trả không hợp lệ.";
                break;
            }
        }
    }

    if ($reason === "" || mb_strlen($reason, "UTF-8") > 2000
        || $itemCondition === "" || mb_strlen($itemCondition, "UTF-8") > 2000) {
        $error = "Vui lòng nhập lý do trả hàng và tình trạng hàng hóa (tối đa 2.000 ký tự mỗi mục).";
    } elseif ($requestedItems === []) {
        $error = "Vui lòng chọn số lượng cần trả cho ít nhất một sản phẩm.";
    } elseif ($order["status"] !== "delivered") {
        $error = "Chỉ có thể yêu cầu trả hàng khi đơn đã được giao.";
    } elseif (!mysqli_begin_transaction($conn)) {
        $error = "Không thể gửi yêu cầu trả hàng. Vui lòng thử lại.";
    } else {
        $lockedOrderStmt = mysqli_prepare($conn, "SELECT status FROM orders WHERE id = ? AND customer_id = ? FOR UPDATE");
        if (!$lockedOrderStmt) {
            mysqli_rollback($conn);
            http_response_code(500);
            exit("Không thể xác nhận trạng thái đơn hàng.");
        }
        mysqli_stmt_bind_param($lockedOrderStmt, "ii", $orderId, $customerId);
        if (!mysqli_stmt_execute($lockedOrderStmt)) {
            mysqli_rollback($conn);
            http_response_code(500);
            exit("Không thể xác nhận trạng thái đơn hàng.");
        }
        $lockedOrder = mysqli_fetch_assoc(mysqli_stmt_get_result($lockedOrderStmt));
        if (!$lockedOrder || $lockedOrder["status"] !== "delivered") {
            mysqli_rollback($conn);
            $error = "Trạng thái đơn đã thay đổi. Chỉ có thể yêu cầu trả hàng khi đơn đã được giao.";
        } else {
            $insertRequest = mysqli_prepare($conn, "INSERT INTO order_return_requests (order_id, customer_id, reason, item_condition) VALUES (?, ?, ?, ?)");
            $saved = $insertRequest !== false;
            if ($saved) {
                mysqli_stmt_bind_param($insertRequest, "iiss", $orderId, $customerId, $reason, $itemCondition);
                $saved = mysqli_stmt_execute($insertRequest);
            }
            $returnRequestId = $saved ? (int)mysqli_insert_id($conn) : 0;
            $lineStmt = mysqli_prepare($conn, "SELECT quantity FROM order_details WHERE order_id = ? AND book_id = ?");
            $insertItem = mysqli_prepare($conn, "INSERT INTO order_return_items (return_request_id, book_id, quantity) VALUES (?, ?, ?)");
            $saved = $saved && $lineStmt !== false && $insertItem !== false;
            foreach ($requestedItems as $bookId => $quantity) {
                if (!$saved) {
                    break;
                }
                mysqli_stmt_bind_param($lineStmt, "ii", $orderId, $bookId);
                $saved = mysqli_stmt_execute($lineStmt);
                $orderLine = $saved ? mysqli_fetch_assoc(mysqli_stmt_get_result($lineStmt)) : null;
                if (!$orderLine || $quantity > (int)$orderLine["quantity"]) {
                    $saved = false;
                    $error = "Số lượng trả không được vượt quá số lượng đã mua.";
                    break;
                }
                mysqli_stmt_bind_param($insertItem, "iii", $returnRequestId, $bookId, $quantity);
                $saved = mysqli_stmt_execute($insertItem);
            }
            if ($saved) {
                $updateOrder = mysqli_prepare($conn, "UPDATE orders SET status = 'return_requested' WHERE id = ? AND status = 'delivered'");
                $saved = $updateOrder !== false;
                if ($saved) {
                    mysqli_stmt_bind_param($updateOrder, "i", $orderId);
                    $saved = mysqli_stmt_execute($updateOrder) && mysqli_stmt_affected_rows($updateOrder) === 1;
                }
            }
            if ($saved && mysqli_commit($conn)) {
                header("Location: my_orders.php");
                exit;
            }
            mysqli_rollback($conn);
            if ($error === "") {
                $error = "Không thể gửi yêu cầu trả hàng. Có thể đơn đã có yêu cầu trả hàng trước đó.";
            }
        }
    }
}

$itemsStmt = mysqli_prepare($conn, "SELECT od.book_id, b.title, od.quantity FROM order_details od JOIN books b ON b.id = od.book_id WHERE od.order_id = ? ORDER BY b.title");
if (!$itemsStmt) {
    http_response_code(500);
    exit("Không thể tải sản phẩm trong đơn hàng.");
}
mysqli_stmt_bind_param($itemsStmt, "i", $orderId);
if (!mysqli_stmt_execute($itemsStmt)) {
    http_response_code(500);
    exit("Không thể tải sản phẩm trong đơn hàng.");
}
$items = mysqli_stmt_get_result($itemsStmt);
?>
<?php include __DIR__ . '/views/return_request.html';
