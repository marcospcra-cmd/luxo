<?php
/**
 * index.php — Catálogo público Solar Amazônia
 * Filtros via GET:
 *   ?categoria=Esmeraldas   → filtra por categoria (server-side)
 *   ?q=esmeralda            → busca por nome (server-side, LIKE seguro via PDO)
 * Adicionalmente, um filtro JS refina os resultados em tempo real
 * conforme o usuário digita, SEM recarregar a página.
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/cliente_auth.php';

// Inicializa WAF se ainda não foi inicializado pelo header
if (!isset($_SESSION['csrf_token'])) {
    require_once __DIR__ . '/middleware/waf_security.php';
    \MaisonDeLuxo\Middleware\waf_init();
}

$page_title = 'Coleção';

// IDs favoritados pelo cliente atual (vazio se não logado)
$favIds = favoritos_ids($pdo);

$categoriasValidas = ['Esmeraldas','Esculturas','Cangas'];
$categoria = $_GET['categoria'] ?? null;
if ($categoria !== null && !in_array($categoria, $categoriasValidas, true)) {
    $categoria = null;
}

// Busca por nome (sanitizada, máx 80 chars)
$q = isset($_GET['q']) ? trim((string)$_GET['q']) : '';
if (mb_strlen($q) > 80) { $q = mb_substr($q, 0, 80); }

// Monta SQL com prepared statements
$sql    = 'SELECT * FROM produtos WHERE 1=1';
$params = [];

if ($categoria) {
    $sql .= ' AND categoria = :c';
    $params[':c'] = $categoria;
}
if ($q !== '') {
    $sql .= ' AND nome LIKE :q';
    // Escapa wildcards do LIKE para evitar busca indevida
    $like = '%' . str_replace(['\\','%','_'], ['\\\\','\\%','\\_'], $q) . '%';
    $params[':q'] = $like;
}
$sql .= ' ORDER BY criado_em DESC';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$produtos = $stmt->fetchAll();

include __DIR__ . '/includes/header.php';
?>

<section class="hero">
  <div class="container hero-content">
    <p class="eyebrow">Curadoria · Edição limitada</p>
    <h1>Tesouros raros da Amazônia para colecionadores exigentes.</h1>
    <p>Esmeraldas certificadas, esculturas autorais e cangas em seda pura — selecionadas uma a uma por nossos especialistas.</p>
    
    <?php
    // Busca vídeo de destaque (primeiro produto com video_destaque preenchido)
    $stmtDestaque = $pdo->query("SELECT video_destaque FROM produtos WHERE video_destaque IS NOT NULL AND video_destaque != '' LIMIT 1");
    $videoDestaque = $stmtDestaque->fetchColumn();
    if ($videoDestaque):
      // Converte URL do YouTube/Vimeo para embed
      $embedUrl = '';
      if (strpos($videoDestaque, 'youtube.com') !== false || strpos($videoDestaque, 'youtu.be') !== false) {
        preg_match('/(?:youtube\.com\/watch\?v=|youtu\.be\/)([a-zA-Z0-9_-]+)/', $videoDestaque, $matches);
        if (!empty($matches[1])) {
          $embedUrl = 'https://www.youtube.com/embed/' . htmlspecialchars($matches[1]);
        }
      } elseif (strpos($videoDestaque, 'vimeo.com') !== false) {
        preg_match('/vimeo\.com\/(\d+)/', $videoDestaque, $matches);
        if (!empty($matches[1])) {
          $embedUrl = 'https://player.vimeo.com/video/' . htmlspecialchars($matches[1]);
        }
      }
    ?>
    <?php if ($embedUrl): ?>
    <div class="video-destaque-wrap mt-4">
      <div class="ratio ratio-16x9" style="max-width:800px;margin:0 auto;border-radius:var(--radius-lg);overflow:hidden;box-shadow:var(--shadow-xl);">
        <iframe src="<?= $embedUrl ?>" title="Vídeo em Destaque" allowfullscreen></iframe>
      </div>
    </div>
    <?php endif; ?>
    <?php endif; ?>
    
    <a href="#colecao" class="btn btn-outline-gold mt-3">Explorar coleção</a>
  </div>
</section>

<section class="container py-5" id="colecao">
  <div class="d-flex justify-content-between align-items-end flex-wrap gap-3">
    <div>
      <p class="eyebrow text-uppercase small mb-1" style="color:var(--color-gold);letter-spacing:.4em;">Catálogo</p>
      <h2 class="serif mb-0"><?= $categoria ? htmlspecialchars($categoria) : 'Todas as peças' ?></h2>
    </div>
    <span class="text-muted small" id="contadorProdutos"><?= count($produtos) ?> peça(s)</span>
  </div>

  <!--
    Formulário GET: ao submeter, recarrega com ?q=...&categoria=...
    Mas o JS abaixo já filtra em tempo real, sem precisar dar Enter.
  -->
  <form class="row g-2 mt-3 align-items-center" method="get" action="index.php" role="search">
    <?php if ($categoria): ?>
      <input type="hidden" name="categoria" value="<?= htmlspecialchars($categoria) ?>">
    <?php endif; ?>
    <div class="col-md-8 col-lg-6">
      <div class="search-wrap">
        <span class="search-icon" aria-hidden="true">⌕</span>
        <input
          id="buscaNome"
          name="q"
          type="search"
          class="form-control search-input"
          placeholder="Buscar por nome da peça..."
          value="<?= htmlspecialchars($q) ?>"
          maxlength="80"
          autocomplete="off">
        <?php if ($q !== ''): ?>
          <a class="search-clear" href="index.php<?= $categoria ? '?categoria='.urlencode($categoria) : '' ?>" title="Limpar busca">×</a>
        <?php endif; ?>
      </div>
    </div>
    <div class="col-md-4 col-lg-2">
      <button class="btn btn-outline-gold w-100" type="submit">Buscar</button>
    </div>
  </form>

  <div class="filter-bar">
    <a class="chip <?= !$categoria ? 'active' : '' ?>" href="index.php<?= $q!==''?'?q='.urlencode($q):'' ?>">Tudo</a>
    <?php foreach ($categoriasValidas as $cat): ?>
      <a class="chip <?= $categoria===$cat ? 'active':'' ?>"
         href="index.php?categoria=<?= urlencode($cat) ?><?= $q!==''?'&q='.urlencode($q):'' ?>"><?= $cat ?></a>
    <?php endforeach; ?>
  </div>

  <?php if (empty($produtos)): ?>
    <div class="text-center py-5 text-muted" style="background:var(--color-bg-card);border-radius:var(--radius-lg);border:1px solid var(--color-border-light);">
      <?= $q !== ''
          ? 'Nenhuma peça encontrada para "'.htmlspecialchars($q).'".'
          : 'Nenhuma peça disponível nesta categoria no momento.' ?>
    </div>
  <?php else: ?>
    <div class="products-grid" id="gradeProdutos">
      <?php foreach ($produtos as $p):
        $semEstoque = ((int)$p['estoque']) <= 0;
      ?>
        <div class="produto-item"
             data-nome="<?= htmlspecialchars(mb_strtolower($p['nome'])) ?>">
          <article class="product-card <?= $semEstoque ? 'is-soldout' : '' ?>">
            <div class="product-thumb">
              <a href="produto.php?id=<?= (int)$p['id'] ?>">
                <img src="<?= htmlspecialchars($p['imagem_url'] ?: 'assets/placeholder.svg') ?>" alt="<?= htmlspecialchars($p['nome']) ?>" onerror="this.src='assets/placeholder.svg'">
              </a>
              <?php if ($semEstoque): ?>
                <span class="badge-soldout">Esgotado</span>
              <?php endif; ?>
              <?php $isFav = in_array((int)$p['id'], $favIds, true); ?>
              <button type="button"
                      class="fav-btn <?= $isFav ? 'is-on' : '' ?>"
                      data-id="<?= (int)$p['id'] ?>"
                      title="<?= $isFav ? 'Remover dos favoritos' : 'Adicionar aos favoritos' ?>"
                      aria-label="<?= $isFav ? 'Remover dos favoritos' : 'Adicionar aos favoritos' ?>">♥</button>
            </div>
            <div class="product-body">
              <a class="text-decoration-none text-reset" href="produto.php?id=<?= (int)$p['id'] ?>">
                <span class="product-cat"><?= htmlspecialchars($p['categoria']) ?></span>
                <h3 class="product-name"><?= htmlspecialchars($p['nome']) ?></h3>
                <p class="text-muted small mb-0"><?= htmlspecialchars($p['descricao_curta'] ?? '') ?></p>
                <div class="product-price">
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

<script src="assets/js/favoritos.js"></script>
<script src="assets/js/busca.js"></script>

<?php include __DIR__ . '/includes/footer.php'; ?>
