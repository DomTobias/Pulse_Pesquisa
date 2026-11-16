<?php
declare(strict_types=1);

namespace App\Services\Indicadores;

/**
 * Adesão = respostas ÷ (colaboradores × ciclos) × 100.
 * Não é calculada com filtro de gestor (não há número de colaboradores por gestor).
 */
final class Adesao
{
    public static function calcular(int $respostas, int $colaboradores, int $ciclos, bool $filtroGestor = false): ?int
    {
        if ($filtroGestor || $colaboradores <= 0 || $ciclos <= 0) {
            return null;
        }
        return (int) round($respostas / ($colaboradores * $ciclos) * 100, 0, PHP_ROUND_HALF_UP);
    }
}
