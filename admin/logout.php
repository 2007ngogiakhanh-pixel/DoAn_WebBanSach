<?php
session_start();
unset($_SESSION["admin"], $_SESSION["user_id"], $_SESSION["user_name"], $_SESSION["user_role"]);
session_regenerate_id(true);
header("Location: ../login.php");
exit;
?>