<?php

declare(strict_types=1);

use App\Actions\RetentionRules\ReviseRetentionRule;
use App\Enums\Role;
use App\Models\Classification;
use App\Models\RetentionRule;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

// Datas gravadas sempre em UTC (o banco usa timestamptz).

beforeEach(function (): void {
    $this->gestor = actingAsRole(Role::Gestor);
    $this->classification = Classification::factory()->create(['code' => '010']);
    $this->payload = ['current_years' => 2, 'intermediate_years' => 5, 'final_destination' => 'eliminacao', 'notes' => 'Após aprovação das contas'];
});

it('cria a primeira regra com versão 1, vigente desde agora', function (): void {
    $this->freezeTime();

    $this->postJson("/api/v1/classifications/{$this->classification->id}/retention-rules", $this->payload)
        ->assertCreated()
        ->assertJsonPath('data.version', 1)
        ->assertJsonPath('data.valid_from', now()->toIso8601String())
        ->assertJsonPath('data.valid_to', null)
        ->assertJsonPath('data.is_current', true);
});

it('recusa criar regra em classe que já tem regra vigente', function (): void {
    RetentionRule::factory()->create(['classification_id' => $this->classification->id]);

    $this->postJson("/api/v1/classifications/{$this->classification->id}/retention-rules", $this->payload)
        ->assertConflict();
});

it('valida prazos inteiros não negativos e destinação', function (): void {
    $this->postJson("/api/v1/classifications/{$this->classification->id}/retention-rules", [
        'current_years' => -1, 'intermediate_years' => 1.5, 'final_destination' => 'queimar',
    ])->assertUnprocessable()->assertJsonValidationErrors(['current_years', 'intermediate_years', 'final_destination']);
});

it('impede o operador de criar regra', function (): void {
    actingAsRole(Role::Operador);

    $this->postJson("/api/v1/classifications/{$this->classification->id}/retention-rules", $this->payload)->assertForbidden();
});

it('alterar a regra vigente encerra a versão 1 e cria a versão 2', function (): void {
    $v1 = RetentionRule::factory()->create(['classification_id' => $this->classification->id]);
    $this->freezeTime();

    $this->putJson("/api/v1/retention-rules/{$v1->id}", [...$this->payload, 'current_years' => 4])
        ->assertCreated()
        ->assertJsonPath('data.version', 2)
        ->assertJsonPath('data.current_years', 4)
        ->assertJsonPath('data.is_current', true);

    expect($v1->fresh()->valid_to?->toIso8601String())->toBe(now()->toIso8601String());

    $history = $this->getJson("/api/v1/classifications/{$this->classification->id}/retention-rules")->assertOk();
    expect(array_column($history->json('data'), 'version'))->toBe([1, 2])
        ->and(array_column($history->json('data'), 'is_current'))->toBe([false, true]);
});

it('recusa editar versão encerrada com 409', function (): void {
    $v1 = RetentionRule::factory()->create(['classification_id' => $this->classification->id, 'valid_to' => now()]);
    RetentionRule::factory()->create(['classification_id' => $this->classification->id, 'version' => 2, 'valid_from' => now()]);

    $this->putJson("/api/v1/retention-rules/{$v1->id}", $this->payload)->assertConflict();
    expect(RetentionRule::query()->count())->toBe(2);
});

describe('concorrência', function (): void {
    it('o banco aceita no máximo uma versão vigente por classe', function (): void {
        RetentionRule::factory()->create(['classification_id' => $this->classification->id]);

        expect(fn () => DB::transaction(fn () => RetentionRule::factory()->create([
            'classification_id' => $this->classification->id,
            'version' => 2,
        ])))->toThrow(UniqueConstraintViolationException::class);
    });

    it('a segunda de duas alterações simultâneas recebe 409', function (): void {
        $v1 = RetentionRule::factory()->create(['classification_id' => $this->classification->id]);

        // Simula a corrida: as duas requisições carregaram a versão 1 vigente; a primeira já gravou.
        $stale = RetentionRule::query()->findOrFail($v1->id);
        app(ReviseRetentionRule::class)->handle($v1, [...$this->payload, 'notes' => 'primeira'], $this->gestor);

        expect($stale->valid_to)->toBeNull();

        $this->putJson("/api/v1/retention-rules/{$stale->id}", [...$this->payload, 'notes' => 'segunda'])->assertConflict();

        expect(RetentionRule::query()->whereNull('valid_to')->count())->toBe(1)
            ->and(RetentionRule::query()->whereNull('valid_to')->value('notes'))->toBe('primeira');
    });
});

describe('consulta por data', function (): void {
    beforeEach(function (): void {
        $this->v1 = RetentionRule::factory()->create([
            'classification_id' => $this->classification->id,
            'version' => 1,
            'current_years' => 1,
            'valid_from' => Carbon::parse('2024-06-01 00:00', 'America/Sao_Paulo')->utc(),
            'valid_to' => Carbon::parse('2025-03-01 00:00', 'America/Sao_Paulo')->utc(),
        ]);
        $this->v2 = RetentionRule::factory()->create([
            'classification_id' => $this->classification->id,
            'version' => 2,
            'current_years' => 3,
            'valid_from' => Carbon::parse('2025-03-01 00:00', 'America/Sao_Paulo')->utc(),
            'valid_to' => null,
        ]);
    });

    it('devolve a versão vigente em uma data passada', function (): void {
        $this->getJson("/api/v1/classifications/{$this->classification->id}/retention-rules?at=2025-01-10")
            ->assertOk()
            ->assertJsonPath('data.version', 1);
    });

    it('usa a nova versão a partir do instante da troca', function (): void {
        $this->getJson("/api/v1/classifications/{$this->classification->id}/retention-rules?at=2025-03-01")
            ->assertOk()
            ->assertJsonPath('data.version', 2);

        $this->getJson("/api/v1/classifications/{$this->classification->id}/retention-rules?at=".urlencode('2025-02-28T23:59:59-03:00'))
            ->assertOk()
            ->assertJsonPath('data.version', 1);
    });

    it('responde 404 antes da primeira regra', function (): void {
        $this->getJson("/api/v1/classifications/{$this->classification->id}/retention-rules?at=2020-01-01")
            ->assertNotFound();
    });

    it('recusa data inválida com 422', function (): void {
        $this->getJson("/api/v1/classifications/{$this->classification->id}/retention-rules?at=ontem")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['at']);
    });
});
