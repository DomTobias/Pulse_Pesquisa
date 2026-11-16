<?php
declare(strict_types=1);

namespace App\Services\Indicadores;

use DateTimeImmutable;
use DateTimeZone;

/**
 * Ciclos de pesquisas recorrentes (mesma regra do Worker):
 * o ciclo N começa em início + (N − 1) × duração e termina um instante antes do próximo.
 * SEMANAL = 7 dias, QUINZENAL = 14, MENSAL = 30.
 */
final class Ciclos
{
    public const DURACAO_DIAS = ['NENHUMA' => 0, 'SEMANAL' => 7, 'QUINZENAL' => 14, 'MENSAL' => 30];

    public static function duracaoSegundos(string $recorrencia): int
    {
        return (self::DURACAO_DIAS[$recorrencia] ?? 0) * 86400;
    }

    public static function recorrente(string $recorrencia): bool
    {
        return self::duracaoSegundos($recorrencia) > 0;
    }

    /** Número do ciclo de um instante (timestamp UTC) ou null fora do período. */
    public static function numeroEm(int $inicioTs, string $recorrencia, int $ts): ?int
    {
        $dur = self::duracaoSegundos($recorrencia);
        if (!$dur || $ts < $inicioTs) {
            return null;
        }
        return intdiv($ts - $inicioTs, $dur) + 1;
    }

    /**
     * Lista os ciclos do início até o instante $ate (inclusive o ciclo em andamento).
     * @return list<array{numero:int,inicio_ts:int,fim_ts:int,inicio:string,fim:string,em_andamento:bool}>
     */
    public static function listar(?int $inicioTs, string $recorrencia, int $ate, ?int $fimTs = null, ?DateTimeZone $fuso = null, ?int $agora = null): array
    {
        $dur = self::duracaoSegundos($recorrencia);
        if (!$dur || $inicioTs === null) {
            return [];
        }
        $limite = $fimTs !== null ? min($ate, $fimTs) : $ate;
        $ultimo = self::numeroEm($inicioTs, $recorrencia, $limite);
        if ($ultimo === null) {
            return [];
        }
        $fuso ??= new DateTimeZone('America/Cuiaba');
        $agora ??= time();
        $lista = [];
        for ($n = 1; $n <= $ultimo; $n++) {
            $ini = $inicioTs + ($n - 1) * $dur;
            $fim = $ini + $dur - 1;
            $lista[] = [
                'numero' => $n,
                'inicio_ts' => $ini,
                'fim_ts' => $fim,
                'inicio' => (new DateTimeImmutable('@' . $ini))->setTimezone($fuso)->format('Y-m-d'),
                'fim' => (new DateTimeImmutable('@' . $fim))->setTimezone($fuso)->format('Y-m-d'),
                'em_andamento' => $agora >= $ini && $agora <= $fim,
            ];
        }
        return $lista;
    }

    /** Quantos ciclos do intervalo [de, ate] existem na lista (para a adesão). */
    public static function contarNoIntervalo(array $ciclos, int $de, int $ate): int
    {
        return count(array_filter($ciclos, static fn ($c) => $c['fim_ts'] >= $de && $c['inicio_ts'] <= $ate));
    }
}
