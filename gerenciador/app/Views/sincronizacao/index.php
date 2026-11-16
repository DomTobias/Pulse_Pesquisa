<?php
$v = static fn (string $k) => $status[$k]['valor'] ?? null;
$uso = (int) $v('supabase_uso_bytes');
$cap = max(1, (int) ($v('supabase_capacidade_bytes') ?: 524288000));
$pctUso = min(100, round($uso / $cap * 100, 1));
$admin = $usuario['perfil'] === 'ADMIN';
$mb = static fn (int $b) => fmt_num($b / 1048576, 1) . ' MB';
?>
<div class="page-head">
  <div><h1>Sincronização</h1><p>Situação informada pelo serviço local. O gerenciador não acessa o Supabase diretamente.</p></div>
  <div class="page-actions"><a class="btn btn-light" href="<?= url('sincronizacao') ?>"><?= icone('arrow-clockwise') ?> Atualizar</a></div>
</div>

<div class="kpis">
  <div class="cartao kpi"><div class="kpi-top"><span>Serviço</span><span class="status-ponto <?= $ativo ? 'ok' : 'erro' ?>"></span></div>
    <div class="kpi-valor"><?= $ativo ? 'Ativo' : 'Sem sinal' ?></div>
    <div class="kpi-sub">Último sinal: <?= local_dt($v('servico_heartbeat')) ?></div></div>
  <div class="cartao kpi"><div class="kpi-top"><span>Última sincronização</span><span class="kpi-ico"><?= icone('arrow-down-circle') ?></span></div>
    <div class="kpi-valor" style="font-size:20px"><?= local_dt($v('ultima_sincronizacao'), 'd/m H:i:s') ?></div>
    <div class="kpi-sub"><?= e($v('ultimo_lote') ?? 'Nenhum lote ainda') ?></div></div>
  <div class="cartao kpi"><div class="kpi-top"><span>Na fila</span><span class="kpi-ico warn"><?= icone('hourglass-split') ?></span></div>
    <div class="kpi-valor"><?= fmt_num((int) $v('fila_pendentes')) ?></div><div class="kpi-sub">Aguardando o próximo ciclo</div></div>
  <div class="cartao kpi"><div class="kpi-top"><span>Com erro</span><span class="kpi-ico danger"><?= icone('exclamation-octagon') ?></span></div>
    <div class="kpi-valor"><?= fmt_num(count($erros)) ?></div><div class="kpi-sub">Após o máximo de tentativas</div></div>
  <div class="cartao kpi"><div class="kpi-top"><span>Uso do Supabase</span><span class="kpi-ico"><?= icone('database') ?></span></div>
    <div class="kpi-valor" style="font-size:20px"><?= $uso ? $pctUso . '%' : '—' ?></div>
    <div class="uso-barra mt-2"><span style="width:<?= $pctUso ?>%"></span></div>
    <div class="kpi-sub"><?= $uso ? $mb($uso) . ' de ' . $mb($cap) : 'Sem leitura ainda' ?></div></div>
</div>

<?php if ($v('ultimo_erro')): ?><div class="alert alert-danger small mt-3"><?= icone('exclamation-triangle') ?> Último erro do serviço: <?= e($v('ultimo_erro')) ?></div><?php endif; ?>

<div class="cartao mt-4">
  <div class="cartao-head"><div><h2>Registros com erro na fila</h2><p>Respostas que não puderam ser gravadas no MySQL. Corrija a causa e reprocesse.</p></div></div>
  <?php if ($erros): ?>
  <div class="table-responsive"><table class="table align-middle mb-0 small">
    <thead><tr><th class="ps-4">Envio</th><th>Formulário</th><th>Tentativas</th><th>Erro</th><th>Recebido</th><th></th></tr></thead>
    <tbody><?php foreach ($erros as $er): ?>
      <tr><td class="ps-4 mono"><?= e(substr($er['uuid_envio'], 0, 8)) ?>…</td><td><?= e($er['titulo'] ?? '—') ?></td><td class="tnum"><?= (int) $er['tentativas'] ?></td>
        <td class="text-truncate" style="max-width:320px" title="<?= e($er['mensagem_erro']) ?>"><?= e($er['mensagem_erro']) ?></td><td class="text-nowrap"><?= local_dt($er['criado_em']) ?></td>
        <td class="text-end pe-3"><?php if (in_array($er['uuid_envio'], $pendentes, true)): ?><span class="tag">Solicitado</span>
          <?php elseif ($admin): ?><form method="post" action="<?= url('sincronizacao/reprocessar') ?>"><?= csrf_campo() ?><input type="hidden" name="uuid" value="<?= e($er['uuid_envio']) ?>"><button class="btn btn-sm btn-light"><?= icone('arrow-repeat') ?> Reprocessar</button></form><?php endif; ?></td></tr>
    <?php endforeach; ?></tbody>
  </table></div>
  <?php else: ?><div class="vazio py-4"><?= icone('check-circle') ?><div class="small">Nenhum registro com erro.</div></div><?php endif; ?>
