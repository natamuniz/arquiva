<?php

declare(strict_types=1);

use App\Models\Document;
use App\Models\DocumentFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    $this->user = actingAsRole();
    $this->disk = Storage::disk((string) config('arquiva.uploads.disk'));
});

it('exclui rascunho e apaga os arquivos depois do commit', function (): void {
    $document = Document::factory()->inUnit($this->user->unit)->create();
    $files = DocumentFile::factory()->count(2)->create(['document_id' => $document->id]);

    $this->deleteJson("/api/v1/documents/{$document->id}")->assertNoContent();

    expect(Document::query()->find($document->id))->toBeNull()
        ->and(DocumentFile::query()->count())->toBe(0);

    foreach ($files as $file) {
        $this->disk->assertMissing($file->storage_path);
    }
});

it('não apaga arquivo físico se a transação falhar', function (): void {
    $document = Document::factory()->inUnit($this->user->unit)->create();
    $file = DocumentFile::factory()->create(['document_id' => $document->id]);

    // Falha simulada depois da exclusão do registro, ainda dentro da transação.
    Document::deleted(function (): void {
        throw new RuntimeException('falha simulada no banco');
    });

    $this->withoutExceptionHandling();

    expect(fn () => $this->deleteJson("/api/v1/documents/{$document->id}"))->toThrow(RuntimeException::class);

    $this->disk->assertExists($file->storage_path);
    expect(Document::query()->find($document->id))->not->toBeNull()
        ->and(DocumentFile::query()->find($file->id))->not->toBeNull();
});

it('recusa excluir documento capturado', function (): void {
    $document = Document::factory()->inUnit($this->user->unit)->captured()->create();

    $this->deleteJson("/api/v1/documents/{$document->id}")->assertConflict();
    expect(Document::query()->find($document->id))->not->toBeNull();
});
