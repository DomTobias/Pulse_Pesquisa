<?php
declare(strict_types=1);

namespace App\Services\Indicadores;

/**
 * Índice do Pulse (seção 5): média das notas de escala normalizadas para 0–10,
 * apresentada em porcentagem (× 10). Este é o ÚNICO lugar em que a conta é feita.
 */
final class Indice
{
    /** Normaliza uma nota da escala [min, max] para 0–10: (n − min) / (max − min) × 10. */
    public static function normalizar(float $nota, float $min = 0.0, float $max = 10.0): float
    {
        if ($max <= $min) {
            throw new \InvalidArgumentException('Escala inválida: máximo deve ser maior que o mínimo.');
        }
        $n = max($min, min($max, $nota));
        return ($n - $min) / ($max - $min) * 10.0;
    }

    /**
     * Média das notas normalizadas.
     * @param iterable<array{0:float|int|string,1?:float|int|string|null,2?:float|int|string|null}> $notas [nota, min, max]
     * @return float|null nota de 0 a 10, ou null sem notas
     */
    public static function media(iterable $notas): ?float
    {
        $soma = 0.0;
        $n = 0;
        foreach ($notas as $item) {
            $min = isset($item[1]) && $item[1] !== null ? (float) $item[1] : 0.0;
            $max = isset($item[2]) && $item[2] !== null ? (float) $item[2] : 10.0;
            $soma += self::normalizar((float) $item[0], $min, $max);
            $n++;
        }
        return $n ? $soma / $n : null;
    }

    /** Nota 0–10 → porcentagem inteira (7,43 → 74). */
    public static function percentual(?float $nota10): ?int
    {
        return $nota10 === null ? null : (int) round($nota10 * 10, 0, PHP_ROUND_HALF_UP);
    }

    /** Variação em pontos percentuais: diferença entre as porcentagens já arredondadas. */
    public static function variacao(?float $atual, ?float $anterior): ?int
    {
        if ($atual === null || $anterior === null) {
            return null;
        }
        return self::percentual($atual) - self::percentual($anterior);
    }
}
