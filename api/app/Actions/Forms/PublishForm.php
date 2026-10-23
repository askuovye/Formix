<?php

declare(strict_types=1);

namespace App\Actions\Forms;

use App\Enums\FormStatus;
use App\Exceptions\ApiException;
use App\Models\Form;
use App\Models\User;
use App\Services\FormSchema\FormConsistency;
use Illuminate\Support\Facades\DB;

/**
 * Publica o rascunho e também reabre formulários fechados (decisão D13).
 * Só gera versão nova quando o conteúdo mudou desde a última publicação.
 */
final class PublishForm
{
    public const EMAIL_NOT_VERIFIED_MESSAGE = 'Confirme seu email para publicar formulários.';

    public function __construct(private readonly FormConsistency $consistency) {}

    public function __invoke(Form $form, User $publisher): Form
    {
        if (! $publisher->hasVerifiedEmail()) {
            throw ApiException::forbidden(self::EMAIL_NOT_VERIFIED_MESSAGE);
        }

        return DB::transaction(function () use ($form): Form {
            $form = Form::query()->lockForUpdate()->findOrFail($form->id);

            $this->consistency->assertPublishable($form);

            if ($form->has_unpublished_changes || ! $form->hasBeenPublished()) {
                $this->createVersion($form);
            }

            $form->forceFill([
                'status' => FormStatus::Published,
                'closed_at' => null,
            ])->save();

            return $form;
        });
    }

    private function createVersion(Form $form): void
    {
        $version = $form->schema_version + 1;
        $publishedAt = now();

        $form->versions()->create([
            'version' => $version,
            'title' => $form->title,
            'description' => $form->description,
            'schema' => $form->schema,
            'published_at' => $publishedAt,
        ]);

        $form->forceFill([
            'schema_version' => $version,
            'has_unpublished_changes' => false,
            'published_at' => $publishedAt,
        ]);
    }
}
