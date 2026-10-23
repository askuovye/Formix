<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Actions\Auth\AuthenticateUser;
use App\Actions\Auth\LogoutUser;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Resources\UserResource;
use Illuminate\Http\Response;

final class SessionController extends Controller
{
    public function store(LoginRequest $request, AuthenticateUser $authenticateUser): UserResource
    {
        $user = $authenticateUser(
            $request->string('email')->value(),
            $request->string('password')->value(),
            $request->boolean('remember'),
        );

        return new UserResource($user);
    }

    public function destroy(LogoutUser $logoutUser): Response
    {
        $logoutUser();

        return response()->noContent();
    }
}
