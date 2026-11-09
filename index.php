<?php
// index.php - Trang chủ hiển thị danh sách sách
session_start();
require "db.php";

$cat_result = mysqli_query($conn, "SELECT id, name FROM categories ORDER BY name");
$queryInput = $_GET["q"] ?? "";
$search = is_string($queryInput) ? trim(mb_substr($queryInput, 0, 100, "UTF-8")) : "";
$categoryInput = $_GET["cat"] ?? "";
$cat_id = filter_var($categoryInput, FILTER_VALIDATE_INT);
$cat_id = $cat_id !== false && $cat_id > 0 ? $cat_id : 0;
$sortOptions = [
    "newest" => "b.id DESC",
    "price_asc" => "b.price ASC, b.id DESC",
    "price_desc" => "b.price DESC, b.id DESC",
    "title_asc" => "b.title ASC, b.id DESC",
];
$sort = $_GET["sort"] ?? "newest";
if (!is_string($sort) || !isset($sortOptions[$sort])) {
    $sort = "newest";
}
$pageInput = $_GET["page"] ?? 1;
$page = filter_var($pageInput, FILTER_VALIDATE_INT);
$page = $page !== false && $page > 0 ? $page : 1;
$perPage = 20;
$offset = ($page - 1) * $perPage;
$searchPattern = "%" . $search . "%";
$filterSql = "(? = '' OR b.title LIKE ? OR b.author LIKE ?) AND (? = 0 OR EXISTS (SELECT 1 FROM book_categories bc WHERE bc.book_id = b.id AND bc.category_id = ?)) AND (b.seller_id IS NULL OR b.approval_status = 'approved')";

$countStmt = mysqli_prepare($conn, "SELECT COUNT(*) AS total FROM books b WHERE $filterSql");
mysqli_stmt_bind_param($countStmt, "sssii", $search, $searchPattern, $searchPattern, $cat_id, $cat_id);
mysqli_stmt_execute($countStmt);
$totalBooks = (int)mysqli_fetch_assoc(mysqli_stmt_get_result($countStmt))["total"];
$totalPages = max(1, (int)ceil($totalBooks / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;

$booksStmt = mysqli_prepare($conn, "SELECT b.* FROM books b WHERE $filterSql ORDER BY " . $sortOptions[$sort] . " LIMIT ? OFFSET ?");
mysqli_stmt_bind_param($booksStmt, "sssiiii", $search, $searchPattern, $searchPattern, $cat_id, $cat_id, $perPage, $offset);
mysqli_stmt_execute($booksStmt);
$books_result = mysqli_stmt_get_result($booksStmt);

$paginationParams = ["q" => $search, "sort" => $sort];
if ($cat_id > 0) {
    $paginationParams["cat"] = $cat_id;
}
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
                        <?php $categoryUrl = http_build_query(array_merge($paginationParams, ["cat" => (int)$cat["id"]])); ?>
                        <a href="index.php?<?= htmlspecialchars($categoryUrl) ?>"
                           class="list-group-item list-group-item-action <?= $cat_id == $cat['id'] ? 'active' : '' ?>">
                            <?= htmlspecialchars($cat['name']) ?>
                        </a>
                    <?php endwhile; ?>
                </div>
            </div>

            <!-- Cột phải: Danh sách sách -->
            <div class="col-md-9">
                <h3 class="mb-3">Danh Sách Sách</h3>
                <form class="row g-2 mb-4" method="get" action="index.php">
                    <?php if ($cat_id > 0): ?><input type="hidden" name="cat" value="<?= $cat_id ?>"><?php endif; ?>
                    <div class="col-md-6">
                        <input class="form-control" type="search" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Tìm theo tên sách hoặc tác giả">
                    </div>
                    <div class="col-md-4">
                        <select class="form-select" name="sort">
                            <option value="newest" <?= $sort === "newest" ? "selected" : "" ?>>Mới nhất</option>
                            <option value="price_asc" <?= $sort === "price_asc" ? "selected" : "" ?>>Giá thấp đến cao</option>
                            <option value="price_desc" <?= $sort === "price_desc" ? "selected" : "" ?>>Giá cao đến thấp</option>
                            <option value="title_asc" <?= $sort === "title_asc" ? "selected" : "" ?>>Tên sách A–Z</option>
                        </select>
                    </div>
                    <div class="col-md-2 d-grid"><button class="btn btn-primary" type="submit">Tìm kiếm</button></div>
                    <?php if ($search !== "" || $cat_id > 0 || $sort !== "newest"): ?><div class="col-12"><a href="index.php" class="small">Xóa bộ lọc</a></div><?php endif; ?>
                </form>
                <p class="text-muted">Tìm thấy <?= number_format($totalBooks) ?> sách · Trang <?= $page ?> / <?= $totalPages ?></p>
                <div class="row row-cols-1 row-cols-md-3 g-4">
                    <?php if ($totalBooks > 0): ?>
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
                        <div class="col-12"><p class="alert alert-info">Không tìm thấy sách phù hợp.</p></div>
                    <?php endif; ?>
                </div>
                <?php if ($totalPages > 1): ?>
                    <nav class="mt-4" aria-label="Phân trang sách">
                        <ul class="pagination justify-content-center">
                            <?php for ($pageNumber = 1; $pageNumber <= $totalPages; $pageNumber++): ?>
                                <?php $pageUrl = http_build_query(array_merge($paginationParams, ["page" => $pageNumber])); ?>
                                <li class="page-item <?= $pageNumber === $page ? "active" : "" ?>">
                                    <a class="page-link" href="index.php?<?= htmlspecialchars($pageUrl) ?>"><?= $pageNumber ?></a>
                                </li>
                            <?php endfor; ?>
                        </ul>
                    </nav>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Nhúng Bootstrap 5 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>