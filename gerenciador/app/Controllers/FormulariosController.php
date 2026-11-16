<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auditoria;
use App\Core\Controller;
use App\Core\Session;
use App\Models\Configuracao;
use App\Models\Formulario;
use App\Services\PublicacaoService;

final class FormulariosController extends Controller
{
    public function index(): void
    {
        $u = $this->exigirLogin();
        $busca = mb_substr($this->entrada('busca'), 0, 100);
        $status = $this->entrada('status');
        $this->view('formularios/index', [
            'titulo' => 'Formulários', 'usuario' => $u,
            'forms' => Formulario::listar($busca, $status), 'busca' => $busca, 'filtroStatus' => $status,
        ]);
    }

    public function novo(): void
    {
        $this->exigirAdmin();
        $this->exigirCsrf();
        $id = Formulario::criar($this->entrada('titulo'));
        Auditoria::registrar('CRIAR', 'formulario', (string) $id);
        Session::flash('sucesso', 'Formulário criado como rascunho.');
        $this->redirecionar("formularios/$id/construtor");
    }

    public function duplicar(string $id): void
    {
        $this->exigirAdmin();
        $this->exigirCsrf();
        $f = $this->formOu404((int) $id);
        $novo = Formulario::duplicar((int) $f['id']);
        Auditoria::registrar('DUPLICAR', 'formulario', (string) $novo, ['origem' => (int) $f['id']]);
        Session::flash('sucesso', 'Cópia criada como rascunho.');
        $this->redirecionar("formularios/$novo/construtor");
    }

    public function excluir(string $id): void
    {
        $this->exigirAdmin();
        $this->exigirCsrf();
        $f = $this->formOu404((int) $id);
        if ($this->entrada('confirmacao') !== $f['slug']) {
            Session::flash('erro', 'Para excluir, digite o endereço do link exatamente como aparece.');
            $this->redirecionar('formularios');
        }
        if ($f['estado'] !== 'RASCUNHO' || PublicacaoService::jaPublicado((int) $f['id'])) {
            PublicacaoService::despublicar((int) $f['id']);
        }
        Formulario::excluir((int) $f['id']);
        Auditoria::registrar('EXCLUIR', 'formulario', (string) $f['id'], ['titulo' => $f['titulo']]);
        Session::flash('sucesso', 'Formulário excluído. As respostas já recebidas continuam guardadas no banco.');
        $this->redirecionar('formularios');
    }

    public function publicar(string $id): void
    {
        $this->exigirAdmin();
        $this->exigirCsrf();
        $f = $this->formOu404((int) $id);
        $erro = PublicacaoService::publicar((int) $f['id']);
        if ($erro) {
            Session::flash('erro', $erro);
        } else {
            $novo = Formulario::buscar((int) $f['id']);
            Session::flash('sucesso', ($novo['status'] === 'AGENDADO' ? 'Formulário agendado.' : 'Formulário publicado.')
                . ' O sincronizador envia a definição para o link público em instantes.');
        }
        $this->redirecionar($this->entrada('voltar') === 'compartilhar' ? "formularios/$id/compartilhar" : 'formularios');
    }

    public function encerrar(string $id): void
    {
        $this->exigirAdmin();
        $this->exigirCsrf();
        $f = $this->formOu404((int) $id);
        PublicacaoService::encerrar((int) $f['id']);
        Session::flash('sucesso', 'Formulário encerrado. Os resultados continuam em Relatórios.');
        $this->redirecionar('formularios');
    }

    public function despublicar(string $id): void
    {
        $this->exigirAdmin();
        $this->exigirCsrf();
        $f = $this->formOu404((int) $id);
        PublicacaoService::despublicar((int) $f['id']);
        Session::flash('sucesso', 'Formulário despublicado. O link deixa de aceitar respostas após a sincronização.');
        $this->redirecionar('formularios');
    }

    public function compartilhar(string $id): void
    {
        $u = $this->exigirLogin();
        $f = $this->formOu404((int) $id);
        $this->view('compartilhar/index', [
            'titulo' => 'Compartilhar', 'usuario' => $u, 'form' => $f,
            'link' => Configuracao::linkPublico($f['slug']),
            'publicado' => PublicacaoService::jaPublicado((int) $f['id']),
        ]);
    }

    private function formOu404(int $id): array
    {
        $f = Formulario::buscar($id);
        if (!$f) {
            Session::flash('erro', 'Formulário não encontrado.');
            $this->redirecionar('formularios');
        }
        return $f;
    }
}
