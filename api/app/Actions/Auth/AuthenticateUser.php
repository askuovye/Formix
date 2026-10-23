<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class AuthenticateUser
{
    public const FAILED_MESSAGE = 'Email ou senha incorretos.';

    private static ?string $dummyHash = null;

    public function __invoke(string $email, #[\SensitiveParameter] string $password, bool $remember): User
    {
        $user = User::query()->where('email', $email)->first();

        // Sem usuário, a senha é conferida contra um hash descartável para que
        // "email inexistente" e "senha errada" levem o mesmo tempo.
        $passwordMatches = Hash::check($password, $user?->getAuthPassword() ?? self::dummyHash());

        if ($user === null || ! $passwordMatches) {
            throw ValidationException::withMessages(['email' => self::FAILED_MESSAGE]);
        }

        if (Hash::needsRehash($user->getAuthPassword())) {
            $user->forceFill(['password' => $password])->save();
        }

        Auth::guard('web')->login($user, $remember);
        Session::regenerate();

        return $user;
    }

    private static function dummyHash(): string
    {
        return self::$dummyHash ??= Hash::make(Str::random(40));
    }
}
