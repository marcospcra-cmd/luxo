<?php
/**
 * admin/relatorios.php - Painel de relatórios e estatísticas
 * -----------------------------------------------------
 *  Relatórios de vendas, produtos mais vendidos, clientes, etc.
 *  Acesso restrito a administradores.
 * -----------------------------------------------------
 */
require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../config.php';

$page_title = 'Relatórios';
include __DIR__ . '/../includes/header.php';

// Filtros de período
$periodo = $_GET['periodo'] ?? '30';
$data_inicio = date('Y-m-d', strtotime("-{$periodo} days"));
$data_fim = date('Y-m-d');

// Estatísticas gerais
$stmt = $pdo->query('SELECT COUNT(*) FROM pedidos WHERE status = "pago"');
$total_pedidos_pagos = (int)$stmt->fetchColumn();

$stmt = $pdo->query('SELECT SUM(total) FROM pedidos WHERE status = "pago"');
$receita_total = (float)($stmt->fetchColumn() ?? 0);

$stmt = $pdo->query('SELECT COUNT(DISTINCT cliente_id) FROM pedidos WHERE status = "pago"');
$clientes_unicos = (int)$stmt->fetchColumn();

$stmt = $pdo->query('SELECT AVG(total) FROM pedidos WHERE status = "pago"');
$ticket_medio = (float)($stmt->fetchColumn() ?? 0);

