<?php
/**
 * admin/produto_form.php — Criar/Editar produto + upload de imagem e vídeo
 */
require_once __DIR__ . '/_auth.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$produto = ['id'=>0,'nome'=>'','categoria'=>'Esmeraldas','preco'=>'','descricao_curta'=>'','especificacoes_tecnicas'=>'','imagem_url'=>'','estoque'=>0,'video_url'=>'','codigo_registro'=>''];
$galeria = [];
$categorias = [];

// Carrega categorias do banco
try {
    $stmtCat = $pdo->query('SELECT id, nome, slug FROM categorias WHERE ativo = 1 ORDER BY ordem, nome');
    $categorias = $stmtCat->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // Fallback para categorias fixas se tabela não existir
    $categorias = [
        ['id' => 1, 'nome' => 'Esmeraldas', 'slug' => 'esmeraldas'],
        ['id' => 2, 'nome' => 'Esculturas', 'slug' => 'esculturas'],
        ['id' => 3, 'nome' => 'Cangas', 'slug' => 'cangas']
    ];
}

if ($id) {
    $s = $pdo->prepare('SELECT * FROM produtos WHERE id=:id');
    $s->execute([':id'=>$id]);
    $produto = $s->fetch();
    if (!$produto) { header('Location: admin.php'); exit; }
    $g = $pdo->prepare('SELECT id,imagem_url FROM produto_imagens WHERE produto_id=:id');
    $g->execute([':id'=>$id]);
    $galeria = $g->fetchAll();
}

