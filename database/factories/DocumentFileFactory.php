<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ProcessingStatus;
use App\Models\Document;
use App\Models\DocumentFile;
use Database\Seeders\Support\SamplePdf;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Cria o registro e grava um PDF de exemplo no disco configurado (use Storage::fake nos testes).
 *
 * @extends Factory<DocumentFile>
 */
class DocumentFileFactory extends Factory
{
    protected $model = DocumentFile::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'document_id' => Document::factory(),
            'original_name' => fake()->slug(3).'.pdf',
            'mime_type' => 'application/pdf',
            'processing_status' => ProcessingStatus::Processado,
            'page_count' => 1,
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (DocumentFile $file): void {
            if (array_key_exists('storage_path', $file->getAttributes())) {
                return;
            }

            $id = (string) Str::uuid();
            $contents = SamplePdf::make(1, 'Arquivo '.$id);
            $path = "documents/{$file->document_id}/{$id}";

            Storage::disk((string) config('arquiva.uploads.disk'))->put($path, $contents);

            $file->id = $id;
            $file->storage_path = $path;
            $file->sha256 = hash('sha256', $contents);
            $file->size_bytes = strlen($contents);
        });
    }

    public function status(ProcessingStatus $status): static
    {
        return $this->state(['processing_status' => $status]);
    }
}
