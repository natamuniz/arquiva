<?php

declare(strict_types=1);

use App\Actions\Files\StoreDocumentFile;
use App\Enums\ProcessingStatus;
use App\Jobs\ProcessDocumentFile;
use App\Models\Document;
use App\Models\DocumentFile;
use Database\Seeders\Support\SamplePdf;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    $this->user = actingAsRole();
    $this->document = Document::factory()->inUnit($this->user->unit)->create();
    $this->disk = Storage::disk((string) config('arquiva.uploads.disk'));
});

function pdfUpload(string $name = 'oficio.pdf', int $pages = 1, string $text = 'Ofício'): UploadedFile
{
    return UploadedFile::fake()->createWithContent($name, SamplePdf::make($pages, $text));
}

it('aceita PDF no rascunho, grava com nome UUID em disco privado e calcula o SHA-256', function (): void {
    Queue::fake();
    $upload = pdfUpload();
    $expectedHash = hash_file('sha256', $upload->getRealPath());

    $response = $this->post("/api/v1/documents/{$this->document->id}/files", ['file' => $upload], ['Accept' => 'application/json'])
        ->assertCreated()
        ->assertJsonPath('data.original_name', 'oficio.pdf')
        ->assertJsonPath('data.sha256', $expectedHash)
        ->assertJsonPath('data.processing_status', 'pendente')
        ->assertJsonPath('data.size_bytes', $upload->getSize())
        ->assertJsonStructure(['data' => ['id', 'mime_type']]);

    $file = DocumentFile::query()->findOrFail($response->json('data.id'));

    expect($file->storage_path)->toBe("documents/{$this->document->id}/{$file->id}")
        ->and($response->json())->not->toHaveKey('data.storage_path');
    $this->disk->assertExists($file->storage_path);
    Queue::assertPushed(ProcessDocumentFile::class, fn (ProcessDocumentFile $job) => $job->fileId === $file->id);
});

it('recusa tipo não permitido', function (): void {
    $this->post("/api/v1/documents/{$this->document->id}/files", [
        'file' => UploadedFile::fake()->create('virus.exe', 10),
    ], ['Accept' => 'application/json'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['file']);
});

it('recusa arquivo acima de 50 MB com 422', function (): void {
    $this->post("/api/v1/documents/{$this->document->id}/files", [
        'file' => UploadedFile::fake()->create('grande.pdf', 51 * 1024, 'application/pdf'),
    ], ['Accept' => 'application/json'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['file']);
});

it('recusa arquivo com o mesmo conteúdo no mesmo documento com 409', function (): void {
    Queue::fake();
    $this->post("/api/v1/documents/{$this->document->id}/files", ['file' => pdfUpload('a.pdf')], ['Accept' => 'application/json'])->assertCreated();

    $this->post("/api/v1/documents/{$this->document->id}/files", ['file' => pdfUpload('copia.pdf')], ['Accept' => 'application/json'])
        ->assertConflict();

    expect(DocumentFile::query()->count())->toBe(1)
        ->and($this->disk->allFiles())->toHaveCount(1);
});

it('garante a unicidade (documento, sha256) no banco', function (): void {
    $file = DocumentFile::factory()->create(['document_id' => $this->document->id]);

    expect(fn () => DB::transaction(fn () => DocumentFile::query()->create([
        ...$file->only(['document_id', 'sha256', 'original_name', 'mime_type', 'size_bytes', 'processing_status']),
        'storage_path' => 'documents/outro',
    ])))->toThrow(UniqueConstraintViolationException::class);
});

it('recusa upload em documento capturado', function (): void {
    $this->document->forceFill(['status' => 'capturado', 'captured_at' => now()])->saveQuietly();

    $this->post("/api/v1/documents/{$this->document->id}/files", ['file' => pdfUpload()], ['Accept' => 'application/json'])
        ->assertConflict();
});

it('remove o arquivo do disco quando a gravação no banco falha', function (): void {
    DocumentFile::creating(function (): void {
        throw new RuntimeException('banco fora do ar');
    });

    expect(fn () => app(StoreDocumentFile::class)->handle($this->document, pdfUpload(), $this->user))
        ->toThrow(RuntimeException::class);

    expect($this->disk->allFiles())->toBe([]);
});

it('remove arquivo do rascunho e apaga do disco depois do commit', function (): void {
    $file = DocumentFile::factory()->create(['document_id' => $this->document->id]);

    $this->deleteJson("/api/v1/files/{$file->id}")->assertNoContent();

    expect(DocumentFile::query()->find($file->id))->toBeNull();
    $this->disk->assertMissing($file->storage_path);
});

it('recusa remover arquivo de documento capturado', function (): void {
    $file = DocumentFile::factory()->create(['document_id' => $this->document->id]);
    $this->document->forceFill(['status' => 'capturado', 'captured_at' => now()])->saveQuietly();

    $this->deleteJson("/api/v1/files/{$file->id}")->assertConflict();
    $this->disk->assertExists($file->storage_path);
});

it('reprocessa só arquivos com falha', function (): void {
    Queue::fake();
    $failed = DocumentFile::factory()->status(ProcessingStatus::Falhou)->create(['document_id' => $this->document->id, 'failure_reason' => 'x']);
    $ok = DocumentFile::factory()->create(['document_id' => $this->document->id]);

    $this->postJson("/api/v1/files/{$failed->id}/reprocess")
        ->assertOk()
        ->assertJsonPath('data.processing_status', 'pendente')
        ->assertJsonPath('data.failure_reason', null);
    $this->postJson("/api/v1/files/{$ok->id}/reprocess")->assertConflict();

    Queue::assertPushed(ProcessDocumentFile::class, 1);
});
