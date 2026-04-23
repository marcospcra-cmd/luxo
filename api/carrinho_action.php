<?php
session_start();
header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
$action = $data['action'] ?? '';
$cart = &$_SESSION['carrinho'];

if ($action === 'add') {
    $id = (int)$data['id'];
    $qty = (int)($data['qty'] ?? 1);
    $cart[$id] = ($cart[$id] ?? 0) + $qty;
    echo json_encode(['success' => true, 'total' => count($cart)]);
} elseif ($action === 'update') {
    $id = (int)$data['id'];
    $qty = (int)$data['qty'];
    if ($qty > 0) $cart[$id] = $qty;
    else unset($cart[$id]);
    echo json_encode(['success' => true]);
} elseif ($action === 'remove') {
    $id = (int)$data['id'];
    unset($cart[$id]);
    echo json_encode(['success' => true]);
} elseif ($action === 'clear') {
    $cart = [];
    echo json_encode(['success' => true]);
}
