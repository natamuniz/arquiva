<?php

declare(strict_types=1);

use App\Models\DocumentFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    $this->disk = Storage::disk((string) config('arquiva.uploads.disk'));
});

it('apaga arquivo sem registro criado há mais de 24 horas', function (): void {
    $this->disk->put('documents/x/orfao-antigo', 'sobra');
    touch($this->disk->path('documents/x/orfao-antigo'), now()->subDays(2)->getTimestamp());

    $this->artisan('arquiva:prune-orphans')
        ->expectsOutputToContain('1 arquivo(s) órfão(s) removido(s).')
        ->assertSuccessful();

    $this->disk->assertMissing('documents/x/orfao-antigo');
});

it('mantém órfão recente (upload em andamento) e arquivos registrados', function (): void {
    $file = DocumentFile::factory()->create();
    touch($this->disk->path($file->storage_path), now()->subDays(10)->getTimestamp());
    $this->disk->put('documents/x/orfao-recente', 'em andamento');

    $this->artisan('arquiva:prune-orphans')->assertSuccessful();

    $this->disk->assertExists('documents/x/orfao-recente');
    $this->disk->assertExists($file->storage_path);
});

it('só lista no modo dry-run', function (): void {
    $this->disk->put('documents/x/orfao', 'sobra');
    touch($this->disk->path('documents/x/orfao'), now()->subDays(2)->getTimestamp());

    $this->artisan('arquiva:prune-orphans', ['--dry-run' => true])->assertSuccessful();

    $this->disk->assertExists('documents/x/orfao');
});
