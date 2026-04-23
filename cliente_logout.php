<?php
/**
 * cliente_logout.php — encerra apenas a sessão do cliente,
 * preservando dados não-relacionados (ex.: tema).
 */
require_once __DIR__ . '/includes/cliente_auth.php';
unset($_SESSION['cliente_id'], $_SESSION['cliente_nome']);
session_regenerate_id(true);
header('Location: index.php');
exit;
