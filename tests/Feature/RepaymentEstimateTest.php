<?php

namespace Tests\Feature;

use App\Models\AiAssessment;
use App\Models\LoanApplication;
use App\Models\User;
use App\Services\OcrService;
use App\Services\RepaymentCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Mockery;
use Tests\TestCase;

class RepaymentEstimateTest extends TestCase
{
    use RefreshDatabase;

    private function calculator(): RepaymentCalculator
    {
        return new RepaymentCalculator();
    }

    public function test_the_formula_matches_known_values(): void
    {
        $this->assertSame([
            'monthly_repayment' => 926.35,
            'total_repayable' => 11116.14,
            'total_interest' => 1116.14,
            'rate' => 0.20,
        ], $this->calculator()->estimate(10000, 12, 0.20));

        $estimate = $this->calculator()->estimate(50000, 60, 0.12);
        $this->assertSame(1112.22, $estimate['monthly_repayment']);
        $this->assertSame(66733.34, $estimate['total_repayable']);
        $this->assertSame(16733.34, $estimate['total_interest']);

        // a single month is the amount plus one month's interest
        $this->assertSame(1016.67, $this->calculator()->estimate(1000, 1, 0.20)['monthly_repayment']);
    }

    public function test_the_rate_defaults_to_the_config_value(): void
    {
        config(['loan.annual_interest_rate' => 0.20]);
        $this->assertSame(926.35, $this->calculator()->estimate(10000, 12)['monthly_repayment']);
        $this->assertSame(0.20, $this->calculator()->estimate(10000, 12)['rate']);

        config(['loan.annual_interest_rate' => 0.12]);
        $this->assertSame(1112.22, $this->calculator()->estimate(50000, 60)['monthly_repayment']);
    }

    public function test_a_zero_rate_splits_the_amount_evenly(): void
    {
        $this->assertSame([
            'monthly_repayment' => 333.33,
            'total_repayable' => 1000.0,
            'total_interest' => 0.0,
            'rate' => 0.0,
        ], $this->calculator()->estimate(1000, 3, 0.0));
    }

    public function test_invalid_input_is_refused(): void
    {
        $cases = [
            [0, 12, 0.2],
            [-500, 12, 0.2],
            [1000, 0, 0.2],
            [1000, -3, 0.2],
            [1000, 12, -0.1],
            [NAN, 12, 0.2],
        ];

        foreach ($cases as [$amount, $term, $rate]) {
            try {
                $this->calculator()->estimate($amount, $term, $rate);
                $this->fail(sprintf('No exception for amount %s, term %s, rate %s', $amount, $term, $rate));
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_the_estimate_is_worked_out_and_stored_at_submission(): void
    {
        config(['loan.annual_interest_rate' => 0.20]);
        Storage::fake('public');
        Http::fake(['http://localhost:8000/api/ml/predict' => Http::response([], 503)]);

        $ocr = Mockery::mock(OcrService::class);
        $ocr->shouldReceive('process')->andReturnNull();
        $this->app->instance(OcrService::class, $ocr);

        /** @var User $user */
        $user = User::factory()->create(['id_number' => '8001015009087', 'date_of_birth' => '1980-01-01', 'monthly_income' => 20000]);

        $this->actingAs($user)->post('/applications', [
            'amount_requested' => '10000',
            'no_of_dependents' => 0,
            'education' => 'Graduate',
            'loan_term' => 12,
            'cibil_score_band' => 'Good',
            'residential_assets_value' => 0,
            'commercial_assets_value' => 0,
            'luxury_assets_value' => 0,
            'bank_asset_value' => 0,
            // numbers sent by the form are ignored
            'interest_rate' => '0',
            'monthly_repayment' => '1',
            'total_repayable' => '1',
            'id_document' => UploadedFile::fake()->image('id.jpg'),
            'payslip' => UploadedFile::fake()->image('payslip.jpg'),
            'bank_statement' => UploadedFile::fake()->image('bank.jpg'),
        ])->assertSessionHasNoErrors()->assertRedirect();

        $application = LoanApplication::where('user_id', $user->id)->firstOrFail();
        $this->assertEquals(0.20, (float) $application->interest_rate);
        $this->assertEquals(926.35, (float) $application->monthly_repayment);
        $this->assertEquals(11116.14, (float) $application->total_repayable);

        $this->actingAs($user)->get(route('applications.confirmation', $application))
            ->assertOk()
            ->assertSeeInOrder(['Monthly repayment', 'R926.35', 'Total repayable', 'R11,116.14', 'Interest', 'R1,116.14 at 20% a year'])
            ->assertSee('Estimate only. Final terms are set by the lender.');

        $this->actingAs($user)->get(route('applications.show', $application))
            ->assertOk()
            ->assertSeeInOrder(['Monthly repayment', 'R926.35', 'Total repayable', 'R11,116.14', 'Interest', 'R1,116.14 at 20% a year']);

        /** @var User $admin */
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->get(route('admin.applications.show', $application))
            ->assertOk()
            ->assertSeeInOrder(['Monthly repayment', 'R926.35', 'Total repayable', 'R11,116.14', 'Interest', 'R1,116.14 at 20% a year']);
    }

    public function test_old_applications_without_an_estimate_show_a_dash(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        $application = LoanApplication::create([
            'user_id' => $user->id,
            'amount_requested' => 5000,
            'loan_term' => 6,
            'status' => 'Approved',
        ]);

        AiAssessment::create([
            'application_id' => $application->id,
            'eligibility_outcome' => 'Approved',
            'confidence_score' => 90,
            'fraud_risk_score' => 'low',
            'reasoning_notes' => 'Fine.',
        ]);

        $this->assertNull($application->fresh()->monthly_repayment);

        foreach ([
            [$user, route('applications.confirmation', $application)],
            [$user, route('applications.show', $application)],
            [User::factory()->create(['role' => 'admin']), route('admin.applications.show', $application)],
        ] as [$viewer, $url]) {
            $this->actingAs($viewer)->get($url)
                ->assertOk()
                ->assertSeeInOrder(['Monthly repayment', '-', 'Total repayable', '-', 'Interest', '-'])
                ->assertDontSee('% a year');
        }
    }

    public function test_the_form_shows_the_estimate_panel_with_the_configured_rate(): void
    {
        config(['loan.annual_interest_rate' => 0.15]);

        /** @var User $user */
        $user = User::factory()->create(['id_number' => '8001015009087', 'date_of_birth' => '1980-01-01', 'monthly_income' => 20000]);

        $this->actingAs($user)->get(route('applications.create'))
            ->assertOk()
            ->assertSee('Repayment estimate')
            ->assertSee('Estimate only. Final terms are set by the lender.')
            ->assertSee('15%')
            // the rate reaches Alpine in the JSON config, followed by the limits
            ->assertSee('u0022:0.15,', false);
    }
}
