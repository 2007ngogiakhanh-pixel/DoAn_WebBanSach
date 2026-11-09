<?php
// admin/index.php - Quản lý sách
session_start();
require "../auth.php";
if (!isset($_SESSION["admin"])) {
    header("Location: login.php");
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
$accounts = mysqli_query($conn, "SELECT id, full_name, email, role, status, created_at FROM users ORDER BY created_at DESC");
$orderInput = $_GET["order_code"] ?? "";
$orderCode = is_string($orderInput) ? trim($orderInput) : "";
$orderError = !is_string($orderInput) || ($orderCode !== "" && !preg_match('/\A\d{10}\z/', $orderCode))
    ? "Mã đơn phải gồm đúng 10 chữ số."
    : "";
if ($orderError !== "") {
    $orders = false;
} elseif ($orderCode === "") {
    $orders = mysqli_query($conn, "SELECT o.order_code, o.customer_name, o.phone, o.address, o.total_price, o.created_at, u.full_name AS account_name, u.email, (SELECT GROUP_CONCAT(CONCAT(od.quantity, ' x ', b.title) SEPARATOR ', ') FROM order_details od JOIN books b ON b.id = od.book_id WHERE od.order_id = o.id) AS items FROM orders o LEFT JOIN users u ON u.id = o.customer_id ORDER BY o.created_at DESC LIMIT 100");
} else {
    $orderStmt = mysqli_prepare($conn, "SELECT o.order_code, o.customer_name, o.phone, o.address, o.total_price, o.created_at, u.full_name AS account_name, u.email, (SELECT GROUP_CONCAT(CONCAT(od.quantity, ' x ', b.title) SEPARATOR ', ') FROM order_details od JOIN books b ON b.id = od.book_id WHERE od.order_id = o.id) AS items FROM orders o LEFT JOIN users u ON u.id = o.customer_id WHERE o.order_code = ? ORDER BY o.created_at DESC");
    mysqli_stmt_bind_param($orderStmt, "s", $orderCode);
    mysqli_stmt_execute($orderStmt);
    $orders = mysqli_stmt_get_result($orderStmt);
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản lý Sách - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand fw-bold" href="index.php">⚙️ ADMIN BOOKSTORE</a>
            <div class="d-flex align-items-center text-white">
                <span class="me-3">Xin chào, <strong><?= $_SESSION["admin"] ?></strong></span>
                <a href="#accounts" class="btn btn-outline-light btn-sm me-2">Tài khoản</a>
                <a href="#orders" class="btn btn-outline-light btn-sm me-2">Đơn hàng</a>
                <a href="../index.php" class="btn btn-outline-light btn-sm me-2" target="_blank">Xem Website</a>
                <a href="logout.php" class="btn btn-danger btn-sm">Đăng xuất</a>
            </div>
        </div>
    </nav>

    <div class="container my-4">
        <section class="mb-5">
            <h3>Yêu cầu đăng sách chờ duyệt</h3>
            <?php if (mysqli_num_rows($pendingBooks) === 0): ?>
                <p class="text-muted">Không có sách nào đang chờ duyệt.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-bordered align-middle bg-white">
                        <thead class="table-secondary">
                            <tr><th>Ảnh</th><th>Sách</th><th>Người bán</th><th>Thể loại</th><th>Giá</th><th>Mô tả</th><th>Thao tác</th></tr>
                        </thead>
                        <tbody>
                        <?php while ($book = mysqli_fetch_assoc($pendingBooks)): ?>
                            <tr>
                                <td><img src="../book_image.php?id=<?= (int)$book["id"] ?>" alt="" style="height:90px;max-width:70px;object-fit:cover"></td>
                                <td><strong><?= htmlspecialchars($book["title"]) ?></strong><br><span class="text-muted"><?= htmlspecialchars($book["author"]) ?></span></td>
                                <td><?= htmlspecialchars($book["seller_name"]) ?><br><a href="mailto:<?= htmlspecialchars($book["seller_email"]) ?>"><?= htmlspecialchars($book["seller_email"]) ?></a></td>
                                <td>
                                    <?= htmlspecialchars($book["category_names"] ?? "") ?>
                                    <?php if (!empty($book["new_category_names"])): ?><br><small class="text-primary">Thể loại đề xuất: <?= htmlspecialchars($book["new_category_names"]) ?></small><?php endif; ?>
                                </td>
                                <td><?= number_format($book["price"], 0, ",", ".") ?> đ</td>
                                <td><?= nl2br(htmlspecialchars($book["description"] ?? "")) ?></td>
                                <td class="text-nowrap">
                                    <form method="post" action="book_review.php" class="mb-2">
                                        <input type="hidden" name="id" value="<?= (int)$book["id"] ?>">
                                        <input type="hidden" name="action" value="approve">
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                                        <button class="btn btn-success btn-sm" type="submit">Duyệt và đăng</button>
                                    </form>
                                    <form method="post" action="book_review.php" onsubmit="return confirm('Từ chối sách này?')">
                                        <input type="hidden" name="id" value="<?= (int)$book["id"] ?>">
                                        <input type="hidden" name="action" value="reject">
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                                        <button class="btn btn-outline-danger btn-sm" type="submit">Từ chối</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>

        <section class="mb-4">
            <h3>Yêu cầu tài khoản người bán</h3>
            <?php if (mysqli_num_rows($pendingSellers) === 0): ?>
                <p class="text-muted">Không có yêu cầu đang chờ duyệt.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-bordered align-middle bg-white">
                        <thead class="table-secondary"><tr><th>Họ tên</th><th>Email</th><th>Ngày đăng ký</th><th>Thao tác</th></tr></thead>
                        <tbody>
                        <?php while ($seller = mysqli_fetch_assoc($pendingSellers)): ?>
                            <tr>
                                <td><?= htmlspecialchars($seller["full_name"]) ?></td>
                                <td><?= htmlspecialchars($seller["email"]) ?></td>
                                <td><?= htmlspecialchars($seller["created_at"]) ?></td>
                                <td class="text-nowrap">
                                    <form class="d-inline" method="post" action="seller_status.php">
                                        <input type="hidden" name="id" value="<?= (int)$seller["id"] ?>">
                                        <input type="hidden" name="action" value="approve">
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                                        <button class="btn btn-success btn-sm" type="submit">Duyệt</button>
                                    </form>
                                    <form class="d-inline" method="post" action="seller_status.php" onsubmit="return confirm('Từ chối yêu cầu này?')">
                                        <input type="hidden" name="id" value="<?= (int)$seller["id"] ?>">
                                        <input type="hidden" name="action" value="reject">
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                                        <button class="btn btn-outline-danger btn-sm" type="submit">Từ chối</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>

        <div class="d-flex justify-content-between align-items-center mb-3" id="books">
            <h3>Danh Sách Sách</h3>
            <a href="book_add.php" class="btn btn-success">+ Thêm Sách Mới</a>
        </div>

        <div class="table-responsive">
            <table class="table table-bordered align-middle">
                <thead class="table-secondary">
                    <tr>
                        <th style="width: 60px;">ID</th>
                        <th style="width: 80px;">Ảnh</th>
                        <th>Tên sách</th>
                        <th>Người bán</th>
                        <th>Tác giả</th>
                        <th>Thể loại</th>
                        <th>Giá bán</th>
                        <th>Tình trạng</th>
                        <th style="width: 150px;">Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = mysqli_fetch_assoc($result)): ?>
                    <tr>
                        <td><?= $row['id'] ?></td>
                        <td class="text-center">
                            <!-- 📌 VỊ TRÍ ẢNH BÌA SÁCH TRONG TRANG QUẢN TRỊ -->
                            <img src="../uploads/<?= htmlspecialchars($row['image']) ?>" 
                                 style="height: 50px; object-fit: cover;" class="rounded"
                                 onerror="this.src=''"> <!-- 👈 Đã để trống: Ảnh dự phòng cho Admin -->
                        </td>
                        <td class="fw-bold"><?= htmlspecialchars($row['title']) ?></td>
                        <td><?= htmlspecialchars($row['seller_name'] ?? "Admin") ?><?php if (!empty($row["seller_email"])): ?><br><small><?= htmlspecialchars($row["seller_email"]) ?></small><?php endif; ?></td>
                        <td><?= htmlspecialchars($row['author']) ?></td>
                        <td><span class="badge bg-info text-dark"><?= htmlspecialchars($row['category_names'] ?? "") ?></span></td>
                        <td class="text-danger fw-bold"><?= number_format($row['price'], 0, ',', '.') ?> đ</td>
                        <td>
                            <span class="badge <?= $row["is_available"] ? "bg-success" : "bg-secondary" ?>"><?= $row["is_available"] ? "Còn hàng" : "Hết hàng" ?></span>
                            <form method="post" action="../book_stock_update.php" class="mt-1">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                                <input type="hidden" name="book_id" value="<?= (int)$row["id"] ?>">
                                <input type="hidden" name="is_available" value="<?= $row["is_available"] ? 0 : 1 ?>">
                                <button class="btn btn-outline-secondary btn-sm" type="submit"><?= $row["is_available"] ? "Đánh dấu hết hàng" : "Đánh dấu còn hàng" ?></button>
                            </form>
                        </td>
                        <td>
                            <a href="book_edit.php?id=<?= $row['id'] ?>" class="btn btn-warning btn-sm me-1">Sửa</a>
                            <a href="book_delete.php?id=<?= $row['id'] ?>" 
                               onclick="return confirm('Bạn có chắc muốn xóa cuốn sách này?')" 
                               class="btn btn-danger btn-sm">Xóa</a>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>

        <section id="accounts" class="mb-5">
            <h3>Tài khoản người bán và khách hàng</h3>
            <p class="text-muted">Thông tin mật khẩu không được hiển thị.</p>
            <div class="table-responsive">
                <table class="table table-bordered align-middle bg-white">
                    <thead class="table-secondary"><tr><th>Họ tên</th><th>Email</th><th>Loại tài khoản</th><th>Trạng thái</th><th>Ngày đăng ký</th><th>Thao tác</th></tr></thead>
                    <tbody>
                    <?php while ($account = mysqli_fetch_assoc($accounts)): ?>
                        <tr>
                            <td><?= htmlspecialchars($account["full_name"]) ?></td>
                            <td><a href="mailto:<?= htmlspecialchars($account["email"]) ?>"><?= htmlspecialchars($account["email"]) ?></a></td>
                            <td><?= $account["role"] === "seller" ? "Người bán" : "Khách hàng" ?></td>
                            <td><?= htmlspecialchars($account["status"]) ?></td>
                            <td><?= htmlspecialchars($account["created_at"]) ?></td>
                            <td>
                                <form method="post" action="account_delete.php" onsubmit="return confirm('Xóa vĩnh viễn tài khoản này? Lịch sử đơn hàng sẽ được giữ lại. Với người bán, sách đã duyệt vẫn được giữ trên cửa hàng; sách đang chờ duyệt sẽ bị từ chối.')">
                                    <input type="hidden" name="id" value="<?= (int)$account["id"] ?>">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                                    <button class="btn btn-outline-danger btn-sm" type="submit">Xóa tài khoản</button>
                                </form>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <section id="orders" class="mb-5">
            <h3>Đơn hàng<?= $orderCode === "" ? " gần đây" : " theo mã" ?></h3>
            <form class="row g-2 my-3" method="get" action="index.php#orders">
                <div class="col-sm-8 col-md-5"><input class="form-control" name="order_code" value="<?= htmlspecialchars($orderCode) ?>" placeholder="Tìm mã đơn 10 chữ số"></div>
                <div class="col-auto"><button class="btn btn-primary" type="submit">Tìm đơn</button><a class="btn btn-outline-secondary ms-1" href="index.php#orders">Xóa tìm kiếm</a></div>
            </form>
            <?php if ($orderError !== ""): ?><div class="alert alert-danger"><?= htmlspecialchars($orderError) ?></div><?php endif; ?>
            <div class="table-responsive">
                <table class="table table-bordered align-middle bg-white">
                    <thead class="table-secondary"><tr><th>Mã đơn</th><th>Người mua</th><th>Liên hệ</th><th>Địa chỉ giao hàng</th><th>Sách</th><th>Tổng tiền</th><th>Ngày đặt</th></tr></thead>
                    <tbody>
                    <?php if ($orders && mysqli_num_rows($orders) === 0): ?>
                        <tr><td colspan="7" class="text-center text-muted">Chưa có đơn hàng.</td></tr>
                    <?php elseif ($orders): ?>
                        <?php while ($order = mysqli_fetch_assoc($orders)): ?>
                            <tr>
                                <td><?= htmlspecialchars($order["order_code"]) ?></td>
                                <td><?= htmlspecialchars($order["account_name"] ?? $order["customer_name"]) ?><?php if (!empty($order["email"])): ?><br><small><?= htmlspecialchars($order["email"]) ?></small><?php endif; ?></td>
                                <td><?= htmlspecialchars($order["phone"]) ?></td>
                                <td><?= htmlspecialchars($order["address"]) ?></td>
                                <td><?= htmlspecialchars($order["items"] ?? "") ?></td>
                                <td><?= number_format($order["total_price"], 0, ",", ".") ?> đ</td>
                                <td><?= htmlspecialchars($order["created_at"] ?? "") ?></td>
                            </tr>
                        <?php endwhile; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</body>
</html>