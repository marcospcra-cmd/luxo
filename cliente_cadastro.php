<?php
/**
 * cliente_cadastro.php — criação de conta de cliente com perfil completo
 */
require_once __DIR__ . '/includes/cliente_auth.php';
$page_title = 'Criar conta';

// Já logado? vai direto para favoritos
if (cliente_logado()) { header('Location: favoritos.php'); exit; }

$erros  = [];
$nome   = '';
$email  = '';
$cpf_cnpj = '';
$telefone_whatsapp = '';
$data_nascimento = '';
$preferencias = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_cliente_valido($_POST['csrf'] ?? '')) {
        $erros[] = 'Sessão inválida. Recarregue a página.';
    }

    $nome  = trim((string)($_POST['nome'] ?? ''));
    $email = strtolower(trim((string)($_POST['email'] ?? '')));
    $senha = (string)($_POST['senha'] ?? '');
    $cpf_cnpj = preg_replace('/[^0-9]/', '', (string)($_POST['cpf_cnpj'] ?? ''));
    $telefone_whatsapp = preg_replace('/[^0-9]/', '', (string)($_POST['telefone_whatsapp'] ?? ''));
    $data_nascimento = (string)($_POST['data_nascimento'] ?? '');
    $preferencias = trim((string)($_POST['preferencias'] ?? ''));

    // Validações
    if ($nome === '' || mb_strlen($nome) > 120) {
        $erros[] = 'Informe um nome (até 120 caracteres).';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $erros[] = 'E-mail inválido.';
    }
    if (mb_strlen($email) > 180) {
        $erros[] = 'E-mail muito longo.';
    }
    if (strlen($senha) < 8 || strlen($senha) > 120) {
        $erros[] = 'Senha deve ter entre 8 e 120 caracteres.';
    }
    if ($cpf_cnpj !== '' && strlen($cpf_cnpj) !== 11 && strlen($cpf_cnpj) !== 14) {
        $erros[] = 'CPF deve ter 11 dígitos ou CNPJ 14 dígitos.';
    }
    if ($telefone_whatsapp !== '' && (strlen($telefone_whatsapp) < 10 || strlen($telefone_whatsapp) > 15)) {
        $erros[] = 'Telefone/WhatsApp inválido.';
    }
    if ($data_nascimento !== '') {
        $dt = DateTime::createFromFormat('Y-m-d', $data_nascimento);
        if (!$dt) {
            $erros[] = 'Data de nascimento inválida.';
        }
    }

    if (!$erros) {
        // Verifica e-mail duplicado
        $check = $pdo->prepare('SELECT id FROM clientes WHERE email = :e');
        $check->execute([':e' => $email]);
        if ($check->fetchColumn()) {
            $erros[] = 'Este e-mail já está cadastrado.';
        } else {
            $hash = password_hash($senha, PASSWORD_BCRYPT);
            $ins  = $pdo->prepare('
                INSERT INTO clientes (
                    nome, email, senha_hash, cpf_cnpj, telefone_whatsapp, 
                    data_nascimento, preferencias
                ) VALUES (:n, :e, :h, :cpf, :tel, :dn, :pref)
            ');
            $ins->execute([
                ':n' => $nome, 
                ':e' => $email, 
                ':h' => $hash,
                ':cpf' => $cpf_cnpj ?: null,
                ':tel' => $telefone_whatsapp ?: null,
                ':dn' => $data_nascimento ?: null,
                ':pref' => $preferencias ?: null
            ]);

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
<section class="container py-5">
  <div class="row justify-content-center">
    <div class="col-lg-8">
      <form class="card bg-dark border border-secondary p-4" method="post" novalidate>
        <h1 class="serif mb-3 text-center" style="color:var(--accent);">Criar Conta</h1>
        <p class="text-muted small text-center mb-4">Salve suas peças favoritas e gerencie seu perfil em qualquer dispositivo.</p>

        <?php foreach ($erros as $e): ?>
          <div class="alert alert-danger small"><?= htmlspecialchars($e) ?></div>
        <?php endforeach; ?>

        <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf_cliente']) ?>">

        <div class="row">
          <div class="col-12 mb-3">
            <label class="form-label small text-uppercase" style="letter-spacing:.2em;">Nome Completo *</label>
            <input class="form-control bg-dark text-light border-secondary" name="nome" required maxlength="120" value="<?= htmlspecialchars($nome) ?>" placeholder="Seu nome completo">
          </div>
        </div>

        <div class="row">
          <div class="col-md-6 mb-3">
            <label class="form-label small text-uppercase" style="letter-spacing:.2em;">E-mail *</label>
            <input class="form-control bg-dark text-light border-secondary" type="email" name="email" required maxlength="180" value="<?= htmlspecialchars($email) ?>" autocomplete="email" placeholder="seu@email.com">
          </div>
          <div class="col-md-6 mb-3">
            <label class="form-label small text-uppercase" style="letter-spacing:.2em;">Senha *</label>
            <input class="form-control bg-dark text-light border-secondary" type="password" name="senha" required minlength="8" maxlength="120" autocomplete="new-password" placeholder="Mínimo 8 caracteres">
            <small class="text-muted">Deve ter entre 8 e 120 caracteres</small>
          </div>
        </div>

        <hr class="border-secondary my-4">
        <h5 class="text-gold mb-3">Informações Adicionais (Opcional)</h5>

        <div class="row">
          <div class="col-md-6 mb-3">
            <label class="form-label small text-uppercase" style="letter-spacing:.2em;">CPF/CNPJ</label>
            <input class="form-control bg-dark text-light border-secondary" name="cpf_cnpj" maxlength="14" value="<?= htmlspecialchars($cpf_cnpj) ?>" placeholder="Somente números" inputmode="numeric">
            <small class="text-muted">Para emissão de Nota Fiscal</small>
          </div>
          <div class="col-md-6 mb-3">
            <label class="form-label small text-uppercase" style="letter-spacing:.2em;">WhatsApp</label>
            <input class="form-control bg-dark text-light border-secondary" name="telefone_whatsapp" maxlength="15" value="<?= htmlspecialchars($telefone_whatsapp) ?>" placeholder="Somente números" inputmode="tel">
            <small class="text-muted">DDD + número</small>
          </div>
        </div>

        <div class="row">
          <div class="col-md-6 mb-3">
            <label class="form-label small text-uppercase" style="letter-spacing:.2em;">Data de Nascimento</label>
            <input class="form-control bg-dark text-light border-secondary" type="date" name="data_nascimento" value="<?= htmlspecialchars($data_nascimento) ?>">
          </div>
          <div class="col-md-6 mb-3">
            <label class="form-label small text-uppercase" style="letter-spacing:.2em;">Preferências</label>
            <select class="form-select bg-dark text-light border-secondary" name="preferencias">
              <option value="">Selecione...</option>
              <option value="Esmeraldas" <?= $preferencias === 'Esmeraldas' ? 'selected' : '' ?>>Esmeraldas</option>
              <option value="Esculturas" <?= $preferencias === 'Esculturas' ? 'selected' : '' ?>>Esculturas</option>
              <option value="Cangas" <?= $preferencias === 'Cangas' ? 'selected' : '' ?>>Cangas</option>
              <option value="Todos" <?= $preferencias === 'Todos' ? 'selected' : '' ?>>Todos</option>
            </select>
          </div>
        </div>

        <button class="btn btn-gold w-100 mt-3">Criar conta</button>
        <p class="small text-muted mt-3 mb-0 text-center">
          Já tem conta? <a href="cliente_login.php" style="color:var(--accent);">Entrar</a>
        </p>
      </form>
    </div>
  </div>
</section>
<?php include __DIR__ . '/includes/footer.php'; ?>
