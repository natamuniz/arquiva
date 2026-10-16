<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Actions\Auth\AuthenticateUser;
use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * @group Autenticação
 */
class AuthController extends Controller
{
    /**
     * Login
     *
     * Troca e-mail e senha por um token de acesso (Bearer) válido por 24 horas.
     * Após 5 erros em um minuto para o mesmo e-mail e IP, responde 429.
     *
     * @unauthenticated
     *
     * @bodyParam email string required E-mail do usuário. Example: operador.adm@arquiva.test
     * @bodyParam password string required Senha. Example: password
     */
    public function login(LoginRequest $request, AuthenticateUser $authenticate): JsonResponse
    {
        $result = $authenticate->handle(
            $request->string('email')->toString(),
            $request->string('password')->toString(),
            (string) $request->ip(),
        );

        $result['user']->load('unit');

        return response()->json([
            'token' => $result['token']->plainTextToken,
            'token_type' => 'Bearer',
            'expires_at' => $result['token']->accessToken->expires_at?->toIso8601String(),
            'user' => new UserResource($result['user']),
        ]);
    }

    /**
     * Usuário autenticado
     */
    public function me(Request $request): UserResource
    {
        /** @var User $user */
        $user = $request->user();

        return new UserResource($user->load('unit'));
    }

    /**
     * Logout
     *
     * Revoga o token usado na requisição.
     */
    public function logout(Request $request, AuditLogger $audit): Response
    {
        /** @var User $user */
        $user = $request->user();
        $user->currentAccessToken()->delete();

        $audit->record('auth.logout', $user);

        return response()->noContent();
    }
}
