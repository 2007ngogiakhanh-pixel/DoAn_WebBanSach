<?php
session_start();
require "db.php";

$bookId = (int)($_GET["id"] ?? 0);
$stmt = mysqli_prepare($conn, "SELECT seller_id, image, pending_image, approval_status FROM books WHERE id = ?");
mysqli_stmt_bind_param($stmt, "i", $bookId);
mysqli_stmt_execute($stmt);
$book = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
if (!$book) {
    http_response_code(404);
    exit;
}

$isAdmin = isset($_SESSION["admin"]);
$isOwner = isset($_SESSION["user_id"])
    && ($_SESSION["user_role"] ?? null) === "seller"
    && (int)$_SESSION["user_id"] === (int)$book["seller_id"];
if (!$isAdmin && !$isOwner) {
    http_response_code(403);
    exit;
}

$imageName = $book["pending_image"] ?: $book["image"];
if (!is_string($imageName) || !preg_match('/\A[a-f0-9]{32}\.(jpg|png|webp)\z/', $imageName)) {
    http_response_code(404);
    exit;
}

$directory = $book["pending_image"] ? "pending_uploads" : "uploads";
$path = __DIR__ . "/" . $directory . "/" . $imageName;
if (!is_file($path)) {
    http_response_code(404);
    exit;
}

$mimeTypes = ["jpg" => "image/jpeg", "png" => "image/png", "webp" => "image/webp"];
$extension = pathinfo($imageName, PATHINFO_EXTENSION);
header("Content-Type: " . $mimeTypes[$extension]);
header("X-Content-Type-Options: nosniff");
header("Cache-Control: private, no-store");
readfile($path);
