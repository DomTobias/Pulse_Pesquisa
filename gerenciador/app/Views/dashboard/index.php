<?php
use App\Core\View;
use App\Models\Formulario;
use App\Services\Indicadores\Indice;

$scripts = ['vendor/chartjs/chart.umd.js', 'js/graficos.js'];
$primeiroNome = explode(' ', trim($usuario['nome']))[0];
$graficos = [];
?>
<div class="page-head">
  <div><h1>Olá, <?= e($primeiroNome) ?></h1><p>Acompanhe a participação nos formulários e o que precisa da sua atenção.</p></div>
  <div class="page-actions">
    <a class="btn btn-light" href="<?= url('relatorios') ?>"><?= icone('bar-chart') ?> Ver relatórios</a>
    <?php if ($usuario['perfil'] === 'ADMIN'): ?>
    <form method="post" action="<?= url('formularios/novo') ?>"><?= csrf_campo() ?><button class="btn btn-primary"><?= icone('plus-lg') ?> Criar formulário</button></form>
    <?php endif; ?>
  </div>
</div>

<?php if (!$syncAtivo): ?>
<div class="alert alert-warning d-flex gap-2 align-items-start small">
  <?= icone('exclamation-triangle') ?>
  <div>O sincronizador não deu sinal nos últimos minutos. Respostas novas e publicações ficam aguardando até ele voltar.
    <a href="<?= url('sincronizacao') ?>">Ver sincronização</a></div>
</div>
<?php endif; ?>

<div class="kpis">
  <?php
  View::parcial('kpi', ['rotulo' => 'Formulários', 'valor' => count($forms), 'icone' => 'file-earmark-text',
      'sub' => ($cont['AGENDADO'] ?? 0) . ' agendado, ' . ($cont['ENCERRADO'] ?? 0) . ' encerrado']);
  View::parcial('kpi', ['rotulo' => 'Publicados', 'valor' => $cont['PUBLICADO'] ?? 0, 'icone' => 'globe', 'tom' => 'ok', 'sub' => 'Recebendo respostas agora']);
  View::parcial('kpi', ['rotulo' => 'Em rascunho', 'valor' => $cont['RASCUNHO'] ?? 0, 'icone' => 'pencil', 'tom' => 'muted', 'sub' => 'Ainda não publicados']);
  View::parcial('kpi', ['rotulo' => 'Respostas recebidas', 'valor' => fmt_num($total), 'icone' => 'inbox', 'sub' => fmt_num($ultimos30) . ' nos últimos 30 dias']);
  View::parcial('kpi', ['rotulo' => 'Respostas em ' . $nomeMes, 'valor' => fmt_num($mes), 'icone' => 'graph-up',
      'sub' => ($mesAnt ? fmt_num($mesAnt) . ' no mês anterior · ' : '') . 'média de ' . fmt_num($mes / max(1, $diasMes), 1) . ' por dia']);
  ?>
</div>

<?php if ($destaque && $resumo):
    $at = $resumo['atual'];
    $var = $resumo['variacao'];
    $graficos['dual'] = [
        'rotulos' => array_column($resumo['por_ciclo'], 'rotulo'),
        'completos' => array_column($resumo['por_ciclo'], 'completo'),
        'series' => [
            ['nome' => 'Adesão', 'cor' => '#FAC043', 'valores' => array_column($resumo['por_ciclo'], 'adesao')],
            ['nome' => 'Resultado (índice)', 'cor' => 'primary', 'valores' => array_column($resumo['por_ciclo'], 'resultado')],
        ],
    ];
?>
<div class="cartao destaque mt-4">
  <div class="destaque-capa" style="background:<?= e($destaque['cor']) ?>">
    <svg class="onda" viewBox="0 0 200 120" aria-hidden="true"><path d="M200 10C140 30 80 80 0 120H200Z" fill="#FAC043"/></svg>
    <div>
      <div class="xsmall opacity-75">Pesquisa recorrente, <?= e(mb_strtolower(Formulario::RECORRENCIAS[$destaque['recorrencia']])) ?></div>
      <div class="fs-5 fw-semibold mt-1"><?= e($destaque['titulo']) ?></div>
      <div class="small mt-3 opacity-90"><?= $at['numero'] ?>º ciclo: <?= date('d/m', strtotime($at['inicio'])) ?> a <?= date('d/m', strtotime($at['fim'])) ?></div>
      <div class="destaque-barra"><span style="width:<?= $resumo['decorrido'] ?>%"></span></div>
      <div class="xsmall opacity-75 mt-1">Próximo ciclo começa em <?= date('d/m/Y', strtotime($at['fim'] . ' +1 day')) ?></div>
      <div class="d-flex flex-wrap gap-2 mt-3">
        <a class="btn btn-sm btn-amarelo" href="<?= url('relatorios?formulario=' . $destaque['id']) ?>">Ver relatório</a>
        <a class="btn btn-sm btn-vidro" href="<?= url('formularios/' . $destaque['id'] . '/compartilhar') ?>">Compartilhar</a>
      </div>
    </div>
  </div>
  <div class="destaque-numeros">
    <div><div class="small text-muted-2">Índice do ciclo atual</div><div class="kpi-valor"><?= fmt_pct($resumo['indice_atual']) ?></div>
      <div class="kpi-sub"><?php $z = $zonas->zonaDe($resumo['indice_atual']); echo $z ? '<span class="zona-badge" style="color:' . e($z['cor']) . ';background:color-mix(in srgb,' . e($z['cor']) . ' 13%,transparent)">' . e($z['nome']) . '</span>' : 'Sem respostas ainda'; ?></div></div>
    <div><div class="small text-muted-2">Índice do ciclo anterior</div><div class="kpi-valor"><?= fmt_pct($resumo['indice_anterior']) ?></div>
      <div class="kpi-sub"><?= $resumo['anterior'] ? $resumo['anterior']['numero'] . 'º ciclo' : 'Primeiro ciclo' ?></div></div>
    <div><div class="small text-muted-2">Variação</div>
      <div class="kpi-valor <?= $var > 0 ? 'text-ok' : ($var < 0 ? 'text-danger-2' : '') ?>"><?= $var === null ? '—' : ($var > 0 ? '+' : '') . $var . ' p.p.' ?></div>
      <div class="kpi-sub">Diferença entre as porcentagens</div></div>
    <div><div class="small text-muted-2">Adesão do ciclo atual</div><div class="kpi-valor"><?= $resumo['adesao_atual'] === null ? '—' : $resumo['adesao_atual'] . '%' ?></div>
      <div class="kpi-sub"><?= $resumo['colaboradores'] ? fmt_num($resumo['respostas_atual']) . ' de ' . fmt_num($resumo['colaboradores']) . ' colaboradores' : 'Cadastre o nº de colaboradores das áreas' ?></div></div>
  </div>
