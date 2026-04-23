<?php
/**
 * login.php — Acesso administrativo
 *  - Senha verificada com password_verify (hash bcrypt)
 *  - Proteção contra SQL Injection via prepared statements
 *  - Token CSRF
 */
require_once __DIR__ . '/config.php';
$page_title = 'Acesso administrativo';
$erro = null;

if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf'] ?? '';
    if (!hash_equals($_SESSION['csrf'], $token)) {
        $erro = 'Sessão inválida. Recarregue a página.';
    } else {
        $usuario = trim($_POST['usuario'] ?? '');
        $senha   = (string)($_POST['senha'] ?? '');

        $stmt = $pdo->prepare('SELECT id, senha_hash FROM administradores WHERE usuario = :u LIMIT 1');
        $stmt->execute([':u' => $usuario]);
        $admin = $stmt->fetch();

        if ($admin && password_verify($senha, $admin['senha_hash'])) {
            session_regenerate_id(true);
            $_SESSION['admin_id']      = (int)$admin['id'];
            $_SESSION['admin_usuario'] = $usuario;
            header('Location: admin/admin.php');
            exit;
        } else {
            $erro = 'Usuário ou senha inválidos.';
        }
    }
}
include __DIR__ . '/includes/header.php';
?>
<section class="container login-shell">
  <form class="login-card" method="post" novalidate>
    <h1 class="serif mb-1" style="color:var(--accent);">Acesso restrito</h1>
    <p class="text-muted small mb-4">Painel administrativo Solar Amazônia</p>

    <?php if ($erro): ?>
      <div class="alert alert-danger small"><?= htmlspecialchars($erro) ?></div>
    <?php endif; ?>

    <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf']) ?>">

    <label class="form-label small text-uppercase" style="letter-spacing:.2em;">Usuário</label>
    <input class="form-control mb-3" name="usuario" required maxlength="60" autocomplete="username">

    <label class="form-label small text-uppercase" style="letter-spacing:.2em;">Senha</label>
    <input class="form-control mb-4" type="password" name="senha" required maxlength="120" autocomplete="current-password">

    <button class="btn btn-gold w-100">Entrar</button>
    <p class="text-muted small mt-3 mb-0">Padrão: <code>admin</code> / <code>admin123</code> — altere após o 1º login.</p>
  </form>
</section>
<?php include __DIR__ . '/includes/footer.php'; ?>
