<?php

namespace App\Services;

use App\Models\AiAssessment;
use App\Models\LoanApplication;
use Illuminate\Contracts\Cache\Lock;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class ApplicationAssessor
{
    /** Automatic retries stop after this many failed attempts; an admin can still retry by hand. */
    public const MAX_AUTOMATIC_ATTEMPTS = 5;

    /** Minimum gap between automatic attempts on the same application. */
    public const RETRY_GAP_SECONDS = 120;

    /** Automatic retries run during a page visit, so they must not hold the page up. */
    public const AUTOMATIC_TIMEOUT_SECONDS = 5;

    /** "Retry all pending" handles at most this many applications per click. */
    public const BATCH_LIMIT = 5;

    public const DEFAULT_TIMEOUT_SECONDS = 60;

    /** Reasoning notes start with these when a check sends an application to manual review. */
    public const DOCUMENT_CHECK_PREFIX = 'Document check:';

    public const AFFORDABILITY_CHECK_PREFIX = 'Affordability check:';

    public const NOTE_NO_INCOME = 'No income on file, affordability not checked';

    public function __construct(
        protected MlPredictionService $mlPredictionService,
        protected FraudCheckService $fraudCheckService,
    ) {
    }

    /**
     * Predict, then run the document check. Every call counts an attempt; success resets the count.
     */
    public function assess(LoanApplication $application, int $timeoutSeconds = self::DEFAULT_TIMEOUT_SECONDS): bool
    {
        $application->assessment_attempts = (int) $application->assessment_attempts + 1;
        $application->last_assessment_attempt_at = now();
        $application->save();

        $predictionStartedAt = microtime(true);
        $assessment = $this->mlPredictionService->predict($application, $timeoutSeconds);

        Log::info(sprintf('TIMING ml prediction took %.2fs', microtime(true) - $predictionStartedAt), [
            'application_id' => $application->id,
        ]);

        // affordability first: the document check then puts its findings in front,
        // so the notes read document findings, affordability findings, the model's notes, then neutral notes
        $this->applyAffordabilityCheck($application, $assessment);
        $this->applyDocumentCheck($application, $assessment);

        if ($assessment === null) {
            return false;
        }

        $application->assessment_attempts = 0;
        $application->save();

        return true;
    }

    /**
     * The lock that stops two requests assessing the same application at once.
     */
    public function lock(LoanApplication $application, int $seconds): Lock
    {
        return Cache::lock('application-assessment:' . $application->id, $seconds);
    }

    /**
     * Retry one retryable application under its lock.
     * Returns null when it was skipped: locked by another request, or no longer retryable.
     */
    public function retry(LoanApplication $application, int $timeoutSeconds = self::DEFAULT_TIMEOUT_SECONDS, ?callable $stillDue = null): ?bool
    {
        $lock = $this->lock($application, $timeoutSeconds + 30);

        if (! $lock->get()) {
            return null;
        }

        try {
            // another request may have assessed it while this one waited
            $application->refresh();

            if (! $application->isRetryable() || ($stillDue && ! $stillDue($application))) {
                return null;
            }

            return $this->assess($application, $timeoutSeconds);
        } finally {
            $lock->release();
        }
    }

    /**
     * Automatic retry during a page visit: at most one due application, never breaking the page.
     */
    public function retryDue(?int $userId = null, ?int $preferredApplicationId = null): ?bool
    {
        try {
            $query = LoanApplication::query()->retryable()->dueForAutomaticRetry();

            if ($userId !== null) {
                $query->where('user_id', $userId);
            }

            if ($preferredApplicationId !== null) {
                $query->orderByRaw('CASE WHEN id = ? THEN 0 ELSE 1 END', [$preferredApplicationId]);
            }

            $application = $query
                ->orderByRaw('CASE WHEN last_assessment_attempt_at IS NULL THEN 0 ELSE 1 END')
                ->orderBy('last_assessment_attempt_at')
                ->orderBy('id')
                ->first();

            if ($application === null) {
                return null;
            }

            return $this->retry(
                $application,
                self::AUTOMATIC_TIMEOUT_SECONDS,
                fn (LoanApplication $fresh) => $fresh->isDueForAutomaticRetry()
            );
        } catch (Throwable $e) {
            Log::warning('Automatic re-assessment failed.', ['message' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * "Retry all pending": up to BATCH_LIMIT retryable applications, oldest first.
     * Stops at the first failure, because the rest would wait on the same unavailable service.
     *
     * @return array{tried: int, assessed: int, stopped_early: bool}
     */
    public function retryPending(): array
    {
        $applications = LoanApplication::query()->retryable()->orderBy('id')->limit(self::BATCH_LIMIT)->get();
        $tried = 0;
        $assessed = 0;

        foreach ($applications as $application) {
            $result = $this->retry($application);

            if ($result === null) {
                continue;
            }

            $tried++;

            if ($result === false) {
                return ['tried' => $tried, 'assessed' => $assessed, 'stopped_early' => $tried < $applications->count()];
            }

            $assessed++;
        }

        return ['tried' => $tried, 'assessed' => $assessed, 'stopped_early' => false];
    }

    /**
     * Run the document check and, when it raises flags, send the application to manual review.
     * With no assessment (the ML call failed) the application stays Pending.
     */
    protected function applyDocumentCheck(LoanApplication $application, ?AiAssessment $assessment): void
    {
        $checkStartedAt = microtime(true);
        $result = $this->fraudCheckService->check($application);

        Log::info(sprintf('TIMING document check took %.2fs', microtime(true) - $checkStartedAt), [
            'application_id' => $application->id,
            'flags' => count($result['flags']),
        ]);

        if ($assessment === null) {
            return;
        }

        $this->appendNotes($assessment, $result['notes']);

        if ($result['flags'] === []) {
            $assessment->save();

            return;
        }

        $this->prependFindings($assessment, self::DOCUMENT_CHECK_PREFIX, $result['flags']);
        $assessment->fraud_risk_score = 'high';
        $assessment->save();

        $application->status = 'Manual Review';
        $application->save();
    }

    /**
     * Compare the monthly repayment with the stated monthly income. Above the limit the application
     * goes to manual review and the fraud risk becomes at least medium.
     * With no assessment (the ML call failed) the application stays Pending.
     */
    protected function applyAffordabilityCheck(LoanApplication $application, ?AiAssessment $assessment): void
    {
        if ($assessment === null) {
            return;
        }

        $income = $application->statedMonthlyIncome();

        if ($income === null || $income <= 0) {
            $this->appendNotes($assessment, [self::NOTE_NO_INCOME]);
            $assessment->save();

            return;
        }

        $repayment = $application->monthlyRepaymentForChecks();
        $limit = (float) config('loan.max_repayment_ratio');

        if ($repayment === null || $repayment / $income <= $limit) {
            return;
        }

        $this->prependFindings($assessment, self::AFFORDABILITY_CHECK_PREFIX, [sprintf(
            'Requested repayment is %s%% of stated income (limit %s%%)',
            $this->percent($repayment / $income),
            $this->percent($limit)
        )]);

        // never lower an existing high
        if (strtolower(trim((string) $assessment->fraud_risk_score)) !== 'high') {
            $assessment->fraud_risk_score = 'medium';
        }

        $assessment->save();

        $application->status = 'Manual Review';
        $application->save();
    }

    /**
     * Put a "Prefix:\n- finding" block in front of the notes.
     *
     * @param  string[]  $findings
     */
    protected function prependFindings(AiAssessment $assessment, string $prefix, array $findings): void
    {
        $notes = $prefix . "\n- " . implode("\n- ", $findings);

        if (filled($assessment->reasoning_notes)) {
            $notes .= "\n\n" . $assessment->reasoning_notes;
        }

        $assessment->reasoning_notes = $notes;
    }

    /**
     * Add neutral notes at the end; they never change the status or the fraud risk.
     *
     * @param  string[]  $notes
     */
    protected function appendNotes(AiAssessment $assessment, array $notes): void
    {
        if ($notes === []) {
            return;
        }

        $lines = implode("\n", array_map(fn (string $note) => 'Note: ' . $note, $notes));

        $assessment->reasoning_notes = filled($assessment->reasoning_notes)
            ? $assessment->reasoning_notes . "\n\n" . $lines
            : $lines;
    }

    /**
     * 0.35 => "35", 0.70589 => "70.6"
     */
    protected function percent(float $fraction): string
    {
        return rtrim(rtrim(number_format($fraction * 100, 1), '0'), '.');
    }
}
