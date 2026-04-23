<?php
/**
 * SOLAR AMAZÔNIA - Página de Detalhe do Produto
 * Arquivo: produto.php
 * Descrição: Exibe detalhes, galeria e especificações completas com SKU e certificação
 */

// 1. SEGURANÇA E CONFIGURAÇÃO (Primeiro para evitar "headers already sent")
require_once __DIR__ . '/middleware/waf_security.php';
require_once __DIR__ . '/config.php';

// Iniciar sessão se não estiver ativa
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 2. BUSCA DO PRODUTO
$id = isset($_GET['id']) ? filter_var($_GET['id'], FILTER_VALIDATE_INT) : 0;

if (!$id || $id <= 0) {
    header('Location: index.php');
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT * FROM produtos WHERE id = :id AND ativo = 1");
    $stmt->execute(['id' => $id]);
    $produto = $stmt->fetch();
    
    if (!$produto) {
        http_response_code(404);
        $erro_msg = "Produto não encontrado ou indisponível.";
    }
} catch (PDOException $e) {
    error_log("Erro SQL Produto: " . $e->getMessage());
    $erro_msg = "Erro ao carregar dados do produto.";
    $produto = null;
}

// 3. PREPARAÇÃO DE DADOS SEGUROS
if ($produto) {
    // Decodificar imagens (JSON)
    $imagens_json = !empty($produto['imagens']) ? json_decode($produto['imagens'], true) : [];
    if (!is_array($imagens_json)) $imagens_json = [];
    
    // Se não houver imagens no JSON, usa imagem_url principal
    if (empty($imagens_json) && !empty($produto['imagem_url'])) {
        $imagens_json[] = $produto['imagem_url'];
    }
    
    // Fallback para placeholder se ainda vazio
    if (empty($imagens_json)) {
        $imagens_json[] = 'assets/img/placeholder.jpg';
    }

    // Formatação de preço
    $preco_exibicao = !empty($produto['preco_promocional']) ? $produto['preco_promocional'] : $produto['preco'];
    $preco_formatado = number_format($preco_exibicao, 2, ',', '.');
    $preco_cheio_formatado = number_format($produto['preco'], 2, ',', '.');
    
    // Estoque e Disponibilidade
    $estoque = (int)($produto['estoque'] ?? 0);
    $disponivel = $estoque > 0 && ($produto['status_disponibilidade'] ?? 'disponivel') === 'disponivel';
    
    // Status formatado
    $status_labels = [
        'disponivel' => 'Disponível',
        'reservado' => 'Reservado',
        'em_transito' => 'Em Trânsito',
        'vendida' => 'Vendida',
        'conservacao' => 'Em Conservação',
        'leilao' => 'Em Leilão'
    ];
    $status_label = $status_labels[$produto['status_disponibilidade'] ?? 'disponivel'] ?? 'Disponível';
}

