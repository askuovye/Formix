<?php

declare(strict_types=1);

namespace App\Actions\Forms;

use App\Models\Form;

/**
 * Exclusão lógica (SoftDeletes): o link público deixa de funcionar na hora e
 * o slug continua reservado. Respostas seguem a política de retenção.
 */
final class DeleteForm
{
    public function __invoke(Form $form): void
    {
        $form->delete();
    }
}
