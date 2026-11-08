<?php
// admin/book_add.php - Thêm sách mới
session_start();
if (!isset($_SESSION["admin"])) { header("Location: login.php"); exit; }
require "../db.php";
require "../book_helpers.php";

$categories = mysqli_query($conn, "SELECT * FROM categories");
$loi = "";
$categoryIds = [];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $title       = trim($_POST["title"]);
    $author      = trim($_POST["author"]);
    $price       = (int)$_POST["price"];
    $categoryIds = normalize_book_category_ids($_POST["category_ids"] ?? []);
    $description = trim($_POST["description"]);

    // Xử lý upload hình ảnh
    $image_name = "default.jpg";
    if (isset($_FILES["image"]) && $_FILES["image"]["error"] == 0) {
        $ext = pathinfo($_FILES["image"]["name"], PATHINFO_EXTENSION);
        $image_name = time() . "_" . uniqid() . "." . $ext;
        move_uploaded_file($_FILES["image"]["tmp_name"], "../uploads/" . $image_name);
    }

    if ($title == "" || $price <= 0 || !book_categories_exist($conn, $categoryIds)) {
        $loi = "Vui lòng điền tiêu đề, giá hợp lệ và chọn ít nhất một thể loại.";
    } else {
        if (mysqli_begin_transaction($conn)) {
            $category_id = $categoryIds[0];
            $stmt = mysqli_prepare($conn, "INSERT INTO books (title, author, price, image, description, category_id) VALUES (?, ?, ?, ?, ?, ?)");
            $saved = $stmt !== false;
            if ($saved) {
                mysqli_stmt_bind_param($stmt, "ssissi", $title, $author, $price, $image_name, $description, $category_id);
                $saved = mysqli_stmt_execute($stmt);
            }
            if ($saved) {
                $saved = replace_book_categories($conn, mysqli_insert_id($conn), $categoryIds);
            }
            if ($saved && mysqli_commit($conn)) {
                header("Location: index.php");
                exit;
            }
            mysqli_rollback($conn);
        }
        $loi = "Không thể lưu sách. Vui lòng thử lại.";
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Thêm Sách Mới</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container my-4" style="max-width: 700px;">
        <div class="card shadow-sm">
            <div class="card-header bg-success text-white">
                <h4 class="mb-0">+ Thêm Sách Mới</h4>
            </div>
            <div class="card-body">
                <?php if ($loi != ""): ?><div class="alert alert-danger"><?= $loi ?></div><?php endif; ?>

                <form method="post" enctype="multipart/form-data">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Tên sách (*):</label>
                        <input type="text" name="title" class="form-control" required>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Tác giả:</label>
                            <input type="text" name="author" class="form-control" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Thể loại (chọn một hoặc nhiều):</label>
                            <div class="border rounded p-3">
                                <?php while ($cat = mysqli_fetch_assoc($categories)): ?>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="category_ids[]" value="<?= (int)$cat['id'] ?>" id="category-<?= (int)$cat['id'] ?>" <?= in_array((int)$cat['id'], $categoryIds, true) ? "checked" : "" ?>>
                                        <label class="form-check-label" for="category-<?= (int)$cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></label>
                                    </div>
                                <?php endwhile; ?>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Giá bán (đ):</label>
                            <input type="number" name="price" class="form-control" required min="1000">
                        </div>
                        <div class="col-md-6 mb-3">
                            <!-- 📌 VỊ TRÍ UPLOAD HÌNH ẢNH SÁCH MỚI -->
                            <label class="form-label fw-bold">Ảnh bìa sách:</label>
                            <!-- Ghi chú: Chọn file hình ảnh định dạng JPG/PNG từ máy tính. 
                                 File ảnh sẽ tự động được lưu vào thư mục /uploads/ -->
                            <input type="file" name="image" class="form-control" accept="image/*">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Mô tả chi tiết:</label>
                        <textarea name="description" class="form-control" rows="4"></textarea>
                    </div>
                    <div class="d-flex justify-content-between">
                        <a href="index.php" class="btn btn-secondary">Hủy bỏ</a>
                        <button type="submit" class="btn btn-success px-4">Lưu Sách</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</body>
</html>