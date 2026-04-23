<?php
/**
 * cliente_cadastro.php — criação de conta de cliente
 */
require_once __DIR__ . '/includes/cliente_auth.php';
$page_title = 'Criar conta';

// Já logado? vai direto para favoritos
if (cliente_logado()) { header('Location: favoritos.php'); exit; }

$erros  = [];
$nome   = '';
$email  = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_cliente_valido($_POST['csrf'] ?? '')) {
        $erros[] = 'Sessão inválida. Recarregue a página.';
    }

    $nome  = trim((string)($_POST['nome'] ?? ''));
    $email = strtolower(trim((string)($_POST['email'] ?? '')));
    $senha = (string)($_POST['senha'] ?? '');

    if ($nome === '' || mb_strlen($nome) > 120)        $erros[] = 'Informe um nome (até 120 caracteres).';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL))    $erros[] = 'E-mail inválido.';
    if (mb_strlen($email) > 180)                       $erros[] = 'E-mail muito longo.';
    if (strlen($senha) < 8 || strlen($senha) > 120)    $erros[] = 'Senha deve ter entre 8 e 120 caracteres.';

    if (!$erros) {
        // Verifica e-mail duplicado
        $check = $pdo->prepare('SELECT id FROM clientes WHERE email = :e');
        $check->execute([':e' => $email]);
        if ($check->fetchColumn()) {
            $erros[] = 'Este e-mail já está cadastrado.';
        } else {
            $hash = password_hash($senha, PASSWORD_BCRYPT);
            $ins  = $pdo->prepare('INSERT INTO clientes (nome,email,senha_hash) VALUES (:n,:e,:h)');
            $ins->execute([':n' => $nome, ':e' => $email, ':h' => $hash]);

            // Login automático
            session_regenerate_id(true);
            $_SESSION['cliente_id']   = (int)$pdo->lastInsertId();
            $_SESSION['cliente_nome'] = $nome;

            $next = $_GET['next'] ?? 'favoritos.php';
            header('Location: ' . $next);
            exit;
        }
    }
}

include __DIR__ . '/includes/header.php';
?>
<section class="container login-shell">
  <form class="login-card" method="post" novalidate>
    <h1 class="serif mb-1" style="color:var(--accent);">Criar conta</h1>
    <p class="text-muted small mb-4">Salve suas peças favoritas em qualquer dispositivo.</p>

    <?php foreach ($erros as $e): ?>
      <div class="alert alert-danger small"><?= htmlspecialchars($e) ?></div>
    <?php endforeach; ?>

    <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf_cliente']) ?>">

    <label class="form-label small text-uppercase" style="letter-spacing:.2em;">Nome</label>
    <input class="form-control mb-3" name="nome" required maxlength="120" value="<?= htmlspecialchars($nome) ?>">

    <label class="form-label small text-uppercase" style="letter-spacing:.2em;">E-mail</label>
    <input class="form-control mb-3" type="email" name="email" required maxlength="180" value="<?= htmlspecialchars($email) ?>" autocomplete="email">

    <label class="form-label small text-uppercase" style="letter-spacing:.2em;">Senha</label>
    <input class="form-control mb-4" type="password" name="senha" required minlength="8" maxlength="120" autocomplete="new-password">

    <button class="btn btn-gold w-100">Criar conta</button>
    <p class="small text-muted mt-3 mb-0 text-center">
      Já tem conta? <a href="cliente_login.php" style="color:var(--accent);">Entrar</a>
    </p>
  </form>
</section>
<?php include __DIR__ . '/includes/footer.php'; ?>
