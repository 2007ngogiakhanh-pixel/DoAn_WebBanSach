<?php
// index.php - Trang chủ hiển thị danh sách sách
session_start();
require "db.php";

// Lấy danh sách thể loại để làm menu lọc
$cat_result = mysqli_query($conn, "SELECT * FROM categories");

// Lọc theo thể loại nếu người dùng nhấp chọn
$cat_id = isset($_GET['cat']) ? (int)$_GET['cat'] : 0;
if ($cat_id > 0) {
    $sql = "SELECT * FROM books WHERE EXISTS (SELECT 1 FROM book_categories bc WHERE bc.book_id = books.id AND bc.category_id = $cat_id) AND (seller_id IS NULL OR approval_status = 'approved') ORDER BY id DESC";
} else {
    $sql = "SELECT * FROM books WHERE seller_id IS NULL OR approval_status = 'approved' ORDER BY id DESC";
}
$books_result = mysqli_query($conn, $sql);
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Trang chủ - Nhà sách Trực tuyến</title>
    <!-- Nhúng Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <?php include "menu.php"; ?>

    <div class="container my-4">
        <div class="row">
            <!-- Cột trái: Danh mục thể loại -->
            <div class="col-md-3 mb-4">
                <div class="list-group">
                    <a href="index.php" class="list-group-item list-group-item-action <?= $cat_id == 0 ? 'active' : '' ?>">
                        Tất cả thể loại
                    </a>
                    <?php while ($cat = mysqli_fetch_assoc($cat_result)): ?>
                        <a href="index.php?cat=<?= $cat['id'] ?>" 
                           class="list-group-item list-group-item-action <?= $cat_id == $cat['id'] ? 'active' : '' ?>">
                            <?= htmlspecialchars($cat['name']) ?>
                        </a>
                    <?php endwhile; ?>
                </div>
            </div>

            <!-- Cột phải: Danh sách sách -->
            <div class="col-md-9">
                <h3 class="mb-3">Danh Sách Sách</h3>
                <div class="row row-cols-1 row-cols-md-3 g-4">
                    <?php if (mysqli_num_rows($books_result) > 0): ?>
                        <?php while ($book = mysqli_fetch_assoc($books_result)): ?>
                            <div class="col">
                                <div class="card h-100 shadow-sm">
                                    <!-- 📌 VỊ TRÍ ẢNH BÌA SÁCH TRÊN CARD -->
                                    <!-- Ghi chú: Nối tên file ảnh từ CSDL ($book['image']) vào thư mục uploads/ 
                                         Ví dụ thực tế: 'uploads/demen.jpg' hoặc 'uploads/dacnhantam.jpg' (hình bìa đứng, tỉ lệ 3:4) -->
                                    <img src="uploads/<?= htmlspecialchars($book['image']) ?>" 
                                         class="card-img-top" 
                                         alt="<?= htmlspecialchars($book['title']) ?>"
                                         style="height: 220px; object-fit: cover;"
                                         onerror="this.src=''"> <!-- 👈 Đã để trống: Ảnh mặc định hiển thị khi chưa có ảnh bìa hoặc đường dẫn ảnh bị lỗi -->
                                    
                                    <div class="card-body d-flex flex-column">
                                        <h5 class="card-title text-truncate"><?= htmlspecialchars($book['title']) ?></h5>
                                        <p class="card-text text-muted small">Tác giả: <?= htmlspecialchars($book['author']) ?></p>
                                        <p class="card-text text-danger fw-bold fs-5 mt-auto">
                                            <?= number_format($book['price'], 0, ',', '.') ?> đ
                                        </p>
                                        <?php if (empty($book["is_available"])): ?><span class="badge bg-secondary mb-2">Hết hàng</span><?php endif; ?>
                                        <div class="d-grid gap-2">
                                            <a href="detail.php?id=<?= $book['id'] ?>" class="btn btn-outline-primary btn-sm">Xem chi tiết</a>
                                            <form action="cart_add.php" method="post">
                                                <input type="hidden" name="id" value="<?= $book['id'] ?>">
                                                <input type="hidden" name="qty" value="1">
                                                <button type="submit" class="btn btn-success btn-sm w-100 mt-1" <?= empty($book["is_available"]) ? "disabled" : "" ?>><?= empty($book["is_available"]) ? "Hết hàng" : "Thêm vào giỏ" ?></button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="col-12"><p class="alert alert-info">Chưa có sách nào thuộc thể loại này.</p></div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Nhúng Bootstrap 5 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>