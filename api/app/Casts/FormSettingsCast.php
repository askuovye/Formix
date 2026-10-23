<?php

declare(strict_types=1);

namespace App\Casts;

use App\Data\FormSettings;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * @implements CastsAttributes<FormSettings, FormSettings>
 */
final class FormSettingsCast implements CastsAttributes
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): FormSettings
    {
        $decoded = is_string($value) ? json_decode($value, true, flags: JSON_THROW_ON_ERROR) : [];

        return FormSettings::fromArray(is_array($decoded) ? $decoded : []);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): string
    {
        if (! $value instanceof FormSettings) {
            throw new InvalidArgumentException('As configurações do formulário devem ser um FormSettings.');
        }

        return json_encode($value->toArray(), JSON_THROW_ON_ERROR);
    }
}
