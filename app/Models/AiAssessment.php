<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiAssessment extends Model
{
    protected $fillable = ['application_id', 'eligibility_outcome', 'confidence_score', 'fraud_risk_score', 'reasoning_notes'];

    public function loanApplication()
    {
        return $this->belongsTo(LoanApplication::class, 'application_id');
    }
}
