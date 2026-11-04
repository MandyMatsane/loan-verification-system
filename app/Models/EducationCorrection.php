<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * An admin's correction of an application's education: the old and new value, the reason, who made it,
 * the decision before the re-assessment and the status after it.
 */
class EducationCorrection extends Model
{
    protected $fillable = [
        'application_id',
        'admin_id',
        'old_education',
        'new_education',
        'reason',
        'previous_status',
        'previous_outcome',
        'previous_confidence_score',
        'previous_fraud_risk',
        'previous_reasoning_notes',
        'new_status',
    ];

    public function loanApplication()
    {
        return $this->belongsTo(LoanApplication::class, 'application_id');
    }

    public function admin()
    {
        return $this->belongsTo(User::class, 'admin_id');
    }
}
