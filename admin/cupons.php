<?php
/**
 * admin/cupons.php - Painel de gestão de cupons de desconto
 * -----------------------------------------------------
 *  CRUD completo para cupons de desconto.
 *  Acesso restrito a administradores.
 * -----------------------------------------------------
 */
require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../config.php';

$page_title = 'Gerenciar Cupons';
include __DIR__ . '/../includes/header.php';

// Processar formulário de criação/edição
$mensagem = '';
$tipo_mensagem = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf'] ?? '';
    if (!hash_equals($_SESSION['csrf'] ?? '', $csrf)) {
        die('Token CSRF inválido');
    }

    $acao = $_POST['acao'] ?? '';
    
    if ($acao === 'criar' || $acao === 'editar') {
        $codigo = strtoupper(trim($_POST['codigo'] ?? ''));
        $descricao = trim($_POST['descricao'] ?? '');
        $tipo_desconto = $_POST['tipo_desconto'] ?? 'percentual';
        $valor = floatval($_POST['valor'] ?? 0);
        $minimo_compra = floatval($_POST['minimo_compra'] ?? 0);
        $validade = !empty($_POST['validade']) ? $_POST['validade'] : null;
        $ativo = isset($_POST['ativo']) ? 1 : 0;
        $usos_maximos = intval($_POST['usos_maximos'] ?? 0);
        
        if (empty($codigo)) {
            $mensagem = 'Código do cupom é obrigatório.';
            $tipo_mensagem = 'danger';
        } elseif ($tipo_desconto === 'percentual' && ($valor < 0 || $valor > 100)) {
            $mensagem = 'Desconto percentual deve estar entre 0 e 100.';
            $tipo_mensagem = 'danger';
        } elseif ($valor <= 0) {
            $mensagem = 'Valor do desconto deve ser maior que zero.';
            $tipo_mensagem = 'danger';
        } else {
            try {
                if ($acao === 'criar') {
                    // Verificar se já existe
                    $stmt = $pdo->prepare('SELECT id FROM cupons WHERE codigo = :codigo');
                    $stmt->execute([':codigo' => $codigo]);
                    if ($stmt->fetch()) {
                        $mensagem = 'Já existe um cupom com este código.';
                        $tipo_mensagem = 'warning';
                    } else {
                        $stmt = $pdo->prepare('
                            INSERT INTO cupons 
                            (codigo, descricao, tipo_desconto, valor, minimo_compra, validade, ativo, usos_maximos, usos_total, criado_em)
                            VALUES (:codigo, :descricao, :tipo, :valor, :minimo, :validade, :ativo, :usos_max, 0, NOW())
                        ');
                        $stmt->execute([
                            ':codigo' => $codigo,
                            ':descricao' => $descricao,
                            ':tipo' => $tipo_desconto,
                            ':valor' => $valor,
                            ':minimo' => $minimo_compra,
                            ':validade' => $validade,
                            ':ativo' => $ativo,
                            ':usos_max' => $usos_maximos > 0 ? $usos_maximos : null
                        ]);
                        $mensagem = 'Cupom criado com sucesso!';
                        $tipo_mensagem = 'success';
                    }
                } else {
                    $id = intval($_POST['id'] ?? 0);
                    $stmt = $pdo->prepare('
                        UPDATE cupons SET
                            codigo = :codigo,
                            descricao = :descricao,
                            tipo_desconto = :tipo,
                            valor = :valor,
                            minimo_compra = :minimo,
                            validade = :validade,
                            ativo = :ativo,
                            usos_maximos = :usos_max
                        WHERE id = :id
                    ');
                    $stmt->execute([
                        ':id' => $id,
                        ':codigo' => $codigo,
                        ':descricao' => $descricao,
                        ':tipo' => $tipo_desconto,
                        ':valor' => $valor,
                        ':minimo' => $minimo_compra,
                        ':validade' => $validade,
                        ':ativo' => $ativo,
                        ':usos_max' => $usos_maximos > 0 ? $usos_maximos : null
                    ]);
                    $mensagem = 'Cupom atualizado com sucesso!';
                    $tipo_mensagem = 'success';
                }
            } catch (PDOException $e) {
                $mensagem = 'Erro ao salvar cupom: ' . $e->getMessage();
                $tipo_mensagem = 'danger';
            }
        }
    } elseif ($acao === 'excluir') {
        $id = intval($_POST['id'] ?? 0);
        try {
            $stmt = $pdo->prepare('DELETE FROM cupons WHERE id = :id');
            $stmt->execute([':id' => $id]);
            $mensagem = 'Cupom excluído com sucesso!';
            $tipo_mensagem = 'success';
        } catch (PDOException $e) {
            $mensagem = 'Erro ao excluir cupom: ' . $e->getMessage();
            $tipo_mensagem = 'danger';
        }
    }
}

// Buscar todos os cupons
$stmt = $pdo->query('SELECT * FROM cupons ORDER BY criado_em DESC');
$cupons = $stmt->fetchAll();

// Cupom em edição
$cupom_edicao = null;
if (isset($_GET['editar'])) {
    $id = intval($_GET['editar']);
    $stmt = $pdo->prepare('SELECT * FROM cupons WHERE id = :id');
    $stmt->execute([':id' => $id]);
    $cupom_edicao = $stmt->fetch();
}
?>
<section class="container py-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="serif">🏷️ Cupons de Desconto</h1>
        <a href="admin.php" class="btn btn-outline-gold">← Voltar ao Admin</a>
    </div>

    <?php if ($mensagem): ?>
        <div class="alert alert-<?= $tipo_mensagem ?>"><?= htmlspecialchars($mensagem) ?></div>
    <?php endif; ?>

    <!-- Formulário -->
    <div class="card bg-dark border border-secondary mb-4">
        <div class="card-header border-bottom border-secondary">
            <h5 class="mb-0 text-gold"><?= $cupom_edicao ? '✏️ Editar Cupom' : '➕ Novo Cupom' ?></h5>
        </div>
        <div class="card-body">
            <form method="POST" action="cupons.php">
                <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf']) ?>">
                <input type="hidden" name="acao" value="<?= $cupom_edicao ? 'editar' : 'criar' ?>">
                <?php if ($cupom_edicao): ?>
                    <input type="hidden" name="id" value="<?= $cupom_edicao['id'] ?>">
                <?php endif; ?>
                
                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">Código *</label>
                        <input type="text" name="codigo" class="form-control bg-dark text-light border-secondary" 
                               value="<?= htmlspecialchars($cupom_edicao['codigo'] ?? '') ?>" required style="text-transform:uppercase;">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Tipo de Desconto</label>
                        <select name="tipo_desconto" class="form-select bg-dark text-light border-secondary">
                            <option value="percentual" <?= ($cupom_edicao['tipo_desconto'] ?? '') === 'percentual' ? 'selected' : '' ?>>Percentual (%)</option>
                            <option value="fixo" <?= ($cupom_edicao['tipo_desconto'] ?? '') === 'fixo' ? 'selected' : '' ?>>Fixo (R$)</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Valor *</label>
                        <input type="number" step="0.01" name="valor" class="form-control bg-dark text-light border-secondary" 
                               value="<?= htmlspecialchars($cupom_edicao['valor'] ?? '') ?>" required min="0">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Descrição</label>
                        <input type="text" name="descricao" class="form-control bg-dark text-light border-secondary" 
                               value="<?= htmlspecialchars($cupom_edicao['descricao'] ?? '') ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Compra Mínima (R$)</label>
                        <input type="number" step="0.01" name="minimo_compra" class="form-control bg-dark text-light border-secondary" 
                               value="<?= htmlspecialchars($cupom_edicao['minimo_compra'] ?? '0') ?>" min="0">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Validade</label>
                        <input type="date" name="validade" class="form-control bg-dark text-light border-secondary" 
                               value="<?= htmlspecialchars($cupom_edicao['validade'] ?? '') ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Usos Máximos</label>
                        <input type="number" name="usos_maximos" class="form-control bg-dark text-light border-secondary" 
                               value="<?= htmlspecialchars($cupom_edicao['usos_maximos'] ?? '0') ?>" min="0" placeholder="0 = ilimitado">
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <div class="form-check">
                            <input type="checkbox" name="ativo" class="form-check-input" id="ativo" 
                                   <?= ($cupom_edicao['ativo'] ?? 1) ? 'checked' : '' ?>>
                            <label class="form-check-label" for="ativo">Ativo</label>
                        </div>
                    </div>
                    <div class="col-md-2 d-flex align-items-end gap-2">
                        <button type="submit" class="btn btn-gold w-100"><?= $cupom_edicao ? 'Salvar' : 'Criar' ?></button>
                        <?php if ($cupom_edicao): ?>
                            <a href="cupons.php" class="btn btn-outline-secondary">Cancelar</a>
                        <?php endif; ?>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Lista de Cupons -->
    <?php if (empty($cupons)): ?>
        <div class="alert alert-info">Nenhum cupom cadastrado ainda.</div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-dark table-hover align-middle">
                <thead>
                    <tr>
                        <th>Código</th>
                        <th>Descrição</th>
                        <th>Desconto</th>
                        <th>Compra Mín.</th>
                        <th>Validade</th>
                        <th>Usos</th>
                        <th>Status</th>
                        <th>Criado</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($cupons as $cupom): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($cupom['codigo']) ?></strong></td>
                            <td><?= htmlspecialchars($cupom['descricao'] ?: '—') ?></td>
                            <td>
                                <?php if ($cupom['tipo_desconto'] === 'percentual'): ?>
                                    <span class="badge bg-success"><?= $cupom['valor'] ?>%</span>
                                <?php else: ?>
                                    <span class="badge bg-info">R$ <?= number_format($cupom['valor'], 2, ',', '.') ?></span>
                                <?php endif; ?>
                            </td>
                            <td>R$ <?= number_format($cupom['minimo_compra'], 2, ',', '.') ?></td>
                            <td>
                                <?php if ($cupom['validade']): ?>
                                    <?= date('d/m/Y', strtotime($cupom['validade'])) ?>
                                    <?php if (strtotime($cupom['validade']) < time()): ?>
                                        <span class="badge bg-danger ms-1">Expirado</span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="text-muted">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?= $cupom['usos_total'] ?>
                                <?php if ($cupom['usos_maximos']): ?>
                                    <span class="text-muted">/ <?= $cupom['usos_maximos'] ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($cupom['ativo']): ?>
                                    <span class="badge bg-success">Ativo</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">Inativo</span>
                                <?php endif; ?>
                            </td>
                            <td><?= date('d/m/Y', strtotime($cupom['criado_em'])) ?></td>
                            <td>
                                <a href="?editar=<?= $cupom['id'] ?>" class="btn btn-sm btn-outline-gold">Editar</a>
                                <form method="POST" action="cupons.php" class="d-inline" onsubmit="return confirm('Excluir este cupom?');">
                                    <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf']) ?>">
                                    <input type="hidden" name="acao" value="excluir">
                                    <input type="hidden" name="id" value="<?= $cupom['id'] ?>">
                                    <button class="btn btn-sm btn-outline-danger">Excluir</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
<?php include __DIR__ . '/../includes/footer.php'; ?>
