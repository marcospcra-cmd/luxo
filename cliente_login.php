<?php
/**
 * cliente_login.php — autenticação do cliente
 */
require_once __DIR__ . '/includes/cliente_auth.php';
$page_title = 'Entrar';

if (cliente_logado()) { header('Location: favoritos.php'); exit; }

$erros = [];
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_cliente_valido($_POST['csrf'] ?? '')) {
        $erros[] = 'Sessão inválida. Recarregue a página.';
    }

    $email = strtolower(trim((string)($_POST['email'] ?? '')));
    $senha = (string)($_POST['senha'] ?? '');

    if (!$erros) {
        $stmt = $pdo->prepare('SELECT id, nome, senha_hash FROM clientes WHERE email = :e LIMIT 1');
        $stmt->execute([':e' => $email]);
        $c = $stmt->fetch();

        if ($c && password_verify($senha, $c['senha_hash'])) {
            session_regenerate_id(true);
            $_SESSION['cliente_id']   = (int)$c['id'];
            $_SESSION['cliente_nome'] = $c['nome'];

            $next = $_GET['next'] ?? 'favoritos.php';
            // Só permite redirect interno
            if (!preg_match('#^[a-zA-Z0-9_./?=&-]+$#', $next)) $next = 'favoritos.php';
            header('Location: ' . $next);
            exit;
        }
        $erros[] = 'E-mail ou senha inválidos.';
    }
}

include __DIR__ . '/includes/header.php';
?>
<section class="container login-shell">
  <form class="login-card" method="post" novalidate>
    <h1 class="serif mb-1" style="color:var(--accent);">Entrar</h1>
    <p class="text-muted small mb-4">Acesse sua conta para gerenciar favoritos.</p>

    <?php foreach ($erros as $e): ?>
      <div class="alert alert-danger small"><?= htmlspecialchars($e) ?></div>
    <?php endforeach; ?>

    <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf_cliente']) ?>">

    <label class="form-label small text-uppercase" style="letter-spacing:.2em;">E-mail</label>
    <input class="form-control mb-3" type="email" name="email" required maxlength="180" value="<?= htmlspecialchars($email) ?>" autocomplete="email">

    <label class="form-label small text-uppercase" style="letter-spacing:.2em;">Senha</label>
    <input class="form-control mb-4" type="password" name="senha" required maxlength="120" autocomplete="current-password">

    <button class="btn btn-gold w-100">Entrar</button>
    <p class="small text-muted mt-3 mb-0 text-center">
      Sem conta? <a href="cliente_cadastro.php" style="color:var(--accent);">Criar agora</a>
    </p>
  </form>
</section>
<?php include __DIR__ . '/includes/footer.php'; ?>
