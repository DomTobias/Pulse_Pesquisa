<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\App;
use App\Core\Database;
use App\Models\Cadastro;
use App\Models\Configuracao;
use App\Models\Formulario;
use App\Services\Indicadores\Adesao;
use App\Services\Indicadores\Anonimato;
use App\Services\Indicadores\Ciclos;
use App\Services\Indicadores\Indice;
use App\Services\Indicadores\Zonas;
use PDO;

/**
 * Relatórios do Pulse (modelo da planilha). Toda conta passa por Services\Indicadores.
 * Carrega as respostas do formulário uma vez e calcula os recortes em memória.
 */
final class RelatorioService
{
    public readonly array $form;
    public readonly array $estrutura;
    public readonly Zonas $zonas;
    public readonly int $limite;
    public readonly array $ciclos;
    public readonly bool $recorrente;
    public readonly bool $segmentado;
    public readonly bool $anonimo;
    /** @var array<int,array> */
    public readonly array $areas;
    /** @var array<int,array> */
    public readonly array $gestores;

    /** @var array<int,array{min:float,max:float,topico_id:int,titulo:string,topico:string,ativo:bool}> */
    private array $escalas = [];
    /** @var array<int,array{titulo:string,topico_id:int,topico:string,tipo:string}> */
    private array $textos = [];
    /** @var array<int,array{id:int,ciclo:?int,area_id:?int,gestor_id:?int,ts:int,status:string}> */
    private array $respostas = [];
    /** @var array<int,array<int,float>> resposta → pergunta → nota */
    private array $notas = [];

    public function __construct(array $form)
    {
        $this->form = $form;
        $this->estrutura = Formulario::estrutura((int) $form['id'], true);
        $this->zonas = new Zonas(Cadastro::faixas());
        $this->limite = max(1, Configuracao::inteiro('grupo_minimo', 3));
        $this->recorrente = Ciclos::recorrente($form['recorrencia']);
        $this->segmentado = (int) $form['segmentado'] === 1;
        $this->anonimo = (int) $form['exige_identificacao'] === 0;
        $inicio = $form['inicio_em'] ? strtotime($form['inicio_em'] . ' UTC') : null;
        $fim = $form['fim_em'] ? strtotime($form['fim_em'] . ' UTC') : null;
        $this->ciclos = $form['estado'] === 'RASCUNHO' && !Formulario::totalRespostas((int) $form['id'])
            ? [] : Ciclos::listar($inicio, $form['recorrencia'], time(), $fim, App::fuso());
        $this->areas = array_column(Cadastro::areas(), null, 'id');
        $this->gestores = array_column(Cadastro::gestores(), null, 'id');

        foreach ($this->estrutura as $t) {
            foreach ($t['perguntas'] as $p) {
                if ($p['tipo'] === 'ESCALA') {
                    $this->escalas[(int) $p['id']] = [
                        'min' => $p['minimo'] === null ? 0.0 : (float) $p['minimo'],
                        'max' => $p['maximo'] === null ? 10.0 : (float) $p['maximo'],
                        'topico_id' => (int) $t['id'], 'titulo' => $p['titulo'], 'topico' => $t['titulo'], 'ativo' => (int) $p['ativo'] === 1,
                    ];
                } elseif (in_array($p['tipo'], ['CURTA', 'LONGA'], true)) {
                    $this->textos[(int) $p['id']] = ['titulo' => $p['titulo'], 'topico_id' => (int) $t['id'], 'topico' => $t['titulo'], 'tipo' => $p['tipo']];
                }
            }
        }
        $this->carregar();
    }

