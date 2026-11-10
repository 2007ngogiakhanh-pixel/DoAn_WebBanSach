<?php
session_start();
require "db.php";
require "cart_storage.php";

if (isset($_SESSION["user_id"]) && !sync_session_cart($conn)) {
    http_response_code(500);
    exit("Không thể lưu giỏ hàng trước khi đăng xuất. Vui lòng thử lại.");
}

unset($_SESSION["user_id"], $_SESSION["user_name"], $_SESSION["user_role"], $_SESSION["cart"]);
session_regenerate_id(true);
header("Location: index.php");
exit;
