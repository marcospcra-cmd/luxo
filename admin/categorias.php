<?php
/**
 * admin/categorias.php — Gerenciar categorias de produtos
 */
require_once __DIR__ . '/_auth.php';

$erros = [];
$sucesso = '';

// Criar nova categoria
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao']) && $_POST['acao'] === 'criar') {
    if (!hash_equals($_SESSION['csrf'], $_POST['csrf'] ?? '')) {
        $erros[] = 'Token inválido.';
    } else {
        $nome = trim($_POST['nome'] ?? '');
        $slug = trim($_POST['slug'] ?? '');
        $descricao = trim($_POST['descricao'] ?? '');
        $ordem = (int)($_POST['ordem'] ?? 0);
        
        if ($nome === '') {
            $erros[] = 'Nome da categoria é obrigatório.';
        }
        
        if ($slug === '') {
            // Gera slug a partir do nome se não fornecido
            $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $nome), '-'));
        } else {
            $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $slug), '-'));
        }
        
        if (strlen($slug) < 3) {
            $erros[] = 'Slug deve ter pelo menos 3 caracteres.';
        }
        
        if (!$erros) {
            try {
                $stmt = $pdo->prepare('INSERT INTO categorias (nome, slug, descricao, ordem, ativo) VALUES (:n, :s, :d, :o, 1)');
                $stmt->execute([':n' => $nome, ':s' => $slug, ':d' => $descricao, ':o' => $ordem]);
                $sucesso = 'Categoria criada com sucesso!';
            } catch (PDOException $e) {
                if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
                    $erros[] = 'Já existe uma categoria com este nome ou slug.';
                } else {
                    $erros[] = 'Erro ao criar categoria: ' . $e->getMessage();
                }
            }
        }
    }
}

// Atualizar categoria existente
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao']) && $_POST['acao'] === 'atualizar') {
    if (!hash_equals($_SESSION['csrf'], $_POST['csrf'] ?? '')) {
        $erros[] = 'Token inválido.';
    } else {
        $id = (int)$_POST['id'];
        $nome = trim($_POST['nome'] ?? '');
        $slug = trim($_POST['slug'] ?? '');
        $descricao = trim($_POST['descricao'] ?? '');
        $ordem = (int)($_POST['ordem'] ?? 0);
        $ativo = isset($_POST['ativo']) ? 1 : 0;
        
        if ($nome === '') {
            $erros[] = 'Nome da categoria é obrigatório.';
        }
        
        if ($slug === '') {
            $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $nome), '-'));
        } else {
            $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $slug), '-'));
        }
        
        if (!$erros) {
            try {
                $stmt = $pdo->prepare('UPDATE categorias SET nome=:n, slug=:s, descricao=:d, ordem=:o, ativo=:a WHERE id=:id');
                $stmt->execute([':n' => $nome, ':s' => $slug, ':d' => $descricao, ':o' => $ordem, ':a' => $ativo, ':id' => $id]);
                $sucesso = 'Categoria atualizada com sucesso!';
            } catch (PDOException $e) {
                if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
                    $erros[] = 'Já existe outra categoria com este nome ou slug.';
                } else {
                    $erros[] = 'Erro ao atualizar categoria: ' . $e->getMessage();
                }
            }
        }
    }
}

// Excluir categoria
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao']) && $_POST['acao'] === 'excluir') {
    if (!hash_equals($_SESSION['csrf'], $_POST['csrf'] ?? '')) {
        $erros[] = 'Token inválido.';
    } else {
        $id = (int)$_POST['id'];
        
        // Verifica se há produtos nesta categoria
        $checkStmt = $pdo->prepare('SELECT COUNT(*) as count FROM produtos WHERE categoria = (SELECT nome FROM categorias WHERE id = :id)');
        $checkStmt->execute([':id' => $id]);
        $result = $checkStmt->fetch();
        
        if ($result['count'] > 0) {
            $erros[] = 'Não é possível excluir esta categoria. Existem ' . $result['count'] . ' produto(s) vinculados a ela.';
        } else {
            try {
                $stmt = $pdo->prepare('DELETE FROM categorias WHERE id = :id');
                $stmt->execute([':id' => $id]);
                $sucesso = 'Categoria excluída com sucesso!';
            } catch (PDOException $e) {
                $erros[] = 'Erro ao excluir categoria: ' . $e->getMessage();
            }
        }
    }
}

// Carregar todas as categorias
try {
    $stmt = $pdo->query('SELECT * FROM categorias ORDER BY ordem, nome');
    $categorias = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $categorias = [];
    $erros[] = 'Erro ao carregar categorias: ' . $e->getMessage();
}

// Categoria sendo editada
$categoriaEditando = null;
if (isset($_GET['editar'])) {
    $idEditar = (int)$_GET['editar'];
    foreach ($categorias as $cat) {
        if ($cat['id'] === $idEditar) {
            $categoriaEditando = $cat;
            break;
        }
    }
}

$page_title = 'Gerenciar Categorias';
$tema = $TEMA_ATUAL;
?>
<!DOCTYPE html>
<html lang="pt-BR" data-bs-theme="<?= $tema==='dark'?'dark':'light' ?>">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= $page_title ?> | Maison</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@600&family=Inter:wght@300;400&display=swap" rel="stylesheet">
<link href="../assets/css/style.css" rel="stylesheet">
</head>
<body class="theme-<?= $tema ?>">
<nav class="navbar site-nav sticky-top"><div class="container">
  <a class="brand" href="admin.php"><span class="brand-mark">A</span><span class="brand-word">Painel<em>Administrativo</em></span></a>
  <div>
    <a class="btn btn-outline-gold btn-sm me-2" href="admin.php">← Voltar</a>
    <a class="btn btn-gold btn-sm" href="#novaCategoria" data-bs-toggle="collapse">+ Nova Categoria</a>
  </div>
