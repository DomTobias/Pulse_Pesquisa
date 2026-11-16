<?php
declare(strict_types=1);

namespace App\Core;

final class Session
{
    public static function iniciar(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
        session_name('PULSESESS');
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => (App::baseUrl() ?: '') . '/',
            'secure' => $https,
            'httponly' => true,
            'samesite' => 'Strict',
        ]);
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        session_start();

        $limite = (int) App::config('app.sessao_minutos', 30) * 60;
        $agora = time();
        if (isset($_SESSION['_ultimo']) && $agora - (int) $_SESSION['_ultimo'] > $limite) {
            $_SESSION = [];
            session_regenerate_id(true);
            $_SESSION['_flash']['aviso'] = 'Sua sessão expirou por inatividade. Entre novamente.';
        }
        $_SESSION['_ultimo'] = $agora;
    }

    public static function get(string $k, mixed $padrao = null): mixed
    {
        return $_SESSION[$k] ?? $padrao;
    }

    public static function set(string $k, mixed $v): void
    {
        $_SESSION[$k] = $v;
    }

    public static function remover(string $k): void
    {
        unset($_SESSION[$k]);
    }

    public static function regenerar(): void
    {
        session_regenerate_id(true);
    }

    public static function destruir(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
        session_destroy();
    }

    public static function flash(string $tipo, string $mensagem): void
    {
        $_SESSION['_flash'][$tipo] = $mensagem;
    }

    public static function consumirFlash(): array
    {
        $f = $_SESSION['_flash'] ?? [];
        unset($_SESSION['_flash']);
        return $f;
    }
}
