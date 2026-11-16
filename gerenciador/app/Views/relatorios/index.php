<?php
use App\Core\View;
use App\Services\Indicadores\Anonimato;
use App\Services\Indicadores\Indice;

/** @var \App\Services\RelatorioService $serv */
$scripts = ['vendor/chartjs/chart.umd.js', 'js/graficos.js'];
$f = $filtros;
$zonas = $serv->zonas;
$seg = $serv->segmentado;
$base = ['formulario' => $form['id'], 'periodo' => $f['periodo'], 'area' => $f['area'] ?: null, 'gestor' => $f['gestor'] ?: null,
    'topico' => $f['topico'] ?: null, 'aba' => $f['aba']] + ($f['periodo'] === 'custom' ? ['de' => $f['de'], 'ate' => $f['ate']] : []);
$link = static fn (array $mudar) => url('relatorios') . '?' . http_build_query(array_filter($mudar + $base, static fn ($v) => $v !== null && $v !== '' && $v !== 0));
$alternar = static fn (string $campo) => static fn ($chave, $ativo) => $link([$campo => $ativo ? null : $chave, 'pagina' => null]);
$exportar = static fn (string $fmt) => url('relatorios/exportar/' . $fmt) . '?' . http_build_query(array_filter($base, static fn ($v) => $v !== null));
$chips = [];
if ($f['area']) $chips[] = [$serv->areas[$f['area']]['nome'], $link(['area' => null])];
if ($f['gestor']) $chips[] = ['Gestor: ' . $serv->gestores[$f['gestor']]['nome'], $link(['gestor' => null])];
if ($f['topico']) foreach ($serv->estrutura as $t) if ((int) $t['id'] === $f['topico']) $chips[] = ['Tópico: ' . $t['titulo'], $link(['topico' => null])];
$ultimoCiclo = $serv->ciclos ? $serv->ciclos[array_key_last($serv->ciclos)] : null;
$graficos = [
    'evolucao' => ['rotulos' => array_column($rel['evolucao'], 'rotulo'), 'completos' => array_column($rel['evolucao'], 'completo'),
        'valores' => array_column($rel['evolucao'], 'valor'), 'nome' => 'Índice'],
    'rosca' => ['rotulos' => array_column($zonas->faixas(), 'nome'), 'cores' => array_column($zonas->faixas(), 'cor'), 'valores' => $rel['distribuicao']],
];
?>
<div class="page-head">
  <div><h1>Relatórios</h1><p>Índices de 0 a 10 convertidos em porcentagem e classificados pelas zonas de pontuação.</p></div>
  <div class="page-actions">
    <a class="btn btn-light" href="<?= e($exportar('pdf')) ?>" <?= $pdf ? '' : 'target="_blank" rel="noopener" title="Abre a versão para impressão; use Salvar como PDF"' ?>><?= icone('file-earmark-pdf') ?> PDF</a>
    <a class="btn btn-light" href="<?= e($exportar('excel')) ?>"<?= $excel ? '' : ' title="Requer PhpSpreadsheet (composer install)"' ?>><?= icone('file-earmark-spreadsheet') ?> Excel</a>
    <a class="btn btn-light" href="<?= e($exportar('csv')) ?>"><?= icone('filetype-csv') ?> CSV</a>
    <a class="btn btn-light" href="<?= e($exportar('imprimir')) ?>" target="_blank" rel="noopener"><?= icone('printer') ?> Imprimir</a>
  </div>
</div>

