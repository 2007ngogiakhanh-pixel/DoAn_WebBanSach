<?php
// admin/book_add.php - Thêm sách mới
session_start();
if (!isset($_SESSION["admin"])) { header("Location: login.php"); exit; }
require "../auth.php";
require "../db.php";
require "../book_helpers.php";

$categories = mysqli_query($conn, "SELECT * FROM categories ORDER BY name");
$loi = "";
$categoryIds = [];
$newCategoryInput = "";
$title = $author = $description = "";
$price = "";
$stockQuantity = 0;

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (!verify_csrf_token()) {
        http_response_code(403);
        exit("Yêu cầu không hợp lệ. Vui lòng tải lại trang và thử lại.");
    }
    $title = trim($_POST["title"] ?? "");
    $author = trim($_POST["author"] ?? "");
    $price = (int)($_POST["price"] ?? 0);
    $stockQuantity = filter_var($_POST["stock_quantity"] ?? null, FILTER_VALIDATE_INT);
    $description = trim($_POST["description"] ?? "");
    $categoryInput = $_POST["new_categories"] ?? "";
    $newCategoryInput = is_string($categoryInput) ? $categoryInput : "";
    $categoryIds = normalize_book_category_ids($_POST["category_ids"] ?? []);
    $pendingCategoryNames = [];
    $categoryError = "";
    $validCategories = resolve_book_category_choices(
        $conn,
        $_POST["category_ids"] ?? [],
        $categoryInput,
        $categoryIds,
        $pendingCategoryNames,
        $categoryError
    );

    // Xử lý upload hình ảnh
    $image_name = "default.jpg";
    if (isset($_FILES["image"]) && $_FILES["image"]["error"] == 0) {
        $ext = pathinfo($_FILES["image"]["name"], PATHINFO_EXTENSION);
        $image_name = time() . "_" . uniqid() . "." . $ext;
        move_uploaded_file($_FILES["image"]["tmp_name"], "../uploads/" . $image_name);
    }

    if (!$validCategories) {
        $loi = $categoryError;
    } elseif ($stockQuantity === false || $stockQuantity < 0 || $stockQuantity > 4294967295) {
        $loi = "Vui lòng nhập số lượng tồn kho hợp lệ.";
    } elseif ($title == "" || $price <= 0) {
        $loi = "Vui lòng điền tiêu đề, giá hợp lệ và chọn ít nhất một thể loại.";
    } else {
        if (mysqli_begin_transaction($conn)) {
            $saved = create_book_categories($conn, $pendingCategoryNames, $categoryIds);
            $category_id = $categoryIds[0] ?? 0;
            $stmt = mysqli_prepare($conn, "INSERT INTO books (title, author, price, image, description, category_id, stock_quantity, is_available) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $saved = $saved && $stmt !== false && $category_id > 0;
            if ($saved) {
                $availability = $stockQuantity > 0 ? 1 : 0;
                mysqli_stmt_bind_param($stmt, "ssissiii", $title, $author, $price, $image_name, $description, $category_id, $stockQuantity, $availability);
                $saved = mysqli_stmt_execute($stmt);
            }
            if ($saved) {
                $bookId = (int)mysqli_insert_id($conn);
                $saved = replace_book_categories($conn, $bookId, $categoryIds);
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
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Tên sách (*):</label>
                        <input type="text" name="title" class="form-control" value="<?= htmlspecialchars($title) ?>" required>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Tác giả:</label>
                            <input type="text" name="author" class="form-control" value="<?= htmlspecialchars($author) ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Thể loại (chọn một hoặc nhiều):</label>
                            <input class="form-control form-control-sm mb-2 category-search" type="search" placeholder="Tìm thể loại..." aria-label="Tìm thể loại">
                            <div class="border rounded p-3 category-list" style="max-height:180px;overflow-y:auto;overscroll-behavior:contain">
                                <?php while ($cat = mysqli_fetch_assoc($categories)): ?>
                                    <div class="form-check category-option">
                                        <input class="form-check-input" type="checkbox" name="category_ids[]" value="<?= (int)$cat['id'] ?>" id="category-<?= (int)$cat['id'] ?>" <?= in_array((int)$cat['id'], $categoryIds, true) ? "checked" : "" ?>>
                                        <label class="form-check-label" for="category-<?= (int)$cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></label>
                                    </div>
                                <?php endwhile; ?>
                                <div class="text-muted small category-empty" hidden>Không tìm thấy thể loại.</div>
                            </div>
                            <button class="btn btn-outline-success btn-sm mt-2" id="toggle-new-category" type="button" aria-expanded="<?= $newCategoryInput !== "" ? "true" : "false" ?>" aria-controls="new-category-panel">+ Thêm thể loại mới</button>
                            <div class="mt-2" id="new-category-panel" <?= $newCategoryInput !== "" ? "" : "hidden" ?>>
                                <label class="form-label" for="new-categories">Tên thể loại mới</label>
                                <input class="form-control" id="new-categories" name="new_categories" value="<?= htmlspecialchars($newCategoryInput) ?>" maxlength="500" placeholder="Ví dụ: Truyện trinh thám, Lịch sử">
                                <div class="form-text">Thể loại mới do admin thêm sẽ có hiệu lực ngay. Có thể nhập nhiều thể loại, cách nhau bằng dấu phẩy.</div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">Số lượng tồn kho:</label>
                            <input type="number" name="stock_quantity" class="form-control" value="<?= htmlspecialchars((string)$stockQuantity) ?>" min="0" required>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Giá bán (đ):</label>
                            <input type="number" name="price" class="form-control" value="<?= htmlspecialchars((string)$price) ?>" required min="1000">
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
        if (isHidden) document.getElementById("new-categories").focus();
    });
    </script>
</body>
</html>