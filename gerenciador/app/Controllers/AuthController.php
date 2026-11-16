<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auditoria;
use App\Core\Auth;
use App\Core\Controller;
use App\Core\Session;
use App\Models\Usuario;

final class AuthController extends Controller
{
    public function formulario(): void
    {
        if (Auth::usuario()) {
            $this->redirecionar('');
        }
        $this->view('auth/login', ['email' => ''], 'simples');
    }

    public function entrar(): void
    {
        $this->exigirCsrf();
        $email = $this->entrada('email');
        $r = Auth::entrar($email, (string) ($_POST['senha'] ?? ''));
        if (!$r['ok']) {
            usleep(300000);
            $this->view('auth/login', ['email' => $email, 'erro' => $r['mensagem']], 'simples');
            return;
        }
        $this->redirecionar('');
    }

    public function sair(): void
    {
        $this->exigirCsrf();
        Auth::sair();
        Session::iniciar();
        Session::flash('sucesso', 'Você saiu do Pulse.');
        $this->redirecionar('login');
    }

    public function trocarSenhaForm(): void
    {
        $u = $this->exigirLogin(true);
        $this->view('auth/trocar_senha', ['obrigatoria' => (int) $u['trocar_senha'] === 1], 'simples');
    }

    public function trocarSenha(): void
    {
        $u = $this->exigirLogin(true);
        $this->exigirCsrf();
        $atual = (string) ($_POST['senha_atual'] ?? '');
        $nova = (string) ($_POST['senha_nova'] ?? '');
        $conf = (string) ($_POST['senha_confirmacao'] ?? '');
        $erro = null;
        if (!password_verify($atual, (string) Usuario::hash((int) $u['id']))) {
            $erro = 'A senha atual não confere.';
        } elseif ($nova !== $conf) {
            $erro = 'A confirmação não é igual à nova senha.';
        } elseif ($nova === $atual) {
            $erro = 'A nova senha deve ser diferente da atual.';
        } else {
            $erro = Auth::validarSenha($nova);
        }
        if ($erro) {
            $this->view('auth/trocar_senha', ['obrigatoria' => (int) $u['trocar_senha'] === 1, 'erro' => $erro], 'simples');
            return;
        }
        Usuario::definirSenha((int) $u['id'], $nova);
        Session::regenerar();
        Auditoria::registrar('TROCAR_SENHA', 'usuario', (string) $u['id']);
        Session::flash('sucesso', 'Senha alterada.');
        $this->redirecionar('');
    }
}
