<?php
session_start();
if (!isset($_SESSION["admin"])) {
    header("Location: login.php");
    exit;
}

require "../auth.php";
require "../db.php";
require "../book_helpers.php";

$idInput = $_GET["id"] ?? $_POST["id"] ?? null;
$id = is_scalar($idInput) ? filter_var($idInput, FILTER_VALIDATE_INT) : false;
if ($id === false || $id <= 0) {
    http_response_code(400);
    exit("Mã sách không hợp lệ.");
}

$bookStmt = mysqli_prepare($conn, "SELECT * FROM books WHERE id = ?");
if (!$bookStmt) {
    http_response_code(500);
    exit("Không thể tải thông tin sách.");
}
mysqli_stmt_bind_param($bookStmt, "i", $id);
if (!mysqli_stmt_execute($bookStmt)) {
    http_response_code(500);
    exit("Không thể tải thông tin sách.");
}
$book = mysqli_fetch_assoc(mysqli_stmt_get_result($bookStmt));
if (!$book) {
    http_response_code(404);
    exit("Không tìm thấy sách.");
}

$categoryStmt = mysqli_prepare($conn, "SELECT category_id FROM book_categories WHERE book_id = ?");
if (!$categoryStmt) {
    http_response_code(500);
    exit("Không thể tải thể loại sách.");
}
mysqli_stmt_bind_param($categoryStmt, "i", $id);
if (!mysqli_stmt_execute($categoryStmt)) {
    http_response_code(500);
    exit("Không thể tải thể loại sách.");
}
$categoryIds = [];
$categoryResult = mysqli_stmt_get_result($categoryStmt);
while ($categoryRow = mysqli_fetch_assoc($categoryResult)) {
    $categoryIds[] = (int)$categoryRow["category_id"];
}
if ($categoryIds === []) {
    $categoryIds[] = (int)$book["category_id"];
}

$categories = mysqli_query($conn, "SELECT id, name FROM categories ORDER BY name");
if (!$categories) {
    http_response_code(500);
    exit("Không thể tải danh sách thể loại.");
}

