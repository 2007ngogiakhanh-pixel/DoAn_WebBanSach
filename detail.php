<?php
// detail.php - Trang chi tiết sản phẩm
session_start();
require "db.php";

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$result = mysqli_query($conn, "SELECT b.*, (SELECT GROUP_CONCAT(c2.name ORDER BY c2.name SEPARATOR ', ') FROM book_categories bc JOIN categories c2 ON c2.id = bc.category_id WHERE bc.book_id = b.id) AS category_names
                               FROM books b 
                               WHERE b.id = $id
                                 AND (b.seller_id IS NULL OR b.approval_status = 'approved')");

if (mysqli_num_rows($result) == 0) {
    header("Location: index.php");
    exit;
}

$book = mysqli_fetch_assoc($result);
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($book['title']) ?> - Chi tiết sách</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <?php include "menu.php"; ?>

    <div class="container my-5">
        <div class="row">
            <!-- 📌 VỊ TRÍ ẢNH BÌA SÁCH LỚN (TRANG CHI TIẾT) -->
            <div class="col-md-5 mb-4 text-center">
                <!-- Ghi chú: Hiển thị hình bìa sách khổ lớn. 
                     Khuyên dùng: Ảnh dạng dọc, nét rõ (kích thước khoảng 600x800px hoặc tỉ lệ 3:4) nằm trong thư mục uploads/ -->
                <img src="uploads/<?= htmlspecialchars($book['image']) ?>" 
                     class="img-fluid rounded shadow" 
                     alt="<?= htmlspecialchars($book['title']) ?>"
                     style="max-height: 400px;"
                     onerror="this.src=''"> <!-- 👈 Đã để trống: Ảnh dự phòng khi chưa tải được ảnh sách -->
            </div>

            <!-- Thông tin sách -->
            <div class="col-md-7">
                <h2><?= htmlspecialchars($book['title']) ?></h2>
                <p class="text-muted">Tác giả: <strong><?= htmlspecialchars($book['author']) ?></strong></p>
                <p class="text-muted">Thể loại: <span class="badge bg-secondary"><?= htmlspecialchars($book['category_names'] ?? "") ?></span></p>
                <h3 class="text-danger my-3"><?= number_format($book['price'], 0, ',', '.') ?> đ</h3>
                <?php if (empty($book["is_available"])): ?><p><span class="badge bg-secondary">Hết hàng</span></p><?php endif; ?>

                <p class="mt-4"><strong>Mô tả nội dung:</strong></p>
                <p class="text-justify"><?= nl2br(htmlspecialchars($book['description'])) ?></p>

                <hr class="my-4">

                <!-- Form chọn số lượng và mua -->
                <?php if (!empty($book["is_available"])): ?><form action="cart_add.php" method="post" class="row g-3 align-items-center">
                    <input type="hidden" name="id" value="<?= $book['id'] ?>">
                    <div class="col-auto">
                        <label for="qty" class="col-form-label fw-bold">Số lượng mua:</label>
                    </div>
                    <div class="col-auto">
                        <input type="number" id="qty" name="qty" class="form-control" value="1" min="1" style="width: 80px;">
                    </div>
                    <div class="col-auto">
                        <button type="submit" class="btn btn-primary px-4">🛒 Thêm vào giỏ hàng</button>
                    </div>
                </form>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>