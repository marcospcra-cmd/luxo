<?php
/**
 * cliente_perfil.php — Área do Cliente para visualizar e editar perfil
 */
require_once __DIR__ . '/includes/cliente_auth.php';
exigir_cliente_logado('cliente_perfil.php');

$page_title = 'Meu Perfil';
$erros = [];
$sucesso = '';

// Busca dados do cliente
$stmt = $pdo->prepare('SELECT * FROM clientes WHERE id = :id');
$stmt->execute([':id' => cliente_id()]);
$cliente = $stmt->fetch();

if (!$cliente) {
    session_destroy();
    header('Location: cliente_login.php');
    exit;
}

// Processar atualização do perfil
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_cliente_valido($_POST['csrf'] ?? '')) {
        $erros[] = 'Sessão inválida. Recarregue a página.';
    } else {
        $nome = trim((string)($_POST['nome'] ?? ''));
        $cpf_cnpj = preg_replace('/[^0-9]/', '', (string)($_POST['cpf_cnpj'] ?? ''));
        $telefone_whatsapp = preg_replace('/[^0-9]/', '', (string)($_POST['telefone_whatsapp'] ?? ''));
        $data_nascimento = (string)($_POST['data_nascimento'] ?? '');
        $preferencias = trim((string)($_POST['preferencias'] ?? ''));
        
        // Endereço
        $endereco_rua = trim((string)($_POST['endereco_rua'] ?? ''));
        $endereco_numero = trim((string)($_POST['endereco_numero'] ?? ''));
        $endereco_bairro = trim((string)($_POST['endereco_bairro'] ?? ''));
        $endereco_cidade = trim((string)($_POST['endereco_cidade'] ?? ''));
        $endereco_estado = strtoupper(trim((string)($_POST['endereco_estado'] ?? '')));
        $endereco_cep = preg_replace('/[^0-9]/', '', (string)($_POST['endereco_cep'] ?? ''));

        // Validações
        if ($nome === '' || mb_strlen($nome) > 120) {
            $erros[] = 'Informe um nome (até 120 caracteres).';
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
        if ($endereco_estado !== '' && strlen($endereco_estado) !== 2) {
            $erros[] = 'Estado deve ter 2 letras (ex: SP, RJ).';
        }
        if ($endereco_cep !== '' && strlen($endereco_cep) !== 8) {
            $erros[] = 'CEP deve ter 8 dígitos.';
        }

        if (!$erros) {
            $update = $pdo->prepare('
                UPDATE clientes SET
                    nome = :nome,
                    cpf_cnpj = :cpf,
                    telefone_whatsapp = :tel,
                    data_nascimento = :dn,
                    preferencias = :pref,
                    endereco_rua = :rua,
                    endereco_numero = :num,
                    endereco_bairro = :bairro,
                    endereco_cidade = :cidade,
                    endereco_estado = :uf,
                    endereco_cep = :cep
                WHERE id = :id
            ');
            $update->execute([
                ':id' => cliente_id(),
                ':nome' => $nome,
                ':cpf' => $cpf_cnpj ?: null,
                ':tel' => $telefone_whatsapp ?: null,
                ':dn' => $data_nascimento ?: null,
                ':pref' => $preferencias ?: null,
                ':rua' => $endereco_rua ?: null,
                ':num' => $endereco_numero ?: null,
                ':bairro' => $endereco_bairro ?: null,
                ':cidade' => $endereco_cidade ?: null,
                ':uf' => $endereco_estado ?: null,
                ':cep' => $endereco_cep ?: null
            ]);

            $_SESSION['cliente_nome'] = $nome;
            $sucesso = 'Perfil atualizado com sucesso!';
            
            // Recarrega dados
            $stmt->execute([':id' => cliente_id()]);
            $cliente = $stmt->fetch();
        }
    }
}

// Processar upload de foto de perfil
if (isset($_FILES['foto_perfil']) && $_FILES['foto_perfil']['error'] === UPLOAD_ERR_OK) {
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $max_size = 5 * 1024 * 1024; // 5MB
    
    $file_type = $_FILES['foto_perfil']['type'];
    $file_size = $_FILES['foto_perfil']['size'];
    
    if (!in_array($file_type, $allowed_types)) {
        $erros[] = 'Tipo de arquivo não permitido. Use JPG, PNG, GIF ou WebP.';
    } elseif ($file_size > $max_size) {
        $erros[] = 'Arquivo muito grande. Máximo 5MB.';
    } else {
        // Gera nome único
        $ext = pathinfo($_FILES['foto_perfil']['name'], PATHINFO_EXTENSION);
        $filename = 'perfil_' . cliente_id() . '_' . time() . '.' . $ext;
        $upload_dir = __DIR__ . '/uploads/perfis/';
        
        // Cria diretório se não existir
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        $upload_path = $upload_dir . $filename;
        
        if (move_uploaded_file($_FILES['foto_perfil']['tmp_name'], $upload_path)) {
            $foto_url = 'uploads/perfis/' . $filename;
            
            // Remove foto antiga se existir
            if ($cliente['foto_perfil_url'] && file_exists(__DIR__ . '/' . $cliente['foto_perfil_url'])) {
                unlink(__DIR__ . '/' . $cliente['foto_perfil_url']);
            }
            
            $upd = $pdo->prepare('UPDATE clientes SET foto_perfil_url = :foto WHERE id = :id');
            $upd->execute([':foto' => $foto_url, ':id' => cliente_id()]);
            
            $cliente['foto_perfil_url'] = $foto_url;
            $sucesso = 'Foto de perfil atualizada com sucesso!';
        } else {
            $erros[] = 'Erro ao fazer upload da foto.';
        }
    }
}

include __DIR__ . '/includes/header.php';
?>
<section class="container py-5">
    <div class="row">
        <!-- Sidebar -->
        <div class="col-lg-3 mb-4">
            <div class="card bg-dark border border-secondary text-center p-4">
                <?php if ($cliente['foto_perfil_url']): ?>
                    <img src="<?= htmlspecialchars($cliente['foto_perfil_url']) ?>" 
                         alt="Foto de perfil" 
                         class="rounded-circle mb-3" 
                         style="width: 150px; height: 150px; object-fit: cover; border: 3px solid var(--accent);">
                <?php else: ?>
                    <div class="rounded-circle mb-3 d-inline-flex align-items-center justify-content-center bg-secondary" 
                         style="width: 150px; height: 150px; font-size: 3rem; color: var(--accent);">
                        <?= strtoupper(substr($cliente['nome'], 0, 1)) ?>
                    </div>
                <?php endif; ?>
                
                <h5 class="text-gold"><?= htmlspecialchars($cliente['nome']) ?></h5>
                <p class="text-muted small"><?= htmlspecialchars($cliente['email']) ?></p>
                
                <form method="post" enctype="multipart/form-data" class="mt-3">
                    <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf_cliente']) ?>">
                    <label class="btn btn-outline-gold btn-sm w-100 mb-2">
                        📷 Alterar Foto
                        <input type="file" name="foto_perfil" accept="image/*" style="display:none;" onchange="this.form.submit()">
                    </label>
                </form>
                
                <hr class="border-secondary">
                <div class="text-start small">
                    <p class="mb-1"><strong>Membro desde:</strong><br><?= date('d/m/Y', strtotime($cliente['criado_em'])) ?></p>
                </div>
            </div>
        </div>
        
        <!-- Formulário de Edição -->
        <div class="col-lg-9">
            <div class="card bg-dark border border-secondary p-4">
                <h3 class="serif text-gold mb-4">Editar Perfil</h3>
                
                <?php if ($sucesso): ?>
                    <div class="alert alert-success"><?= htmlspecialchars($sucesso) ?></div>
                <?php endif; ?>
                
                <?php foreach ($erros as $e): ?>
                    <div class="alert alert-danger"><?= htmlspecialchars($e) ?></div>
                <?php endforeach; ?>
                
                <form method="post" novalidate>
                    <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf_cliente']) ?>">
                    
                    <h5 class="text-light mb-3">Informações Pessoais</h5>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label small text-uppercase">Nome Completo *</label>
                            <input class="form-control bg-dark text-light border-secondary" 
                                   name="nome" 
                                   required 
                                   maxlength="120" 
                                   value="<?= htmlspecialchars($cliente['nome']) ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label small text-uppercase">E-mail</label>
                            <input class="form-control bg-dark text-light border-secondary" 
                                   type="email" 
                                   disabled 
                                   value="<?= htmlspecialchars($cliente['email']) ?>">
                            <small class="text-muted">E-mail não pode ser alterado</small>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label small text-uppercase">CPF/CNPJ</label>
                            <input class="form-control bg-dark text-light border-secondary" 
                                   name="cpf_cnpj" 
                                   maxlength="14" 
                                   value="<?= htmlspecialchars($cliente['cpf_cnpj'] ?? '') ?>" 
                                   placeholder="Somente números"
                                   inputmode="numeric">
                            <small class="text-muted">Para emissão de Nota Fiscal</small>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label small text-uppercase">WhatsApp</label>
                            <input class="form-control bg-dark text-light border-secondary" 
                                   name="telefone_whatsapp" 
                                   maxlength="15" 
                                   value="<?= htmlspecialchars($cliente['telefone_whatsapp'] ?? '') ?>" 
                                   placeholder="Somente números"
                                   inputmode="tel">
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label small text-uppercase">Data de Nascimento</label>
                            <input class="form-control bg-dark text-light border-secondary" 
                                   type="date" 
                                   name="data_nascimento" 
                                   value="<?= htmlspecialchars($cliente['data_nascimento'] ?? '') ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label small text-uppercase">Preferências</label>
                            <select class="form-select bg-dark text-light border-secondary" name="preferencias">
                                <option value="">Selecione...</option>
                                <option value="Esmeraldas" <?= ($cliente['preferencias'] ?? '') === 'Esmeraldas' ? 'selected' : '' ?>>Esmeraldas</option>
                                <option value="Esculturas" <?= ($cliente['preferencias'] ?? '') === 'Esculturas' ? 'selected' : '' ?>>Esculturas</option>
                                <option value="Cangas" <?= ($cliente['preferencias'] ?? '') === 'Cangas' ? 'selected' : '' ?>>Cangas</option>
                                <option value="Todos" <?= ($cliente['preferencias'] ?? '') === 'Todos' ? 'selected' : '' ?>>Todos</option>
                            </select>
                        </div>
                    </div>
                    
                    <hr class="border-secondary my-4">
                    
                    <h5 class="text-light mb-3">Endereço de Entrega</h5>
                    
                    <div class="row">
                        <div class="col-md-8 mb-3">
                            <label class="form-label small text-uppercase">Rua</label>
                            <input class="form-control bg-dark text-light border-secondary" 
                                   name="endereco_rua" 
                                   maxlength="255" 
                                   value="<?= htmlspecialchars($cliente['endereco_rua'] ?? '') ?>">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label small text-uppercase">Número</label>
                            <input class="form-control bg-dark text-light border-secondary" 
                                   name="endereco_numero" 
                                   maxlength="20" 
                                   value="<?= htmlspecialchars($cliente['endereco_numero'] ?? '') ?>">
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label small text-uppercase">Bairro</label>
                            <input class="form-control bg-dark text-light border-secondary" 
                                   name="endereco_bairro" 
                                   maxlength="100" 
                                   value="<?= htmlspecialchars($cliente['endereco_bairro'] ?? '') ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label small text-uppercase">Cidade</label>
                            <input class="form-control bg-dark text-light border-secondary" 
                                   name="endereco_cidade" 
                                   maxlength="100" 
                                   value="<?= htmlspecialchars($cliente['endereco_cidade'] ?? '') ?>">
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label small text-uppercase">Estado</label>
                            <input class="form-control bg-dark text-light border-secondary" 
                                   name="endereco_estado" 
                                   maxlength="2" 
                                   value="<?= htmlspecialchars($cliente['endereco_estado'] ?? '') ?>" 
                                   placeholder="UF"
                                   style="text-transform:uppercase;">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label small text-uppercase">CEP</label>
                            <input class="form-control bg-dark text-light border-secondary" 
                                   name="endereco_cep" 
                                   maxlength="9" 
                                   value="<?= htmlspecialchars($cliente['endereco_cep'] ?? '') ?>" 
                                   placeholder="Somente números"
                                   inputmode="numeric">
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-gold w-100 mt-3">Salvar Alterações</button>
                </form>
            </div>
        </div>
    </div>
</section>
<?php include __DIR__ . '/includes/footer.php'; ?>
