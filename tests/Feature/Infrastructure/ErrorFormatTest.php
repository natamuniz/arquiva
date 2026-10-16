<?php

declare(strict_types=1);

it('responde erros no formato { message, errors }', function (string $method, string $uri, int $status): void {
    if ($status !== 401) {
        actingAsRole();
    }

    $this->json($method, $uri)
        ->assertStatus($status)
        ->assertJsonStructure(['message', 'errors']);
})->with([
    'não autenticado' => ['GET', '/api/v1/documents', 401],
    'proibido' => ['POST', '/api/v1/classifications', 403],
    'não encontrado' => ['GET', '/api/v1/documents/00000000-0000-0000-0000-000000000000', 404],
    'rota inexistente' => ['GET', '/api/v1/nada', 404],
    'validação' => ['GET', '/api/v1/documents?per_page=0', 422],
]);
