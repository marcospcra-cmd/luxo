<?php
/**
 * success.php - Página de sucesso após pagamento
 * -----------------------------------------------------
 *  Confirma o pagamento e atualiza o estoque do produto.
 * -----------------------------------------------------
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/cliente_auth.php';

$session_id = $_GET['session_id'] ?? '';
$pedido_id = $_GET['pedido_id'] ?? 0;

if (!$session_id || !$pedido_id) {
    header('Location: ../index.php');
    exit;
}

// Verifica se cliente está logado
if (!cliente_logado()) {
    header('Location: ../cliente_login.php');
    exit;
}

try {
    // Busca dados do pedido
    $stmt = $pdo->prepare('SELECT p.*, pr.nome as produto_nome, pr.preco FROM pedidos p JOIN produtos pr ON p.produto_id = pr.id WHERE p.id = :id AND p.cliente_id = :cliente_id');
    $stmt->execute([
        ':id' => $pedido_id,
        ':cliente_id' => $_SESSION['cliente_id']
    ]);
    $pedido = $stmt->fetch();
    
    if (!$pedido) {
        header('Location: ../index.php');
        exit;
    }
    
    // Verifica se já foi processado
    if ($pedido['status'] === 'pago') {
        $ja_pago = true;
    } else {
        // Valida com Stripe se SDK disponível
        if (class_exists('\\Stripe\\Stripe')) {
            \Stripe\Stripe::setApiKey(STRIPE_SECRET_KEY);
            $session = \Stripe\Checkout\Session::retrieve($session_id);
            
            if ($session->payment_status === 'paid') {
                // Atualiza pedido para pago
                $update = $pdo->prepare('UPDATE pedidos SET status = :status, stripe_payment_intent = :pi WHERE id = :id');
                $update->execute([
                    ':status' => 'pago',
                    ':pi' => $session->payment_intent,
                    ':id' => $pedido_id
                ]);
                
                // Decrementa estoque
                $estoque = $pdo->prepare('UPDATE produtos SET estoque = estoque - 1 WHERE id = :id AND estoque > 0');
                $estoque->execute([':id' => $pedido['produto_id']]);
                
                $ja_pago = true;
            } else {
                $ja_pago = false;
            }
        } else {
            // Modo demo - assume pagamento aprovado
            $update = $pdo->prepare('UPDATE pedidos SET status = :status WHERE id = :id');
            $update->execute([
                ':status' => 'pago',
                ':id' => $pedido_id
            ]);
            
            // Decrementa estoque
            $estoque = $pdo->prepare('UPDATE produtos SET estoque = estoque - 1 WHERE id = :id AND estoque > 0');
            $estoque->execute([':id' => $pedido['produto_id']]);
            
            $ja_pago = true;
        }
    }
    
} catch (Exception $e) {
    error_log('Stripe Success Error: ' . $e->getMessage());
    $ja_pago = false;
}

$page_title = 'Pagamento ' . ($ja_pago ? 'Aprovado' : 'Pendente');
include __DIR__ . '/../includes/header.php';
?>
<section class="container py-5 text-center">
    <div class="row justify-content-center">
        <div class="col-lg-6">
            <?php if ($ja_pago): ?>
                <div style="font-size:5rem;color:#28a745;">✓</div>
                <h1 class="serif mb-3">Pagamento Aprovado!</h1>
                <p class="lead mb-4">Obrigado pela sua compra, <strong><?= htmlspecialchars($_SESSION['cliente_nome']) ?></strong>.</p>
                
                <div class="card bg-dark bg-opacity-10 p-4 mb-4">
                    <h5 class="serif mb-3">Resumo do Pedido #<?= $pedido_id ?></h5>
                    <p class="mb-1"><strong>Produto:</strong> <?= htmlspecialchars($pedido['produto_nome']) ?></p>
                    <p class="mb-1"><strong>Valor Pago:</strong> R$ <?= number_format((float)$pedido['preco'], 2, ',', '.') ?></p>
                    <p class="mb-0"><strong>Status:</strong> <span class="badge bg-success">Pago</span></p>
                </div>
                
                <p class="text-muted mb-4">Enviamos os detalhes da compra para o seu e-mail.</p>
                <p class="small text-muted">Em breve entraremos em contato para combinar a entrega.</p>
                
                <div class="mt-4">
                    <a href="../index.php" class="btn btn-gold me-2">Continuar Comprando</a>
                    <a href="../favoritos.php" class="btn btn-outline-gold">Meus Favoritos</a>
                </div>
            <?php else: ?>
                <div style="font-size:5rem;color:#ffc107;">⏳</div>
                <h1 class="serif mb-3">Verificando Pagamento</h1>
                <p class="lead mb-4">Seu pagamento está sendo processado. Por favor, aguarde.</p>
                <div class="spinner-border text-gold" role="status">
                    <span class="visually-hidden">Carregando...</span>
                </div>
                <p class="mt-3 small text-muted">Recarregue a página em alguns instantes.</p>
            <?php endif; ?>
        </div>
    </div>
</section>
<?php include __DIR__ . '/../includes/footer.php'; ?>
