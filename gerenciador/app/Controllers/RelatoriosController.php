<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auditoria;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Session;
use App\Core\View;
use App\Models\Formulario;
use App\Services\ExportService;
use App\Services\RelatorioService;

final class RelatoriosController extends Controller
{
    /** Escolhe o formulário: o pedido, ou o primeiro com respostas. */
    private function escolherFormulario(): ?array
    {
        $id = (int) ($_GET['formulario'] ?? 0);
        if ($id && ($f = Formulario::buscar($id))) {
            return $f;
        }
        $id = (int) Database::valor('SELECT f.id FROM formularios f WHERE f.excluido_em IS NULL
                                      ORDER BY (SELECT COUNT(*) FROM respostas r WHERE r.formulario_id = f.id) > 0 DESC, f.segmentado DESC, f.atualizado_em DESC LIMIT 1');
        return $id ? Formulario::buscar($id) : null;
    }

    public function index(): void
    {
        $u = $this->exigirLogin();
        $f = $this->escolherFormulario();
        if (!$f) {
            $this->view('relatorios/vazio', ['titulo' => 'Relatórios', 'usuario' => $u]);
            return;
        }
        $serv = new RelatorioService($f);
        $filtros = $serv->normalizarFiltros($_GET);
        $this->view('relatorios/index', [
            'titulo' => 'Relatórios', 'usuario' => $u, 'form' => $f, 'serv' => $serv, 'filtros' => $filtros,
            'rel' => $serv->gerar($filtros), 'forms' => Formulario::listar(),
            'excel' => ExportService::excelDisponivel(), 'pdf' => ExportService::pdfDisponivel(),
        ]);
    }

    public function resposta(string $id): void
    {
        $u = $this->exigirLogin();
        $formId = (int) Database::valor('SELECT formulario_id FROM respostas WHERE id = ?', [(int) $id]);
        $f = $formId ? Formulario::buscar($formId) : null;
        if (!$f) {
            Session::flash('erro', 'Resposta não encontrada.');
            $this->redirecionar('relatorios');
        }
        $serv = new RelatorioService($f);
        $det = $serv->detalhe((int) $id);
        Auditoria::registrar('VER_RESPOSTA', 'resposta', $id);
        $this->view('relatorios/resposta', ['titulo' => 'Resposta', 'usuario' => $u, 'form' => $f, 'serv' => $serv, 'det' => $det,
            'voltar' => url('relatorios') . '?' . http_build_query(['formulario' => $f['id'], 'aba' => 'base'] + array_intersect_key($_GET, array_flip(['periodo', 'area', 'gestor', 'topico', 'de', 'ate', 'pagina'])))]);
    }

    public function exportar(string $formato): void
    {
        $u = $this->exigirLogin();
        $f = $this->escolherFormulario();
        if (!$f) {
            $this->redirecionar('relatorios');
        }
        $serv = new RelatorioService($f);
        $filtros = $serv->normalizarFiltros($_GET);
        $volta = 'relatorios?' . http_build_query(['formulario' => $f['id']] + $filtros);
        Auditoria::registrar('EXPORTAR', 'formulario', (string) $f['id'], ['formato' => $formato]);

        if ($formato === 'csv') {
            ExportService::csv($f, $serv->linhasExportacao($filtros));
        }
        if ($formato === 'excel') {
            if (!ExportService::excelDisponivel()) {
                Session::flash('erro', 'A exportação para Excel precisa do PhpSpreadsheet. Rode "composer install" na pasta do gerenciador.');
                $this->redirecionar($volta);
            }
            ExportService::excel($f, $serv->gerar(['aba' => 'painel'] + $filtros), $serv->linhasExportacao($filtros), $serv->zonas->faixas());
        }
        if ($formato === 'pdf' || $formato === 'imprimir') {
            $rel = $serv->gerar(['aba' => 'export'] + $filtros);
            $rel['filtros'] = $filtros;
            $dados = ['form' => $f, 'serv' => $serv, 'rel' => $rel, 'usuario' => $u, 'paraPdf' => $formato === 'pdf'];
            if ($formato === 'pdf' && ExportService::pdfDisponivel()) {
                ob_start();
                View::renderizar('relatorios/imprimir', $dados, '');
                ExportService::pdf($f, (string) ob_get_clean());
            }
            // Sem Dompdf: abre a versão para impressão (o navegador salva em PDF)
            $dados['paraPdf'] = false;
            $dados['avisoPdf'] = $formato === 'pdf';
            View::renderizar('relatorios/imprimir', $dados, '');
            return;
        }
        $this->redirecionar($volta);
    }
}
