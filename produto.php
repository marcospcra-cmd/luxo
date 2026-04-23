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
        <!-- Imagem principal -->
        <img id="mainImg" src="<?= htmlspecialchars($produto['imagem_url'] ?: 'assets/placeholder.svg') ?>" alt="<?= htmlspecialchars($produto['nome']) ?>" onerror="this.src='assets/placeholder.svg'">
        
        <!-- Thumbnails das imagens -->
        <?php if (!empty($galeria) || !empty($produto['video_url'])): ?>
          <div class="thumb-row mt-3">
            <img src="<?= htmlspecialchars($produto['imagem_url']) ?>" onclick="setMainImage(this)" class="thumb-active" alt="Imagem principal">
            <?php foreach ($galeria as $img): ?>
              <img src="<?= htmlspecialchars($img) ?>" onclick="setMainImage(this)" alt="Galeria">
            <?php endforeach; ?>
            <?php if (!empty($produto['video_url'])): ?>
              <!-- Thumbnail do vídeo -->
              <img src="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'%3E%3Crect fill='%231a1a1a' width='100' height='100'/%3E%3Cpolygon fill='%23d4af37' points='40,30 70,50 40,70'/%3E%3C/svg%3E" 
                   onclick="scrollToVideo()" 
                   alt="Ver vídeo" class="video-thumb">
            <?php endif; ?>
          </div>
        <?php endif; ?>
        
        <!-- Vídeo integrado à galeria -->
        <?php if (!empty($produto['video_url'])): ?>
          <div class="video-container mt-3" id="videoSection">
            <video controls class="gallery-video" id="productVideo">
              <source src="../<?= htmlspecialchars($produto['video_url']) ?>" type="<?= pathinfo($produto['video_url'], PATHINFO_EXTENSION) === 'mp4' ? 'video/mp4' : (pathinfo($produto['video_url'], PATHINFO_EXTENSION) === 'webm' ? 'video/webm' : 'video/ogg') ?>">
              Seu navegador não suporta vídeos.
            </video>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <script>
    function setMainImage(thumb) {
        // Remove classe active de todos os thumbnails
        document.querySelectorAll('.thumb-row img').forEach(img => {
            img.classList.remove('thumb-active');
        });
        // Adiciona classe active ao thumbnail clicado
        thumb.classList.add('thumb-active');
        // Atualiza imagem principal
        document.getElementById('mainImg').src = thumb.src;
    }
    
    function scrollToVideo() {
        const videoSection = document.getElementById('videoSection');
        if (videoSection) {
            videoSection.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            const video = document.getElementById('productVideo');
            if (video) {
                video.play();
            }
        }
    }
    </script>
    
    <!-- Coluna de Informações do Produto -->
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
      
      <!-- Botão Adicionar ao Carrinho -->
      <?php if (!$indisponivel): ?>
      <button type="button" class="btn btn-outline-gold w-100 mb-2" onclick="addToCart(<?= $produto['id'] ?>)">
        🛒 Adicionar ao Carrinho
      </button>
      <?php endif; ?>
      
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
/**
 * Adiciona produto ao carrinho via AJAX
 */
function addToCart(produtoId) {
    fetch('api/carrinho_action.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({action: 'add', id: produtoId, qty: 1})
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            showToast('Produto adicionado ao carrinho!', 'success');
            // Atualiza contador do carrinho no header se existir
            const cartCount = document.querySelector('.cart-count');
            if (cartCount) {
                cartCount.textContent = parseInt(cartCount.textContent || '0') + 1;
            }
        } else {
            showToast('Erro ao adicionar ao carrinho', 'error');
        }
    })
    .catch(err => {
        console.error(err);
        showToast('Erro de conexão', 'error');
    });
}

/**
 * Exibe toast notification
 */
function showToast(message, type = 'success') {
    const toast = document.createElement('div');
    toast.className = `toast-notification toast-${type} show`;
    toast.textContent = message;
    toast.style.cssText = `
        position: fixed;
        bottom: 20px;
        right: 20px;
        padding: 1rem 1.5rem;
        border-radius: 8px;
        color: white;
        font-weight: 500;
        z-index: 9999;
        transition: all 0.3s ease;
        ${type === 'success' ? 'background: #28a745;' : 'background: #dc3545;'}
    `;
    document.body.appendChild(toast);
    
    setTimeout(() => {
        toast.classList.remove('show');
        setTimeout(() => toast.remove(), 500);
    }, 3000);
}

document.addEventListener('DOMContentLoaded', function() {
    const btnComprar = document.querySelector('.stripe-comprar');
    if (!btnComprar) return;
    
    btnComprar.addEventListener('click', async function() {
        const produtoId = this.dataset.produtoId;
        
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
