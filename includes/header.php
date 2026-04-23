<?php
/**
 * includes/header.php
 * Header integrado com WAF, sessão segura e carrinho com contador dinâmico
 */

// Inicializa WAF Security (deve ser o primeiro include)
require_once __DIR__ . '/../middleware/waf_security.php';
\MaisonDeLuxo\Middleware\waf_init();

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/cliente_auth.php';

$tema = $TEMA_ATUAL;

// Conta favoritos do cliente logado
$favTotalHeader = 0;
if (cliente_logado()) {
    $s = $pdo->prepare('SELECT COUNT(*) FROM favoritos WHERE cliente_id = :c');
    $s->execute([':c' => cliente_id()]);
    $favTotalHeader = (int)$s->fetchColumn();
}

// Calcula total de itens no carrinho
$cartItemCount = 0;
if (isset($_SESSION['carrinho']) && is_array($_SESSION['carrinho'])) {
    foreach ($_SESSION['carrinho'] as $qty) {
        $cartItemCount += (int)$qty;
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR" data-bs-theme="<?= $tema === 'dark' ? 'dark' : 'light' ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= isset($page_title) ? htmlspecialchars($page_title) . ' | ' : '' ?>Maison de Luxo</title>
<meta name="description" content="Curadoria exclusiva de esmeraldas, esculturas e cangas de alto padrão.">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@400;600;700&family=Inter:wght@300;400;500&display=swap" rel="stylesheet">
<link href="assets/css/style.css" rel="stylesheet">
<link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>💎</text></svg>">
</head>
<body class="theme-<?= $tema ?>">
<nav class="navbar navbar-expand-lg site-nav sticky-top">
  <div class="container">
    <a class="navbar-brand brand" href="index.php">
      <span class="brand-mark">M</span><span class="brand-word">Maison<em>de Luxo</em></span>
    </a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="mainNav">
      <ul class="navbar-nav me-auto ms-lg-4">
        <li class="nav-item"><a class="nav-link" href="index.php">Coleção</a></li>
        <li class="nav-item"><a class="nav-link" href="index.php?categoria=Esmeraldas">Esmeraldas</a></li>
        <li class="nav-item"><a class="nav-link" href="index.php?categoria=Esculturas">Esculturas</a></li>
        <li class="nav-item"><a class="nav-link" href="index.php?categoria=Cangas">Cangas</a></li>
      </ul>
      <div class="d-flex align-items-center gap-3 flex-wrap">
        <!-- Ícone do Carrinho com Contador Dinâmico -->
        <a class="nav-link position-relative" href="carrinho.php" title="Seu carrinho" aria-label="Carrinho">
          🛒 <span class="d-none d-lg-inline">Carrinho</span>
          <span id="cartCountBadge" class="fav-count" style="<?= $cartItemCount > 0 ? '' : 'display:none;' ?>"><?= $cartItemCount ?></span>
        </a>
        
        <a class="nav-link fav-link position-relative" href="favoritos.php" title="Meus favoritos" aria-label="Meus favoritos">
          ♥ <span class="d-none d-lg-inline">Favoritos</span>
          <span id="favCountBadge" class="fav-count" style="<?= $favTotalHeader>0?'':'display:none;' ?>"><?= $favTotalHeader ?></span>
        </a>

        <?php if (cliente_logado()): ?>
          <div class="dropdown">
            <button class="btn btn-outline-gold btn-sm dropdown-toggle" data-bs-toggle="dropdown">
              <?= htmlspecialchars(mb_strimwidth($_SESSION['cliente_nome'] ?? 'Conta', 0, 18, '…')) ?>
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
              <li><a class="dropdown-item" href="favoritos.php">Meus favoritos</a></li>
              <li><hr class="dropdown-divider"></li>
              <li><a class="dropdown-item" href="cliente_logout.php">Sair</a></li>
            </ul>
          </div>
        <?php else: ?>
          <a class="btn btn-outline-gold btn-sm" href="cliente_login.php">Entrar</a>
        <?php endif; ?>

        <div class="theme-switch" role="group" aria-label="Tema">
          <a class="t-btn <?= $tema==='light'?'active':'' ?>" href="?<?= http_build_query(array_merge($_GET, ['tema'=>'light'])) ?>" title="Tema claro">☀</a>
          <a class="t-btn <?= $tema==='dark'?'active':'' ?>"  href="?<?= http_build_query(array_merge($_GET, ['tema'=>'dark']))  ?>" title="Tema escuro">☾</a>
        </div>
        <a class="btn btn-gold btn-sm" href="https://wa.me/<?= WHATSAPP_NUMERO ?>" target="_blank">Especialista</a>
      </div>
    </div>
  </div>
</nav>
<main class="site-main">

<script>
window.CSRF_CLIENTE = <?= json_encode(\MaisonDeLuxo\Middleware\WAFSecurity::getCSRFToken()) ?>;
window.PRECISA_LOGIN = <?= cliente_logado() ? 'false' : 'true' ?>;
window.CART_ITEM_COUNT = <?= $cartItemCount ?>;
</script>