<form class="cartao p-3 mb-3" method="get" action="<?= url('relatorios') ?>">
  <input type="hidden" name="aba" value="<?= e($f['aba']) ?>">
  <div class="row g-3 align-items-end">
    <div class="col-sm-6 col-xl-3"><label class="form-label small" for="fr">Pesquisa</label>
      <select class="form-select" id="fr" name="formulario" data-auto-enviar>
        <?php foreach ($forms as $x): ?><option value="<?= (int) $x['id'] ?>"<?= (int) $x['id'] === (int) $form['id'] ? ' selected' : '' ?>><?= e($x['titulo']) ?></option><?php endforeach; ?>
      </select></div>
    <div class="col-sm-6 col-xl-2"><label class="form-label small" for="fa">Área</label>
      <select class="form-select" id="fa" name="area"<?= $seg ? '' : ' disabled' ?>><option value=""><?= $seg ? 'Todas as áreas' : 'Sem segmentação' ?></option>
        <?php foreach ($serv->areas as $a): if ((int) $a['ativo'] !== 1 && (int) $a['id'] !== $f['area']) continue; ?><option value="<?= (int) $a['id'] ?>"<?= (int) $a['id'] === $f['area'] ? ' selected' : '' ?>><?= e($a['nome']) ?></option><?php endforeach; ?>
      </select></div>
    <div class="col-sm-6 col-xl-2"><label class="form-label small" for="ft">Tópico</label>
      <select class="form-select" id="ft" name="topico"><option value="">Todos os tópicos</option>
        <?php foreach ($serv->estrutura as $t): ?><option value="<?= (int) $t['id'] ?>"<?= (int) $t['id'] === $f['topico'] ? ' selected' : '' ?>><?= e($t['titulo']) ?><?= (int) $t['ativo'] === 1 ? '' : ' (removido)' ?></option><?php endforeach; ?>
      </select></div>
    <div class="col-sm-6 col-xl-2"><label class="form-label small" for="fg">Gestor</label>
      <select class="form-select" id="fg" name="gestor"<?= $seg ? '' : ' disabled' ?>><option value=""><?= $seg ? 'Todos os gestores' : 'Sem segmentação' ?></option>
        <?php foreach ($serv->gestores as $g): if ((int) $g['ativo'] !== 1 && (int) $g['id'] !== $f['gestor']) continue; ?><option value="<?= (int) $g['id'] ?>"<?= (int) $g['id'] === $f['gestor'] ? ' selected' : '' ?>><?= e($g['nome']) ?></option><?php endforeach; ?>
      </select></div>
    <div class="col-sm-8 col-xl-2"><label class="form-label small" for="fp">Período</label>
      <select class="form-select" id="fp" name="periodo">
        <?php if ($serv->recorrente && $serv->ciclos): ?>
          <option value="atual"<?= $f['periodo'] === 'atual' ? ' selected' : '' ?>>Ciclo atual (<?= $ultimoCiclo['numero'] ?>º)</option>
          <?php if (count($serv->ciclos) > 1): ?><option value="anterior"<?= $f['periodo'] === 'anterior' ? ' selected' : '' ?>>Ciclo anterior</option><?php endif; ?>
          <option value="ultimos3"<?= $f['periodo'] === 'ultimos3' ? ' selected' : '' ?>>Últimos 3 ciclos</option>
        <?php endif; ?>
        <option value="todos"<?= $f['periodo'] === 'todos' ? ' selected' : '' ?>><?= $serv->recorrente ? 'Todos os ciclos' : 'Todo o período' ?></option>
        <?php if ($serv->recorrente && $serv->ciclos): ?><optgroup label="Ciclo específico">
          <?php foreach (array_reverse($serv->ciclos) as $c): ?><option value="c:<?= $c['numero'] ?>"<?= $f['periodo'] === 'c:' . $c['numero'] ? ' selected' : '' ?>><?= $c['numero'] ?>º ciclo: <?= date('d/m', strtotime($c['inicio'])) ?> a <?= date('d/m', strtotime($c['fim'])) ?></option><?php endforeach; ?>
        </optgroup><?php endif; ?>
        <option value="custom"<?= $f['periodo'] === 'custom' ? ' selected' : '' ?>>Datas personalizadas</option>
      </select></div>
    <div class="col-sm-4 col-xl-1 d-flex gap-1"><button class="btn btn-primary flex-grow-1" title="Aplicar filtros"><?= icone('funnel') ?></button>
      <a class="btn btn-light" href="<?= url('relatorios?formulario=' . $form['id']) ?>" title="Limpar filtros"><?= icone('arrow-counterclockwise') ?></a></div>
  </div>
  <div class="row g-3 mt-0<?= $f['periodo'] === 'custom' ? '' : ' d-none' ?>" data-datas>
    <div class="col-6 col-sm-3"><label class="form-label small" for="de">De</label><input type="date" class="form-control" id="de" name="de" value="<?= e($f['de']) ?>"></div>
    <div class="col-6 col-sm-3"><label class="form-label small" for="ate">Até</label><input type="date" class="form-control" id="ate" name="ate" value="<?= e($f['ate']) ?>"></div>
  </div>
  <?php if ($chips): ?><div class="d-flex flex-wrap gap-1 mt-3"><?php foreach ($chips as [$r, $h]): ?><a class="chip" href="<?= e($h) ?>"><?= e($r) ?> <?= icone('x') ?></a><?php endforeach; ?></div><?php endif; ?>
