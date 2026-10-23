<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Form;
use App\Models\Submission;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Submission>
 */
class SubmissionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'form_id' => Form::factory(),
            'schema_version' => 1,
            'data' => ['nome' => 'Maria Souza', 'email' => 'maria@example.com'],
            'ip_hash' => null,
            'user_agent' => null,
        ];
    }
}
