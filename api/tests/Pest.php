<?php

declare(strict_types=1);

use App\Actions\Forms\PublishForm;
use App\Data\FormSettings;
use App\Models\Form;
use App\Services\FormSchema\FormSchemaNormalizer;
use App\Services\Health\HealthCheck;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
*/

pest()->extend(TestCase::class)->in('Feature', 'Unit');

/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/

/**
 * Carrega as fixtures de validação compartilhadas com o frontend.
 *
 * @return array<string, mixed>
 */
function validationFixtures(): array
{
    // Relativo a este arquivo (não a base_path()): os datasets são montados antes do boot da aplicação.
    $path = dirname(__DIR__, 2).'/fixtures/validation-cases.json';

    return json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
}

/**
 * Cabeçalhos de uma requisição vinda da SPA (domínio stateful do Sanctum).
 *
 * @return array<string, string>
 */
function spaHeaders(): array
{
    return ['Origin' => 'http://localhost:5173', 'Referer' => 'http://localhost:5173/'];
}

/**
 * Schema válido cobrindo os tipos de campo principais, no formato enviado pelo builder.
 *
 * @return array{fields: list<array<string, mixed>>}
 */
function validFormSchema(): array
{
    return ['fields' => [
        ['id' => 'nome', 'type' => 'short_text', 'label' => 'Nome completo', 'required' => true,
            'rules' => [['type' => 'min_length', 'value' => 5], ['type' => 'no_repeated_chars', 'threshold' => 0.8]]],
        ['id' => 'email', 'type' => 'email', 'label' => 'Email', 'required' => true],
        ['id' => 'relato', 'type' => 'long_text', 'label' => 'Conte sua experiência', 'rules' => [['type' => 'min_words', 'value' => 3, 'message' => 'Escreva pelo menos 3 palavras.']]],
        ['id' => 'nota', 'type' => 'single_choice', 'label' => 'Nota', 'display' => 'select',
            'options' => [['value' => 'boa', 'label' => 'Boa'], ['value' => 'ruim', 'label' => 'Ruim']]],
        ['id' => 'comprovante', 'type' => 'file', 'label' => 'Comprovante', 'file' => ['accept' => ['pdf', 'image'], 'max_files' => 2, 'max_size_mb' => 5]],
    ]];
}

/**
 * Formulário publicado (versão 1) com validFormSchema(), via a Action real.
 *
 * @param  array<string, mixed>  $settings  configurações em snake_case
 */
function publishedForm(array $settings = []): Form
{
    $form = Form::factory()->create([
        'schema' => app(FormSchemaNormalizer::class)->normalize(validFormSchema()),
        'settings' => FormSettings::fromArray($settings),
    ]);

    return app(PublishForm::class)($form, $form->user);
}

/**
 * @return array<string, mixed>
 */
function validSubmissionAnswers(): array
{
    return [
        'nome' => 'Maria Souza',
        'email' => 'maria@example.com',
        'relato' => 'Fui muito bem atendida pela equipe.',
        'nota' => 'boa',
    ];
}

function fakeHealthCheck(string $name, bool $passes, bool $critical = true): HealthCheck
{
    return new readonly class($name, $passes, $critical) implements HealthCheck
    {
        public function __construct(
            private string $name,
            private bool $passes,
            private bool $critical,
        ) {}

        public function name(): string
        {
            return $this->name;
        }

        public function isCritical(): bool
        {
            return $this->critical;
        }

        public function passes(): bool
        {
            return $this->passes;
        }
    };
}
