<?php

namespace Tests\Feature;

use App\Models\AiAssessment;
use App\Models\Document;
use App\Models\LoanApplication;
use App\Models\OcrResult;
use App\Models\User;
use App\Services\ApplicationAssessor;
use App\Services\OcrService;
use App\Services\PayslipIncomeExtractor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

class IncomeChecksTest extends TestCase
{
    use RefreshDatabase;

    private const ML_URL = 'http://localhost:8000/api/ml/predict';

    private const VALID_ID = '8001015009087';

    private const ID_TEXT = "REPUBLIC OF SOUTH AFRICA\nSurname: MOKOENA\nNames: THANDI\nIdentity Number: 800101 5009 087\nDate of Birth: 01 JAN 1980";

    // as Tesseract reads the sample payslip (dummy_payslip.jpg)
    private const SAMPLE_PAYSLIP = "Payslip\nZoonodle Inc\nEarnings Amount\nBasic 10000\nTotal Earnings 11600\nDeductions Amount\nTotal Deductions 2100\nNet Pay 9500\n9500\nNine Thousand Five Hundred";

    private const NOT_READABLE = 'Note: Income could not be read from the payslip';

    private const NO_INCOME = 'Note: No income on file, affordability not checked';

    private function applicant(?float $income, array $attributes = []): User
    {
        /** @var User $user */
        $user = User::factory()->create(array_merge([
            'name' => 'Thandi Mokoena',
            'id_number' => self::VALID_ID,
            'date_of_birth' => '1980-01-01',
            'monthly_income' => $income,
        ], $attributes));

        return $user;
    }

    private function fakeModel(string $outcome = 'Approved', string $risk = 'low', int $confidence = 96): void
    {
        Http::fake([self::ML_URL => Http::response([
            'eligibility_outcome' => $outcome,
            'confidence_score' => $confidence,
            'fraud_risk_score' => $risk,
            'reasoning_notes' => 'Strong repayment profile.',
        ], 200)]);
    }

    private function fakeOcr(string $payslipText): void
    {
        $mock = Mockery::mock(OcrService::class);
        $mock->shouldReceive('process')->andReturnUsing(fn (Document $document) => OcrResult::create([
            'document_id' => $document->id,
            'extracted_text' => match ($document->type) {
                'id_document' => self::ID_TEXT,
                'payslip' => $payslipText,
                default => 'BANK STATEMENT',
            },
        ]));
        $this->app->instance(OcrService::class, $mock);
    }

    private function apply(User $user, string $amount = '5000', int $term = 12): LoanApplication
    {
        $this->actingAs($user)->post('/applications', [
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
        ])->assertSessionHasNoErrors()->assertRedirect();

        return LoanApplication::with('aiAssessment', 'documents')->where('user_id', $user->id)->latest('id')->firstOrFail();
    }

    private function reviewPage(LoanApplication $application)
    {
        /** @var User $admin */
        $admin = User::factory()->create(['role' => 'admin']);

        return $this->actingAs($admin)->get(route('admin.applications.show', $application))->assertOk();
    }

    // --- extractor ---

    public function test_the_sample_payslip_gives_gross_and_net_pay(): void
    {
        $this->assertSame(['gross' => 11600.0, 'net' => 9500.0], (new PayslipIncomeExtractor())->extract(self::SAMPLE_PAYSLIP));
    }

    public function test_amount_formats_parse(): void
    {
        $extractor = new PayslipIncomeExtractor();

        foreach ([
            'R12,345.67' => 12345.67,
            'R 12,345.67' => 12345.67,
            '12 345.67' => 12345.67,
            '12345,67' => 12345.67,
            '12345' => 12345.0,
            '12,345' => 12345.0,
            '1.234.567,89' => 1234567.89,
        ] as $text => $expected) {
            $this->assertSame($expected, $extractor->parseAmount($text), $text);
        }

        $this->assertSame(
            ['gross' => 15000.0, 'net' => 12345.67],
            $extractor->extract("GROSS SALARY: R15 000,00\nDeductions 2654.33\nNett Pay R12,345.67")
        );
        // the figure on the line after the label
        $this->assertSame(['gross' => null, 'net' => 8250.5], $extractor->extract("Take-home pay\n8 250,50"));
        $this->assertSame(['gross' => 9000.0, 'net' => null], $extractor->extract('Gross pay - 9000'));
    }