    private function carregar(): void
    {
        $st = Database::executar('SELECT id, ciclo, area_id, gestor_id, UNIX_TIMESTAMP(recebido_em) AS ts, status FROM respostas WHERE formulario_id = ?', [$this->form['id']]);
        while ($r = $st->fetch(PDO::FETCH_ASSOC)) {
            $this->respostas[(int) $r['id']] = [
                'id' => (int) $r['id'], 'ciclo' => $r['ciclo'] === null ? null : (int) $r['ciclo'],
                'area_id' => $r['area_id'] === null ? null : (int) $r['area_id'],
                'gestor_id' => $r['gestor_id'] === null ? null : (int) $r['gestor_id'],
                'ts' => (int) $r['ts'], 'status' => $r['status'],
            ];
        }
        if (!$this->escalas || !$this->respostas) {
            return;
        }
        $ids = array_keys($this->escalas);
        $st = Database::executar('SELECT ri.resposta_id, ri.pergunta_id, ri.valor_numerico FROM respostas_itens ri
                                    JOIN respostas r ON r.id = ri.resposta_id
                                   WHERE r.formulario_id = ? AND ri.pergunta_id IN (' . Database::marcadores($ids) . ') AND ri.valor_numerico IS NOT NULL',
            [$this->form['id'], ...$ids]);
        while ($r = $st->fetch(PDO::FETCH_NUM)) {
            $this->notas[(int) $r[0]][(int) $r[1]] = (float) $r[2];
        }
    }

    // ------------------------------------------------------------------ filtros

