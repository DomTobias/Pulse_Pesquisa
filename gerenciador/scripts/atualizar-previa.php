<?php
declare(strict_types=1);

/*
 * Atualiza a cópia do renderizador do participante usada na prévia do gerenciador.
 * O gerenciador não lê a pasta do frontend em tempo de execução: rode este script
 * sempre que a página pública (frontend/assets) for alterada.
 *
 *   php scripts/atualizar-previa.php ..\frontend
 */
$origem = rtrim($argv[1] ?? (__DIR__ . '/../../frontend'), '/\\') . '/assets';
$destino = __DIR__ . '/../public/assets/participante';

$arquivos = [
    'css/pulse.css' => 'pulse.css',
    'js/app.js' => 'js/app.js',
    'js/util.js' => 'js/util.js',
    'js/validacao.js' => 'js/validacao.js',
    'js/api.js' => 'js/api.js',
];

if (!is_dir($origem)) {
    fwrite(STDERR, "Pasta do frontend não encontrada: $origem\n");
    exit(1);
}
foreach ($arquivos as $de => $para) {
    $alvo = "$destino/$para";
    if (!is_dir(dirname($alvo))) {
        mkdir(dirname($alvo), 0775, true);
    }
    if (!copy("$origem/$de", $alvo)) {
        fwrite(STDERR, "Falha ao copiar $de\n");
        exit(1);
    }
    echo "copiado: $de -> participante/$para\n";
}
echo "Prévia atualizada.\n";
