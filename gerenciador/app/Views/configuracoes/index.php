<?php
use App\Models\Formulario;

$admin = $usuario['perfil'] === 'ADMIN';
$abas = $admin
    ? ['geral' => ['Geral', 'sliders'], 'cadastros' => ['Áreas e gestores', 'diagram-3'], 'zonas' => ['Zonas de pontuação', 'palette'], 'usuarios' => ['Usuários', 'people'], 'perfil' => ['Meu perfil', 'person'], 'auditoria' => ['Auditoria', 'journal-text']]
    : ['perfil' => ['Meu perfil', 'person']];
?>
<div class="page-head"><div><h1>Configurações</h1><p>Preferências da plataforma, cadastros e acessos. Tudo fica no MySQL local.</p></div></div>
<div class="segmentado mb-4">
  <?php foreach ($abas as $k => [$r, $i]): ?><a href="<?= url('configuracoes?aba=' . $k) ?>" class="<?= $aba === $k ? 'active' : '' ?>"><?= icone($i) ?> <?= e($r) ?></a><?php endforeach; ?>
</div>

<?php if ($aba === 'geral'): ?>
<form method="post" action="<?= url('configuracoes/geral') ?>" class="row g-4"><?= csrf_campo() ?>
  <div class="col-lg-6"><div class="cartao p-4 h-100">
    <h2 class="fs-6 fw-semibold mb-3">Plataforma</h2>
    <div class="mb-3"><label class="form-label" for="pn">Nome da plataforma</label><input class="form-control" id="pn" name="plataforma_nome" maxlength="60" value="<?= e($cfg['plataforma_nome'] ?? 'Pulse') ?>"></div>
    <div class="mb-3"><label class="form-label" for="dl">Domínio público dos links</label><input class="form-control" id="dl" name="dominio_links" placeholder="https://pesquisa.suaempresa.com.br" value="<?= e($cfg['dominio_links'] ?? '') ?>">
      <div class="form-text">Endereço do Cloudflare Pages. Os links ficam em <code>/formulario/&lt;endereço&gt;</code>.</div></div>
    <div class="mb-3"><label class="form-label" for="ec">E-mail de contato</label><input class="form-control" id="ec" name="email_contato" type="email" value="<?= e($cfg['email_contato'] ?? '') ?>"></div>
    <div class="row g-3">
      <div class="col-sm-7"><label class="form-label" for="fz">Fuso horário</label><select class="form-select" id="fz" name="fuso_horario">
        <?php foreach ($fusos as $fz): ?><option<?= ($cfg['fuso_horario'] ?? 'America/Cuiaba') === $fz ? ' selected' : '' ?>><?= e($fz) ?></option><?php endforeach; ?></select></div>
      <div class="col-sm-5"><label class="form-label" for="cp">Cor principal</label><input class="form-control form-control-color w-100" id="cp" type="color" name="cor_primaria" value="<?= e($cfg['cor_primaria'] ?? '#032B4C') ?>"></div>
    </div>
  </div></div>
  <div class="col-lg-6"><div class="cartao p-4 h-100">
    <h2 class="fs-6 fw-semibold mb-3">Anonimato e padrões</h2>
    <div class="mb-3"><label class="form-label" for="gm">Grupo mínimo para exibir resultados</label><input class="form-control" id="gm" type="number" min="1" max="50" name="grupo_minimo" value="<?= e($cfg['grupo_minimo'] ?? '3') ?>">
      <div class="form-text">Recortes de área ou gestor com menos respostas não mostram índice nem comentários.</div></div>
    <div class="mb-3"><label class="form-label" for="pf">Formato padrão de novos formulários</label><select class="form-select" id="pf" name="padrao_formato">
      <?php foreach (Formulario::FORMATOS as $k => $r): ?><option value="<?= e($k) ?>"<?= ($cfg['padrao_formato'] ?? 'AUTO') === $k ? ' selected' : '' ?>><?= e($r) ?></option><?php endforeach; ?></select></div>
    <div class="form-check form-switch mb-2"><input class="form-check-input" type="checkbox" role="switch" id="pi" name="padrao_exige_identificacao"<?= ($cfg['padrao_exige_identificacao'] ?? '0') === '1' ? ' checked' : '' ?>><label class="form-check-label" for="pi">Novos formulários exigem identificação</label></div>
    <div class="form-check form-switch mb-3"><input class="form-check-input" type="checkbox" role="switch" id="pm" name="padrao_permite_multiplas"<?= ($cfg['padrao_permite_multiplas'] ?? '0') === '1' ? ' checked' : '' ?>><label class="form-check-label" for="pm">Novos formulários permitem várias respostas</label></div>
    <div class="mb-3"><label class="form-label" for="mc">Mensagem de conclusão padrão</label><textarea class="form-control" id="mc" name="padrao_mensagem_conclusao" rows="2" maxlength="1000"><?= e($cfg['padrao_mensagem_conclusao'] ?? '') ?></textarea></div>
    <div class="row g-3">
      <div class="col-6"><label class="form-label" for="tm">Tema</label><select class="form-select" id="tm" name="tema">
        <?php foreach (['auto' => 'Automático', 'light' => 'Claro', 'dark' => 'Escuro'] as $k => $r): ?><option value="<?= $k ?>"<?= ($cfg['tema'] ?? 'auto') === $k ? ' selected' : '' ?>><?= $r ?></option><?php endforeach; ?></select></div>
      <div class="col-6"><label class="form-label" for="dn">Densidade</label><select class="form-select" id="dn" name="densidade">
        <option value="confortavel"<?= ($cfg['densidade'] ?? '') !== 'compacta' ? ' selected' : '' ?>>Confortável</option><option value="compacta"<?= ($cfg['densidade'] ?? '') === 'compacta' ? ' selected' : '' ?>>Compacta</option></select></div>
    </div>
  </div></div>
  <div class="col-12"><button class="btn btn-primary"><?= icone('check2') ?> Salvar configurações</button></div>
