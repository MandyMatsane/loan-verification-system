<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LoanApplication extends Model
{
    protected $fillable = ['user_id', 'amount_requested', 'status'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function documents()
    {
        return $this->hasMany(Document::class, 'application_id');
    }

    public function aiAssessment()
    {
        return $this->hasOne(AiAssessment::class, 'application_id');
    }
}
