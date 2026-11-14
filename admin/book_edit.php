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
<?php include __DIR__ . '/views/book_edit.html';
