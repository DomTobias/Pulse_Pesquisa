<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class Usuario
{
    public static function listar(): array
    {
        return Database::todos('SELECT id, nome, email, perfil, cargo, ativo, ultimo_acesso FROM usuarios_admin ORDER BY nome');
    }

    public static function emailEmUso(string $email, int $exceto = 0): bool
    {
        return (bool) Database::valor('SELECT 1 FROM usuarios_admin WHERE email = ? AND id <> ?', [mb_strtolower($email), $exceto]);
    }

    public static function criar(string $nome, string $email, string $perfil, string $cargo, string $senha): int
    {
        return Database::inserir('INSERT INTO usuarios_admin (nome, email, senha_hash, perfil, cargo, trocar_senha) VALUES (?, ?, ?, ?, ?, 1)',
            [$nome, mb_strtolower($email), password_hash($senha, PASSWORD_DEFAULT), $perfil, $cargo ?: null]);
    }

    public static function atualizar(int $id, string $nome, string $email, string $perfil, string $cargo, bool $ativo): void
    {
        Database::executar('UPDATE usuarios_admin SET nome = ?, email = ?, perfil = ?, cargo = ?, ativo = ? WHERE id = ?',
            [$nome, mb_strtolower($email), $perfil, $cargo ?: null, $ativo ? 1 : 0, $id]);
    }

    public static function atualizarPerfil(int $id, string $nome, string $email, string $cargo): void
    {
        Database::executar('UPDATE usuarios_admin SET nome = ?, email = ?, cargo = ? WHERE id = ?', [$nome, mb_strtolower($email), $cargo ?: null, $id]);
    }

    public static function definirSenha(int $id, string $senha, bool $exigirTroca = false): void
    {
        Database::executar('UPDATE usuarios_admin SET senha_hash = ?, trocar_senha = ?, tentativas_login = 0, bloqueado_ate = NULL WHERE id = ?',
            [password_hash($senha, PASSWORD_DEFAULT), $exigirTroca ? 1 : 0, $id]);
    }

    public static function hash(int $id): ?string
    {
        $h = Database::valor('SELECT senha_hash FROM usuarios_admin WHERE id = ?', [$id]);
        return $h === null ? null : (string) $h;
    }

    public static function adminsAtivos(): int
    {
        return (int) Database::valor("SELECT COUNT(*) FROM usuarios_admin WHERE perfil = 'ADMIN' AND ativo = 1");
    }
}