    public function test_the_extractor_returns_null_and_never_throws(): void
    {
        $extractor = new PayslipIncomeExtractor();

        foreach ([null, '', 'Payslip without figures', 'Net Pay abc', 'Net Pay 0', str_repeat("Net Pay \x00\xff", 50)] as $text) {
            $this->assertNull($extractor->extract($text));
        }

        foreach (['abc', '', '0', '-5', '1e5'] as $value) {
            $this->assertNull($extractor->parseAmount($value), $value);
        }
    }

    // --- payslip cross-check ---

    public function test_a_stated_income_matching_the_payslip_passes(): void
    {
        $this->fakeModel();

        // net pay matches; so does gross for the second applicant
        foreach ([9500, 11600, 13000] as $income) {
            $this->fakeOcr(self::SAMPLE_PAYSLIP);
            $application = $this->apply($this->applicant($income, ['email' => "applicant{$income}@example.com"]));

            $this->assertSame('Approved', $application->status, (string) $income);
            $this->assertSame('low', $application->aiAssessment->fraud_risk_score);
            $this->assertSame('Strong repayment profile.', $application->aiAssessment->reasoning_notes);
        }
    }

    public function test_a_stated_income_more_than_15_percent_off_is_flagged_high(): void
    {
        $this->fakeModel();
        $this->fakeOcr(self::SAMPLE_PAYSLIP);

        // closest payslip figure is gross 11,600; 15,000 is 29% above it
        $application = $this->apply($this->applicant(15000));

        $this->assertSame('Manual Review', $application->status);
        $this->assertSame('high', $application->aiAssessment->fraud_risk_score);
        $this->assertSame(
            "Document check:\n- Stated income does not match the payslip (stated R15,000.00, payslip R11,600.00)\n\nStrong repayment profile.",
            $application->aiAssessment->reasoning_notes
        );

        $this->reviewPage($application)->assertSee('Overridden by the document check. See the reasoning notes.');
    }

    public function test_the_tolerance_comes_from_config(): void
    {
        config(['loan.income_tolerance' => 0.30]);
        $this->fakeModel();
        $this->fakeOcr(self::SAMPLE_PAYSLIP);

        $this->assertSame('Approved', $this->apply($this->applicant(15000))->status);
    }

    public function test_no_payslip_figure_does_not_flag_and_adds_a_note(): void
    {
        $this->fakeModel();
        $this->fakeOcr('A blurry payslip');

        $application = $this->apply($this->applicant(9500));

        $this->assertSame('Approved', $application->status);
        $this->assertSame('low', $application->aiAssessment->fraud_risk_score);
        $this->assertSame("Strong repayment profile.\n\n" . self::NOT_READABLE, $application->aiAssessment->reasoning_notes);

        $this->reviewPage($application)
            ->assertSee('Not found on the payslip')
            ->assertDontSee('Overridden by the document check');
    }

    // --- affordability ---

    public function test_r20000_over_6_months_on_r5000_income_is_flagged(): void
    {
        $this->fakeModel();
        $this->fakeOcr("Net Pay 5000");

        // the saved estimate is R3,530.46 a month: 70.6% of R5,000
        $application = $this->apply($this->applicant(5000), '20000', 6);

        $this->assertSame('Manual Review', $application->status);
        $this->assertSame('medium', $application->aiAssessment->fraud_risk_score);
        $this->assertSame(
            "Affordability check:\n- Requested repayment is 70.6% of stated income (limit 35%)\n\nStrong repayment profile.",
            $application->aiAssessment->reasoning_notes
        );

        $this->reviewPage($application)->assertSee('Flagged by the affordability check. See the reasoning notes.');
    }

