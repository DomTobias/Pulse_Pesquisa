<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\App;
use App\Core\Auditoria;
use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Session;
use App\Models\Cadastro;
use App\Models\Configuracao;
use App\Models\Formulario;
use App\Models\Usuario;

final class ConfiguracoesController extends Controller
{
    private const ABAS = ['geral', 'cadastros', 'zonas', 'usuarios', 'perfil', 'auditoria'];

    public function index(): void
    {
        $u = $this->exigirLogin();
        $aba = in_array($_GET['aba'] ?? '', self::ABAS, true) ? $_GET['aba'] : 'geral';
        if ($u['perfil'] !== 'ADMIN' && !in_array($aba, ['perfil'], true)) {
            $aba = 'perfil';
        }
        $this->view('configuracoes/index', [
            'titulo' => 'Configurações', 'usuario' => $u, 'aba' => $aba,
            'cfg' => Configuracao::todas(), 'areas' => Cadastro::areas(), 'gestores' => Cadastro::gestores(),
            'faixas' => Cadastro::faixas(), 'usuarios' => $aba === 'usuarios' ? Usuario::listar() : [],
            'auditoria' => $aba === 'auditoria' ? Database::todos('SELECT a.*, u.nome FROM auditoria a LEFT JOIN usuarios_admin u ON u.id = a.usuario_id ORDER BY a.id DESC LIMIT 200') : [],
            'fusos' => \DateTimeZone::listIdentifiers(\DateTimeZone::AMERICA),
        ]);
    }

    public function salvar(string $secao): void
    {
        $u = $secao === 'perfil' || $secao === 'senha' ? $this->exigirLogin() : $this->exigirAdmin();
        $this->exigirCsrf();
        $erro = match ($secao) {
            'geral' => $this->geral(),
            'areas' => $this->lista('areas'),
            'gestores' => $this->lista('gestores'),
            'zonas' => Cadastro::salvarFaixas(is_array($_POST['faixas'] ?? null) ? $_POST['faixas'] : []),
            'usuarios' => $this->usuario($u),
            'perfil' => $this->perfil($u),
            default => 'Seção inválida.',
        };
        if ($erro) {
            Session::flash('erro', $erro);
        } else {
            Auditoria::registrar('CONFIGURAR', 'configuracoes', $secao);
            Session::flash('sucesso', 'Configurações salvas.');
        }
        $aba = ['areas' => 'cadastros', 'gestores' => 'cadastros'][$secao] ?? $secao;
        $this->redirecionar('configuracoes?aba=' . $aba);
    }

    private function geral(): ?string
    {
        $dominio = rtrim($this->entrada('dominio_links'), '/');
        if ($dominio !== '' && !preg_match('#^https://[a-z0-9.-]+(:\d+)?(/[A-Za-z0-9._~/-]*)?$#i', $dominio)) {
            return 'O domínio dos links deve começar com https://';
        }
        $fuso = $this->entrada('fuso_horario');
        if (!in_array($fuso, \DateTimeZone::listIdentifiers(), true)) {
            return 'Fuso horário inválido.';
        }
        $cor = $this->entrada('cor_primaria');
        if (!preg_match('/^#[0-9a-fA-F]{6}$/', $cor)) {
            return 'Cor inválida.';
        }
        $email = $this->entrada('email_contato');
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return 'E-mail de contato inválido.';
        }
        $grupo = (int) $this->entrada('grupo_minimo', '3');
        if ($grupo < 1 || $grupo > 50) {
            return 'O grupo mínimo deve ficar entre 1 e 50.';
        }
        $formato = $this->entrada('padrao_formato');
        Configuracao::salvar([
            'plataforma_nome' => mb_substr($this->entrada('plataforma_nome') ?: 'Pulse', 0, 60),
            'dominio_links' => $dominio,
            'email_contato' => $email,
            'fuso_horario' => $fuso,
            'cor_primaria' => strtoupper($cor),
            'grupo_minimo' => (string) $grupo,
            'padrao_formato' => isset(Formulario::FORMATOS[$formato]) ? $formato : 'AUTO',
            'padrao_exige_identificacao' => isset($_POST['padrao_exige_identificacao']) ? '1' : '0',
            'padrao_permite_multiplas' => isset($_POST['padrao_permite_multiplas']) ? '1' : '0',
            'padrao_mensagem_conclusao' => mb_substr($this->entrada('padrao_mensagem_conclusao'), 0, 1000),
            'tema' => in_array($this->entrada('tema'), ['auto', 'light', 'dark'], true) ? $this->entrada('tema') : 'auto',
            'densidade' => $this->entrada('densidade') === 'compacta' ? 'compacta' : 'confortavel',
        ]);
        return null;
    }

    private function lista(string $tipo): ?string
    {
        $itens = is_array($_POST['itens'] ?? null) ? $_POST['itens'] : [];
        if (count($itens) > 500) {
            return 'Lista grande demais.';
        }
        $tipo === 'areas' ? Cadastro::salvarAreas($itens) : Cadastro::salvarGestores($itens);
        return null;
    }

    private function usuario(array $eu): ?string
    {
        $id = (int) $this->entrada('id');
        $nome = mb_substr($this->entrada('nome'), 0, 120);
        $email = mb_strtolower($this->entrada('email'));
        $perfil = $this->entrada('perfil') === 'ADMIN' ? 'ADMIN' : 'LEITOR';
        $cargo = mb_substr($this->entrada('cargo'), 0, 120);
        if ($nome === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return 'Informe nome e e-mail válidos.';
        }
        if (Usuario::emailEmUso($email, $id)) {
            return 'Já existe um usuário com este e-mail.';
        }
        $senha = (string) ($_POST['senha'] ?? '');
        if (!$id) {
            if ($e = Auth::validarSenha($senha)) {
                return $e;
            }
            Usuario::criar($nome, $email, $perfil, $cargo, $senha);
            return null;
        }
        $ativo = isset($_POST['ativo']);
        if ($id === (int) $eu['id'] && (!$ativo || $perfil !== 'ADMIN')) {
            return 'Você não pode remover o seu próprio acesso de administrador.';
        }
        Usuario::atualizar($id, $nome, $email, $perfil, $cargo, $ativo);
        if (Usuario::adminsAtivos() === 0) {
            Usuario::atualizar($id, $nome, $email, 'ADMIN', $cargo, true);
            return 'O sistema precisa de pelo menos um administrador ativo.';
        }
        if ($senha !== '') {
            if ($e = Auth::validarSenha($senha)) {
                return $e;
            }
            Usuario::definirSenha($id, $senha, true);
        }
        return null;
    }

    private function perfil(array $u): ?string
    {
        $nome = mb_substr($this->entrada('nome'), 0, 120);
        $email = mb_strtolower($this->entrada('email'));
        if ($nome === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return 'Informe nome e e-mail válidos.';
        }
        if (Usuario::emailEmUso($email, (int) $u['id'])) {
            return 'Já existe um usuário com este e-mail.';
        }
        Usuario::atualizarPerfil((int) $u['id'], $nome, $email, mb_substr($this->entrada('cargo'), 0, 120));
        Auth::limparCache();
        return null;
    }
}
