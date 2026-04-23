<?php
/**
 * includes/cliente_auth.php
 * -----------------------------------------------------
 *  Helpers de autenticação do CLIENTE (loja).
 *  Sessão de cliente é independente da sessão de admin.
 *  - $_SESSION['cliente_id']    : id do cliente logado
 *  - $_SESSION['cliente_nome']  : nome para exibição
 *  - $_SESSION['csrf_cliente']  : token CSRF para forms/AJAX
 * -----------------------------------------------------
 */

require_once __DIR__ . '/../config.php';

if (empty($_SESSION['csrf_cliente'])) {
    $_SESSION['csrf_cliente'] = bin2hex(random_bytes(32));
}

/** Cliente está logado? */
function cliente_logado(): bool {
    return !empty($_SESSION['cliente_id']);
}

/** Retorna o id do cliente ou null. */
function cliente_id(): ?int {
    return cliente_logado() ? (int)$_SESSION['cliente_id'] : null;
}

/** Exige login; redireciona para a tela de login com `next` preservado. */
function exigir_cliente_logado(string $next = ''): void {
    if (!cliente_logado()) {
        $url = 'cliente_login.php';
        if ($next !== '') $url .= '?next=' . urlencode($next);
        header('Location: ' . $url);
        exit;
    }
}

/** Verifica token CSRF do cliente. */
function csrf_cliente_valido(?string $token): bool {
    return is_string($token) && hash_equals($_SESSION['csrf_cliente'] ?? '', $token);
}

/**
 * Retorna lista de IDs de produtos favoritados pelo cliente atual.
 * Usado para marcar os corações no catálogo.
 */
function favoritos_ids(PDO $pdo): array {
    $cid = cliente_id();
    if (!$cid) return [];
    $s = $pdo->prepare('SELECT produto_id FROM favoritos WHERE cliente_id = :c');
    $s->execute([':c' => $cid]);
    return array_map('intval', $s->fetchAll(PDO::FETCH_COLUMN));
}
