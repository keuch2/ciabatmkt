<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Resources\UserResource;

class LoginController extends Controller
{
    public function __invoke(LoginRequest $request): UserResource
    {
        $request->authenticate();
        $request->session()->regenerate();

        return new UserResource($request->user());
    }
}
