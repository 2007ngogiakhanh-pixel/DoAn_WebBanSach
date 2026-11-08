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
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đăng nhập Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container my-5" style="max-width: 400px;">
        <div class="card shadow">
            <div class="card-header bg-dark text-white text-center py-3">
                <h4 class="mb-0">🔐 ĐĂNG NHẬP ADMIN</h4>
            </div>
            <div class="card-body p-4">
                <?php if ($loi != ""): ?>
                    <div class="alert alert-danger py-2"><?= $loi ?></div>
                <?php endif; ?>

                <form method="post">
                    <div class="mb-3">
                        <label class="form-label font-weight-bold">Tài khoản:</label>
                        <input type="text" name="username" class="form-control" required placeholder="admin">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Mật khẩu:</label>
                        <input type="password" name="password" class="form-control" required placeholder="123456">
                    </div>
                    <button type="submit" class="btn btn-dark w-100 py-2">Đăng nhập</button>
                </form>
            </div>
        </div>
    </div>
</body>
</html>