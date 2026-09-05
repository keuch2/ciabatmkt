<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Password;

class ForgotPasswordController extends Controller
{
    public function __invoke(ForgotPasswordRequest $request): JsonResponse
    {
        $status = Password::sendResetLink($request->only('email'));

        // La respuesta es la misma exista o no el correo, para no revelar qué cuentas hay.
        if ($status === Password::RESET_THROTTLED) {
            return response()->json(['message' => __($status)], 429);
        }

        return response()->json([
            'message' => 'Si el correo está registrado, vas a recibir un enlace para restablecer la contraseña.',
        ]);
    }
}
