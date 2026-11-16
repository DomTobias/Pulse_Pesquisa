<?php
use App\Models\Formulario;

$scripts = ['vendor/qrcode/qrcode.js', 'js/compartilhar.js'];
$st = $form['status'];
$dominioOk = str_starts_with($link, 'https://');
$mensagem = 'Olá! Participe do formulário "' . $form['titulo'] . '": ' . $link;
?>
<div class="page-head">
  <div><h1>Compartilhar</h1><p><?= e($form['titulo']) ?> <span class="badge-status st-<?= e($st) ?> ms-1"><?= e(Formulario::STATUS[$st][0]) ?></span></p></div>
  <div class="page-actions">
    <a class="btn btn-light" href="<?= url('formularios/' . $form['id'] . '/previa') ?>" target="_blank" rel="noopener"><?= icone('phone') ?> Ver como participante</a>
    <a class="btn btn-light" href="<?= url('formularios/' . $form['id'] . '/construtor') ?>"><?= icone('pencil') ?> Construtor</a>
  </div>
</div>

<?php if ($st === 'RASCUNHO'): ?>
<div class="alert alert-warning d-flex flex-wrap gap-3 align-items-center small">
  <span><?= icone('info-circle') ?> Este formulário está em rascunho: quem abrir o link verá o aviso de que ele não está disponível.</span>
  <?php if ($usuario['perfil'] === 'ADMIN'): ?>
  <form method="post" action="<?= url('formularios/' . $form['id'] . '/publicar') ?>" class="ms-auto" data-confirmar="O formulário ficará disponível no link público." data-titulo="Publicar formulário?" data-rotulo="Publicar">
    <?= csrf_campo() ?><input type="hidden" name="voltar" value="compartilhar"><button class="btn btn-sm btn-primary"><?= icone('send') ?> Publicar agora</button></form>
  <?php endif; ?>
</div>
<?php elseif ($st === 'AGENDADO'): ?>
<div class="alert alert-info small"><?= icone('clock') ?> Agendado: o link passa a aceitar respostas em <?= local_dt($form['inicio_em']) ?>.</div>
<?php elseif ($st === 'ENCERRADO'): ?>
<div class="alert alert-secondary small"><?= icone('lock') ?> Encerrado: o link mostra que o período de respostas terminou.</div>
<?php endif; ?>
<?php if (!$dominioOk): ?>
<div class="alert alert-warning small"><?= icone('exclamation-triangle') ?> Configure o domínio público dos links em <a href="<?= url('configuracoes') ?>">Configurações</a> (deve começar com https://).</div>
<?php endif; ?>

<div class="row g-4">
  <div class="col-lg-7">
    <div class="cartao h-100">
      <div class="cartao-head"><div><h2>Link público</h2><p>Envie por e-mail, WhatsApp ou na intranet. O link é o mesmo em todos os ciclos.</p></div></div>
      <div class="cartao-corpo">
        <div class="link-caixa"><input class="form-control" id="link" value="<?= e($link) ?>" readonly><button class="btn btn-primary" data-copiar="#link"><?= icone('clipboard') ?> Copiar</button></div>
        <label class="form-label mt-4" for="msg">Mensagem pronta</label>
        <textarea class="form-control" id="msg" rows="3" readonly><?= e($mensagem) ?></textarea>
        <div class="d-flex flex-wrap gap-2 mt-2">
          <button class="btn btn-light btn-sm" data-copiar="#msg"><?= icone('clipboard') ?> Copiar mensagem</button>
          <a class="btn btn-light btn-sm" href="mailto:?subject=<?= rawurlencode($form['titulo']) ?>&amp;body=<?= rawurlencode($mensagem) ?>"><?= icone('envelope') ?> Abrir no e-mail</a>
        </div>
        <?php if ($form['recorrencia'] !== 'NENHUMA'): ?>
        <p class="small text-muted-2 mt-4 mb-0"><?= icone('arrow-repeat') ?> Pesquisa <?= e(mb_strtolower(Formulario::RECORRENCIAS[$form['recorrencia']])) ?>: o mesmo link recebe as respostas de todos os ciclos. Cada resposta é marcada com o ciclo em que foi enviada.</p>
        <?php endif; ?>
        <?php if (!$publicado): ?><p class="small text-muted-2 mt-3 mb-0"><?= icone('info-circle') ?> O link passa a funcionar depois da primeira publicação e da sincronização.</p><?php endif; ?>
      </div>
    </div>
  </div>
  <div class="col-lg-5">
    <div class="cartao h-100 text-center">
      <div class="cartao-head text-start"><div><h2>QR Code</h2><p>Para cartazes e murais. Gerado neste computador.</p></div></div>
      <div class="cartao-corpo">
        <div class="qr" id="qr" data-texto="<?= e($link) ?>" aria-label="QR Code do link público"></div>
        <div class="mt-3"><a class="btn btn-light btn-sm" id="baixar-qr" download="qrcode-<?= e($form['slug']) ?>.png" href="#"><?= icone('download') ?> Baixar PNG</a></div>
      </div>
    </div>
  </div>
</div>