</form>

<?php elseif ($aba === 'cadastros'): ?>
<div class="row g-4">
  <div class="col-xl-6"><form class="cartao p-4" method="post" action="<?= url('configuracoes/areas') ?>"><?= csrf_campo() ?>
    <h2 class="fs-6 fw-semibold mb-1">Áreas</h2>
    <p class="small text-muted-2">O número de colaboradores é usado no cálculo da adesão. Áreas removidas ficam inativas (as respostas antigas continuam ligadas a elas).</p>
    <div class="row g-2 xsmall text-muted-2 mb-1"><div class="col-8">Nome</div><div class="col-3">Colaboradores</div></div>
    <div id="lista-areas">
      <?php foreach ($areas as $a): if ((int) $a['ativo'] !== 1) continue; $k = 'a' . $a['id']; ?>
        <div class="row g-2 mb-2 align-items-center" data-linha><input type="hidden" name="itens[<?= $k ?>][id]" value="<?= (int) $a['id'] ?>">
          <div class="col-8"><input class="form-control form-control-sm" name="itens[<?= $k ?>][nome]" maxlength="160" value="<?= e($a['nome']) ?>" required></div>
          <div class="col-3"><input class="form-control form-control-sm" type="number" min="0" name="itens[<?= $k ?>][colaboradores]" value="<?= (int) $a['colaboradores'] ?>"></div>
          <div class="col-1"><button type="button" class="icon-btn sm" data-remover-linha aria-label="Remover área"><?= icone('x-lg') ?></button></div></div>
      <?php endforeach; ?>
    </div>
    <template id="tpl-area"><div class="row g-2 mb-2 align-items-center" data-linha>
      <div class="col-8"><input class="form-control form-control-sm" name="itens[__i__][nome]" maxlength="160" required></div>
      <div class="col-3"><input class="form-control form-control-sm" type="number" min="0" name="itens[__i__][colaboradores]" value="0"></div>
      <div class="col-1"><button type="button" class="icon-btn sm" data-remover-linha aria-label="Remover área"><?= icone('x-lg') ?></button></div></div></template>
    <div class="d-flex gap-2 mt-3"><button type="button" class="btn btn-soft btn-sm" data-add-linha="#tpl-area" data-destino="#lista-areas"><?= icone('plus') ?> Área</button>
      <button class="btn btn-primary btn-sm ms-auto">Salvar áreas</button></div>
    <p class="xsmall text-muted-2 mt-3 mb-0">Total: <?= fmt_num(array_sum(array_map(static fn ($a) => (int) $a['ativo'] === 1 ? (int) $a['colaboradores'] : 0, $areas))) ?> colaboradores. Mudanças nas áreas chegam aos formulários publicados na próxima publicação ou edição.</p>
  </form></div>
  <div class="col-xl-6"><form class="cartao p-4" method="post" action="<?= url('configuracoes/gestores') ?>"><?= csrf_campo() ?>
    <h2 class="fs-6 fw-semibold mb-1">Gestores</h2>
    <p class="small text-muted-2">O vínculo com a área é opcional. Quando existe, o participante só vê os gestores da área escolhida.</p>
    <div class="row g-2 xsmall text-muted-2 mb-1"><div class="col-5">Nome</div><div class="col-6">Área (opcional)</div></div>
    <?php $opcoesArea = static function ($sel) use ($areas) { $h = '<option value="">Sem vínculo</option>'; foreach ($areas as $a) { if ((int) $a['ativo'] === 1) { $h .= '<option value="' . (int) $a['id'] . '"' . ((int) $sel === (int) $a['id'] ? ' selected' : '') . '>' . e($a['nome']) . '</option>'; } } return $h; }; ?>
    <div id="lista-gestores">
      <?php foreach ($gestores as $g): if ((int) $g['ativo'] !== 1) continue; $k = 'g' . $g['id']; ?>
        <div class="row g-2 mb-2 align-items-center" data-linha><input type="hidden" name="itens[<?= $k ?>][id]" value="<?= (int) $g['id'] ?>">
          <div class="col-5"><input class="form-control form-control-sm" name="itens[<?= $k ?>][nome]" maxlength="120" value="<?= e($g['nome']) ?>" required></div>
          <div class="col-6"><select class="form-select form-select-sm" name="itens[<?= $k ?>][area_id]"><?= $opcoesArea($g['area_id']) ?></select></div>
          <div class="col-1"><button type="button" class="icon-btn sm" data-remover-linha aria-label="Remover gestor"><?= icone('x-lg') ?></button></div></div>
      <?php endforeach; ?>
    </div>
    <template id="tpl-gestor"><div class="row g-2 mb-2 align-items-center" data-linha>
      <div class="col-5"><input class="form-control form-control-sm" name="itens[__i__][nome]" maxlength="120" required></div>
      <div class="col-6"><select class="form-select form-select-sm" name="itens[__i__][area_id]"><?= $opcoesArea(null) ?></select></div>
      <div class="col-1"><button type="button" class="icon-btn sm" data-remover-linha aria-label="Remover gestor"><?= icone('x-lg') ?></button></div></div></template>
    <div class="d-flex gap-2 mt-3"><button type="button" class="btn btn-soft btn-sm" data-add-linha="#tpl-gestor" data-destino="#lista-gestores"><?= icone('plus') ?> Gestor</button>
      <button class="btn btn-primary btn-sm ms-auto">Salvar gestores</button></div>
  </form></div>
