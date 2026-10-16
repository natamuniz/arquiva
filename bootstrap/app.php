<?php

declare(strict_types=1);

use App\Exceptions\ConflictException;
use App\Exceptions\UnprocessableException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Formato único de erro da API: { "message": "...", "errors": { ... } }.
 *
 * @param  array<string, mixed>  $errors
 * @param  array<string, string>  $headers
 */
$error = static fn (string $message, int $status, array $errors = [], array $headers = []): JsonResponse => new JsonResponse(
    ['message' => $message, 'errors' => (object) $errors],
    $status,
    $headers,
);

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        apiPrefix: 'api/v1',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions) use ($error): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->render(function (Throwable $e, Request $request) use ($error) {
            if (! $request->is('api/*')) {
                return null;
            }

            return match (true) {
                $e instanceof ValidationException => $error($e->getMessage(), 422, $e->errors()),
                $e instanceof AuthenticationException => $error('Não autenticado.', 401),
                $e instanceof AuthorizationException,
                $e instanceof AccessDeniedHttpException => $error('Ação não permitida para o seu perfil.', 403),
                $e instanceof ModelNotFoundException,
                $e instanceof NotFoundHttpException => $error('Recurso não encontrado.', 404),
                $e instanceof UnprocessableException => $error($e->getMessage(), 422, $e->errors()),
                $e instanceof ConflictException => $error($e->getMessage(), 409, $e->errors()),
                // Índices únicos são a última barreira contra condições de corrida.
                $e instanceof UniqueConstraintViolationException => $error(
                    'Conflito: a operação viola uma regra de unicidade. Recarregue os dados e tente de novo.',
                    409,
                ),
                $e instanceof ThrottleRequestsException => $error(
                    'Muitas tentativas. Aguarde antes de tentar de novo.',
                    429,
                    [],
                    $e->getHeaders(),
                ),
                $e instanceof HttpExceptionInterface => $error(
                    $e->getMessage() !== '' ? $e->getMessage() : 'Erro na requisição.',
                    $e->getStatusCode(),
                    [],
                    $e->getHeaders(),
                ),
                default => null,
            };
        });
    })->create();
