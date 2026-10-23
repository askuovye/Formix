<?php

declare(strict_types=1);

namespace App\Services;

use finfo;
use Illuminate\Http\UploadedFile;
use Throwable;

/**
 * MIME real pelo conteúdo, com finfo direto no arquivo: não usa getMimeType(),
 * que pode ser sobrescrito (o arquivo falso dos testes responde pela extensão).
 * Arquivo que não dá para ler (ex.: bloqueado pelo antivírus do servidor) fica
 * sem tipo e é recusado por quem chama, nunca derruba a requisição com 500.
 */
final class FileTypeDetector
{
    public function detect(UploadedFile $upload): string
    {
        $path = $upload->getRealPath();

        if ($path === false) {
            return '';
        }

        try {
            return (string) (new finfo(FILEINFO_MIME_TYPE))->file($path);
        } catch (Throwable) {
            return '';
        }
    }
}
