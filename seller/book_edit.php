<?php
session_start();
require "../auth.php";
require_once "../db.php";
require_role("seller");
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
$categoryResultStmt = mysqli_prepare($conn, "SELECT category_id FROM book_categories WHERE book_id = ?");
$categoryIds = [];
if ($categoryResultStmt) {
    mysqli_stmt_bind_param($categoryResultStmt, "i", $id);
    mysqli_stmt_execute($categoryResultStmt);
    $categoryResult = mysqli_stmt_get_result($categoryResultStmt);
} else {
    $categoryResult = false;
}
while ($categoryResult && ($categoryRow = mysqli_fetch_assoc($categoryResult))) {
    $categoryIds[] = (int)$categoryRow["category_id"];
}
$pendingCategoryInput = "";
$pendingCategoryStmt = mysqli_prepare($conn, "SELECT category_name FROM book_pending_categories WHERE book_id = ?");
if ($pendingCategoryStmt) {
    mysqli_stmt_bind_param($pendingCategoryStmt, "i", $id);
    mysqli_stmt_execute($pendingCategoryStmt);
    $pendingCategoryResult = mysqli_stmt_get_result($pendingCategoryStmt);
    $pendingNames = [];
    while ($pendingCategoryRow = mysqli_fetch_assoc($pendingCategoryResult)) {
        $pendingNames[] = $pendingCategoryRow["category_name"];
    }
    $pendingCategoryInput = implode(", ", $pendingNames);
}
if ($categoryIds === [] && $pendingCategoryInput === "") {
    $categoryIds[] = (int)$book["category_id"];
}

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
    $pendingCategoryInput = is_string($categoryInput) ? $categoryInput : "";
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
        $newImage = save_book_image($_FILES["image"] ?? [], $imageError);
        if ($imageError !== "") {
            $error = $imageError;
        } else {
            $pendingImage = $newImage ?? $book["pending_image"];
            if (mysqli_begin_transaction($conn)) {
                $update = mysqli_prepare($conn, "UPDATE books SET title = ?, author = ?, price = ?, pending_image = ?, description = ?, approval_status = 'pending' WHERE id = ? AND seller_id = ?");
                $saved = $update !== false;
                if ($saved) {
                    mysqli_stmt_bind_param($update, "ssissii", $title, $author, $price, $pendingImage, $description, $id, $sellerId);
                    $saved = mysqli_stmt_execute($update);
                }
                if ($saved) {
                    $saved = replace_book_categories($conn, $id, $categoryIds);
                }
                if ($saved) {
                    $saved = replace_pending_book_categories($conn, $id, $pendingCategoryNames);
                }
                if ($saved && mysqli_commit($conn)) {
                    if ($newImage !== null && $book["pending_image"] !== null && $book["pending_image"] !== $newImage) {
                        delete_pending_book_image($book["pending_image"]);
                    }
                    $_SESSION["seller_notice"] = "Đã gửi thay đổi. Sách đang chờ admin duyệt trước khi hiển thị trên cửa hàng.";
                    header("Location: index.php");
                    exit;
                }
                mysqli_rollback($conn);
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
                    <button class="btn btn-outline-success btn-sm mt-2" id="toggle-new-category" type="button" aria-expanded="<?= $pendingCategoryInput !== "" ? "true" : "false" ?>" aria-controls="new-category-panel">+ Thêm thể loại mới</button>
                    <div class="mt-2" id="new-category-panel" <?= $pendingCategoryInput !== "" ? "" : "hidden" ?>>
                        <label class="form-label" for="new-categories">Tên thể loại mới</label>
                        <input class="form-control" id="new-categories" name="new_categories" value="<?= htmlspecialchars($pendingCategoryInput) ?>" maxlength="500" placeholder="Ví dụ: Truyện trinh thám, Lịch sử">
                        <div class="form-text">Có thể nhập nhiều thể loại, cách nhau bằng dấu phẩy. Chữ cái đầu sẽ tự viết hoa; thể loại mới chỉ được thêm sau khi admin duyệt sách.</div>
                    </div>
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
