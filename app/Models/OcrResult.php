<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OcrResult extends Model
{
    protected $fillable = ['document_id', 'extracted_text', 'extracted_fields', 'confidence_score'];

    protected $casts = [
        'extracted_fields' => 'array',
    ];

    public function document()
    {
        return $this->belongsTo(Document::class);
    }
}