</div>

<div class="row g-4 mt-0">
  <div class="col-xl-5"><div class="cartao h-100">
    <div class="cartao-head"><div><h2>Publicações</h2><p>Definições enviadas para o link público (Fluxo B).</p></div></div>
    <div class="table-responsive"><table class="table align-middle mb-0 small">
      <thead><tr><th class="ps-4">Formulário</th><th>Motivo</th><th>Situação</th><th></th></tr></thead>
      <tbody><?php foreach ($publicacoes as $p): ?>
        <tr><td class="ps-4"><div class="text-truncate" style="max-width:180px"><?= e($p['titulo']) ?></div><div class="xsmall text-muted-2">v<?= (int) $p['versao'] ?> · <?= local_dt($p['criado_em']) ?></div></td>
          <td><?= e(mb_strtolower($p['motivo'])) ?></td>
          <td><span class="badge-status st-<?= e($p['status']) ?>" title="<?= e($p['mensagem_erro'] ?? '') ?>"><?= ['PENDENTE' => 'Pendente', 'ENVIADA' => 'Enviada', 'ERRO' => 'Erro'][$p['status']] ?></span></td>
          <td class="text-end pe-3"><?php if ($p['status'] === 'ERRO' && $admin): ?><form method="post" action="<?= url('sincronizacao/publicacao/' . $p['id'] . '/reenviar') ?>"><?= csrf_campo() ?><button class="btn btn-sm btn-light" title="<?= e($p['mensagem_erro'] ?? '') ?>">Reenviar</button></form><?php endif; ?></td></tr>
      <?php endforeach; if (!$publicacoes): ?><tr><td colspan="4" class="text-center text-muted-2 py-4">Nenhuma publicação ainda.</td></tr><?php endif; ?></tbody>
    </table></div>
  </div></div>
  <div class="col-xl-7"><div class="cartao h-100">
    <div class="cartao-head"><div><h2>Log do sincronizador</h2><p>Últimos 100 eventos (sem senhas nem tokens).</p></div>
      <form method="get" action="<?= url('sincronizacao') ?>"><select class="form-select form-select-sm" name="nivel" data-auto-enviar aria-label="Nível">
        <option value="">Todos os níveis</option><?php foreach (['INFO', 'WARNING', 'ERROR', 'CRITICAL'] as $n): ?><option<?= $nivel === $n ? ' selected' : '' ?>><?= $n ?></option><?php endforeach; ?></select></form></div>
    <div style="max-height:420px;overflow-y:auto"><table class="table align-middle mb-0 small">
      <tbody><?php foreach ($logs as $l): ?>
        <tr><td class="ps-4 text-nowrap tnum text-muted-2"><?= local_dt($l['criado_em'], 'd/m H:i:s') ?></td>
          <td><span class="tag<?= in_array($l['nivel'], ['ERROR', 'CRITICAL'], true) ? ' text-danger-2' : ($l['nivel'] === 'WARNING' ? ' text-warn' : '') ?>"><?= e($l['nivel']) ?></span></td>
          <td><span class="mono"><?= e($l['evento']) ?></span><div><?= e($l['mensagem']) ?></div></td></tr>
      <?php endforeach; if (!$logs): ?><tr><td class="text-center text-muted-2 py-4">Sem eventos.</td></tr><?php endif; ?></tbody>
    </table></div>
  </div></div>
</div>
