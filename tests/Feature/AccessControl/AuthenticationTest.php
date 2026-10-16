<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Models\AuditEvent;
use App\Models\User;
use Illuminate\Support\Facades\RateLimiter;

beforeEach(function (): void {
    RateLimiter::clear('login:ana@arquiva.test|127.0.0.1');
    $this->user = makeUser(Role::Operador);
    $this->user->update(['email' => 'ana@arquiva.test']);
});

it('faz login com credenciais válidas e devolve token sem a senha', function (): void {
    $response = $this->postJson('/api/v1/auth/login', ['email' => 'ana@arquiva.test', 'password' => 'password']);

    $response->assertOk()
        ->assertJsonStructure(['token', 'token_type', 'expires_at', 'user' => ['id', 'name', 'email', 'role', 'clearance', 'unit']])
        ->assertJsonMissingPath('user.password');

    expect($response->json('token'))->toBeString()->not->toBeEmpty();
});

it('recusa senha errada com 422 e mensagem genérica, sem indicar o campo', function (): void {
    $this->postJson('/api/v1/auth/login', ['email' => 'ana@arquiva.test', 'password' => 'errada'])
        ->assertUnprocessable()
        ->assertJsonPath('errors.credentials.0', 'E-mail ou senha inválidos.')
        ->assertJsonMissingPath('errors.email')
        ->assertJsonMissingPath('errors.password');
});

it('dá a mesma resposta para e-mail inexistente', function (): void {
    $this->postJson('/api/v1/auth/login', ['email' => 'ninguem@arquiva.test', 'password' => 'password'])
        ->assertUnprocessable()
        ->assertJsonPath('errors.credentials.0', 'E-mail ou senha inválidos.');
});

it('bloqueia com 429 e Retry-After depois de 5 erros em um minuto', function (): void {
    foreach (range(1, 5) as $attempt) {
        $this->postJson('/api/v1/auth/login', ['email' => 'ana@arquiva.test', 'password' => 'errada'])->assertUnprocessable();
    }

    $this->postJson('/api/v1/auth/login', ['email' => 'ana@arquiva.test', 'password' => 'password'])
        ->assertTooManyRequests()
        ->assertHeader('Retry-After');
});

it('responde 401 em rota protegida sem token', function (): void {
    $this->getJson('/api/v1/documents')
        ->assertUnauthorized()
        ->assertJsonPath('message', 'Não autenticado.');
});

it('responde 401 com token expirado', function (): void {
    $token = $this->user->createToken('api', ['*'], now()->addDay())->plainTextToken;

    $this->travel(25)->hours();

    $this->withToken($token)->getJson('/api/v1/auth/me')->assertUnauthorized();
});

it('emite token que expira em 24 horas', function (): void {
    $this->freezeTime();

    $response = $this->postJson('/api/v1/auth/login', ['email' => 'ana@arquiva.test', 'password' => 'password']);

    expect($response->json('expires_at'))->toBe(now()->addDay()->toIso8601String());
});

it('revoga o token no logout', function (): void {
    $token = $this->postJson('/api/v1/auth/login', ['email' => 'ana@arquiva.test', 'password' => 'password'])->json('token');

    $this->withToken($token)->postJson('/api/v1/auth/logout')->assertNoContent();

    // Limpa o usuário em cache do guard para a próxima requisição reler o token.
    app('auth')->forgetGuards();

    $this->withToken($token)->getJson('/api/v1/auth/me')->assertUnauthorized();
    expect(AuditEvent::query()->where('action', 'auth.logout')->count())->toBe(1);
});

it('não grava a senha no evento de falha de login', function (): void {
    $this->postJson('/api/v1/auth/login', ['email' => 'ana@arquiva.test', 'password' => 'segredo123']);

    $event = AuditEvent::query()->where('action', 'auth.login_failed')->sole();

    expect($event->user_id)->toBeNull()
        ->and($event->after)->toBe(['email' => 'ana@arquiva.test'])
        ->and(json_encode($event->getAttributes()))->not->toContain('segredo123');
});

it('não expõe rota para cadastrar usuários', function (): void {
    actingAsRole(Role::Admin);

    $this->postJson('/api/v1/users', ['name' => 'X'])->assertNotFound();
    expect(User::query()->count())->toBe(2);
});
