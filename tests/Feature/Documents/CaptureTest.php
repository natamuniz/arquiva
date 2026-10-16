<?php

declare(strict_types=1);

use App\Enums\ProcessingStatus;
use App\Enums\Role;
use App\Models\Classification;
use App\Models\Document;
use App\Models\DocumentFile;
use App\Models\RetentionRule;
use Laravel\Sanctum\Sanctum;

beforeEach(function (): void {
    $this->user = actingAsRole();
    $this->classification = classificationWithRule('010');
    $this->rule = $this->classification->currentRetentionRule()->firstOrFail();
});

function completeDraft(object $test): Document
{
    $document = Document::factory()->inUnit($test->user->unit)->create([
        'classification_id' => $test->classification->id,
        'produced_at' => '2024-05-10',
    ]);
    DocumentFile::factory()->create(['document_id' => $document->id]);

    return $document;
}

it('captura rascunho completo e guarda a versão vigente da regra', function (): void {
    $document = completeDraft($this);

    $this->postJson("/api/v1/documents/{$document->id}/capture")
        ->assertOk()
        ->assertJsonPath('data.status', 'capturado')
        ->assertJsonPath('data.retention_rule_id', $this->rule->id)
        ->assertJsonPath('data.retention_rule.version', 1);

    expect($document->fresh()->captured_at)->not->toBeNull();
});

it('lista todas as pendências de uma vez', function (): void {
    $document = Document::factory()->inUnit($this->user->unit)->create([
        'title' => null,
        'produced_at' => null,
        'classification_id' => null,
    ]);

    $this->postJson("/api/v1/documents/{$document->id}/capture")
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['title', 'produced_at', 'classification_id', 'files']);

    expect($document->fresh()->isDraft())->toBeTrue();
});

it('recusa captura sem arquivo', function (): void {
    $document = Document::factory()->inUnit($this->user->unit)->create(['classification_id' => $this->classification->id]);

    $this->postJson("/api/v1/documents/{$document->id}/capture")
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['files']);
});

it('recusa captura com um arquivo processado e outro com falha', function (): void {
    $document = completeDraft($this);
    DocumentFile::factory()->status(ProcessingStatus::Falhou)->create([
        'document_id' => $document->id,
        'original_name' => 'escaneado.pdf',
    ]);

    $this->postJson("/api/v1/documents/{$document->id}/capture")
        ->assertUnprocessable()
        ->assertJsonPath('errors.files.0', fn (string $m): bool => str_contains($m, 'escaneado.pdf'));
});

it('recusa classe sem regra vigente', function (): void {
    $withoutRule = Classification::factory()->create(['code' => '020']);
    $document = completeDraft($this);
    $document->update(['classification_id' => $withoutRule->id]);

    $this->postJson("/api/v1/documents/{$document->id}/capture")
        ->assertUnprocessable()
        ->assertJsonPath('errors.classification_id.0', 'A classe 020 não tem regra de temporalidade vigente.');
});

it('recusa capturar de novo', function (): void {
    $document = completeDraft($this);
    $this->postJson("/api/v1/documents/{$document->id}/capture")->assertOk();

    $this->postJson("/api/v1/documents/{$document->id}/capture")->assertConflict();
});

it('mantém a versão da data de captura quando a regra muda depois', function (): void {
    $document = completeDraft($this);
    $this->postJson("/api/v1/documents/{$document->id}/capture")->assertOk();

    Sanctum::actingAs(makeUser(Role::Gestor));
    $this->putJson("/api/v1/retention-rules/{$this->rule->id}", [
        'current_years' => 10, 'intermediate_years' => 10, 'final_destination' => 'guarda_permanente',
    ])->assertCreated();

    expect(RetentionRule::query()->where('classification_id', $this->classification->id)->count())->toBe(2)
        ->and($document->fresh()->retention_rule_id)->toBe($this->rule->id);
});

it('impede o perfil consulta de capturar', function (): void {
    $document = completeDraft($this);
    actingAsRole(Role::Consulta, $this->user->unit);

    $this->postJson("/api/v1/documents/{$document->id}/capture")->assertForbidden();
});
