<?php
// Copie para config/config.php e ajuste. O config.php NÃO vai para o controle de versão.
return [
    'app' => [
        // Caminho em que o gerenciador é servido. Ex.: '' (raiz) ou '/pulse' (subpasta do XAMPP/IIS).
        'base_url' => '',
        // Mostra detalhes de erro na tela. Mantenha false em uso normal.
        'debug' => false,
        // Minutos sem uso até a sessão expirar
        'sessao_minutos' => 30,
    ],
    'db' => [
        'host' => '127.0.0.1',
        'port' => 3306,
        'nome' => 'pulse',
        'usuario' => 'pulse_app',
        'senha' => '',
    ],
    // Pasta das imagens de capa enviadas no construtor (o sincronizador lê daqui)
    'uploads_dir' => __DIR__ . '/../storage/uploads',
    'logs_dir' => __DIR__ . '/../storage/logs',
];
