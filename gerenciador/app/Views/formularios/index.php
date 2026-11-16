<?php
use App\Models\Formulario;

$admin = $usuario['perfil'] === 'ADMIN';
?>
<div class="page-head">
  <div><h1>Formulários</h1><p>Crie, publique e acompanhe os formulários. As alterações chegam ao link público pelo sincronizador.</p></div>
  <?php if ($admin): ?>
  <div class="page-actions"><form method="post" action="<?= url('formularios/novo') ?>"><?= csrf_campo() ?><button class="btn btn-primary"><?= icone('plus-lg') ?> Criar formulário</button></form></div>
  <?php endif; ?>
</div>

<form class="cartao p-3 mb-3 d-flex flex-wrap gap-2 align-items-end" method="get" action="<?= url('formularios') ?>">
  <div class="flex-grow-1" style="min-width:220px"><label class="form-label small" for="busca">Buscar</label>
    <input class="form-control" id="busca" name="busca" value="<?= e($busca) ?>" placeholder="Título ou endereço do link"></div>
  <div><label class="form-label small" for="st">Status</label>
    <select class="form-select" id="st" name="status" data-auto-enviar>
      <option value="">Todos</option>
      <?php foreach (Formulario::STATUS as $k => [$rot]): ?><option value="<?= e($k) ?>"<?= $filtroStatus === $k ? ' selected' : '' ?>><?= e($rot) ?></option><?php endforeach; ?>
    </select></div>
  <button class="btn btn-light"><?= icone('search') ?> Filtrar</button>
</form>