</div>

<div class="cartao mt-4">
  <div class="cartao-head"><div><h2>Adesão x resultado por ciclo</h2><p><?= e($destaque['titulo']) ?>: % de colaboradores que responderam e % de resultado de cada ciclo</p></div>
    <a class="btn btn-sm btn-ghost" href="<?= url('relatorios?formulario=' . $destaque['id']) ?>">Detalhar <?= icone('chevron-right') ?></a></div>
  <div class="cartao-corpo"><div class="grafico"><canvas data-grafico="dual" aria-label="Gráfico de adesão e resultado por ciclo" role="img"></canvas></div></div>
</div>

<div class="row g-4 mt-0">
  <div class="col-lg-6"><div class="cartao h-100">
    <div class="cartao-head"><div><h2>Áreas no ciclo atual</h2><p>Da menor para a maior nota. As primeiras pedem atenção.</p></div></div>
    <div class="cartao-corpo"><?php $destaque['segmentado']
        ? View::parcial('zbarras', ['itens' => $resumo['areas'], 'zonas' => $zonas])
        : print('<div class="vazio py-4 small">Este formulário não pede área e gestor.</div>'); ?></div>
  </div></div>
  <div class="col-lg-6"><div class="cartao h-100">
    <div class="cartao-head"><div><h2>Tópicos: ciclo atual x anterior</h2><p><?= $resumo['anterior'] ? $resumo['anterior']['numero'] . 'º ciclo para o ' . $at['numero'] . 'º ciclo' : 'Comparação entre ciclos' ?></p></div></div>
    <div class="tabela-topicos cab"><span>Tópico</span><span class="text-end">Anterior</span><span class="text-end">Atual</span><span class="text-end">Variação</span></div>
    <?php foreach ($resumo['topicos'] as $t): $d = $t['variacao']; ?>
      <div class="tabela-topicos">
        <span class="d-flex align-items-center gap-2 min-w-0"><span class="ponto" style="background:<?= e($t['zona']['cor'] ?? 'rgb(var(--line))') ?>"></span><span class="text-truncate fw-medium"><?= e($t['rotulo']) ?></span></span>
        <span class="text-end tnum text-muted-2"><?= fmt_pct($t['anterior']) ?></span>
        <span class="text-end tnum fw-semibold"><?= fmt_pct($t['atual']) ?></span>
        <span class="text-end tnum fw-semibold <?= $d > 0 ? 'text-ok' : ($d < 0 ? 'text-danger-2' : 'text-muted-2') ?>"><?= $d === null ? '—' : ($d > 0 ? '↑ +' : ($d < 0 ? '↓ ' : '')) . $d ?></span>
      </div>
    <?php endforeach; if (!$resumo['topicos']): ?><div class="vazio py-4 small">Cadastre perguntas de escala para comparar os tópicos.</div><?php endif; ?>
    <p class="px-4 py-3 border-top xsmall text-muted-2 mb-0">O ciclo atual ainda está recebendo respostas e pode mudar até <?= date('d/m/Y', strtotime($at['fim'])) ?>.</p>
  </div></div>
</div>

<?php else:
    $graficos['linha'] = ['rotulos' => array_column($serie, 'rotulo'), 'valores' => array_column($serie, 'valor'), 'nome' => 'Respostas']; ?>
<div class="cartao mt-4">
  <div class="cartao-head"><div><h2>Respostas nos últimos 30 dias</h2><p>Todos os formulários</p></div>
    <a class="btn btn-sm btn-ghost" href="<?= url('relatorios') ?>">Detalhar <?= icone('chevron-right') ?></a></div>
  <div class="cartao-corpo"><div class="grafico baixo"><canvas data-grafico="linha" role="img" aria-label="Respostas por dia"></canvas></div></div>
</div>
<?php endif; ?>

<script type="application/json" id="dados-graficos"><?= json_encode($graficos, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) ?></script>
