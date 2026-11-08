<?php
session_start();
require "../auth.php";
require_role("seller");
require "../db.php";
require "../book_helpers.php";

$sellerId = (int)$_SESSION["user_id"];
$id = (int)($_GET["id"] ?? $_POST["id"] ?? 0);
$stmt = mysqli_prepare($conn, "SELECT * FROM books WHERE id = ? AND seller_id = ?");
mysqli_stmt_bind_param($stmt, "ii", $id, $sellerId);
mysqli_stmt_execute($stmt);
$book = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
if (!$book) {
    http_response_code(404);
    exit("Không tìm thấy sách của bạn.");
}

$categories = mysqli_query($conn, "SELECT id, name FROM categories ORDER BY name");
$error = "";
$title = $book["title"];
$author = $book["author"];
$description = $book["description"] ?? "";
$price = (int)$book["price"];
$categoryId = (int)$book["category_id"];

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!verify_csrf_token()) {
        http_response_code(403);
        exit("Yêu cầu không hợp lệ. Vui lòng tải lại trang và thử lại.");
    }

    $title = trim($_POST["title"] ?? "");
    $author = trim($_POST["author"] ?? "");
    $description = trim($_POST["description"] ?? "");
    $price = (int)($_POST["price"] ?? 0);
    $categoryId = (int)($_POST["category_id"] ?? 0);
    $categoryCheck = mysqli_prepare($conn, "SELECT id FROM categories WHERE id = ?");
    mysqli_stmt_bind_param($categoryCheck, "i", $categoryId);
    mysqli_stmt_execute($categoryCheck);
    $validCategory = mysqli_stmt_get_result($categoryCheck);

    if ($title === "" || $author === "" || $price <= 0 || mysqli_num_rows($validCategory) === 0) {
        $error = "Vui lòng nhập tên sách, tác giả, giá hợp lệ và chọn thể loại.";
    } else {
        $imageError = "";
        $newImage = save_book_image($_FILES["image"] ?? [], $imageError);
        if ($imageError !== "") {
            $error = $imageError;
        } else {
            $pendingImage = $newImage ?? $book["pending_image"];
            $update = mysqli_prepare($conn, "UPDATE books SET title = ?, author = ?, price = ?, pending_image = ?, description = ?, category_id = ?, approval_status = 'pending' WHERE id = ? AND seller_id = ?");
            mysqli_stmt_bind_param($update, "ssissiii", $title, $author, $price, $pendingImage, $description, $categoryId, $id, $sellerId);
            if (mysqli_stmt_execute($update)) {
                if ($newImage !== null && $book["pending_image"] !== null && $book["pending_image"] !== $newImage) {
                    delete_pending_book_image($book["pending_image"]);
                }
                header("Location: index.php");
                exit;
            }
            delete_pending_book_image($newImage);
            $error = "Không thể cập nhật sách. Vui lòng thử lại.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sửa sách</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<main class="container my-4" style="max-width:700px">
    <div class="card shadow-sm">
        <div class="card-header bg-warning"><h4 class="mb-0">Sửa thông tin sách</h4></div>
        <div class="card-body">
            <?php if ($error !== ""): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
            <form method="post" enctype="multipart/form-data">
                <input type="hidden" name="id" value="<?= $id ?>">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                <div class="mb-3"><label class="form-label">Tên sách</label><input class="form-control" name="title" value="<?= htmlspecialchars($title) ?>" required></div>
                <div class="mb-3"><label class="form-label">Tác giả</label><input class="form-control" name="author" value="<?= htmlspecialchars($author) ?>" required></div>
                <div class="mb-3">
                    <label class="form-label">Thể loại</label>
                    <select class="form-select" name="category_id" required>
                        <?php while ($category = mysqli_fetch_assoc($categories)): ?>
                            <option value="<?= (int)$category["id"] ?>" <?= $categoryId === (int)$category["id"] ? "selected" : "" ?>><?= htmlspecialchars($category["name"]) ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="mb-3"><label class="form-label">Giá bán (đ)</label><input class="form-control" type="number" min="1" name="price" value="<?= $price ?>" required></div>
                <div class="mb-3"><label class="form-label">Ảnh bìa mới (không chọn nếu giữ ảnh hiện tại; ảnh mới cần admin duyệt)</label><input class="form-control" type="file" name="image" accept=".jpg,.jpeg,.png,.webp"></div>
                <div class="mb-3"><label class="form-label">Mô tả</label><textarea class="form-control" name="description" rows="4"><?= htmlspecialchars($description) ?></textarea></div>
                <a class="btn btn-secondary" href="index.php">Hủy</a>
                <button class="btn btn-warning" type="submit">Lưu thay đổi</button>
            </form>
        </div>
    </div>
</main>
</body>
</html>
