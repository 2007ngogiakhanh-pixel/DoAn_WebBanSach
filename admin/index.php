<?php
// admin/index.php - Quản lý sách
session_start();
require "../auth.php";
if (!isset($_SESSION["admin"])) {
    header("Location: login.php");
    exit;
}
require "../db.php";

$sql = "SELECT b.*, c.name AS category_name 
        FROM books b 
        JOIN categories c ON b.category_id = c.id 
        ORDER BY b.id DESC";
$result = mysqli_query($conn, $sql);
$pendingSellers = mysqli_query($conn, "SELECT id, full_name, email, created_at FROM users WHERE role = 'seller' AND status = 'pending' ORDER BY created_at ASC");
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
                <a href="../index.php" class="btn btn-outline-light btn-sm me-2" target="_blank">Xem Website</a>
                <a href="logout.php" class="btn btn-danger btn-sm">Đăng xuất</a>
            </div>
        </div>
    </nav>

    <div class="container my-4">
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

        <div class="d-flex justify-content-between align-items-center mb-3">
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
                        <th>Tác giả</th>
                        <th>Thể loại</th>
                        <th>Giá bán</th>
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
                        <td><?= htmlspecialchars($row['author']) ?></td>
                        <td><span class="badge bg-info text-dark"><?= htmlspecialchars($row['category_name']) ?></span></td>
                        <td class="text-danger fw-bold"><?= number_format($row['price'], 0, ',', '.') ?> đ</td>
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
    </div>
</body>
</html>