$erros = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf'], $_POST['csrf'] ?? '')) {
        $erros[] = 'Token inválido.';
    }

    $nome      = trim($_POST['nome'] ?? '');
    $categoria = $_POST['categoria'] ?? '';
    $preco     = str_replace(',', '.', $_POST['preco'] ?? '0');
    $descCurta = trim($_POST['descricao_curta'] ?? '');
    $especs    = trim($_POST['especificacoes_tecnicas'] ?? '');
    $videoDestaque = trim($_POST['video_destaque'] ?? '');
    $codigoRegistro = trim($_POST['codigo_registro'] ?? '');

    // Validação do código de registro (obrigatório e único)
    if ($codigoRegistro === '') {
        $erros[] = 'Código de registro é obrigatório.';
    } else {
        // Verifica se já existe outro produto com este código
        $checkSql = 'SELECT id FROM produtos WHERE codigo_registro = :cr';
        $checkParams = [':cr' => $codigoRegistro];
        if ($id) {
            $checkSql .= ' AND id != :id';
            $checkParams[':id'] = $id;
        }
        $checkStmt = $pdo->prepare($checkSql);
        $checkStmt->execute($checkParams);
        if ($checkStmt->fetch()) {
            $erros[] = 'Código de registro já está em uso por outro produto.';
        }
    }

    // === Validação consistente do estoque ===
    // 1) precisa ser numérico (sem letras, decimais ou vazio)
    // 2) precisa ser inteiro >= 0
    // 3) limite superior sane (evita overflow / digitação errada)
    $estoqueRaw = $_POST['estoque'] ?? '';
    if ($estoqueRaw === '' || !is_numeric($estoqueRaw) || (string)(int)$estoqueRaw !== (string)$estoqueRaw) {
        $erros[]  = 'Estoque deve ser um número inteiro.';
        $estoque  = (int)$produto['estoque']; // mantém valor anterior em caso de erro
    } else {
        $estoque = (int)$estoqueRaw;
        if ($estoque < 0)         { $erros[] = 'Estoque não pode ser negativo.'; }
        if ($estoque > 100000)    { $erros[] = 'Estoque acima do limite permitido (100.000).'; }
    }

    if ($nome === '' || mb_strlen($nome) > 180)               $erros[] = 'Nome inválido.';
    
    // Valida categoria contra lista dinâmica
    $categoriaValida = false;
    foreach ($categorias as $cat) {
        if ($cat['nome'] === $categoria) {
            $categoriaValida = true;
            break;
        }
    }
    if (!$categoriaValida) $erros[] = 'Categoria inválida.';
    
    if (!is_numeric($preco) || (float)$preco < 0)             $erros[] = 'Preço inválido.';
    
    // Validação do URL do vídeo (opcional)
    if ($videoDestaque !== '' && !filter_var($videoDestaque, FILTER_VALIDATE_URL)) {
        $erros[] = 'URL do vídeo inválida.';
    }

    /**
     * Upload da imagem principal (opcional na edição)
     */
    $imagem_url = $produto['imagem_url'];
    if (!empty($_FILES['imagem']['name'])) {
        $up = uploadImagem($_FILES['imagem'], $erros);
        if ($up) $imagem_url = $up;
    }
    
    /**
     * Upload do vídeo do produto (opcional)
     */
    $video_url = $produto['video_url'];
    if (!empty($_FILES['video']['name'])) {
        $video_url = uploadVideo($_FILES['video'], $erros);
    }

    if (!$erros) {
        if ($id) {
            $sql = 'UPDATE produtos SET nome=:n,categoria=:c,preco=:p,descricao_curta=:dc,especificacoes_tecnicas=:e,imagem_url=:img,estoque=:s,video_url=:v,video_destaque=:vd,codigo_registro=:cr WHERE id=:id';
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':n'=>$nome,':c'=>$categoria,':p'=>$preco,':dc'=>$descCurta,':e'=>$especs,':img'=>$imagem_url,':s'=>$estoque,':v'=>$video_url,':vd'=>$videoDestaque,':cr'=>$codigoRegistro,':id'=>$id]);
        } else {
            $sql = 'INSERT INTO produtos (nome,categoria,preco,descricao_curta,especificacoes_tecnicas,imagem_url,estoque,video_url,video_destaque,codigo_registro) VALUES (:n,:c,:p,:dc,:e,:img,:s,:v,:vd,:cr)';
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':n'=>$nome,':c'=>$categoria,':p'=>$preco,':dc'=>$descCurta,':e'=>$especs,':img'=>$imagem_url,':s'=>$estoque,':v'=>$video_url,':vd'=>$videoDestaque,':cr'=>$codigoRegistro]);
            $id = (int)$pdo->lastInsertId();
        }

        // Galeria secundária (múltiplas imagens)
        if (!empty($_FILES['galeria']['name'][0])) {
            $files = $_FILES['galeria'];
            for ($i=0; $i < count($files['name']); $i++) {
                $f = ['name'=>$files['name'][$i],'tmp_name'=>$files['tmp_name'][$i],'error'=>$files['error'][$i],'size'=>$files['size'][$i],'type'=>$files['type'][$i]];
                $url = uploadImagem($f, $erros);
                if ($url) {
                    $stmt = $pdo->prepare('INSERT INTO produto_imagens (produto_id,imagem_url) VALUES (:p,:u)');
                    $stmt->execute([':p'=>$id, ':u'=>$url]);
                }
            }
        }

        $msgFlash = 'Produto salvo com sucesso.';
        if ($estoque === 0) {
            $msgFlash .= ' Atenção: estoque = 0, venda bloqueada na loja.';
        }
        $_SESSION['flash'] = $msgFlash;
        header('Location: admin.php');
        exit;
    }
}

/**
 * Faz upload validado e redimensiona a imagem (máx 1600px no lado maior).
 */
