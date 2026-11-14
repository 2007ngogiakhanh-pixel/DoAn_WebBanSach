<?php
// admin/login.php - Trang đăng nhập quản trị
session_start();

$loi = "";
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST["username"]);
    $password = trim($_POST["password"]);

    // Tài khoản cố định cho bản demo đồ án
    if ($username === "admin" && $password === "123456") {
        $_SESSION["admin"] = $username;
        header("Location: index.php");
        exit;
    } else {
        $loi = "Tên đăng nhập hoặc mật khẩu không đúng!";
    }
}
?>
<?php include __DIR__ . '/views/login.html';
