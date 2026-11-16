<?php
use App\Core\Session;
use App\Models\Configuracao;

/** @var string $conteudo */
$usuario = $usuario ?? \App\Core\Auth::usuario();
$plataforma = Configuracao::obter('plataforma_nome', 'Pulse');
$tema = Configuracao::obter('tema', 'auto');
$cor = Configuracao::obter('cor_primaria', '#032B4C');
$densidade = Configuracao::obter('densidade', 'confortavel');
$rota = trim(substr((string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH), strlen(\App\Core\App::baseUrl())), '/');
$secao = explode('/', $rota)[0] ?: 'visao-geral';
$noAr = (int) \App\Core\Database::valor("SELECT COUNT(*) FROM formularios WHERE excluido_em IS NULL AND estado = 'ATIVO' AND (inicio_em IS NULL OR inicio_em <= UTC_TIMESTAMP()) AND (fim_em IS NULL OR fim_em > UTC_TIMESTAMP())");
$nav = [
    ['', 'visao-geral', 'Visão geral', 'grid-1x2'],
    ['formularios', 'formularios', 'Formulários', 'file-earmark-text'],
    ['relatorios', 'relatorios', 'Relatórios', 'bar-chart'],
    ['sincronizacao', 'sincronizacao', 'Sincronização', 'arrow-repeat'],
    ['configuracoes', 'configuracoes', 'Configurações', 'sliders'],
];
$flash = Session::consumirFlash();
$iniciais = implode('', array_map(static fn ($p) => mb_strtoupper(mb_substr($p, 0, 1)), array_slice(preg_split('/\s+/', trim($usuario['nome'] ?? 'U')) ?: ['U'], 0, 2)));
?><!DOCTYPE html>
<html lang="pt-BR"<?= in_array($tema, ['light', 'dark'], true) ? ' data-theme="' . e($tema) . '"' : '' ?> data-densidade="<?= e($densidade) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<meta name="csrf-token" content="<?= e(\App\Core\Csrf::token()) ?>">
<meta name="base-url" content="<?= e(\App\Core\App::baseUrl()) ?>">
<meta name="cor-primaria" content="<?= e($cor) ?>">
<title><?= e($plataforma) ?> · <?= e($titulo ?? '') ?></title>
<link rel="icon" href="<?= asset('img/favicon.svg') ?>" type="image/svg+xml">
<link rel="stylesheet" href="<?= asset('vendor/bootstrap/bootstrap.min.css') ?>">
<link rel="stylesheet" href="<?= asset('vendor/bootstrap-icons/bootstrap-icons.min.css') ?>">
<link rel="stylesheet" href="<?= asset('css/app.css') ?>">
<script src="<?= asset('js/tema.js') ?>"></script>
</head>
<body>
<div class="app-shell">
  <aside class="app-sidebar" id="sidebar" aria-label="Menu principal">
    <div class="sidebar-brand">
      <span class="brand-mark"><?= icone('activity') ?></span>
      <div class="min-w-0"><div class="brand-name text-truncate"><?= e($plataforma) ?></div><div class="brand-sub">Gerenciador local</div></div>
    </div>
    <nav class="sidebar-nav">
      <?php foreach ($nav as [$href, $id, $rotulo, $ic]):
          $ativo = $secao === $id || ($id === 'visao-geral' && $rota === '') || ($id === 'formularios' && str_starts_with($rota, 'formularios')); ?>
        <a href="<?= url($href) ?>" class="nav-item<?= $ativo ? ' active' : '' ?>"<?= $ativo ? ' aria-current="page"' : '' ?>>
          <?= icone($ic) ?><span><?= e($rotulo) ?></span>
          <?php if ($id === 'formularios'): ?><span class="nav-meta"><?= $noAr ?> no ar</span><?php endif; ?>
        </a>
      <?php endforeach; ?>
    </nav>
    <?php if (($usuario['perfil'] ?? '') === 'ADMIN'): ?>
    <div class="sidebar-card">
      <p class="fw-medium mb-1 small">Novo formulário</p>
      <p class="text-muted-2 mb-2 xsmall">Organize as perguntas em tópicos e publique com um link.</p>
      <form method="post" action="<?= url('formularios/novo') ?>"><?= csrf_campo() ?>
        <button class="btn btn-primary btn-sm w-100"><?= icone('plus-lg') ?> Criar formulário</button>
      </form>
    </div>
    <?php endif; ?>
  </aside>
  <div class="sidebar-backdrop" data-fechar-menu></div>

  <div class="app-main">
    <header class="app-header">
      <button class="icon-btn d-lg-none" type="button" data-abrir-menu aria-label="Abrir menu"><?= icone('list') ?></button>
      <div class="flex-grow-1 min-w-0"><div class="header-title text-truncate"><?= e($titulo ?? '') ?></div></div>
      <button class="icon-btn" type="button" data-alternar-tema title="Alternar tema claro/escuro" aria-label="Alternar tema"><?= icone('circle-half') ?></button>
      <div class="dropdown">
        <button class="user-btn" type="button" data-bs-toggle="dropdown" aria-expanded="false">
          <span class="avatar"><?= e($iniciais) ?></span>
          <span class="d-none d-sm-block text-start lh-sm"><span class="d-block small fw-medium"><?= e($usuario['nome'] ?? '') ?></span><span class="d-block xsmall text-muted-2"><?= e($usuario['cargo'] ?: (($usuario['perfil'] ?? '') === 'ADMIN' ? 'Administrador' : 'Leitor')) ?></span></span>
          <?= icone('chevron-down', 'text-muted-2 d-none d-sm-inline') ?>
        </button>
        <ul class="dropdown-menu dropdown-menu-end shadow">
          <li class="px-3 py-2 border-bottom"><div class="small fw-medium"><?= e($usuario['nome'] ?? '') ?></div><div class="xsmall text-muted-2"><?= e($usuario['email'] ?? '') ?></div></li>
          <li><a class="dropdown-item" href="<?= url('configuracoes?aba=perfil') ?>"><?= icone('person') ?> Meu perfil</a></li>
          <li><a class="dropdown-item" href="<?= url('trocar-senha') ?>"><?= icone('key') ?> Trocar senha</a></li>
          <li><hr class="dropdown-divider"></li>
          <li><form method="post" action="<?= url('sair') ?>"><?= csrf_campo() ?><button class="dropdown-item"><?= icone('box-arrow-right') ?> Sair</button></form></li>
        </ul>
      </div>
    </header>
    <main class="app-content">
      <div class="conteudo<?= ($largura ?? '') === 'ampla' ? ' conteudo-amplo' : '' ?>">
        <?= $conteudo ?>
      </div>
    </main>
  </div>
