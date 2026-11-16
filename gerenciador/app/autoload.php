<?php
declare(strict_types=1);

// Autoload PSR-4 simples para App\ (sem depender do Composer).
spl_autoload_register(static function (string $classe): void {
    if (!str_starts_with($classe, 'App\\')) {
        return;
    }
    $arquivo = __DIR__ . '/' . str_replace('\\', '/', substr($classe, 4)) . '.php';
    if (is_file($arquivo)) {
        require $arquivo;
    }
});

// Dependências opcionais instaladas pelo Composer (PhpSpreadsheet para Excel)
$composer = dirname(__DIR__) . '/vendor/autoload.php';
if (is_file($composer)) {
    require_once $composer;
}

require_once __DIR__ . '/Core/helpers.php';
