<?php
declare(strict_types=1);

use App\Services\Indicadores\Adesao;
use App\Services\Indicadores\Anonimato;
use App\Services\Indicadores\Ciclos;
use App\Services\Indicadores\Indice;
use App\Services\Indicadores\Zonas;

// --- Índice
igual(7.5, Indice::normalizar(3, 0, 4) , 'normaliza 3 numa escala 0–4 para 7,5');
igual(5.0, Indice::normalizar(3, 1, 5), 'normaliza 3 numa escala 1–5 para 5');
igual(10.0, Indice::normalizar(10), 'nota 10 na escala 0–10 continua 10');
$media = Indice::media([[8], [7], [9], [6]]);
igual(7.5, $media, 'média de 8, 7, 9 e 6 = 7,5');
igual(75, Indice::percentual($media), 'índice 7,5 = 75%');
igual(null, Indice::media([]), 'sem notas não há índice');
igual(74, Indice::percentual(7.43), '7,43 vira 74%');
igual(75, Indice::percentual(7.45), '7,45 vira 75% (arredonda para cima no meio)');
// variação usa as porcentagens já arredondadas: 74% − 71% = 3 p.p.
igual(3, Indice::variacao(7.43, 7.06), 'variação 74% − 71% = +3 p.p.');
igual(-2, Indice::variacao(6.84, 7.0), 'variação 68% − 70% = −2 p.p.');
igual(null, Indice::variacao(7.0, null), 'sem ciclo anterior não há variação');
$mista = Indice::media([[3, 1, 5], [8, 0, 10]]);
igual(65, Indice::percentual($mista), 'escalas diferentes: (5 + 8) / 2 = 6,5 → 65%');

// --- Zonas (nota no limite pertence à zona de cima)
$z = new Zonas();
igual('Zona crítica', $z->zonaDe(4.94)['nome'], '4,94 (49%) é crítica');
igual('Zona de melhoramento', $z->zonaDe(5.0)['nome'], '5,0 (50%) é melhoramento');
igual('Zona de melhoramento', $z->zonaDe(4.96)['nome'], '4,96 aparece como 50% e fica em melhoramento');
igual('Zona de aperfeiçoamento', $z->zonaDe(7.0)['nome'], '7,0 é aperfeiçoamento');
igual('Zona satisfatória', $z->zonaDe(8.0)['nome'], '8,0 é satisfatória');
igual('Zona satisfatória', $z->zonaDe(10.0)['nome'], '10 é satisfatória');
igual('Zona crítica', $z->zonaDe(0.0)['nome'], '0 é crítica');
igual('#D64545', $z->zonaDe(1.0)['cor'], 'cor da zona crítica');
igual(null, $z->zonaDe(null), 'sem índice não há zona');

// --- Ciclos (QUINZENAL = 14 dias)
$inicio = gmmktime(4, 0, 0, 11, 3, 2026); // 03/11/2026 00:00 em Cuiabá (UTC−4)
igual(1, Ciclos::numeroEm($inicio, 'QUINZENAL', $inicio), 'início do período é o ciclo 1');
igual(1, Ciclos::numeroEm($inicio, 'QUINZENAL', $inicio + 14 * 86400 - 1), 'último instante do 1º ciclo');
igual(2, Ciclos::numeroEm($inicio, 'QUINZENAL', $inicio + 14 * 86400), 'dia 15 começa o ciclo 2');
igual(3, Ciclos::numeroEm($inicio, 'SEMANAL', $inicio + 15 * 86400), 'semanal: dia 16 é o ciclo 3');
igual(2, Ciclos::numeroEm($inicio, 'MENSAL', $inicio + 30 * 86400), 'mensal: dia 31 é o ciclo 2');
igual(null, Ciclos::numeroEm($inicio, 'QUINZENAL', $inicio - 1), 'antes do início não há ciclo');
igual(null, Ciclos::numeroEm($inicio, 'NENHUMA', $inicio + 100), 'sem recorrência não há ciclo');
$lista = Ciclos::listar($inicio, 'QUINZENAL', $inicio + 30 * 86400, null, new DateTimeZone('America/Cuiaba'));
igual(3, count($lista), '30 dias de quinzenal = 3 ciclos (o 3º em andamento)');
igual('2026-11-03', $lista[0]['inicio'], 'ciclo 1 começa em 03/11');
igual('2026-11-16', $lista[0]['fim'], 'ciclo 1 termina em 16/11');
igual('2026-11-17', $lista[1]['inicio'], 'ciclo 2 começa em 17/11');
igual(2, Ciclos::contarNoIntervalo($lista, $inicio, $inicio + 20 * 86400), 'intervalo de 20 dias toca 2 ciclos');

// --- Adesão = respostas ÷ (colaboradores × ciclos)
igual(80, Adesao::calcular(96, 60, 2), '96 respostas, 60 colaboradores, 2 ciclos = 80%');
igual(null, Adesao::calcular(96, 60, 2, true), 'com filtro de gestor a adesão não é calculada');
igual(null, Adesao::calcular(10, 0, 1), 'sem colaboradores cadastrados não há adesão');

// --- Anonimato (grupo mínimo 3)
afirmar(Anonimato::suprimir(2, 3), '2 respostas com grupo mínimo 3 são suprimidas');
afirmar(!Anonimato::suprimir(3, 3), '3 respostas com grupo mínimo 3 aparecem');
afirmar(!Anonimato::suprimir(0, 3), 'zero respostas mostra "sem dados", não supressão');
