<?php
function require_role(string $role): void
{
    if (($_SESSION["user_role"] ?? null) === $role && isset($_SESSION["user_id"])) {
        global $conn;

        $stmt = mysqli_prepare($conn, "SELECT status FROM users WHERE id = ? AND role = ?");
        $userId = (int)$_SESSION["user_id"];
        mysqli_stmt_bind_param($stmt, "is", $userId, $role);
        mysqli_stmt_execute($stmt);
        $user = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        if ($user && $user["status"] === "active") {
            return;
        }

        unset($_SESSION["user_id"], $_SESSION["user_name"], $_SESSION["user_role"]);
        session_regenerate_id(true);
    }

    $sellerPage = strpos($_SERVER["PHP_SELF"] ?? "", "/seller/") !== false;
    if (isset($_SESSION["user_id"])) {
        $destination = ($_SESSION["user_role"] ?? "") === "seller"
            ? ($sellerPage ? "index.php" : "seller/index.php")
            : ($sellerPage ? "../index.php" : "index.php");
        header("Location: " . $destination);
    } else {
        header("Location: " . ($sellerPage ? "../login.php" : "login.php"));
    }
    exit;
}

function csrf_token(): string
{
    if (empty($_SESSION["csrf_token"])) {
        $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
    }
    return $_SESSION["csrf_token"];
}

function verify_csrf_token(): bool
{
    $token = $_POST["csrf_token"] ?? "";
    return is_string($token)
        && isset($_SESSION["csrf_token"])
        && hash_equals($_SESSION["csrf_token"], $token);
}
