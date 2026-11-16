<?php
/**
 * Barras por zona.
 * @var array $itens [chave, rotulo, valor (0–10|null), n, suprimido, grupo?]
 * @var \App\Services\Indicadores\Zonas $zonas
 * @var ?callable $link fn(chave, ativo): string  (barras clicáveis que alternam o filtro)
 * @var mixed $ativo chave selecionada
 */
$link = $link ?? null;
$ativo = $ativo ?? null;
$vazio = $vazio ?? 'Sem respostas para os filtros selecionados.';
$comDados = array_filter($itens, static fn ($i) => $i['valor'] !== null || $i['suprimido']);
if (!$comDados): ?>
  <div class="vazio py-4"><?= icone('bar-chart') ?><div class="small"><?= e($vazio) ?></div></div>
<?php return; endif; ?>
<div class="zbars">
<?php $grupo = null;
foreach ($itens as $it):
    if (!empty($it['grupo']) && $it['grupo'] !== $grupo): $grupo = $it['grupo']; ?>
    <div class="zbar-grupo"><?= e($grupo) ?></div>
  <?php endif;
    $z = $zonas->zonaDe($it['valor']);
    $sel = $ativo !== null && (string) $ativo === (string) $it['chave'];
    $tag = $link ? 'a' : 'div';
    $href = $link ? ' href="' . e($link($it['chave'], $sel)) . '" title="' . ($sel ? 'Remover filtro' : 'Filtrar por este item') . '"' : ''; ?>
  <<?= $tag ?> class="zbar<?= $ativo !== null && !$sel ? ' dim' : '' ?>"<?= $href ?>>
    <div class="zbar-top">
      <span class="text-truncate<?= $sel ? ' fw-semibold' : '' ?>"><?= e($it['rotulo']) ?></span>
      <span class="r"><span class="n tnum"><?= $it['n'] ? fmt_num($it['n']) . ' resp.' : '' ?></span>
        <?php if ($it['suprimido']): ?><span class="zbar-sup" title="<?= e(\App\Services\Indicadores\Anonimato::MENSAGEM) ?>">grupo pequeno</span>
        <?php else: ?><span class="v" style="color:<?= e($z['cor'] ?? 'inherit') ?>"><?= fmt_pct($it['valor']) ?></span><?php endif; ?></span>
    </div>
    <div class="zbar-trilho">
      <?php if ($it['valor'] !== null): ?><span style="width:<?= max(1.5, $it['valor'] * 10) ?>%;background:<?= e($z['cor']) ?>"></span><?php endif; ?>
      <?php foreach (array_slice($zonas->faixas(), 0, -1) as $f): ?><i style="left:<?= $f['limite_superior'] * 10 ?>%"></i><?php endforeach; ?>
    </div>
  </<?= $tag ?>>
<?php endforeach; ?>
</div>
