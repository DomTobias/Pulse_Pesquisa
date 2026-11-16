<?php
declare(strict_types=1);

namespace App\Services\Indicadores;

/** Grupo mínimo: recortes com menos respostas que o limite não exibem índice nem detalhe. */
final class Anonimato
{
    public const MENSAGEM = 'Respostas insuficientes para preservar o anonimato.';

    public static function suprimir(int $respostas, int $limite): bool
    {
        return $limite > 1 && $respostas > 0 && $respostas < $limite;
    }
}
