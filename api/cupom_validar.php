<?php
/**
 * api/cupom_validar.php - Validação de cupons via AJAX
 * -----------------------------------------------------
 *  Valida cupons de desconto para aplicação no carrinho.
 *  Requer autenticação de cliente logado.
 * -----------------------------------------------------
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/cliente_auth.php';

header('Content-Type: application/json');

// Verifica se o cliente está logado
if (!cliente_logado()) {
    echo json_encode(['valido' => false, 'erro' => 'É necessário estar logado para aplicar cupons.']);
    exit;
}

// Verifica método HTTP
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['valido' => false, 'erro' => 'Método não permitido.']);
    exit;
}

// Lê dados JSON
$input = json_decode(file_get_contents('php://input'), true);
$codigo = strtoupper(trim($input['codigo'] ?? ''));

if (empty($codigo)) {
    echo json_encode(['valido' => false, 'erro' => 'Código do cupom é obrigatório.']);
    exit;
}

try {
    // Busca cupom no banco
    $stmt = $pdo->prepare('SELECT * FROM cupons WHERE codigo = :codigo AND ativo = 1');
    $stmt->execute([':codigo' => $codigo]);
    $cupom = $stmt->fetch();

    if (!$cupom) {
        echo json_encode(['valido' => false, 'erro' => 'Cupom inválido ou inativo.']);
        exit;
    }

    // Verifica validade
    if ($cupom['validade'] && strtotime($cupom['validade']) < time()) {
        echo json_encode(['valido' => false, 'erro' => 'Cupom expirado.']);
        exit;
    }

    // Verifica usos máximos
    if ($cupom['usos_maximos'] && $cupom['usos_total'] >= $cupom['usos_maximos']) {
        echo json_encode(['valido' => false, 'erro' => 'Cupom atingiu o limite de usos.']);
        exit;
    }

    // Calcula subtotal do carrinho
    $carrinho = $_SESSION['carrinho'] ?? [];
    $subtotal = 0;
    
    if (!empty($carrinho)) {
        $ids = array_keys($carrinho);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $pdo->prepare("SELECT preco FROM produtos WHERE id IN ($placeholders)");
        $stmt->execute($ids);
        $produtos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($produtos as $p) {
            $qty = $carrinho[$p['id']] ?? 0;
            $subtotal += $p['preco'] * $qty;
        }
    }

    // Verifica compra mínima
    if ($cupom['minimo_compra'] > 0 && $subtotal < $cupom['minimo_compra']) {
        echo json_encode([
            'valido' => false, 
            'erro' => sprintf('Compra mínima de R$ %s necessária.', number_format($cupom['minimo_compra'], 2, ',', '.'))
        ]);
        exit;
    }

    // Calcula desconto
    $desconto = 0;
    if ($cupom['tipo_desconto'] === 'percentual') {
        $desconto = $cupom['valor'];
    } else {
        // Desconto fixo
        $desconto = ($cupom['valor'] / max($subtotal, 1)) * 100;
    }

    // Retorna sucesso
    echo json_encode([
        'valido' => true,
        'codigo' => $cupom['codigo'],
        'tipo_desconto' => $cupom['tipo_desconto'],
        'valor' => (float)$cupom['valor'],
        'desconto' => $cupom['tipo_desconto'] === 'percentual' ? $cupom['valor'] : number_format($cupom['valor'], 2, ',', '.'),
        'minimo_compra' => (float)$cupom['minimo_compra']
    ]);

} catch (PDOException $e) {
    error_log('Erro ao validar cupom: ' . $e->getMessage());
    echo json_encode(['valido' => false, 'erro' => 'Erro ao validar cupom. Tente novamente.']);
}