function uploadImagem(array $file, array &$erros): ?string {
    if ($file['error'] !== UPLOAD_ERR_OK) { $erros[]='Erro no upload.'; return null; }
    if ($file['size'] > 8 * 1024 * 1024)  { $erros[]='Imagem maior que 8MB.'; return null; }

    $info = @getimagesize($file['tmp_name']);
    if (!$info)                               { $erros[]='Arquivo não é imagem.'; return null; }
    $mime = $info['mime'];
    if (!in_array($mime, ['image/jpeg','image/png'], true)) { $erros[]='Use apenas JPG ou PNG.'; return null; }

    $ext = $mime === 'image/png' ? 'png' : 'jpg';
    $nomeFinal = uniqid('p_', true) . '.' . $ext;
    $destino   = __DIR__ . '/../uploads/' . $nomeFinal;

    // Redimensionamento opcional via GD
    if (function_exists('imagecreatefromstring')) {
        $src = @imagecreatefromstring(file_get_contents($file['tmp_name']));
        if ($src) {
            $w = imagesx($src); $h = imagesy($src);
            $max = 1600;
            if ($w > $max || $h > $max) {
                $r = $w > $h ? $max/$w : $max/$h;
                $nw = (int)($w*$r); $nh = (int)($h*$r);
                $dst = imagecreatetruecolor($nw,$nh);
                if ($mime === 'image/png') { imagealphablending($dst,false); imagesavealpha($dst,true); }
                imagecopyresampled($dst,$src,0,0,0,0,$nw,$nh,$w,$h);
                $mime === 'image/png' ? imagepng($dst,$destino,6) : imagejpeg($dst,$destino,88);
                imagedestroy($src); imagedestroy($dst);
                return 'uploads/'.$nomeFinal;
            }
            imagedestroy($src);
        }
    }
    move_uploaded_file($file['tmp_name'], $destino);
    return 'uploads/'.$nomeFinal;
}

/**
 * Faz upload validado de vídeo (MP4, WebM, Ogg - máx 50MB).
 */
function uploadVideo(array $file, array &$erros): ?string {
    if ($file['error'] !== UPLOAD_ERR_OK) { $erros[]='Erro no upload do vídeo.'; return null; }
    if ($file['size'] > 50 * 1024 * 1024) { $erros[]='Vídeo maior que 50MB.'; return null; }
    
    $mime = $file['type'];
    $allowedMimes = ['video/mp4', 'video/webm', 'video/ogg'];
    if (!in_array($mime, $allowedMimes, true)) { 
        $erros[]='Use apenas vídeos MP4, WebM ou Ogg.'; 
        return null; 
    }
    
    // Verifica extensão pelo nome do arquivo
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['mp4', 'webm', 'ogg'])) {
        $erros[]='Extensão de vídeo não permitida.';
        return null;
    }
    
    $nomeFinal = uniqid('v_', true) . '.' . $ext;
    $destino   = __DIR__ . '/../uploads/' . $nomeFinal;
    
    move_uploaded_file($file['tmp_name'], $destino);
    return 'uploads/'.$nomeFinal;
}

$page_title = $id ? 'Editar produto' : 'Novo produto';
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
  <a class="btn btn-outline-gold btn-sm" href="admin.php">← Voltar</a>
</div></nav>

