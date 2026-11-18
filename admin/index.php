<?php
// admin/index.php - Quản lý sách
session_start();
require "../auth.php";
if (!isset($_SESSION["admin"])) {
    header("Location: ../login.php");
    exit;
}
require "../db.php";

$sql = "SELECT b.*, (SELECT GROUP_CONCAT(c2.name ORDER BY c2.name SEPARATOR ', ') FROM book_categories bc2 JOIN categories c2 ON c2.id = bc2.category_id WHERE bc2.book_id = b.id) AS category_names, u.full_name AS seller_name, u.email AS seller_email
        FROM books b
        LEFT JOIN users u ON b.seller_id = u.id
        WHERE b.seller_id IS NULL OR b.approval_status = 'approved'
        ORDER BY b.id DESC";
$result = mysqli_query($conn, $sql);
$pendingSellers = mysqli_query($conn, "SELECT id, full_name, email, created_at FROM users WHERE role = 'seller' AND status = 'pending' ORDER BY created_at ASC");
$pendingBooks = mysqli_query($conn, "SELECT b.id, b.title, b.author, b.price, b.image, b.pending_image, b.description, (SELECT GROUP_CONCAT(c2.name ORDER BY c2.name SEPARATOR ', ') FROM book_categories bc2 JOIN categories c2 ON c2.id = bc2.category_id WHERE bc2.book_id = b.id) AS category_names, (SELECT GROUP_CONCAT(bpc.category_name ORDER BY bpc.category_name SEPARATOR ', ') FROM book_pending_categories bpc WHERE bpc.book_id = b.id) AS new_category_names, u.full_name AS seller_name, u.email AS seller_email FROM books b JOIN users u ON b.seller_id = u.id WHERE b.approval_status = 'pending' ORDER BY b.id ASC");
$accounts = mysqli_query($conn, "SELECT id, full_name, email, role, status, gender, phone, shipping_address, ward, district, province, country, bank_name, bank_account_name, bank_account_number, created_at FROM users ORDER BY created_at DESC");
$orderInput = $_GET["order_code"] ?? "";
$orderCode = is_string($orderInput) ? trim($orderInput) : "";
$orderError = !is_string($orderInput) || ($orderCode !== "" && !preg_match('/\A\d{10}\z/', $orderCode))
    ? "Mã đơn phải gồm đúng 10 chữ số."
    : "";
if ($orderError !== "") {
    $orders = false;
} elseif ($orderCode === "") {
    $orders = mysqli_query($conn, "SELECT o.id, o.order_code, o.status, o.cancellation_reason, o.customer_name, o.recipient_email, o.phone, o.address, o.order_note, o.payment_method, o.total_price, o.created_at, u.full_name AS account_name, u.email, rr.id AS return_request_id, rr.reason AS return_reason, rr.item_condition, rr.status AS return_status, rr.admin_note AS return_admin_note, (SELECT GROUP_CONCAT(CONCAT(ori.quantity, ' x ', b.title) SEPARATOR ', ') FROM order_return_items ori JOIN books b ON b.id = ori.book_id WHERE ori.return_request_id = rr.id) AS return_items, (SELECT GROUP_CONCAT(CONCAT(od.quantity, ' x ', b.title) SEPARATOR ', ') FROM order_details od JOIN books b ON b.id = od.book_id WHERE od.order_id = o.id) AS items FROM orders o LEFT JOIN users u ON u.id = o.customer_id LEFT JOIN order_return_requests rr ON rr.order_id = o.id ORDER BY o.created_at DESC LIMIT 100");
} else {
    $orderStmt = mysqli_prepare($conn, "SELECT o.id, o.order_code, o.status, o.cancellation_reason, o.customer_name, o.recipient_email, o.phone, o.address, o.order_note, o.payment_method, o.total_price, o.created_at, u.full_name AS account_name, u.email, rr.id AS return_request_id, rr.reason AS return_reason, rr.item_condition, rr.status AS return_status, rr.admin_note AS return_admin_note, (SELECT GROUP_CONCAT(CONCAT(ori.quantity, ' x ', b.title) SEPARATOR ', ') FROM order_return_items ori JOIN books b ON b.id = ori.book_id WHERE ori.return_request_id = rr.id) AS return_items, (SELECT GROUP_CONCAT(CONCAT(od.quantity, ' x ', b.title) SEPARATOR ', ') FROM order_details od JOIN books b ON b.id = od.book_id WHERE od.order_id = o.id) AS items FROM orders o LEFT JOIN users u ON u.id = o.customer_id LEFT JOIN order_return_requests rr ON rr.order_id = o.id WHERE o.order_code = ? ORDER BY o.created_at DESC");
    mysqli_stmt_bind_param($orderStmt, "s", $orderCode);
    mysqli_stmt_execute($orderStmt);
    $orders = mysqli_stmt_get_result($orderStmt);
}
?>
<?php include __DIR__ . '/views/index.html';
