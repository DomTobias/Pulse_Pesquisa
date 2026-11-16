<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\App;

/** Imagens de capa: até 1,5 MB, PNG/JPG/WEBP/SVG, gravadas com o hash SHA-256 como nome. */
final class UploadService
{
    public const LIMITE = 1_572_864;
    private const TIPOS = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp', 'image/svg+xml' => 'svg'];

    public static function pasta(): string
    {
        $dir = (string) App::config('uploads_dir', App::raiz() . '/storage/uploads');
        if (!is_dir($dir)) {
            mkdir($dir, 0770, true);
        }
        return realpath($dir) ?: $dir;
    }

    /** @return array{ok:bool, ref?:string, url?:string, mensagem?:string} */
    public static function salvar(array $arquivo): array
    {
        if (($arquivo['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file($arquivo['tmp_name'] ?? '')) {
            $erro = ($arquivo['error'] ?? 0) === UPLOAD_ERR_INI_SIZE || ($arquivo['error'] ?? 0) === UPLOAD_ERR_FORM_SIZE
                ? 'A imagem passa do limite de 1,5 MB.' : 'Não foi possível receber a imagem.';
            return ['ok' => false, 'mensagem' => $erro];
        }
        if ((int) $arquivo['size'] > self::LIMITE) {
            return ['ok' => false, 'mensagem' => 'A imagem passa do limite de 1,5 MB. Reduza o tamanho e tente de novo.'];
        }
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($arquivo['tmp_name']) ?: '';
        if ($mime === 'text/xml' || $mime === 'text/plain') {
            $mime = str_ends_with(strtolower((string) $arquivo['name']), '.svg') ? 'image/svg+xml' : $mime;
        }
        if (!isset(self::TIPOS[$mime])) {
            return ['ok' => false, 'mensagem' => 'Use uma imagem PNG, JPG, WEBP ou SVG.'];
        }
        $conteudo = (string) file_get_contents($arquivo['tmp_name']);
        if ($mime === 'image/svg+xml' && !self::svgSeguro($conteudo)) {
            return ['ok' => false, 'mensagem' => 'O SVG contém scripts ou referências externas e não pode ser usado.'];
        }
        if ($mime !== 'image/svg+xml' && @getimagesizefromstring($conteudo) === false) {
            return ['ok' => false, 'mensagem' => 'O arquivo não é uma imagem válida.'];
        }
        $nome = hash('sha256', $conteudo) . '.' . self::TIPOS[$mime];
        $destino = self::pasta() . DIRECTORY_SEPARATOR . $nome;
        if (!is_file($destino) && !move_uploaded_file($arquivo['tmp_name'], $destino)) {
            return ['ok' => false, 'mensagem' => 'Não foi possível gravar a imagem.'];
        }
        return ['ok' => true, 'ref' => 'local:' . $nome, 'url' => url('uploads/' . $nome)];
    }

    /**
     * Recusa SVG com conteúdo ativo ou referências externas. Lista de bloqueio deliberadamente ampla:
     * scripts, eventos (on*=), entidades numéricas (escondem "javascript:"), DOCTYPE/ENTITY (XXE),
     * elementos que embutem conteúdo, animações que trocam atributos, links que não sejam âncoras
     * locais (#id) e CSS que carrega recursos (url(...), @import).
     */
    public static function svgSeguro(string $svg): bool
    {
        $proibidos = [
            '/<\s*script/i',
            '/\bon[a-z]+\s*=/i',
            '/javascript\s*:/i',
            '/&#/',
            '/<!\s*(DOCTYPE|ENTITY)/i',
            '/<\s*(foreignObject|iframe|embed|object|handler|listener)\b/i',
            '/<\s*(set|animate|animateTransform|animateMotion)\b/i',
            '/\bhref\s*=\s*(?!["\']?#)/i',
            '/url\s*\(\s*(?!["\']?#)/i',
            '/@import/i',
        ];
        foreach ($proibidos as $p) {
            if (preg_match($p, $svg)) {
                return false;
            }
        }
        return true;
    }

    /** Caminho do arquivo pedido ou null (nome validado; impede sair da pasta). */
    public static function caminho(string $nome): ?string
    {
        if (!preg_match('/^[a-f0-9]{64}\.(png|jpg|jpeg|webp|svg)$/', $nome)) {
            return null;
        }
        $p = self::pasta() . DIRECTORY_SEPARATOR . $nome;
        return is_file($p) ? $p : null;
    }
}
