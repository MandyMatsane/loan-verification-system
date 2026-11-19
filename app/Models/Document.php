<?php

namespace App\Models;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Document extends Model
{
    /** New uploads go here: not reachable by URL, only through the download route. */
    public const PRIVATE_DISK = 'documents';

    /** Where uploads were stored before; rows from then keep pointing here. */
    public const LEGACY_DISK = 'public';

    protected $fillable = ['application_id', 'type', 'file_path', 'uploaded_at', 'reused_from_document_id', 'disk', 'content_hash'];

    /** The algorithm for content_hash: SHA-256, 64 hex characters. */
    public const HASH_ALGORITHM = 'sha256';

    protected $casts = [
        'uploaded_at' => 'datetime',
    ];

    public function LoanApplication()
    {
        return $this->belongsTo(LoanApplication::class, 'application_id');
    }

    public function ocrResult()
    {
        return  $this->hasOne(OcrResult::class);
    }

    public function reusedFrom()
    {
        return $this->belongsTo(Document::class, 'reused_from_document_id');
    }

    public function isReused(): bool
    {
        return $this->reused_from_document_id !== null;
    }

    /**
     * The disk this document's file is on: rows without one predate the private disk.
     */
    public function storage(): Filesystem
    {
        return Storage::disk($this->disk ?: self::LEGACY_DISK);
    }

    public function fileExists(): bool
    {
        return filled($this->file_path) && $this->storage()->exists($this->file_path);
    }

    /**
     * Stored as "{type}-{uuid}-{original name}": the original name only.
     */
    public function displayName(): string
    {
        return preg_replace('/^' . preg_quote($this->type, '/') . '-[0-9a-f-]{36}-/i', '', basename((string) $this->file_path));
    }
}
