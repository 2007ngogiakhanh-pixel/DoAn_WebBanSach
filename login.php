<?php
session_start();
require "db.php";
require "cart_storage.php";
$next = ($_POST["next"] ?? $_GET["next"] ?? "") === "checkout" ? "checkout" : "";
$loi = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $account = trim($_POST["email"] ?? "");
    $password = $_POST["password"] ?? "";

    if ($account === "admin" && $password === "123456") {
        session_regenerate_id(true);
        unset($_SESSION["user_id"], $_SESSION["user_name"], $_SESSION["user_role"], $_SESSION["cart"]);
        $_SESSION["admin"] = "admin";
        header("Location: admin/index.php");
        exit;
    } else {
        $stmt = mysqli_prepare($conn, "SELECT id, full_name, password_hash, role, status FROM users WHERE email = ?");
        mysqli_stmt_bind_param($stmt, "s", $account);
        mysqli_stmt_execute($stmt);
        $user = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

        if (!$user || !password_verify($password, $user["password_hash"])) {
            $loi = "Email/tài khoản hoặc mật khẩu không đúng.";
        } elseif ($user["status"] !== "active") {
            $loi = $user["status"] === "pending"
                ? "Tài khoản người bán đang chờ admin duyệt."
                : "Tài khoản này chưa được kích hoạt.";
        } else {
            $previousUserId = isset($_SESSION["user_id"]) ? (int)$_SESSION["user_id"] : null;
            $accountCart = [];
            $cartLoaded = load_user_cart($conn, (int)$user["id"], $accountCart);
            if (!$cartLoaded) {
                $loi = "Không thể tải giỏ hàng của tài khoản. Vui lòng thử lại.";
            } else {
                if ($previousUserId === null) {
                    foreach (normalize_cart($_SESSION["cart"] ?? []) as $bookId => $quantity) {
                        $accountCart[$bookId] = min(4294967295, ($accountCart[$bookId] ?? 0) + $quantity);
                    }
                    if (!save_user_cart($conn, (int)$user["id"], $accountCart)) {
                        $loi = "Không thể lưu giỏ hàng vào tài khoản. Vui lòng thử lại.";
                    }
                }

                if ($loi === "") {
                    session_regenerate_id(true);
                    unset($_SESSION["admin"]);
                    $_SESSION["user_id"] = (int)$user["id"];
                    $_SESSION["user_name"] = $user["full_name"];
                    $_SESSION["user_role"] = $user["role"];
                    $_SESSION["cart"] = $accountCart;
                    header("Location: " . ($user["role"] === "seller"
                        ? "seller/index.php"
                        : ($next === "checkout" ? "checkout.php" : "index.php")));
                    exit;
                }
            }
        }
    }
}
?>
<?php include __DIR__ . '/views/login.html';
