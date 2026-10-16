<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ProcessingStatus;
use App\Observers\AuditObserver;
use Database\Factories\DocumentFileFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $document_id
 * @property string $original_name
 * @property string $storage_path
 * @property string $mime_type
 * @property int $size_bytes
 * @property string $sha256
 * @property ProcessingStatus $processing_status
 * @property int|null $page_count
 * @property string|null $failure_reason
 * @property Carbon|null $last_fixity_check_at
 * @property bool|null $fixity_ok
 * @property int|null $created_by
 * @property Carbon $created_at
 * @property-read Document $document
 */
#[ObservedBy(AuditObserver::class)]
class DocumentFile extends Model
{
    /** @use HasFactory<DocumentFileFactory> */
    use HasFactory;

    use HasUuids;

    protected $fillable = [
        'document_id', 'original_name', 'storage_path', 'mime_type', 'size_bytes', 'sha256',
        'processing_status', 'page_count', 'failure_reason', 'last_fixity_check_at', 'fixity_ok', 'created_by',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'size_bytes' => 'integer',
            'page_count' => 'integer',
            'processing_status' => ProcessingStatus::class,
            'last_fixity_check_at' => 'datetime',
            'fixity_ok' => 'boolean',
        ];
    }

    /** @return BelongsTo<Document, $this> */
    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function extension(): string
    {
        return strtolower(pathinfo($this->original_name, PATHINFO_EXTENSION));
    }
}