</div>

<?php elseif ($aba === 'zonas'): ?>
<form class="cartao p-4" method="post" action="<?= url('configuracoes/zonas') ?>" style="max-width:52rem"><?= csrf_campo() ?>
  <h2 class="fs-6 fw-semibold mb-1">Zonas de pontuação</h2>
  <p class="small text-muted-2">Cada faixa vai até o limite informado (exclusive); a nota exatamente no limite pertence à zona de cima. A última faixa termina em 10.</p>
  <div class="row g-2 xsmall text-muted-2 mb-1"><div class="col-6">Nome</div><div class="col-3">Limite superior (0–10)</div><div class="col-2">Cor</div></div>
  <div id="lista-faixas">
    <?php foreach ($faixas as $i => $z): $k = 'f' . $i; ?>
      <div class="row g-2 mb-2 align-items-center" data-linha>
        <div class="col-6"><input class="form-control form-control-sm" name="faixas[<?= $k ?>][nome]" maxlength="80" value="<?= e($z['nome']) ?>" required></div>
        <div class="col-3"><input class="form-control form-control-sm" type="number" step="0.01" min="0.01" max="10" name="faixas[<?= $k ?>][limite_superior]" value="<?= e($z['limite_superior']) ?>" required></div>
        <div class="col-2"><input class="form-control form-control-sm form-control-color w-100" type="color" name="faixas[<?= $k ?>][cor]" value="<?= e($z['cor']) ?>"></div>
        <div class="col-1"><button type="button" class="icon-btn sm" data-remover-linha aria-label="Remover faixa"><?= icone('x-lg') ?></button></div></div>
    <?php endforeach; ?>
  </div>
  <template id="tpl-faixa"><div class="row g-2 mb-2 align-items-center" data-linha>
    <div class="col-6"><input class="form-control form-control-sm" name="faixas[__i__][nome]" maxlength="80" required></div>
    <div class="col-3"><input class="form-control form-control-sm" type="number" step="0.01" min="0.01" max="10" name="faixas[__i__][limite_superior]" required></div>
    <div class="col-2"><input class="form-control form-control-sm form-control-color w-100" type="color" name="faixas[__i__][cor]" value="#5C6A78"></div>
    <div class="col-1"><button type="button" class="icon-btn sm" data-remover-linha aria-label="Remover faixa"><?= icone('x-lg') ?></button></div></div></template>
  <div class="d-flex gap-2 mt-3"><button type="button" class="btn btn-soft btn-sm" data-add-linha="#tpl-faixa" data-destino="#lista-faixas"><?= icone('plus') ?> Faixa</button>
    <button class="btn btn-primary btn-sm ms-auto">Salvar zonas</button></div>
