<div class="cartao p-4 text-center">
  <div class="fs-1 text-muted-2 mb-2"><?= icone('exclamation-circle') ?></div>
  <h1 class="fs-4 fw-semibold"><?= e($titulo ?? 'Erro') ?></h1>
  <p class="text-muted-2"><?= e($mensagem ?? '') ?></p>
  <a class="btn btn-primary" href="<?= url('') ?>">Ir para o início</a>
</div>
