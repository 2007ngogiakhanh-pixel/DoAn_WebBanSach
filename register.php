<?php
session_start();
require "auth.php";
require "db.php";

$loi = "";
$hoTen = "";
$email = "";
$role = "customer";
$thongBao = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $nameInput = $_POST["full_name"] ?? "";
    $emailInput = $_POST["email"] ?? "";
    $passwordInput = $_POST["password"] ?? "";
    $roleInput = $_POST["role"] ?? "customer";
    if (!verify_csrf_token()) {
        http_response_code(403);
        exit("Yêu cầu không hợp lệ. Vui lòng tải lại trang và thử lại.");
    }

    $hoTen = is_string($nameInput) ? trim($nameInput) : "";
    $email = is_string($emailInput) ? trim($emailInput) : "";
    $password = is_string($passwordInput) ? $passwordInput : "";
    $role = is_string($roleInput) ? $roleInput : "";

    if ($hoTen === "" || mb_strlen($hoTen, "UTF-8") > 150 || !filter_var($email, FILTER_VALIDATE_EMAIL)
        || strlen($email) > 190 || strlen($password) < 8) {
        $loi = "Vui lòng nhập họ tên, email hợp lệ và mật khẩu ít nhất 8 ký tự.";
    } elseif (!in_array($role, ["customer", "seller"], true)) {
        $loi = "Vai trò đăng ký không hợp lệ.";
    } else {
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        $status = $role === "seller" ? "pending" : "active";
        $stmt = mysqli_prepare($conn, "INSERT INTO users (full_name, email, password_hash, role, status) VALUES (?, ?, ?, ?, ?)");
        if ($stmt === false) {
            $loi = "Không thể tạo tài khoản. Vui lòng thử lại.";
        } else {
            mysqli_stmt_bind_param($stmt, "sssss", $hoTen, $email, $passwordHash, $role, $status);
        }
        if ($stmt !== false && mysqli_stmt_execute($stmt)) {
            $thongBao = $role === "seller"
                ? "Đăng ký người bán thành công. Tài khoản sẽ dùng được sau khi admin duyệt."
                : "Đăng ký thành công. Bạn có thể đăng nhập ngay.";
        } elseif (mysqli_errno($conn) === 1062) {
            $loi = "Email này đã được đăng ký.";
        } else {
            $loi = "Không thể tạo tài khoản. Vui lòng thử lại.";
        }
    }
}
?>
<?php include __DIR__ . '/views/register.html';