// Pedidos por status
$stmt = $pdo->query('
    SELECT status, COUNT(*) as total, SUM(total) as valor 
    FROM pedidos 
    GROUP BY status
');
$pedidos_por_status = $stmt->fetchAll();

// Produtos mais vendidos (com filtro de período)
$stmt = $pdo->prepare('
    SELECT 
        p.id,
        p.nome,
        p.categoria,
        p.imagem_url,
        COUNT(pe.id) as total_vendas,
        SUM(pe.total) as receita_gerada
    FROM produtos p
    JOIN pedidos pe ON p.id = pe.produto_id
    WHERE pe.status = "pago" AND DATE(pe.criado_em) >= :inicio AND DATE(pe.criado_em) <= :fim
    GROUP BY p.id
    ORDER BY total_vendas DESC
    LIMIT 10
');
$stmt->execute([':inicio' => $data_inicio, ':fim' => $data_fim]);
$produtos_mais_vendidos = $stmt->fetchAll();

// Vendas por dia (últimos dias do período)
$stmt = $pdo->prepare('
    SELECT 
        DATE(criado_em) as data,
        COUNT(*) as total_pedidos,
        SUM(total) as total_vendas
    FROM pedidos
    WHERE status = "pago" AND DATE(criado_em) >= :inicio AND DATE(criado_em) <= :fim
    GROUP BY DATE(criado_em)
    ORDER BY data DESC
');
$stmt->execute([':inicio' => $data_inicio, ':fim' => $data_fim]);
$vendas_por_dia = $stmt->fetchAll();

// Clientes que mais compraram
$stmt = $pdo->query('
    SELECT 
        c.id,
        c.nome,
        c.email,
        COUNT(p.id) as total_pedidos,
        SUM(p.total) as total_gasto
    FROM clientes c
    JOIN pedidos p ON c.id = p.cliente_id
    WHERE p.status = "pago"
    GROUP BY c.id
    ORDER BY total_gasto DESC
    LIMIT 10
');
$clientes_top = $stmt->fetchAll();
?>
<section class="container py-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="serif">📊 Relatórios e Estatísticas</h1>
        <a href="admin.php" class="btn btn-outline-gold">← Voltar ao Admin</a>
    </div>

    <!-- Filtro de Período -->
    <div class="card bg-dark border border-secondary mb-4">
        <div class="card-body">
            <form method="GET" class="d-flex gap-2 align-items-end flex-wrap">
                <div>
                    <label class="form-label">Período:</label>
                    <select name="periodo" class="form-select bg-dark text-light border-secondary" style="max-width:200px;">
                        <option value="7" <?= $periodo == '7' ? 'selected' : '' ?>>Últimos 7 dias</option>
                        <option value="30" <?= $periodo == '30' ? 'selected' : '' ?>>Últimos 30 dias</option>
                        <option value="90" <?= $periodo == '90' ? 'selected' : '' ?>>Últimos 90 dias</option>
                        <option value="365" <?= $periodo == '365' ? 'selected' : '' ?>>Último ano</option>
                    </select>
                </div>
                <button type="submit" class="btn btn-gold">Filtrar</button>
            </form>
            <small class="text-muted">Período selecionado: <?= date('d/m/Y', strtotime($data_inicio)) ?> até <?= date('d/m/Y', strtotime($data_fim)) ?></small>
        </div>
    </div>

    <!-- Cards de Estatísticas -->
    <div class="row g-4 mb-4">
        <div class="col-md-3">
            <div class="card bg-dark border border-secondary h-100">
                <div class="card-body text-center">
                    <h2 class="text-gold display-4"><?= $total_pedidos_pagos ?></h2>
                    <p class="mb-0">Pedidos Pagos</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-dark border border-secondary h-100">
                <div class="card-body text-center">
                    <h2 class="text-gold display-4">R$ <?= number_format($receita_total, 2, ',', '.') ?></h2>
                    <p class="mb-0">Receita Total</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-dark border border-secondary h-100">
                <div class="card-body text-center">
                    <h2 class="text-gold display-4"><?= $clientes_unicos ?></h2>
                    <p class="mb-0">Clientes Únicos</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-dark border border-secondary h-100">
                <div class="card-body text-center">
                    <h2 class="text-gold display-4">R$ <?= number_format($ticket_medio, 2, ',', '.') ?></h2>
                    <p class="mb-0">Ticket Médio</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Pedidos por Status -->
    <div class="card bg-dark border border-secondary mb-4">
        <div class="card-header border-bottom border-secondary">
            <h5 class="mb-0 text-gold">📦 Pedidos por Status</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-dark table-hover">
                    <thead>
                        <tr>
                            <th>Status</th>
                            <th>Total de Pedidos</th>
                            <th>Valor Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pedidos_por_status as $status): ?>
                            <tr>
                                <td><?= ucfirst($status['status']) ?></td>
                                <td><?= $status['total'] ?></td>
                                <td>R$ <?= number_format($status['valor'] ?? 0, 2, ',', '.') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Produtos Mais Vendidos -->
    <div class="card bg-dark border border-secondary mb-4">
        <div class="card-header border-bottom border-secondary">
            <h5 class="mb-0 text-gold">🏆 Produtos Mais Vendidos (Período)</h5>
        </div>
        <div class="card-body">
            <?php if (empty($produtos_mais_vendidos)): ?>
                <p class="text-muted">Nenhuma venda registrada neste período.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-dark table-hover align-middle">
                        <thead>
                            <tr>
                                <th>Produto</th>
                                <th>Categoria</th>
                                <th>Vendas</th>
                                <th>Receita Gerada</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($produtos_mais_vendidos as $prod): ?>
                                <tr>
                                    <td>
                                        <img src="../<?= htmlspecialchars($prod['imagem_url']) ?>" 
                                             style="width:40px;height:40px;object-fit:cover;border-radius:4px;" 
                                             class="me-2" alt="">
                                        <?= htmlspecialchars($prod['nome']) ?>
                                    </td>
                                    <td><?= htmlspecialchars($prod['categoria']) ?></td>
                                    <td><span class="badge bg-success"><?= $prod['total_vendas'] ?></span></td>
                                    <td>R$ <?= number_format($prod['receita_gerada'], 2, ',', '.') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Vendas por Dia -->
    <div class="card bg-dark border border-secondary mb-4">
        <div class="card-header border-bottom border-secondary">
            <h5 class="mb-0 text-gold">📈 Vendas por Dia (Período)</h5>
        </div>
        <div class="card-body">
            <?php if (empty($vendas_por_dia)): ?>
                <p class="text-muted">Nenhuma venda registrada neste período.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-dark table-hover">
                        <thead>
                            <tr>
                                <th>Data</th>
                                <th>Pedidos</th>
                                <th>Total Vendido</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($vendas_por_dia as $dia): ?>
                                <tr>
                                    <td><?= date('d/m/Y', strtotime($dia['data'])) ?></td>
                                    <td><?= $dia['total_pedidos'] ?></td>
                                    <td>R$ <?= number_format($dia['total_vendas'], 2, ',', '.') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Top Clientes -->
    <div class="card bg-dark border border-secondary mb-4">
        <div class="card-header border-bottom border-secondary">
            <h5 class="mb-0 text-gold">👥 Top 10 Clientes (Maior Gasto)</h5>
        </div>
        <div class="card-body">
            <?php if (empty($clientes_top)): ?>
                <p class="text-muted">Nenhum cliente com compras registradas.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-dark table-hover">
                        <thead>
                            <tr>
                                <th>Cliente</th>
                                <th>Email</th>
                                <th>Pedidos</th>
                                <th>Total Gasto</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($clientes_top as $cliente): ?>
                                <tr>
                                    <td><?= htmlspecialchars($cliente['nome']) ?></td>
                                    <td><?= htmlspecialchars($cliente['email']) ?></td>
                                    <td><?= $cliente['total_pedidos'] ?></td>
                                    <td><span class="badge bg-gold" style="background-color: var(--color-gold); color: var(--color-bg-primary);">R$ <?= number_format($cliente['total_gasto'], 2, ',', '.') ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>
<?php include __DIR__ . '/../includes/footer.php'; ?>
