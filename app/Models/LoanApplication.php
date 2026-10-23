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
        'interest_rate',
        'monthly_repayment',
        'total_repayable',
        'stated_monthly_income',
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

    /**
     * "1 month", "N months", or "-" when no term was given.
     */
    public function loanTermLabel(): string
    {
        if ($this->loan_term === null) {
            return '-';
        }

        return $this->loan_term . ((int) $this->loan_term === 1 ? ' month' : ' months');
    }

    /**
     * The monthly income the applicant stated when they applied; for older applications, their current profile income.
     */
    public function statedMonthlyIncome(): ?float
    {
        $income = $this->stated_monthly_income ?? $this->user?->monthly_income;

        return $income === null ? null : (float) $income;
    }

    /**
     * The monthly repayment the affordability check uses: the saved estimate, else amount / term.
     */
    public function monthlyRepaymentForChecks(): ?float
    {
        if ($this->monthly_repayment !== null) {
            return (float) $this->monthly_repayment;
        }

        if ($this->amount_requested === null || (int) $this->loan_term < 1) {
            return null;
        }

        return (float) $this->amount_requested / (int) $this->loan_term;
    }

    /**
     * The repayment estimate saved at submission, formatted for display; "-" for applications from before it existed.
     *
     * @return array{monthly_repayment: string, total_repayable: string, interest: string}
     */
    public function repaymentEstimate(): array
    {
        $money = fn ($value) => $value === null ? '-' : 'R' . number_format((float) $value, 2);

        $interest = '-';
        if ($this->total_repayable !== null && $this->amount_requested !== null) {
            $interest = $money(round((float) $this->total_repayable - (float) $this->amount_requested, 2));

            if ($this->interest_rate !== null) {
                $interest .= ' at ' . rtrim(rtrim(number_format((float) $this->interest_rate * 100, 2), '0'), '.') . '% a year';
            }
        }

        return [
            'monthly_repayment' => $money($this->monthly_repayment),
            'total_repayable' => $money($this->total_repayable),
            'interest' => $interest,
        ];
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
