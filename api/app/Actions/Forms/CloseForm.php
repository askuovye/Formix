<?php

declare(strict_types=1);

namespace App\Actions\Forms;

use App\Enums\FormStatus;
use App\Exceptions\ApiException;
use App\Models\Form;
use Illuminate\Support\Facades\DB;

final class CloseForm
{
    public const NOT_PUBLISHED_MESSAGE = 'Só formulários publicados podem ser fechados.';

    public function __invoke(Form $form): Form
    {
        return DB::transaction(function () use ($form): Form {
            $form = Form::query()->lockForUpdate()->findOrFail($form->id);

            if ($form->status !== FormStatus::Published) {
                throw ApiException::conflict(self::NOT_PUBLISHED_MESSAGE);
            }

            $form->forceFill([
                'status' => FormStatus::Closed,
                'closed_at' => now(),
            ])->save();

            return $form;
        });
    }
}
