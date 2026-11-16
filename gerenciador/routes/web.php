<?php
declare(strict_types=1);

use App\Controllers\AuthController;
use App\Controllers\ConfiguracoesController;
use App\Controllers\ConstrutorController;
use App\Controllers\DashboardController;
use App\Controllers\FormulariosController;
use App\Controllers\PreviaController;
use App\Controllers\RelatoriosController;
use App\Controllers\SincronizacaoController;

/** @var App\Core\Router $r */
$r->get('/login', [AuthController::class, 'formulario']);
$r->post('/login', [AuthController::class, 'entrar']);
$r->post('/sair', [AuthController::class, 'sair']);
$r->get('/trocar-senha', [AuthController::class, 'trocarSenhaForm']);
$r->post('/trocar-senha', [AuthController::class, 'trocarSenha']);

$r->get('/', [DashboardController::class, 'index']);

$r->get('/formularios', [FormulariosController::class, 'index']);
$r->post('/formularios/novo', [FormulariosController::class, 'novo']);
$r->post('/formularios/{id}/duplicar', [FormulariosController::class, 'duplicar']);
$r->post('/formularios/{id}/excluir', [FormulariosController::class, 'excluir']);
$r->post('/formularios/{id}/publicar', [FormulariosController::class, 'publicar']);
$r->post('/formularios/{id}/encerrar', [FormulariosController::class, 'encerrar']);
$r->post('/formularios/{id}/despublicar', [FormulariosController::class, 'despublicar']);
$r->get('/formularios/{id}/compartilhar', [FormulariosController::class, 'compartilhar']);

$r->get('/formularios/{id}/construtor', [ConstrutorController::class, 'editar']);
$r->post('/formularios/{id}/salvar', [ConstrutorController::class, 'salvar']);
$r->post('/formularios/{id}/imagem', [ConstrutorController::class, 'imagem']);

$r->get('/formularios/{id}/previa', [PreviaController::class, 'previa']);
$r->get('/uploads/{arquivo}', [PreviaController::class, 'upload']);

$r->get('/relatorios', [RelatoriosController::class, 'index']);
$r->get('/relatorios/exportar/{formato}', [RelatoriosController::class, 'exportar']);
$r->get('/relatorios/resposta/{id}', [RelatoriosController::class, 'resposta']);

$r->get('/configuracoes', [ConfiguracoesController::class, 'index']);
$r->post('/configuracoes/{secao}', [ConfiguracoesController::class, 'salvar']);

$r->get('/sincronizacao', [SincronizacaoController::class, 'index']);
$r->post('/sincronizacao/reprocessar', [SincronizacaoController::class, 'reprocessar']);
$r->post('/sincronizacao/publicacao/{id}/reenviar', [SincronizacaoController::class, 'reenviarPublicacao']);
