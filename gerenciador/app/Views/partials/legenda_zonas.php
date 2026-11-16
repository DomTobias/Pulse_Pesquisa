<?php /** @var \App\Services\Indicadores\Zonas $zonas */ ?>
<div class="legenda-zonas">
  <?php foreach ($zonas->faixas() as $i => $z): ?>
    <div style="background: color-mix(in srgb, <?= e($z['cor']) ?> 12%, transparent)">
      <b style="color:<?= e($z['cor']) ?>"><span class="ponto" style="background:<?= e($z['cor']) ?>"></span><?= e($z['nome']) ?></b>
      <span class="tnum"><?= e($zonas->descricao($i)) ?></span>
    </div>
  <?php endforeach; ?>
</div>
