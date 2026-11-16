<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Models\Formulario;

/**
 * Valida e grava o formulário editado no construtor.
 * Regras de histórico:
 *  - perguntas/opções/tópicos removidos que já têm respostas ficam inativos (não são apagados);
 *  - não é permitido trocar o tipo de uma pergunta que já tem respostas;
 *  - se a estrutura mudar depois de publicado ou com respostas, a versão é incrementada.
 */
final class ConstrutorService
{
    public const LIM = ['titulo' => 200, 'texto' => 5000, 'pergunta' => 500, 'opcao' => 300, 'rotulo' => 80, 'capa' => 300];
    private const UUID = '/^[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i';
    private const IMG = '/^local:[a-f0-9]{64}\.(png|jpg|jpeg|webp|svg)$/';

    /** @return array{ok:bool, erros?:list<string>, versao?:int, mudou?:bool} */
    public function salvar(int $id, array $d): array
    {
        $form = Formulario::buscar($id);
        if (!$form) {
            return ['ok' => false, 'erros' => ['Formulário não encontrado.']];
        }
        [$dados, $erros] = $this->validar($id, $d);
        if ($erros) {
            return ['ok' => false, 'erros' => $erros];
        }

        return Database::transacao(function () use ($id, $form, $dados) {
            $antes = $this->assinatura($id);
            $temRespostas = Formulario::totalRespostas($id) > 0;
            $erroTipo = $this->gravarEstrutura($id, $dados['topicos'], (int) $form['versao'] + 1);
            if ($erroTipo) {
                throw new RegraConstrutor($erroTipo);
            }
            $mudou = $antes !== $this->assinatura($id);
            $versao = (int) $form['versao'];
            if ($mudou && ($temRespostas || $form['estado'] !== 'RASCUNHO')) {
                $versao++;
            }
            Database::executar('UPDATE formularios SET titulo = ?, descricao = ?, slug = ?, mensagem_apresentacao = ?, mensagem_conclusao = ?,
                                inicio_em = ?, fim_em = ?, recorrencia = ?, topico_por_ciclo = ?, segmentado = ?, exige_identificacao = ?, permite_multiplas = ?,
                                formato = ?, cor = ?, capa = ?, versao = ? WHERE id = ?', [
                $dados['titulo'], $dados['descricao'], $dados['slug'], $dados['mensagem_apresentacao'], $dados['mensagem_conclusao'],
                $dados['inicio_em'], $dados['fim_em'], $dados['recorrencia'], $dados['topico_por_ciclo'], $dados['segmentado'], $dados['exige_identificacao'],
                $dados['permite_multiplas'], $dados['formato'], $dados['cor'], json_encode($dados['capa'], JSON_UNESCAPED_UNICODE), $versao, $id,
            ]);
            return ['ok' => true, 'versao' => $versao, 'mudou' => $mudou];
        });
    }

    /** @return array{0:array,1:list<string>} */
    public function validar(int $id, array $d): array
    {
        $erros = [];
        $txt = static fn ($v, int $max) => mb_substr(trim((string) ($v ?? '')), 0, $max);
        $titulo = $txt($d['titulo'] ?? '', self::LIM['titulo']);
        if ($titulo === '') {
            $erros[] = 'Informe o título do formulário.';
        }
        $slug = slugify((string) ($d['slug'] ?? '')) ?: slugify($titulo);
        if ($slug === '' || !preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug)) {
            $erros[] = 'O endereço do link deve ter apenas letras minúsculas, números e hífens.';
        } elseif (!Formulario::slugDisponivel($slug, $id)) {
            $erros[] = 'Já existe outro formulário com este endereço de link.';
        }
        $recorrencia = (string) ($d['recorrencia'] ?? 'NENHUMA');
        if (!isset(Formulario::RECORRENCIAS[$recorrencia])) {
            $erros[] = 'Recorrência inválida.';
        }
        $formato = (string) ($d['formato'] ?? 'AUTO');
        if (!isset(Formulario::FORMATOS[$formato])) {
            $erros[] = 'Formato inválido.';
        }
        $cor = (string) ($d['cor'] ?? '#032B4C');
        if (!preg_match('/^#[0-9a-fA-F]{6}$/', $cor)) {
            $erros[] = 'Cor inválida.';
        }
        $inicio = utc_de_local($d['inicio_em'] ?? '');
        $fim = utc_de_local($d['fim_em'] ?? '');
        if (($d['inicio_em'] ?? '') !== '' && !$inicio) {
            $erros[] = 'Data de abertura inválida.';
        }
        if (($d['fim_em'] ?? '') !== '' && !$fim) {
            $erros[] = 'Data de encerramento inválida.';
        }
        if ($inicio && $fim && strtotime($fim) <= strtotime($inicio)) {
            $erros[] = 'O encerramento deve ser depois da abertura.';
        }
        if ($recorrencia !== 'NENHUMA' && !$inicio) {
            $erros[] = 'Pesquisas recorrentes precisam de uma data de abertura (início do 1º ciclo).';
        }

        $capaIn = is_array($d['capa'] ?? null) ? $d['capa'] : [];
        $capa = ['tipo' => in_array($capaIn['tipo'] ?? '', Formulario::CAPAS, true) ? $capaIn['tipo'] : 'NENHUMA'];
        foreach (['titulo', 'subtitulo', 'chamada', 'destaque_titulo', 'destaque_texto', 'convite', 'encerramento'] as $k) {
            $capa[$k] = $txt($capaIn[$k] ?? '', self::LIM['capa']);
        }
        foreach (['simbolo', 'foto', 'imagem'] as $k) {
            $v = $capaIn[$k] ?? null;
            if ($v !== null && $v !== '' && !preg_match(self::IMG, (string) $v)) {
                $erros[] = 'Imagem de capa inválida. Envie a imagem novamente.';
                $v = null;
            }
            $capa[$k] = $v ?: null;
        }

        $topicos = [];
        $nPerg = 0;
        foreach (array_values(is_array($d['topicos'] ?? null) ? $d['topicos'] : []) as $ti => $t) {
            $tt = $txt($t['titulo'] ?? '', self::LIM['titulo']);
            if ($tt === '') {
                $erros[] = 'O tópico ' . ($ti + 1) . ' está sem título.';
            }
            $perguntas = [];
            foreach (array_values(is_array($t['perguntas'] ?? null) ? $t['perguntas'] : []) as $pi => $p) {
                $nPerg++;
                $ref = 'Tópico ' . ($ti + 1) . ', pergunta ' . ($pi + 1);
                $tipo = (string) ($p['tipo'] ?? '');
                if (!in_array($tipo, Formulario::TIPOS, true)) {
                    $erros[] = "$ref: tipo inválido.";
                    continue;
                }
                $pt = $txt($p['titulo'] ?? '', self::LIM['pergunta']);
                if ($pt === '') {
                    $erros[] = "$ref: escreva o enunciado.";
                }
                $min = $max = null;
                if ($tipo === 'ESCALA') {
                    $min = is_numeric($p['minimo'] ?? null) ? (int) $p['minimo'] : 0;
                    $max = is_numeric($p['maximo'] ?? null) ? (int) $p['maximo'] : 10;
                    if ($max <= $min || $max - $min > 10) {
                        $erros[] = "$ref: a escala deve ter mínimo menor que o máximo e no máximo 11 pontos.";
                    }
                } elseif ($tipo === 'NUMERO') {
                    $min = is_numeric($p['minimo'] ?? null) ? (float) $p['minimo'] : null;
                    $max = is_numeric($p['maximo'] ?? null) ? (float) $p['maximo'] : null;
                    if ($min !== null && $max !== null && $max < $min) {
                        $erros[] = "$ref: o valor máximo deve ser maior que o mínimo.";
                    }
                }
                $opcoes = [];
                if (in_array($tipo, Formulario::COM_OPCOES, true)) {
                    foreach (array_values(is_array($p['opcoes'] ?? null) ? $p['opcoes'] : []) as $o) {
                        $r = $txt($o['rotulo'] ?? '', self::LIM['opcao']);
                        if ($r !== '') {
                            $opcoes[] = ['uuid' => $this->uuidOuNulo($o['uuid'] ?? null), 'rotulo' => $r];
                        }
                    }
                    if (!$opcoes) {
                        $erros[] = "$ref: cadastre pelo menos uma opção.";
                    }
                    if (count(array_unique(array_map(static fn ($o) => mb_strtolower($o['rotulo']), $opcoes))) !== count($opcoes)) {
                        $erros[] = "$ref: há opções repetidas.";
                    }
                }
                $perguntas[] = [
                    'uuid' => $this->uuidOuNulo($p['uuid'] ?? null),
                    'tipo' => $tipo,
                    'titulo' => $pt,
                    'orientacao' => $txt($p['orientacao'] ?? '', self::LIM['texto']),
                    'obrigatoria' => $tipo !== 'INFO' && !empty($p['obrigatoria']) ? 1 : 0,
                    'minimo' => $min,
                    'maximo' => $max,
                    'rotulo_minimo' => $tipo === 'ESCALA' ? $txt($p['rotulo_minimo'] ?? '', self::LIM['rotulo']) : null,
                    'rotulo_maximo' => $tipo === 'ESCALA' ? $txt($p['rotulo_maximo'] ?? '', self::LIM['rotulo']) : null,
                    'aparencia' => $tipo === 'ESCALA' && ($p['aparencia'] ?? '') === 'ZONAS' ? 'ZONAS' : 'NEUTRA',
                    'opcoes' => $opcoes,
                ];
            }
            $topicos[] = ['uuid' => $this->uuidOuNulo($t['uuid'] ?? null), 'titulo' => $tt, 'descricao' => $txt($t['descricao'] ?? '', self::LIM['texto']), 'perguntas' => $perguntas];
        }
        if ($nPerg > 300) {
            $erros[] = 'O formulário pode ter no máximo 300 perguntas.';
        }

        return [[
            'titulo' => $titulo,
            'descricao' => $txt($d['descricao'] ?? '', self::LIM['texto']),
            'slug' => $slug,
            'mensagem_apresentacao' => $txt($d['mensagem_apresentacao'] ?? '', self::LIM['texto']),
            'mensagem_conclusao' => $txt($d['mensagem_conclusao'] ?? '', self::LIM['texto']),
            'inicio_em' => $inicio,
            'fim_em' => $fim,
            'recorrencia' => $recorrencia,
            // Rodízio de tópicos só faz sentido em pesquisa recorrente
            'topico_por_ciclo' => $recorrencia !== 'NENHUMA' && !empty($d['topico_por_ciclo']) ? 1 : 0,
            'segmentado' => !empty($d['segmentado']) ? 1 : 0,
            'exige_identificacao' => !empty($d['exige_identificacao']) ? 1 : 0,
            'permite_multiplas' => !empty($d['permite_multiplas']) ? 1 : 0,
            'formato' => $formato,
            'cor' => strtoupper($cor),
            'capa' => $capa,
            'topicos' => $topicos,
        ], array_values(array_unique($erros))];
    }

    private function uuidOuNulo(mixed $v): ?string
    {
        return is_string($v) && preg_match(self::UUID, $v) ? strtolower($v) : null;
    }

    /** Assinatura da estrutura ativa (detecta mudanças que exigem nova versão). */
    private function assinatura(int $id): string
    {
        $e = [];
        foreach (Formulario::estrutura($id) as $t) {
            $e[] = [$t['uuid'], $t['titulo'], array_map(static fn ($p) => [
                $p['uuid'], $p['tipo'], $p['titulo'], (int) $p['obrigatoria'], $p['minimo'], $p['maximo'],
                array_map(static fn ($o) => [$o['uuid'], $o['rotulo']], $p['opcoes']),
            ], $t['perguntas'])];
        }
        return hash('sha256', json_encode($e));
    }

    /** @return string|null erro de regra */
    private function gravarEstrutura(int $formId, array $topicos, int $proximaVersao): ?string
    {
        $exT = [];
        foreach (Database::todos('SELECT id, uuid FROM topicos WHERE formulario_id = ?', [$formId]) as $r) {
            $exT[$r['uuid']] = (int) $r['id'];
        }
        $exP = [];
        foreach (Database::todos('SELECT p.id, p.uuid, p.tipo, EXISTS(SELECT 1 FROM respostas_itens ri WHERE ri.pergunta_id = p.id) AS usada
                                    FROM perguntas p JOIN topicos t ON t.id = p.topico_id WHERE t.formulario_id = ?', [$formId]) as $r) {
            $exP[$r['uuid']] = $r;
        }
        $exO = [];
        foreach (Database::todos('SELECT o.id, o.uuid, o.pergunta_id, EXISTS(SELECT 1 FROM respostas_itens ri WHERE ri.opcao_id = o.id) AS usada
                                    FROM opcoes o JOIN perguntas p ON p.id = o.pergunta_id JOIN topicos t ON t.id = p.topico_id
                                   WHERE t.formulario_id = ?', [$formId]) as $r) {
            $exO[$r['uuid']] = $r;
        }

        $vistosT = $vistosP = $vistosO = [];
        foreach ($topicos as $ti => $t) {
            if ($t['uuid'] && isset($exT[$t['uuid']])) {
                $tid = $exT[$t['uuid']];
                Database::executar('UPDATE topicos SET titulo = ?, descricao = ?, ordem = ?, ativo = 1 WHERE id = ?', [$t['titulo'], $t['descricao'], $ti + 1, $tid]);
            } else {
                $tid = Database::inserir('INSERT INTO topicos (uuid, formulario_id, titulo, descricao, ordem) VALUES (?, ?, ?, ?, ?)', [uuid4(), $formId, $t['titulo'], $t['descricao'], $ti + 1]);
            }
            $vistosT[] = $tid;
            foreach ($t['perguntas'] as $pi => $p) {
                $atual = $p['uuid'] ? ($exP[$p['uuid']] ?? null) : null;
                $campos = [$tid, $p['tipo'], $p['titulo'], $p['orientacao'], $p['obrigatoria'], $pi + 1, $p['minimo'], $p['maximo'], $p['rotulo_minimo'], $p['rotulo_maximo'], $p['aparencia']];
                if ($atual) {
                    if ($atual['tipo'] !== $p['tipo'] && (int) $atual['usada'] === 1) {
                        return 'Não é possível trocar o tipo da pergunta "' . mb_substr($p['titulo'], 0, 60) . '" porque ela já tem respostas. Crie uma nova pergunta.';
                    }
                    $pid = (int) $atual['id'];
                    Database::executar('UPDATE perguntas SET topico_id = ?, tipo = ?, titulo = ?, orientacao = ?, obrigatoria = ?, ordem = ?, minimo = ?, maximo = ?,
                                        rotulo_minimo = ?, rotulo_maximo = ?, aparencia = ?, ativo = 1 WHERE id = ?', [...$campos, $pid]);
                } else {
                    $pid = Database::inserir('INSERT INTO perguntas (topico_id, tipo, titulo, orientacao, obrigatoria, ordem, minimo, maximo, rotulo_minimo, rotulo_maximo, aparencia, uuid, versao_inclusao)
                                              VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)', [...$campos, uuid4(), $proximaVersao]);
                }
                $vistosP[] = $pid;
                foreach ($p['opcoes'] as $oi => $o) {
                    $oa = $o['uuid'] ? ($exO[$o['uuid']] ?? null) : null;
                    if ($oa && (int) $oa['pergunta_id'] === $pid) {
                        Database::executar('UPDATE opcoes SET rotulo = ?, ordem = ?, ativo = 1 WHERE id = ?', [$o['rotulo'], $oi + 1, $oa['id']]);
                        $vistosO[] = (int) $oa['id'];
                    } else {
                        $vistosO[] = Database::inserir('INSERT INTO opcoes (uuid, pergunta_id, rotulo, ordem) VALUES (?, ?, ?, ?)', [uuid4(), $pid, $o['rotulo'], $oi + 1]);
                    }
                }
            }
        }

        // Removidos: inativa o que tem respostas; apaga o resto
        foreach ($exO as $o) {
            if (!in_array((int) $o['id'], $vistosO, true)) {
                (int) $o['usada'] === 1
                    ? Database::executar('UPDATE opcoes SET ativo = 0 WHERE id = ?', [$o['id']])
                    : Database::executar('DELETE FROM opcoes WHERE id = ?', [$o['id']]);
            }
        }
        foreach ($exP as $p) {
            if (!in_array((int) $p['id'], $vistosP, true)) {
                (int) $p['usada'] === 1
                    ? Database::executar('UPDATE perguntas SET ativo = 0 WHERE id = ?', [$p['id']])
                    : Database::executar('DELETE FROM perguntas WHERE id = ?', [$p['id']]);
            }
        }
        foreach ($exT as $tid) {
            if (!in_array($tid, $vistosT, true)) {
                $guardaHistorico = (bool) Database::valor('SELECT 1 FROM perguntas WHERE topico_id = ? LIMIT 1', [$tid]);
                $guardaHistorico
                    ? Database::executar('UPDATE topicos SET ativo = 0 WHERE id = ?', [$tid])
                    : Database::executar('DELETE FROM topicos WHERE id = ?', [$tid]);
            }
        }
        return null;
    }
}

