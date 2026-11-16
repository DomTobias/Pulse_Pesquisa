<?php
declare(strict_types=1);

namespace App\Core;

final class Auth
{
    public const MAX_TENTATIVAS = 5;
    public const BLOQUEIO_MINUTOS = 15;

    private static ?array $usuario = null;

    /** @return array{ok:bool, mensagem?:string} */
    public static function entrar(string $email, string $senha): array
    {
        $email = mb_strtolower(trim($email));
        $u = Database::um('SELECT * FROM usuarios_admin WHERE email = ? AND ativo = 1', [$email]);
        $generica = 'E-mail ou senha incorretos.';
        if (!$u) {
            password_verify($senha, '$2y$12$VHpS9cnDfddZm/3h7JAUIO7VfX/xPVYYmq8dFdMPAIIk9CcpcgV9i'); // tempo constante
            return ['ok' => false, 'mensagem' => $generica];
        }
        if ($u['bloqueado_ate'] && strtotime($u['bloqueado_ate'] . ' UTC') > time()) {
            return ['ok' => false, 'mensagem' => 'Acesso bloqueado temporariamente por excesso de tentativas. Tente novamente em alguns minutos.'];
        }
        if (!password_verify($senha, $u['senha_hash'])) {
            $tent = (int) $u['tentativas_login'] + 1;
            $bloq = $tent >= self::MAX_TENTATIVAS ? gmdate('Y-m-d H:i:s', time() + self::BLOQUEIO_MINUTOS * 60) : null;
            Database::executar('UPDATE usuarios_admin SET tentativas_login = ?, bloqueado_ate = ? WHERE id = ?',
                [$bloq ? 0 : $tent, $bloq, $u['id']]);
            Auditoria::registrar('LOGIN_FALHOU', 'usuario', (string) $u['id'], [], (int) $u['id']);
            return ['ok' => false, 'mensagem' => $bloq ? 'Muitas tentativas. O acesso foi bloqueado por ' . self::BLOQUEIO_MINUTOS . ' minutos.' : $generica];
        }
        if (password_needs_rehash($u['senha_hash'], PASSWORD_DEFAULT)) {
            Database::executar('UPDATE usuarios_admin SET senha_hash = ? WHERE id = ?', [password_hash($senha, PASSWORD_DEFAULT), $u['id']]);
        }
        Database::executar('UPDATE usuarios_admin SET tentativas_login = 0, bloqueado_ate = NULL, ultimo_acesso = UTC_TIMESTAMP() WHERE id = ?', [$u['id']]);
        Session::regenerar();
        Session::set('usuario_id', (int) $u['id']);
        Csrf::token();
        Auditoria::registrar('LOGIN', 'usuario', (string) $u['id']);
        return ['ok' => true];
    }

    public static function sair(): void
    {
        if (self::id()) {
            Auditoria::registrar('LOGOUT', 'usuario', (string) self::id());
        }
        Session::destruir();
    }

    public static function id(): ?int
    {
        $id = Session::get('usuario_id');
        return $id ? (int) $id : null;
    }

    public static function usuario(): ?array
    {
        if (self::$usuario === null && self::id()) {
            self::$usuario = Database::um('SELECT id, nome, email, perfil, cargo, trocar_senha, ultimo_acesso FROM usuarios_admin WHERE id = ? AND ativo = 1', [self::id()]);
        }
        return self::$usuario;
    }

    public static function ehAdmin(): bool
    {
        return (self::usuario()['perfil'] ?? '') === 'ADMIN';
    }

    public static function limparCache(): void
    {
        self::$usuario = null;
    }

    /** Política mínima de senha. Retorna mensagem de erro ou null. */
    public static function validarSenha(string $s): ?string
    {
        if (mb_strlen($s) < 10) {
            return 'A senha deve ter pelo menos 10 caracteres.';
        }
        if (!preg_match('/[A-Za-z]/', $s) || !preg_match('/\d/', $s)) {
            return 'Use letras e números na senha.';
        }
        return null;
    }
}