    public function test_without_a_saved_estimate_the_repayment_is_amount_over_term(): void
    {
        $this->fakeModel();
        $user = $this->applicant(5000);
        $application = $this->storedApplication($user, ['amount_requested' => 20000, 'loan_term' => 6]);
        $this->readDocuments($application, 'Net Pay 5000');

        app(ApplicationAssessor::class)->assess($application);

        // R20,000 / 6 = R3,333.33: 66.7% of R5,000
        $this->assertStringStartsWith(
            "Affordability check:\n- Requested repayment is 66.7% of stated income (limit 35%)",
            $application->aiAssessment()->first()->reasoning_notes
        );
    }

    public function test_r5000_on_r5000_income_passes(): void
    {
        $this->fakeModel();
        $this->fakeOcr("Net Pay 5000");

        $application = $this->apply($this->applicant(5000), '5000', 12);

        $this->assertSame('Approved', $application->status);
        $this->assertSame('low', $application->aiAssessment->fraud_risk_score);
        $this->assertSame('Strong repayment profile.', $application->aiAssessment->reasoning_notes);
    }

    public function test_affordability_never_lowers_a_high_fraud_risk(): void
    {
        $this->fakeModel('Approved', 'high');
        $this->fakeOcr("Net Pay 5000");

        $application = $this->apply($this->applicant(5000), '20000', 6);

        $this->assertSame('high', $application->aiAssessment->fraud_risk_score);
        $this->assertSame('Manual Review', $application->status);
    }

    public function test_missing_income_skips_affordability_with_a_note(): void
    {
        $this->fakeModel();
        // an older application: no saved income, and none on the profile now
        $application = $this->storedApplication($this->applicant(null), ['amount_requested' => 20000, 'loan_term' => 6]);
        $this->readDocuments($application, 'Net Pay 5000');

        app(ApplicationAssessor::class)->assess($application);
        $application->refresh();

        $this->assertSame('Approved', $application->status);
        $assessment = $application->aiAssessment;
        $this->assertSame('low', $assessment->fraud_risk_score);
        $this->assertStringStartsWith('Strong repayment profile.', $assessment->reasoning_notes);
        $this->assertStringContainsString(self::NO_INCOME, $assessment->reasoning_notes);
        $this->assertStringNotContainsString('Affordability check:', $assessment->reasoning_notes);
    }

    public function test_zero_income_also_skips_affordability(): void
    {
        $this->fakeModel();
        $application = $this->storedApplication($this->applicant(9000), ['stated_monthly_income' => 0]);

        app(ApplicationAssessor::class)->assess($application);

        $this->assertStringContainsString(self::NO_INCOME, $application->aiAssessment()->first()->reasoning_notes);
    }

    // --- both checks ---

    public function test_both_checks_give_both_notes_in_order_and_the_combined_line(): void
    {
        $this->fakeModel();
        $this->fakeOcr(self::SAMPLE_PAYSLIP);

        // stated R5,000 vs net pay R9,500 (47% off), and R3,530.46 a month is 70.6% of R5,000
        $application = $this->apply($this->applicant(5000), '20000', 6);

        $this->assertSame('Manual Review', $application->status);
        $this->assertSame('high', $application->aiAssessment->fraud_risk_score);
        $this->assertSame(
            "Document check:\n- Stated income does not match the payslip (stated R5,000.00, payslip R9,500.00)"
            . "\n\nAffordability check:\n- Requested repayment is 70.6% of stated income (limit 35%)"
            . "\n\nStrong repayment profile.",
            $application->aiAssessment->reasoning_notes
        );

        $this->reviewPage($application)
            ->assertSee('Flagged by the document and affordability checks. See the reasoning notes.')
            ->assertDontSee('Overridden by the document check')
            ->assertDontSee('Flagged by the affordability check.');
    }

    public function test_a_failed_ml_call_keeps_pending_and_adds_no_notes(): void
    {
        Http::fake([self::ML_URL => Http::response([], 503)]);
        $this->fakeOcr(self::SAMPLE_PAYSLIP);

        $application = $this->apply($this->applicant(5000), '20000', 6);

        $this->assertSame('Pending', $application->status);
        $this->assertNull($application->aiAssessment);
    }