</div></nav>

<main class="container py-5" style="max-width:980px;">
  <h2 class="serif mb-4">Gerenciar Categorias</h2>

  <?php if ($sucesso): ?>
    <div class="alert alert-success"><?= htmlspecialchars($sucesso) ?></div>
  <?php endif; ?>
  
  <?php foreach ($erros as $e): ?>
    <div class="alert alert-danger small"><?= htmlspecialchars($e) ?></div>
  <?php endforeach; ?>

  <!-- Formulário de Nova Categoria -->
  <div class="collapse mb-4 <?= $categoriaEditando ? '' : 'show' ?>" id="novaCategoria">
    <div class="card">
      <div class="card-header">
        <h5 class="mb-0"><?= $categoriaEditando ? 'Editar Categoria' : 'Nova Categoria' ?></h5>
      </div>
      <div class="card-body">
        <form method="post" class="row g-3">
          <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf']) ?>">
          <?php if ($categoriaEditando): ?>
            <input type="hidden" name="acao" value="atualizar">
            <input type="hidden" name="id" value="<?= $categoriaEditando['id'] ?>">
          <?php else: ?>
            <input type="hidden" name="acao" value="criar">
          <?php endif; ?>
          
          <div class="col-md-6">
            <label class="form-label small text-uppercase">Nome</label>
            <input class="form-control" name="nome" required value="<?= htmlspecialchars($categoriaEditando['nome'] ?? '') ?>" placeholder="Ex: Joias Raras">
          </div>
          <div class="col-md-6">
            <label class="form-label small text-uppercase">Slug (URL amigável)</label>
            <input class="form-control" name="slug" value="<?= htmlspecialchars($categoriaEditando['slug'] ?? '') ?>" placeholder="joias-raras">
            <div class="form-text">Deixe em branco para gerar automaticamente.</div>
          </div>
          <div class="col-12">
            <label class="form-label small text-uppercase">Descrição</label>
            <textarea class="form-control" name="descricao" rows="2"><?= htmlspecialchars($categoriaEditando['descricao'] ?? '') ?></textarea>
          </div>
          <div class="col-md-4">
            <label class="form-label small text-uppercase">Ordem</label>
            <input class="form-control" type="number" name="ordem" value="<?= (int)($categoriaEditando['ordem'] ?? 0) ?>">
            <div class="form-text">Menor número = aparece primeiro.</div>
          </div>
          <?php if ($categoriaEditando): ?>
          <div class="col-md-4 d-flex align-items-end">
            <div class="form-check">
              <input class="form-check-input" type="checkbox" name="ativo" id="ativo" <?= $categoriaEditando['ativo'] ? 'checked' : '' ?>>
              <label class="form-check-label" for="ativo">Ativa</label>
            </div>
          </div>
          <?php endif; ?>
          <div class="col-12 mt-3">
            <button class="btn btn-gold"><?= $categoriaEditando ? 'Atualizar' : 'Criar' ?> Categoria</button>
            <?php if ($categoriaEditando): ?>
              <a class="btn btn-outline-gold ms-2" href="categorias.php">Cancelar</a>
            <?php endif; ?>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Lista de Categorias -->
  <div class="card">
    <div class="card-header">
      <h5 class="mb-0">Categorias Existentes</h5>
    </div>
    <div class="card-body p-0">
      <table class="table table-hover mb-0">
        <thead>
          <tr>
            <th style="width:60px;">Ordem</th>
            <th>Nome</th>
            <th>Slug</th>
            <th style="width:100px;">Status</th>
            <th style="width:80px;">Produtos</th>
            <th style="width:150px;" class="text-end">Ações</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($categorias)): ?>
            <tr>
              <td colspan="6" class="text-center py-4 text-muted">Nenhuma categoria cadastrada.</td>
            </tr>
          <?php else: ?>
            <?php foreach ($categorias as $cat): ?>
              <?php
              // Conta produtos nesta categoria
              $stmtProd = $pdo->prepare('SELECT COUNT(*) as count FROM produtos WHERE categoria = :nome');
              $stmtProd->execute([':nome' => $cat['nome']]);
              $prodCount = $stmtProd->fetch()['count'];
              ?>
              <tr>
                <td><?= (int)$cat['ordem'] ?></td>
                <td class="fw-semibold"><?= htmlspecialchars($cat['nome']) ?></td>
                <td><code><?= htmlspecialchars($cat['slug']) ?></code></td>
                <td>
                  <span class="badge bg-<?= $cat['ativo'] ? 'success' : 'secondary' ?>">
                    <?= $cat['ativo'] ? 'Ativa' : 'Inativa' ?>
                  </span>
                </td>
                <td><?= $prodCount ?></td>
                <td class="text-end">
                  <a href="?editar=<?= $cat['id'] ?>" class="btn btn-sm btn-outline-gold">Editar</a>
                  <?php if ($prodCount === 0): ?>
                    <form method="post" class="d-inline" onsubmit="return confirm('Tem certeza que deseja excluir esta categoria?');">
                      <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf']) ?>">
                      <input type="hidden" name="acao" value="excluir">
                      <input type="hidden" name="id" value="<?= $cat['id'] ?>">
                      <button class="btn btn-sm btn-outline-danger">Excluir</button>
                    </form>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
