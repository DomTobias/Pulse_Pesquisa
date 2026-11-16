<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class Configuracao
{
    private static ?array $cache = null;

    public static function todas(): array
    {
        if (self::$cache === null) {
            self::$cache = [];
            foreach (Database::todos('SELECT chave, valor FROM configuracoes') as $r) {
                self::$cache[$r['chave']] = $r['valor'];
            }
        }
        return self::$cache;
    }

    public static function obter(string $chave, ?string $padrao = null): ?string
    {
        return self::todas()[$chave] ?? $padrao;
    }

    public static function inteiro(string $chave, int $padrao): int
    {
        $v = self::obter($chave);
        return is_numeric($v) ? (int) $v : $padrao;
    }

    public static function salvar(array $valores): void
    {
        foreach ($valores as $k => $v) {
            Database::executar('INSERT INTO configuracoes (chave, valor) VALUES (?, ?) ON DUPLICATE KEY UPDATE valor = VALUES(valor)', [$k, $v]);
        }
        self::$cache = null;
    }

    /** URL pública de um formulário: domínio configurado + /formulario/slug */
    public static function linkPublico(string $slug): string
    {
        return rtrim((string) self::obter('dominio_links', ''), '/') . '/formulario/' . rawurlencode($slug);
    }
}
