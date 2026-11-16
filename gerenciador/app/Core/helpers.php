<?php
declare(strict_types=1);

use App\Core\App;
use App\Core\Csrf;

/** Escapa para HTML. */
function e(mixed $v): string
{
    return htmlspecialchars((string) ($v ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function url(string $caminho = ''): string
{
    return App::baseUrl() . '/' . ltrim($caminho, '/');
}

function asset(string $caminho): string
{
    $arquivo = App::raiz() . '/public/assets/' . ltrim($caminho, '/');
    $v = is_file($arquivo) ? substr(md5((string) filemtime($arquivo)), 0, 8) : '1';
    return url('assets/' . ltrim($caminho, '/')) . '?v=' . $v;
}

function csrf_campo(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(Csrf::token()) . '">';
}

function uuid4(): string
{
    $b = random_bytes(16);
    $b[6] = chr((ord($b[6]) & 0x0f) | 0x40);
    $b[8] = chr((ord($b[8]) & 0x3f) | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($b), 4));
}

function fmt_num(float|int|null $n, int $casas = 0): string
{
    return $n === null ? '—' : number_format((float) $n, $casas, ',', '.');
}

/** 7.43 (escala 0–10) → "74%" */
function fmt_pct(?float $nota10): string
{
    return $nota10 === null ? '—' : ((string) (int) round($nota10 * 10)) . '%';
}

/** Converte DATETIME UTC do MySQL para o fuso configurado. */
function local_dt(?string $utc, string $formato = 'd/m/Y H:i'): string
{
    if (!$utc) {
        return '—';
    }
    $d = new DateTimeImmutable($utc, new DateTimeZone('UTC'));
    return $d->setTimezone(App::fuso())->format($formato);
}

/** Converte "AAAA-MM-DDTHH:MM" do fuso configurado para DATETIME UTC. */
function utc_de_local(?string $local): ?string
{
    $local = trim((string) $local);
    if ($local === '') {
        return null;
    }
    $d = DateTimeImmutable::createFromFormat('Y-m-d\TH:i', $local, App::fuso())
        ?: DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $local, App::fuso());
    if (!$d) {
        return null;
    }
    return $d->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
}

function para_input_local(?string $utc): string
{
    return $utc ? local_dt($utc, 'Y-m-d\TH:i') : '';
}

/** ISO 8601 em UTC (contratos JSON). */
function iso_utc(?string $utc): ?string
{
    return $utc ? (new DateTimeImmutable($utc, new DateTimeZone('UTC')))->format('Y-m-d\TH:i:s\Z') : null;
}

function slugify(string $t): string
{
    $t = mb_strtolower(trim($t), 'UTF-8');
    $t = strtr($t, ['á' => 'a', 'à' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a', 'é' => 'e', 'ê' => 'e', 'è' => 'e', 'ë' => 'e',
        'í' => 'i', 'î' => 'i', 'ì' => 'i', 'ï' => 'i', 'ó' => 'o', 'ô' => 'o', 'õ' => 'o', 'ò' => 'o', 'ö' => 'o',
        'ú' => 'u', 'û' => 'u', 'ù' => 'u', 'ü' => 'u', 'ç' => 'c', 'ñ' => 'n']);
    $t = preg_replace('/[^a-z0-9]+/', '-', $t) ?? '';
    return trim(substr(trim($t, '-'), 0, 70), '-');
}

function icone(string $nome, string $classe = ''): string
{
    return '<i class="bi bi-' . e($nome) . ($classe ? ' ' . e($classe) : '') . '" aria-hidden="true"></i>';
}
