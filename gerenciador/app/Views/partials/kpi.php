<div class="cartao kpi<?= !empty($classe) ? ' ' . e($classe) : '' ?>">
  <div class="kpi-top"><span><?= e($rotulo) ?></span><span class="kpi-ico <?= e($tom ?? '') ?>"><?= icone($icone) ?></span></div>
  <div class="kpi-valor" title="<?= e((string) $valor) ?>"><?= e((string) $valor) ?></div>
  <?php if (!empty($sub)): ?><div class="kpi-sub"><?= e($sub) ?></div><?php endif; ?>
</div>