<main class="container py-5" style="max-width:880px;">
  <h2 class="serif mb-4"><?= $page_title ?></h2>

  <?php foreach ($erros as $e): ?>
    <div class="alert alert-danger small"><?= htmlspecialchars($e) ?></div>
  <?php endforeach; ?>

  <form method="post" enctype="multipart/form-data" class="row g-3">
    <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf']) ?>">

    <div class="col-md-6">
      <label class="form-label small text-uppercase">Código de Registro</label>
      <input class="form-control" name="codigo_registro" maxlength="50" required value="<?= htmlspecialchars($produto['codigo_registro'] ?? '') ?>" placeholder="EX: ESM-2024-001">
      <div class="form-text">Código único para identificar esta peça. Ex: ESM-2024-001, ESC-BR-042, CGA-SD-108</div>
    </div>
    <div class="col-md-6">
      <label class="form-label small text-uppercase">Categoria</label>
      <select class="form-select" name="categoria" required>
        <?php foreach ($categorias as $cat): ?>
          <option value="<?= htmlspecialchars($cat['nome']) ?>" <?= ($produto['categoria'] ?? '') === $cat['nome'] ? 'selected' : '' ?>><?= htmlspecialchars($cat['nome']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-8">
      <label class="form-label small text-uppercase">Nome</label>
      <input class="form-control" name="nome" maxlength="180" required value="<?= htmlspecialchars($produto['nome']) ?>">
    </div>
    <div class="col-md-4">
      <label class="form-label small text-uppercase">Preço (R$)</label>
      <input class="form-control" name="preco" required value="<?= htmlspecialchars($produto['preco']) ?>" placeholder="0.00">
    </div>
    <div class="col-md-4">
      <label class="form-label small text-uppercase">Estoque</label>
      <input class="form-control" type="number" min="0" max="100000" step="1" name="estoque"
             required value="<?= (int)$produto['estoque'] ?>">
      <div class="form-text">Inteiro entre 0 e 100.000. Estoque <strong>0</strong> bloqueia a venda na loja.</div>
    </div>
    <div class="col-12">
      <label class="form-label small text-uppercase">Descrição curta</label>
      <input class="form-control" name="descricao_curta" maxlength="255" value="<?= htmlspecialchars($produto['descricao_curta'] ?? '') ?>">
    </div>
    <div class="col-12">
      <label class="form-label small text-uppercase">Especificações técnicas</label>
      <textarea class="form-control" name="especificacoes_tecnicas" rows="6" placeholder="Quilates: 3.2ct&#10;Origem: Muzo&#10;Lapidação: Oval"><?= htmlspecialchars($produto['especificacoes_tecnicas'] ?? '') ?></textarea>
      <div class="form-text">Use uma linha por item, no formato <code>Chave: valor</code>.</div>
    </div>

    <div class="col-md-6">
      <label class="form-label small text-uppercase">Imagem principal (JPG/PNG)</label>
      <input class="form-control" type="file" name="imagem" accept="image/jpeg,image/png">
      <?php if ($produto['imagem_url']): ?>
        <img src="../<?= htmlspecialchars($produto['imagem_url']) ?>" class="admin-thumb mt-2" style="width:120px;height:120px;">
      <?php endif; ?>
    </div>
    <div class="col-md-6">
      <label class="form-label small text-uppercase">Galeria (várias imagens)</label>
      <input class="form-control" type="file" name="galeria[]" accept="image/jpeg,image/png" multiple>
      <?php if ($galeria): ?>
        <div class="d-flex flex-wrap gap-2 mt-2">
          <?php foreach ($galeria as $g): ?>
            <img src="../<?= htmlspecialchars($g['imagem_url']) ?>" class="admin-thumb" style="width:60px;height:60px;">
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
    
    <div class="col-md-6">
      <label class="form-label small text-uppercase">Vídeo do produto (MP4/WebM/Ogg - máx 50MB)</label>
      <input class="form-control" type="file" name="video" accept="video/mp4,video/webm,video/ogg">
      <?php if (!empty($produto['video_url'])): ?>
        <video controls class="mt-2" style="max-width:200px;">
          <source src="../<?= htmlspecialchars($produto['video_url']) ?>" type="<?= htmlspecialchars(pathinfo($produto['video_url'], PATHINFO_EXTENSION)) === 'mp4' ? 'video/mp4' : (pathinfo($produto['video_url'], PATHINFO_EXTENSION) === 'webm' ? 'video/webm' : 'video/ogg') ?>">
          Seu navegador não suporta vídeos.
        </video>
      <?php endif; ?>
    </div>
    <div class="col-md-6">
      <label class="form-label small text-uppercase">URL do vídeo de destaque (YouTube/Vimeo - opcional)</label>
      <input class="form-control" type="url" name="video_destaque" placeholder="https://www.youtube.com/watch?v=..." value="<?= htmlspecialchars($produto['video_destaque'] ?? '') ?>">
      <div class="form-text">Cole a URL completa do YouTube ou Vimeo para exibir na página inicial.</div>
    </div>

    <div class="col-12 mt-4">
      <button class="btn btn-gold">Salvar peça</button>
      <a class="btn btn-outline-gold ms-2" href="admin.php">Cancelar</a>
    </div>
  </form>
</main>
</body>
</html>
