<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\LoanApplication;
use App\Models\OcrResult;
use App\Models\User;
use App\Services\FraudCheckService;
use App\Services\OcrService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

class FraudCheckServiceTest extends TestCase
{
    use RefreshDatabase;

    // a 13-digit number that passes the Luhn checksum, born 1 January 1980
    private const VALID_ID = '8001015009087';

    private const ID_TEXT = "REPUBLIC OF SOUTH AFRICA\nSurname: MOKOENA\nNames: THANDI\nIdentity Number: 800101 5009 087\nDate of Birth: 01 JAN 1980";

    /**
     * An application whose ID document has the given OCR text (null for no OCR result).
     */
    private function applicationFor(array $userAttributes = [], ?string $ocrText = self::ID_TEXT): LoanApplication
    {
        /** @var User $user */
        $user = User::factory()->create(array_merge([
            'name' => 'Thandi Mokoena',
            'id_number' => self::VALID_ID,
            'date_of_birth' => '1980-01-01',
        ], $userAttributes));

        $application = LoanApplication::create([
            'user_id' => $user->id,
            'amount_requested' => 50000,
            'status' => 'Pending',
        ]);

        $document = Document::create([
            'application_id' => $application->id,
            'type' => 'id_document',
            'file_path' => 'loan-applications/' . $application->id . '/id-document.jpg',
            'uploaded_at' => now(),
        ]);

        if ($ocrText !== null) {
            OcrResult::create(['document_id' => $document->id, 'extracted_text' => $ocrText]);
        }

        return $application;
    }

    private function check(LoanApplication $application): array
    {
        return app(FraudCheckService::class)->check($application);
    }

    public function test_a_matching_name_and_id_pass(): void
    {
        $result = $this->check($this->applicationFor());

        $this->assertSame([], $result['flags']);
        $this->assertSame(100.0, $result['name_similarity']);
    }

    public function test_a_one_letter_ocr_typo_in_the_name_still_passes(): void
    {
        $result = $this->check($this->applicationFor([], str_replace('MOKOENA', 'MOKOEMA', self::ID_TEXT)));

        $this->assertSame([], $result['flags']);
        $this->assertGreaterThanOrEqual(FraudCheckService::NAME_SIMILARITY_THRESHOLD, $result['name_similarity']);
        $this->assertLessThan(100, $result['name_similarity']);
    }

    public function test_a_different_name_is_flagged_with_the_percentage(): void
    {
        $result = $this->check($this->applicationFor(['name' => 'John Smith']));

        $this->assertLessThan(FraudCheckService::NAME_SIMILARITY_THRESHOLD, $result['name_similarity']);
        $this->assertCount(1, $result['flags']);
        $this->assertStringContainsString(sprintf('%.1f%%', $result['name_similarity']), $result['flags'][0]);
    }

    public function test_an_id_number_that_fails_the_luhn_checksum_is_flagged(): void
    {
        $invalidId = '8001015009086';

        $result = $this->check($this->applicationFor(
            ['id_number' => $invalidId],
            str_replace('800101 5009 087', '800101 5009 086', self::ID_TEXT)
        ));

        $this->assertSame([FraudCheckService::FLAG_ID_CHECKSUM], $result['flags']);
    }

    public function test_an_id_number_that_is_not_13_digits_is_flagged(): void
    {
        $result = $this->check($this->applicationFor(['id_number' => '80010150090']));

        $this->assertSame([FraudCheckService::FLAG_ID_LENGTH], $result['flags']);
    }

    public function test_an_id_number_missing_from_the_document_is_flagged(): void
    {
        $result = $this->check($this->applicationFor(
            [],
            str_replace('800101 5009 087', '750505 5009 081', self::ID_TEXT)
        ));

        $this->assertSame([FraudCheckService::FLAG_ID_NOT_ON_DOCUMENT], $result['flags']);
    }

    public function test_a_date_of_birth_mismatch_is_flagged(): void
    {
        $result = $this->check($this->applicationFor(['date_of_birth' => '1981-01-01']));

        $this->assertSame([FraudCheckService::FLAG_DATE_OF_BIRTH], $result['flags']);
    }

    public function test_empty_ocr_text_is_flagged(): void
    {
        foreach (['', null] as $ocrText) {
            $result = $this->check($this->applicationFor([], $ocrText));

            $this->assertSame([FraudCheckService::FLAG_UNREADABLE], $result['flags']);
            $this->assertSame(0.0, $result['name_similarity']);
        }
    }

