<?php

namespace App\Models;

use App\Services\ApplicationAssessor;
use Illuminate\Database\Eloquent\Builder;
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

    protected $casts = [
        'assessment_attempts' => 'integer',
        'last_assessment_attempt_at' => 'datetime',
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

    /**
     * Pending (any capitalisation) with no AI assessment: the only applications a retry may touch.
     */
    public function scopeRetryable(Builder $query): void
    {
        $query->whereRaw('LOWER(TRIM(status)) = ?', ['pending'])->whereDoesntHave('aiAssessment');
    }

    public function scopeDueForAutomaticRetry(Builder $query): void
    {
        $query->where('assessment_attempts', '<', ApplicationAssessor::MAX_AUTOMATIC_ATTEMPTS)
            ->where(function (Builder $query) {
                $query->whereNull('last_assessment_attempt_at')
                    ->orWhere('last_assessment_attempt_at', '<', now()->subSeconds(ApplicationAssessor::RETRY_GAP_SECONDS));
            });
    }

    public function isRetryable(): bool
    {
        return strtolower(trim((string) $this->status)) === 'pending' && $this->aiAssessment === null;
    }

    public function isDueForAutomaticRetry(): bool
    {
        return $this->isRetryable()
            && (int) $this->assessment_attempts < ApplicationAssessor::MAX_AUTOMATIC_ATTEMPTS
            && ($this->last_assessment_attempt_at === null
                || $this->last_assessment_attempt_at->lt(now()->subSeconds(ApplicationAssessor::RETRY_GAP_SECONDS)));
    }

    /**
     * Automatic retries have given up; an admin has to retry it.
     */
    public function needsManualRetry(): bool
    {
        return $this->isRetryable() && (int) $this->assessment_attempts >= ApplicationAssessor::MAX_AUTOMATIC_ATTEMPTS;
    }
}
