<?php
/**
 * produto.php — Página de detalhe
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/cliente_auth.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) { header('Location: index.php'); exit; }

$stmt = $pdo->prepare('SELECT * FROM produtos WHERE id = :id');
$stmt->execute([':id' => $id]);
$produto = $stmt->fetch();
if (!$produto) { http_response_code(404); exit('Produto não encontrado.'); }

// Galeria secundária
$g = $pdo->prepare('SELECT imagem_url FROM produto_imagens WHERE produto_id = :id');
$g->execute([':id' => $id]);
$galeria = $g->fetchAll(PDO::FETCH_COLUMN);

$page_title = $produto['nome'];

// === Regra de negócio: estoque ===
// Considera-se "indisponível" qualquer produto com estoque <= 0.
// A coluna no banco usa INT NOT NULL DEFAULT 0, então é seguro fazer cast.
$estoque       = max(0, (int)$produto['estoque']);
$indisponivel  = $estoque <= 0;

// Mensagem do WhatsApp adapta-se à disponibilidade
if ($indisponivel) {
    $msg = "Olá! Tenho interesse na peça \"{$produto['nome']}\" (cód. {$produto['id']}), que consta como ESGOTADA. Existe previsão de reposição ou peça similar?";
} else {
    $msg = "Olá! Tenho interesse na peça \"{$produto['nome']}\" (cód. {$produto['id']}). Poderia me enviar mais informações?";
}
$wapp = 'https://wa.me/' . WHATSAPP_NUMERO . '?text=' . rawurlencode($msg);

include __DIR__ . '/includes/header.php';
?>
<section class="container py-5">
  <a href="index.php" class="text-muted small text-decoration-none">← Voltar à coleção</a>
  <div class="row g-5 mt-1">
    <div class="col-lg-7">
      <div class="detail-gallery">
        <img id="mainImg" src="<?= htmlspecialchars($produto['imagem_url'] ?: 'assets/placeholder.svg') ?>" alt="<?= htmlspecialchars($produto['nome']) ?>" onerror="this.src='assets/placeholder.svg'">
        <?php if (!empty($galeria)): ?>
          <div class="thumb-row">
            <img src="<?= htmlspecialchars($produto['imagem_url']) ?>" onclick="document.getElementById('mainImg').src=this.src">
            <?php foreach ($galeria as $img): ?>
              <img src="<?= htmlspecialchars($img) ?>" onclick="document.getElementById('mainImg').src=this.src">
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
        <?php if (!empty($produto['video_url'])): ?>
          <div class="mt-3">
            <video controls style="width:100%;max-width:600px;border-radius:8px;">
              <source src="../<?= htmlspecialchars($produto['video_url']) ?>" type="<?= pathinfo($produto['video_url'], PATHINFO_EXTENSION) === 'mp4' ? 'video/mp4' : (pathinfo($produto['video_url'], PATHINFO_EXTENSION) === 'webm' ? 'video/webm' : 'video/ogg') ?>">
              Seu navegador não suporta vídeos.
            </video>
          </div>
        <?php endif; ?>
      </div>
    </div>
    <div class="col-lg-5">
      <p class="product-cat mb-2"><?= htmlspecialchars($produto['categoria']) ?></p>
      <h1 class="serif"><?= htmlspecialchars($produto['nome']) ?></h1>
      <p class="text-muted"><?= htmlspecialchars($produto['descricao_curta'] ?? '') ?></p>

      <div class="my-4">
        <div class="text-muted small">Valor</div>
        <div class="serif" style="font-size:2rem;">R$ <?= number_format((float)$produto['preco'], 2, ',', '.') ?></div>
        <div class="small">
          <?php if ($indisponivel): ?>
            <span class="badge bg-danger">Esgotado</span>
            <span class="text-muted ms-2">Venda temporariamente bloqueada.</span>
          <?php else: ?>
            <span class="text-success">● Disponível</span>
            <span class="text-muted">· <?= $estoque ?> em estoque</span>
          <?php endif; ?>
        </div>
      </div>

      <?php
        $isFav = in_array((int)$produto['id'], favoritos_ids($pdo), true);
      ?>
      <button type="button"
              class="btn btn-fav-detail w-100 mb-2 fav-btn <?= $isFav ? 'is-on' : '' ?>"
              data-id="<?= (int)$produto['id'] ?>"
              title="<?= $isFav ? 'Remover dos favoritos' : 'Adicionar aos favoritos' ?>">
        <span class="heart">♥</span>
        <span class="lbl-on">Salvo nos favoritos</span>
        <span class="lbl-off">Adicionar aos favoritos</span>
      </button>

      <?php if ($indisponivel): ?>
        <button class="btn btn-gold w-100 mb-2" disabled aria-disabled="true">Indisponível para venda</button>
        <a href="<?= $wapp ?>" target="_blank" class="btn btn-outline-gold w-100 mb-2">Consultar reposição no WhatsApp</a>
      <?php else: ?>
        <button type="button" class="btn btn-gold w-100 mb-2 stripe-comprar" data-produto-id="<?= $produto['id'] ?>">
          💳 Comprar Agora
        </button>
        <a href="<?= $wapp ?>" target="_blank" class="btn btn-outline-gold w-100 mb-2">Consultar especialista</a>
      <?php endif; ?>
      <a href="index.php?categoria=<?= urlencode($produto['categoria']) ?>" class="btn btn-outline-gold w-100">Ver mais em <?= htmlspecialchars($produto['categoria']) ?></a>

      <h5 class="serif mt-5" style="color:var(--accent);">Especificações técnicas</h5>
      <table class="spec-table">
        <?php
          $linhas = preg_split('/\r\n|\r|\n/', (string)$produto['especificacoes_tecnicas']);
          foreach ($linhas as $linha) {
              $linha = trim($linha);
              if ($linha === '') continue;
              if (strpos($linha, ':') !== false) {
                  [$k,$v] = explode(':', $linha, 2);
                  echo '<tr><td>'.htmlspecialchars(trim($k)).'</td><td>'.htmlspecialchars(trim($v)).'</td></tr>';
              } else {
                  echo '<tr><td colspan="2">'.htmlspecialchars($linha).'</td></tr>';
              }
          }
        ?>
      </table>
    </div>
  </div>
</section>
<script src="assets/js/favoritos.js"></script>

<!-- Stripe JS -->
<script src="https://js.stripe.com/v3/"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const btnComprar = document.querySelector('.stripe-comprar');
    if (!btnComprar) return;
    
    btnComprar.addEventListener('click', async function() {
        const produtoId = this.dataset.produtoId;
        
        // Verifica se cliente está logado (opcional - pode redirecionar para login)
        // Aqui fazemos a chamada direta e o backend verifica
        
        this.disabled = true;
        this.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Processando...';
        
        try {
            const formData = new FormData();
            formData.append('produto_id', produtoId);
            
            const response = await fetch('stripe/checkout.php', {
                method: 'POST',
                body: formData
            });
            
            const data = await response.json();
            
            if (data.success && data.url) {
                // Redireciona para checkout do Stripe
                window.location.href = data.url;
            } else if (data.modo_demo) {
                // Modo demonstração sem SDK
                alert('MODO DEMONSTRAÇÃO\\n\\nPedido #' + data.pedido_id + ' criado!\\nProduto: ' + data.produto + '\\nValor: R$ ' + data.valor.toFixed(2) + '\\n\\nPara processar pagamentos reais, instale o SDK: composer require stripe/stripe-php');
                window.location.href = 'stripe/success.php?pedido_id=' + data.pedido_id;
            } else {
                alert('Erro: ' + (data.error || 'Falha ao iniciar pagamento'));
                // Se não autenticado, redireciona para login
                if (response.status === 401) {
                    window.location.href = 'cliente_login.php?redirect=produto.php?id=' + produtoId;
                }
            }
        } catch (error) {
            console.error('Erro:', error);
            alert('Erro de conexão. Tente novamente.');
        } finally {
            if (!this.classList.contains('stripe-comprar')) {
                this.disabled = false;
                this.innerHTML = '💳 Comprar Agora';
            }
        }
    });
});
</script>
<?php include __DIR__ . '/includes/footer.php'; ?>
