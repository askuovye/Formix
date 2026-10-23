<?php

declare(strict_types=1);

namespace App\Services\Submissions;

use App\Models\Form;
use App\Models\FormVersion;
use Illuminate\Contracts\Cache\Repository as Cache;

/**
 * Cache do conteúdo das versões publicadas.
 *
 * A chave é (formulário, versão) e uma versão é imutável, então o cache nunca
 * fica desatualizado e não precisa de invalidação: publicar cria uma versão
 * (chave) nova, e status/configurações vêm sempre da linha do formulário.
 */
final class PublishedFormVersions
{
    private const TTL_SECONDS = 86_400;

    public function __construct(private readonly Cache $cache) {}

    /**
     * @return array{version: int, title: string, description: string|null, schema: array{fields: list<array<string, mixed>>}}
     */
    public function get(Form $form, ?int $version = null): array
    {
        $version ??= $form->schema_version;

        return $this->cache->remember(
            "form-version:{$form->id}:{$version}",
            self::TTL_SECONDS,
            function () use ($form, $version): array {
                $snapshot = FormVersion::query()
                    ->where('form_id', $form->id)
                    ->where('version', $version)
                    ->firstOrFail();

                return [
                    'version' => $snapshot->version,
                    'title' => $snapshot->title,
                    'description' => $snapshot->description,
                    'schema' => $snapshot->schema,
                ];
            },
        );
    }
}
