<?php
/**
 * admin/clientes.php - Painel de gestão de clientes
 * -----------------------------------------------------
 *  Lista todos os clientes cadastrados na loja.
 *  Acesso restrito a administradores.
 * -----------------------------------------------------
 */
require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../config.php';

$page_title = 'Gerenciar Clientes';
include __DIR__ . '/../includes/header.php';

// Busca todos os clientes com dados completos
$stmt = $pdo->prepare('
    SELECT 
        c.*,
        COUNT(DISTINCT f.id) as total_favoritos,
        COUNT(DISTINCT p.id) as total_pedidos
    FROM clientes c
    LEFT JOIN favoritos f ON c.id = f.cliente_id
    LEFT JOIN pedidos p ON c.id = p.cliente_id
    GROUP BY c.id
    ORDER BY c.criado_em DESC
');
$stmt->execute();
$clientes = $stmt->fetchAll();
?>
<section class="container py-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="serif">👥 Clientes Cadastrados</h1>
        <a href="admin.php" class="btn btn-outline-gold">← Voltar ao Admin</a>
    </div>

    <?php if (empty($clientes)): ?>
        <div class="alert alert-info">Nenhum cliente registrado ainda.</div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-dark table-hover align-middle">
                <thead>
                    <tr>
                        <th>Foto</th>
                        <th>ID</th>
                        <th>Nome</th>
                        <th>Email</th>
                        <th>CPF/CNPJ</th>
                        <th>Telefone</th>
                        <th>Favoritos</th>
                        <th>Pedidos</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($clientes as $cliente): ?>
                        <tr>
                            <td>
                                <?php if ($cliente['foto_perfil_url']): ?>
                                    <img src="../<?= htmlspecialchars($cliente['foto_perfil_url']) ?>" 
                                         alt="Foto" 
                                         class="rounded-circle" 
                                         style="width: 40px; height: 40px; object-fit: cover;">
                                <?php else: ?>
                                    <div class="rounded-circle bg-secondary d-inline-flex align-items-center justify-content-center" 
                                         style="width: 40px; height: 40px; font-size: 0.9rem;">
                                        <?= strtoupper(substr($cliente['nome'], 0, 1)) ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td>#<?= $cliente['id'] ?></td>
                            <td><?= htmlspecialchars($cliente['nome']) ?></td>
                            <td><?= htmlspecialchars($cliente['email']) ?></td>
                            <td><?= $cliente['cpf_cnpj'] ? htmlspecialchars($cliente['cpf_cnpj']) : '<span class="text-muted">-</span>' ?></td>
                            <td><?= $cliente['telefone_whatsapp'] ? htmlspecialchars($cliente['telefone_whatsapp']) : '<span class="text-muted">-</span>' ?></td>
                            <td>
                                <span class="badge bg-secondary"><?= $cliente['total_favoritos'] ?></span>
                            </td>
                            <td>
                                <span class="badge bg-gold" style="background-color: var(--color-gold); color: var(--color-bg-primary);"><?= $cliente['total_pedidos'] ?></span>
                            </td>
                            <td>
                                <button class="btn btn-sm btn-outline-gold" 
                                        type="button" 
                                        data-bs-toggle="collapse" 
                                        data-bs-target="#endereco-<?= $cliente['id'] ?>">
                                    📍 Ver Endereço
                                </button>
                            </td>
                        </tr>
                        <!-- Linha expansível com endereço completo -->
                        <tr>
                            <td colspan="9" class="p-0">
                                <div class="collapse" id="endereco-<?= $cliente['id'] ?>">
                                    <div class="card bg-dark border border-secondary m-3">
                                        <div class="card-body">
                                            <h6 class="text-gold">📬 Endereço Completo</h6>
                                            <p class="mb-2">
                                                <?php
                                                $endereco = [];
                                                if ($cliente['endereco_rua']) $endereco[] = $cliente['endereco_rua'];
                                                if ($cliente['endereco_numero']) $endereco[] = 'nº ' . $cliente['endereco_numero'];
                                                if ($cliente['endereco_bairro']) $endereco[] = $cliente['endereco_bairro'];
                                                if ($cliente['endereco_cidade'] || $cliente['endereco_estado']) {
                                                    $local = trim(($cliente['endereco_cidade'] ?? '') . ' - ' . ($cliente['endereco_estado'] ?? ''));
                                                    if ($local !== ' - ') $endereco[] = $local;
                                                }
                                                if ($cliente['endereco_cep']) $endereco[] = 'CEP: ' . $cliente['endereco_cep'];
                                                echo !empty($endereco) ? htmlspecialchars(implode(', ', $endereco)) : '<span class="text-muted">Endereço não cadastrado</span>';
                                                ?>
                                            </p>
                                            <?php if ($cliente['preferencias']): ?>
                                                <p class="mb-0"><strong>Preferências:</strong> <?= htmlspecialchars($cliente['preferencias']) ?></p>
                                            <?php endif; ?>
                                            <?php if ($cliente['data_nascimento']): ?>
                                                <p class="mb-0"><strong>Data de Nascimento:</strong> <?= date('d/m/Y', strtotime($cliente['data_nascimento'])) ?></p>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <div class="mt-4">
            <div class="card bg-dark border border-secondary">
                <div class="card-body">
                    <h5 class="card-title text-gold">📊 Resumo</h5>
                    <div class="row">
                        <div class="col-md-3">
                            <p class="mb-1"><strong>Total de clientes:</strong></p>
                            <p class="display-6 text-gold"><?= count($clientes) ?></p>
                        </div>
                        <div class="col-md-3">
                            <p class="mb-1"><strong>Com favoritos:</strong></p>
                            <p class="display-6"><?= count(array_filter($clientes, fn($c) => $c['total_favoritos'] > 0)) ?></p>
                        </div>
                        <div class="col-md-3">
                            <p class="mb-1"><strong>Com pedidos:</strong></p>
                            <p class="display-6"><?= count(array_filter($clientes, fn($c) => $c['total_pedidos'] > 0)) ?></p>
                        </div>
                        <div class="col-md-3">
                            <p class="mb-1"><strong>Com foto de perfil:</strong></p>
                            <p class="display-6"><?= count(array_filter($clientes, fn($c) => !empty($c['foto_perfil_url']))) ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</section>
<?php include __DIR__ . '/../includes/footer.php'; ?>
