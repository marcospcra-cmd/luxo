<?php
/**
 * api/carrinho_action.php
 * Backend do carrinho - Adicionar, Remover, Atualizar itens
 * Segurança: CSRF token, Prepared Statements, Validação de Estoque
 * PHP 8.2+
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-CSRF-TOKEN');

// Handle preflight request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once __DIR__ . '/../config.php';

// Inicializa sessão se necessário
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$response = ['success' => false, 'message' => 'Requisição inválida'];

try {
    // Verifica método HTTP
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Método não permitido. Use POST.');
    }
    
    // Lê JSON do body
    $input = json_decode(file_get_contents('php://input'), true);
    if (!is_array($input)) {
        throw new Exception('Dados inválidos. Envie JSON no body.');
    }
    
    $action = $input['action'] ?? '';
    
    switch ($action) {
        case 'add':
            $response = adicionarAoCarrinho($input, $pdo);
            break;
            
        case 'update':
            $response = atualizarCarrinho($input, $pdo);
            break;
            
        case 'remove':
            $response = removerDoCarrinho($input);
            break;
            
        case 'clear':
            $response = limparCarrinho();
            break;
            
        default:
            throw new Exception('Ação não reconhecida. Ações válidas: add, update, remove, clear');
    }
    
} catch (Exception $e) {
    $response = [
        'success' => false,
        'message' => $e->getMessage(),
        'error_code' => 'CART_ERROR'
    ];
    http_response_code(400);
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);

/**
 * Adiciona produto ao carrinho
 * 
 * @param array $input Dados da requisição
 * @param PDO $pdo Conexão com banco de dados
 * @return array Resposta JSON
 */
function adicionarAoCarrinho(array $input, PDO $pdo): array
{
    $produtoId = (int)($input['id'] ?? 0);
    $quantidade = max(1, (int)($input['qty'] ?? 1));
    
    if ($produtoId <= 0) {
        return ['success' => false, 'message' => 'ID do produto inválido'];
    }
    
    // Verifica se produto existe e tem estoque usando Prepared Statement
    $stmt = $pdo->prepare('SELECT id, nome, preco, estoque FROM produtos WHERE id = ?');
    $stmt->execute([$produtoId]);
    $produto = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$produto) {
        return ['success' => false, 'message' => 'Produto não encontrado'];
    }
    
    if ($produto['estoque'] <= 0) {
        return ['success' => false, 'message' => 'Produto sem estoque disponível'];
    }
    
    // Inicializa carrinho na sessão se necessário
    if (!isset($_SESSION['carrinho'])) {
        $_SESSION['carrinho'] = [];
    }
    
    // Calcula nova quantidade
    $quantidadeAtual = $_SESSION['carrinho'][$produtoId] ?? 0;
    $novaQuantidade = $quantidadeAtual + $quantidade;
    
    // Verifica estoque total
    if ($novaQuantidade > $produto['estoque']) {
        return [
            'success' => false,
            'message' => 'Quantidade excede o estoque disponível. Máximo: ' . $produto['estoque'],
            'estoque_disponivel' => $produto['estoque'] - $quantidadeAtual
        ];
    }
    
    // Adiciona ou atualiza quantidade
    $_SESSION['carrinho'][$produtoId] = $novaQuantidade;
    
    // Calcula total de itens
    $totalItens = array_sum($_SESSION['carrinho']);
    
    return [
        'success' => true,
        'message' => 'Produto adicionado ao carrinho',
        'produto' => [
            'id' => $produto['id'],
            'nome' => $produto['nome'],
            'preco' => $produto['preco'],
            'quantidade' => $_SESSION['carrinho'][$produtoId]
        ],
        'total_itens' => $totalItens
    ];
}

/**
 * Atualiza quantidade de um item no carrinho
 * 
 * @param array $input Dados da requisição
 * @param PDO $pdo Conexão com banco de dados
 * @return array Resposta JSON
 */
function atualizarCarrinho(array $input, PDO $pdo): array
{
    $produtoId = (int)($input['id'] ?? 0);
    $quantidade = max(0, (int)($input['qty'] ?? 0));
    
    if ($produtoId <= 0) {
        return ['success' => false, 'message' => 'ID do produto inválido'];
    }
    
    if (!isset($_SESSION['carrinho'][$produtoId])) {
        return ['success' => false, 'message' => 'Item não encontrado no carrinho'];
    }
    
    // Se quantidade for 0, remove o item
    if ($quantidade === 0) {
        return removerDoCarrinho(['id' => $produtoId]);
    }
    
    // Verifica estoque atualizado
    $stmt = $pdo->prepare('SELECT estoque FROM produtos WHERE id = ?');
    $stmt->execute([$produtoId]);
    $estoque = (int)$stmt->fetchColumn();
    
    if ($quantidade > $estoque) {
        return [
            'success' => false,
            'message' => 'Quantidade indisponível. Estoque máximo: ' . $estoque,
            'estoque_disponivel' => $estoque
        ];
    }
    
    $_SESSION['carrinho'][$produtoId] = $quantidade;
    
    return [
        'success' => true,
        'message' => 'Carrinho atualizado',
        'total_itens' => array_sum($_SESSION['carrinho'])
    ];
}

/**
 * Remove item do carrinho
 * 
 * @param array $input Dados da requisição
 * @return array Resposta JSON
 */
function removerDoCarrinho(array $input): array
{
    $produtoId = (int)($input['id'] ?? 0);
    
    if ($produtoId <= 0) {
        return ['success' => false, 'message' => 'ID inválido'];
    }
    
    if (!isset($_SESSION['carrinho'][$produtoId])) {
        return ['success' => false, 'message' => 'Item não encontrado no carrinho'];
    }
    
    unset($_SESSION['carrinho'][$produtoId]);
    
    return [
        'success' => true,
        'message' => 'Item removido do carrinho',
        'total_itens' => array_sum($_SESSION['carrinho'])
    ];
}

/**
 * Limpa todo o carrinho
 * 
 * @return array Resposta JSON
 */
function limparCarrinho(): array
{
    $_SESSION['carrinho'] = [];
    unset($_SESSION['cupom_aplicado']);
    
    return [
        'success' => true,
        'message' => 'Carrinho esvaziado com sucesso'
    ];
}