</form>

<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-3">
  <div class="segmentado">
    <a href="<?= e($link(['aba' => 'painel', 'pagina' => null])) ?>" class="<?= $f['aba'] === 'painel' ? 'active' : '' ?>"><?= icone('bar-chart') ?> Painel</a>
    <a href="<?= e($link(['aba' => 'abertas', 'pagina' => null])) ?>" class="<?= $f['aba'] === 'abertas' ? 'active' : '' ?>"><?= icone('chat-left-text') ?> Perguntas abertas (<?= fmt_num($rel['total_abertas']) ?>)</a>
    <a href="<?= e($link(['aba' => 'base', 'pagina' => null])) ?>" class="<?= $f['aba'] === 'base' ? 'active' : '' ?>"><?= icone('table') ?> Base de respostas</a>
  </div>
  <span class="xsmall text-muted-2"><?= e($rel['periodo_rotulo']) ?>, <?= fmt_num($rel['total']) ?> respostas</span>
</div>

<?php if ($rel['suprimido']): ?>
<div class="alert alert-warning d-flex gap-2 align-items-center"><?= icone('shield-lock') ?> <div><b><?= e(Anonimato::MENSAGEM) ?></b><br><span class="small">Este recorte tem menos de <?= $serv->limite ?> respostas. Amplie o período ou remova filtros de área e gestor.</span></div></div>
<?php endif; ?>

<?php if ($f['aba'] === 'painel'): ?>
<div class="kpis">
  <div class="cartao kpi"><div class="kpi-top"><span>Índice geral</span></div>
    <div class="kpi-valor" style="font-size:34px"><?= fmt_pct($rel['geral']) ?></div>
    <div class="kpi-sub"><?php $z = $zonas->zonaDe($rel['geral']); echo $z ? '<span class="zona-badge" style="color:' . e($z['cor']) . ';background:color-mix(in srgb,' . e($z['cor']) . ' 13%,transparent)">' . e($z['nome']) . '</span>' : 'Média de todas as notas do recorte'; ?></div></div>
  <?php
  View::parcial('kpi', ['rotulo' => 'Respostas', 'valor' => fmt_num($rel['total']), 'icone' => 'inbox', 'sub' => $rel['periodo_rotulo']]);
  View::parcial('kpi', ['rotulo' => 'Adesão', 'valor' => $rel['adesao'] === null ? '—' : $rel['adesao'] . '%', 'icone' => 'people',
      'sub' => $rel['adesao'] !== null ? fmt_num($rel['total']) . ' de ' . fmt_num($rel['colaboradores'] * max(1, $rel['ciclos_no_periodo'])) . ' possíveis'
          : (!$serv->recorrente ? 'Para pesquisas recorrentes' : ($f['gestor'] ? 'Indisponível com filtro de gestor' : 'Cadastre o nº de colaboradores'))]);
  View::parcial('kpi', ['rotulo' => 'Tópico com menor índice', 'valor' => $rel['menor_topico'] ? fmt_pct($rel['menor_topico']['valor']) : '—', 'icone' => 'graph-down', 'tom' => 'warn', 'sub' => $rel['menor_topico']['rotulo'] ?? 'Sem dados']);
  View::parcial('kpi', ['rotulo' => 'Perguntas na zona crítica', 'valor' => fmt_num($rel['criticas']), 'icone' => 'exclamation-triangle', 'tom' => 'danger', 'sub' => 'de ' . $rel['perguntas_escala'] . ' perguntas com nota']);
  ?>
</div>
<div class="mt-3"><?php View::parcial('legenda_zonas', ['zonas' => $zonas]); ?></div>

