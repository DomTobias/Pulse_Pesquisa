<?php
use App\Models\Formulario;

$scripts = ['js/construtor.js'];
$largura = 'ampla';
$st = $form['status'];
?>
<div class="barra-salvar">
  <div class="flex-grow-1 min-w-0">
    <div class="d-flex align-items-center gap-2 flex-wrap">
      <a class="icon-btn" href="<?= url('formularios') ?>" aria-label="Voltar para formulários"><?= icone('arrow-left') ?></a>
      <span class="fw-semibold text-truncate" data-titulo-topo><?= e($form['titulo']) ?></span>
      <span class="badge-status st-<?= e($st) ?>"><?= e(Formulario::STATUS[$st][0]) ?></span>
      <span class="xsmall text-muted-2">versão <span data-versao><?= (int) $form['versao'] ?></span></span>
      <span class="estado-salvo" data-estado-salvo><?= $somenteLeitura ? 'Somente leitura' : 'Tudo salvo' ?></span>
    </div>
  </div>
  <a class="btn btn-light btn-sm" href="<?= url('formularios/' . $form['id'] . '/previa') ?>" target="_blank" rel="noopener"><?= icone('box-arrow-up-right') ?> Abrir prévia</a>
  <a class="btn btn-light btn-sm" href="<?= url('formularios/' . $form['id'] . '/compartilhar') ?>"><?= icone('share') ?> Compartilhar</a>
  <?php if (!$somenteLeitura): ?>
    <?php if ($form['estado'] === 'RASCUNHO'): ?>
    <form method="post" action="<?= url('formularios/' . $form['id'] . '/publicar') ?>" data-confirmar="Salve as alterações antes. O formulário ficará disponível no link público." data-titulo="Publicar formulário?" data-rotulo="Publicar" data-publicar>
      <?= csrf_campo() ?><button class="btn btn-light btn-sm"><?= icone('send') ?> Publicar</button></form>
    <?php endif; ?>
    <button class="btn btn-primary btn-sm" type="button" data-salvar><?= icone('check2') ?> Salvar <kbd class="d-none d-xl-inline ms-1 bg-transparent border-0 p-0 small opacity-75">Ctrl+S</kbd></button>
  <?php endif; ?>
</div>

<?php if ($totalRespostas > 0): ?>
<div class="alert alert-info small py-2"><?= icone('info-circle') ?> Este formulário já tem <?= fmt_num($totalRespostas) ?> respostas. Perguntas removidas ficam guardadas no histórico, e mudanças nas perguntas criam uma nova versão.</div>
<?php endif; ?>
<div class="alert alert-danger small d-none" data-erros role="alert"></div>

<div class="construtor">
  <aside class="cartao col-fixa p-2" aria-label="Estrutura do formulário">
    <div class="d-flex align-items-center justify-content-between px-2 pt-1 pb-2">
      <span class="small fw-semibold">Estrutura</span>
      <?php if (!$somenteLeitura): ?><button class="btn btn-soft btn-sm" type="button" data-acao="add-topico"><?= icone('plus') ?> Tópico</button><?php endif; ?>
    </div>
    <div data-estrutura></div>
  </aside>

  <section class="min-w-0">
    <div class="segmentado mb-3" role="tablist">
      <a href="#" class="active" data-aba="perguntas"><?= icone('list-check') ?> Perguntas</a>
      <a href="#" data-aba="capa"><?= icone('image') ?> Capa e textos</a>
      <a href="#" data-aba="opcoes"><?= icone('gear') ?> Período e opções</a>
    </div>
    <div data-painel="perguntas"></div>
    <div data-painel="capa" hidden></div>
    <div data-painel="opcoes" hidden></div>
  </section>

  <aside class="col-fixa" aria-label="Prévia">
    <div class="d-flex align-items-center justify-content-between mb-2">
      <span class="small fw-semibold">Prévia do participante</span>
      <div class="segmentado"><a href="#" class="active" data-previa="desktop" title="Computador"><?= icone('display') ?></a><a href="#" data-previa="celular" title="Celular"><?= icone('phone') ?></a></div>
    </div>
    <div class="previa-moldura desktop" data-moldura><iframe title="Prévia do formulário" data-iframe src="<?= e($construtor['urls']['previa']) ?>"></iframe></div>
    <p class="xsmall text-muted-2 mt-2">A prévia mostra as alterações ainda não salvas. Nada é enviado.</p>
  </aside>
</div>

<div class="modal fade" id="modal-tipos" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable"><div class="modal-content">
    <div class="modal-header"><div><h2 class="modal-title fs-5">Adicionar pergunta</h2><p class="small text-muted-2 mb-0">Escolha o tipo de resposta.</p></div><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button></div>
    <div class="modal-body"><div class="tipo-grid" data-tipos></div></div>
  </div></div>
</div>

<script type="application/json" id="dados-construtor"><?= json_encode($construtor + ['somente_leitura' => $somenteLeitura], JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) ?></script>
