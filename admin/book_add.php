<?php
// admin/book_add.php - Thêm sách mới
session_start();
if (!isset($_SESSION["admin"])) { header("Location: login.php"); exit; }
require "../db.php";

$categories = mysqli_query($conn, "SELECT * FROM categories");
$loi = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $title       = trim($_POST["title"]);
    $author      = trim($_POST["author"]);
    $price       = (int)$_POST["price"];
    $category_id = (int)$_POST["category_id"];
    $description = trim($_POST["description"]);

    // Xử lý upload hình ảnh
    $image_name = "default.jpg";
    if (isset($_FILES["image"]) && $_FILES["image"]["error"] == 0) {
        $ext = pathinfo($_FILES["image"]["name"], PATHINFO_EXTENSION);
        $image_name = time() . "_" . uniqid() . "." . $ext;
        move_uploaded_file($_FILES["image"]["tmp_name"], "../uploads/" . $image_name);
    }

    if ($title == "" || $price <= 0) {
        $loi = "Vui lòng điền đầy đủ tiêu đề và giá hợp lệ!";
    } else {
        $stmt = mysqli_prepare($conn, "INSERT INTO books (title, author, price, image, description, category_id) VALUES (?, ?, ?, ?, ?, ?)");
        mysqli_stmt_bind_param($stmt, "ssissi", $title, $author, $price, $image_name, $description, $category_id);
        mysqli_stmt_execute($stmt);
        header("Location: index.php");
        exit;
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
                            <label class="form-label fw-bold">Thể loại:</label>
                            <select name="category_id" class="form-select">
                                <?php while ($cat = mysqli_fetch_assoc($categories)): ?>
                                    <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                                <?php endwhile; ?>
                            </select>
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