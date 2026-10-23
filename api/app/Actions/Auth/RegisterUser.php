<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Actions\Legal\RecordLegalAcceptance;
use App\Models\User;
use App\Notifications\AccountAlreadyExistsNotification;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\DB;

/**
 * Cadastro resistente a enumeração: o resultado visível é sempre o mesmo.
 * Se o email já existe, o aviso vai só para a caixa de entrada do dono.
 */
final class RegisterUser
{
    public function __construct(private readonly RecordLegalAcceptance $recordLegalAcceptance) {}

    public function __invoke(string $name, string $email, #[\SensitiveParameter] string $password, ?string $ip): void
    {
        // A senha é sempre "hasheada" (cast `hashed`), inclusive quando o email já existe:
        // os dois caminhos levam tempo parecido e não denunciam a conta pela latência.
        $user = DB::transaction(function () use ($name, $email, $password, $ip): User {
            $user = User::query()->createOrFirst(
                ['email' => $email],
                ['name' => $name, 'password' => $password],
            );

            // Conta e prova de consentimento nascem juntas: nunca existe uma sem a outra.
            if ($user->wasRecentlyCreated) {
                ($this->recordLegalAcceptance)($user, $ip);
            }

            return $user;
        });

        if ($user->wasRecentlyCreated) {
            event(new Registered($user));

            return;
        }

        if ($user->hasVerifiedEmail()) {
            $user->notify(new AccountAlreadyExistsNotification);

            return;
        }

        $user->sendEmailVerificationNotification();
    }
}
