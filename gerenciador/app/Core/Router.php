<?php
declare(strict_types=1);

namespace App\Core;

final class Router
{
    private array $rotas = [];

    public function get(string $padrao, array $acao): void
    {
        $this->rotas[] = ['GET', $padrao, $acao];
    }

    public function post(string $padrao, array $acao): void
    {
        $this->rotas[] = ['POST', $padrao, $acao];
    }

    public function despachar(string $metodo, string $caminho): void
    {
        $caminho = '/' . trim($caminho, '/');
        foreach ($this->rotas as [$m, $padrao, $acao]) {
            $regex = '#^' . preg_replace('#\{(\w+)\}#', '(?P<$1>[^/]+)', rtrim($padrao, '/') ?: '/') . '$#';
            if ($m === $metodo && preg_match($regex, $caminho, $mm)) {
                $params = array_filter($mm, 'is_string', ARRAY_FILTER_USE_KEY);
                [$classe, $metodoAcao] = $acao;
                $controller = new $classe();
                $controller->$metodoAcao(...$params);
                return;
            }
        }
        http_response_code(404);
        View::renderizar('erros/erro', ['titulo' => 'Página não encontrada', 'mensagem' => 'O endereço acessado não existe.'], 'simples');
    }
}
