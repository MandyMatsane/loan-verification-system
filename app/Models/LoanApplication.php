<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LoanApplication extends Model
{
    protected $fillable = [
        'user_id',
        'amount_requested',
        'status',
        'no_of_dependents',
        'education',
        'loan_term',
        'cibil_score_band',
        'residential_assets_value',
        'commercial_assets_value',
        'luxury_assets_value',
        'bank_asset_value',
    ];

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