    public function test_a_retry_applies_both_checks_the_same_way(): void
    {
        Http::fakeSequence(self::ML_URL)
            ->pushStatus(503)
            ->push(['eligibility_outcome' => 'Approved', 'confidence_score' => 96, 'fraud_risk_score' => 'low', 'reasoning_notes' => 'Strong repayment profile.'], 200);
        $this->fakeOcr(self::SAMPLE_PAYSLIP);

        $user = $this->applicant(5000);
        $application = $this->apply($user, '20000', 6);
        $this->assertSame('Pending', $application->status);

        // the applicant changes their income before the retry: the checks still use the income they applied with
        $user->forceFill(['monthly_income' => 30000])->save();

        $this->assertTrue(app(ApplicationAssessor::class)->retry($application->fresh()));
        $application->refresh();

        $this->assertSame('Manual Review', $application->status);
        $this->assertSame('high', $application->aiAssessment->fraud_risk_score);
        $this->assertStringStartsWith(
            "Document check:\n- Stated income does not match the payslip (stated R5,000.00, payslip R9,500.00)\n\nAffordability check:\n- Requested repayment is 70.6%",
            $application->aiAssessment->reasoning_notes
        );
    }

    // --- income snapshot and the apply gate ---

    public function test_the_income_is_saved_on_the_application(): void
    {
        $this->fakeModel();
        $this->fakeOcr(self::SAMPLE_PAYSLIP);

        $application = $this->apply($this->applicant(9500));

        $this->assertEquals(9500, (float) $application->stated_monthly_income);
        $this->reviewPage($application)
            ->assertSeeInOrder(['Stated monthly income', 'R9,500.00'])
            ->assertSeeInOrder(['Payslip income', 'Gross R11,600.00, Net R9,500.00']);
    }

    public function test_older_applications_use_the_current_profile_income(): void
    {
        $application = $this->storedApplication($this->applicant(7000));

        $this->assertNull($application->stated_monthly_income);
        $this->assertSame(7000.0, $application->statedMonthlyIncome());
        $this->reviewPage($application)->assertSeeInOrder(['Stated monthly income', 'R7,000.00']);
    }

    public function test_applicants_without_an_income_cannot_apply(): void
    {
        Http::fake();
        $message = 'Add your ID number, date of birth and monthly income before you apply.';

        foreach ([null, 0] as $income) {
            $user = $this->applicant($income, ['email' => 'no-income-' . var_export($income, true) . '@example.com']);

            $this->actingAs($user)->get(route('applications.create'))
                ->assertRedirect(route('profile.edit'))
                ->assertSessionHas('warning', $message);

            $this->actingAs($user)->post('/applications', ['amount_requested' => '5000', 'loan_term' => 12])
                ->assertRedirect(route('profile.edit'))
                ->assertSessionHas('warning', $message);
        }

        $this->assertSame(0, LoanApplication::count());
        Http::assertNothingSent();
    }

    // --- downloads and storage ---

    public function test_new_uploads_are_stored_on_the_private_disk(): void
    {
        Storage::fake('public');
        $this->fakeModel();
        $this->fakeOcr(self::SAMPLE_PAYSLIP);

        $application = $this->apply($this->applicant(9500));

        foreach ($application->documents as $document) {
            $this->assertSame(Document::PRIVATE_DISK, $document->disk);
            Storage::disk('documents')->assertExists($document->file_path);
            Storage::disk('public')->assertMissing($document->file_path);
        }
    }

