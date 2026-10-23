<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Respostas e anexos
|--------------------------------------------------------------------------
*/

return [

    // Soma máxima dos arquivos de um envio. Deve caber no `post_max_size` e no
    // `upload_max_filesize` do PHP (no hPanel: Avançado → Configuração do PHP).
    'max_total_upload_mb' => (int) env('SUBMISSION_MAX_TOTAL_UPLOAD_MB', 25),

    // Validade dos links assinados de download enviados por email ao dono.
    'attachment_link_ttl_days' => (int) env('ATTACHMENT_LINK_TTL_DAYS', 7),

    // Exportações CSV: disco privado, arquivo apagado depois de `export_ttl_hours`.
    'export_disk' => env('EXPORT_DISK', 'local'),
    'export_ttl_hours' => (int) env('EXPORT_TTL_HOURS', 24),
    // Validade de cada link de download gerado para uma exportação pronta.
    'export_link_ttl_minutes' => 60,

    // Respostas removidas por execução do Job de retenção (sem pcntl, Jobs devem ser curtos; D16).
    'retention_batch_size' => 500,

];
