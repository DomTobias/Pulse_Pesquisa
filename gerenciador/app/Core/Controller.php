<?php
declare(strict_types=1);

namespace App\Core;

abstract class Controller
{
    protected function view(string $view, array $dados = [], string $layout = 'admin'): void
    {
        View::renderizar($view, $dados, $layout);
    }

    protected function redirecionar(string $caminho): never
    {
        header('Location: ' . url($caminho));
        exit;
    }

    protected function json(array $dados, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($dados, JSON_UNESCAPED_UNICODE);
        exit;
    }

    /** Exige sessão. Usuário com troca de senha pendente só acessa a tela de troca. */
    protected function exigirLogin(bool $permitirTrocaPendente = false): array
    {
        $u = Auth::usuario();
        if (!$u) {
            if ($this->querJson()) {
                $this->json(['ok' => false, 'mensagem' => 'Sessão expirada. Entre novamente.'], 401);
            }
            $this->redirecionar('login');
        }
        if ((int) $u['trocar_senha'] === 1 && !$permitirTrocaPendente) {
            $this->redirecionar('trocar-senha');
        }
        return $u;
    }

    protected function exigirAdmin(): array
    {
        $u = $this->exigirLogin();
        if ($u['perfil'] !== 'ADMIN') {
            if ($this->querJson()) {
                $this->json(['ok' => false, 'mensagem' => 'Seu perfil permite apenas consulta.'], 403);
            }
            Session::flash('erro', 'Seu perfil permite apenas consulta.');
            $this->redirecionar('');
        }
        return $u;
    }

    protected function exigirCsrf(): void
    {
        if (!Csrf::valido()) {
            if ($this->querJson()) {
                $this->json(['ok' => false, 'mensagem' => 'Token de segurança inválido. Recarregue a página.'], 419);
            }
            http_response_code(419);
            Session::flash('erro', 'Token de segurança inválido. Tente novamente.');
            $this->redirecionar($this->caminhoDoReferer());
        }
    }

    protected function corpoJson(): array
    {
        $bruto = file_get_contents('php://input') ?: '';
        if (strlen($bruto) > 2_000_000) {
            $this->json(['ok' => false, 'mensagem' => 'Conteúdo grande demais.'], 413);
        }
        $d = json_decode($bruto, true);
        if (!is_array($d)) {
            $this->json(['ok' => false, 'mensagem' => 'JSON inválido.'], 400);
        }
        return $d;
    }

    protected function querJson(): bool
    {
        return str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json')
            || str_contains($_SERVER['CONTENT_TYPE'] ?? '', 'application/json');
    }

    protected function entrada(string $k, string $padrao = ''): string
    {
        $v = $_POST[$k] ?? $_GET[$k] ?? $padrao;
        return is_string($v) ? trim($v) : $padrao;
    }

    private function caminhoDoReferer(): string
    {
        $ref = (string) parse_url((string) ($_SERVER['HTTP_REFERER'] ?? ''), PHP_URL_PATH);
        $base = App::baseUrl();
        if ($base !== '' && str_starts_with($ref, $base)) {
            $ref = substr($ref, strlen($base));
        }
        return ltrim($ref, '/');
    }
}
