<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/** Áreas, gestores e faixas de pontuação. */
final class Cadastro
{
    public static function areas(bool $somenteAtivas = false): array
    {
        return Database::todos('SELECT id, uuid, nome, colaboradores, ordem, ativo FROM areas'
            . ($somenteAtivas ? ' WHERE ativo = 1' : '') . ' ORDER BY ordem, nome');
    }

    public static function gestores(bool $somenteAtivos = false): array
    {
        return Database::todos('SELECT g.id, g.uuid, g.nome, g.area_id, g.ordem, g.ativo, a.nome AS area_nome
                                  FROM gestores g LEFT JOIN areas a ON a.id = g.area_id'
            . ($somenteAtivos ? ' WHERE g.ativo = 1' : '') . ' ORDER BY g.ordem, g.nome');
    }

    public static function faixas(): array
    {
        return Database::todos('SELECT id, ordem, nome, limite_superior, cor FROM faixas_pontuacao ORDER BY ordem');
    }

    /**
     * Sincroniza uma lista editada na tela. Itens com id existente são atualizados,
     * itens sem id são criados e itens ausentes são desativados (nunca apagados: há respostas ligadas).
     */
    public static function salvarAreas(array $itens): void
    {
        Database::transacao(function () use ($itens) {
            $mantidos = [];
            foreach (array_values($itens) as $i => $it) {
                $nome = trim((string) ($it['nome'] ?? ''));
                if ($nome === '') {
                    continue;
                }
                $colab = max(0, (int) ($it['colaboradores'] ?? 0));
                $id = (int) ($it['id'] ?? 0);
                if ($id && Database::valor('SELECT id FROM areas WHERE id = ?', [$id])) {
                    Database::executar('UPDATE areas SET nome = ?, colaboradores = ?, ordem = ?, ativo = 1 WHERE id = ?', [mb_substr($nome, 0, 160), $colab, $i + 1, $id]);
                } else {
                    $id = Database::inserir('INSERT INTO areas (uuid, nome, colaboradores, ordem) VALUES (?, ?, ?, ?)', [uuid4(), mb_substr($nome, 0, 160), $colab, $i + 1]);
                }
                $mantidos[] = $id;
            }
            $sql = 'UPDATE areas SET ativo = 0' . ($mantidos ? ' WHERE id NOT IN (' . Database::marcadores($mantidos) . ')' : '');
            Database::executar($sql, $mantidos);
        });
    }

    public static function salvarGestores(array $itens): void
    {
        Database::transacao(function () use ($itens) {
            $mantidos = [];
            foreach (array_values($itens) as $i => $it) {
                $nome = trim((string) ($it['nome'] ?? ''));
                if ($nome === '') {
                    continue;
                }
                $area = (int) ($it['area_id'] ?? 0) ?: null;
                if ($area && !Database::valor('SELECT id FROM areas WHERE id = ?', [$area])) {
                    $area = null;
                }
                $id = (int) ($it['id'] ?? 0);
                if ($id && Database::valor('SELECT id FROM gestores WHERE id = ?', [$id])) {
                    Database::executar('UPDATE gestores SET nome = ?, area_id = ?, ordem = ?, ativo = 1 WHERE id = ?', [mb_substr($nome, 0, 120), $area, $i + 1, $id]);
                } else {
                    $id = Database::inserir('INSERT INTO gestores (uuid, nome, area_id, ordem) VALUES (?, ?, ?, ?)', [uuid4(), mb_substr($nome, 0, 120), $area, $i + 1]);
                }
                $mantidos[] = $id;
            }
            $sql = 'UPDATE gestores SET ativo = 0' . ($mantidos ? ' WHERE id NOT IN (' . Database::marcadores($mantidos) . ')' : '');
            Database::executar($sql, $mantidos);
        });
    }

    /** @return string|null mensagem de erro */
    public static function salvarFaixas(array $itens): ?string
    {
        $faixas = [];
        foreach (array_values($itens) as $it) {
            $nome = trim((string) ($it['nome'] ?? ''));
            $lim = str_replace(',', '.', (string) ($it['limite_superior'] ?? ''));
            $cor = (string) ($it['cor'] ?? '');
            if ($nome === '' || !is_numeric($lim) || !preg_match('/^#[0-9a-fA-F]{6}$/', $cor)) {
                return 'Preencha nome, limite (0 a 10) e cor de todas as faixas.';
            }
            $faixas[] = ['nome' => mb_substr($nome, 0, 80), 'limite' => round((float) $lim, 2), 'cor' => strtoupper($cor)];
        }
        if (count($faixas) < 2) {
            return 'Cadastre pelo menos duas faixas.';
        }
        usort($faixas, static fn ($a, $b) => $a['limite'] <=> $b['limite']);
        $anterior = 0.0;
        foreach ($faixas as $f) {
            if ($f['limite'] <= $anterior || $f['limite'] > 10) {
                return 'Os limites devem ser crescentes, maiores que 0 e no máximo 10.';
            }
            $anterior = $f['limite'];
        }
        if (end($faixas)['limite'] != 10.0) {
            return 'A última faixa deve terminar em 10.';
        }
        Database::transacao(function () use ($faixas) {
            Database::executar('DELETE FROM faixas_pontuacao');
            foreach ($faixas as $i => $f) {
                Database::executar('INSERT INTO faixas_pontuacao (ordem, nome, limite_superior, cor) VALUES (?, ?, ?, ?)', [$i + 1, $f['nome'], $f['limite'], $f['cor']]);
            }
        });
        return null;
    }
}
