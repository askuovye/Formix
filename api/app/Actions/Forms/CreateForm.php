<?php

declare(strict_types=1);

namespace App\Actions\Forms;

use App\Data\FormSettings;
use App\Enums\FormStatus;
use App\Models\Form;
use App\Models\User;
use App\Services\FormSlugGenerator;

final class CreateForm
{
    public function __construct(private readonly FormSlugGenerator $slugGenerator) {}

    public function __invoke(User $owner, string $title, ?string $description): Form
    {
        $form = new Form([
            'title' => $title,
            'description' => $description,
            'slug' => $this->slugGenerator->generate($title),
            'schema' => ['fields' => []],
            'settings' => new FormSettings,
        ]);

        $form->forceFill([
            'user_id' => $owner->id,
            'status' => FormStatus::Draft,
            'schema_version' => 0,
            'has_unpublished_changes' => true,
        ])->save();

        return $form;
    }
}