</form>

<?php elseif ($aba === 'usuarios'): ?>
<div class="row g-4">
  <div class="col-xl-7"><div class="cartao">
    <div class="cartao-head"><div><h2>Usuários do gerenciador</h2><p>Administradores editam e publicam. Leitores só consultam.</p></div></div>
    <div class="table-responsive"><table class="table align-middle mb-0">
      <thead><tr><th class="ps-4">Nome</th><th>Perfil</th><th>Último acesso</th><th></th></tr></thead>
      <tbody><?php foreach ($usuarios as $x): ?>
        <tr<?= (int) $x['ativo'] === 1 ? '' : ' class="opacity-50"' ?>><td class="ps-4"><div class="fw-medium"><?= e($x['nome']) ?></div><div class="xsmall text-muted-2"><?= e($x['email']) ?><?= $x['cargo'] ? ' · ' . e($x['cargo']) : '' ?></div></td>
          <td><span class="tag<?= $x['perfil'] === 'ADMIN' ? ' prim' : '' ?>"><?= $x['perfil'] === 'ADMIN' ? 'Administrador' : 'Leitor' ?></span><?= (int) $x['ativo'] === 1 ? '' : ' <span class="tag">inativo</span>' ?></td>
          <td class="small text-muted-2"><?= local_dt($x['ultimo_acesso']) ?></td>
          <td class="text-end pe-3"><a class="btn btn-sm btn-ghost" href="<?= url('configuracoes?aba=usuarios&editar=' . $x['id']) ?>"><?= icone('pencil') ?></a></td></tr>
      <?php endforeach; ?></tbody>
    </table></div>
  </div></div>
  <div class="col-xl-5">
    <?php $ed = null; foreach ($usuarios as $x) if ((int) ($_GET['editar'] ?? 0) === (int) $x['id']) $ed = $x; ?>
    <form class="cartao p-4" method="post" action="<?= url('configuracoes/usuarios') ?>"><?= csrf_campo() ?>
      <h2 class="fs-6 fw-semibold mb-3"><?= $ed ? 'Editar usuário' : 'Novo usuário' ?></h2>
      <input type="hidden" name="id" value="<?= (int) ($ed['id'] ?? 0) ?>">
      <div class="mb-3"><label class="form-label" for="un">Nome</label><input class="form-control" id="un" name="nome" maxlength="120" required value="<?= e($ed['nome'] ?? '') ?>"></div>
      <div class="mb-3"><label class="form-label" for="ue">E-mail</label><input class="form-control" id="ue" name="email" type="email" required value="<?= e($ed['email'] ?? '') ?>"></div>
      <div class="row g-3 mb-3">
        <div class="col-6"><label class="form-label" for="up">Perfil</label><select class="form-select" id="up" name="perfil"><option value="LEITOR">Leitor</option><option value="ADMIN"<?= ($ed['perfil'] ?? '') === 'ADMIN' ? ' selected' : '' ?>>Administrador</option></select></div>
        <div class="col-6"><label class="form-label" for="uc">Cargo</label><input class="form-control" id="uc" name="cargo" maxlength="120" value="<?= e($ed['cargo'] ?? '') ?>"></div>
      </div>
      <div class="mb-3"><label class="form-label" for="us"><?= $ed ? 'Nova senha temporária (opcional)' : 'Senha temporária' ?></label><input class="form-control" id="us" name="senha" type="password" autocomplete="new-password" minlength="10"<?= $ed ? '' : ' required' ?>>
        <div class="form-text">A pessoa troca a senha no primeiro acesso. Mínimo de 10 caracteres com letras e números.</div></div>
      <?php if ($ed): ?><div class="form-check form-switch mb-3"><input class="form-check-input" type="checkbox" role="switch" id="ua" name="ativo"<?= (int) $ed['ativo'] === 1 ? ' checked' : '' ?>><label class="form-check-label" for="ua">Acesso ativo</label></div><?php endif; ?>
      <div class="d-flex gap-2"><button class="btn btn-primary"><?= $ed ? 'Salvar' : 'Criar usuário' ?></button><?php if ($ed): ?><a class="btn btn-light" href="<?= url('configuracoes?aba=usuarios') ?>">Cancelar</a><?php endif; ?></div>
    </form>
  </div>
