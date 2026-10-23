<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Actions\Auth\ResetUserPassword;
use App\Actions\Auth\SendPasswordResetLink;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Http\Resources\MessageResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

final class PasswordResetController extends Controller
{
    public const LINK_SENT_MESSAGE = 'Se houver uma conta com este email, enviamos um link para redefinir a senha.';

    public const RESET_MESSAGE = 'Senha redefinida. Entre com a nova senha.';

    public function sendLink(ForgotPasswordRequest $request, SendPasswordResetLink $sendPasswordResetLink): JsonResponse
    {
        $sendPasswordResetLink($request->string('email')->value());

        return (new MessageResource(self::LINK_SENT_MESSAGE))
            ->response()
            ->setStatusCode(Response::HTTP_ACCEPTED);
    }

    public function reset(ResetPasswordRequest $request, ResetUserPassword $resetUserPassword): MessageResource
    {
        $resetUserPassword(
            $request->string('email')->value(),
            $request->string('token')->value(),
            $request->string('password')->value(),
        );

        return new MessageResource(self::RESET_MESSAGE);
    }
}
