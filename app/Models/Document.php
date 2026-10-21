<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Document extends Model
{
    protected $fillable = ['application_id', 'type', 'file_path', 'uploaded_at', 'reused_from_document_id'];

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

    public function fileExists(): bool
    {
        return filled($this->file_path) && Storage::disk('public')->exists($this->file_path);
    }

    /**
     * Stored as "{type}-{uuid}-{original name}": the original name only.
     */
    public function displayName(): string
    {
        return preg_replace('/^' . preg_quote($this->type, '/') . '-[0-9a-f-]{36}-/i', '', basename((string) $this->file_path));
    }
}
