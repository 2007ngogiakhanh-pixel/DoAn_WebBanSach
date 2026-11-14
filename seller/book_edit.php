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
$stockQuantity = (int)$book["stock_quantity"];
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
    $stockQuantity = filter_var($_POST["stock_quantity"] ?? null, FILTER_VALIDATE_INT);
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

    if ($stockQuantity === false || $stockQuantity < 0 || $stockQuantity > 4294967295) {
        $error = "Số lượng tồn kho không hợp lệ.";
    } elseif ($validCategories && ($title === "" || $author === "" || $price <= 0)) {
        $error = "Vui lòng nhập tên sách, tác giả, giá và tồn kho hợp lệ.";
    } elseif ($validCategories) {
        $imageError = "";
        $newImage = save_book_image($_FILES["image"] ?? [], $imageError);
        if ($imageError !== "") {
            $error = $imageError;
        } else {
            $pendingImage = $newImage ?? $book["pending_image"];
            if (mysqli_begin_transaction($conn)) {
                $update = mysqli_prepare($conn, "UPDATE books SET title = ?, author = ?, price = ?, pending_image = ?, description = ?, stock_quantity = ?, is_available = IF(? > 0, 1, 0), approval_status = 'pending' WHERE id = ? AND seller_id = ?");
                $saved = $update !== false;
                if ($saved) {
                    mysqli_stmt_bind_param($update, "ssissiiii", $title, $author, $price, $pendingImage, $description, $stockQuantity, $stockQuantity, $id, $sellerId);
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
<?php include __DIR__ . '/views/book_edit.html';
