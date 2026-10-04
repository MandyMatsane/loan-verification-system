<?php

namespace Tests\Feature;

use App\Models\AiAssessment;
use App\Models\LoanApplication;
use App\Models\User;
use App\Services\MlPredictionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MlPredictionServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_sends_the_expected_ml_payload_and_updates_application_status(): void
    {
        Http::fake([
            'http://localhost:8000/api/ml/predict' => Http::response([
                'eligibility_outcome' => 'Approved',
                'confidence_score' => 86.5,
                'fraud_risk_score' => 'low',
                'reasoning_notes' => 'Strong profile and good repayment capacity.',
            ], 200),
        ]);

        $user = User::factory()->create([
            'employment_status' => 'Self-employed',
            'monthly_income' => 6000,
        ]);

        $application = LoanApplication::create([
            'user_id' => $user->id,
            'amount_requested' => 250000,
            'status' => 'Pending',
            'no_of_dependents' => 2,
            'education' => 'Graduate',
            'loan_term' => 36,
            'cibil_score_band' => 'Good',
            'residential_assets_value' => 150000,
            'commercial_assets_value' => 120000,
            'luxury_assets_value' => 40000,
            'bank_asset_value' => 80000,
        ]);

        $service = app(MlPredictionService::class);
        $service->predict($application);

        Http::assertSent(function ($request) {
            $payload = $request->data();

            return $request->url() === 'http://localhost:8000/api/ml/predict'
                && $payload['no_of_dependents'] === 2
                && $payload['education'] === 'Graduate'
                && $payload['self_employed'] === 'Yes'
                && $payload['income_annum'] == 72000.0
                && $payload['loan_amount'] == 250000.0
                && $payload['loan_term'] === 36
                && $payload['cibil_score'] === 700
                && $payload['residential_assets_value'] == 150000.0
                && $payload['commercial_assets_value'] == 120000.0
                && $payload['luxury_assets_value'] == 40000.0
                && $payload['bank_asset_value'] == 80000.0;
        });

        $this->assertDatabaseHas('ai_assessments', [
            'application_id' => $application->id,
            'eligibility_outcome' => 'Approved',
            'confidence_score' => 86.5,
            'fraud_risk_score' => 'low',
        ]);

        $this->assertSame('Approved', $application->refresh()->status);
    }

    public function test_it_sets_manual_review_when_confidence_is_below_70_percent(): void
    {
        Http::fake([
            'http://localhost:8000/api/ml/predict' => Http::response([
                'eligibility_outcome' => 'Rejected',
                'confidence_score' => 68.4,
                'fraud_risk_score' => 'medium',
                'reasoning_notes' => 'Profile is borderline for approval.',
            ], 200),
        ]);

        $user = User::factory()->create([
            'employment_status' => 'Employed',
            'monthly_income' => 4500,
        ]);

        $application = LoanApplication::create([
            'user_id' => $user->id,
            'amount_requested' => 180000,
            'status' => 'Pending',
            'no_of_dependents' => 1,
            'education' => 'Not Graduate',
            'loan_term' => 24,
            'cibil_score_band' => 'Fair',
            'residential_assets_value' => 90000,
            'commercial_assets_value' => 50000,
            'luxury_assets_value' => 15000,
            'bank_asset_value' => 70000,
        ]);

        app(MlPredictionService::class)->predict($application);

        $this->assertSame('Manual Review', $application->refresh()->status);
    }

    public function test_admin_dashboard_lists_applications_and_feature_importance(): void
    {
        Http::fake([
            'http://localhost:8000/api/ml/feature-importance' => Http::response([
                'features' => [
                    // the service returns importance as a percentage
                    ['feature' => 'loan_amount', 'importance' => 42.0],
                    ['feature' => 'cibil_score', 'importance' => 21.0],
                ],
            ], 200),
        ]);

        /** @var User $admin */
        $admin = User::factory()->create(['role' => 'admin', 'name' => 'Admin User']);
        /** @var User $applicant */
        $applicant = User::factory()->create(['role' => 'applicant', 'name' => 'Jane Applicant']);

        $application = LoanApplication::create([
            'user_id' => $applicant->id,
            'amount_requested' => 50000,
            'status' => 'Approved',
            'no_of_dependents' => 2,
            'education' => 'Graduate',
            'loan_term' => 24,
            'cibil_score_band' => 'Good',
            'residential_assets_value' => 200000,
            'commercial_assets_value' => 25000,
            'luxury_assets_value' => 15000,
            'bank_asset_value' => 100000,
        ]);

        AiAssessment::create([
            'application_id' => $application->id,
            'eligibility_outcome' => 'Approved',
            'confidence_score' => 88.2,
            'fraud_risk_score' => 'low',
            'reasoning_notes' => 'Strong repayment profile.',
        ]);

        $response = $this->actingAs($admin)->get('/admin/dashboard');

        $response
            ->assertOk()
            ->assertSee('Operations overview')
            ->assertSee('Jane Applicant')
            ->assertSee('Approved')
            ->assertSee('88.2%')
            ->assertSee('Loan amount')
            ->assertSee('42.0%')
            ->assertSee('CIBIL score')
            ->assertSee('21.0%');
    }
}
