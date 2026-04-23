<?php
session_start();
require_once 'config.php';

$cart = $_SESSION['carrinho'] ?? [];
$total = 0;
$produtos = [];

if (!empty($cart)) {
    $ids = implode(',', array_keys($cart));
    $stmt = $pdo->query("SELECT * FROM produtos WHERE id IN ($ids)");
    $produtos = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

foreach ($produtos as $p) {
    $qty = $cart[$p['id']] ?? 0;
    $total += $p['preco'] * $qty;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Carrinho - Luxo Store</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="container" style="padding-top: 120px; min-height: 80vh;">
        <h1 style="font-size: 3rem; margin-bottom: 2rem;">Seu Carrinho</h1>
        
        <?php if (empty($cart)): ?>
            <div style="text-align: center; padding: 4rem;">
                <p style="font-size: 1.2rem; color: var(--text-light);">Seu carrinho está vazio.</p>
                <a href="index.php" class="btn btn-primary" style="margin-top: 2rem;">Continuar Comprando</a>
            </div>
        <?php else: ?>
            <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 3rem;">
                <div class="cart-items">
                    <?php foreach ($produtos as $p): 
                        $qty = $cart[$p['id']];
                        $subtotal = $p['preco'] * $qty;
                    ?>
                    <div class="cart-item">
                        <img src="<?= htmlspecialchars($p['imagem_url']) ?>" alt="<?= htmlspecialchars($p['nome']) ?>" class="cart-item-image">
                        <div class="cart-item-details">
                            <h3 class="cart-item-name"><?= htmlspecialchars($p['nome']) ?></h3>
                            <p class="cart-item-price">R$ <?= number_format($p['preco'], 2, ',', '.') ?></p>
                            <div style="display: flex; align-items: center; gap: 1rem; margin-top: 1rem;">
                                <button onclick="updateCart(<?= $p['id'] ?>, <?= $qty - 1 ?>)" class="btn btn-outline" style="padding: 0.5rem 1rem;">-</button>
                                <span><?= $qty ?></span>
                                <button onclick="updateCart(<?= $p['id'] ?>, <?= $qty + 1 ?>)" class="btn btn-outline" style="padding: 0.5rem 1rem;">+</button>
                                <button onclick="removeFromCart(<?= $p['id'] ?>)" class="btn btn-secondary" style="margin-left: auto; padding: 0.5rem 1rem;">Remover</button>
                            </div>
                        </div>
                        <div style="text-align: right;">
                            <p style="font-size: 1.3rem; font-weight: 700; color: var(--secondary);">R$ <?= number_format($subtotal, 2, ',', '.') ?></p>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                
                <div class="cart-footer" style="border-radius: var(--radius-lg);">
                    <h2 style="margin-bottom: 1.5rem;">Resumo do Pedido</h2>
                    <div class="cart-total">
                        <span>Total:</span>
                        <span style="color: var(--secondary);">R$ <?= number_format($total, 2, ',', '.') ?></span>
                    </div>
                    
                    <!-- Campo de Cupom -->
                    <div class="form-group">
                        <label class="form-label">Cupom de Desconto</label>
                        <div style="display: flex; gap: 0.5rem;">
                            <input type="text" id="cupom" class="form-control" placeholder="Digite seu cupom">
                            <button onclick="aplicarCupom()" class="btn btn-secondary">Aplicar</button>
                        </div>
                        <p id="msg-cupom" style="margin-top: 0.5rem; font-size: 0.9rem;"></p>
                    </div>
                    
                    <!-- Simulador de Frete -->
                    <div class="form-group">
                        <label class="form-label">Calcular Frete</label>
                        <input type="text" id="cep" class="form-control" placeholder="CEP" maxlength="9">
                        <button onclick="calcularFrete()" class="btn btn-secondary" style="width: 100%; margin-top: 0.5rem;">Calcular</button>
                        <div id="resultado-frete" style="margin-top: 1rem;"></div>
                    </div>
                    
                    <a href="stripe/checkout.php" class="btn btn-primary" style="width: 100%; margin-top: 1.5rem;">Finalizar Compra</a>
                    <a href="index.php" class="btn btn-outline" style="width: 100%; margin-top: 1rem;">Continuar Comprando</a>
                </div>
            </div>
        <?php endif; ?>
    </div>
    
    <script>
    function updateCart(id, qty) {
        if (qty < 1) return;
        fetch('api/carrinho_action.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({action: 'update', id, qty})
        }).then(() => location.reload());
    }
    
    function removeFromCart(id) {
        fetch('api/carrinho_action.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({action: 'remove', id})
        }).then(() => location.reload());
    }
    
    function aplicarCupom() {
        const codigo = document.getElementById('cupom').value;
        fetch('api/cupom_validar.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({codigo})
        })
        .then(r => r.json())
        .then(data => {
            const msg = document.getElementById('msg-cupom');
            if (data.valido) {
                msg.style.color = 'var(--success)';
                msg.textContent = 'Cupom aplicado: ' + data.desconto;
            } else {
                msg.style.color = 'var(--danger)';
                msg.textContent = data.erro || 'Cupom inválido';
            }
        });
    }
    
    function calcularFrete() {
        const cep = document.getElementById('cep').value;
        // Implementar lógica de frete aqui
        document.getElementById('resultado-frete').innerHTML = '<p>Frete para ' + cep + ': R$ 25,00 (PAC)</p>';
    }
    </script>
</body>
</html>
