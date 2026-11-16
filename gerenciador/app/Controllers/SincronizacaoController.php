<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auditoria;
use App\Core\Controller;
use App\Core\Session;
use App\Models\Sincronizacao;

/** Painel de sincronização. Os dados vêm do MySQL, gravados pelo sincronizador. */
final class SincronizacaoController extends Controller
{
    public function index(): void
    {
        $u = $this->exigirLogin();
        $status = Sincronizacao::status();
        $nivel = in_array($_GET['nivel'] ?? '', ['INFO', 'WARNING', 'ERROR', 'CRITICAL'], true) ? $_GET['nivel'] : '';
        $this->view('sincronizacao/index', [
            'titulo' => 'Sincronização', 'usuario' => $u, 'status' => $status,
            'ativo' => Sincronizacao::servicoAtivo($status), 'erros' => Sincronizacao::erros(),
            'logs' => Sincronizacao::logs($nivel), 'nivel' => $nivel, 'publicacoes' => Sincronizacao::publicacoes(),
            'pendentes' => array_column(Sincronizacao::comandosPendentes(), 'alvo'),
        ]);
    }

    public function reprocessar(): void
    {
        $u = $this->exigirAdmin();
        $this->exigirCsrf();
        $alvo = strtolower($this->entrada('uuid'));
        if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/', $alvo)) {
            Session::flash('erro', 'Identificador inválido.');
            $this->redirecionar('sincronizacao');
        }
        Sincronizacao::solicitarReprocessamento($alvo, (int) $u['id']);
        Auditoria::registrar('REPROCESSAR', 'envio', $alvo);
        Session::flash('sucesso', 'Reprocessamento solicitado. O sincronizador devolve o registro à fila no próximo ciclo.');
        $this->redirecionar('sincronizacao');
    }

    public function reenviarPublicacao(string $id): void
    {
        $this->exigirAdmin();
        $this->exigirCsrf();
        if (Sincronizacao::reenviarPublicacao((int) $id)) {
            Auditoria::registrar('REENVIAR_PUBLICACAO', 'publicacao', $id);
            Session::flash('sucesso', 'Publicação colocada de novo na fila.');
        } else {
            Session::flash('erro', 'Só publicações com erro podem ser reenviadas.');
        }
        $this->redirecionar('sincronizacao');
    }
}