<div class="row g-4 mt-0">
  <div class="col-lg-6"><div class="cartao h-100"><div class="cartao-head"><div><h2>Índice por área</h2><p><?= $f['area'] ? 'Clique de novo para ver todas' : 'Clique em uma área para filtrar' ?></p></div></div>
    <div class="cartao-corpo"><?php $seg ? View::parcial('zbarras', ['itens' => $rel['por_area'], 'zonas' => $zonas, 'link' => $alternar('area'), 'ativo' => $f['area'] ?: null])
        : print('<div class="vazio py-4 small">Este formulário não pede área e gestor. Ative a segmentação no construtor.</div>'); ?></div></div></div>
  <div class="col-lg-6"><div class="cartao h-100"><div class="cartao-head"><div><h2>Índice por tópico</h2><p><?= $f['topico'] ? 'Clique de novo para ver todos' : 'Clique em um tópico para filtrar' ?></p></div></div>
    <div class="cartao-corpo"><?php View::parcial('zbarras', ['itens' => $rel['por_topico'], 'zonas' => $zonas, 'link' => $alternar('topico'), 'ativo' => $f['topico'] ?: null, 'vazio' => 'Sem notas de escala neste recorte.']); ?></div></div></div>
  <div class="col-lg-6"><div class="cartao h-100"><div class="cartao-head"><div><h2>Índice por pergunta</h2><p><?= $f['topico'] ? 'Perguntas do tópico filtrado' : 'Agrupado por tópico' ?></p></div></div>
    <div class="cartao-corpo" style="max-height:420px;overflow-y:auto"><?php View::parcial('zbarras', ['itens' => $rel['por_pergunta'], 'zonas' => $zonas, 'vazio' => 'Este recorte não tem perguntas de escala.']); ?></div></div></div>
  <div class="col-lg-6"><div class="cartao h-100"><div class="cartao-head"><div><h2>Índice por gestor</h2><p><?= $f['gestor'] ? 'Clique de novo para ver todos' : 'Clique em um gestor para filtrar' ?></p></div></div>
    <div class="cartao-corpo" style="max-height:420px;overflow-y:auto"><?php $seg ? View::parcial('zbarras', ['itens' => $rel['por_gestor'], 'zonas' => $zonas, 'link' => $alternar('gestor'), 'ativo' => $f['gestor'] ?: null])
        : print('<div class="vazio py-4 small">Este formulário não pede área e gestor.</div>'); ?></div></div></div>
</div>

<div class="row g-4 mt-0">
  <div class="col-xl-8"><div class="cartao h-100"><div class="cartao-head"><div><h2>Evolução do índice por ciclo</h2><p><?= $serv->recorrente ? 'Considera área, gestor e tópico filtrados' : 'Disponível para pesquisas recorrentes' ?></p></div></div>
    <div class="cartao-corpo"><?php if ($serv->recorrente && $rel['evolucao']): ?><div class="grafico baixo"><canvas data-grafico="evolucao" role="img" aria-label="Evolução do índice por ciclo"></canvas></div>
      <?php else: ?><div class="vazio py-4 small">Defina uma frequência de envio no construtor para acompanhar a evolução por ciclo.</div><?php endif; ?></div></div></div>
  <div class="col-xl-4"><div class="cartao h-100"><div class="cartao-head"><div><h2>Notas por zona</h2><p>Todas as notas do recorte</p></div></div>
    <div class="cartao-corpo"><?php if (array_sum($rel['distribuicao'])): ?><div class="grafico baixo"><canvas data-grafico="rosca" role="img" aria-label="Distribuição das notas por zona"></canvas></div>
      <?php else: ?><div class="vazio py-4 small">Sem notas neste recorte.</div><?php endif; ?></div></div></div>
</div>

<?php elseif ($f['aba'] === 'abertas'): $ab = $rel['abertas']; ?>
<div class="cartao">
  <form class="p-3 border-bottom d-flex flex-wrap gap-2 align-items-center" method="get" action="<?= url('relatorios') ?>">
    <?php foreach (array_filter($base, static fn ($v) => $v !== null) as $k => $v): ?><input type="hidden" name="<?= e($k) ?>" value="<?= e((string) $v) ?>"><?php endforeach; ?>
    <div class="input-group" style="max-width:28rem"><span class="input-group-text" style="background:rgb(var(--surface));border-color:rgb(var(--line))"><?= icone('search') ?></span>
      <input class="form-control" name="busca" value="<?= e($f['busca']) ?>" placeholder="Buscar nos comentários"></div>
    <button class="btn btn-light">Buscar</button>
    <span class="xsmall text-muted-2"><?= fmt_num($ab['total']) ?> comentários. Use os filtros acima para recortar.</span>
  </form>
  <?php if ($ab['itens']): ?>
  <ul class="comentarios">
    <?php foreach ($ab['itens'] as $i): ?>
      <li>
        <div class="d-flex flex-wrap gap-1"><span class="tag prim"><?= e($i['topico']) ?></span>
          <?php if ($i['area']): ?><span class="tag"><?= e($i['area']) ?></span><?php endif; ?>
          <?php if ($i['gestor']): ?><span class="tag"><?= e($i['gestor']) ?></span><?php endif; ?></div>
        <p>“<?= e($i['texto']) ?>”</p>
        <div class="xsmall text-muted-2 mt-2"><?= e($i['pergunta']) ?> · <?= $i['ciclo'] ? $i['ciclo'] . 'º ciclo, ' : '' ?><?= (new DateTimeImmutable('@' . $i['ts']))->setTimezone(\App\Core\App::fuso())->format('d/m/Y') ?></div>
      </li>
    <?php endforeach; ?>
  </ul>
  <?php else: ?><div class="vazio"><?= icone('chat-left-text') ?><h3>Nenhum comentário neste recorte</h3><p class="small mb-0">Amplie o período ou remova filtros.</p></div><?php endif; ?>
  <?php View::parcial('paginacao', ['pagina' => $f['pagina'], 'paginas' => $ab['paginas'], 'total' => $ab['total'], 'porPagina' => 12, 'link' => static fn ($p) => $link(['pagina' => $p, 'busca' => $f['busca'] ?: null])]); ?>
