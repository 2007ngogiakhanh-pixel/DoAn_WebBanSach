<?php
session_start();
if ($_SERVER["REQUEST_METHOD"] != "POST") { header("Location: cart.php"); exit; }

$dsQty = $_POST["qty"] ?? [];
foreach ($dsQty as $id => $qty) {
    $id  = (int)$id;
    $qty = (int)$qty;
    if (!isset($_SESSION["cart"][$id])) continue;

    if ($qty <= 0) {
        unset($_SESSION["cart"][$id]);
    } else {
        $_SESSION["cart"][$id] = $qty;
    }
}
header("Location: cart.php");
exit;
?>