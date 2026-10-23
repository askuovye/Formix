<?php

declare(strict_types=1);

namespace App\Actions\Forms;

use App\Models\Form;
use App\Services\FormSchema\FormConsistency;
use App\Services\FormSchema\FormSchemaNormalizer;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Atualiza o rascunho. Título, descrição e schema fazem parte do conteúdo
 * versionado (marcam "alterações não publicadas"); configurações valem na hora.
 */
final class UpdateForm
{
    public const SLUG_LOCKED_MESSAGE = 'O endereço não pode ser alterado depois que o formulário foi publicado.';

    public function __construct(
        private readonly FormSchemaNormalizer $normalizer,
        private readonly FormConsistency $consistency,
    ) {}

    /**
     * @param  array<string, mixed>  $data  dados validados (chaves ausentes não mudam)
     */
    public function __invoke(Form $form, array $data): Form
    {
        return DB::transaction(function () use ($form, $data): Form {
            // Trava a linha: uma publicação simultânea não pode ver metade da edição.
            $form = Form::query()->lockForUpdate()->findOrFail($form->id);

            $this->updateSlug($form, $data);

            $content = array_intersect_key($data, array_flip(['title', 'description']));

            if (array_key_exists('schema', $data)) {
                $content['schema'] = $this->normalizer->normalize($data['schema']);
            }

            $form->fill($content);

            if (array_key_exists('settings', $data)) {
                $form->settings = $form->settings->merge($data['settings']);
                $this->consistency->assertSettingsMatchSchema($form->settings, $form->schema);
            }

            if ($form->isDirty(['title', 'description', 'schema'])) {
                $form->has_unpublished_changes = true;
            }

            $form->save();

            return $form;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function updateSlug(Form $form, array $data): void
    {
        if (! array_key_exists('slug', $data) || $data['slug'] === $form->slug) {
            return;
        }

        // O link já pode ter sido divulgado: trocá-lo quebraria quem já o recebeu.
        if ($form->hasBeenPublished()) {
            throw ValidationException::withMessages(['slug' => self::SLUG_LOCKED_MESSAGE]);
        }

        $form->slug = (string) $data['slug'];
    }
}
