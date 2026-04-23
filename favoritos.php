<?php
/**
 * favoritos.php — Lista de favoritos do cliente
 *  Mantém os MESMOS filtros de busca e categoria do catálogo principal:
 *   ?categoria=Esmeraldas
 *   ?q=esmeralda
 */
require_once __DIR__ . '/includes/cliente_auth.php';
exigir_cliente_logado('favoritos.php');

$page_title = 'Meus favoritos';

$categoriasValidas = ['Esmeraldas','Esculturas','Cangas'];
$categoria = $_GET['categoria'] ?? null;
if ($categoria !== null && !in_array($categoria, $categoriasValidas, true)) {
    $categoria = null;
}

$q = isset($_GET['q']) ? trim((string)$_GET['q']) : '';
if (mb_strlen($q) > 80) { $q = mb_substr($q, 0, 80); }

// JOIN com favoritos do cliente atual
$sql = 'SELECT p.* FROM produtos p
        INNER JOIN favoritos f ON f.produto_id = p.id
        WHERE f.cliente_id = :cid';
$params = [':cid' => cliente_id()];

if ($categoria) {
    $sql .= ' AND p.categoria = :c';
    $params[':c'] = $categoria;
}
if ($q !== '') {
    $sql .= ' AND p.nome LIKE :q';
    $like = '%' . str_replace(['\\','%','_'], ['\\\\','\\%','\\_'], $q) . '%';
    $params[':q'] = $like;
}
$sql .= ' ORDER BY f.criado_em DESC';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$produtos = $stmt->fetchAll();

$favIds = array_map(fn($p) => (int)$p['id'], $produtos);

include __DIR__ . '/includes/header.php';
?>

<section class="container py-5" id="colecao">
  <div class="d-flex justify-content-between align-items-end flex-wrap gap-3">
    <div>
      <p class="eyebrow text-uppercase small mb-1" style="color:var(--accent);letter-spacing:.4em;">Sua seleção</p>
      <h1 class="serif mb-0">Meus favoritos</h1>
    </div>
    <span class="text-muted small" id="contadorProdutos"><?= count($produtos) ?> peça(s)</span>
  </div>

  <form class="row g-2 mt-3 align-items-center" method="get" action="favoritos.php" role="search">
    <?php if ($categoria): ?>
      <input type="hidden" name="categoria" value="<?= htmlspecialchars($categoria) ?>">
    <?php endif; ?>
    <div class="col-md-8 col-lg-6">
      <div class="search-wrap">
        <span class="search-icon" aria-hidden="true">⌕</span>
        <input id="buscaNome" name="q" type="search" class="form-control search-input"
               placeholder="Buscar nos seus favoritos..."
               value="<?= htmlspecialchars($q) ?>" maxlength="80" autocomplete="off">
        <?php if ($q !== ''): ?>
          <a class="search-clear" href="favoritos.php<?= $categoria ? '?categoria='.urlencode($categoria) : '' ?>" title="Limpar busca">×</a>
        <?php endif; ?>
      </div>
    </div>
    <div class="col-md-4 col-lg-2">
      <button class="btn btn-outline-gold w-100" type="submit">Buscar</button>
    </div>
  </form>

  <div class="filter-bar">
    <a class="chip <?= !$categoria ? 'active' : '' ?>" href="favoritos.php<?= $q!==''?'?q='.urlencode($q):'' ?>">Tudo</a>
    <?php foreach ($categoriasValidas as $cat): ?>
      <a class="chip <?= $categoria===$cat ? 'active':'' ?>"
         href="favoritos.php?categoria=<?= urlencode($cat) ?><?= $q!==''?'&q='.urlencode($q):'' ?>"><?= $cat ?></a>
    <?php endforeach; ?>
  </div>

  <?php if (empty($produtos)): ?>
    <div class="text-center py-5 text-muted">
      <?php if ($q !== '' || $categoria): ?>
        Nenhum favorito corresponde a este filtro.
      <?php else: ?>
        Você ainda não favoritou nenhuma peça. <a href="index.php" style="color:var(--accent);">Explorar coleção →</a>
      <?php endif; ?>
    </div>
  <?php else: ?>
    <div class="row g-4" id="gradeProdutos">
      <?php foreach ($produtos as $p):
        $semEstoque = ((int)$p['estoque']) <= 0;
      ?>
        <div class="col-12 col-sm-6 col-lg-4 produto-item"
             data-nome="<?= htmlspecialchars(mb_strtolower($p['nome'])) ?>">
          <article class="product-card <?= $semEstoque ? 'is-soldout' : '' ?>">
            <div class="product-thumb">
              <a href="produto.php?id=<?= (int)$p['id'] ?>">
                <img src="<?= htmlspecialchars($p['imagem_url'] ?: 'assets/placeholder.svg') ?>" alt="<?= htmlspecialchars($p['nome']) ?>" onerror="this.src='assets/placeholder.svg'">
              </a>
              <?php if ($semEstoque): ?>
                <span class="badge-soldout">Esgotado</span>
              <?php endif; ?>
              <button type="button" class="fav-btn is-on" data-id="<?= (int)$p['id'] ?>" title="Remover dos favoritos" aria-label="Remover dos favoritos">♥</button>
            </div>
            <div class="product-body">
              <a class="text-decoration-none text-reset" href="produto.php?id=<?= (int)$p['id'] ?>">
                <span class="product-cat"><?= htmlspecialchars($p['categoria']) ?></span>
                <h3 class="product-name"><?= htmlspecialchars($p['nome']) ?></h3>
                <p class="text-muted small mb-0"><?= htmlspecialchars($p['descricao_curta'] ?? '') ?></p>
                <div class="product-price mt-2">
                  <small>A partir de</small><br>
                  R$ <?= number_format((float)$p['preco'], 2, ',', '.') ?>
                </div>
              </a>
            </div>
          </article>
        </div>
      <?php endforeach; ?>
    </div>
    <div id="vazioBusca" class="text-center py-5 text-muted" style="display:none;">
      Nenhuma peça corresponde à sua busca.
    </div>
  <?php endif; ?>
</section>

<script>
window.CSRF_CLIENTE = <?= json_encode($_SESSION['csrf_cliente']) ?>;
window.PRECISA_LOGIN = false;
</script>
<script src="assets/js/favoritos.js"></script>
<script src="assets/js/busca.js"></script>

<?php include __DIR__ . '/includes/footer.php'; ?>
