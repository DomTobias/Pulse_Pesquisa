<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Auditoria;
use App\Core\Database;
use App\Models\Formulario;

/**
 * Fluxo B: o gerenciador grava a definição em `publicacoes` (PENDENTE) e o sincronizador
 * a envia ao Supabase. O gerenciador nunca se conecta ao Supabase.
 */
final class PublicacaoService
{
    /** @return string|null mensagem de erro */
    public static function publicar(int $id): ?string
    {
        $f = Formulario::buscar($id);
        if (!$f) {
            return 'Formulário não encontrado.';
        }
        $perguntas = 0;
        foreach (Formulario::estrutura($id) as $t) {
            foreach ($t['perguntas'] as $p) {
                $perguntas += $p['tipo'] !== 'INFO' ? 1 : 0;
            }
        }
        if (!$perguntas) {
            return 'Adicione pelo menos uma pergunta antes de publicar.';
        }
        if ($f['fim_em'] && strtotime($f['fim_em'] . ' UTC') <= time()) {
            return 'A data de encerramento já passou. Ajuste o período no construtor antes de publicar.';
        }
        if ((int) $f['segmentado'] === 1 && !Database::valor('SELECT 1 FROM areas WHERE ativo = 1')) {
            return 'O formulário pede área e gestor, mas não há áreas ativas em Configurações.';
        }
        Database::transacao(function () use ($id) {
            Formulario::definirEstado($id, 'ATIVO', gmdate('Y-m-d H:i:s'), true);
            self::enfileirar($id, 'PUBLICAR', 'ATIVO');
        });
        Auditoria::registrar('PUBLICAR', 'formulario', (string) $id);
        return null;
    }

    public static function encerrar(int $id): void
    {
        Database::transacao(function () use ($id) {
            Formulario::encerrarAgora($id);
            self::enfileirar($id, 'ENCERRAR', 'ENCERRADO');
        });
        Auditoria::registrar('ENCERRAR', 'formulario', (string) $id);
    }

    public static function despublicar(int $id): void
    {
        Database::transacao(function () use ($id) {
            Formulario::definirEstado($id, 'RASCUNHO');
            self::enfileirar($id, 'DESPUBLICAR', 'DESPUBLICADO');
        });
        Auditoria::registrar('DESPUBLICAR', 'formulario', (string) $id);
    }

    /** Depois de salvar no construtor: reenvia a definição se o formulário já estiver no ar. */
    public static function atualizarSeNoAr(int $id): bool
    {
        $f = Formulario::buscar($id);
        if (!$f || $f['estado'] === 'RASCUNHO') {
            return false;
        }
        self::enfileirar($id, 'ATUALIZAR', $f['estado'] === 'ENCERRADO' ? 'ENCERRADO' : 'ATIVO');
        return true;
    }

    /** Já houve publicação enviada (o formulário existe no Supabase)? */
    public static function jaPublicado(int $id): bool
    {
        return (bool) Database::valor('SELECT 1 FROM publicacoes WHERE formulario_id = ? LIMIT 1', [$id]);
    }

    private static function enfileirar(int $id, string $motivo, string $estado): void
    {
        $f = Formulario::buscar($id);
        $definicao = DefinicaoBuilder::montar($f, $estado);
        Database::executar('INSERT INTO publicacoes (formulario_id, versao, status, motivo, definicao) VALUES (?, ?, \'PENDENTE\', ?, ?)',
            [$id, (int) $f['versao'], $motivo, json_encode($definicao, JSON_UNESCAPED_UNICODE)]);
    }
}