$page_title = $produto['nome'] ?? 'Produto';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?> | Solar Amazônia</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* CSS Crítico da Página de Produto */
        .product-detail-container {
            max-width: 1200px;
            margin: 40px auto;
            padding: 20px;
            display: grid;
            grid-template-columns: 1.2fr 1fr;
            gap: 50px;
        }

        /* Galeria */
        .gallery-wrapper { position: relative; }
        
        .main-media {
            width: 100%;
            aspect-ratio: 1 / 1;
            background: var(--bg-card, #f5f5f5);
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }

        .main-media img, .main-media video {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.5s ease;
        }

        .thumbs-list {
            display: flex;
            gap: 12px;
            overflow-x: auto;
            padding-bottom: 5px;
        }

        .thumb-item {
            width: 70px;
            height: 70px;
            border-radius: 8px;
            cursor: pointer;
            opacity: 0.6;
            transition: all 0.3s;
            border: 2px solid transparent;
            object-fit: cover;
            flex-shrink: 0;
        }

        .thumb-item:hover, .thumb-item.active {
            opacity: 1;
            border-color: var(--gold, #d4af37);
            transform: translateY(-3px);
        }

        /* Info Produto */
        .product-info-content {
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .prod-sku {
            font-size: 0.85rem;
            color: var(--text-secondary, #666);
            font-family: monospace;
            margin-bottom: 5px;
        }

        .prod-category {
            color: var(--gold, #d4af37);
            text-transform: uppercase;
            letter-spacing: 2px;
            font-size: 0.85rem;
            font-weight: 700;
            margin-bottom: 10px;
        }

        .prod-title {
            font-size: 2.2rem;
            color: var(--text-primary, #1a1a1a);
            margin-bottom: 15px;
            line-height: 1.2;
            font-family: 'Playfair Display', serif;
        }

        .prod-price-box {
            margin: 20px 0;
            padding: 20px 0;
            border-top: 1px solid var(--border-color, #eee);
            border-bottom: 1px solid var(--border-color, #eee);
        }

        .price-value {
            font-size: 2rem;
            font-weight: 700;
            color: var(--text-primary, #1a1a1a);
        }

        .price-old {
            font-size: 1.2rem;
            color: var(--text-secondary, #999);
            text-decoration: line-through;
            margin-left: 15px;
        }

        /* Especificações Técnicas */
        .specs-panel {
            background: rgba(212, 175, 55, 0.05);
            border: 1px solid rgba(212, 175, 55, 0.2);
            border-radius: 8px;
            padding: 20px;
            margin: 25px 0;
        }

        .specs-title {
            font-size: 1rem;
            color: var(--gold, #d4af37);
            margin-bottom: 15px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .spec-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            font-size: 0.95rem;
            padding-bottom: 8px;
            border-bottom: 1px solid rgba(0,0,0,0.05);
        }
        .spec-row:last-child { border-bottom: none; margin-bottom: 0; }

        .spec-label { color: var(--text-secondary, #666); }
        .spec-val { font-weight: 600; color: var(--text-primary, #333); text-align: right; }

        /* Badge de Edição */
        .edition-badge {
            display: inline-block;
            padding: 4px 12px;
            background: var(--gold, #d4af37);
            color: #fff;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 10px;
        }

        /* Ações */
        .action-area {
            margin-top: 30px;
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
        }

        .input-qty {
            width: 70px;
            padding: 12px;
            text-align: center;
            border: 1px solid var(--border-color, #ddd);
            border-radius: 6px;
            font-size: 1.1rem;
        }

        .btn-buy {
            flex: 1;
            min-width: 200px;
            padding: 15px;
            background: linear-gradient(135deg, var(--gold, #d4af37), #b8962e);
            color: #fff;
            border: none;
            border-radius: 6px;
            font-weight: 700;
            font-size: 1rem;
            cursor: pointer;
            text-transform: uppercase;
            letter-spacing: 1px;
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .btn-buy:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(212, 175, 55, 0.4);
        }

        .btn-buy:disabled {
            background: #ccc;
            cursor: not-allowed;
            transform: none;
        }

        .desc-text {
            margin-top: 30px;
            color: var(--text-secondary, #555);
            line-height: 1.8;
        }

        /* Responsivo */
        @media (max-width: 900px) {
            .product-detail-container { grid-template-columns: 1fr; gap: 30px; }
            .prod-title { font-size: 1.8rem; }
        }
        @media (max-width: 576px) {
            .prod-title { font-size: 1.5rem; }
            .price-value { font-size: 1.6rem; }
            .action-area { flex-direction: column; }
            .input-qty { width: 100%; }
            .spec-row { flex-direction: column; gap: 4px; }
            .spec-val { text-align: left; }
        }
    </style>
</head>
<body>

<?php include 'includes/header.php'; ?>

<main class="container">
    <?php if (isset($erro_msg)): ?>
        <div style="text-align:center; padding: 80px 20px;">
            <i class="fas fa-exclamation-triangle" style="font-size: 3rem; color: var(--gold, #d4af37); margin-bottom: 20px;"></i>
            <h2><?php echo $erro_msg; ?></h2>
            <a href="index.php" class="btn-buy" style="display:inline-block; text-decoration:none; margin-top:20px; min-width:auto; padding:12px 30px;">Voltar à Loja</a>
        </div>
    <?php elseif ($produto): ?>
        
        <div class="product-detail-container">
            <!-- Coluna Esquerda: Mídia -->
            <div class="gallery-wrapper">
                <div class="main-media" id="mainMediaBox">
                    <?php 
                    $first_media = $imagens_json[0];
                    $is_video = pathinfo($first_media, PATHINFO_EXTENSION) === 'mp4';
                    if ($is_video): ?>
                        <video src="<?php echo htmlspecialchars($first_media); ?>" autoplay muted loop playsinline></video>
                    <?php else: ?>
                        <img src="<?php echo htmlspecialchars($first_media); ?>" alt="<?php echo htmlspecialchars($produto['nome']); ?>">
                    <?php endif; ?>
                </div>

                <div class="thumbs-list">
                    <?php foreach ($imagens_json as $idx => $media): 
                        $is_vid = pathinfo($media, PATHINFO_EXTENSION) === 'mp4';
                        $active = ($idx === 0) ? 'active' : '';
                    ?>
                        <?php if ($is_vid): ?>
                            <video class="thumb-item <?php echo $active; ?>" src="<?php echo htmlspecialchars($media); ?>" onclick="swapMedia(this, 'video')"></video>
                        <?php else: ?>
                            <img class="thumb-item <?php echo $active; ?>" src="<?php echo htmlspecialchars($media); ?>" alt="Thumb" onclick="swapMedia(this, 'img')">
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Coluna Direita: Dados -->
            <div class="product-info-content">
                <!-- Badge de Tipo de Edição -->
                <?php 
                $tipo_edicao = $produto['tipo_edicao'] ?? 'unica';
                $badge_text = $tipo_edicao === 'unica' ? 'Peça Única' : ($tipo_edicao === 'limitada' ? "Edição Limitada ({$produto['numero_edicao']}/{$produto['total_edicoes']})" : 'Coleção Aberta');
                ?>
                <span class="edition-badge"><?php echo htmlspecialchars($badge_text); ?></span>
                
                <!-- SKU -->
                <?php if (!empty($produto['sku'])): ?>
                <div class="prod-sku">SKU: <?php echo htmlspecialchars($produto['sku']); ?></div>
                <?php endif; ?>
                
                <span class="prod-category"><?php echo htmlspecialchars($produto['categoria'] ?? 'Obra de Arte'); ?></span>
                <h1 class="prod-title"><?php echo htmlspecialchars($produto['nome']); ?></h1>
                
                <div class="prod-price-box">
                    <span class="price-value">R$ <?php echo $preco_formatado; ?></span>
                    <?php if (!empty($produto['preco_promocional']) && $produto['preco_promocional'] < $produto['preco']): ?>
                        <span class="price-old">R$ <?php echo $preco_cheio_formatado; ?></span>
                    <?php endif; ?>
                </div>

                <!-- Especificações Completas -->
                <div class="specs-panel">
                    <div class="specs-title"><i class="fas fa-certificate"></i> Identificação e Autenticidade</div>
                    
                    <?php if (!empty($produto['certificado_id'])): ?>
                    <div class="spec-row">
                        <span class="spec-label">Certificado nº:</span>
                        <span class="spec-val"><?php echo htmlspecialchars($produto['certificado_id']); ?></span>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($produto['codigo_rastreio_interno'])): ?>
                    <div class="spec-row">
                        <span class="spec-label">Código Interno:</span>
                        <span class="spec-val"><?php echo htmlspecialchars($produto['codigo_rastreio_interno']); ?></span>
                    </div>
                    <?php endif; ?>

                    <div class="spec-row">
                        <span class="spec-label">Status:</span>
                        <span class="spec-val" style="color: <?php echo $disponivel ? 'green' : 'red'; ?>">
                            <?php echo $status_label; ?>
                        </span>
                    </div>
                </div>

                <div class="specs-panel">
                    <div class="specs-title"><i class="fas fa-ruler-combined"></i> Especificações Físicas</div>
                    
                    <?php if (!empty($produto['material'])): ?>
                    <div class="spec-row">
                        <span class="spec-label">Material:</span>
                        <span class="spec-val"><?php echo htmlspecialchars($produto['material']); ?></span>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($produto['peso'])): ?>
                    <div class="spec-row">
                        <span class="spec-label">Peso:</span>
                        <span class="spec-val"><?php echo htmlspecialchars($produto['peso']); ?></span>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($produto['dimensoes'])): ?>
                    <div class="spec-row">
                        <span class="spec-label">Dimensões:</span>
                        <span class="spec-val"><?php echo htmlspecialchars($produto['dimensoes']); ?></span>
                    </div>
                    <?php endif; ?>
                </div>

                <p class="desc-text">
                    <?php echo nl2br(htmlspecialchars($produto['descricao_completa'] ?? $produto['descricao_curta'] ?? 'Sem descrição disponível.')); ?>
                </p>

                <?php if (!empty($produto['historia_obra'])): ?>
                <div style="margin-top: 20px; padding: 15px; background: rgba(0,0,0,0.03); border-left: 3px solid var(--gold, #d4af37); border-radius: 0 8px 8px 0;">
                    <strong style="color: var(--gold, #d4af37);"><i class="fas fa-book-open"></i> História da Obra:</strong>
                    <p style="margin-top: 10px; font-style: italic;"><?php echo nl2br(htmlspecialchars($produto['historia_obra'])); ?></p>
                </div>
                <?php endif; ?>

                <form action="api/carrinho_action.php" method="POST" class="action-area">
                    <input type="hidden" name="acao" value="add">
                    <input type="hidden" name="produto_id" value="<?php echo $produto['id']; ?>">
                    
                    <input type="number" name="quantidade" value="1" min="1" max="<?php echo $estoque; ?>" class="input-qty" <?php echo !$disponivel ? 'disabled' : ''; ?>>
                    
                    <button type="submit" name="adicionar" class="btn-buy" <?php echo !$disponivel ? 'disabled' : ''; ?>>
                        <i class="fas fa-shopping-bag"></i> <?php echo $disponivel ? 'Adicionar à Bolsa' : 'Indisponível'; ?>
                    </button>
                </form>
                
                <div style="margin-top: 20px; font-size: 0.85rem; color: var(--text-secondary, #666); display: flex; gap: 15px; flex-wrap: wrap;">
                    <span><i class="fas fa-truck" style="color: var(--gold, #d4af37);"></i> Frete Internacional Segurado</span>
                    <span><i class="fas fa-file-contract" style="color: var(--gold, #d4af37);"></i> Certificado Incluso</span>
                    <span><i class="fab fa-whatsapp" style="color: var(--gold, #d4af37);"></i> Curadoria Especializada</span>
                </div>
            </div>
        </div>

    <?php endif; ?>
</main>

<?php include 'includes/footer.php'; ?>

<script>
    function swapMedia(el, type) {
        const mainBox = document.getElementById('mainMediaBox');
        const src = el.src;
        
        document.querySelectorAll('.thumb-item').forEach(t => t.classList.remove('active'));
        el.classList.add('active');

        if (type === 'video') {
            mainBox.innerHTML = `<video src="${src}" autoplay muted loop playsinline></video>`;
        } else {
            mainBox.innerHTML = `<img src="${src}" alt="Produto">`;
        }
    }
</script>

</body>
</html>

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
