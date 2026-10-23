<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Data\FormSettings;
use App\Enums\FieldType;
use App\Enums\FormStatus;
use App\Models\Form;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Form>
 */
class FormFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'title' => 'Pesquisa de satisfação',
            'description' => null,
            'slug' => 'pesquisa-'.Str::lower(Str::random(8)),
            'status' => FormStatus::Draft,
            'schema' => ['fields' => []],
            'schema_version' => 0,
            'has_unpublished_changes' => true,
            'settings' => new FormSettings,
        ];
    }

    /**
     * Rascunho com um campo de texto e um de email, pronto para publicar.
     */
    public function withFields(): static
    {
        return $this->state(fn (): array => ['schema' => ['fields' => [
            ['id' => 'nome', 'type' => FieldType::ShortText->value, 'label' => 'Nome', 'help_text' => null, 'required' => true, 'placeholder' => null, 'rules' => []],
            ['id' => 'email', 'type' => FieldType::Email->value, 'label' => 'Email', 'help_text' => null, 'required' => true, 'placeholder' => null, 'rules' => []],
        ]]]);
    }
}
