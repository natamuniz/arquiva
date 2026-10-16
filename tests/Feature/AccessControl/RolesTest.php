<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Enums\Secrecy;
use App\Models\Document;

it('impede o perfil consulta de criar documento', function (): void {
    actingAsRole(Role::Consulta);

    $this->postJson('/api/v1/documents', ['title' => 'Ofício'])
        ->assertForbidden()
        ->assertJsonStructure(['message', 'errors']);
});

it('impede o operador de alterar o plano de classificação', function (): void {
    actingAsRole(Role::Operador);

    $this->postJson('/api/v1/classifications', ['code' => '010', 'name' => 'Administração geral'])
        ->assertForbidden();
});

it('permite ao gestor alterar o plano de classificação', function (): void {
    actingAsRole(Role::Gestor);

    $this->postJson('/api/v1/classifications', ['code' => '010', 'name' => 'Administração geral'])
        ->assertCreated();
});

it('permite ao perfil consulta ler documentos', function (): void {
    $user = actingAsRole(Role::Consulta);
    Document::factory()->inUnit($user->unit)->create();

    $this->getJson('/api/v1/documents')->assertOk()->assertJsonCount(1, 'data');
});

it('impede o perfil consulta de editar documento da própria unidade', function (): void {
    $user = actingAsRole(Role::Consulta);
    $document = Document::factory()->inUnit($user->unit)->create();

    $this->patchJson("/api/v1/documents/{$document->id}", ['title' => 'Novo'])->assertForbidden();
});

it('permite ao admin ver e editar documento sigiloso de qualquer unidade', function (): void {
    actingAsRole(Role::Admin);
    $document = Document::factory()->secrecy(Secrecy::Sigiloso)->create();

    $this->getJson("/api/v1/documents/{$document->id}")->assertOk();
    $this->patchJson("/api/v1/documents/{$document->id}", ['title' => 'Revisado'])->assertOk();
});
