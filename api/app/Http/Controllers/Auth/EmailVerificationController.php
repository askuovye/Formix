<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Actions\Auth\ResendEmailVerification;
use App\Actions\Auth\VerifyUserEmail;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\VerifyEmailRequest;
use App\Http\Resources\MessageResource;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

final class EmailVerificationController extends Controller
{
    public const VERIFIED_MESSAGE = 'Email confirmado.';

    public const RESENT_MESSAGE = 'Se o seu email ainda não foi confirmado, enviamos um novo link.';

    public function verify(VerifyEmailRequest $request, VerifyUserEmail $verifyUserEmail): MessageResource
    {
        $verifyUserEmail($request->userId(), $request->emailHash());

        return new MessageResource(self::VERIFIED_MESSAGE);
    }

    public function resend(#[CurrentUser] User $user, ResendEmailVerification $resendEmailVerification): JsonResponse
    {
        $resendEmailVerification($user);

        return (new MessageResource(self::RESENT_MESSAGE))
            ->response()
            ->setStatusCode(Response::HTTP_ACCEPTED);
    }
}
