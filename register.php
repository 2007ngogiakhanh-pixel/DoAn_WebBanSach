<?php
session_start();
require "db.php";

$loi = "";
$hoTen = "";
$email = "";
$role = "customer";
$thongBao = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $hoTen = trim($_POST["full_name"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $password = $_POST["password"] ?? "";
    $role = $_POST["role"] ?? "customer";

    if ($hoTen === "" || strlen($hoTen) > 150 || !filter_var($email, FILTER_VALIDATE_EMAIL)
        || strlen($email) > 190 || strlen($password) < 8) {
        $loi = "Vui lòng nhập họ tên, email hợp lệ và mật khẩu ít nhất 8 ký tự.";
    } elseif (!in_array($role, ["customer", "seller"], true)) {
        $loi = "Vai trò đăng ký không hợp lệ.";
    } else {
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        $status = $role === "seller" ? "pending" : "active";
        $stmt = mysqli_prepare($conn, "INSERT INTO users (full_name, email, password_hash, role, status) VALUES (?, ?, ?, ?, ?)");
        mysqli_stmt_bind_param($stmt, "sssss", $hoTen, $email, $passwordHash, $role, $status);
        if (mysqli_stmt_execute($stmt)) {
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
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đăng ký tài khoản</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <main class="container my-5" style="max-width: 520px;">
        <div class="card shadow-sm">
            <div class="card-header bg-primary text-white"><h4 class="mb-0">Tạo tài khoản</h4></div>
            <div class="card-body p-4">
                <?php if ($loi !== ""): ?><div class="alert alert-danger"><?= htmlspecialchars($loi) ?></div><?php endif; ?>
                <?php if ($thongBao !== ""): ?>
                    <div class="alert alert-success"><?= htmlspecialchars($thongBao) ?></div>
                    <a class="btn btn-primary w-100" href="login.php">Đăng nhập</a>
                <?php else: ?>
                    <form method="post">
                        <div class="mb-3">
                            <label class="form-label">Họ và tên</label>
                            <input class="form-control" name="full_name" maxlength="150" value="<?= htmlspecialchars($hoTen) ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Email</label>
                            <input class="form-control" type="email" name="email" maxlength="190" value="<?= htmlspecialchars($email) ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Mật khẩu (ít nhất 8 ký tự)</label>
                            <input class="form-control" type="password" name="password" minlength="8" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Loại tài khoản</label>
                            <select class="form-select" name="role">
                                <option value="customer" <?= $role === "customer" ? "selected" : "" ?>>Khách hàng</option>
                                <option value="seller" <?= $role === "seller" ? "selected" : "" ?>>Người bán (chờ admin duyệt)</option>
                            </select>
                        </div>
                        <button class="btn btn-primary w-100" type="submit">Đăng ký</button>
                    </form>
                    <p class="mt-3 mb-0 text-center">Đã có tài khoản? <a href="login.php">Đăng nhập</a></p>
                <?php endif; ?>
            </div>
        </div>
    </main>
</body>
</html>
