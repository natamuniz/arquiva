<?php

declare(strict_types=1);

use App\Enums\Secrecy;
use App\Models\AuditEvent;
use App\Models\Document;
use App\Models\DocumentFile;

it('baixa pela API com o nome original e grava file.downloaded', function (): void {
    $user = actingAsRole(clearance: Secrecy::Restrito);
    $document = Document::factory()->inUnit($user->unit)->secrecy(Secrecy::Restrito)->create();
    $file = DocumentFile::factory()->create(['document_id' => $document->id, 'original_name' => 'Ata da reunião.pdf']);

    $response = $this->get("/api/v1/files/{$file->id}/download")
        ->assertOk()
        ->assertDownload('Ata da reuniao.pdf'); // versão ASCII do cabeçalho

    expect($response->headers->get('Content-Disposition'))->toContain("filename*=utf-8''Ata%20da%20reuni%C3%A3o.pdf");

    $event = AuditEvent::query()->where('action', 'file.downloaded')->sole();
    expect($event->user_id)->toBe($user->id)
        ->and($event->auditable_id)->toBe($file->id)
        ->and($event->document_id)->toBe($document->id)
        ->and($event->ip)->toBe('127.0.0.1');
});

it('não audita download negado', function (): void {
    $user = actingAsRole(clearance: Secrecy::Publico);
    $document = Document::factory()->inUnit($user->unit)->secrecy(Secrecy::Sigiloso)->create();
    $file = DocumentFile::factory()->create(['document_id' => $document->id]);

    $this->get("/api/v1/files/{$file->id}/download")->assertForbidden();

    expect(AuditEvent::query()->where('action', 'file.downloaded')->count())->toBe(0);
});
