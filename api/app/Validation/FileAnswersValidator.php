<?php

declare(strict_types=1);

namespace App\Validation;

use App\Data\AcceptedFile;
use App\Data\FileValidationResult;
use App\Enums\FieldType;
use App\Enums\FileCategory;
use finfo;
use Illuminate\Http\UploadedFile;
use Throwable;

/**
 * Valida os arquivos enviados para os campos `file` do schema publicado.
 *
 * O tipo é o MIME **real**, detectado pelo conteúdo (finfo), nunca a extensão
 * nem o Content-Type informados pelo cliente. Sem antivírus (D8), só entram as
 * categorias fechadas de FileCategory: nada executável, HTML ou SVG.
 */
final class FileAnswersValidator
{
    public const ERROR_REQUIRED = 'O campo :label é obrigatório.';

    public const ERROR_TOO_MANY = 'Envie no máximo :max arquivo(s) em ":label".';

    public const ERROR_TOO_LARGE = 'O arquivo ":name" passa do limite de :max MB.';

    public const ERROR_WRONG_TYPE = 'O arquivo ":name" não é de um tipo aceito. Envie: :types.';

    public const ERROR_UPLOAD_FAILED = 'Não foi possível receber o arquivo ":name". Tente enviar de novo.';

    public const ERROR_TOTAL_TOO_LARGE = 'Os arquivos enviados somam mais de :max MB.';

    public const TOTAL_ERROR_KEY = 'files';

    private const BYTES_PER_MB = 1024 * 1024;

    private const DISPLAY_NAME_MAX_LENGTH = 120;

    private const FALLBACK_NAME = 'arquivo';

    /**
     * @param  array{fields: list<array<string, mixed>>}  $schema
     * @param  array<string, mixed>  $files  arquivos enviados por id de campo (um ou uma lista)
     * @param  array<string, int>  $existingCounts  arquivos já guardados por campo (edição)
     */
    public function validate(array $schema, array $files, array $existingCounts = []): FileValidationResult
    {
        $accepted = [];
        $errors = [];
        $totalBytes = 0;

        foreach ($schema['fields'] as $field) {
            if ($field['type'] !== FieldType::File->value) {
                continue;
            }

            $id = (string) $field['id'];
            $uploads = $this->uploadsOf($files[$id] ?? null);
            $totalBytes += array_sum(array_map(fn (UploadedFile $file): int => (int) $file->getSize(), $uploads));

            $result = $this->validateField($field, $uploads, $existingCounts[$id] ?? 0);

            if (is_string($result)) {
                $errors[$id] = [$result];
            } elseif ($result !== []) {
                $accepted[$id] = $result;
            }
        }

        $maxTotalMb = (int) config('submissions.max_total_upload_mb');

        if ($totalBytes > $maxTotalMb * self::BYTES_PER_MB) {
            $errors[self::TOTAL_ERROR_KEY] = [strtr(self::ERROR_TOTAL_TOO_LARGE, [':max' => (string) $maxTotalMb])];
        }

        return new FileValidationResult($errors === [] ? $accepted : [], $errors);
    }

    /**
     * @param  array<string, mixed>  $field
     * @param  list<UploadedFile>  $uploads
     * @return list<AcceptedFile>|string arquivos aceitos ou a primeira mensagem de erro
     */
    private function validateField(array $field, array $uploads, int $existingCount): array|string
    {
        $label = (string) $field['label'];
        $settings = (array) $field['file'];
        $maxFiles = (int) $settings['max_files'];

        if ($uploads === []) {
            $missing = ($field['required'] ?? false) && $existingCount === 0;

            return $missing ? strtr(self::ERROR_REQUIRED, [':label' => $label]) : [];
        }

        if (count($uploads) > $maxFiles) {
            return strtr(self::ERROR_TOO_MANY, [':max' => (string) $maxFiles, ':label' => $label]);
        }

        $categories = array_values(array_map(fn (string $value): FileCategory => FileCategory::from($value), (array) $settings['accept']));
        $accepted = [];

        foreach ($uploads as $upload) {
            $result = $this->acceptFile((string) $field['id'], $upload, $categories, (int) $settings['max_size_mb']);

            if (is_string($result)) {
                return $result;
            }

            $accepted[] = $result;
        }

        return $accepted;
    }

