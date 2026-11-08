<?php
session_start();
require "../auth.php";
require_role("seller");
require "../db.php";

$sellerId = (int)$_SESSION["user_id"];
$stmt = mysqli_prepare($conn, "SELECT b.*, c.name AS category_name FROM books b LEFT JOIN categories c ON b.category_id = c.id WHERE b.seller_id = ? ORDER BY b.id DESC");
mysqli_stmt_bind_param($stmt, "i", $sellerId);
mysqli_stmt_execute($stmt);
$books = mysqli_stmt_get_result($stmt);
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
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h3>Sách của tôi</h3>
            <a class="btn btn-success" href="book_add.php">+ Thêm sách</a>
        </div>
        <?php if (mysqli_num_rows($books) === 0): ?>
            <div class="alert alert-info">Bạn chưa đăng bán cuốn sách nào.</div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-bordered align-middle bg-white">
                    <thead class="table-secondary">
                        <tr><th>Ảnh</th><th>Tên sách</th><th>Tác giả</th><th>Thể loại</th><th>Giá</th><th>Trạng thái</th><th>Thao tác</th></tr>
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
                            <td><?= htmlspecialchars($book["category_name"] ?? "") ?></td>
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
    </main>
</body>
</html>
