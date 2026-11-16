<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="robots" content="noindex">
<title>Prévia · <?= e($definicao['titulo']) ?></title>
<link rel="icon" href="<?= asset('img/favicon.svg') ?>" type="image/svg+xml">
<link rel="stylesheet" href="<?= asset('vendor/bootstrap/bootstrap.min.css') ?>">
<link rel="stylesheet" href="<?= asset('participante/pulse.css') ?>">
</head>
<body>
<div id="app" class="pl-app" aria-live="polite"></div>
<script type="application/json" id="pulse-definicao"><?= json_encode($definicao, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) ?></script>
<script src="<?= asset('js/previa-boot.js') ?>"></script>
<script type="module" src="<?= asset('participante/js/app.js') ?>"></script>
</body>
</html>
