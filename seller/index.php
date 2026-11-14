<?php
session_start();
require "../auth.php";
require_once "../db.php";
require_role("seller");

$sellerId = (int)$_SESSION["user_id"];
$stmt = mysqli_prepare($conn, "SELECT b.*, (SELECT GROUP_CONCAT(c2.name ORDER BY c2.name SEPARATOR ', ') FROM book_categories bc JOIN categories c2 ON c2.id = bc.category_id WHERE bc.book_id = b.id) AS category_names FROM books b WHERE b.seller_id = ? ORDER BY b.id DESC");
mysqli_stmt_bind_param($stmt, "i", $sellerId);
mysqli_stmt_execute($stmt);
$books = mysqli_stmt_get_result($stmt);
$orderInput = $_GET["order_code"] ?? "";
$orderCode = is_string($orderInput) ? trim($orderInput) : "";
$orderError = !is_string($orderInput) || ($orderCode !== "" && !preg_match('/\A\d{10}\z/', $orderCode))
    ? "Mã đơn phải gồm đúng 10 chữ số."
    : "";
if ($orderError !== "") {
    $sellerOrders = false;
} elseif ($orderCode === "") {
    $orderStmt = mysqli_prepare($conn, "SELECT o.id, o.order_code, o.status, o.customer_name, o.recipient_email, o.phone, o.address, o.order_note, o.payment_method, o.created_at, SUM(od.quantity * od.price) AS seller_total, GROUP_CONCAT(CONCAT(od.quantity, ' x ', b.title) SEPARATOR ', ') AS items FROM orders o JOIN order_details od ON od.order_id = o.id JOIN books b ON b.id = od.book_id WHERE b.seller_id = ? GROUP BY o.id ORDER BY o.created_at DESC LIMIT 100");
    mysqli_stmt_bind_param($orderStmt, "i", $sellerId);
} else {
    $orderStmt = mysqli_prepare($conn, "SELECT o.id, o.order_code, o.status, o.customer_name, o.recipient_email, o.phone, o.address, o.order_note, o.payment_method, o.created_at, SUM(od.quantity * od.price) AS seller_total, GROUP_CONCAT(CONCAT(od.quantity, ' x ', b.title) SEPARATOR ', ') AS items FROM orders o JOIN order_details od ON od.order_id = o.id JOIN books b ON b.id = od.book_id WHERE b.seller_id = ? AND o.order_code = ? GROUP BY o.id ORDER BY o.created_at DESC");
    mysqli_stmt_bind_param($orderStmt, "is", $sellerId, $orderCode);
}
if ($orderError === "") {
    mysqli_stmt_execute($orderStmt);
    $sellerOrders = mysqli_stmt_get_result($orderStmt);
}
$notice = $_SESSION["seller_notice"] ?? "";
unset($_SESSION["seller_notice"]);
?>
<?php include __DIR__ . '/views/index.html';
