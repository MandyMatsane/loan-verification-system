<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\LoanApplication;
use App\Models\OcrResult;
use App\Models\User;
use App\Services\OcrService;
use App\Services\PayslipIncomeExtractor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Mockery;
use Tests\TestCase;

class PayslipNameCheckTest extends TestCase
{
    use RefreshDatabase;

    private const ML_URL = 'http://localhost:8000/api/ml/predict';

    // a 13-digit number that passes the Luhn checksum, born 1 January 1980
    private const VALID_ID = '8001015009087';

    private const ID_TEXT = "REPUBLIC OF SOUTH AFRICA\nSurname: MOKOENA\nNames: THANDI\nIdentity Number: 800101 5009 087\nDate of Birth: 01 JAN 1980";

    private const BANK_STATEMENT_TEXT = "Name: Thandi Mokoena\nClosing Balance: 0.00 ZAR";

    private const MODEL_NOTES = 'Strong repayment profile.';

    private const NAME_FLAG = 'Name on the payslip does not match the applicant';

    /**
     * As Tesseract reads the sample payslip (dummy_payslip.jpg), with the employee name swapped in.
     */
    private function payslip(?string $name = 'Sally Harley'): string
    {
        $nameLine = $name === null ? 'Date of Joining :2018-06-23' : "Date of Joining :2018-06-23 Employee name : {$name}";

        return "Payslip\nZoonodle Inc\n\n21023 Pearson Point Road\nGateway Avenue\n\n{$nameLine}\n"
            . "Pay Period : August 2021 Designation : Marketing Executive\nWorked Days 126 Department : Marketing\n"
            . "Earnings Amount\nBasic 10000\nTotal Earnings 11600\nDeductions Amount\nTotal Deductions 2100\nNet Pay 9500\n9500";
    }

    private function applicant(): User
    {
        /** @var User $user */
        $user = User::factory()->create([
            'name' => 'Thandi Mokoena',
            'id_number' => self::VALID_ID,
            'date_of_birth' => '1980-01-01',
            'monthly_income' => 9500,
        ]);

        return $user;
    }

    private function fakeModel(): void
    {
        Http::fake([self::ML_URL => Http::response([
            'eligibility_outcome' => 'Approved',
            'confidence_score' => 96,
            'fraud_risk_score' => 'low',
            'reasoning_notes' => self::MODEL_NOTES,
        ], 200)]);
    }

    private function apply(string $payslipText, ?User $user = null): LoanApplication
    {
        $this->fakeModel();
        $mock = Mockery::mock(OcrService::class);
        $mock->shouldReceive('process')->andReturnUsing(fn (Document $document) => OcrResult::create([
            'document_id' => $document->id,
            'extracted_text' => match ($document->type) {
                'id_document' => self::ID_TEXT,
                'payslip' => $payslipText,
                default => self::BANK_STATEMENT_TEXT,
            },
        ]));
        $this->app->instance(OcrService::class, $mock);

        $user ??= $this->applicant();
        $this->actingAs($user)->post('/applications', [
            'amount_requested' => '2500',
            'no_of_dependents' => 0,
            'education' => 'Graduate',
            'loan_term' => 12,
            'cibil_score_band' => 'Good',
            'residential_assets_value' => 0,
            'commercial_assets_value' => 0,
            'luxury_assets_value' => 0,
            'bank_asset_value' => 0,
            'id_document' => UploadedFile::fake()->image('id.jpg'),
            'payslip' => UploadedFile::fake()->image('payslip.jpg'),
            'bank_statement' => UploadedFile::fake()->image('bank.jpg'),
        ])->assertSessionHasNoErrors()->assertRedirect();

        return LoanApplication::with('aiAssessment')->where('user_id', $user->id)->latest('id')->firstOrFail();
    }

    public function test_the_employee_name_is_read_from_the_payslip(): void
    {
        $extractor = new PayslipIncomeExtractor();

        $this->assertSame('Sally Harley', $extractor->employeeName($this->payslip()));
        $this->assertSame('Thandi Nkosi', $extractor->employeeName($this->payslip('Thandi Nkosi')));
        $this->assertSame('Thandi Mokoena', $extractor->employeeName("Name: Thandi Mokoena  Employee No 1234"));
        $this->assertNull($extractor->employeeName($this->payslip(null)));
        $this->assertNull($extractor->employeeName(''));
        $this->assertNull($extractor->employeeName(null));
    }

    public function test_a_matching_name_is_not_flagged(): void
    {
        $application = $this->apply($this->payslip('THANDI M0KOENA'));

        $this->assertSame('Approved', $application->status);
        $this->assertSame('low', $application->aiAssessment->fraud_risk_score);
        $this->assertSame(self::MODEL_NOTES, $application->aiAssessment->reasoning_notes);
    }

    public function test_a_different_name_is_flagged(): void
    {
        $application = $this->apply($this->payslip('Sally Harley'));

        $this->assertSame('Manual Review', $application->status);
        $this->assertSame('high', $application->aiAssessment->fraud_risk_score);
        $this->assertSame("Document check:\n- " . self::NAME_FLAG . "\n\n" . self::MODEL_NOTES, $application->aiAssessment->reasoning_notes);
    }

    public function test_no_name_on_the_payslip_is_not_flagged_and_the_income_check_still_runs(): void
    {
        $application = $this->apply($this->payslip(null));

        $this->assertSame('Approved', $application->status);
        $this->assertSame(self::MODEL_NOTES, $application->aiAssessment->reasoning_notes);

        // the income check still compares the stated R9,500 with the payslip
        $user = $this->applicant();
        $user->forceFill(['email' => 'other@example.com', 'monthly_income' => 30000])->save();
        $application = $this->apply($this->payslip(null), $user);

        $this->assertSame('Manual Review', $application->status);
        $this->assertStringContainsString('Stated income does not match the payslip (stated R30,000.00, payslip R11,600.00)', $application->aiAssessment->reasoning_notes);
        $this->assertStringNotContainsString(self::NAME_FLAG, $application->aiAssessment->reasoning_notes);
    }

    public function test_the_threshold_comes_from_config(): void
    {
        config(['loan.payslip_name_similarity' => 0]);

        $this->assertSame('Approved', $this->apply($this->payslip('Sally Harley'))->status);
    }

    public function test_the_admin_page_shows_the_flag_and_the_amber_line(): void
    {
        $application = $this->apply($this->payslip('Sally Harley'));

        /** @var User $admin */
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->get(route('admin.applications.show', $application))
            ->assertOk()
            ->assertSee('Overridden by the document check. See the reasoning notes.')
            ->assertSeeInOrder(['Document check:', self::NAME_FLAG]);
    }
}