<div class="cartao">
  <?php if (!$forms): ?>
    <div class="vazio"><?= icone('file-earmark-text') ?><h3>Nenhum formulário encontrado</h3><p class="small mb-0"><?= $busca || $filtroStatus ? 'Ajuste a busca ou o filtro.' : 'Crie o primeiro formulário para começar.' ?></p></div>
  <?php endif; ?>
  <?php foreach ($forms as $f):
      $st = $f['status'];
      $pub = $f['publicacao_status']; ?>
    <div class="form-linha">
      <span class="form-cor" style="background:<?= e($f['cor']) ?>"></span>
      <div class="flex-grow-1 min-w-0">
        <div class="d-flex flex-wrap align-items-center gap-2">
          <a class="titulo text-truncate" href="<?= url('formularios/' . $f['id'] . '/construtor') ?>"><?= e($f['titulo']) ?></a>
          <span class="badge-status st-<?= e($st) ?>"><?= e(Formulario::STATUS[$st][0]) ?></span>
          <?php if ($pub === 'PENDENTE'): ?><span class="badge-status st-PENDENTE" title="O sincronizador ainda não enviou esta versão para o link público">Aguardando sincronização</span><?php endif; ?>
          <?php if ($pub === 'ERRO'): ?><a class="badge-status st-ERRO text-decoration-none" href="<?= url('sincronizacao') ?>">Erro na publicação</a><?php endif; ?>
        </div>
        <div class="meta">
          <span><?= icone('link-45deg') ?> /formulario/<?= e($f['slug']) ?></span>
          <span><?= (int) $f['total_perguntas'] ?> perguntas</span>
          <span><?= fmt_num((int) $f['total_respostas']) ?> respostas</span>
          <?php if ($f['recorrencia'] !== 'NENHUMA'): ?><span><?= icone('arrow-repeat') ?> <?= e(Formulario::RECORRENCIAS[$f['recorrencia']]) ?></span><?php endif; ?>
          <?php if ($f['inicio_em'] || $f['fim_em']): ?><span><?= icone('calendar3') ?> <?= $f['inicio_em'] ? local_dt($f['inicio_em'], 'd/m/Y') : '…' ?> a <?= $f['fim_em'] ? local_dt($f['fim_em'], 'd/m/Y') : 'sem fim' ?></span><?php endif; ?>
          <?php if ($f['ultima_resposta']): ?><span>Última resposta <?= local_dt($f['ultima_resposta']) ?></span><?php endif; ?>
        </div>
      </div>
      <div class="d-flex gap-1 flex-shrink-0">
        <a class="btn btn-sm btn-light" href="<?= url('relatorios?formulario=' . $f['id']) ?>" title="Relatório"><?= icone('bar-chart') ?><span class="d-none d-xl-inline">Relatório</span></a>
        <a class="btn btn-sm btn-light" href="<?= url('formularios/' . $f['id'] . '/compartilhar') ?>" title="Compartilhar"><?= icone('share') ?><span class="d-none d-xl-inline">Compartilhar</span></a>
        <div class="dropdown">
          <button class="icon-btn" data-bs-toggle="dropdown" aria-label="Mais ações"><?= icone('three-dots-vertical') ?></button>
          <ul class="dropdown-menu dropdown-menu-end shadow">
            <li><a class="dropdown-item" href="<?= url('formularios/' . $f['id'] . '/construtor') ?>"><?= icone($admin ? 'pencil' : 'eye') ?> <?= $admin ? 'Editar' : 'Ver estrutura' ?></a></li>
            <li><a class="dropdown-item" href="<?= url('formularios/' . $f['id'] . '/previa') ?>" target="_blank" rel="noopener"><?= icone('phone') ?> Ver como participante</a></li>
            <?php if ($admin): ?>
            <li><hr class="dropdown-divider"></li>
            <?php if ($st === 'RASCUNHO' || $st === 'ENCERRADO'): ?>
              <li><form method="post" action="<?= url('formularios/' . $f['id'] . '/publicar') ?>" data-confirmar="“<?= e($f['titulo']) ?>” ficará disponível no link público<?= $f['inicio_em'] || $f['fim_em'] ? '' : ', aberto até ser encerrado' ?>." data-titulo="Publicar formulário?" data-rotulo="Publicar"><?= csrf_campo() ?>
                <button class="dropdown-item"><?= icone('send') ?> Publicar</button></form></li>
            <?php else: ?>
              <li><form method="post" action="<?= url('formularios/' . $f['id'] . '/despublicar') ?>" data-confirmar="“<?= e($f['titulo']) ?>” volta para rascunho e o link deixa de aceitar respostas. As respostas recebidas são mantidas." data-titulo="Despublicar formulário?" data-rotulo="Despublicar"><?= csrf_campo() ?>
                <button class="dropdown-item"><?= icone('pause-circle') ?> Despublicar</button></form></li>
              <li><form method="post" action="<?= url('formularios/' . $f['id'] . '/encerrar') ?>" data-confirmar="“<?= e($f['titulo']) ?>” deixa de aceitar respostas imediatamente. Os resultados continuam em Relatórios." data-titulo="Encerrar formulário?" data-rotulo="Encerrar" data-perigo><?= csrf_campo() ?>
                <button class="dropdown-item"><?= icone('lock') ?> Encerrar</button></form></li>
            <?php endif; ?>
            <li><form method="post" action="<?= url('formularios/' . $f['id'] . '/duplicar') ?>"><?= csrf_campo() ?><button class="dropdown-item"><?= icone('copy') ?> Duplicar</button></form></li>
            <li><hr class="dropdown-divider"></li>
            <li><form method="post" action="<?= url('formularios/' . $f['id'] . '/excluir') ?>" data-confirmar="“<?= e($f['titulo']) ?>” some das listas e o link é desativado. As <?= fmt_num((int) $f['total_respostas']) ?> respostas continuam guardadas no banco." data-titulo="Excluir formulário?" data-rotulo="Excluir" data-perigo data-digitar="<?= e($f['slug']) ?>"><?= csrf_campo() ?>
              <button class="dropdown-item text-danger-2"><?= icone('trash') ?> Excluir</button></form></li>
            <?php endif; ?>
          </ul>
        </div>
      </div>
    </div>
  <?php endforeach; ?>
</div>
