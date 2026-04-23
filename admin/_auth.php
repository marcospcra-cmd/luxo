<?php
require_once __DIR__ . '/../config.php';
if (empty($_SESSION['admin_id'])) {
    header('Location: ../login.php');
    exit;
}
if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}
