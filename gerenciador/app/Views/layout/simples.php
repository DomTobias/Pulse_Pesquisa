<?php
$plataforma = 'Pulse';
try {
    $plataforma = \App\Models\Configuracao::obter('plataforma_nome', 'Pulse');
} catch (\Throwable) {
}
$flash = \App\Core\Session::consumirFlash();
?><!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= e($plataforma) ?></title>
<link rel="icon" href="<?= asset('img/favicon.svg') ?>" type="image/svg+xml">
<link rel="stylesheet" href="<?= asset('vendor/bootstrap/bootstrap.min.css') ?>">
<link rel="stylesheet" href="<?= asset('vendor/bootstrap-icons/bootstrap-icons.min.css') ?>">
<link rel="stylesheet" href="<?= asset('css/app.css') ?>">
<script src="<?= asset('js/tema.js') ?>"></script>
</head>
<body class="tela-simples">
  <div class="simples-wrap">
    <div class="simples-marca"><span class="brand-mark"><?= icone('activity') ?></span><span class="brand-name"><?= e($plataforma) ?></span></div>
    <?php foreach ($flash as $tipo => $msg): ?>
      <div class="alert <?= $tipo === 'erro' ? 'alert-danger' : ($tipo === 'aviso' ? 'alert-warning' : 'alert-success') ?> small"><?= e($msg) ?></div>
    <?php endforeach; ?>
    <?= $conteudo ?>
  </div>
</body>
</html>
