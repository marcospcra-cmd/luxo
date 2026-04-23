<?php
/**
 * admin/admin.php — Painel CRUD Solar Amazônia
 * Funções ampliadas: Dashboard, Produtos, Pedidos, Clientes, Cupons, Relatórios
 */
require_once __DIR__ . '/_auth.php';
$page_title = 'Painel administrativo';

$msg = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

// Estatísticas do dashboard
$stmt = $pdo->query('SELECT COUNT(*) FROM produtos');
$totalProdutos = (int)$stmt->fetchColumn();

$stmt = $pdo->query('SELECT COUNT(*) FROM pedidos');
$totalPedidos = (int)$stmt->fetchColumn();

$stmt = $pdo->query('SELECT COUNT(*) FROM clientes');
$totalClientes = (int)$stmt->fetchColumn();

$stmt = $pdo->query('SELECT SUM(total) FROM pedidos WHERE status != "cancelado"');
$receitaTotal = (float)($stmt->fetchColumn() ?? 0);

$stmt = $pdo->query('SELECT COUNT(*) FROM produtos WHERE estoque <= 3');
$produtosBaixoEstoque = (int)$stmt->fetchColumn();

$produtos = $pdo->query('SELECT * FROM produtos ORDER BY criado_em DESC')->fetchAll();