    /**
     * @param  list<FileCategory>  $categories
     */
    private function acceptFile(string $fieldId, UploadedFile $upload, array $categories, int $maxSizeMb): AcceptedFile|string
    {
        $name = $this->shortName($upload->getClientOriginalName());

        if (! $upload->isValid()) {
            return strtr(self::ERROR_UPLOAD_FAILED, [':name' => $name]);
        }

        if ((int) $upload->getSize() > $maxSizeMb * self::BYTES_PER_MB) {
            return strtr(self::ERROR_TOO_LARGE, [':name' => $name, ':max' => (string) $maxSizeMb]);
        }

        $allowed = array_merge(...array_map(fn (FileCategory $category): array => $category->mimeTypes(), $categories));
        $mimeType = $this->detectMimeType($upload, $allowed);

        if (! in_array($mimeType, $allowed, strict: true)) {
            $types = implode(', ', array_map(fn (FileCategory $category): string => $category->label(), $categories));

            return strtr(self::ERROR_WRONG_TYPE, [':name' => $name, ':types' => $types]);
        }

        $extension = FileCategory::extensionFor($mimeType);

        return new AcceptedFile($upload, $fieldId, $mimeType, $extension, $this->displayName($upload->getClientOriginalName(), $extension));
    }

    /**
     * MIME pelo conteúdo, com finfo direto no arquivo: não usa getMimeType(), que
     * pode ser sobrescrito (o arquivo falso dos testes, por exemplo, responde pela
     * extensão). Exceção única: CSV não tem assinatura binária e o finfo costuma
     * dizer "text/plain"; aceita como CSV se a extensão for .csv e CSV for permitido.
     *
     * @param  list<string>  $allowed
     */
    private function detectMimeType(UploadedFile $upload, array $allowed): string
    {
        $detected = $this->sniff($upload);

        $looksLikeCsv = $detected === 'text/plain'
            && mb_strtolower($upload->getClientOriginalExtension()) === 'csv'
            && in_array('text/csv', $allowed, strict: true);

        return $looksLikeCsv ? 'text/csv' : $detected;
    }

    /**
     * Arquivo que não dá para ler (ex.: bloqueado pelo antivírus do servidor) fica
     * sem tipo e é recusado como "tipo não aceito", nunca derruba o envio com 500.
     */
    private function sniff(UploadedFile $upload): string
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

    /**
     * Nome para exibir e baixar: sem caminho, sem controle, sem a extensão do
     * cliente (que pode enganar, ex.: "nota.pdf.exe") e com a extensão real.
     */
    private function displayName(string $original, string $extension): string
    {
        $base = pathinfo(str_replace('\\', '/', $original), PATHINFO_FILENAME);
        $base = (string) preg_replace('/[\x00-\x1F\x7F\/\\\\:*?"<>|]+/u', ' ', $base);
        $base = trim((string) preg_replace('/\s+/u', ' ', $base), ' .');
        $base = mb_substr($base, 0, self::DISPLAY_NAME_MAX_LENGTH);

        return ($base !== '' ? $base : self::FALLBACK_NAME).'.'.$extension;
    }

    private function shortName(string $original): string
    {
        $name = trim((string) preg_replace('/[\x00-\x1F\x7F]+/u', '', basename(str_replace('\\', '/', $original))));

        return mb_substr($name !== '' ? $name : self::FALLBACK_NAME, 0, 60);
    }

    /**
     * @return list<UploadedFile>
     */
    private function uploadsOf(mixed $value): array
    {
        $values = is_array($value) ? $value : [$value];

        return array_values(array_filter($values, fn (mixed $item): bool => $item instanceof UploadedFile));
    }
}
