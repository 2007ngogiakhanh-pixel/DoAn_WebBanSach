<?php
// cart.php - Xem danh sách sách trong giỏ
session_start();
require "db.php";
require "auth.php";
require "cart_storage.php";

$cart  = normalize_cart($_SESSION["cart"] ?? []);
$items = [];
$tong  = 0;
$hasUnavailable = false;
$cartError = $_SESSION["cart_error"] ?? "";
unset($_SESSION["cart_error"]);

if (count($cart) > 0) {
    $ids = implode(",", array_map("intval", array_keys($cart)));
    $result = mysqli_query($conn, "SELECT * FROM books WHERE id IN ($ids)");
    if ($result === false) {
        http_response_code(500);
        exit("Không thể tải giỏ hàng.");
    }

    $cartChanged = false;
    $seenBookIds = [];
    while ($book = mysqli_fetch_assoc($result)) {
        $bookId = (int)$book["id"];
        $seenBookIds[$bookId] = true;
        $quantity = (int)$cart[$bookId];
        if ((int)$book["stock_quantity"] === 0) {
            unset($cart[$bookId]);
            $cartChanged = true;
            $cartError = "Sách hết hàng đã được bỏ khỏi giỏ.";
            continue;
        }
        if ($quantity > (int)$book["stock_quantity"]) {
            $quantity = (int)$book["stock_quantity"];
            $cart[$bookId] = $quantity;
            $cartChanged = true;
            $cartError = "Số lượng giỏ hàng đã tự giảm theo tồn kho hiện tại.";
        }

        $book["soluong"]   = $quantity;
        $book["thanhtien"] = $book["price"] * $book["soluong"];
        $tong += $book["thanhtien"];
        $hasUnavailable = $hasUnavailable
            || !(bool)$book["is_available"]
            || (int)$book["stock_quantity"] < $book["soluong"]
            || ($book["seller_id"] !== null && $book["approval_status"] !== "approved");
        $items[] = $book;
    }
    foreach (array_keys($cart) as $bookId) {
        if (!isset($seenBookIds[$bookId])) {
            unset($cart[$bookId]);
            $cartChanged = true;
        }
    }
    if ($cartChanged) {
        $_SESSION["cart"] = $cart;
        if (!sync_session_cart($conn)) {
            http_response_code(500);
            exit("Không thể tự cập nhật giỏ hàng. Vui lòng thử lại.");
        }
    }
}
?>
<?php include __DIR__ . '/views/cart.html';
