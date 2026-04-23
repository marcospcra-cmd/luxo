<?php
/**
 * admin/admin.php — Painel CRUD
 */
require_once __DIR__ . '/_auth.php';
$page_title = 'Painel administrativo';

$msg = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

$produtos = $pdo->query('SELECT * FROM produtos ORDER BY criado_em DESC')->fetchAll();

// Header simples (caminho relativo ../includes)
$tema = $TEMA_ATUAL;
?>
<!DOCTYPE html>
<html lang="pt-BR" data-bs-theme="<?= $tema==='dark'?'dark':'light' ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= $page_title ?> | Maison de Luxo</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@400;600;700&family=Inter:wght@300;400;500&display=swap" rel="stylesheet">
<link href="../assets/css/style.css" rel="stylesheet">
</head>
<body class="theme-<?= $tema ?>">
<nav class="navbar site-nav sticky-top">
  <div class="container">
    <a class="brand" href="admin.php">
      <span class="brand-mark">A</span>
      <span class="brand-word">Painel<em>Administrativo</em></span>
    </a>
    <div class="d-flex gap-2">
      <a class="btn btn-outline-gold btn-sm" href="../index.php" target="_blank">Ver loja</a>
      <a class="btn btn-gold btn-sm" href="../logout.php">Sair</a>
    </div>
  </div>
</nav>

<main class="container py-5">
  <div class="d-flex justify-content-between align-items-end flex-wrap gap-3 mb-4">
    <div>
      <p class="eyebrow small text-uppercase mb-1" style="color:var(--accent);letter-spacing:.4em;">Gestão</p>
      <h2 class="serif mb-0">Produtos</h2>
    </div>
    <a href="produto_form.php" class="btn btn-gold">+ Novo produto</a>
  </div>

  <?php if ($msg): ?>
    <div class="alert alert-success small"><?= htmlspecialchars($msg) ?></div>
  <?php endif; ?>

  <div class="table-responsive">
    <table class="table admin-table align-middle">
      <thead>
        <tr>
          <th>Foto</th><th>Nome</th><th>Categoria</th><th>Preço</th><th>Estoque</th><th class="text-end">Ações</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($produtos as $p): ?>
        <tr>
          <td><img class="admin-thumb" src="<?= htmlspecialchars('../'.$p['imagem_url']) ?>" onerror="this.src='../assets/placeholder.svg'"></td>
          <td><?= htmlspecialchars($p['nome']) ?></td>
          <td><span class="product-cat"><?= htmlspecialchars($p['categoria']) ?></span></td>
          <td>R$ <?= number_format((float)$p['preco'],2,',','.') ?></td>
          <td>
            <?php $est = (int)$p['estoque']; ?>
            <?php if ($est <= 0): ?>
              <span class="badge bg-danger">Esgotado</span>
            <?php elseif ($est <= 3): ?>
              <span class="badge bg-warning text-dark"><?= $est ?> · baixo</span>
            <?php else: ?>
              <span class="badge bg-success"><?= $est ?></span>
            <?php endif; ?>
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
          <tr><td colspan="6" class="text-center text-muted py-4">Nenhum produto cadastrado.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</main>
</body>
</html>
