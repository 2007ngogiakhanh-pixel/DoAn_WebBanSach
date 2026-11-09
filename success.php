<?php
// success.php - Thông báo mua hàng thành công
session_start();
require "auth.php";
require "db.php";
require_role("customer");
$id = (int)($_GET["id"] ?? 0);
$orderCode = "";
if (($_SESSION["user_role"] ?? null) === "customer" && $id > 0) {
    $customerId = (int)$_SESSION["user_id"];
    $stmt = mysqli_prepare($conn, "SELECT order_code FROM orders WHERE id = ? AND customer_id = ?");
    mysqli_stmt_bind_param($stmt, "ii", $id, $customerId);
    mysqli_stmt_execute($stmt);
    $order = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    $orderCode = $order["order_code"] ?? "";
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đặt hàng thành công</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <?php include "menu.php"; ?>

    <div class="container my-5 text-center" style="max-width: 500px;">
        <div class="card shadow-sm p-4">
            <h1 class="display-3 text-success">🎉</h1>
            <h3 class="text-success">Đặt Hàng Thành Công!</h3>
            <p class="mt-3">Mã đơn hàng của bạn là: <strong class="fs-4 text-primary"><?= htmlspecialchars($orderCode !== "" ? $orderCode : "Không tìm thấy đơn hàng") ?></strong></p>
            <p class="text-muted">Bộ phận nhà sách sẽ sớm liên hệ xác nhận và giao hàng cho bạn.</p>
            <div class="mt-4">
                <a href="index.php" class="btn btn-primary">Tiếp tục mua sách</a>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>