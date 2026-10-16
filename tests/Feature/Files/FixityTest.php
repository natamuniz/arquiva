<?php

declare(strict_types=1);

use App\Models\AuditEvent;
use App\Models\DocumentFile;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    $this->disk = Storage::disk((string) config('arquiva.uploads.disk'));
});

it('começa sem verificação: fixity_ok nulo', function (): void {
    expect(DocumentFile::factory()->create()->fresh()->fixity_ok)->toBeNull();
});

it('marca arquivo íntegro como ok e atualiza a data da verificação', function (): void {
    $file = DocumentFile::factory()->create();
    $this->freezeTime();

    $this->artisan('arquiva:check-fixity')->assertSuccessful();

    $file->refresh();
    expect($file->fixity_ok)->toBeTrue()
        ->and($file->last_fixity_check_at?->toIso8601String())->toBe(now()->toIso8601String())
        ->and(AuditEvent::query()->where('action', 'file.fixity_failed')->count())->toBe(0);
});

it('detecta arquivo corrompido e grava file.fixity_failed', function (): void {
    $file = DocumentFile::factory()->create();
    $this->disk->put($file->storage_path, 'conteúdo adulterado');

    $this->artisan('arquiva:check-fixity')->assertSuccessful();

    expect($file->fresh()->fixity_ok)->toBeFalse();

    $event = AuditEvent::query()->where('action', 'file.fixity_failed')->sole();
    expect($event->after['reason'])->toBe('hash divergente')
        ->and($event->after['sha256'])->toBe(hash('sha256', 'conteúdo adulterado'))
        ->and($event->before['sha256'])->toBe($file->sha256)
        ->and($event->user_id)->toBeNull();
});

it('detecta arquivo ausente', function (): void {
    $file = DocumentFile::factory()->create();
    $this->disk->delete($file->storage_path);

    $this->artisan('arquiva:check-fixity')->assertSuccessful();

    expect($file->fresh()->fixity_ok)->toBeFalse()
        ->and(AuditEvent::query()->where('action', 'file.fixity_failed')->sole()->after['reason'])->toBe('arquivo ausente');
});

it('verifica em lotes', function (): void {
    DocumentFile::factory()->count(5)->create();

    $this->artisan('arquiva:check-fixity', ['--batch' => 2])
        ->expectsOutputToContain('5 arquivo(s) enviados para verificação em 3 lote(s).')
        ->assertSuccessful();

    expect(DocumentFile::query()->where('fixity_ok', true)->count())->toBe(5);
});

it('não enche a trilha com alterações da verificação de rotina', function (): void {
    DocumentFile::factory()->create();
    $before = AuditEvent::query()->count();

    $this->artisan('arquiva:check-fixity');

    expect(AuditEvent::query()->count())->toBe($before);
});

it('está agendado diariamente', function (): void {
    $events = collect(app(Schedule::class)->events())->map(fn ($e) => [$e->command, $e->expression]);

    expect($events->first(fn ($e) => str_contains((string) $e[0], 'arquiva:check-fixity')))->not->toBeNull()
        ->and($events->first(fn ($e) => str_contains((string) $e[0], 'arquiva:check-fixity'))[1])->toBe('0 2 * * *');
});