    public function normalizarFiltros(array $q): array
    {
        $topicos = array_column($this->estrutura, 'id');
        $periodo = (string) ($q['periodo'] ?? ($this->recorrente && $this->ciclos ? 'atual' : 'todos'));
        $validos = ['todos', 'custom'];
        if ($this->recorrente && $this->ciclos) {
            array_push($validos, 'atual', 'anterior', 'ultimos3');
        }
        if (!in_array($periodo, $validos, true) && !preg_match('/^c:\d+$/', $periodo)) {
            $periodo = $this->recorrente && $this->ciclos ? 'atual' : 'todos';
        }
        $data = static fn ($v) => is_string($v) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) ? $v : '';
        $area = (int) ($q['area'] ?? 0);
        $gestor = (int) ($q['gestor'] ?? 0);
        $topico = (int) ($q['topico'] ?? 0);
        return [
            'periodo' => $periodo,
            'de' => $data($q['de'] ?? '') ?: (new \DateTimeImmutable('-29 days', App::fuso()))->format('Y-m-d'),
            'ate' => $data($q['ate'] ?? '') ?: (new \DateTimeImmutable('now', App::fuso()))->format('Y-m-d'),
            'area' => $this->segmentado && isset($this->areas[$area]) ? $area : 0,
            'gestor' => $this->segmentado && isset($this->gestores[$gestor]) ? $gestor : 0,
            'topico' => in_array($topico, $topicos, false) ? $topico : 0,
            'aba' => in_array($q['aba'] ?? '', ['painel', 'abertas', 'base'], true) ? $q['aba'] : 'painel',
            'pagina' => max(1, (int) ($q['pagina'] ?? 1)),
            'busca' => mb_substr(trim((string) ($q['busca'] ?? '')), 0, 100),
        ];
    }

    /** @return array{0:callable(array):bool,1:string,2:int} filtro de período, rótulo e nº de ciclos */
    private function periodo(array $f): array
    {
        $c = $this->ciclos;
        $fmt = static fn ($x) => date('d/m', strtotime($x['inicio'])) . ' a ' . date('d/m', strtotime($x['fim']));
        $porCiclos = static function (array $lista) {
            $nums = array_column($lista, 'numero');
            return static fn (array $r) => $r['ciclo'] !== null && in_array($r['ciclo'], $nums, true);
        };
        switch (true) {
            case $f['periodo'] === 'atual' && $c:
                $x = end($c);
                return [$porCiclos([$x]), $x['numero'] . 'º ciclo (' . $fmt($x) . ')', 1];
            case $f['periodo'] === 'anterior' && count($c) > 1:
                $x = $c[count($c) - 2];
                return [$porCiclos([$x]), $x['numero'] . 'º ciclo (' . $fmt($x) . ')', 1];
            case $f['periodo'] === 'ultimos3' && $c:
                $l = array_slice($c, -3);
                return [$porCiclos($l), 'Últimos ' . count($l) . ' ciclos', count($l)];
            case str_starts_with($f['periodo'], 'c:'):
                $n = (int) substr($f['periodo'], 2);
                foreach ($c as $x) {
                    if ($x['numero'] === $n) {
                        return [$porCiclos([$x]), $x['numero'] . 'º ciclo (' . $fmt($x) . ')', 1];
                    }
                }
                break;
            case $f['periodo'] === 'custom':
                $de = (new \DateTimeImmutable($f['de'] . ' 00:00:00', App::fuso()))->getTimestamp();
                $ate = (new \DateTimeImmutable($f['ate'] . ' 23:59:59', App::fuso()))->getTimestamp();
                if ($de > $ate) {
                    [$de, $ate] = [$ate, $de];
                }
                return [static fn (array $r) => $r['ts'] >= $de && $r['ts'] <= $ate,
                    date('d/m/Y', strtotime($f['de'])) . ' a ' . date('d/m/Y', strtotime($f['ate'])),
                    $this->recorrente ? Ciclos::contarNoIntervalo($c, $de, $ate) : 0];
        }
        return [static fn () => true, $this->recorrente ? 'Todos os ciclos' : 'Todo o período', count($c)];
    }

    private function filtrar(callable $periodo, int $area, int $gestor): array
    {
        return array_filter($this->respostas, static fn ($r) => $periodo($r)
            && (!$area || $r['area_id'] === $area) && (!$gestor || $r['gestor_id'] === $gestor));
    }

    /** Índice (0–10) de um conjunto de respostas e perguntas de escala. */
    public function indice(array $respostas, array $perguntas): array
    {
        $lista = [];
        foreach ($respostas as $rid => $_) {
            foreach ($this->notas[$rid] ?? [] as $pid => $nota) {
                if (isset($perguntas[$pid])) {
                    $lista[] = [$nota, $perguntas[$pid]['min'], $perguntas[$pid]['max']];
                }
            }
        }
        return ['valor' => Indice::media($lista), 'notas' => count($lista)];
    }

    private function escalasDoTopico(int $topico): array
    {
        return $topico ? array_filter($this->escalas, static fn ($q) => $q['topico_id'] === $topico) : $this->escalas;
    }

    private function colaboradores(int $area): int
    {
        if ($area) {
            return (int) ($this->areas[$area]['colaboradores'] ?? 0);
        }
        return array_sum(array_map(static fn ($a) => (int) $a['ativo'] === 1 ? (int) $a['colaboradores'] : 0, $this->areas));
    }

    /** Contagem de respostas por área e por gestor no período (para mascarar grupos pequenos). */
    private function contagemGrupos(callable $periodo): array
    {
        $a = $g = [];
        foreach ($this->respostas as $r) {
            if ($periodo($r)) {
                if ($r['area_id']) {
                    $a[$r['area_id']] = ($a[$r['area_id']] ?? 0) + 1;
                }
                if ($r['gestor_id']) {
                    $g[$r['gestor_id']] = ($g[$r['gestor_id']] ?? 0) + 1;
                }
            }
        }
        return [$a, $g];
    }

    // ------------------------------------------------------------------ relatório

    public function gerar(array $f): array
    {
        [$periodo, $rotuloPeriodo, $nCiclos] = $this->periodo($f);
        $resps = $this->filtrar($periodo, $f['area'], $f['gestor']);
        $total = count($resps);
        $suprimido = ($f['area'] || $f['gestor']) && Anonimato::suprimir($total, $this->limite);
        $qs = $this->escalasDoTopico($f['topico']);
        $geral = $suprimido ? ['valor' => null, 'notas' => 0] : $this->indice($resps, $qs);

        // Por área (ignora o filtro de área, mantém gestor)
        $porArea = [];
        if ($this->segmentado) {
            foreach ($this->areas as $id => $a) {
                if ((int) $a['ativo'] !== 1 && !array_filter($this->respostas, static fn ($r) => $r['area_id'] === $id)) {
                    continue;
                }
                $rs = $this->filtrar($periodo, $id, $f['gestor']);
                $sup = Anonimato::suprimir(count($rs), $this->limite);
                $porArea[] = ['chave' => $id, 'rotulo' => $a['nome'], 'n' => count($rs), 'suprimido' => $sup,
                    'valor' => $sup ? null : $this->indice($rs, $qs)['valor']];
            }
            usort($porArea, static fn ($x, $y) => ($x['valor'] ?? 99) <=> ($y['valor'] ?? 99));
        }

        // Por gestor (ignora o filtro de gestor, mantém área)
        $porGestor = [];
        if ($this->segmentado) {
            foreach ($this->gestores as $id => $g) {
                $rs = $this->filtrar($periodo, $f['area'], $id);
                if (!$rs && ((int) $g['ativo'] !== 1 || ($f['area'] && $g['area_id'] && (int) $g['area_id'] !== $f['area']))) {
                    continue;
                }
                $sup = Anonimato::suprimir(count($rs), $this->limite);
                $porGestor[] = ['chave' => $id, 'rotulo' => $g['nome'], 'n' => count($rs), 'suprimido' => $sup,
                    'valor' => $sup ? null : $this->indice($rs, $qs)['valor']];
            }
            usort($porGestor, static fn ($x, $y) => ($x['valor'] ?? 99) <=> ($y['valor'] ?? 99));
        }

        // Por tópico e por pergunta
        $porTopico = $porPergunta = [];
        foreach ($this->estrutura as $t) {
            if ($f['topico'] && (int) $t['id'] !== $f['topico']) {
                continue;
            }
            $tq = array_filter($this->escalas, static fn ($q) => $q['topico_id'] === (int) $t['id']);
            if (!$tq) {
                continue;
            }
            $porTopico[] = ['chave' => (int) $t['id'], 'rotulo' => $t['titulo'], 'n' => $total, 'suprimido' => $suprimido,
                'valor' => $suprimido ? null : $this->indice($resps, $tq)['valor']];
            foreach ($tq as $pid => $q) {
                $ind = $suprimido ? ['valor' => null, 'notas' => 0] : $this->indice($resps, [$pid => $q]);
                $porPergunta[] = ['chave' => $pid, 'rotulo' => $q['titulo'] . ($q['ativo'] ? '' : ' (removida)'), 'grupo' => $q['topico'],
                    'n' => $ind['notas'], 'suprimido' => $suprimido, 'valor' => $ind['valor']];
            }
        }

        $comValor = array_filter($porTopico, static fn ($t) => $t['valor'] !== null);
        usort($comValor, static fn ($a, $b) => $a['valor'] <=> $b['valor']);
        $menorTopico = $comValor[0] ?? null;
        $criticas = count(array_filter($porPergunta, fn ($p) => $p['valor'] !== null && $this->zonas->indiceDe($p['valor']) === 0));

        // Distribuição das notas por zona
        $dist = array_fill(0, count($this->zonas->faixas()), 0);
        if (!$suprimido) {
            foreach ($resps as $rid => $_) {
                foreach ($this->notas[$rid] ?? [] as $pid => $nota) {
                    if (isset($qs[$pid])) {
                        $dist[$this->zonas->indiceDe(Indice::normalizar($nota, $qs[$pid]['min'], $qs[$pid]['max']))]++;
                    }
                }
            }
        }

        // Adesão: respostas ÷ (colaboradores × ciclos), nunca com filtro de gestor
        $colab = $this->colaboradores($f['area']);
        $adesao = $this->recorrente ? Adesao::calcular($total, $colab, $nCiclos, (bool) $f['gestor']) : null;

        // Evolução por ciclo (respeita área, gestor e tópico; ignora o período)
        $evolucao = [];
        foreach ($this->ciclos as $c) {
            $rs = $this->filtrar(static fn ($r) => $r['ciclo'] === $c['numero'], $f['area'], $f['gestor']);
            $sup = ($f['area'] || $f['gestor']) && Anonimato::suprimir(count($rs), $this->limite);
            $v = $sup ? null : $this->indice($rs, $qs)['valor'];
            $evolucao[] = ['rotulo' => $c['numero'] . 'º', 'completo' => $c['numero'] . 'º ciclo, ' . date('d/m', strtotime($c['inicio'])) . ' a ' . date('d/m', strtotime($c['fim'])) . ($c['em_andamento'] ? ' (em andamento)' : ''),
                'valor' => Indice::percentual($v), 'n' => count($rs),
                'adesao' => Adesao::calcular(count($rs), $colab, 1, (bool) $f['gestor'])];
        }

        return [
            'filtros' => $f,
            'periodo_rotulo' => $rotuloPeriodo,
            'ciclos_no_periodo' => $nCiclos,
            'total' => $total,
            'suprimido' => $suprimido,
            'geral' => $geral['valor'],
            'adesao' => $adesao,
            'colaboradores' => $colab,
            'menor_topico' => $menorTopico,
            'criticas' => $criticas,
            'perguntas_escala' => count($porPergunta),
            'por_area' => $porArea,
            'por_gestor' => $porGestor,
            'por_topico' => $porTopico,
            'por_pergunta' => $porPergunta,
            'distribuicao' => $dist,
            'evolucao' => $evolucao,
            'abertas' => $f['aba'] === 'abertas' || $f['aba'] === 'export' ? $this->abertas($resps, $f, $periodo, $suprimido, $f['aba'] === 'export') : null,
            'base' => $f['aba'] === 'base' ? $this->base($resps, $f, $periodo, $suprimido) : null,
            'total_abertas' => $suprimido ? 0 : $this->contarAbertas($resps, $f['topico']),
        ];
    }

    private function textosDoTopico(int $topico): array
    {
        return $topico ? array_filter($this->textos, static fn ($q) => $q['topico_id'] === $topico) : $this->textos;
    }

    private function contarAbertas(array $resps, int $topico): int
    {
        $qs = $this->textosDoTopico($topico);
        if (!$qs || !$resps) {
            return 0;
        }
        $ids = array_keys($qs);
        $st = Database::executar('SELECT ri.resposta_id FROM respostas_itens ri JOIN respostas r ON r.id = ri.resposta_id
                                   WHERE r.formulario_id = ? AND ri.pergunta_id IN (' . Database::marcadores($ids) . ')
                                     AND ri.valor_texto IS NOT NULL AND TRIM(ri.valor_texto) <> \'\'', [$this->form['id'], ...$ids]);
        $n = 0;
        while (($rid = $st->fetchColumn()) !== false) {
            $n += isset($resps[(int) $rid]) ? 1 : 0;
        }
        return $n;
    }

    /** Nome do grupo, ou aviso quando o grupo é pequeno demais para preservar o anonimato. */
    private function rotuloGrupo(?int $id, array $cadastro, array $contagem): ?string
    {
        if (!$id) {
            return null;
        }
        if ($this->anonimo && ($contagem[$id] ?? 0) < $this->limite) {
            return 'Grupo pequeno';
        }
        return $cadastro[$id]['nome'] ?? '—';
    }

    public function abertas(array $resps, array $f, callable $periodo, bool $suprimido, bool $tudo = false): array
    {
        if ($suprimido) {
            return ['itens' => [], 'total' => 0, 'paginas' => 1];
        }
        $qs = $this->textosDoTopico($f['topico']);
        if (!$qs || !$resps) {
            return ['itens' => [], 'total' => 0, 'paginas' => 1];
        }
        [$ca, $cg] = $this->contagemGrupos($periodo);
        $ids = array_keys($qs);
        $linhas = Database::todos('SELECT ri.resposta_id, ri.pergunta_id, ri.valor_texto FROM respostas_itens ri JOIN respostas r ON r.id = ri.resposta_id
                                    WHERE r.formulario_id = ? AND ri.pergunta_id IN (' . Database::marcadores($ids) . ')
                                      AND ri.valor_texto IS NOT NULL AND TRIM(ri.valor_texto) <> \'\'
                                    ORDER BY r.recebido_em DESC, ri.id', [$this->form['id'], ...$ids]);
        $itens = [];
        $busca = $f['busca'] !== '' ? mb_strtolower($f['busca']) : '';
        foreach ($linhas as $l) {
            $r = $resps[(int) $l['resposta_id']] ?? null;
            if (!$r || ($busca !== '' && !str_contains(mb_strtolower($l['valor_texto']), $busca))) {
                continue;
            }
            $q = $qs[(int) $l['pergunta_id']];
            $itens[] = ['texto' => $l['valor_texto'], 'pergunta' => $q['titulo'], 'topico' => $q['topico'], 'ciclo' => $r['ciclo'], 'ts' => $r['ts'],
                'area' => $this->rotuloGrupo($r['area_id'], $this->areas, $ca), 'gestor' => $this->rotuloGrupo($r['gestor_id'], $this->gestores, $cg)];
        }
        $porPagina = 12;
        $total = count($itens);
        return ['itens' => $tudo ? $itens : array_slice($itens, ($f['pagina'] - 1) * $porPagina, $porPagina), 'total' => $total,
            'paginas' => max(1, (int) ceil($total / $porPagina))];
    }

    public function base(array $resps, array $f, callable $periodo, bool $suprimido, bool $tudo = false): array
    {
        if ($suprimido) {
            return ['itens' => [], 'total' => 0, 'paginas' => 1];
        }
        [$ca, $cg] = $this->contagemGrupos($periodo);
        usort($resps, static fn ($a, $b) => $b['ts'] <=> $a['ts']);
        $porPagina = 10;
        $total = count($resps);
        $fatia = $tudo ? $resps : array_slice($resps, ($f['pagina'] - 1) * $porPagina, $porPagina);
        $itens = [];
        foreach ($fatia as $r) {
            $ind = $this->indice([$r['id'] => true], $this->escalas)['valor'];
            $itens[] = $r + ['indice' => $ind, 'zona' => $this->zonas->zonaDe($ind),
                'area' => $this->rotuloGrupo($r['area_id'], $this->areas, $ca), 'gestor' => $this->rotuloGrupo($r['gestor_id'], $this->gestores, $cg)];
        }
        return ['itens' => $itens, 'total' => $total, 'paginas' => max(1, (int) ceil($total / $porPagina))];
    }

    /** Linhas completas para exportação (CSV/Excel), respeitando filtros e anonimato. */
    public function linhasExportacao(array $f): array
    {
        [$periodo] = $this->periodo($f);
        $resps = $this->filtrar($periodo, $f['area'], $f['gestor']);
        $suprimido = ($f['area'] || $f['gestor']) && Anonimato::suprimir(count($resps), $this->limite);
        if ($suprimido || !$resps) {
            return ['cabecalho' => [], 'linhas' => [], 'suprimido' => $suprimido];
        }
        [$ca, $cg] = $this->contagemGrupos($periodo);
        $perguntas = [];
        foreach ($this->estrutura as $t) {
            foreach ($t['perguntas'] as $p) {
                if ($p['tipo'] !== 'INFO') {
                    $perguntas[(int) $p['id']] = $p;
                }
            }
        }
        $ids = array_keys($resps);
        $valores = [];
        $opcoes = [];
        foreach ($this->estrutura as $t) {
            foreach ($t['perguntas'] as $p) {
                foreach ($p['opcoes'] as $o) {
                    $opcoes[(int) $o['id']] = $o['rotulo'];
                }
            }
        }
        foreach (array_chunk($ids, 500) as $lote) {
            $st = Database::executar('SELECT resposta_id, pergunta_id, valor_numerico, valor_texto, opcao_id FROM respostas_itens WHERE resposta_id IN ('
                . Database::marcadores($lote) . ')', $lote);
            while ($l = $st->fetch(PDO::FETCH_ASSOC)) {
                $v = $l['opcao_id'] !== null ? ($opcoes[(int) $l['opcao_id']] ?? '') : ($l['valor_numerico'] !== null ? (float) $l['valor_numerico'] : $l['valor_texto']);
                if ($v === 'SIM' || $v === 'NAO') {
                    $v = $v === 'SIM' ? 'Sim' : 'Não';
                }
                $atual = $valores[(int) $l['resposta_id']][(int) $l['pergunta_id']] ?? null;
                $valores[(int) $l['resposta_id']][(int) $l['pergunta_id']] = $atual === null ? $v : $atual . '; ' . $v;
            }
        }
        $cab = ['Envio', 'Ciclo'];
        if ($this->segmentado) {
            array_push($cab, 'Área', 'Gestor');
        }
        $cab[] = 'Índice (%)';
        foreach ($perguntas as $p) {
            $cab[] = $p['titulo'] . ((int) $p['ativo'] === 1 ? '' : ' (removida)');
        }
        usort($resps, static fn ($a, $b) => $a['ts'] <=> $b['ts']);
        $linhas = [];
        foreach ($resps as $r) {
            $l = [(new \DateTimeImmutable('@' . $r['ts']))->setTimezone(App::fuso())->format('d/m/Y H:i'), $r['ciclo'] ?? ''];
            if ($this->segmentado) {
                array_push($l, $this->rotuloGrupo($r['area_id'], $this->areas, $ca) ?? '', $this->rotuloGrupo($r['gestor_id'], $this->gestores, $cg) ?? '');
            }
            $l[] = Indice::percentual($this->indice([$r['id'] => true], $this->escalas)['valor']) ?? '';
            foreach ($perguntas as $pid => $_) {
                $l[] = $valores[$r['id']][$pid] ?? '';
            }
            $linhas[] = $l;
        }
        return ['cabecalho' => $cab, 'linhas' => $linhas, 'suprimido' => false];
    }

    /** Resumo do ciclo atual × anterior para a Visão geral. */
    public function resumoCiclos(): ?array
    {
        if (!$this->recorrente || !$this->ciclos) {
            return null;
        }
        $atual = $this->ciclos[array_key_last($this->ciclos)];
        $anterior = count($this->ciclos) > 1 ? $this->ciclos[count($this->ciclos) - 2] : null;
        $rsA = array_filter($this->respostas, static fn ($r) => $r['ciclo'] === $atual['numero']);
        $rsP = $anterior ? array_filter($this->respostas, static fn ($r) => $r['ciclo'] === $anterior['numero']) : [];
        $iA = $this->indice($rsA, $this->escalas)['valor'];
        $iP = $anterior ? $this->indice($rsP, $this->escalas)['valor'] : null;
        $topicos = [];
        foreach ($this->estrutura as $t) {
            $tq = array_filter($this->escalas, static fn ($q) => $q['topico_id'] === (int) $t['id'] && $q['ativo']);
            if ($tq) {
                $a = $this->indice($rsA, $tq)['valor'];
                $p = $anterior ? $this->indice($rsP, $tq)['valor'] : null;
                if ($p === null && (int) ($this->form['topico_por_ciclo'] ?? 0) === 1) {
                    // Rodízio: compara com a última rodada em que o tópico foi respondido
                    for ($n = $atual['numero'] - 1; $n >= 1 && $p === null; $n--) {
                        $p = $this->indice(array_filter($this->respostas, static fn ($r) => $r['ciclo'] === $n), $tq)['valor'];
                    }
                }
                $topicos[] = ['rotulo' => $t['titulo'], 'atual' => $a, 'anterior' => $p, 'variacao' => Indice::variacao($a, $p), 'zona' => $this->zonas->zonaDe($a)];
            }
        }
        $areas = [];
        if ($this->segmentado) {
            foreach ($this->areas as $id => $a) {
                if ((int) $a['ativo'] !== 1) {
                    continue;
                }
                $rs = array_filter($rsA, static fn ($r) => $r['area_id'] === $id);
                $sup = Anonimato::suprimir(count($rs), $this->limite);
                $areas[] = ['chave' => $id, 'rotulo' => $a['nome'], 'n' => count($rs), 'suprimido' => $sup, 'valor' => $sup ? null : $this->indice($rs, $this->escalas)['valor']];
            }
            usort($areas, static fn ($x, $y) => ($x['valor'] ?? 99) <=> ($y['valor'] ?? 99));
        }
        $colab = $this->colaboradores(0);
        $porCiclo = [];
        foreach ($this->ciclos as $c) {
            $rs = array_filter($this->respostas, static fn ($r) => $r['ciclo'] === $c['numero']);
            $porCiclo[] = ['rotulo' => date('d/m', strtotime($c['inicio'])) . ' a ' . date('d/m', strtotime($c['fim'])),
                'completo' => $c['numero'] . 'º ciclo' . ($c['em_andamento'] ? ' (em andamento)' : ''),
                'adesao' => Adesao::calcular(count($rs), $colab, 1), 'resultado' => Indice::percentual($this->indice($rs, $this->escalas)['valor'])];
        }
        $agora = time();
        return [
            'atual' => $atual, 'anterior' => $anterior,
            'indice_atual' => $iA, 'indice_anterior' => $iP, 'variacao' => Indice::variacao($iA, $iP),
            'respostas_atual' => count($rsA), 'adesao_atual' => Adesao::calcular(count($rsA), $colab, 1),
            'decorrido' => (int) min(100, max(0, round(($agora - $atual['inicio_ts']) / max(1, $atual['fim_ts'] - $atual['inicio_ts']) * 100))),
            'topicos' => $topicos, 'areas' => $areas, 'por_ciclo' => $porCiclo, 'colaboradores' => $colab,
        ];
    }

    /** Detalhe de uma resposta (respeitando o anonimato). */
    public function detalhe(int $respostaId): ?array
    {
        $r = $this->respostas[$respostaId] ?? null;
        if (!$r) {
            return null;
        }
        $itens = Database::todos('SELECT pergunta_id, valor_numerico, valor_texto, opcao_id FROM respostas_itens WHERE resposta_id = ?', [$respostaId]);
        $valores = [];
        $opcoes = [];
        foreach ($this->estrutura as $t) {
            foreach ($t['perguntas'] as $p) {
                foreach ($p['opcoes'] as $o) {
                    $opcoes[(int) $o['id']] = $o['rotulo'];
                }
            }
        }
        foreach ($itens as $i) {
            $v = $i['opcao_id'] !== null ? ($opcoes[(int) $i['opcao_id']] ?? '') : ($i['valor_numerico'] !== null ? rtrim(rtrim(number_format((float) $i['valor_numerico'], 2, ',', ''), '0'), ',') : (string) $i['valor_texto']);
            $v = $v === 'SIM' ? 'Sim' : ($v === 'NAO' ? 'Não' : $v);
            $valores[(int) $i['pergunta_id']][] = $v;
        }
        $mesmoCiclo = static fn ($x) => true;
        [$ca, $cg] = $this->contagemGrupos($mesmoCiclo);
        $ident = null;
        if (!$this->anonimo) {
            $ident = json_decode((string) Database::valor('SELECT identificacao FROM respostas WHERE id = ?', [$respostaId]), true);
        }
        $ind = $this->indice([$respostaId => true], $this->escalas)['valor'];
        return $r + ['valores' => $valores, 'indice' => $ind, 'zona' => $this->zonas->zonaDe($ind), 'identificacao' => $ident,
            'area' => $this->rotuloGrupo($r['area_id'], $this->areas, $ca), 'gestor' => $this->rotuloGrupo($r['gestor_id'], $this->gestores, $cg)];
    }

    public function normalizada(int $perguntaId, float $nota): ?float
    {
        $q = $this->escalas[$perguntaId] ?? null;
        return $q ? Indice::normalizar($nota, $q['min'], $q['max']) : null;
    }
}
