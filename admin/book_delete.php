<?php
// admin/book_delete.php - Xóa sách
session_start();
if (!isset($_SESSION["admin"])) { header("Location: login.php"); exit; }
require "../db.php";

$id = isset($_GET["id"]) ? (int)$_GET["id"] : 0;
if ($id > 0) {
    // Lấy thông tin ảnh cũ để xóa khỏi thư mục uploads/
    $res = mysqli_query($conn, "SELECT image FROM books WHERE id = $id");
    if ($row = mysqli_fetch_assoc($res)) {
        if ($row['image'] != "default.jpg" && file_exists("../uploads/" . $row['image'])) {
            unlink("../uploads/" . $row['image']);
        }
    }
    mysqli_query($conn, "DELETE FROM books WHERE id = $id");
}

header("Location: index.php");
exit;
?>