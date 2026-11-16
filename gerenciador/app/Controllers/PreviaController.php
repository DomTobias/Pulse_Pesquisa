<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Models\Formulario;
use App\Services\DefinicaoBuilder;
use App\Services\UploadService;

/**
 * Prévia local da página do participante. Usa a cópia do renderizador em
 * public/assets/participante (o gerenciador não depende da pasta frontend/). Nada é enviado.
 */
final class PreviaController extends Controller
{
    public function previa(string $id): void
    {
        $this->exigirLogin();
        $f = Formulario::buscar((int) $id);
        if (!$f) {
            http_response_code(404);
            exit('Formulário não encontrado.');
        }
        $definicao = DefinicaoBuilder::paraPrevia(DefinicaoBuilder::montar($f));
        header('X-Frame-Options: SAMEORIGIN');
        $this->view('preview/index', ['definicao' => $definicao, 'embutida' => isset($_GET['embed'])], '');
    }

    public function upload(string $arquivo): void
    {
        $this->exigirLogin();
        $caminho = UploadService::caminho($arquivo);
        if (!$caminho) {
            http_response_code(404);
            exit;
        }
        $tipos = ['png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'webp' => 'image/webp', 'svg' => 'image/svg+xml'];
        header('Content-Type: ' . ($tipos[strtolower(pathinfo($arquivo, PATHINFO_EXTENSION))] ?? 'application/octet-stream'));
        header('Cache-Control: private, max-age=86400');
        header('X-Content-Type-Options: nosniff');
        header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; sandbox");
        readfile($caminho);
        exit;
    }
}
