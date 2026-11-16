<?php
declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOStatement;

/** PDO com prepared statements reais e sessão em UTC. */
final class Database
{
    private static ?PDO $pdo = null;

    public static function pdo(): PDO
    {
        if (self::$pdo === null) {
            $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
                App::config('db.host', '127.0.0.1'), (int) App::config('db.port', 3306), App::config('db.nome', 'pulse'));
            self::$pdo = new PDO($dsn, (string) App::config('db.usuario'), (string) App::config('db.senha'), [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_TIMEOUT => 10,
            ]);
            self::$pdo->exec("SET time_zone = '+00:00'");
        }
        return self::$pdo;
    }

    public static function executar(string $sql, array $params = []): PDOStatement
    {
        $st = self::pdo()->prepare($sql);
        $st->execute($params);
        return $st;
    }

    public static function todos(string $sql, array $params = []): array
    {
        return self::executar($sql, $params)->fetchAll();
    }

    public static function um(string $sql, array $params = []): ?array
    {
        $r = self::executar($sql, $params)->fetch();
        return $r === false ? null : $r;
    }

    public static function valor(string $sql, array $params = []): mixed
    {
        $r = self::executar($sql, $params)->fetchColumn();
        return $r === false ? null : $r;
    }

    public static function inserir(string $sql, array $params = []): int
    {
        self::executar($sql, $params);
        return (int) self::pdo()->lastInsertId();
    }

    /** Executa $fn dentro de uma transação. */
    public static function transacao(callable $fn): mixed
    {
        $pdo = self::pdo();
        $pdo->beginTransaction();
        try {
            $r = $fn($pdo);
            $pdo->commit();
            return $r;
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /** Gera "?, ?, ?" para cláusulas IN. */
    public static function marcadores(array $itens): string
    {
        return implode(',', array_fill(0, max(1, count($itens)), '?'));
    }
}
