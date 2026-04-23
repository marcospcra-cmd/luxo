<?php
/**
 * cancel.php - Página de cancelamento de pagamento
 * -----------------------------------------------------
 *  Exibe mensagem quando cliente cancela o checkout.
 * -----------------------------------------------------
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/cliente_auth.php';

$pedido_id = $_GET['pedido_id'] ?? 0;

// Verifica se cliente está logado
if (!cliente_logado()) {
    header('Location: ../cliente_login.php');
    exit;
}

// Atualiza pedido para cancelado se existir
if ($pedido_id > 0) {
    $stmt = $pdo->prepare('SELECT * FROM pedidos WHERE id = :id AND cliente_id = :cliente_id AND status = :status');
    $stmt->execute([
        ':id' => $pedido_id,
        ':cliente_id' => $_SESSION['cliente_id'],
        ':status' => 'pendente'
    ]);
    $pedido = $stmt->fetch();
    
    if ($pedido) {
        // Marca como cancelado
        $update = $pdo->prepare('UPDATE pedidos SET status = :status WHERE id = :id');
        $update->execute([
            ':status' => 'cancelado',
            ':id' => $pedido_id
        ]);
    }
}

$page_title = 'Pagamento Cancelado';
include __DIR__ . '/../includes/header.php';
?>
<section class="container py-5 text-center">
    <div class="row justify-content-center">
        <div class="col-lg-6">
            <div style="font-size:5rem;color:#dc3545;">✕</div>
            <h1 class="serif mb-3">Compra Cancelada</h1>
            <p class="lead mb-4">Seu pagamento não foi finalizado.</p>
            
            <?php if ($pedido_id > 0): ?>
                <div class="card bg-dark bg-opacity-10 p-4 mb-4">
                    <p class="mb-2">Pedido #<?= $pedido_id ?> foi cancelado.</p>
                    <p class="small text-muted mb-0">O produto continua disponível em nosso catálogo.</p>
                </div>
            <?php endif; ?>
            
            <p class="mb-4">Deseja tentar novamente ou continuar navegando?</p>
            
            <div class="mt-4">
                <a href="../index.php" class="btn btn-gold me-2">Continuar Comprando</a>
                <a href="../favoritos.php" class="btn btn-outline-gold">Meus Favoritos</a>
            </div>
        </div>
    </div>
</section>
<?php include __DIR__ . '/../includes/footer.php'; ?>
