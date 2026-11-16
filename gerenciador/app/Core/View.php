<?php
declare(strict_types=1);

namespace App\Core;

final class View
{
    public static function renderizar(string $view, array $dados = [], string $layout = 'admin'): void
    {
        $arquivo = App::raiz() . '/app/Views/' . $view . '.php';
        if (!is_file($arquivo)) {
            throw new \RuntimeException("View inexistente: $view");
        }
        extract($dados, EXTR_SKIP);
        ob_start();
        require $arquivo;
        $conteudo = ob_get_clean();
        if ($layout === '') {
            echo $conteudo;
            return;
        }
        require App::raiz() . '/app/Views/layout/' . $layout . '.php';
    }

    public static function parcial(string $view, array $dados = []): void
    {
        extract($dados, EXTR_SKIP);
        require App::raiz() . '/app/Views/partials/' . $view . '.php';
    }
}
