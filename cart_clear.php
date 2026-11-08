<?php
session_start();
if ($_SERVER["REQUEST_METHOD"] != "POST") { header("Location: cart.php"); exit; }

unset($_SESSION["cart"]);

header("Location: cart.php");
exit;
?>