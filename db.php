<?php
// db.php - Kết nối cơ sở dữ liệu bookstore
$conn = mysqli_connect("localhost", "root", "", "bookstore");

if (!$conn) {
    die("Kết nối CSDL thất bại: " . mysqli_connect_error());
}

mysqli_set_charset($conn, "utf8mb4");
?>