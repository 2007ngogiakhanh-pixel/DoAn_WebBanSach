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
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Khu vực người bán</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <nav class="navbar navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="../index.php">Khu vực người bán</a>
            <div class="text-white">
                <?= htmlspecialchars($_SESSION["user_name"]) ?>
                <a class="btn btn-outline-light btn-sm ms-3" href="../index.php">Xem cửa hàng</a>
                <a class="btn btn-danger btn-sm ms-2" href="../logout.php">Đăng xuất</a>
            </div>
        </div>
    </nav>
    <main class="container my-4">
        <?php if ($notice !== ""): ?><div class="alert alert-success"><?= htmlspecialchars($notice) ?></div><?php endif; ?>
        <div class="d-flex justify-content-between align-items-center mb-3" id="books">
            <h3>Sách của tôi</h3>
            <a class="btn btn-success" href="book_add.php">+ Thêm sách</a>
        </div>
        <?php if (mysqli_num_rows($books) === 0): ?>
            <div class="alert alert-info">Bạn chưa đăng bán cuốn sách nào.</div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-bordered align-middle bg-white">
                    <thead class="table-secondary">
                        <tr><th>Ảnh</th><th>Tên sách</th><th>Tác giả</th><th>Thể loại</th><th>Giá</th><th>Trạng thái</th><th>Tồn kho</th><th>Thao tác</th></tr>
                    </thead>
                    <tbody>
                    <?php while ($book = mysqli_fetch_assoc($books)): ?>
                        <tr>
                            <td>
                                <?php if ($book["seller_id"] !== null && $book["approval_status"] !== "approved"): ?>
                                    <img src="../book_image.php?id=<?= (int)$book["id"] ?>" alt="" style="height:55px;max-width:60px;object-fit:cover">
                                <?php else: ?>
                                    <img src="../uploads/<?= htmlspecialchars($book["image"] ?? "default.jpg") ?>" alt="" style="height:55px;max-width:60px;object-fit:cover">
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($book["title"]) ?></td>
                            <td><?= htmlspecialchars($book["author"]) ?></td>
                            <td><?= htmlspecialchars($book["category_names"] ?? "") ?></td>
                            <td><?= number_format($book["price"], 0, ",", ".") ?> đ</td>
                            <td>
                                <?php if ($book["seller_id"] === null || $book["approval_status"] === "approved"): ?>
                                    <span class="badge bg-success">Đã duyệt</span>
                                <?php elseif ($book["approval_status"] === "pending"): ?>
                                    <span class="badge bg-warning text-dark">Chờ admin duyệt</span>
                                <?php else: ?>
                                    <span class="badge bg-danger">Bị từ chối — chỉnh sửa để gửi lại</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge <?= $book["is_available"] && (int)$book["stock_quantity"] > 0 ? "bg-success" : "bg-secondary" ?>"><?= $book["is_available"] && (int)$book["stock_quantity"] > 0 ? "Còn hàng" : "Hết hàng" ?></span>
                                <form method="post" action="../book_stock_update.php" class="mt-1 d-flex gap-1">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                                    <input type="hidden" name="book_id" value="<?= (int)$book["id"] ?>">
                                    <input class="form-control form-control-sm" type="number" min="0" max="4294967295" name="stock_quantity" value="<?= (int)$book["stock_quantity"] ?>" aria-label="Số lượng tồn kho">
                                    <button class="btn btn-primary btn-sm" type="submit">Lưu</button>
                                </form>
                                <?php if ($book["is_available"] || (int)$book["stock_quantity"] > 0): ?><form method="post" action="../book_stock_update.php" class="mt-1">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                                    <input type="hidden" name="book_id" value="<?= (int)$book["id"] ?>">
                                    <input type="hidden" name="is_available" value="<?= $book["is_available"] ? 0 : 1 ?>">
                                    <button class="btn btn-outline-secondary btn-sm" type="submit"><?= $book["is_available"] ? "Tạm ngừng bán" : "Bật bán lại" ?></button>
                                </form><?php else: ?><small class="text-muted">Nhập tồn kho để bật bán</small><?php endif; ?>
                            </td>
                            <td class="text-nowrap">
                                <a class="btn btn-warning btn-sm" href="book_edit.php?id=<?= (int)$book["id"] ?>">Sửa</a>
                                <form class="d-inline" method="post" action="book_delete.php" onsubmit="return confirm('Xóa sách này?')">
                                    <input type="hidden" name="id" value="<?= (int)$book["id"] ?>">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                                    <button class="btn btn-danger btn-sm" type="submit">Xóa</button>
                                </form>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <section id="orders" class="mt-5">
            <h3>Đơn hàng có sách đã bán</h3>
            <form class="row g-2 my-3" method="get" action="index.php#orders">
                <div class="col-sm-8 col-md-5"><input class="form-control" name="order_code" value="<?= htmlspecialchars($orderCode) ?>" placeholder="Tìm mã đơn 10 chữ số"></div>
                <div class="col-auto"><button class="btn btn-primary" type="submit">Tìm đơn</button><a class="btn btn-outline-secondary ms-1" href="index.php#orders">Xóa tìm kiếm</a></div>
            </form>
            <?php if ($orderError !== ""): ?><div class="alert alert-danger"><?= htmlspecialchars($orderError) ?></div><?php endif; ?>
            <?php if ($sellerOrders && mysqli_num_rows($sellerOrders) === 0): ?><div class="alert alert-info">Không tìm thấy đơn hàng có sách của bạn.</div><?php endif; ?>
            <?php if ($sellerOrders && mysqli_num_rows($sellerOrders) > 0): ?>
                <div class="table-responsive">
                    <table class="table table-bordered align-middle bg-white">
                        <thead class="table-secondary"><tr><th>Mã đơn</th><th>Người nhận</th><th>Liên hệ</th><th>Địa chỉ</th><th>Sách của bạn</th><th>Ghi chú</th><th>Thanh toán</th><th>Tiền sách của bạn</th><th>Trạng thái</th><th>Ngày đặt</th></tr></thead>
                        <tbody>
                        <?php
                        $sellerOrderStatusLabels = [
                            "pending_confirmation" => "Chờ xác nhận",
                            "confirmed" => "Đã xác nhận",
                            "preparing" => "Người bán đang chuẩn bị hàng",
                            "shipping" => "Đang vận chuyển đơn hàng",
                            "delivered" => "Đã nhận được hàng",
                            "cancelled" => "Đã hủy",
                            "return_requested" => "Chờ duyệt trả hàng",
                            "returned" => "Đã trả hàng",
                        ];
                        ?>
                        <?php while ($order = mysqli_fetch_assoc($sellerOrders)): ?>
                            <?php
                            $nextSellerStatus = [
                                "pending_confirmation" => ["confirmed", "Xác nhận đơn hàng"],
                                "confirmed" => ["preparing", "Bắt đầu chuẩn bị hàng"],
                                "preparing" => ["shipping", "Bàn giao vận chuyển"],
                                "shipping" => ["delivered", "Xác nhận đã giao"],
                            ][$order["status"]] ?? null;
                            ?>
                            <tr>
                                <td><?= htmlspecialchars($order["order_code"]) ?></td>
                                <td><?= htmlspecialchars($order["customer_name"]) ?></td>
                                <td><?= htmlspecialchars($order["phone"]) ?><br><small><?= htmlspecialchars($order["recipient_email"] ?? "") ?></small></td>
                                <td><?= htmlspecialchars($order["address"]) ?></td>
                                <td><?= htmlspecialchars($order["items"] ?? "") ?></td>
                                <td><?= htmlspecialchars($order["order_note"] ?? "") ?></td>
                                <td><?= $order["payment_method"] === "bank_transfer" ? "Chuyển khoản thủ công" : "Thanh toán khi nhận hàng (COD)" ?></td>
                                <td><?= number_format($order["seller_total"], 0, ",", ".") ?> đ</td>
                                <td>
                                    <?= htmlspecialchars($sellerOrderStatusLabels[$order["status"]] ?? $order["status"]) ?>
                                    <?php if ($nextSellerStatus !== null): ?>
                                        <form method="post" action="../admin/order_status_update.php" class="mt-1">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                                            <input type="hidden" name="order_id" value="<?= (int)$order["id"] ?>">
                                            <input type="hidden" name="order_code" value="<?= htmlspecialchars($orderCode) ?>">
                                            <input type="hidden" name="status" value="<?= htmlspecialchars($nextSellerStatus[0]) ?>">
                                            <button class="btn btn-primary btn-sm" type="submit"><?= htmlspecialchars($nextSellerStatus[1]) ?></button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($order["created_at"] ?? "") ?></td>
                            </tr>
                        <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>
    </main>
</body>
</html>
