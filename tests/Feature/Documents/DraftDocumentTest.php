<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Enums\Secrecy;
use App\Models\Classification;
use App\Models\Document;
use App\Models\Unit;

it('cria rascunho na unidade do operador com sigilo restrito por padrão', function (): void {
    $user = actingAsRole(Role::Operador);
    $classification = Classification::factory()->create(['code' => '010']);

    $this->postJson('/api/v1/documents', ['title' => 'Ofício 12/2024', 'classification_id' => $classification->id])
        ->assertCreated()
        ->assertJsonPath('data.status', 'rascunho')
        ->assertJsonPath('data.secrecy', 'restrito')
        ->assertJsonPath('data.unit_id', $user->unit_id)
        ->assertJsonPath('data.classification.code', '010');
});

it('ignora a unidade de outros perfis: só o admin informa unit_id', function (): void {
    actingAsRole(Role::Operador);
    $other = Unit::factory()->create();

    $this->postJson('/api/v1/documents', ['title' => 'X', 'unit_id' => $other->id])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['unit_id']);
});

it('exige que o admin informe a unidade', function (): void {
    actingAsRole(Role::Admin);
    $unit = Unit::factory()->create();

    $this->postJson('/api/v1/documents', ['title' => 'X'])->assertJsonValidationErrors(['unit_id']);
    $this->postJson('/api/v1/documents', ['title' => 'X', 'unit_id' => $unit->id])
        ->assertCreated()
        ->assertJsonPath('data.unit_id', $unit->id);
});

it('aceita rascunho incompleto, mas valida o que foi enviado', function (): void {
    actingAsRole();

    $this->postJson('/api/v1/documents', [])->assertCreated();
    $this->postJson('/api/v1/documents', ['produced_at' => now()->addDay()->toDateString(), 'secrecy' => 'secreto'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['produced_at', 'secrecy']);
});

it('edita metadados do rascunho', function (): void {
    $user = actingAsRole();
    $document = Document::factory()->inUnit($user->unit)->create();

    $this->patchJson("/api/v1/documents/{$document->id}", ['title' => 'Novo título', 'secrecy' => 'publico'])
        ->assertOk()
        ->assertJsonPath('data.title', 'Novo título')
        ->assertJsonPath('data.secrecy', 'publico');
});

it('recusa mover rascunho para classe inativa', function (): void {
    $user = actingAsRole();
    $document = Document::factory()->inUnit($user->unit)->create();
    $inactive = Classification::factory()->inactive()->create();

    $this->patchJson("/api/v1/documents/{$document->id}", ['classification_id' => $inactive->id])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['classification_id']);
});

describe('documento capturado', function (): void {
    beforeEach(function (): void {
        $this->user = actingAsRole(Role::Operador);
        $this->classification = classificationWithRule('010');
        $this->document = Document::factory()->inUnit($this->user->unit)->captured()->create([
            'classification_id' => $this->classification->id,
        ]);
    });

    it('recusa reclassificar com 409', function (): void {
        $other = Classification::factory()->create(['code' => '020']);

        $this->patchJson("/api/v1/documents/{$this->document->id}", ['classification_id' => $other->id])->assertConflict();
        expect($this->document->fresh()->classification_id)->toBe($this->classification->id);
    });

    it('permite editar os demais metadados', function (): void {
        $this->patchJson("/api/v1/documents/{$this->document->id}", ['subject' => 'Assunto corrigido'])
            ->assertOk()
            ->assertJsonPath('data.subject', 'Assunto corrigido');
    });

    it('impede o operador de alterar o sigilo', function (): void {
        $this->patchJson("/api/v1/documents/{$this->document->id}", ['secrecy' => 'publico'])->assertForbidden();
        expect($this->document->fresh()->secrecy)->toBe(Secrecy::Restrito);
    });

    it('permite ao gestor da unidade alterar o sigilo', function (): void {
        actingAsRole(Role::Gestor, $this->user->unit);

        $this->patchJson("/api/v1/documents/{$this->document->id}", ['secrecy' => 'sigiloso'])
            ->assertOk()
            ->assertJsonPath('data.secrecy', 'sigiloso');
    });
});
