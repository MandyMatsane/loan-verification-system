<?php

namespace Tests\Feature;

use App\Models\LoanApplication;
use App\Models\User;
use App\Services\OcrService;
use App\Services\RepaymentCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use InvalidArgumentException;
use Mockery;
use Tests\TestCase;

class LoanLimitsTest extends TestCase
{
    use RefreshDatabase;

    // the largest value a decimal(12,2) column holds
    private const DECIMAL_12_2_MAX = 9999999999.99;

    private const AMOUNT_TOO_HIGH = 'The most you can apply for is R100,000';

    private const TERM_OUT_OF_RANGE = 'Choose a term between 1 and 60 months';

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        Http::fake(['http://localhost:8000/api/ml/predict' => Http::response([], 503)]);

        $ocr = Mockery::mock(OcrService::class);
        $ocr->shouldReceive('process')->andReturnNull();
        $this->app->instance(OcrService::class, $ocr);

        $this->user = User::factory()->create(['id_number' => '8001015009087', 'date_of_birth' => '1980-01-01']);
    }

    private function submit(mixed $amount, mixed $term): TestResponse
    {
        return $this->actingAs($this->user)->post('/applications', [
            'amount_requested' => $amount,
            'no_of_dependents' => 0,
            'education' => 'Graduate',
            'loan_term' => $term,
            'cibil_score_band' => 'Good',
            'residential_assets_value' => 0,
            'commercial_assets_value' => 0,
            'luxury_assets_value' => 0,
            'bank_asset_value' => 0,
            'id_document' => UploadedFile::fake()->image('id.jpg'),
            'payslip' => UploadedFile::fake()->image('payslip.jpg'),
            'bank_statement' => UploadedFile::fake()->image('bank.jpg'),
        ]);
    }

    public function test_the_limits_come_from_config(): void
    {
        $this->assertSame(100000, config('loan.max_amount'));
        $this->assertSame(1, config('loan.min_term'));
        $this->assertSame(60, config('loan.max_term'));
    }

    public function test_amounts_at_the_limits_are_accepted(): void
    {
        foreach (['1', '100000', '100000.00'] as $amount) {
            $this->submit($amount, 12)->assertSessionHasNoErrors()->assertRedirect();
        }

        $this->assertSame(3, LoanApplication::count());
        $this->assertEquals(100000, (float) LoanApplication::latest('id')->first()->amount_requested);
    }

    public function test_an_amount_over_the_limit_is_refused(): void
    {
        foreach (['100000.01', '100001', '9999999999999'] as $amount) {
            $this->submit($amount, 12)->assertSessionHasErrors(['amount_requested' => self::AMOUNT_TOO_HIGH]);
        }

        $this->assertSame(0, LoanApplication::count());
    }

    public function test_an_amount_under_1_is_refused(): void
    {
        foreach (['0.99', '0', '-50'] as $amount) {
            $this->submit($amount, 12)->assertSessionHasErrors(['amount_requested' => 'The least you can apply for is R1']);
        }

        $this->assertSame(0, LoanApplication::count());
    }

    public function test_terms_at_the_limits_are_accepted(): void
    {
        foreach ([1, 60] as $term) {
            $this->submit('5000', $term)->assertSessionHasNoErrors()->assertRedirect();
        }

        $this->assertEqualsCanonicalizing([1, 60], LoanApplication::pluck('loan_term')->map(fn ($term) => (int) $term)->all());
    }

    public function test_a_term_outside_the_limits_is_refused(): void
    {
        foreach ([0, 61, 120, '12.5', 'abc'] as $term) {
            $this->submit('5000', $term)->assertSessionHasErrors(['loan_term' => self::TERM_OUT_OF_RANGE]);
        }

        $this->assertSame(0, LoanApplication::count());
    }

    public function test_the_form_shows_the_limits_as_hints(): void
    {
        $this->actingAs($this->user)->get(route('applications.create'))
            ->assertOk()
            ->assertSee('From R1 to R100,000')
            ->assertSee('1 to 60 months')
            ->assertSee('max="100000"', false)
            ->assertSee('max="60"', false);
    }

    public function test_an_invalid_rate_is_refused(): void
    {
        $calculator = new RepaymentCalculator();

        foreach ([-0.01, 1.01, 5.0, INF, NAN] as $rate) {
            try {
                $calculator->estimate(10000, 12, $rate);
                $this->fail('No exception for rate ' . $rate);
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }

        // the cap itself is allowed
        $this->assertGreaterThan(0, $calculator->estimate(10000, 12, RepaymentCalculator::MAX_ANNUAL_RATE)['monthly_repayment']);
    }

    public function test_the_largest_valid_loan_fits_the_database_columns(): void
    {
        $estimate = (new RepaymentCalculator())->estimate(
            (float) config('loan.max_amount'),
            (int) config('loan.max_term'),
            RepaymentCalculator::MAX_ANNUAL_RATE
        );

        $this->assertLessThan(self::DECIMAL_12_2_MAX, $estimate['total_repayable']);
        $this->assertLessThan(self::DECIMAL_12_2_MAX, $estimate['monthly_repayment']);
        // interest_rate is decimal(5,4): at most 9.9999
        $this->assertLessThan(10, RepaymentCalculator::MAX_ANNUAL_RATE);

        // and it is actually stored at the top limits
        config(['loan.annual_interest_rate' => RepaymentCalculator::MAX_ANNUAL_RATE]);
        $this->submit('100000', 60)->assertSessionHasNoErrors();

        $application = LoanApplication::firstOrFail();
        $this->assertEquals($estimate['total_repayable'], (float) $application->total_repayable);
        $this->assertEquals(1.0, (float) $application->interest_rate);
    }
}
