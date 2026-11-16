<?php
/** @var int $pagina @var int $paginas @var int $total @var int $porPagina @var callable $link */
$ini = $total ? ($pagina - 1) * $porPagina + 1 : 0;
$fim = min($total, $pagina * $porPagina);
?>
<div class="d-flex align-items-center justify-content-between gap-3 px-4 py-3 border-top xsmall text-muted-2">
  <span><?= $total ? fmt_num($ini) . '–' . fmt_num($fim) . ' de ' . fmt_num($total) : '0 itens' ?></span>
  <div class="d-flex align-items-center gap-1">
    <?php if ($pagina > 1): ?><a class="icon-btn sm" href="<?= e($link($pagina - 1)) ?>" aria-label="Página anterior"><?= icone('chevron-left') ?></a><?php else: ?><span class="icon-btn sm" aria-hidden="true" style="opacity:.35"><?= icone('chevron-left') ?></span><?php endif; ?>
    <span class="tnum px-1"><?= $pagina ?> / <?= max(1, $paginas) ?></span>
    <?php if ($pagina < $paginas): ?><a class="icon-btn sm" href="<?= e($link($pagina + 1)) ?>" aria-label="Próxima página"><?= icone('chevron-right') ?></a><?php else: ?><span class="icon-btn sm" aria-hidden="true" style="opacity:.35"><?= icone('chevron-right') ?></span><?php endif; ?>
  </div>
</div>
