<?php
declare(strict_types=1);

namespace App\Services\Indicadores;

/**
 * Zonas de pontuação (faixas da planilha). Regra: a nota no limite pertence à zona de cima.
 * Padrão: < 5 crítica, < 7 melhoramento, < 8 aperfeiçoamento, ≤ 10 satisfatória.
 * A classificação usa a porcentagem arredondada, a mesma que aparece na tela
 * (49,6% aparece como 50% e fica na zona de melhoramento).
 */
final class Zonas
{
    public const PADRAO = [
        ['nome' => 'Zona crítica', 'limite_superior' => 5.0, 'cor' => '#D64545'],
        ['nome' => 'Zona de melhoramento', 'limite_superior' => 7.0, 'cor' => '#EE8B2A'],
        ['nome' => 'Zona de aperfeiçoamento', 'limite_superior' => 8.0, 'cor' => '#C9A414'],
        ['nome' => 'Zona satisfatória', 'limite_superior' => 10.0, 'cor' => '#2E9E5B'],
    ];

    /** @var list<array{nome:string,limite_superior:float,cor:string}> */
    private array $faixas;

    public function __construct(array $faixas = [])
    {
        $faixas = $faixas ?: self::PADRAO;
        usort($faixas, static fn ($a, $b) => (float) $a['limite_superior'] <=> (float) $b['limite_superior']);
        $this->faixas = array_map(static fn ($f) => [
            'nome' => (string) $f['nome'],
            'limite_superior' => (float) $f['limite_superior'],
            'cor' => (string) $f['cor'],
        ], $faixas);
    }

    /** @return list<array{nome:string,limite_superior:float,cor:string}> */
    public function faixas(): array
    {
        return $this->faixas;
    }

    /** Índice da faixa para uma nota 0–10. */
    public function indiceDe(float $nota10): int
    {
        $pct = Indice::percentual($nota10);
        $ultimo = count($this->faixas) - 1;
        foreach ($this->faixas as $i => $f) {
            if ($i < $ultimo && $pct < (int) round($f['limite_superior'] * 10)) {
                return $i;
            }
        }
        return $ultimo;
    }

    /** @return array{nome:string,limite_superior:float,cor:string}|null */
    public function zonaDe(?float $nota10): ?array
    {
        return $nota10 === null ? null : $this->faixas[$this->indiceDe($nota10)];
    }

    /** Texto do intervalo de cada faixa, ex.: "0 a 4,99 (0% a 49%)" */
    public function descricao(int $i): string
    {
        $ini = $i === 0 ? 0.0 : $this->faixas[$i - 1]['limite_superior'];
        $fim = $this->faixas[$i]['limite_superior'];
        $ultimo = $i === count($this->faixas) - 1;
        $fimNota = $ultimo ? $fim : $fim - 0.01;
        return sprintf('Notas de %s a %s (%d%% a %d%%)',
            number_format($ini, $ini == (int) $ini ? 0 : 2, ',', ''),
            number_format($fimNota, $fimNota == (int) $fimNota ? 0 : 2, ',', ''),
            (int) round($ini * 10), $ultimo ? (int) round($fim * 10) : (int) round($fim * 10) - 1);
    }
}
