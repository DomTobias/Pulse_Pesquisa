<?php
declare(strict_types=1);

namespace App\Core;

final class Auditoria
{
    /** Registra uma ação administrativa. Nunca inclua senhas ou tokens em $detalhes. */
    public static function registrar(string $acao, string $entidade, ?string $entidadeId = null, array $detalhes = [], ?int $usuarioId = null): void
    {
        unset($detalhes['senha'], $detalhes['senha_hash'], $detalhes['_csrf'], $detalhes['token']);
        try {
            Database::executar(
                'INSERT INTO auditoria (usuario_id, acao, entidade, entidade_id, detalhes, criado_em) VALUES (?, ?, ?, ?, ?, UTC_TIMESTAMP())',
                [$usuarioId ?? Auth::id(), $acao, $entidade, $entidadeId, $detalhes ? json_encode($detalhes, JSON_UNESCAPED_UNICODE) : null]
            );
        } catch (\Throwable $e) {
            App::log('Falha ao auditar: ' . $e->getMessage());
        }
    }
}
