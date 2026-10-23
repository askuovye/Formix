<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Actions\Auth\RegisterUser;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\MessageResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

final class RegisterController extends Controller
{
    public const MESSAGE = 'Se o cadastro puder ser concluído, você receberá um email com as próximas instruções.';

    public function __invoke(RegisterRequest $request, RegisterUser $registerUser): JsonResponse
    {
        $registerUser(
            $request->string('name')->value(),
            $request->string('email')->value(),
            $request->string('password')->value(),
            $request->ip(),
        );

        return (new MessageResource(self::MESSAGE))
            ->response()
            ->setStatusCode(Response::HTTP_ACCEPTED);
    }
}
