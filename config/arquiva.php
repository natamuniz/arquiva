<?php

declare(strict_types=1);

return [
    // Fuso usado para interpretar datas sem hora (ex.: ?at=2025-01-10). O banco guarda tudo em UTC.
    'timezone' => env('ARQUIVA_TIMEZONE', 'America/Sao_Paulo'),

    // Validade do token de acesso, em minutos.
    'token_ttl_minutes' => (int) env('ARQUIVA_TOKEN_TTL_MINUTES', 60 * 24),

    // Tentativas de login com erro, por e-mail + IP, por minuto.
    'login_max_attempts' => (int) env('ARQUIVA_LOGIN_MAX_ATTEMPTS', 5),

    'uploads' => [
        'disk' => env('ARQUIVA_UPLOAD_DISK', 'local'),
        'max_kilobytes' => 50 * 1024,
        // Extensão => tipos MIME aceitos para o conteúdo real (verificado no job).
        'types' => [
            'pdf' => ['application/pdf'],
            'png' => ['image/png'],
            'jpg' => ['image/jpeg'],
            'jpeg' => ['image/jpeg'],
            'tif' => ['image/tiff'],
            'tiff' => ['image/tiff'],
        ],
    ],

    'fixity' => [
        'batch_size' => (int) env('ARQUIVA_FIXITY_BATCH_SIZE', 100),
    ],

    // Arquivos no disco sem registro, mais antigos que isto, são removidos por arquiva:prune-orphans.
    'orphan_grace_hours' => 24,
];