// Header simples (caminho relativo ../includes)
$tema = $TEMA_ATUAL;
?>
<!DOCTYPE html>
<html lang="pt-BR" data-bs-theme="<?= $tema==='dark'?'dark':'light' ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= $page_title ?> | Solar Amazônia</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@400;600;700&family=Inter:wght@300;400;500&display=swap" rel="stylesheet">
<link href="../assets/css/style.css" rel="stylesheet">
<style>
    .dashboard-cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.5rem; margin-bottom: 2rem; }
    .dash-card { background: var(--color-bg-card); border-radius: var(--radius-lg); padding: 1.5rem; border: 1px solid var(--color-border-light); transition: transform var(--transition-fast), box-shadow var(--transition-fast); }
    .dash-card:hover { transform: translateY(-4px); box-shadow: var(--shadow-lg); }
    .dash-card-icon { font-size: 2rem; margin-bottom: 0.5rem; }
    .dash-card-value { font-family: var(--font-heading); font-size: 2rem; font-weight: 700; color: var(--color-gold); }
    .dash-card-label { font-size: 0.85rem; color: var(--color-text-secondary); text-transform: uppercase; letter-spacing: 0.05em; }
    .dash-card.alert-low { border-color: #dc3545; background: rgba(220, 53, 69, 0.1); }
    .admin-tabs { display: flex; gap: 0.5rem; flex-wrap: wrap; margin-bottom: 2rem; }
    .admin-tab { padding: 0.75rem 1.5rem; background: var(--color-surface); border: 1px solid var(--color-border-light); border-radius: var(--radius-md); color: var(--color-text-secondary); text-decoration: none; transition: all var(--transition-fast); font-weight: 500; }
    .admin-tab:hover, .admin-tab.active { background: var(--color-gold); color: var(--color-bg-primary); border-color: var(--color-gold); }
    @media (max-width: 768px) { .dashboard-cards { grid-template-columns: repeat(2, 1fr); } }
</style>
</head>
<body class="theme-<?= $tema ?>">
<nav class="navbar site-nav sticky-top">
  <div class="container">
    <a class="brand" href="admin.php">
      <span class="brand-mark">S</span>
      <span class="brand-word">Solar<em>Amazônia</em> Admin</span>
    </a>
    <div class="d-flex gap-2">
      <a class="btn btn-outline-gold btn-sm" href="../index.php" target="_blank">Ver loja</a>
      <a class="btn btn-gold btn-sm" href="../logout.php">Sair</a>
    </div>
  </div>
</nav>

<main class="container py-5">
  <div class="dashboard-cards">
    <div class="dash-card"><div class="dash-card-icon">📦</div><div class="dash-card-value"><?= $totalProdutos ?></div><div class="dash-card-label">Produtos</div></div>
    <div class="dash-card <?= $produtosBaixoEstoque > 0 ? 'alert-low' : '' ?>"><div class="dash-card-icon">⚠️</div><div class="dash-card-value"><?= $produtosBaixoEstoque ?></div><div class="dash-card-label">Baixo Estoque</div></div>
    <div class="dash-card"><div class="dash-card-icon">🛒</div><div class="dash-card-value"><?= $totalPedidos ?></div><div class="dash-card-label">Pedidos</div></div>
    <div class="dash-card"><div class="dash-card-icon">👥</div><div class="dash-card-value"><?= $totalClientes ?></div><div class="dash-card-label">Clientes</div></div>
    <div class="dash-card"><div class="dash-card-icon">💰</div><div class="dash-card-value">R$ <?= number_format($receitaTotal, 2, ',', '.') ?></div><div class="dash-card-label">Receita Total</div></div>
  </div>

  <div class="admin-tabs">
    <a href="admin.php" class="admin-tab active">📦 Produtos</a>
    <a href="pedidos.php" class="admin-tab">🛒 Pedidos</a>
    <a href="produto_form.php" class="admin-tab">➕ Novo Produto</a>
    <a href="categorias.php" class="admin-tab">📂 Categorias</a>
    <a href="clientes.php" class="admin-tab">👥 Clientes</a>
    <a href="cupons.php" class="admin-tab">🏷️ Cupons</a>
    <a href="relatorios.php" class="admin-tab">📊 Relatórios</a>
  </div>

  <?php if ($msg): ?>
    <div class="alert alert-success small"><?= htmlspecialchars($msg) ?></div>
  <?php endif; ?>

  <div class="d-flex justify-content-between align-items-end flex-wrap gap-3 mb-4">
    <div>
      <p class="eyebrow small text-uppercase mb-1" style="color:var(--accent);letter-spacing:.4em;">Gestão</p>
      <h2 class="serif mb-0">Produtos Cadastrados</h2>
    </div>
    <div class="d-flex gap-2">
      <a href="produto_form.php" class="btn btn-gold">+ Novo produto</a>
    </div>
  </div>

  <div class="table-responsive">
    <table class="table admin-table align-middle">
      <thead><tr><th>Foto</th><th>Cód. Registro</th><th>Nome</th><th>Categoria</th><th>Preço</th><th>Estoque</th><th>Vídeo</th><th class="text-end">Ações</th></tr></thead>
      <tbody>
        <?php foreach ($produtos as $p): ?>
        <tr>
          <td><img class="admin-thumb" src="<?= htmlspecialchars('../'.$p['imagem_url']) ?>" onerror="this.src='../assets/placeholder.svg'" style="width:50px;height:50px;object-fit:cover;border-radius:var(--radius-sm);"></td>
          <td><code><?= htmlspecialchars($p['codigo_registro'] ?? '—') ?></code></td>
          <td><?= htmlspecialchars($p['nome']) ?></td>
          <td><span class="product-cat"><?= htmlspecialchars($p['categoria']) ?></span></td>
          <td>R$ <?= number_format((float)$p['preco'],2,',','.') ?></td>
          <td>
            <?php $est = (int)$p['estoque']; ?>
            <?php if ($est <= 0): ?><span class="badge bg-danger">Esgotado</span>
            <?php elseif ($est <= 3): ?><span class="badge bg-warning text-dark"><?= $est ?> · baixo</span>
            <?php else: ?><span class="badge bg-success"><?= $est ?></span><?php endif; ?>
          </td>
          <td>
            <?php if (!empty($p['video_url'])): ?><span class="badge bg-info">✓ Vídeo</span><?php endif; ?>
            <?php if (!empty($p['video_destaque'])): ?><span class="badge bg-primary">Destaque</span><?php endif; ?>
          </td>
          <td class="text-end">
            <a href="produto_form.php?id=<?= (int)$p['id'] ?>" class="btn btn-sm btn-outline-gold">Editar</a>
            <form method="post" action="produto_excluir.php" class="d-inline" onsubmit="return confirm('Excluir esta peça?');">
              <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf']) ?>">
              <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
              <button class="btn btn-sm btn-outline-danger">Excluir</button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($produtos)): ?>
          <tr><td colspan="8" class="text-center text-muted py-4">Nenhum produto cadastrado.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</main>
</body>
</html>