$error = "";
$title = $book["title"];
$author = $book["author"];
$description = $book["description"] ?? "";
$price = (int)$book["price"];
$stockQuantity = (int)$book["stock_quantity"];
$newCategoryInput = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!verify_csrf_token()) {
        http_response_code(403);
        exit("Yêu cầu không hợp lệ. Vui lòng tải lại trang và thử lại.");
    }

    $titleInput = $_POST["title"] ?? "";
    $authorInput = $_POST["author"] ?? "";
    $descriptionInput = $_POST["description"] ?? "";
    $priceInput = filter_var($_POST["price"] ?? null, FILTER_VALIDATE_INT);
    $stockInput = filter_var($_POST["stock_quantity"] ?? null, FILTER_VALIDATE_INT);
    $newCategoryInput = $_POST["new_categories"] ?? "";
    $title = is_string($titleInput) ? trim($titleInput) : "";
    $author = is_string($authorInput) ? trim($authorInput) : "";
    $description = is_string($descriptionInput) ? trim($descriptionInput) : "";
    $price = $priceInput === false ? 0 : $priceInput;
    $stockQuantity = $stockInput === false ? -1 : $stockInput;

    $pendingCategoryNames = [];
    $validCategories = resolve_book_category_choices(
        $conn,
        $_POST["category_ids"] ?? [],
        $newCategoryInput,
        $categoryIds,
        $pendingCategoryNames,
        $error
    );

    if ($title === "" || $author === "" || $price <= 0) {
        $error = "Vui lòng nhập tên sách, tác giả và giá hợp lệ.";
    } elseif ($stockQuantity < 0 || $stockQuantity > 4294967295) {
        $error = "Số lượng tồn kho không hợp lệ.";
    } elseif ($validCategories) {
        $imageError = "";
        $newImage = save_book_image($_FILES["image"] ?? [], $imageError);
        if ($imageError !== "") {
            $error = $imageError;
        } else {
            $imageReady = $newImage === null || promote_pending_book_image($newImage);
            if (!$imageReady) {
                delete_pending_book_image($newImage);
                $error = "Không thể lưu ảnh bìa. Vui lòng thử lại.";
            } elseif (mysqli_begin_transaction($conn)) {
                $saved = create_book_categories($conn, $pendingCategoryNames, $categoryIds);
                $image = $newImage ?? $book["image"];
                $availability = $stockQuantity > 0 ? 1 : 0;
                $update = mysqli_prepare($conn, "UPDATE books SET title = ?, author = ?, price = ?, image = ?, description = ?, stock_quantity = ?, is_available = ? WHERE id = ?");
                $saved = $saved && $update !== false;
                if ($saved) {
                    mysqli_stmt_bind_param($update, "ssissiii", $title, $author, $price, $image, $description, $stockQuantity, $availability, $id);
                    $saved = mysqli_stmt_execute($update);
                }
                if ($saved) {
                    $saved = replace_book_categories($conn, $id, $categoryIds);
                }
                if ($saved && mysqli_commit($conn)) {
                    if ($newImage !== null && $book["image"] !== "default.jpg" && basename($book["image"]) === $book["image"]) {
                        $oldImagePath = __DIR__ . "/../uploads/" . $book["image"];
                        if (is_file($oldImagePath)) {
                            unlink($oldImagePath);
                        }
                    }
                    header("Location: index.php#books");
                    exit;
                }

                mysqli_rollback($conn);
                if ($newImage !== null) {
                    $newImagePath = __DIR__ . "/../uploads/" . $newImage;
                    if (is_file($newImagePath)) {
                        unlink($newImagePath);
                    }
                }
                $error = "Không thể cập nhật sách. Vui lòng thử lại.";
            } else {
                if ($newImage !== null) {
                    $newImagePath = __DIR__ . "/../uploads/" . $newImage;
                    if (is_file($newImagePath)) {
                        unlink($newImagePath);
                    }
                }
                $error = "Không thể cập nhật sách. Vui lòng thử lại.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sửa sách - Admin</title>
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
                <div class="mb-3">
                    <label class="form-label" for="title">Tên sách</label>
                    <input class="form-control" id="title" name="title" value="<?= htmlspecialchars($title) ?>" required>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="author">Tác giả</label>
                    <input class="form-control" id="author" name="author" value="<?= htmlspecialchars($author) ?>" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Thể loại (chọn một hoặc nhiều)</label>
                    <input class="form-control form-control-sm mb-2 category-search" type="search" placeholder="Tìm thể loại..." aria-label="Tìm thể loại">
                    <div class="border rounded p-3 category-list" style="max-height:180px;overflow-y:auto;overscroll-behavior:contain">
                        <?php while ($category = mysqli_fetch_assoc($categories)): ?>
                            <div class="form-check category-option">
                                <input class="form-check-input" type="checkbox" name="category_ids[]" value="<?= (int)$category["id"] ?>" id="category-<?= (int)$category["id"] ?>" <?= in_array((int)$category["id"], $categoryIds, true) ? "checked" : "" ?>>
                                <label class="form-check-label" for="category-<?= (int)$category["id"] ?>"><?= htmlspecialchars($category["name"]) ?></label>
                            </div>
                        <?php endwhile; ?>
                        <div class="text-muted small category-empty" hidden>Không tìm thấy thể loại.</div>
                    </div>
                    <button class="btn btn-outline-success btn-sm mt-2" id="toggle-new-category" type="button" aria-expanded="<?= $newCategoryInput !== "" ? "true" : "false" ?>" aria-controls="new-category-panel">+ Thêm thể loại mới</button>
                    <div class="mt-2" id="new-category-panel" <?= $newCategoryInput !== "" ? "" : "hidden" ?>>
                        <label class="form-label" for="new-categories">Tên thể loại mới</label>
                        <input class="form-control" id="new-categories" name="new_categories" value="<?= htmlspecialchars(is_string($newCategoryInput) ? $newCategoryInput : "") ?>" maxlength="500" placeholder="Ví dụ: Truyện trinh thám, Lịch sử">
                        <div class="form-text">Thể loại mới do admin thêm sẽ có hiệu lực ngay. Có thể nhập nhiều thể loại, cách nhau bằng dấu phẩy.</div>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="price">Giá bán (đ)</label>
                    <input class="form-control" id="price" type="number" min="1" name="price" value="<?= htmlspecialchars((string)$price) ?>" required>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="stock-quantity">Số lượng tồn kho</label>
                    <input class="form-control" id="stock-quantity" type="number" min="0" max="4294967295" name="stock_quantity" value="<?= htmlspecialchars((string)$stockQuantity) ?>" required>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="image">Ảnh bìa mới</label>
                    <input class="form-control" id="image" type="file" name="image" accept=".jpg,.jpeg,.png,.webp">
                    <div class="form-text">Để trống nếu muốn giữ ảnh hiện tại. JPG, PNG hoặc WebP, tối đa 5 MB.</div>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="description">Mô tả</label>
                    <textarea class="form-control" id="description" name="description" rows="4"><?= htmlspecialchars($description) ?></textarea>
                </div>
                <a class="btn btn-secondary" href="index.php#books">Hủy</a>
                <button class="btn btn-warning" type="submit">Lưu thay đổi</button>
            </form>
        </div>
    </div>
</main>
<script>
document.querySelectorAll(".category-search").forEach((search) => {
    const list = search.nextElementSibling;
    const options = list.querySelectorAll(".category-option");
    const emptyMessage = list.querySelector(".category-empty");
    search.addEventListener("input", () => {
        const query = search.value.trim().toLocaleLowerCase("vi");
        let visibleCount = 0;
        options.forEach((option) => {
            const matches = option.textContent.toLocaleLowerCase("vi").includes(query);
            option.hidden = !matches;
            visibleCount += matches ? 1 : 0;
        });
        emptyMessage.hidden = visibleCount > 0;
    });
});
const newCategoryToggle = document.getElementById("toggle-new-category");
const newCategoryPanel = document.getElementById("new-category-panel");
newCategoryToggle.addEventListener("click", () => {
    const isHidden = newCategoryPanel.hidden;
    newCategoryPanel.hidden = !isHidden;
    newCategoryToggle.setAttribute("aria-expanded", String(isHidden));
    if (isHidden) {
        document.getElementById("new-categories").focus();
    }
});
</script>
</body>
</html>
