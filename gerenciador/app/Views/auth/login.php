<div class="cartao p-4">
  <h1 class="fs-4 fw-semibold mb-1">Entrar</h1>
  <p class="text-muted-2 small mb-4">Acesso restrito à rede interna.</p>
  <?php if (!empty($erro)): ?><div class="alert alert-danger small" role="alert"><?= e($erro) ?></div><?php endif; ?>
  <form method="post" action="<?= url('login') ?>" novalidate>
    <?= csrf_campo() ?>
    <div class="mb-3">
      <label class="form-label" for="email">E-mail</label>
      <input class="form-control" id="email" name="email" type="email" autocomplete="username" required value="<?= e($email ?? '') ?>" autofocus>
    </div>
    <div class="mb-4">
      <label class="form-label" for="senha">Senha</label>
      <input class="form-control" id="senha" name="senha" type="password" autocomplete="current-password" required>
    </div>
    <button class="btn btn-primary w-100"><?= icone('box-arrow-in-right') ?> Entrar</button>
  </form>
</div>
