<?php
require_once __DIR__ . '/_auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: admin.php'); exit; }
if (!hash_equals($_SESSION['csrf'], $_POST['csrf'] ?? '')) { exit('Token inválido.'); }

$id = (int)($_POST['id'] ?? 0);
if ($id > 0) {
    // Apaga arquivos de imagem do disco
    $s = $pdo->prepare('SELECT imagem_url FROM produtos WHERE id=:id');
    $s->execute([':id'=>$id]);
    if ($img = $s->fetchColumn()) {
        $f = __DIR__ . '/../' . $img;
        if (is_file($f)) @unlink($f);
    }
    $g = $pdo->prepare('SELECT imagem_url FROM produto_imagens WHERE produto_id=:id');
    $g->execute([':id'=>$id]);
    foreach ($g->fetchAll(PDO::FETCH_COLUMN) as $img) {
        $f = __DIR__ . '/../' . $img;
        if (is_file($f)) @unlink($f);
    }

    $pdo->prepare('DELETE FROM produtos WHERE id=:id')->execute([':id'=>$id]);
    $_SESSION['flash'] = 'Produto excluído.';
}
header('Location: admin.php');
