<?php
session_start();
require "../auth.php";
require_role("seller");
require "../db.php";
require "../book_helpers.php";

$categories = mysqli_query($conn, "SELECT id, name FROM categories ORDER BY name");
$error = "";
$title = $author = $description = "";
$price = 0;
$categoryIds = [];
$newCategoryInput = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!verify_csrf_token()) {
        http_response_code(403);
        exit("Yêu cầu không hợp lệ. Vui lòng tải lại trang và thử lại.");
    }

    $title = trim($_POST["title"] ?? "");
    $author = trim($_POST["author"] ?? "");
    $description = trim($_POST["description"] ?? "");
    $price = (int)($_POST["price"] ?? 0);
    $categoryInput = $_POST["new_categories"] ?? "";
    $newCategoryInput = is_string($categoryInput) ? $categoryInput : "";
    $pendingCategoryNames = [];
    $validCategories = resolve_book_category_choices(
        $conn,
        $_POST["category_ids"] ?? [],
        $categoryInput,
        $categoryIds,
        $pendingCategoryNames,
        $error
    );

    if ($validCategories && ($title === "" || $author === "" || $price <= 0)) {
        $error = "Vui lòng nhập tên sách, tác giả và giá hợp lệ.";
    } elseif ($validCategories) {
        $imageError = "";
        $pendingImage = save_book_image($_FILES["image"] ?? [], $imageError);
        if ($imageError !== "") {
            $error = $imageError;
        } else {
            if (mysqli_begin_transaction($conn)) {
                $sellerId = (int)$_SESSION["user_id"];
                $imageName = "default.jpg";
                $approvalStatus = "pending";
                $categoryId = $categoryIds[0] ?? 0;
                if ($categoryId === 0) {
                    $fallbackCategory = mysqli_query($conn, "SELECT id FROM categories ORDER BY id LIMIT 1");
                    $fallback = $fallbackCategory ? mysqli_fetch_assoc($fallbackCategory) : null;
                    $categoryId = (int)($fallback["id"] ?? 0);
                }
                $stmt = mysqli_prepare($conn, "INSERT INTO books (title, author, price, image, pending_image, description, category_id, seller_id, approval_status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $saved = $stmt !== false && $categoryId > 0;
                if ($saved) {
                    mysqli_stmt_bind_param($stmt, "ssisssiis", $title, $author, $price, $imageName, $pendingImage, $description, $categoryId, $sellerId, $approvalStatus);
                    $saved = mysqli_stmt_execute($stmt);
                }
                if ($saved) {
                    $bookId = (int)mysqli_insert_id($conn);
                    $saved = replace_book_categories($conn, $bookId, $categoryIds);
                }
                if ($saved) {
                    $saved = replace_pending_book_categories($conn, $bookId, $pendingCategoryNames);
                }
                if ($saved && mysqli_commit($conn)) {
                    $_SESSION["seller_notice"] = "Đã lưu sách. Sách đang chờ admin duyệt trước khi hiển thị trên cửa hàng.";
                    header("Location: index.php");
                    exit;
                }
                mysqli_rollback($conn);
            }
            delete_pending_book_image($pendingImage);
            $error = "Không thể lưu sách. Vui lòng thử lại.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đăng bán sách</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<main class="container my-4" style="max-width:700px">
    <div class="card shadow-sm">
        <div class="card-header bg-success text-white"><h4 class="mb-0">Gửi sách để admin duyệt</h4></div>
        <div class="card-body">
            <?php if ($error !== ""): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
            <form method="post" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                <div class="mb-3"><label class="form-label">Tên sách</label><input class="form-control" name="title" value="<?= htmlspecialchars($title) ?>" required></div>
                <div class="mb-3"><label class="form-label">Tác giả</label><input class="form-control" name="author" value="<?= htmlspecialchars($author) ?>" required></div>
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
                        <input class="form-control" id="new-categories" name="new_categories" value="<?= htmlspecialchars($newCategoryInput) ?>" maxlength="500" placeholder="Ví dụ: Truyện trinh thám, Lịch sử">
                        <div class="form-text">Có thể nhập nhiều thể loại, cách nhau bằng dấu phẩy. Chữ cái đầu sẽ tự viết hoa; thể loại mới chỉ được thêm sau khi admin duyệt sách.</div>
                    </div>
                </div>
                <div class="mb-3"><label class="form-label">Giá bán (đ)</label><input class="form-control" type="number" min="1" name="price" value="<?= $price ?: "" ?>" required></div>
                <div class="mb-3"><label class="form-label">Ảnh bìa (JPG, PNG, WebP; tối đa 5 MB)</label><input class="form-control" type="file" name="image" accept=".jpg,.jpeg,.png,.webp"></div>
                <div class="mb-3"><label class="form-label">Mô tả</label><textarea class="form-control" name="description" rows="4"><?= htmlspecialchars($description) ?></textarea></div>
                <p class="text-muted">Sách và ảnh sẽ được admin kiểm tra trước khi hiển thị trên cửa hàng.</p>
                <a class="btn btn-secondary" href="index.php">Hủy</a>
                <button class="btn btn-success" type="submit">Lưu sách</button>
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
