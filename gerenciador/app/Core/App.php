<?php
declare(strict_types=1);

namespace App\Core;

use DateTimeZone;
use Throwable;

final class App
{
    private static array $config = [];
    private static string $raiz = '';
    private static ?DateTimeZone $fuso = null;

    public static function iniciar(string $raiz): void
    {
        self::$raiz = $raiz;
        $arquivo = $raiz . '/config/config.php';
        if (!is_file($arquivo)) {
            http_response_code(500);
            exit('Configuração ausente: copie config/config.example.php para config/config.php.');
        }
        self::$config = require $arquivo;
        date_default_timezone_set('UTC');
        mb_internal_encoding('UTF-8');

        set_exception_handler([self::class, 'tratarErro']);
        set_error_handler(static function (int $nivel, string $msg, string $arq, int $linha): bool {
            if (!(error_reporting() & $nivel)) {
                return false;
            }
            throw new \ErrorException($msg, 0, $nivel, $arq, $linha);
        });
    }

    public static function config(string $chave, mixed $padrao = null): mixed
    {
        $v = self::$config;
        foreach (explode('.', $chave) as $p) {
            if (!is_array($v) || !array_key_exists($p, $v)) {
                return $padrao;
            }
            $v = $v[$p];
        }
        return $v;
    }

    public static function raiz(): string
    {
        return self::$raiz;
    }

    public static function baseUrl(): string
    {
        return rtrim((string) self::config('app.base_url', ''), '/');
    }

    public static function fuso(): DateTimeZone
    {
        if (self::$fuso === null) {
            $nome = 'America/Cuiaba';
            try {
                $nome = \App\Models\Configuracao::obter('fuso_horario', $nome) ?: $nome;
            } catch (Throwable) {
            }
            try {
                self::$fuso = new DateTimeZone($nome);
            } catch (Throwable) {
                self::$fuso = new DateTimeZone('America/Cuiaba');
            }
        }
        return self::$fuso;
    }

    public static function log(string $mensagem): void
    {
        $dir = (string) self::config('logs_dir', self::$raiz . '/storage/logs');
        if (!is_dir($dir)) {
            @mkdir($dir, 0770, true);
        }
        // Nunca registrar senhas ou tokens
        $mensagem = preg_replace('/(senha|password|token|_csrf)=[^&\s]+/i', '$1=***', $mensagem) ?? $mensagem;
        @file_put_contents($dir . '/gerenciador-' . gmdate('Y-m-d') . '.log',
            gmdate('c') . ' ' . $mensagem . PHP_EOL, FILE_APPEND | LOCK_EX);
    }

    public static function tratarErro(Throwable $e): void
    {
        self::log(get_class($e) . ': ' . $e->getMessage() . ' em ' . $e->getFile() . ':' . $e->getLine());
        if (!headers_sent()) {
            http_response_code(500);
        }
        if (self::config('app.debug')) {
            echo '<pre>' . htmlspecialchars((string) $e, ENT_QUOTES, 'UTF-8') . '</pre>';
            return;
        }
        $ehJson = str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json')
            || str_contains($_SERVER['CONTENT_TYPE'] ?? '', 'application/json');
        if ($ehJson) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => false, 'mensagem' => 'Erro interno. Consulte o log do gerenciador.']);
            return;
        }
        View::renderizar('erros/erro', ['titulo' => 'Algo deu errado', 'mensagem' => 'Ocorreu um erro interno. O detalhe foi registrado no log do gerenciador.'], 'simples');
    }
}
