<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\App;
use App\Core\Controller;
use App\Core\Database;
use App\Models\Formulario;
use App\Models\Sincronizacao;
use App\Services\RelatorioService;

final class DashboardController extends Controller
{
    public function index(): void
    {
        $u = $this->exigirLogin();
        $forms = Formulario::listar();
        $cont = array_count_values(array_column($forms, 'status'));
        $total = (int) Database::valor('SELECT COUNT(*) FROM respostas r JOIN formularios f ON f.id = r.formulario_id WHERE f.excluido_em IS NULL');
        $ultimos30 = (int) Database::valor('SELECT COUNT(*) FROM respostas WHERE recebido_em >= UTC_TIMESTAMP() - INTERVAL 30 DAY');

        // Mês corrente no fuso configurado
        $fuso = App::fuso();
        $iniMes = (new \DateTimeImmutable('first day of this month 00:00', $fuso))->setTimezone(new \DateTimeZone('UTC'));
        $iniAnt = (new \DateTimeImmutable('first day of last month 00:00', $fuso))->setTimezone(new \DateTimeZone('UTC'));
        $mes = (int) Database::valor('SELECT COUNT(*) FROM respostas WHERE recebido_em >= ?', [$iniMes->format('Y-m-d H:i:s')]);
        $mesAnt = (int) Database::valor('SELECT COUNT(*) FROM respostas WHERE recebido_em >= ? AND recebido_em < ?', [$iniAnt->format('Y-m-d H:i:s'), $iniMes->format('Y-m-d H:i:s')]);

        // Pesquisa recorrente em destaque (a primeira publicada)
        $destaque = null;
        $resumo = null;
        foreach ($forms as $f) {
            if ($f['recorrencia'] !== 'NENHUMA' && $f['status'] === 'PUBLICADO') {
                $destaque = Formulario::buscar((int) $f['id']);
                break;
            }
        }
        $serv = null;
        if ($destaque) {
            $serv = new RelatorioService($destaque);
            $resumo = $serv->resumoCiclos();
        }

        // Série diária dos últimos 30 dias (quando não há pesquisa recorrente)
        $serie = [];
        if (!$resumo) {
            $porDia = [];
            foreach (Database::todos('SELECT recebido_em FROM respostas WHERE recebido_em >= UTC_TIMESTAMP() - INTERVAL 31 DAY') as $r) {
                $d = local_dt($r['recebido_em'], 'Y-m-d');
                $porDia[$d] = ($porDia[$d] ?? 0) + 1;
            }
            for ($i = 29; $i >= 0; $i--) {
                $d = (new \DateTimeImmutable("-$i days", $fuso))->format('Y-m-d');
                $serie[] = ['rotulo' => date('d/m', strtotime($d)), 'valor' => $porDia[$d] ?? 0];
            }
        }

        $status = Sincronizacao::status();
        $this->view('dashboard/index', [
            'titulo' => 'Visão geral', 'usuario' => $u, 'forms' => $forms, 'cont' => $cont,
            'total' => $total, 'ultimos30' => $ultimos30, 'mes' => $mes, 'mesAnt' => $mesAnt,
            'diasMes' => (int) (new \DateTimeImmutable('now', $fuso))->format('j'),
            'nomeMes' => ['', 'janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho', 'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro'][(int) (new \DateTimeImmutable('now', $fuso))->format('n')],
            'destaque' => $destaque, 'resumo' => $resumo, 'zonas' => $serv?->zonas, 'serie' => $serie,
            'syncAtivo' => Sincronizacao::servicoAtivo($status), 'status' => $status,
        ]);
    }
}
