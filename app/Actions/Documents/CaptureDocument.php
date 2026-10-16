<?php

declare(strict_types=1);

namespace App\Actions\Documents;

use App\Enums\DocumentStatus;
use App\Enums\ProcessingStatus;
use App\Exceptions\ConflictException;
use App\Exceptions\UnprocessableException;
use App\Models\Classification;
use App\Models\Document;
use App\Models\RetentionRule;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;

class CaptureDocument
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * Valida todas as pendências de uma vez e, se estiver tudo certo, captura o documento
     * guardando a versão vigente da regra de temporalidade (snapshot).
     */
    public function handle(Document $document): Document
    {
        return DB::transaction(function () use ($document): Document {
            $document = Document::query()->lockForUpdate()->findOrFail($document->id);

            if (! $document->isDraft()) {
                throw new ConflictException('O documento já foi capturado.');
            }

            [$errors, $rule] = $this->pendencies($document);

            if ($errors !== [] || $rule === null) {
                throw new UnprocessableException('O documento tem pendências e não pode ser capturado.', $errors);
            }

            $before = ['status' => $document->status->value, 'retention_rule_id' => null, 'captured_at' => null];

            $document->forceFill([
                'status' => DocumentStatus::Capturado,
                'captured_at' => now(),
                'retention_rule_id' => $rule->id,
            ])->saveQuietly();

            $this->audit->record('document.captured', $document, $before, [
                'status' => $document->status->value,
                'retention_rule_id' => $rule->id,
                'retention_rule_version' => $rule->version,
                'captured_at' => $document->captured_at?->toIso8601String(),
            ]);

            return $document;
        });
    }

    /**
     * @return array{0: array<string, list<string>>, 1: RetentionRule|null}
     */
    private function pendencies(Document $document): array
    {
        $errors = [];
        $rule = null;

        if (blank($document->title)) {
            $errors['title'][] = 'Informe o título.';
        }

        if ($document->produced_at === null) {
            $errors['produced_at'][] = 'Informe a data de produção.';
        } elseif ($document->produced_at->isFuture()) {
            $errors['produced_at'][] = 'A data de produção não pode ser futura.';
        }

        $classification = $document->classification_id === null
            ? null
            : Classification::query()->with('currentRetentionRule')->find($document->classification_id);

        if ($classification === null) {
            $errors['classification_id'][] = 'Informe a classe.';
        } elseif (! $classification->active) {
            $errors['classification_id'][] = "A classe {$classification->code} está inativa.";
        } elseif ($classification->currentRetentionRule === null) {
            $errors['classification_id'][] = "A classe {$classification->code} não tem regra de temporalidade vigente.";
        } else {
            $rule = $classification->currentRetentionRule;
        }

        $files = $document->files()->get(['id', 'original_name', 'processing_status']);

        if ($files->isEmpty()) {
            $errors['files'][] = 'Envie pelo menos um arquivo.';
        }

        foreach ($files as $file) {
            if ($file->processing_status !== ProcessingStatus::Processado) {
                $errors['files'][] = "O arquivo {$file->original_name} está \"{$file->processing_status->value}\": "
                    .'aguarde o processamento, reprocesse ou remova o arquivo.';
            }
        }

        return [$errors, $rule];
    }
}
