<?php
/**
 * checkout.php - Cria sessão de pagamento Stripe
 * -----------------------------------------------------
 *  Recebe produto_id via POST, verifica estoque e login,
 *  cria Checkout Session no Stripe e redireciona o cliente.
 * -----------------------------------------------------
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/cliente_auth.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método não permitido']);
    exit;
}

// Verifica se cliente está logado
if (!cliente_logado()) {
    http_response_code(401);
    echo json_encode(['error' => 'Cliente não autenticado']);
    exit;
}

$produto_id = isset($_POST['produto_id']) ? (int)$_POST['produto_id'] : 0;
if ($produto_id <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Produto inválido']);
    exit;
}

// Busca dados do produto
$stmt = $pdo->prepare('SELECT * FROM produtos WHERE id = :id');
$stmt->execute([':id' => $produto_id]);
$produto = $stmt->fetch();

if (!$produto) {
    http_response_code(404);
    echo json_encode(['error' => 'Produto não encontrado']);
    exit;
}

// Verifica estoque
$estoque = (int)$produto['estoque'];
if ($estoque <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Produto esgotado']);
    exit;
}

// Carrega SDK do Stripe via Composer ou CDN
if (!class_exists('\\Stripe\\Stripe')) {
    // Fallback: usa API HTTP direta se SDK não estiver disponível
    // Em produção, instale via: composer require stripe/stripe-php
    $sdk_available = false;
} else {
    $sdk_available = true;
    \Stripe\Stripe::setApiKey(STRIPE_SECRET_KEY);
}

try {
    // Cria registro de pedido pendente
    $cliente_id = cliente_id();
    
    if (!$cliente_id) {
        http_response_code(401);
        echo json_encode(['error' => 'Cliente não autenticado. Faça login para continuar.']);
        exit;
    }
    
    $total = (float)$produto['preco'];
    
    // Verifica se a coluna cliente_id existe na tabela pedidos
    try {
        $check = $pdo->query("DESCRIBE pedidos");
        $cols = $check->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array('cliente_id', $cols)) {
            throw new Exception('Tabela pedidos não possui coluna cliente_id. Execute a migração do banco de dados.');
        }
    } catch (PDOException $e) {
        throw new Exception('Tabela pedidos não existe. Execute a migração do banco de dados (update_schema.sql).');
    }
    
    $insert = $pdo->prepare('INSERT INTO pedidos (cliente_id, produto_id, status, total) VALUES (:cliente_id, :produto_id, :status, :total)');
    $insert->execute([
        ':cliente_id' => $cliente_id,
        ':produto_id' => $produto_id,
        ':status' => 'pendente',
        ':total' => $total
    ]);
    $pedido_id = $pdo->lastInsertId();
    
    if ($sdk_available) {
        // Usa SDK do Stripe
        $session = \Stripe\Checkout\Session::create([
            'payment_method_types' => ['card'],
            'line_items' => [[
                'price_data' => [
                    'currency' => 'brl',
                    'product_data' => [
                        'name' => $produto['nome'],
                        'description' => $produto['descricao_curta'] ?? '',
                        'images' => [SITE_URL . '/' . $produto['imagem_url']],
                    ],
                    'unit_amount' => (int)($total * 100), // Stripe usa centavos
                ],
                'quantity' => 1,
            ]],
            'mode' => 'payment',
            'success_url' => SITE_URL . '/stripe/success.php?session_id={CHECKOUT_SESSION_ID}&pedido_id=' . $pedido_id,
            'cancel_url' => SITE_URL . '/stripe/cancel.php?pedido_id=' . $pedido_id,
            'metadata' => [
                'pedido_id' => $pedido_id,
                'produto_id' => $produto_id,
                'cliente_id' => $cliente_id
            ],
            'client_reference_id' => (string)$cliente_id,
        ]);
        
        // Atualiza pedido com session ID
        $update = $pdo->prepare('UPDATE pedidos SET stripe_session_id = :session_id WHERE id = :id');
        $update->execute([
            ':session_id' => $session->id,
            ':id' => $pedido_id
        ]);
        
        echo json_encode([
            'success' => true,
            'sessionId' => $session->id,
            'publicKey' => STRIPE_PUBLISHABLE_KEY,
            'url' => $session->url
        ]);
    } else {
        // Modo fallback sem SDK - apenas simula para demonstração
        // EM PRODUÇÃO: instale o SDK via composer
        echo json_encode([
            'success' => false,
            'error' => 'SDK do Stripe não instalado. Execute: composer require stripe/stripe-php',
            'modo_demo' => true,
            'pedido_id' => $pedido_id,
            'valor' => $total,
            'produto' => $produto['nome']
        ]);
    }
    
} catch (Exception $e) {
    error_log('Stripe Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Erro ao criar sessão de pagamento: ' . $e->getMessage()]);
}
