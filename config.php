<?php
/**
 * config.php
 * -----------------------------------------------------
 *  Conexão segura ao MySQL via PDO (Hostinger).
 *  Edite as credenciais abaixo conforme o painel hPanel.
 * -----------------------------------------------------
 */

// === CREDENCIAIS DO BANCO (preencher na Hostinger) ===
define('DB_HOST', 'localhost');         // geralmente 'localhost' na Hostinger
define('DB_NAME', 'u000000000_luxo');   // nome do banco criado
define('DB_USER', 'root');   // usuário do banco
define('DB_PASS', '');       // senha do banco
define('DB_CHARSET', 'utf8mb4');

// === TEMA PADRÃO DA LOJA ('dark' | 'light') ===
define('TEMA_PADRAO', 'dark');

// === WHATSAPP DO ESPECIALISTA (formato internacional, só dígitos) ===
define('WHATSAPP_NUMERO', '5511999999999');

// === Sessão segura ===
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'httponly' => true,
        'secure'   => isset($_SERVER['HTTPS']),
        'samesite' => 'Lax',
    ]);
    session_start();
}

// === Conexão PDO ===
try {
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
} catch (PDOException $e) {
    // Em produção, não exiba detalhes do erro ao usuário final
    http_response_code(500);
    exit('Erro de conexão com o banco de dados.');
}

// === Tema atual (cookie) ===
if (isset($_GET['tema']) && in_array($_GET['tema'], ['dark','light'], true)) {
    setcookie('tema', $_GET['tema'], time() + 60*60*24*365, '/');
    $_COOKIE['tema'] = $_GET['tema'];
}
$TEMA_ATUAL = $_COOKIE['tema'] ?? TEMA_PADRAO;

// === Configurações do Stripe ===
define('STRIPE_SECRET_KEY', 'sk_test_YOUR_SECRET_KEY'); // Troque pela sua chave secreta
define('STRIPE_PUBLISHABLE_KEY', 'pk_test_YOUR_PUBLISHABLE_KEY'); // Troque pela sua chave publicável
define('STRIPE_WEBHOOK_SECRET', 'whsec_YOUR_WEBHOOK_SECRET'); // Troque pelo segredo do webhook
define('SITE_URL', 'https://seusite.com'); // URL base do seu site
