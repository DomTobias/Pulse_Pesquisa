<?php
// Executa os testes sem dependências: php tests/run.php
declare(strict_types=1);

require __DIR__ . '/../app/autoload.php';

$falhas = 0;
$total = 0;

function afirmar(bool $condicao, string $descricao): void
{
    global $falhas, $total;
    $total++;
    if ($condicao) {
        echo "  ok  $descricao\n";
    } else {
        $falhas++;
        echo "  FALHOU  $descricao\n";
    }
}

function igual(mixed $esperado, mixed $obtido, string $descricao): void
{
    afirmar($esperado === $obtido, $descricao . ($esperado === $obtido ? '' : ' (esperado ' . var_export($esperado, true) . ', obtido ' . var_export($obtido, true) . ')'));
}

foreach (glob(__DIR__ . '/*Test.php') as $arquivo) {
    echo basename($arquivo) . "\n";
    require $arquivo;
}

echo "\n$total verificações, $falhas falha(s).\n";
exit($falhas ? 1 : 0);
