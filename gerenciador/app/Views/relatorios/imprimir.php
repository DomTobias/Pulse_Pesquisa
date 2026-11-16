<?php
/** Versão para impressão e PDF (HTML simples, compatível com Dompdf). */
use App\Services\Indicadores\Anonimato;

$zonas = $serv->zonas;
$fuso = \App\Core\App::fuso();
$filtrosTxt = [];
if ($rel['filtros']['area']) $filtrosTxt[] = 'Área: ' . $serv->areas[$rel['filtros']['area']]['nome'];
if ($rel['filtros']['gestor']) $filtrosTxt[] = 'Gestor: ' . $serv->gestores[$rel['filtros']['gestor']]['nome'];
if ($rel['filtros']['topico']) foreach ($serv->estrutura as $t) if ((int) $t['id'] === $rel['filtros']['topico']) $filtrosTxt[] = 'Tópico: ' . $t['titulo'];
$barras = static function (string $titulo, array $itens) use ($zonas) {
    if (!$itens) return;
    echo '<h2>' . e($titulo) . '</h2><table class="barras">';
    $grupo = null;
    foreach ($itens as $i) {
        if (!empty($i['grupo']) && $i['grupo'] !== $grupo) { $grupo = $i['grupo']; echo '<tr><td colspan="3" class="grupo">' . e($grupo) . '</td></tr>'; }
        $z = $zonas->zonaDe($i['valor']);
        $larg = $i['valor'] === null ? 0 : max(1, round($i['valor'] * 10));
        echo '<tr><td class="rot">' . e($i['rotulo']) . '</td><td class="trilho"><div class="fundo"><div class="barra" style="width:' . $larg . '%;background:' . e($z['cor'] ?? '#ccc') . '"></div></div></td>'
            . '<td class="val">' . ($i['suprimido'] ? '<span class="sup">grupo pequeno</span>' : '<b style="color:' . e($z['cor'] ?? '#555') . '">' . fmt_pct($i['valor']) . '</b>') . ' <span class="n">' . ($i['n'] ? fmt_num($i['n']) : '') . '</span></td></tr>';
    }
    echo '</table>';
};
?><!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<title>Relatório · <?= e($form['titulo']) ?></title>
<style>
  @page { margin: 18mm 14mm; }
  body { font-family: "DejaVu Sans", "Segoe UI", Arial, sans-serif; color: #111E2C; font-size: 11px; margin: 0; }
  .topo { border-bottom: 3px solid #FAC043; padding-bottom: 8px; margin-bottom: 12px; }
  h1 { font-size: 20px; margin: 0; color: #032B4C; }
  h2 { font-size: 13px; margin: 18px 0 6px; color: #032B4C; page-break-after: avoid; }
  .sub { color: #5C6A78; margin-top: 3px; }
  table { width: 100%; border-collapse: collapse; }
  .kpis td { border: 1px solid #DEE3E9; padding: 8px; width: 25%; vertical-align: top; }
  .kpis .v { font-size: 20px; font-weight: bold; margin-top: 4px; }
  .barras td { padding: 3px 4px; vertical-align: middle; }
  .barras .rot { width: 45%; }
  .barras .trilho { width: 38%; }
  .barras .val { width: 17%; text-align: right; white-space: nowrap; }
  .fundo { background: #EEF1F4; height: 8px; border-radius: 4px; }
  .barra { height: 8px; border-radius: 4px; }
  .grupo { font-weight: bold; color: #5C6A78; padding-top: 6px !important; }
  .n { color: #8A96A3; font-size: 9px; }
  .sup { color: #8A96A3; font-style: italic; }
  .legenda td { padding: 4px 6px; font-size: 10px; }
  .coment { border: 1px solid #DEE3E9; padding: 6px 8px; margin-bottom: 5px; page-break-inside: avoid; }
  .coment .m { color: #5C6A78; font-size: 9px; margin-top: 3px; }
  .aviso { background: #FFF6E0; border: 1px solid #F1D48A; padding: 8px; margin: 10px 0; }
  .rodape { margin-top: 20px; color: #8A96A3; font-size: 9px; }
  .acoes { margin: 10px 0; }
  @media print { .acoes { display: none; } }
</style>
</head>
<body>
<?php if (empty($paraPdf)): ?>
<div class="acoes no-print">
  <?php if (!empty($avisoPdf)): ?><p>Para gerar o PDF, escolha <b>Salvar como PDF</b> na janela de impressão. (Para baixar o PDF direto, instale o Dompdf com <code>composer install</code>.)</p><?php endif; ?>
  <button type="button" id="imprimir">Imprimir / Salvar como PDF</button>
</div>
<?php endif; ?>
<div class="topo">
  <h1><?= e($form['titulo']) ?></h1>
  <div class="sub"><?= e($rel['periodo_rotulo']) ?> · <?= fmt_num($rel['total']) ?> respostas<?= $filtrosTxt ? ' · ' . e(implode(' · ', $filtrosTxt)) : '' ?></div>
  <div class="sub">Gerado em <?= (new DateTimeImmutable('now', $fuso))->format('d/m/Y H:i') ?> por <?= e($usuario['nome'] ?? '') ?></div>
</div>

<?php if ($rel['suprimido']): ?>
  <div class="aviso"><b><?= e(Anonimato::MENSAGEM) ?></b> O recorte tem menos de <?= $serv->limite ?> respostas.</div>
<?php else: ?>
<table class="kpis"><tr>
  <td>Índice geral<div class="v"><?= fmt_pct($rel['geral']) ?></div><?= e($zonas->zonaDe($rel['geral'])['nome'] ?? '') ?></td>
  <td>Respostas<div class="v"><?= fmt_num($rel['total']) ?></div></td>
  <td>Adesão<div class="v"><?= $rel['adesao'] === null ? '—' : $rel['adesao'] . '%' ?></div></td>
  <td>Perguntas na zona crítica<div class="v"><?= fmt_num($rel['criticas']) ?></div>de <?= $rel['perguntas_escala'] ?></td>
</tr></table>

<h2>Zonas de pontuação</h2>
<table class="legenda"><tr><?php foreach ($zonas->faixas() as $i => $z): ?><td style="border-left:4px solid <?= e($z['cor']) ?>"><b><?= e($z['nome']) ?></b><br><?= e($zonas->descricao($i)) ?></td><?php endforeach; ?></tr></table>

<?php
if ($serv->segmentado) { $barras('Índice por área', $rel['por_area']); }
$barras('Índice por tópico', $rel['por_topico']);
$barras('Índice por pergunta', $rel['por_pergunta']);
if ($serv->segmentado) { $barras('Índice por gestor', $rel['por_gestor']); }
if ($serv->recorrente && $rel['evolucao']): ?>
  <h2>Evolução do índice por ciclo</h2>
  <table class="barras"><?php foreach ($rel['evolucao'] as $c): ?>
    <tr><td class="rot"><?= e($c['completo']) ?></td><td class="trilho"><div class="fundo"><div class="barra" style="width:<?= (int) ($c['valor'] ?? 0) ?>%;background:#032B4C"></div></div></td><td class="val"><b><?= $c['valor'] === null ? '—' : $c['valor'] . '%' ?></b> <span class="n"><?= fmt_num($c['n']) ?></span></td></tr>
  <?php endforeach; ?></table>
<?php endif; ?>

<?php $ab = $rel['abertas']; if ($ab && $ab['total']): ?>
  <h2>Perguntas abertas (<?= fmt_num($ab['total']) ?>)</h2>
  <?php foreach ($ab['itens'] as $i): ?>
    <div class="coment">“<?= e($i['texto']) ?>”<div class="m"><?= e($i['topico']) ?><?= $i['area'] ? ' · ' . e($i['area']) : '' ?><?= $i['gestor'] ? ' · ' . e($i['gestor']) : '' ?><?= $i['ciclo'] ? ' · ' . $i['ciclo'] . 'º ciclo' : '' ?></div></div>
  <?php endforeach; ?>
  <?php if ($ab['total'] > count($ab['itens'])): ?><p class="sub">Mostrando <?= count($ab['itens']) ?> de <?= fmt_num($ab['total']) ?> comentários. Exporte em Excel ou CSV para ver todos.</p><?php endif; ?>
<?php endif; ?>
<?php endif; ?>
<div class="rodape">Índice = média das notas de escala normalizadas para 0–10, em porcentagem. Recortes com menos de <?= $serv->limite ?> respostas não são exibidos para preservar o anonimato.</div>
<?php if (empty($paraPdf)): ?><script src="<?= asset('js/imprimir.js') ?>"></script><?php endif; ?>
</body>
</html>
