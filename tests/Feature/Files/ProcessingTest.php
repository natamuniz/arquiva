<?php

declare(strict_types=1);

use App\Enums\ProcessingStatus;
use App\Jobs\ProcessDocumentFile;
use App\Models\Document;
use App\Models\DocumentFile;
use Database\Seeders\Support\SamplePdf;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    $this->user = actingAsRole();
    $this->document = Document::factory()->inUnit($this->user->unit)->create();
});

function uploadAndProcess(object $test, UploadedFile $upload): DocumentFile
{
    // Fila "sync" nos testes: o job roda logo após o commit do upload.
    $id = $test->post("/api/v1/documents/{$test->document->id}/files", ['file' => $upload], ['Accept' => 'application/json'])
        ->assertCreated()
        ->json('data.id');

    return DocumentFile::query()->findOrFail($id);
}

it('processa PDF válido e registra o número de páginas', function (): void {
    $file = uploadAndProcess($this, UploadedFile::fake()->createWithContent('relatorio.pdf', SamplePdf::make(3)));

    expect($file->processing_status)->toBe(ProcessingStatus::Processado)
        ->and($file->page_count)->toBe(3)
        ->and($file->mime_type)->toBe('application/pdf')
        ->and($file->failure_reason)->toBeNull();
});

it('marca como falhou o .pdf que não é PDF de verdade', function (): void {
    $file = uploadAndProcess($this, UploadedFile::fake()->createWithContent('falso.pdf', 'isto é só texto'));

    expect($file->processing_status)->toBe(ProcessingStatus::Falhou)
        ->and($file->failure_reason)->toBe('O conteúdo do arquivo (text/plain) não corresponde à extensão .pdf.');
});

it('marca como falhou o PDF corrompido', function (): void {
    $file = uploadAndProcess($this, UploadedFile::fake()->createWithContent('quebrado.pdf', "%PDF-1.4\nconteúdo truncado"));

    expect($file->processing_status)->toBe(ProcessingStatus::Falhou)
        ->and($file->failure_reason)->toStartWith('PDF inválido ou corrompido');
});

it('processa imagem PNG sem contar páginas', function (): void {
    $file = uploadAndProcess($this, UploadedFile::fake()->image('foto.png', 20, 20));

    expect($file->processing_status)->toBe(ProcessingStatus::Processado)
        ->and($file->page_count)->toBeNull()
        ->and($file->mime_type)->toBe('image/png');
});

it('tem tentativas limitadas e registra o motivo quando elas acabam', function (): void {
    $file = DocumentFile::factory()->status(ProcessingStatus::Pendente)->create(['document_id' => $this->document->id]);
    $job = new ProcessDocumentFile($file->id);

    expect($job->tries)->toBe(3);

    $job->failed(new RuntimeException('timeout no disco'));

    expect($file->fresh()->processing_status)->toBe(ProcessingStatus::Falhou)
        ->and($file->fresh()->failure_reason)->toBe('Falha após 3 tentativas: timeout no disco');
});

it('marca como falhou quando o arquivo físico sumiu', function (): void {
    $file = DocumentFile::factory()->status(ProcessingStatus::Pendente)->create(['document_id' => $this->document->id]);
    Storage::disk((string) config('arquiva.uploads.disk'))->delete($file->storage_path);

    (new ProcessDocumentFile($file->id))->handle();

    expect($file->fresh()->processing_status)->toBe(ProcessingStatus::Falhou);
});
