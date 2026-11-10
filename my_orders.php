<?php
session_start();
require "auth.php";
require "db.php";
require_role("customer");

$customerId = (int)$_SESSION["user_id"];
$orderInput = $_GET["order_code"] ?? "";
$orderCode = is_string($orderInput) ? trim($orderInput) : "";
$error = "";
if (!is_string($orderInput) || ($orderCode !== "" && !preg_match('/\A\d{10}\z/', $orderCode))) {
    $error = "Mã đơn phải gồm đúng 10 chữ số.";
}

if ($error !== "") {
    $orders = false;
} elseif ($orderCode === "") {
    $stmt = mysqli_prepare($conn, "SELECT o.id, o.order_code, o.status, o.created_at, o.total_price, o.payment_method, (SELECT GROUP_CONCAT(CONCAT(od.quantity, ' x ', b.title) SEPARATOR ', ') FROM order_details od JOIN books b ON b.id = od.book_id WHERE od.order_id = o.id) AS items FROM orders o WHERE o.customer_id = ? ORDER BY o.created_at DESC LIMIT 100");
    mysqli_stmt_bind_param($stmt, "i", $customerId);
} else {
    $stmt = mysqli_prepare($conn, "SELECT o.id, o.order_code, o.status, o.created_at, o.total_price, o.payment_method, (SELECT GROUP_CONCAT(CONCAT(od.quantity, ' x ', b.title) SEPARATOR ', ') FROM order_details od JOIN books b ON b.id = od.book_id WHERE od.order_id = o.id) AS items FROM orders o WHERE o.customer_id = ? AND o.order_code = ? ORDER BY o.created_at DESC");
    mysqli_stmt_bind_param($stmt, "is", $customerId, $orderCode);
}
if ($error === "") {
    mysqli_stmt_execute($stmt);
    $orders = mysqli_stmt_get_result($stmt);
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đơn hàng đã mua</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <?php include "menu.php"; ?>
    <main class="container my-4">
        <h2>Đơn hàng đã mua</h2>
        <form class="row g-2 my-3" method="get">
            <div class="col-sm-8 col-md-5"><input class="form-control" name="order_code" value="<?= htmlspecialchars($orderCode) ?>" placeholder="Tìm theo mã đơn 10 chữ số"></div>
            <div class="col-auto"><button class="btn btn-primary" type="submit">Tìm đơn</button><a class="btn btn-outline-secondary ms-1" href="my_orders.php">Xóa tìm kiếm</a></div>
        </form>
        <?php if ($error !== ""): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
        <?php if ($orders && mysqli_num_rows($orders) === 0): ?><div class="alert alert-info">Không tìm thấy đơn hàng.</div><?php endif; ?>
        <?php if ($orders && mysqli_num_rows($orders) > 0): ?>
            <div class="table-responsive">
                <table class="table table-bordered align-middle">
                    <thead class="table-secondary"><tr><th>Mã đơn</th><th>Sách</th><th>Thanh toán</th><th>Tổng tiền</th><th>Trạng thái</th><th>Ngày đặt</th></tr></thead>
                    <tbody>
                    <?php while ($order = mysqli_fetch_assoc($orders)): ?>
                        <?php
                        $statusLabels = [
                            "pending_confirmation" => "Chờ xác nhận",
                            "shipping" => "Đang giao",
                            "delivered" => "Đã giao",
                            "cancelled" => "Đã hủy",
                        ];
                        ?>
                        <tr><td><?= htmlspecialchars($order["order_code"]) ?></td><td><?= htmlspecialchars($order["items"] ?? "") ?></td><td><?= $order["payment_method"] === "bank_transfer" ? "Chuyển khoản thủ công" : "Thanh toán khi nhận hàng (COD)" ?></td><td><?= number_format($order["total_price"], 0, ",", ".") ?> đ</td><td><?= htmlspecialchars($statusLabels[$order["status"]] ?? $order["status"]) ?></td><td><?= htmlspecialchars($order["created_at"] ?? "") ?></td></tr>
                    <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </main>
</body>
</html>
