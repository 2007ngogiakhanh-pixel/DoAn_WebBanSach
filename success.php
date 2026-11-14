<?php
// success.php - Thông báo mua hàng thành công
session_start();
require "auth.php";
require "db.php";
require_role(["customer", "seller"]);
$id = (int)($_GET["id"] ?? 0);
$orderCode = "";
if (in_array($_SESSION["user_role"] ?? null, ["customer", "seller"], true) && $id > 0) {
    $customerId = (int)$_SESSION["user_id"];
    $stmt = mysqli_prepare($conn, "SELECT order_code FROM orders WHERE id = ? AND customer_id = ?");
    mysqli_stmt_bind_param($stmt, "ii", $id, $customerId);
    mysqli_stmt_execute($stmt);
    $order = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    $orderCode = $order["order_code"] ?? "";
}
?>
<?php include __DIR__ . '/views/success.html';
