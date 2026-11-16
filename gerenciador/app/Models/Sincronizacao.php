<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/** Leitura do estado informado pelo sincronizador (o gerenciador nunca acessa o Supabase). */
final class Sincronizacao
{
    public static function status(): array
    {
        $s = [];
        foreach (Database::todos('SELECT chave, valor, atualizado_em FROM sync_status') as $r) {
            $s[$r['chave']] = ['valor' => $r['valor'], 'atualizado_em' => $r['atualizado_em']];
        }
        return $s;
    }

    public static function erros(): array
    {
        return Database::todos('SELECT e.uuid_envio, e.formulario_id, e.tentativas, e.mensagem_erro, e.criado_em, f.titulo
                                  FROM fila_erros e LEFT JOIN formularios f ON f.uuid = e.formulario_id
                                 ORDER BY e.criado_em DESC LIMIT 200');
    }

    public static function logs(string $nivel = '', int $limite = 100): array
    {
        if ($nivel !== '' && in_array($nivel, ['INFO', 'WARNING', 'ERROR', 'CRITICAL'], true)) {
            return Database::todos('SELECT * FROM sincronizacao_log WHERE nivel = ? ORDER BY id DESC LIMIT ' . $limite, [$nivel]);
        }
        return Database::todos('SELECT * FROM sincronizacao_log ORDER BY id DESC LIMIT ' . $limite);
    }

    public static function publicacoes(int $limite = 30): array
    {
        return Database::todos('SELECT p.id, p.versao, p.status, p.motivo, p.tentativas, p.criado_em, p.enviado_em, p.mensagem_erro, f.titulo, f.id AS formulario_id
                                  FROM publicacoes p JOIN formularios f ON f.id = p.formulario_id ORDER BY p.id DESC LIMIT ' . $limite);
    }

    public static function comandosPendentes(): array
    {
        return Database::todos("SELECT alvo FROM comandos_sync WHERE status = 'PENDENTE'");
    }

    public static function solicitarReprocessamento(string $uuidEnvio, int $usuarioId): void
    {
        $existe = Database::valor("SELECT 1 FROM comandos_sync WHERE comando = 'REPROCESSAR' AND alvo = ? AND status = 'PENDENTE'", [$uuidEnvio]);
        if (!$existe) {
            Database::executar("INSERT INTO comandos_sync (comando, alvo, usuario_id) VALUES ('REPROCESSAR', ?, ?)", [$uuidEnvio, $usuarioId]);
        }
    }

    public static function reenviarPublicacao(int $pubId): bool
    {
        return Database::executar("UPDATE publicacoes SET status = 'PENDENTE', tentativas = 0, mensagem_erro = NULL WHERE id = ? AND status = 'ERRO'", [$pubId])->rowCount() > 0;
    }

    /** Serviço considerado ativo se o último sinal tem menos de 3 intervalos (padrão 30 s). */
    public static function servicoAtivo(array $status): bool
    {
        $hb = $status['servico_heartbeat']['valor'] ?? null;
        return $hb && (time() - strtotime($hb . ' UTC')) < 180;
    }
}
