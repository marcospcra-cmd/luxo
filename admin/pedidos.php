<?php
/**
 * admin/pedidos.php - Painel de gestão de pedidos
 * -----------------------------------------------------
 *  Lista todos os pedidos com status e detalhes do pagamento.
 * -----------------------------------------------------
 */
require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../config.php';

$page_title = 'Gerenciar Pedidos';
include __DIR__ . '/../includes/header.php';

// Busca todos os pedidos
$stmt = $pdo->prepare('
    SELECT 
        p.*,
        c.nome as cliente_nome,
        c.email as cliente_email,
        pr.nome as produto_nome,
        pr.imagem_url
    FROM pedidos p
    JOIN clientes c ON p.cliente_id = c.id
    JOIN produtos pr ON p.produto_id = pr.id
    ORDER BY p.criado_em DESC
');
$stmt->execute();
$pedidos = $stmt->fetchAll();
?>
<section class="container py-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="serif">Pedidos</h1>
        <a href="admin.php" class="btn btn-outline-gold">← Voltar ao Admin</a>
    </div>

    <?php if (empty($pedidos)): ?>
        <div class="alert alert-info">Nenhum pedido registrado ainda.</div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-dark table-hover align-middle">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Cliente</th>
                        <th>Produto</th>
                        <th>Valor</th>
                        <th>Status</th>
                        <th>Stripe Session</th>
                        <th>Data</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pedidos as $pedido): ?>
                        <tr>
                            <td>#<?= $pedido['id'] ?></td>
                            <td>
                                <?= htmlspecialchars($pedido['cliente_nome']) ?><br>
                                <small class="text-muted"><?= htmlspecialchars($pedido['cliente_email']) ?></small>
                            </td>
                            <td>
                                <img src="../<?= htmlspecialchars($pedido['imagem_url']) ?>" 
                                     style="width:50px;height:50px;object-fit:cover;border-radius:4px;" 
                                     class="me-2" alt="">
                                <?= htmlspecialchars($pedido['produto_nome']) ?>
                            </td>
                            <td>R$ <?= number_format((float)$pedido['total'], 2, ',', '.') ?></td>
                            <td>
                                <?php
                                $badge_class = match($pedido['status']) {
                                    'pago' => 'bg-success',
                                    'pendente' => 'bg-warning text-dark',
                                    'cancelado' => 'bg-danger',
                                    'reembolsado' => 'bg-secondary',
                                    default => 'bg-light text-dark'
                                };
                                ?>
                                <span class="badge <?= $badge_class ?>"><?= ucfirst($pedido['status']) ?></span>
                            </td>
                            <td>
                                <?php if ($pedido['stripe_session_id']): ?>
                                    <small class="text-muted"><?= substr($pedido['stripe_session_id'], 0, 20) ?>...</small>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td><?= date('d/m/Y H:i', strtotime($pedido['criado_em'])) ?></td>
                            <td>
                                <button type="button" 
                                        class="btn btn-sm btn-outline-info"
                                        data-bs-toggle="modal" 
                                        data-bs-target="#modalPedido<?= $pedido['id'] ?>">
                                    Detalhes
                                </button>
                            </td>
                        </tr>
                        
                        <!-- Modal Detalhes -->
                        <div class="modal fade" id="modalPedido<?= $pedido['id'] ?>" tabindex="-1">
                            <div class="modal-dialog modal-lg">
                                <div class="modal-content bg-dark border border-secondary">
                                    <div class="modal-header border-bottom border-secondary">
                                        <h5 class="modal-title serif">Pedido #<?= $pedido['id'] ?></h5>
                                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                    </div>
                                    <div class="modal-body">
                                        <div class="row">
                                            <div class="col-md-6 mb-3">
                                                <h6 class="text-gold">Cliente</h6>
                                                <p><strong>Nome:</strong> <?= htmlspecialchars($pedido['cliente_nome']) ?></p>
                                                <p><strong>Email:</strong> <?= htmlspecialchars($pedido['cliente_email']) ?></p>
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <h6 class="text-gold">Produto</h6>
                                                <p><strong>Nome:</strong> <?= htmlspecialchars($pedido['produto_nome']) ?></p>
                                                <p><strong>Valor:</strong> R$ <?= number_format((float)$pedido['total'], 2, ',', '.') ?></p>
                                            </div>
                                        </div>
                                        <hr class="border-secondary">
                                        <div class="row">
                                            <div class="col-md-6">
                                                <h6 class="text-gold">Pagamento</h6>
                                                <p><strong>Status:</strong> 
                                                    <span class="badge <?= $badge_class ?>"><?= ucfirst($pedido['status']) ?></span>
                                                </p>
                                                <?php if ($pedido['stripe_session_id']): ?>
                                                    <p><strong>Session ID:</strong> <code><?= htmlspecialchars($pedido['stripe_session_id']) ?></code></p>
                                                <?php endif; ?>
                                                <?php if ($pedido['stripe_payment_intent']): ?>
                                                    <p><strong>Payment Intent:</strong> <code><?= htmlspecialchars($pedido['stripe_payment_intent']) ?></code></p>
                                                <?php endif; ?>
                                            </div>
                                            <div class="col-md-6">
                                                <h6 class="text-gold">Datas</h6>
                                                <p><strong>Criado:</strong> <?= date('d/m/Y H:i:s', strtotime($pedido['criado_em'])) ?></p>
                                                <p><strong>Atualizado:</strong> <?= date('d/m/Y H:i:s', strtotime($pedido['atualizado_em'])) ?></p>
                                            </div>
                                        </div>
                                        
                                        <?php if ($pedido['status'] === 'pendente'): ?>
                                        <hr class="border-secondary">
                                        <form method="POST" action="pedido_atualizar.php" class="mt-3">
                                            <input type="hidden" name="pedido_id" value="<?= $pedido['id'] ?>">
                                            <label class="form-label">Alterar Status:</label>
                                            <div class="d-flex gap-2">
                                                <select name="status" class="form-select bg-dark text-light border-secondary" style="max-width:200px;">
                                                    <option value="pago" <?= $pedido['status'] === 'pago' ? 'selected' : '' ?>>Pago</option>
                                                    <option value="cancelado" <?= $pedido['status'] === 'cancelado' ? 'selected' : '' ?>>Cancelado</option>
                                                    <option value="reembolsado" <?= $pedido['status'] === 'reembolsado' ? 'selected' : '' ?>>Reembolsado</option>
                                                </select>
                                                <button type="submit" class="btn btn-gold">Atualizar</button>
                                            </div>
                                        </form>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
<?php include __DIR__ . '/../includes/footer.php'; ?>
