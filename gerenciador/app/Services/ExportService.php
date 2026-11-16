<?php
declare(strict_types=1);

namespace App\Services;

/** Exportações do relatório: CSV (nativo), Excel (PhpSpreadsheet) e PDF (Dompdf). */
final class ExportService
{
    public static function nomeArquivo(array $form, string $ext): string
    {
        return 'pulse-' . (slugify($form['titulo']) ?: 'relatorio') . '-' . gmdate('Ymd-His') . '.' . $ext;
    }

    /** Evita injeção de fórmulas ao abrir no Excel. */
    public static function celula(mixed $v): mixed
    {
        if (is_string($v) && $v !== '' && in_array($v[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
            return "'" . $v;
        }
        return $v;
    }

    public static function csv(array $form, array $dados): never
    {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . self::nomeArquivo($form, 'csv') . '"');
        header('Cache-Control: no-store');
        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF"); // BOM para o Excel reconhecer UTF-8
        fputcsv($out, $dados['cabecalho'], ';', '"', '');
        foreach ($dados['linhas'] as $l) {
            fputcsv($out, array_map([self::class, 'celula'], $l), ';', '"', '');
        }
        fclose($out);
        exit;
    }

    public static function excelDisponivel(): bool
    {
        return class_exists(\PhpOffice\PhpSpreadsheet\Spreadsheet::class);
    }

    public static function pdfDisponivel(): bool
    {
        return class_exists(\Dompdf\Dompdf::class);
    }

    public static function excel(array $form, array $rel, array $dados, array $zonas): never
    {
        $planilha = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $resumo = $planilha->getActiveSheet();
        $resumo->setTitle('Resumo');
        $linhas = [
            [$form['titulo']],
            ['Período', $rel['periodo_rotulo']],
            ['Respostas', $rel['total']],
            ['Índice geral (%)', $rel['geral'] === null ? '' : \App\Services\Indicadores\Indice::percentual($rel['geral'])],
            ['Adesão (%)', $rel['adesao'] ?? ''],
            [],
        ];
        $bloco = static function (string $titulo, array $itens) use (&$linhas) {
            $linhas[] = [$titulo, 'Índice (%)', 'Respostas'];
            foreach ($itens as $i) {
                $linhas[] = [self::celula(($i['grupo'] ?? '') ? $i['grupo'] . ' · ' . $i['rotulo'] : $i['rotulo']),
                    $i['suprimido'] ? 'Respostas insuficientes' : (\App\Services\Indicadores\Indice::percentual($i['valor']) ?? ''), $i['n']];
            }
            $linhas[] = [];
        };
        if ($rel['por_area']) {
            $bloco('Por área', $rel['por_area']);
        }
        if ($rel['por_gestor']) {
            $bloco('Por gestor', $rel['por_gestor']);
        }
        $bloco('Por tópico', $rel['por_topico']);
        $bloco('Por pergunta', $rel['por_pergunta']);
        $linhas[] = ['Zonas de pontuação'];
        foreach ($zonas as $z) {
            $linhas[] = [$z['nome'], 'até ' . number_format($z['limite_superior'], 2, ',', '')];
        }
        $resumo->fromArray($linhas, null, 'A1');
        $resumo->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $resumo->getColumnDimension('A')->setWidth(60);
        $resumo->getColumnDimension('B')->setWidth(18);
        $resumo->getColumnDimension('C')->setWidth(12);

        $base = $planilha->createSheet();
        $base->setTitle('Respostas');
        if ($dados['cabecalho']) {
            $base->fromArray($dados['cabecalho'], null, 'A1');
            $base->fromArray(array_map(static fn ($l) => array_map([self::class, 'celula'], $l), $dados['linhas']), null, 'A2', true);
            $base->getStyle('1:1')->getFont()->setBold(true);
            $base->freezePane('A2');
        } else {
            $base->setCellValue('A1', $dados['suprimido'] ? 'Respostas insuficientes para preservar o anonimato.' : 'Sem respostas no recorte.');
        }

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . self::nomeArquivo($form, 'xlsx') . '"');
        header('Cache-Control: no-store');
        (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($planilha))->save('php://output');
        exit;
    }

    public static function pdf(array $form, string $html): never
    {
        $opcoes = new \Dompdf\Options();
        $opcoes->set('isRemoteEnabled', false);
        $opcoes->set('defaultFont', 'DejaVu Sans');
        $dompdf = new \Dompdf\Dompdf($opcoes);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . self::nomeArquivo($form, 'pdf') . '"');
        header('Cache-Control: no-store');
        echo $dompdf->output();
        exit;
    }
}
