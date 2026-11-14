<?php
// index.php - Trang chủ hiển thị danh sách sách
session_start();
require "db.php";
require "auth.php";

$cat_result = mysqli_query($conn, "SELECT id, name FROM categories ORDER BY name");
$queryInput = $_GET["q"] ?? "";
$search = is_string($queryInput) ? trim(mb_substr($queryInput, 0, 100, "UTF-8")) : "";
$categoryInput = $_GET["cat"] ?? "";
$cat_id = filter_var($categoryInput, FILTER_VALIDATE_INT);
$cat_id = $cat_id !== false && $cat_id > 0 ? $cat_id : 0;
$sortOptions = [
    "newest" => "b.id DESC",
    "price_asc" => "b.price ASC, b.id DESC",
    "price_desc" => "b.price DESC, b.id DESC",
    "title_asc" => "b.title ASC, b.id DESC",
];
$sort = $_GET["sort"] ?? "newest";
if (!is_string($sort) || !isset($sortOptions[$sort])) {
    $sort = "newest";
}
$pageInput = $_GET["page"] ?? 1;
$page = filter_var($pageInput, FILTER_VALIDATE_INT);
$page = $page !== false && $page > 0 ? $page : 1;
$perPage = 20;
$offset = ($page - 1) * $perPage;
$searchPattern = "%" . $search . "%";
$filterSql = "(? = '' OR b.title LIKE ? OR b.author LIKE ?) AND (? = 0 OR EXISTS (SELECT 1 FROM book_categories bc WHERE bc.book_id = b.id AND bc.category_id = ?)) AND (b.seller_id IS NULL OR b.approval_status = 'approved')";

$countStmt = mysqli_prepare($conn, "SELECT COUNT(*) AS total FROM books b WHERE $filterSql");
mysqli_stmt_bind_param($countStmt, "sssii", $search, $searchPattern, $searchPattern, $cat_id, $cat_id);
mysqli_stmt_execute($countStmt);
$totalBooks = (int)mysqli_fetch_assoc(mysqli_stmt_get_result($countStmt))["total"];
$totalPages = max(1, (int)ceil($totalBooks / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;

$booksStmt = mysqli_prepare($conn, "SELECT b.* FROM books b WHERE $filterSql ORDER BY " . $sortOptions[$sort] . " LIMIT ? OFFSET ?");
mysqli_stmt_bind_param($booksStmt, "sssiiii", $search, $searchPattern, $searchPattern, $cat_id, $cat_id, $perPage, $offset);
mysqli_stmt_execute($booksStmt);
$books_result = mysqli_stmt_get_result($booksStmt);

$paginationParams = ["q" => $search, "sort" => $sort];
if ($cat_id > 0) {
    $paginationParams["cat"] = $cat_id;
}
?>
<?php include __DIR__ . '/views/index.html';