    public function test_the_admin_and_the_owner_can_download_and_others_get_403(): void
    {
        $owner = $this->applicant(9500);
        $application = $this->storedApplication($owner);
        $document = $this->storedDocument($application, Document::PRIVATE_DISK, 'payslip-' . str_repeat('a', 8) . '-1111-2222-3333-444444444444-payslip.jpg');

        /** @var User $admin */
        $admin = User::factory()->create(['role' => 'admin']);
        $someoneElse = $this->applicant(9500, ['email' => 'someone@example.com']);

        $this->actingAs($admin)->get(route('documents.download', $document))
            ->assertOk()
            ->assertDownload('payslip.jpg');
        $this->assertSame('PAYSLIP IMAGE', $this->actingAs($owner)->get(route('documents.download', $document))->assertOk()->streamedContent());

        $this->actingAs($someoneElse)->get(route('documents.download', $document))->assertForbidden();

        auth()->logout();
        $this->get(route('documents.download', $document))->assertRedirect(route('login'));
    }

    public function test_older_files_on_the_public_disk_still_download(): void
    {
        Storage::fake('public');
        $owner = $this->applicant(9500);
        $document = $this->storedDocument($this->storedApplication($owner), null, 'old-payslip.jpg');

        $this->assertSame('public', $document->fresh()->disk);
        $this->actingAs($owner)->get(route('documents.download', $document))->assertOk()->assertDownload('old-payslip.jpg');
    }

    public function test_a_missing_file_says_it_is_no_longer_available_and_has_no_link(): void
    {
        $owner = $this->applicant(9500);
        $application = $this->storedApplication($owner);
        $document = $this->storedDocument($application, Document::PRIVATE_DISK, 'gone.jpg');
        Storage::disk('documents')->delete($document->file_path);

        $this->actingAs($owner)->get(route('documents.download', $document))
            ->assertNotFound()
            ->assertSeeText('File no longer available');

        $this->actingAs($owner)->get(route('applications.show', $application))
            ->assertOk()
            ->assertSee('File no longer available')
            ->assertDontSee(route('documents.download', $document));

        $this->reviewPage($application)->assertDontSee(route('documents.download', $document));
    }

    public function test_download_links_show_when_the_file_exists(): void
    {
        $owner = $this->applicant(9500);
        $application = $this->storedApplication($owner);
        $document = $this->storedDocument($application, Document::PRIVATE_DISK, 'payslip.jpg');

        $this->actingAs($owner)->get(route('applications.show', $application))
            ->assertOk()
            ->assertSee(route('documents.download', $document))
            ->assertSee('Download');

        $this->reviewPage($application)->assertSee(route('documents.download', $document));
    }

    /**
     * An ID document and a payslip that OCR has already read, without files.
     */
    private function readDocuments(LoanApplication $application, string $payslipText): void
    {
        foreach (['id_document' => self::ID_TEXT, 'payslip' => $payslipText] as $type => $text) {
            $document = Document::create([
                'application_id' => $application->id,
                'type' => $type,
                'file_path' => 'loan-applications/' . $application->id . '/' . $type . '.jpg',
                'disk' => Document::PRIVATE_DISK,
                'uploaded_at' => now(),
            ]);

            OcrResult::create(['document_id' => $document->id, 'extracted_text' => $text]);
        }
    }

    private function storedApplication(User $user, array $attributes = []): LoanApplication
    {
        $application = LoanApplication::create(array_merge([
            'user_id' => $user->id,
            'amount_requested' => 5000,
            'loan_term' => 12,
            'status' => 'Pending',
        ], $attributes));

        if (array_key_exists('stated_monthly_income', $attributes)) {
            $application->forceFill(['stated_monthly_income' => $attributes['stated_monthly_income']])->save();
        }

        return $application;
    }

    /**
     * A payslip document whose file is on the given disk (null: an older row, which defaults to the public disk).
     */
    private function storedDocument(LoanApplication $application, ?string $disk, string $fileName): Document
    {
        $path = 'loan-applications/' . $application->id . '/' . $fileName;
        Storage::disk($disk ?? 'public')->put($path, 'PAYSLIP IMAGE');

        $attributes = [
            'application_id' => $application->id,
            'type' => 'payslip',
            'file_path' => $path,
            'uploaded_at' => now(),
        ];

        if ($disk !== null) {
            $attributes['disk'] = $disk;
        }

        return Document::create($attributes);
    }
}
