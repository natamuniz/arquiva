<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\NewAccessToken;

class AuthenticateUser
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @return array{user: User, token: NewAccessToken}
     *
     * @throws ValidationException quando as credenciais são inválidas (mensagem genérica)
     * @throws ThrottleRequestsException quando o limite de tentativas foi atingido
     */
    public function handle(string $email, string $password, string $ip): array
    {
        $key = 'login:'.Str::lower($email).'|'.$ip;
        $maxAttempts = (int) config('arquiva.login_max_attempts');

        if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
            $seconds = RateLimiter::availableIn($key);

            throw new ThrottleRequestsException(
                'Muitas tentativas de login.',
                null,
                ['Retry-After' => (string) $seconds],
            );
        }

        $user = User::query()->where('email', Str::lower($email))->first();

        if ($user === null || ! Hash::check($password, $user->password)) {
            RateLimiter::hit($key, 60);
            // Sem gravar a senha: só o e-mail tentado e o IP.
            $this->audit->record('auth.login_failed', null, null, ['email' => Str::lower($email)], null);

            throw ValidationException::withMessages([
                'credentials' => ['E-mail ou senha inválidos.'],
            ]);
        }

        RateLimiter::clear($key);

        $token = $user->createToken(
            'api',
            ['*'],
            now()->addMinutes((int) config('arquiva.token_ttl_minutes')),
        );

        $this->audit->record('auth.login', $user, null, null, $user->id);

        return ['user' => $user, 'token' => $token];
    }
}
