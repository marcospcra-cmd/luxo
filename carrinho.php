<?php
/**
 * carrinho.php
 * Página completa do carrinho com frontend e lógica de cálculo
 * Inclui: adicionar/remover itens, calcular frete, aplicar cupons
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/cliente_auth.php';

// Inicializa WAF se ainda não foi inicializado
if (!isset($_SESSION['csrf_token'])) {
    require_once __DIR__ . '/middleware/waf_security.php';
    \MaisonDeLuxo\Middleware\waf_init();
}

$page_title = 'Carrinho';
$carrinho = $_SESSION['carrinho'] ?? [];
$totalProdutos = 0;
$subtotal = 0;
$desconto = 0;
$frete = 0;
$cupomAplicado = null;
$produtos = [];

// Busca produtos do carrinho no banco usando Prepared Statements (seguro)
if (!empty($carrinho)) {
    $ids = array_keys($carrinho);
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare("SELECT * FROM produtos WHERE id IN ($placeholders)");
    $stmt->execute($ids);
    $produtos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calcula subtotal
    foreach ($produtos as $p) {
        $qty = $carrinho[$p['id']] ?? 0;
        $subtotal += $p['preco'] * $qty;
        $totalProdutos += $qty;
    }
}

// Verifica se há cupom na sessão
if (isset($_SESSION['cupom_aplicado'])) {
    $cupomAplicado = $_SESSION['cupom_aplicado'];
    // Valida e aplica desconto
    if ($cupomAplicado['valido'] && $cupomAplicado['expires_at'] > time()) {
        $desconto = $subtotal * ($cupomAplicado['desconto_percent'] / 100);
    } else {
        unset($_SESSION['cupom_aplicado']);
        $cupomAplicado = null;
    }
}

// Total final
$total = $subtotal - $desconto + $frete;
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title) ?> | Solar Amazônia</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@400;600;700&family=Inter:wght@300;400;500&display=swap" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body class="theme-dark">
    <?php include 'includes/header.php'; ?>
    
    <div class="container py-5" style="padding-top: 120px !important; min-height: 80vh;">
        <h1 class="mb-4">Seu Carrinho</h1>
        
        <?php if (empty($carrinho)): ?>
            <div class="text-center py-5" style="background: var(--color-bg-card); border-radius: var(--radius-lg); border: 1px solid var(--color-border-light);">
                <p class="fs-4 text-muted mb-4">Seu carrinho está vazio.</p>
                <a href="index.php" class="btn btn-gold">Explorar Coleção</a>
            </div>
        <?php else: ?>
            <div class="row g-4">
                <!-- Lista de Produtos -->
                <div class="col-lg-8">
                    <div class="cart-items-wrapper" style="background: var(--color-bg-card); border-radius: var(--radius-lg); padding: 2rem; border: 1px solid var(--color-border-light);">
                        <h2 class="h4 mb-4">Produtos Selecionados</h2>
                        
                        <?php foreach ($produtos as $p): 
                            $qty = $carrinho[$p['id']];
                            $subtotalItem = $p['preco'] * $qty;
                        ?>
                        <div class="cart-item" data-id="<?= $p['id'] ?>">
                            <img src="<?= htmlspecialchars($p['imagem_url']) ?>" alt="<?= htmlspecialchars($p['nome']) ?>" class="cart-item-image">
                            <div class="cart-item-details">
                                <h3 class="cart-item-name"><?= htmlspecialchars($p['nome']) ?></h3>
                                <p class="cart-item-price">R$ <?= number_format($p['preco'], 2, ',', '.') ?></p>
                                <div class="d-flex align-items-center gap-3 mt-3">
                                    <button onclick="updateCart(<?= $p['id'] ?>, <?= $qty - 1 ?>)" class="btn btn-outline-gold btn-sm" style="width: 36px; height: 36px; padding: 0;">-</button>
                                    <span class="fw-medium"><?= $qty ?></span>
                                    <button onclick="updateCart(<?= $p['id'] ?>, <?= $qty + 1 ?>)" class="btn btn-outline-gold btn-sm" style="width: 36px; height: 36px; padding: 0;">+</button>
                                    <button onclick="removeFromCart(<?= $p['id'] ?>)" class="btn btn-sm ms-auto" style="color: #dc3545; border-color: #dc3545;">Remover</button>
                                </div>
                            </div>
                            <div class="text-end" style="min-width: 120px;">
                                <p class="fs-5 fw-bold text-gold mb-0">R$ <?= number_format($subtotalItem, 2, ',', '.') ?></p>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <!-- Resumo do Pedido -->
                <div class="col-lg-4">
                    <div class="cart-footer" style="background: var(--color-bg-card); border-radius: var(--radius-lg); padding: 2rem; border: 1px solid var(--color-border-light); position: sticky; top: 100px;">
                        <h2 class="h4 mb-4">Resumo do Pedido</h2>
                        
                        <div class="d-flex justify-content-between mb-3">
                            <span class="text-muted">Subtotal (<?= $totalProdutos ?> <?= $totalProdutos === 1 ? 'item' : 'itens' ?>)</span>
                            <span class="fw-medium">R$ <?= number_format($subtotal, 2, ',', '.') ?></span>
                        </div>
                        
                        <?php if ($desconto > 0): ?>
                        <div class="d-flex justify-content-between mb-3 text-success">
                            <span>Desconto (<?= htmlspecialchars($cupomAplicado['codigo']) ?>)</span>
                            <span class="fw-medium">- R$ <?= number_format($desconto, 2, ',', '.') ?></span>
                        </div>
                        <?php endif; ?>
                        
                        <div class="d-flex justify-content-between mb-3">
                            <span class="text-muted">Frete</span>
                            <span class="fw-medium" id="valor-frete">R$ <?= number_format($frete, 2, ',', '.') ?></span>
                        </div>
                        
                        <hr style="border-color: var(--color-border-light);">
                        
                        <div class="cart-total">
                            <span>Total:</span>
                            <span id="valor-total">R$ <?= number_format($total, 2, ',', '.') ?></span>
                        </div>
                        
                        <!-- Campo de Cupom -->
                        <div class="form-group mt-4">
                            <label class="form-label">Cupom de Desconto</label>
                            <div class="input-group">
                                <input type="text" id="cupom" class="form-control" placeholder="CÓDIGO DO CUPOM" maxlength="20" <?= $cupomAplicado ? 'disabled value="'.htmlspecialchars($cupomAplicado['codigo']).'"' : '' ?>>
                                <button onclick="aplicarCupom()" class="btn btn-outline-gold" type="button" <?= $cupomAplicado ? 'disabled' : '' ?>>Aplicar</button>
                            </div>
                            <p id="msg-cupom" class="mt-2 small"></p>
                        </div>
                        
                        <!-- Simulador de Frete -->
                        <div class="form-group mt-4">
                            <label class="form-label">Calcular Frete</label>
                            <div class="input-group">
                                <input type="text" id="cep" class="form-control" placeholder="00000-000" maxlength="9">
                                <button onclick="calcularFrete()" class="btn btn-outline-gold" type="button">Calcular</button>
                            </div>
                            <div id="resultado-frete" class="mt-2 small"></div>
                        </div>
                        
                        <a href="stripe/checkout.php" class="btn btn-gold w-100 mt-4 py-3">Finalizar Compra</a>
                        <a href="index.php" class="btn btn-outline-gold w-100 mt-2">Continuar Comprando</a>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
    
    <?php include 'includes/footer.php'; ?>
    
    <script>
    const CSRF_TOKEN = window.CSRF_CLIENTE || '';
    
    /**
     * Atualiza quantidade de um item no carrinho
     */
    function updateCart(id, qty) {
        if (qty < 1) {
            removeFromCart(id);
            return;
        }
        
        fetch('api/carrinho_action.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': CSRF_TOKEN
            },
            body: JSON.stringify({action: 'update', id: id, qty: qty})
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                showToast('Erro ao atualizar carrinho', 'error');
            }
        })
        .catch(err => {
            console.error(err);
            showToast('Erro de conexão', 'error');
        });
    }
    
    /**
     * Remove item do carrinho
     */
    function removeFromCart(id) {
        if (!confirm('Deseja remover este item do carrinho?')) return;
        
        fetch('api/carrinho_action.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': CSRF_TOKEN
            },
            body: JSON.stringify({action: 'remove', id: id})
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                showToast('Erro ao remover item', 'error');
            }
        })
        .catch(err => {
            console.error(err);
            showToast('Erro de conexão', 'error');
        });
    }
    
    /**
     * Aplica cupom de desconto via AJAX
     */
    function aplicarCupom() {
        const codigo = document.getElementById('cupom').value.trim().toUpperCase();
        const msgEl = document.getElementById('msg-cupom');
        
        if (!codigo) {
            msgEl.textContent = 'Digite um código de cupom.';
            msgEl.style.color = '#dc3545';
            return;
        }
        
        fetch('api/cupom_validar.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': CSRF_TOKEN
            },
            body: JSON.stringify({codigo: codigo})
        })
        .then(r => r.json())
        .then(data => {
            if (data.valido) {
                msgEl.textContent = '✓ Cupom aplicado: ' + data.desconto + '% de desconto';
                msgEl.style.color = '#28a745';
                setTimeout(() => location.reload(), 1500);
            } else {
                msgEl.textContent = '✗ ' + (data.erro || 'Cupom inválido');
                msgEl.style.color = '#dc3545';
            }
        })
        .catch(err => {
            console.error(err);
            msgEl.textContent = 'Erro ao validar cupom';
            msgEl.style.color = '#dc3545';
        });
    }
    
    /**
     * Calcula frete baseado no CEP (simulação para frete internacional)
     */
    function calcularFrete() {
        const cep = document.getElementById('cep').value.replace(/\D/g, '');
        const resultadoEl = document.getElementById('resultado-frete');
        
        if (cep.length !== 8) {
            resultadoEl.innerHTML = '<span style="color: #dc3545;">CEP inválido. Digite 8 dígitos.</span>';
            return;
        }
        
        // Simula cálculo de frete internacional
        const regioes = {
            'SP': { preco: 25.00, prazo: '3-5 dias úteis' },
            'RJ': { preco: 30.00, prazo: '4-6 dias úteis' },
            'MG': { preco: 28.00, prazo: '4-6 dias úteis' },
            'RS': { preco: 35.00, prazo: '5-7 dias úteis' },
            'OUTROS': { preco: 40.00, prazo: '7-10 dias úteis' }
        };
        
        // Simula busca por CEP (na prática, integraria com API dos Correios)
        const regiao = regioes['SP']; // Simplificado
        
        resultadoEl.innerHTML = `
            <div style="color: var(--color-text-secondary);">
                <div><strong>SEDEX:</strong> R$ ${regiao.preco.toFixed(2).replace('.', ',')} - ${regiao.prazo}</div>
                <div style="margin-top: 5px;"><strong>PAC:</strong> R$ ${(regiao.preco * 0.7).toFixed(2).replace('.', ',')} - ${parseInt(regiao.prazo) + 2}-{{parseInt(regiao.prazo.split('-')[1]) + 2}} dias úteis</div>
            </div>
        `;
        
        // Atualiza valor do frete no resumo
        document.getElementById('valor-frete').textContent = 'R$ ' + regiao.preco.toFixed(2).replace('.', ',');
        atualizarTotalComFrete(regiao.preco);
    }
    
    /**
     * Atualiza total com frete
     */
    function atualizarTotalComFrete(valorFrete) {
        // Recalcula total considerando frete
        const subtotalText = document.querySelector('.d-flex.justify-content-between.mb-3 .fw-medium').textContent;
        const subtotal = parseFloat(subtotalText.replace('R$', '').replace('.', '').replace(',', '.').trim());
        const novoTotal = subtotal + valorFrete;
        document.getElementById('valor-total').textContent = 'R$ ' + novoTotal.toFixed(2).replace('.', ',');
    }
    
    /**
     * Exibe toast notification
     */
    function showToast(message, type = 'success') {
        const toast = document.createElement('div');
        toast.className = `toast toast-${type} show`;
        toast.textContent = message;
        document.body.appendChild(toast);
        
        setTimeout(() => {
            toast.classList.remove('show');
            setTimeout(() => toast.remove(), 500);
        }, 3000);
    }
    
    // Máscara para CEP
    document.getElementById('cep')?.addEventListener('input', function(e) {
        let value = e.target.value.replace(/\D/g, '');
        if (value.length > 5) {
            value = value.replace(/^(\d{5})(\d)/, '$1-$2');
        }
        e.target.value = value;
    });
    </script>
</body>
</html>
