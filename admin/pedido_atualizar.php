<?php
/**
 * admin/pedido_atualizar.php - Atualiza status do pedido
 * -----------------------------------------------------
 *  Permite ao admin alterar manualmente o status de um pedido.
 * -----------------------------------------------------
 */
require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: pedidos.php');
    exit;
}

$pedido_id = isset($_POST['pedido_id']) ? (int)$_POST['pedido_id'] : 0;
$novo_status = $_POST['status'] ?? '';

$status_validos = ['pendente', 'pago', 'cancelado', 'reembolsado'];

if ($pedido_id <= 0 || !in_array($novo_status, $status_validos)) {
    $_SESSION['admin_msg'] = 'Dados inválidos.';
    $_SESSION['admin_msg_type'] = 'danger';
    header('Location: pedidos.php');
    exit;
}

try {
    // Atualiza status
    $update = $pdo->prepare('UPDATE pedidos SET status = :status WHERE id = :id');
    $update->execute([
        ':status' => $novo_status,
        ':id' => $pedido_id
    ]);
    
    // Se marcado como pago e não tinha baixa no estoque, decrementa
    if ($novo_status === 'pago') {
        $stmt = $pdo->prepare('SELECT produto_id FROM pedidos WHERE id = :id');
        $stmt->execute([':id' => $pedido_id]);
        $pedido = $stmt->fetch();
        
        if ($pedido) {
            $estoque = $pdo->prepare('UPDATE produtos SET estoque = estoque - 1 WHERE id = :id AND estoque > 0');
            $estoque->execute([':id' => $pedido['produto_id']]);
        }
    }
    
    $_SESSION['admin_msg'] = 'Status do pedido atualizado com sucesso!';
    $_SESSION['admin_msg_type'] = 'success';
    
} catch (Exception $e) {
    error_log('Erro ao atualizar pedido: ' . $e->getMessage());
    $_SESSION['admin_msg'] = 'Erro ao atualizar status: ' . $e->getMessage();
    $_SESSION['admin_msg_type'] = 'danger';
}

header('Location: pedidos.php');
exit;
