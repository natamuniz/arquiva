<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Enums\Secrecy;
use App\Exceptions\ImmutableAuditEventException;
use App\Models\AuditEvent;
use App\Models\Classification;
use App\Models\Document;
use App\Models\DocumentFile;
use App\Models\RetentionRule;
use App\Models\Unit;
use App\Models\User;

describe('registro automático', function (): void {
    it('grava document.updated só com os campos alterados', function (): void {
        $user = actingAsRole();
        $document = Document::factory()->inUnit($user->unit)->create(['title' => 'Antigo', 'subject' => 'Mesmo']);

        $this->patchJson("/api/v1/documents/{$document->id}", ['title' => 'Novo', 'subject' => 'Mesmo'])->assertOk();

        $event = AuditEvent::query()->where('action', 'document.updated')->sole();
        expect($event->before)->toBe(['title' => 'Antigo'])
            ->and($event->after)->toBe(['title' => 'Novo'])
            ->and($event->user_id)->toBe($user->id)
            ->and($event->auditable_type)->toBe('document')
            ->and($event->auditable_id)->toBe($document->id)
            ->and($event->ip)->toBe('127.0.0.1');
    });

    it('grava document.captured', function (): void {
        $user = actingAsRole();
        $classification = classificationWithRule();
        $document = Document::factory()->inUnit($user->unit)->create(['classification_id' => $classification->id]);
        DocumentFile::factory()->create(['document_id' => $document->id]);

        $this->postJson("/api/v1/documents/{$document->id}/capture")->assertOk();

        $event = AuditEvent::query()->where('action', 'document.captured')->sole();
        expect($event->before['status'])->toBe('rascunho')
            ->and($event->after['status'])->toBe('capturado')
            ->and($event->after['retention_rule_version'])->toBe(1);
    });

    it('audita criação e exclusão de classes, regras e arquivos', function (): void {
        actingAsRole(Role::Gestor);

        $classification = Classification::factory()->create(['code' => '010']);
        RetentionRule::factory()->create(['classification_id' => $classification->id]);
        $file = DocumentFile::factory()->create();
        $file->delete();

        expect(AuditEvent::query()->pluck('action')->all())->toContain(
            'classification.created', 'retention_rule.created', 'file.created', 'file.deleted', 'document.created',
        );
    });

    it('grava auth.login com o IP', function (): void {
        $user = makeUser();

        $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'password'])->assertOk();

        $event = AuditEvent::query()->where('action', 'auth.login')->sole();
        expect($event->ip)->toBe('127.0.0.1')
            ->and($event->user_id)->toBe($user->id);
    });
});

describe('imutabilidade', function (): void {
    it('lança exceção ao tentar alterar evento e nada muda', function (): void {
        $event = AuditEvent::query()->create(['action' => 'teste']);

        expect(fn () => $event->update(['action' => 'adulterado']))->toThrow(ImmutableAuditEventException::class);
        expect($event->fresh()->action)->toBe('teste');
    });

    it('lança exceção ao tentar excluir evento e nada muda', function (): void {
        $event = AuditEvent::query()->create(['action' => 'teste']);

        expect(fn () => $event->delete())->toThrow(ImmutableAuditEventException::class);
        expect(AuditEvent::query()->find($event->id))->not->toBeNull();
    });

    it('não tem rota de escrita para auditoria', function (string $method): void {
        actingAsRole(Role::Admin);

        $this->json($method, '/api/v1/audit-events')->assertStatus(405);
    })->with(['POST', 'PUT', 'PATCH', 'DELETE']);
});

describe('consulta', function (): void {
    it('devolve o histórico do documento e dos arquivos em ordem cronológica', function (): void {
        $user = actingAsRole();
        $document = Document::factory()->inUnit($user->unit)->create(['title' => 'A']);
        $this->travel(1)->minute();
        $file = DocumentFile::factory()->create(['document_id' => $document->id]);
        $this->travel(1)->minute();
        $this->patchJson("/api/v1/documents/{$document->id}", ['title' => 'B'])->assertOk();
        $this->travel(1)->minute();
        $this->get("/api/v1/files/{$file->id}/download");
        Document::factory()->inUnit($user->unit)->create(); // outro documento: fora do histórico

        $actions = array_column($this->getJson("/api/v1/documents/{$document->id}/history")->assertOk()->json('data'), 'action');

        expect($actions)->toBe(['document.created', 'file.created', 'document.updated', 'file.downloaded']);
    });

    it('impede o operador de consultar a trilha geral', function (): void {
        actingAsRole(Role::Operador);

        $this->getJson('/api/v1/audit-events')->assertForbidden();
    });

    it('esconde do gestor eventos de documento sigiloso de outra unidade', function (): void {
        $gestor = actingAsRole(Role::Gestor);
        $hidden = Document::factory()->inUnit(Unit::factory()->create())->secrecy(Secrecy::Sigiloso)->create(['title' => 'Segredo']);
        $own = Document::factory()->inUnit($gestor->unit)->create();

        $documentIds = collect($this->getJson('/api/v1/audit-events?per_page=100')->assertOk()->json('data'))
            ->pluck('document_id')->filter()->unique()->values()->all();

        expect($documentIds)->toBe([$own->id])
            ->and(json_encode($this->getJson('/api/v1/audit-events?per_page=100')->json()))->not->toContain('Segredo');
        expect($hidden->id)->not->toBeIn($documentIds);
    });

    it('mostra ao admin a trilha completa, com filtros', function (): void {
        $admin = actingAsRole(Role::Admin);
        $other = User::factory()->create();
        Document::factory()->secrecy(Secrecy::Sigiloso)->create();
        Classification::factory()->create();
        AuditEvent::query()->create(['action' => 'auth.login', 'user_id' => $other->id]);

        expect($this->getJson('/api/v1/audit-events?action=document.created')->json('meta.total'))->toBe(1)
            ->and($this->getJson('/api/v1/audit-events?auditable_type=classification')->json('meta.total'))->toBe(1)
            ->and($this->getJson("/api/v1/audit-events?user_id={$other->id}")->json('meta.total'))->toBe(1)
            ->and($this->getJson('/api/v1/audit-events?from='.now()->addDay()->toDateString())->json('meta.total'))->toBe(0);

        expect($admin->id)->toBeInt();
    });
});
