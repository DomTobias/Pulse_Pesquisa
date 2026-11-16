<div class="cartao p-4">
  <h1 class="fs-4 fw-semibold mb-1">Trocar senha</h1>
  <p class="text-muted-2 small mb-4"><?= !empty($obrigatoria) ? 'No primeiro acesso é obrigatório definir uma senha nova.' : 'Defina uma nova senha de acesso.' ?></p>
  <?php if (!empty($erro)): ?><div class="alert alert-danger small" role="alert"><?= e($erro) ?></div><?php endif; ?>
  <form method="post" action="<?= url('trocar-senha') ?>">
    <?= csrf_campo() ?>
    <div class="mb-3"><label class="form-label" for="sa">Senha atual</label><input class="form-control" id="sa" name="senha_atual" type="password" autocomplete="current-password" required></div>
    <div class="mb-3"><label class="form-label" for="sn">Nova senha</label><input class="form-control" id="sn" name="senha_nova" type="password" autocomplete="new-password" minlength="10" required>
      <div class="form-text">Pelo menos 10 caracteres, com letras e números.</div></div>
    <div class="mb-4"><label class="form-label" for="sc">Confirme a nova senha</label><input class="form-control" id="sc" name="senha_confirmacao" type="password" autocomplete="new-password" required></div>
    <button class="btn btn-primary w-100">Salvar nova senha</button>
  </form>
  <?php if (empty($obrigatoria)): ?><a class="d-block text-center small mt-3" href="<?= url('') ?>">Voltar</a><?php else: ?>
  <form method="post" action="<?= url('sair') ?>" class="text-center mt-3"><?= csrf_campo() ?><button class="btn btn-link btn-sm text-muted-2">Sair</button></form><?php endif; ?>
</div>
