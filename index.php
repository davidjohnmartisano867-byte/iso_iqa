<?php

session_start();

if (isset($_SESSION['user_id'])) {
    header("Location: pages/dashboard/index.php");
    exit;
}

header("Location: pages/auth/login.php");
exit;

