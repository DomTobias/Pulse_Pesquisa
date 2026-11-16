<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class Formulario
{
    public const TIPOS = ['CURTA', 'LONGA', 'NUMERO', 'EMAIL', 'TELEFONE', 'DATA', 'UNICA', 'MULTIPLA', 'LISTA', 'ESCALA', 'SIM_NAO', 'INFO'];
    public const COM_OPCOES = ['UNICA', 'MULTIPLA', 'LISTA'];
    public const RECORRENCIAS = ['NENHUMA' => 'Sem recorrência', 'SEMANAL' => 'Semanal', 'QUINZENAL' => 'Quinzenal', 'MENSAL' => 'Mensal'];
    public const FORMATOS = ['AUTO' => 'Automático', 'ETAPAS' => 'Uma pergunta por tela', 'PAGINA_UNICA' => 'Um tópico por tela'];
    public const CAPAS = ['INSTITUCIONAL', 'FAIXA', 'IMAGEM', 'NENHUMA'];
    public const STATUS = [
        'RASCUNHO' => ['Rascunho', 'secondary'],
        'AGENDADO' => ['Agendado', 'info'],
        'PUBLICADO' => ['Publicado', 'success'],
        'ENCERRADO' => ['Encerrado', 'dark'],
    ];

    /** Status exibido: RASCUNHO, AGENDADO, PUBLICADO ou ENCERRADO (mesma regra do Worker). */
    public static function statusEfetivo(array $f, ?int $agora = null): string
    {
        $agora ??= time();
        if ($f['estado'] === 'RASCUNHO') {
            return 'RASCUNHO';
        }
        if ($f['estado'] === 'ENCERRADO') {
            return 'ENCERRADO';
        }
        if ($f['inicio_em'] && strtotime($f['inicio_em'] . ' UTC') > $agora) {
            return 'AGENDADO';
        }
        if ($f['fim_em'] && strtotime($f['fim_em'] . ' UTC') < $agora) {
            return 'ENCERRADO';
        }
        return 'PUBLICADO';
    }

    public static function listar(string $busca = '', string $status = ''): array
    {
        $params = [];
        $sql = "SELECT f.*, (SELECT COUNT(*) FROM respostas r WHERE r.formulario_id = f.id) AS total_respostas,
                       (SELECT MAX(r.recebido_em) FROM respostas r WHERE r.formulario_id = f.id) AS ultima_resposta,
                       (SELECT COUNT(*) FROM perguntas p JOIN topicos t ON t.id = p.topico_id
                         WHERE t.formulario_id = f.id AND p.ativo = 1 AND p.tipo <> 'INFO') AS total_perguntas,
                       (SELECT p.status FROM publicacoes p WHERE p.formulario_id = f.id ORDER BY p.id DESC LIMIT 1) AS publicacao_status
                  FROM formularios f WHERE f.excluido_em IS NULL";
        if ($busca !== '') {
            $sql .= ' AND (f.titulo LIKE ? OR f.slug LIKE ?)';
            $params[] = '%' . $busca . '%';
            $params[] = '%' . $busca . '%';
        }
        $sql .= ' ORDER BY f.atualizado_em DESC';
        $lista = Database::todos($sql, $params);
        foreach ($lista as &$f) {
            $f['status'] = self::statusEfetivo($f);
        }
        unset($f);
        if ($status !== '' && isset(self::STATUS[$status])) {
            $lista = array_values(array_filter($lista, static fn ($f) => $f['status'] === $status));
        }
        return $lista;
    }

    public static function buscar(int $id): ?array
    {
        $f = Database::um('SELECT * FROM formularios WHERE id = ? AND excluido_em IS NULL', [$id]);
        if ($f) {
            $f['capa'] = $f['capa'] ? (json_decode((string) $f['capa'], true) ?: []) : [];
            $f['status'] = self::statusEfetivo($f);
        }
        return $f;
    }

    public static function totalRespostas(int $id): int
    {
        return (int) Database::valor('SELECT COUNT(*) FROM respostas WHERE formulario_id = ?', [$id]);
    }

    /**
     * Estrutura de tópicos/perguntas/opções.
     * @param bool $incluirInativas inclui perguntas removidas que guardam histórico (relatórios)
     */
    public static function estrutura(int $id, bool $incluirInativas = false): array
    {
        $filtro = $incluirInativas ? '' : ' AND ativo = 1';
        $topicos = Database::todos('SELECT id, uuid, titulo, descricao, ordem, ativo FROM topicos WHERE formulario_id = ?' . $filtro . ' ORDER BY ordem, id', [$id]);
        if (!$topicos) {
            return [];
        }
        $ids = array_column($topicos, 'id');
        $perguntas = Database::todos('SELECT * FROM perguntas WHERE topico_id IN (' . Database::marcadores($ids) . ')'
            . ($incluirInativas ? '' : ' AND ativo = 1') . ' ORDER BY ordem, id', $ids);
        $pids = array_column($perguntas, 'id');
        $opcoes = $pids ? Database::todos('SELECT id, uuid, pergunta_id, rotulo, ordem, ativo FROM opcoes WHERE pergunta_id IN ('
            . Database::marcadores($pids) . ')' . ($incluirInativas ? '' : ' AND ativo = 1') . ' ORDER BY ordem, id', $pids) : [];
        $porPergunta = [];
        foreach ($opcoes as $o) {
            $porPergunta[$o['pergunta_id']][] = $o;
        }
        $porTopico = [];
        foreach ($perguntas as $p) {
            $p['opcoes'] = $porPergunta[$p['id']] ?? [];
            $porTopico[$p['topico_id']][] = $p;
        }
        foreach ($topicos as &$t) {
            $t['perguntas'] = $porTopico[$t['id']] ?? [];
        }
        return $topicos;
    }

    public static function slugDisponivel(string $slug, int $exceto = 0): bool
    {
        return !Database::valor('SELECT 1 FROM formularios WHERE slug = ? AND id <> ?', [$slug, $exceto]);
    }

    public static function gerarSlug(string $titulo, int $exceto = 0): string
    {
        $base = slugify($titulo) ?: 'formulario';
        $slug = $base;
        $n = 2;
        while (!self::slugDisponivel($slug, $exceto)) {
            $slug = $base . '-' . $n++;
        }
        return $slug;
    }

    public static function criar(string $titulo): int
    {
        $titulo = trim($titulo) ?: 'Formulário sem título';
        $id = Database::inserir('INSERT INTO formularios (uuid, slug, titulo, mensagem_conclusao, formato, exige_identificacao, permite_multiplas, cor, capa)
                                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)', [
            uuid4(), self::gerarSlug($titulo), mb_substr($titulo, 0, 200),
            Configuracao::obter('padrao_mensagem_conclusao', 'Obrigado! Sua resposta foi registrada.'),
            Configuracao::obter('padrao_formato', 'AUTO'),
            (int) Configuracao::obter('padrao_exige_identificacao', '0'),
            (int) Configuracao::obter('padrao_permite_multiplas', '0'),
            Configuracao::obter('cor_primaria', '#032B4C'),
            json_encode(['tipo' => 'FAIXA'], JSON_UNESCAPED_UNICODE),
        ]);
        Database::executar('INSERT INTO topicos (uuid, formulario_id, titulo, ordem) VALUES (?, ?, ?, 1)', [uuid4(), $id, 'Tópico 1']);
        return $id;
    }

    public static function duplicar(int $id): int
    {
        return Database::transacao(function () use ($id) {
            $f = Database::um('SELECT * FROM formularios WHERE id = ?', [$id]);
            $titulo = mb_substr('Cópia de ' . $f['titulo'], 0, 200);
            $novo = Database::inserir('INSERT INTO formularios (uuid, slug, titulo, descricao, mensagem_apresentacao, mensagem_conclusao, estado, recorrencia,
                                       topico_por_ciclo, segmentado, exige_identificacao, permite_multiplas, formato, cor, capa)
                                       VALUES (?, ?, ?, ?, ?, ?, \'RASCUNHO\', ?, ?, ?, ?, ?, ?, ?, ?)', [
                uuid4(), self::gerarSlug($titulo), $titulo, $f['descricao'], $f['mensagem_apresentacao'], $f['mensagem_conclusao'],
                $f['recorrencia'], $f['topico_por_ciclo'], $f['segmentado'], $f['exige_identificacao'], $f['permite_multiplas'], $f['formato'], $f['cor'], $f['capa'],
            ]);
            foreach (self::estrutura($id) as $t) {
                $tid = Database::inserir('INSERT INTO topicos (uuid, formulario_id, titulo, descricao, ordem) VALUES (?, ?, ?, ?, ?)',
                    [uuid4(), $novo, $t['titulo'], $t['descricao'], $t['ordem']]);
                foreach ($t['perguntas'] as $p) {
                    $pid = Database::inserir('INSERT INTO perguntas (uuid, topico_id, tipo, titulo, orientacao, obrigatoria, ordem, minimo, maximo, rotulo_minimo, rotulo_maximo, aparencia)
                                              VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                        [uuid4(), $tid, $p['tipo'], $p['titulo'], $p['orientacao'], $p['obrigatoria'], $p['ordem'], $p['minimo'], $p['maximo'], $p['rotulo_minimo'], $p['rotulo_maximo'], $p['aparencia']]);
                    foreach ($p['opcoes'] as $o) {
                        Database::executar('INSERT INTO opcoes (uuid, pergunta_id, rotulo, ordem) VALUES (?, ?, ?, ?)', [uuid4(), $pid, $o['rotulo'], $o['ordem']]);
                    }
                }
            }
            return $novo;
        });
    }

    /** Exclusão lógica: some das listas, mas as respostas continuam no banco. */
    public static function excluir(int $id): void
    {
        Database::executar("UPDATE formularios SET excluido_em = UTC_TIMESTAMP(), estado = 'RASCUNHO', slug = CONCAT('excluido-', id, '-', slug) WHERE id = ?", [$id]);
    }

    public static function definirEstado(int $id, string $estado, ?string $inicioUtc = null, bool $ajustarInicio = false): void
    {
        if ($ajustarInicio) {
            Database::executar('UPDATE formularios SET estado = ?, inicio_em = COALESCE(inicio_em, ?) WHERE id = ?', [$estado, $inicioUtc, $id]);
        } else {
            Database::executar('UPDATE formularios SET estado = ? WHERE id = ?', [$estado, $id]);
        }
    }

    public static function encerrarAgora(int $id): void
    {
        Database::executar("UPDATE formularios SET estado = 'ENCERRADO', fim_em = IF(fim_em IS NULL OR fim_em > UTC_TIMESTAMP(), UTC_TIMESTAMP(), fim_em) WHERE id = ?", [$id]);
    }
}