</div>

<?php else: $bs = $rel['base']; ?>
<div class="cartao">
  <?php if ($bs['itens']): ?>
  <div class="table-responsive"><table class="table table-hover align-middle mb-0">
    <thead><tr><th class="ps-4">Envio</th><?php if ($serv->recorrente): ?><th>Ciclo</th><?php endif; ?><?php if ($seg): ?><th>Área</th><th>Gestor</th><?php endif; ?><th class="text-end">Índice</th><th class="d-none d-md-table-cell">Zona</th><th class="d-none d-sm-table-cell">Status</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($bs['itens'] as $r): ?>
      <tr>
        <td class="ps-4 tnum text-nowrap"><?= (new DateTimeImmutable('@' . $r['ts']))->setTimezone(\App\Core\App::fuso())->format('d/m/Y') ?> <span class="text-muted-2"><?= (new DateTimeImmutable('@' . $r['ts']))->setTimezone(\App\Core\App::fuso())->format('H:i') ?></span></td>
        <?php if ($serv->recorrente): ?><td class="tnum"><?= $r['ciclo'] ? $r['ciclo'] . 'º' : '—' ?></td><?php endif; ?>
        <?php if ($seg): ?><td class="text-truncate" style="max-width:220px"><?= e($r['area'] ?? '—') ?></td><td><?= e($r['gestor'] ?? '—') ?></td><?php endif; ?>
        <td class="text-end tnum fw-semibold" style="color:<?= e($r['zona']['cor'] ?? 'inherit') ?>"><?= fmt_pct($r['indice']) ?></td>
        <td class="d-none d-md-table-cell"><?= $r['zona'] ? '<span class="zona-badge" style="color:' . e($r['zona']['cor']) . ';background:color-mix(in srgb,' . e($r['zona']['cor']) . ' 13%,transparent)">' . e($r['zona']['nome']) . '</span>' : '<span class="xsmall text-muted-2">Sem nota</span>' ?></td>
        <td class="d-none d-sm-table-cell"><span class="badge-status <?= $r['status'] === 'COMPLETA' ? 'st-PUBLICADO' : 'st-PENDENTE' ?>"><?= $r['status'] === 'COMPLETA' ? 'Completa' : 'Parcial' ?></span></td>
        <td class="text-end pe-3"><a class="btn btn-sm btn-ghost" href="<?= url('relatorios/resposta/' . $r['id']) . '?' . http_build_query(array_filter($base + ['pagina' => $f['pagina']], static fn ($v) => $v !== null)) ?>"><?= icone('eye') ?> Ver</a></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php else: ?><div class="vazio"><?= icone('table') ?><h3>Nenhuma resposta neste recorte</h3><p class="small mb-0">Amplie o período ou remova filtros.</p></div><?php endif; ?>
  <?php View::parcial('paginacao', ['pagina' => $f['pagina'], 'paginas' => $bs['paginas'], 'total' => $bs['total'], 'porPagina' => 10, 'link' => static fn ($p) => $link(['pagina' => $p])]); ?>
</div>
<?php endif; ?>

<script type="application/json" id="dados-graficos"><?= json_encode($graficos, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) ?></script>