</div>

<?php elseif ($aba === 'perfil'): ?>
<form class="cartao p-4" method="post" action="<?= url('configuracoes/perfil') ?>" style="max-width:40rem"><?= csrf_campo() ?>
  <h2 class="fs-6 fw-semibold mb-3">Meu perfil</h2>
  <div class="row g-3">
    <div class="col-sm-6"><label class="form-label" for="mn">Nome</label><input class="form-control" id="mn" name="nome" maxlength="120" required value="<?= e($usuario['nome']) ?>"></div>
    <div class="col-sm-6"><label class="form-label" for="mcg">Cargo</label><input class="form-control" id="mcg" name="cargo" maxlength="120" value="<?= e($usuario['cargo'] ?? '') ?>"></div>
    <div class="col-12"><label class="form-label" for="me">E-mail</label><input class="form-control" id="me" name="email" type="email" required value="<?= e($usuario['email']) ?>"></div>
  </div>
  <div class="d-flex gap-2 mt-4"><button class="btn btn-primary">Salvar</button><a class="btn btn-light" href="<?= url('trocar-senha') ?>"><?= icone('key') ?> Trocar senha</a></div>
</form>

<?php else: ?>
<div class="cartao">
  <div class="cartao-head"><div><h2>Auditoria</h2><p>Últimas 200 ações administrativas.</p></div></div>
  <div class="table-responsive"><table class="table align-middle mb-0 small">
    <thead><tr><th class="ps-4">Quando</th><th>Usuário</th><th>Ação</th><th>Item</th><th>Detalhes</th></tr></thead>
    <tbody><?php foreach ($auditoria as $a): ?>
      <tr><td class="ps-4 text-nowrap tnum"><?= local_dt($a['criado_em'], 'd/m/Y H:i:s') ?></td><td><?= e($a['nome'] ?? '—') ?></td><td><span class="tag"><?= e($a['acao']) ?></span></td>
        <td><?= e($a['entidade']) ?> <?= e($a['entidade_id'] ?? '') ?></td><td class="mono text-muted-2 text-truncate" style="max-width:280px"><?= e($a['detalhes'] ?? '') ?></td></tr>
    <?php endforeach; if (!$auditoria): ?><tr><td colspan="5" class="text-center text-muted-2 py-4">Sem registros.</td></tr><?php endif; ?></tbody>
  </table></div>
</div>
<?php endif; ?>
