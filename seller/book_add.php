<?php
session_start();
require "../auth.php";
require_once "../db.php";
require_role("seller");
require "../book_helpers.php";

$categories = mysqli_query($conn, "SELECT id, name FROM categories ORDER BY name");
$error = "";
$title = $author = $description = "";
$price = 0;
$stockQuantity = 0;
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
    $stockQuantity = filter_var($_POST["stock_quantity"] ?? null, FILTER_VALIDATE_INT);
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

    if ($stockQuantity === false || $stockQuantity < 0 || $stockQuantity > 4294967295) {
        $error = "Số lượng tồn kho không hợp lệ.";
    } elseif ($validCategories && ($title === "" || $author === "" || $price <= 0)) {
        $error = "Vui lòng nhập tên sách, tác giả, giá và tồn kho hợp lệ.";
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
                $availability = $stockQuantity > 0 ? 1 : 0;
                $stmt = mysqli_prepare($conn, "INSERT INTO books (title, author, price, image, pending_image, description, category_id, seller_id, approval_status, stock_quantity, is_available) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $saved = $stmt !== false && $categoryId > 0;
                if ($saved) {
                    mysqli_stmt_bind_param($stmt, "ssisssiisii", $title, $author, $price, $imageName, $pendingImage, $description, $categoryId, $sellerId, $approvalStatus, $stockQuantity, $availability);
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
<?php include __DIR__ . '/views/book_add.html';
