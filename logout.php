<?php
session_start();
unset($_SESSION["user_id"], $_SESSION["user_name"], $_SESSION["user_role"]);
header("Location: index.php");
exit;