    /**
     * Submit an application as the given user, with OCR returning the given text for every document.
     */
    private function submitApplication(User $user, string $ocrText): LoanApplication
    {
        Storage::fake('public');

        Http::fake([
            'http://localhost:8000/api/ml/predict' => Http::response([
                'eligibility_outcome' => 'Approved',
                'confidence_score' => 96,
                'fraud_risk_score' => 'low',
                'reasoning_notes' => 'Strong repayment profile.',
            ], 200),
        ]);

        $mock = Mockery::mock(OcrService::class);
        $mock->shouldReceive('process')->times(3)->andReturnUsing(
            fn (Document $document) => OcrResult::create(['document_id' => $document->id, 'extracted_text' => $ocrText])
        );
        $this->app->instance(OcrService::class, $mock);

        $this->actingAs($user)
            ->post('/applications', [
                'amount_requested' => '2500.00',
                'no_of_dependents' => 2,
                'education' => 'Graduate',
                'loan_term' => 12,
                'cibil_score_band' => 'Good',
                'residential_assets_value' => 0,
                'commercial_assets_value' => 0,
                'luxury_assets_value' => 0,
                'bank_asset_value' => 0,
                'id_document' => UploadedFile::fake()->image('id-document.jpg', 800, 600),
                'payslip' => UploadedFile::fake()->image('payslip.jpg', 800, 600),
                'bank_statement' => UploadedFile::fake()->image('bank-statement.png', 800, 600),
            ])
            ->assertRedirect();

        return LoanApplication::with('aiAssessment')->where('user_id', $user->id)->firstOrFail();
    }

    public function test_an_approved_application_goes_to_manual_review_when_the_document_check_fails(): void
    {
        /** @var User $user */
        $user = User::factory()->create([
            'name' => 'John Smith',
            'id_number' => self::VALID_ID,
            'date_of_birth' => '1980-01-01',
        ]);

        $application = $this->submitApplication($user, self::ID_TEXT);

        $this->assertSame('Manual Review', $application->status);
        $this->assertSame('high', $application->aiAssessment->fraud_risk_score);
        $this->assertSame('Approved', $application->aiAssessment->eligibility_outcome);
        $this->assertStringStartsWith("Document check:\n- Name on the ID document", $application->aiAssessment->reasoning_notes);
        $this->assertStringEndsWith('Strong repayment profile.', $application->aiAssessment->reasoning_notes);
    }

    public function test_an_approved_application_stays_approved_when_the_document_check_passes(): void
    {
        /** @var User $user */
        $user = User::factory()->create([
            'name' => 'Thandi Mokoena',
            'id_number' => self::VALID_ID,
            'date_of_birth' => '1980-01-01',
        ]);

        $application = $this->submitApplication($user, self::ID_TEXT);

        $this->assertSame('Approved', $application->status);
        $this->assertSame('low', $application->aiAssessment->fraud_risk_score);
        $this->assertSame('Strong repayment profile.', $application->aiAssessment->reasoning_notes);
    }

    public function test_a_failed_ml_call_keeps_the_application_pending_even_with_flags(): void
    {
        Storage::fake('public');
        Http::fake(['http://localhost:8000/api/ml/predict' => Http::response([], 503)]);

        $mock = Mockery::mock(OcrService::class);
        $mock->shouldReceive('process')->times(3)->andReturnNull();
        $this->app->instance(OcrService::class, $mock);

        /** @var User $user */
        $user = User::factory()->create(['id_number' => self::VALID_ID, 'date_of_birth' => '1980-01-01']);

        $this->actingAs($user)
            ->post('/applications', [
                'amount_requested' => '2500.00',
                'no_of_dependents' => 2,
                'education' => 'Graduate',
                'loan_term' => 12,
                'cibil_score_band' => 'Good',
                'residential_assets_value' => 0,
                'commercial_assets_value' => 0,
                'luxury_assets_value' => 0,
                'bank_asset_value' => 0,
                'id_document' => UploadedFile::fake()->image('id-document.jpg', 800, 600),
                'payslip' => UploadedFile::fake()->image('payslip.jpg', 800, 600),
                'bank_statement' => UploadedFile::fake()->image('bank-statement.png', 800, 600),
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('loan_applications', ['user_id' => $user->id, 'status' => 'Pending']);
        $this->assertDatabaseCount('ai_assessments', 0);
    }
}
