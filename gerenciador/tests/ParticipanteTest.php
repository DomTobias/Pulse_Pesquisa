<?php
declare(strict_types=1);

// A prévia usa uma cópia própria do renderizador do participante.
// Quando a pasta do frontend estiver disponível (ambiente de desenvolvimento), confere se a cópia está igual.
$frontend = __DIR__ . '/../../frontend/assets';
$copia = __DIR__ . '/../public/assets/participante';

afirmar(is_file("$copia/pulse.css") && is_file("$copia/js/app.js"), 'cópia do renderizador existe no gerenciador');

if (!is_dir($frontend)) {
    echo "  (pasta do frontend ausente: comparação ignorada)\n";
    return;
}
foreach (['css/pulse.css' => 'pulse.css', 'js/app.js' => 'js/app.js', 'js/util.js' => 'js/util.js',
          'js/validacao.js' => 'js/validacao.js', 'js/api.js' => 'js/api.js'] as $de => $para) {
    afirmar(hash_file('sha256', "$frontend/$de") === hash_file('sha256', "$copia/$para"),
        "prévia igual ao frontend: $para (se falhar, rode php scripts/atualizar-previa.php)");
}
