<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Document extends Model
{
    protected $fillable = ['application_id', 'type', 'file_path', 'uploaded_at'];

    public function LoanApplication()
    {
        return $this->belongsTo(LoanApplication::class, 'application_id');
    }

    public function ocrResult()
    {
        return  $this->hasOne(OcrResult::class);
    }
}
