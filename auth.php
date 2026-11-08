<?php
function require_role(string $role): void
{
    if (($_SESSION["user_role"] ?? null) === $role) {
        return;
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