</div>

<div class="toast-container position-fixed bottom-0 end-0 p-3" id="toasts">
  <?php foreach ($flash as $tipo => $msg): ?>
    <div class="toast align-items-center border-0 toast-<?= e($tipo) ?>" role="<?= $tipo === 'erro' ? 'alert' : 'status' ?>" data-bs-delay="<?= $tipo === 'erro' ? 8000 : 4500 ?>">
      <div class="d-flex"><div class="toast-body"><?= icone($tipo === 'erro' ? 'exclamation-circle' : ($tipo === 'aviso' ? 'info-circle' : 'check-circle')) ?> <?= e($msg) ?></div>
      <button type="button" class="btn-close me-2 m-auto" data-bs-dismiss="toast" aria-label="Fechar"></button></div>
    </div>
  <?php endforeach; ?>
</div>

<div class="modal fade" id="modal-confirmar" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
    <div class="modal-header border-0 pb-0"><h2 class="modal-title fs-5" data-titulo></h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button></div>
    <div class="modal-body pt-2"><p class="text-muted-2 mb-0" data-mensagem></p><div data-extra></div></div>
    <div class="modal-footer border-0 pt-0"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button><button type="button" class="btn btn-primary" data-confirmar>Confirmar</button></div>
  </div></div>
</div>

<script src="<?= asset('vendor/bootstrap/bootstrap.bundle.min.js') ?>"></script>
<script src="<?= asset('js/app.js') ?>"></script>
<?php foreach ($scripts ?? [] as $s): ?>
<script src="<?= asset($s) ?>"></script>
<?php endforeach; ?>
</body>
</html>
