<?php
function normalize_cart($cart): array
{
    if (!is_array($cart)) {
        return [];
    }

    $normalized = [];
    foreach ($cart as $bookId => $quantity) {
        $bookId = filter_var($bookId, FILTER_VALIDATE_INT);
        $quantity = filter_var($quantity, FILTER_VALIDATE_INT);
        if ($bookId !== false && $bookId > 0 && $quantity !== false && $quantity > 0) {
            $normalized[$bookId] = min($quantity, 4294967295);
        }
    }

    return $normalized;
}

function load_user_cart(mysqli $conn, int $userId, array &$cart): bool
{
    $stmt = mysqli_prepare($conn, "SELECT book_id, quantity FROM user_cart_items WHERE user_id = ?");
    if ($stmt === false) {
        return false;
    }

    mysqli_stmt_bind_param($stmt, "i", $userId);
    if (!mysqli_stmt_execute($stmt)) {
        return false;
    }

    $result = mysqli_stmt_get_result($stmt);
    if ($result === false) {
        return false;
    }
    $cart = [];
    while ($item = mysqli_fetch_assoc($result)) {
        $cart[(int)$item["book_id"]] = (int)$item["quantity"];
    }

    return true;
}

function save_user_cart(mysqli $conn, int $userId, $cart): bool
{
    $cart = normalize_cart($cart);
    if ($cart !== []) {
        $bookIds = implode(",", array_map("intval", array_keys($cart)));
        $books = mysqli_query($conn, "SELECT id FROM books WHERE id IN ($bookIds)");
        if ($books === false) {
            return false;
        }
        $existingBooks = [];
        while ($book = mysqli_fetch_assoc($books)) {
            $existingBooks[(int)$book["id"]] = true;
        }
        $cart = array_intersect_key($cart, $existingBooks);
    }

    if (!mysqli_begin_transaction($conn)) {
        return false;
    }

    $delete = mysqli_prepare($conn, "DELETE FROM user_cart_items WHERE user_id = ?");
    if ($delete === false) {
        mysqli_rollback($conn);
        return false;
    }
    mysqli_stmt_bind_param($delete, "i", $userId);
    if (!mysqli_stmt_execute($delete)) {
        mysqli_rollback($conn);
        return false;
    }

    $insert = mysqli_prepare($conn, "INSERT INTO user_cart_items (user_id, book_id, quantity) VALUES (?, ?, ?)");
    if ($insert === false) {
        mysqli_rollback($conn);
        return false;
    }
    foreach ($cart as $bookId => $quantity) {
        mysqli_stmt_bind_param($insert, "iii", $userId, $bookId, $quantity);
        if (!mysqli_stmt_execute($insert)) {
            mysqli_rollback($conn);
            return false;
        }
    }

    if (!mysqli_commit($conn)) {
        mysqli_rollback($conn);
        return false;
    }

    return true;
}

function sync_session_cart(mysqli $conn): bool
{
    if (!isset($_SESSION["user_id"])) {
        return true;
    }

    return save_user_cart($conn, (int)$_SESSION["user_id"], $_SESSION["cart"] ?? []);
}
