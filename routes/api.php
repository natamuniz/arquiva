<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\AuditEventController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\ClassificationController;
use App\Http\Controllers\Api\V1\DocumentController;
use App\Http\Controllers\Api\V1\DocumentFileController;
use App\Http\Controllers\Api\V1\RetentionRuleController;
use Illuminate\Support\Facades\Route;

// Prefixo /api/v1 definido em bootstrap/app.php.

Route::post('auth/login', [AuthController::class, 'login'])->name('auth.login');

Route::middleware('auth:sanctum')->group(function (): void {
    Route::post('auth/logout', [AuthController::class, 'logout'])->name('auth.logout');
    Route::get('auth/me', [AuthController::class, 'me'])->name('auth.me');

    // Plano de classificação
    Route::post('classifications/import', [ClassificationController::class, 'import'])->name('classifications.import');
    Route::get('classifications/{classification}/tree', [ClassificationController::class, 'tree'])->name('classifications.tree');
    Route::post('classifications/{classification}/deactivate', [ClassificationController::class, 'deactivate'])->name('classifications.deactivate');
    Route::post('classifications/{classification}/activate', [ClassificationController::class, 'activate'])->name('classifications.activate');
    Route::apiResource('classifications', ClassificationController::class);

    // Tabela de temporalidade
    Route::get('classifications/{classification}/retention-rules', [RetentionRuleController::class, 'index'])->name('retention-rules.index');
    Route::post('classifications/{classification}/retention-rules', [RetentionRuleController::class, 'store'])->name('retention-rules.store');
    Route::get('retention-rules/{retentionRule}', [RetentionRuleController::class, 'show'])->name('retention-rules.show');
    Route::put('retention-rules/{retentionRule}', [RetentionRuleController::class, 'update'])->name('retention-rules.update');

    // Documentos
    Route::post('documents/{document}/capture', [DocumentController::class, 'capture'])->name('documents.capture');
    Route::get('documents/{document}/history', [DocumentController::class, 'history'])->name('documents.history');
    Route::apiResource('documents', DocumentController::class);

    // Arquivos
    Route::post('documents/{document}/files', [DocumentFileController::class, 'store'])->name('files.store');
    Route::get('files/{file}', [DocumentFileController::class, 'show'])->name('files.show');
    Route::get('files/{file}/download', [DocumentFileController::class, 'download'])->name('files.download');
    Route::post('files/{file}/reprocess', [DocumentFileController::class, 'reprocess'])->name('files.reprocess');
    Route::delete('files/{file}', [DocumentFileController::class, 'destroy'])->name('files.destroy');

    // Auditoria (somente leitura: não existe rota de escrita)
    Route::get('audit-events', [AuditEventController::class, 'index'])->name('audit-events.index');
});
