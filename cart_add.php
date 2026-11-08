<?php
// cart_add.php - Thêm sách vào giỏ hàng
session_start();
require "db.php";

if ($_SERVER["REQUEST_METHOD"] != "POST") {
    header("Location: index.php");
    exit;
}

$id  = (int)$_POST["id"];
$qty = (int)$_POST["qty"];
if ($qty < 1) {
    $qty = 1;
}

// Kiểm tra sách có tồn tại trong CSDL không
$result = mysqli_query($conn, "SELECT id FROM books WHERE id = $id");
if (mysqli_num_rows($result) == 0) {
    die("Sách không tồn tại!");
}

if (!isset($_SESSION["cart"])) {
    $_SESSION["cart"] = [];
}

// Nếu đã có trong giỏ thì cộng dồn, chưa có thì thêm mới
if (isset($_SESSION["cart"][$id])) {
    $_SESSION["cart"][$id] += $qty;
} else {
    $_SESSION["cart"][$id] = $qty;
}

header("Location: cart.php");
exit;
?>