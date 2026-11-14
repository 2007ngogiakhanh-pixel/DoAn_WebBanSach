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
<?php include __DIR__ . '/views/book_add.html';
