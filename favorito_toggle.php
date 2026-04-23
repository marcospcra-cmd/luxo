<?php
/**
 * favorito_toggle.php — endpoint AJAX (e fallback POST)
 *  Adiciona/remove um produto dos favoritos do cliente logado.
 *  Resposta JSON: {ok, favorito, total, msg?}
 */
require_once __DIR__ . '/includes/cliente_auth.php';
header('Content-Type: application/json; charset=utf-8');

function json_out(array $data, int $code = 200): void {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_out(['ok' => false, 'msg' => 'Método inválido.'], 405);
}
if (!cliente_logado()) {
    json_out(['ok' => false, 'msg' => 'Faça login para favoritar.', 'precisa_login' => true], 401);
}
if (!csrf_cliente_valido($_POST['csrf'] ?? '')) {
    json_out(['ok' => false, 'msg' => 'Token inválido.'], 403);
}

$produto_id = (int)($_POST['produto_id'] ?? 0);
if ($produto_id <= 0) json_out(['ok' => false, 'msg' => 'Produto inválido.'], 400);

// Confirma que o produto existe
$check = $pdo->prepare('SELECT id FROM produtos WHERE id = :id');
$check->execute([':id' => $produto_id]);
if (!$check->fetchColumn()) json_out(['ok' => false, 'msg' => 'Produto não encontrado.'], 404);

$cid = cliente_id();

// Existe já?
$s = $pdo->prepare('SELECT id FROM favoritos WHERE cliente_id = :c AND produto_id = :p');
$s->execute([':c' => $cid, ':p' => $produto_id]);
$existe = $s->fetchColumn();

if ($existe) {
    $del = $pdo->prepare('DELETE FROM favoritos WHERE id = :id');
    $del->execute([':id' => $existe]);
    $favorito = false;
} else {
    $ins = $pdo->prepare('INSERT INTO favoritos (cliente_id, produto_id) VALUES (:c, :p)');
    $ins->execute([':c' => $cid, ':p' => $produto_id]);
    $favorito = true;
}

// Total atual
$tot = $pdo->prepare('SELECT COUNT(*) FROM favoritos WHERE cliente_id = :c');
$tot->execute([':c' => $cid]);
$total = (int)$tot->fetchColumn();

json_out(['ok' => true, 'favorito' => $favorito, 'total' => $total]);
