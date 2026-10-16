<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Models\Classification;
use App\Models\Document;

beforeEach(function (): void {
    actingAsRole(Role::Gestor);
    $this->root = Classification::factory()->create(['code' => '010', 'name' => 'Organização e funcionamento']);
});

describe('criação', function (): void {
    it('cria classe filha válida com depth do pai + 1', function (): void {
        $this->postJson('/api/v1/classifications', ['code' => '010.1', 'name' => 'Normas', 'parent_id' => $this->root->id])
            ->assertCreated()
            ->assertJsonPath('data.code', '010.1')
            ->assertJsonPath('data.parent_id', $this->root->id)
            ->assertJsonPath('data.depth', $this->root->depth + 1);
    });

    it('cria classe raiz com 3 dígitos', function (): void {
        $this->postJson('/api/v1/classifications', ['code' => '020', 'name' => 'Pessoal'])
            ->assertCreated()
            ->assertJsonPath('data.depth', 1);
    });

    it('recusa código incompatível com o pai', function (): void {
        $this->postJson('/api/v1/classifications', ['code' => '020.1', 'name' => 'X', 'parent_id' => $this->root->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['code']);

        expect($this->getJson('/api/v1/classifications')->json('data.0.children'))->toBe([]);
    });

    it('recusa código sem separador', function (): void {
        $this->postJson('/api/v1/classifications', ['code' => '0101', 'name' => 'X', 'parent_id' => $this->root->id])
            ->assertUnprocessable()
            ->assertJsonPath('errors.code.0', fn (string $message): bool => str_contains($message, 'esperado 010.N'));
    });

    it('recusa raiz fora do formato de 3 dígitos', function (string $code): void {
        $this->postJson('/api/v1/classifications', ['code' => $code, 'name' => 'X'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['code']);
    })->with(['01', '0100', 'abc', '010.1']);

    it('recusa código duplicado', function (): void {
        $this->postJson('/api/v1/classifications', ['code' => '010', 'name' => 'Repetida'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['code']);
    });
});

describe('árvore', function (): void {
    it('devolve a subárvore aninhada e ordenada por código numérico', function (): void {
        $c1 = Classification::factory()->childOf($this->root, 1)->create();
        Classification::factory()->childOf($this->root, 10)->create();
        Classification::factory()->childOf($this->root, 2)->create();
        Classification::factory()->childOf($c1, 1)->create();
        Classification::factory()->create(['code' => '020']);

        $response = $this->getJson("/api/v1/classifications/{$this->root->id}/tree")->assertOk();

        expect($response->json('data.code'))->toBe('010')
            ->and(array_column($response->json('data.children'), 'code'))->toBe(['010.1', '010.2', '010.10'])
            ->and($response->json('data.children.0.children.0.code'))->toBe('010.1.1')
            ->and($response->json('data.children.1.children'))->toBe([]);
    });

    it('lista o plano inteiro como árvore', function (): void {
        Classification::factory()->childOf($this->root, 1)->create();
        Classification::factory()->create(['code' => '020']);

        $response = $this->getJson('/api/v1/classifications')->assertOk();

        expect(array_column($response->json('data'), 'code'))->toBe(['010', '020'])
            ->and($response->json('data.0.children.0.code'))->toBe('010.1');
    });

    it('responde 404 para id inválido', function (): void {
        $this->getJson('/api/v1/classifications/abc/tree')->assertNotFound();
        $this->getJson('/api/v1/classifications/999999/tree')->assertNotFound();
    });
});

describe('edição', function (): void {
    it('renomeia a classe', function (): void {
        $this->patchJson("/api/v1/classifications/{$this->root->id}", ['name' => 'Novo nome'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Novo nome');
    });

    it('recusa mudar código ou pai com 409', function (array $payload): void {
        $other = Classification::factory()->create(['code' => '090']);
        $payload = array_map(fn ($v) => $v === 'OTHER' ? $other->id : $v, $payload);

        $this->patchJson("/api/v1/classifications/{$this->root->id}", $payload)->assertConflict();
        expect($this->root->fresh()->code)->toBe('010');
    })->with([
        'código' => [['code' => '011']],
        'pai' => [['parent_id' => 'OTHER']],
    ]);

    it('aceita reenviar o mesmo código e pai junto com o nome', function (): void {
        $this->patchJson("/api/v1/classifications/{$this->root->id}", ['code' => '010', 'parent_id' => null, 'name' => 'Ok'])
            ->assertOk();
    });
});

describe('desativação e exclusão', function (): void {
    it('recusa excluir classe com documentos e sugere desativar', function (): void {
        Document::factory()->create(['classification_id' => $this->root->id]);

        $this->deleteJson("/api/v1/classifications/{$this->root->id}")
            ->assertConflict()
            ->assertJsonPath('message', fn (string $m): bool => str_contains($m, 'Desative'));
    });

    it('recusa excluir classe com filhas', function (): void {
        Classification::factory()->childOf($this->root)->create();

        $this->deleteJson("/api/v1/classifications/{$this->root->id}")->assertConflict();
    });

    it('exclui classe sem filhas nem documentos', function (): void {
        $this->deleteJson("/api/v1/classifications/{$this->root->id}")->assertNoContent();
        expect(Classification::query()->count())->toBe(0);
    });

    it('recusa desativar classe com filhas ativas e lista as filhas', function (): void {
        Classification::factory()->childOf($this->root, 1)->create();

        $this->postJson("/api/v1/classifications/{$this->root->id}/deactivate")
            ->assertConflict()
            ->assertJsonPath('errors.children', ['010.1']);
    });

    it('desativa e reativa classe', function (): void {
        $this->postJson("/api/v1/classifications/{$this->root->id}/deactivate")
            ->assertOk()->assertJsonPath('data.active', false);
        $this->postJson("/api/v1/classifications/{$this->root->id}/activate")
            ->assertOk()->assertJsonPath('data.active', true);
    });

    it('recusa reativar filha com pai inativo', function (): void {
        $child = Classification::factory()->childOf($this->root)->inactive()->create();
        $this->root->update(['active' => false]);

        $this->postJson("/api/v1/classifications/{$child->id}/activate")->assertConflict();
    });

    it('recusa criar documento em classe inativa', function (): void {
        $this->root->update(['active' => false]);

        $this->postJson('/api/v1/documents', ['title' => 'Ofício', 'classification_id' => $this->root->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['classification_id']);
    });
});
