<?php

namespace App\Services;

use App\Models\AiAssessment;
use App\Models\LoanApplication;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class MlPredictionService
{
    public function getFeatureImportance(): array
    {
        return Cache::remember('ml_feature_importance', 300, function () {
            $baseUrl = rtrim(config('services.ml_service.url', 'http://localhost:8000'), '/');
            $url = $baseUrl . '/api/ml/feature-importance';

            try {
                $response = Http::timeout(15)
                    ->acceptJson()
                    ->get($url);

                if (! $response->successful()) {
                    Log::warning('ML feature importance request failed.', [
                        'status' => $response->status(),
                        'body' => $response->body(),
                    ]);

                    return [];
                }

                $payload = $response->json();
                $items = $payload['features'] ?? $payload['data'] ?? $payload ?? [];

                if (! is_array($items)) {
                    return [];
                }

                $normalized = [];

                foreach ($items as $item) {
                    $feature = $item['feature'] ?? $item['feature_name'] ?? $item['name'] ?? 'Unknown';
                    $importance = $item['importance'] ?? $item['importance_score'] ?? $item['value'] ?? 0;

                    $normalized[] = [
                        'feature' => (string) $feature,
                        'importance' => (float) $importance,
                    ];
                }

                usort($normalized, fn($a, $b) => $b['importance'] <=> $a['importance']);

                return $normalized;
            } catch (Throwable $e) {
                Log::error('ML feature importance service is unreachable.', [
                    'message' => $e->getMessage(),
                ]);

                return [];
            }
        });
    }

    public function predict(LoanApplication $application): ?AiAssessment
    {
        $application->loadMissing('user');

        $payload = $this->buildPayload($application);

        try {
            $response = Http::timeout(15)
                ->acceptJson()
                ->post('http://localhost:8000/api/ml/predict', $payload);

            if (! $response->successful()) {
                Log::warning('ML prediction service request failed.', [
                    'application_id' => $application->id,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                $application->status = 'Pending';
                $application->save();

                return null;
            }
        } catch (Throwable $e) {
            Log::error('ML prediction service is unreachable.', [
                'application_id' => $application->id,
                'message' => $e->getMessage(),
            ]);

            $application->status = 'Pending';
            $application->save();

            return null;
        }

        $data = $response->json();
        $confidenceScore = (float) ($data['confidence_score'] ?? 0);
        $eligibilityOutcome = (string) ($data['eligibility_outcome'] ?? 'Pending');

        $assessment = AiAssessment::updateOrCreate(
            ['application_id' => $application->id],
            [
                'eligibility_outcome' => $eligibilityOutcome,
                'confidence_score' => $confidenceScore,
                'fraud_risk_score' => $data['fraud_risk_score'] ?? null,
                'reasoning_notes' => $data['reasoning_notes'] ?? null,
            ]
        );

        $application->status = $confidenceScore < 70
            ? 'Manual Review'
            : $eligibilityOutcome;

        $application->save();

        return $assessment;
    }

    protected function buildPayload(LoanApplication $application): array
    {
        $user = $application->user;

        return [
            'no_of_dependents' => (int) ($application->no_of_dependents ?? 0),
            'education' => $application->education ?? 'Not Graduate',
            'self_employed' => $this->normalizeSelfEmployed($user?->employment_status),
            'income_annum' => (float) (($user?->monthly_income ?? 0) * 12),
            'loan_amount' => (float) ($application->amount_requested ?? 0),
            'loan_term' => (int) ($application->loan_term ?? 0),
            'cibil_score' => $this->cibilScoreFromBand($application->cibil_score_band),
            'residential_assets_value' => (float) ($application->residential_assets_value ?? 0),
            'commercial_assets_value' => (float) ($application->commercial_assets_value ?? 0),
            'luxury_assets_value' => (float) ($application->luxury_assets_value ?? 0),
            'bank_asset_value' => (float) ($application->bank_asset_value ?? 0),
        ];
    }

    protected function normalizeSelfEmployed(?string $value): string
    {
        if (blank($value)) {
            return 'No';
        }

        $normalized = strtolower(trim($value));

        if (str_contains($normalized, 'self')) {
            return 'Yes';
        }

        if (in_array($normalized, ['yes', 'y', '1'], true)) {
            return 'Yes';
        }

        return 'No';
    }

    protected function cibilScoreFromBand(?string $band): int
    {
        $scoreMap = [
            'Poor' => 425,
            'Fair' => 600,
            'Good' => 700,
            'Excellent' => 825,
        ];

        return $scoreMap[$band] ?? 0;
    }
}
