<?php
session_start();
require "db.php";
$next = ($_POST["next"] ?? $_GET["next"] ?? "") === "checkout" ? "checkout" : "";
$loi = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = trim($_POST["email"] ?? "");
    $password = $_POST["password"] ?? "";
    $stmt = mysqli_prepare($conn, "SELECT id, full_name, password_hash, role, status FROM users WHERE email = ?");
    mysqli_stmt_bind_param($stmt, "s", $email);
    mysqli_stmt_execute($stmt);
    $user = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

    if (!$user || !password_verify($password, $user["password_hash"])) {
        $loi = "Email hoặc mật khẩu không đúng.";
    } elseif ($user["status"] !== "active") {
        $loi = $user["status"] === "pending"
            ? "Tài khoản người bán đang chờ admin duyệt."
            : "Tài khoản này chưa được kích hoạt.";
    } else {
        session_regenerate_id(true);
        $_SESSION["user_id"] = (int)$user["id"];
        $_SESSION["user_name"] = $user["full_name"];
        $_SESSION["user_role"] = $user["role"];
        header("Location: " . ($user["role"] === "seller"
            ? "seller/index.php"
            : ($next === "checkout" ? "checkout.php" : "index.php")));
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đăng nhập</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <main class="container my-5" style="max-width: 440px;">
        <div class="card shadow-sm">
            <div class="card-header bg-dark text-white"><h4 class="mb-0">Đăng nhập</h4></div>
            <div class="card-body p-4">
                <?php if ($loi !== ""): ?><div class="alert alert-danger"><?= htmlspecialchars($loi) ?></div><?php endif; ?>
                <form method="post">
                    <input type="hidden" name="next" value="<?= htmlspecialchars($next) ?>">
                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input class="form-control" type="email" name="email" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Mật khẩu</label>
                        <input class="form-control" type="password" name="password" required>
                    </div>
                    <button class="btn btn-dark w-100" type="submit">Đăng nhập</button>
                </form>
                <p class="mt-3 mb-0 text-center">Chưa có tài khoản? <a href="register.php">Đăng ký</a></p>
            </div>
        </div>
    </main>
</body>
</html>
