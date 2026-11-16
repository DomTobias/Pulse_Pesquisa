<?php
declare(strict_types=1);

// Front controller do gerenciador (uso local, nunca exposto à internet).

if (PHP_SAPI === 'cli-server') {
    // Servidor embutido: arquivos estáticos de public/ são servidos diretamente
    $arquivo = __DIR__ . parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    if ($arquivo !== __FILE__ && is_file($arquivo)) {
        return false;
    }
}

$raiz = dirname(__DIR__);
require $raiz . '/app/autoload.php';

use App\Core\App;
use App\Core\Router;
use App\Core\Session;

App::iniciar($raiz);

// Cabeçalhos de segurança
header_remove('X-Powered-By');
// Páginas com dados internos não ficam em cache (downloads e imagens definem o próprio Cache-Control)
header('Cache-Control: no-store, max-age=0');
if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
    header('Strict-Transport-Security: max-age=31536000');
}
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: same-origin');
header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self' data: blob:; font-src 'self'; connect-src 'self'; frame-src 'self'; frame-ancestors 'self'; form-action 'self'; base-uri 'self'; object-src 'none'");

Session::iniciar();

$caminho = (string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$base = App::baseUrl();
if ($base !== '' && str_starts_with($caminho, $base)) {
    $caminho = substr($caminho, strlen($base));
}

$r = new Router();
require $raiz . '/routes/web.php';
$r->despachar($_SERVER['REQUEST_METHOD'] ?? 'GET', rawurldecode($caminho ?: '/'));
