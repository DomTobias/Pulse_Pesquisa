<?php
/** @var \App\Services\RelatorioService $serv @var ?array $det */
$fuso = \App\Core\App::fuso();
?>
<div class="page-head">
  <div><h1><?= $det && $det['identificacao'] ? e($det['identificacao']['nome'] ?? 'Participante') : 'Resposta anônima' ?></h1>
    <p><?= e($form['titulo']) ?><?= $det ? ', enviada em ' . (new DateTimeImmutable('@' . $det['ts']))->setTimezone($fuso)->format('d/m/Y \à\s H:i') : '' ?></p></div>
  <div class="page-actions"><a class="btn btn-light" href="<?= e($voltar) ?>"><?= icone('arrow-left') ?> Voltar à base</a></div>
</div>
<?php if (!$det): ?>
  <div class="cartao"><div class="vazio"><h3>Resposta não encontrada</h3></div></div>
<?php else: ?>
<div class="row g-2 mb-4">
  <?php foreach ([['Área', $det['area'] ?? '—'], ['Gestor', $det['gestor'] ?? '—'], ['Ciclo', $det['ciclo'] ? $det['ciclo'] . 'º' : '—'], ['Índice da resposta', fmt_pct($det['indice'])]] as [$r, $v]): ?>
    <div class="col-6 col-md-3"><div class="cartao p-3"><div class="xsmall text-muted-2"><?= e($r) ?></div><div class="fw-medium text-truncate" title="<?= e($v) ?>"><?= e($v) ?></div></div></div>
  <?php endforeach; ?>
  <?php if ($det['identificacao']): ?><div class="col-12"><div class="cartao p-3 small"><?= icone('person') ?> <?= e($det['identificacao']['nome'] ?? '') ?> · <?= e($det['identificacao']['email'] ?? '') ?></div></div><?php endif; ?>
</div>
<?php foreach ($serv->estrutura as $ti => $t): ?>
  <section class="cartao mb-3">
    <h2 class="fs-6 fw-semibold px-4 py-3 border-bottom mb-0"><?= $ti + 1 ?>. <?= e($t['titulo']) ?></h2>
    <dl class="mb-0">
      <?php foreach ($t['perguntas'] as $p): if ($p['tipo'] === 'INFO') continue;
          $v = $det['valores'][(int) $p['id']] ?? null;
          if ($v === null && (int) $p['ativo'] !== 1) continue;
          $z = null;
          if ($p['tipo'] === 'ESCALA' && $v) {
              $z = $serv->zonas->zonaDe($serv->normalizada((int) $p['id'], (float) str_replace(',', '.', $v[0])));
          } ?>
        <div class="row g-2 px-4 py-2 border-bottom small">
          <dt class="col-md-7 fw-normal text-muted-2"><?= e($p['titulo']) ?><?= (int) $p['ativo'] === 1 ? '' : ' <span class="tag">removida</span>' ?></dt>
          <dd class="col-md-5 mb-0" style="white-space:pre-line"><?php if ($v === null): ?><span class="text-muted-2">Sem resposta</span>
            <?php elseif ($z): ?><span class="zona-badge" style="color:<?= e($z['cor']) ?>;background:color-mix(in srgb,<?= e($z['cor']) ?> 13%,transparent)"><?= e($v[0]) ?></span>
            <?php else: ?><?= e(implode('; ', $v)) ?><?php endif; ?></dd>
        </div>
      <?php endforeach; ?>
    </dl>
  </section>
<?php endforeach; ?>
<?php endif; ?>
