<?php
declare(strict_types=1);

use App\Services\UploadService;

// SVG enviado na capa: aceita desenhos simples, recusa qualquer conteúdo ativo ou externo.
$bom = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10"><defs><linearGradient id="g"/></defs>'
    . '<rect width="10" height="10" fill="url(#g)"/><use href="#g"/><text>P&amp;D</text></svg>';
afirmar(UploadService::svgSeguro($bom), 'SVG simples (gradiente, âncora local, &amp;) é aceito');

$ruins = [
    'script' => '<svg><script>alert(1)</script></svg>',
    'evento' => '<svg onload="alert(1)"></svg>',
    'javascript:' => '<svg><a href="javascript:alert(1)">x</a></svg>',
    'entidade numérica' => '<svg><a href="&#106;avascript:alert(1)">x</a></svg>',
    'DOCTYPE/ENTITY' => '<!DOCTYPE svg [<!ENTITY x SYSTEM "file:///c:/windows/win.ini">]><svg>&x;</svg>',
    'foreignObject' => '<svg><foreignObject><div>x</div></foreignObject></svg>',
    'iframe' => '<svg><iframe src="https://x"></iframe></svg>',
    'animação de atributo' => '<svg><a><set attributeName="href" to="javascript:alert(1)"/></a></svg>',
    'link externo' => '<svg><image href="https://rastreador.com/p.png"/></svg>',
    'xlink externo' => '<svg><image xlink:href="data:image/png;base64,AAA"/></svg>',
    'CSS url externa' => '<svg><style>rect{fill:url(https://x/y)}</style></svg>',
    '@import' => '<svg><style>@import "https://x/y.css";</style></svg>',
];
foreach ($ruins as $nome => $svg) {
    afirmar(!UploadService::svgSeguro($svg), "SVG recusado: $nome");
}

// Nome de arquivo pedido em /uploads/{arquivo}: só <sha256>.<ext>, sem sair da pasta.
foreach (['../config/config.php', '..\\..\\x.png', str_repeat('a', 64) . '.php', 'abc.png'] as $nome) {
    afirmar(UploadService::caminho($nome) === null, "upload recusa o nome: $nome");
}
