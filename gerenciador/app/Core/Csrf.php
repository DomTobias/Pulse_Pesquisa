<?php
declare(strict_types=1);

namespace App\Core;

final class Csrf
{
    public static function token(): string
    {
        $t = Session::get('_csrf');
        if (!is_string($t) || strlen($t) !== 64) {
            $t = bin2hex(random_bytes(32));
            Session::set('_csrf', $t);
        }
        return $t;
    }

    /** Aceita o token no campo _csrf ou no cabeçalho X-CSRF-Token (requisições JSON). */
    public static function valido(): bool
    {
        $enviado = $_POST['_csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
        $t = Session::get('_csrf');
        return is_string($enviado) && is_string($t) && $t !== '' && hash_equals($t, $enviado);
    }
}
