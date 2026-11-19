<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\LoanApplication;
use App\Models\OcrResult;
use App\Models\User;
use App\Services\ApplicationAssessor;
use App\Services\FraudCheckService;
use App\Services\OcrService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Mockery;
use Tests\TestCase;

class DuplicateDocumentCheckTest extends TestCase
{
    use RefreshDatabase;

    private const ML_URL = 'http://localhost:8000/api/ml/predict';

    private const PAYSLIP_TEXT = "Payslip\nTotal Earnings 11600\nTotal Deductions 2100\nNet Pay 9500";

    private const MODEL_NOTES = 'Strong repayment profile.';

    protected function setUp(): void
    {
        parent::setUp();

        $this->fakeOcr();
    }

    private function flag(string $label): string
    {
        return "This file was also submitted by another applicant ({$label})";
    }

    /**
     * Two applicants whose profiles each match their own documents' text, so only the duplicate check can flag.
     */
    private function thandi(): User
    {
        return $this->applicant('Thandi Mokoena', '8001015009087', '1980-01-01');
    }

    private function sipho(): User
    {
        return $this->applicant('Sipho Dlamini', '8501015800088', '1985-01-01');
    }

    private function applicant(string $name, string $idNumber, string $dateOfBirth): User
    {
        /** @var User $user */
        $user = User::factory()->create([
            'name' => $name,
            'id_number' => $idNumber,
            'date_of_birth' => $dateOfBirth,
            'monthly_income' => 9500,
        ]);

        return $user;
    }

    /**
     * OCR text that matches whoever's application the document is on.
     */
    private function fakeOcr(): void
    {
        $mock = Mockery::mock(OcrService::class);
        $mock->shouldReceive('process')->andReturnUsing(function (Document $document) {
            $user = $document->LoanApplication->user;
            [$firstName, $surname] = explode(' ', strtoupper($user->name));

            return OcrResult::create([
                'document_id' => $document->id,
                'extracted_text' => match ($document->type) {
                    'id_document' => "REPUBLIC OF SOUTH AFRICA\nSurname: {$surname}\nNames: {$firstName}\nIdentity Number: {$user->id_number}",
                    'payslip' => self::PAYSLIP_TEXT,
                    default => "Name: {$user->name}\nClosing Balance: 0.00",
                },
            ]);
        });
        $this->app->instance(OcrService::class, $mock);
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

    /**
     * A fake image's bytes depend only on its size: the same size is the same file.
     */
    private function file(int $size, string $name = 'scan.jpg'): UploadedFile
    {
        return UploadedFile::fake()->image($name, $size, $size);
    }

    /**
     * @param  array<string, UploadedFile|int|string>  $documents  a file, or "{type}_reuse" with a document id
     */
    private function apply(User $user, array $documents): LoanApplication
    {
        $this->actingAs($user)->post('/applications', array_merge([
            'amount_requested' => '2500',
            'no_of_dependents' => 0,
            'education' => 'Graduate',
            'loan_term' => 12,
            'cibil_score_band' => 'Good',
            'residential_assets_value' => 0,
            'commercial_assets_value' => 0,
            'luxury_assets_value' => 0,
            'bank_asset_value' => 0,
        ], $documents))->assertSessionHasNoErrors()->assertRedirect();

        return LoanApplication::with('aiAssessment', 'documents')->where('user_id', $user->id)->latest('id')->firstOrFail();
    }

    /**
     * @return array<string, UploadedFile>
     */
    private function files(int $id, int $payslip, int $bankStatement): array
    {
        return [
            'id_document' => $this->file($id, 'id.jpg'),
            'payslip' => $this->file($payslip, 'payslip.jpg'),
            'bank_statement' => $this->file($bankStatement, 'bank.png'),
        ];
    }

    private function assertNotFlagged(LoanApplication $application): void
    {
        $this->assertSame('Approved', $application->status);
        $this->assertSame('low', $application->aiAssessment->fraud_risk_score);
        $this->assertSame(self::MODEL_NOTES, $application->aiAssessment->reasoning_notes);
    }

    public function test_new_uploads_store_a_sha256_of_the_file_contents(): void
    {
        $this->fakeModel();
        $file = $this->file(20);
        $expected = hash_file('sha256', $file->getRealPath());

        $application = $this->apply($this->thandi(), ['id_document' => $file, 'payslip' => $this->file(21), 'bank_statement' => $this->file(22)]);
        $document = $application->documents->firstWhere('type', 'id_document');

        $this->assertSame($expected, $document->content_hash);
        $this->assertSame(64, strlen($document->content_hash));
        $this->assertSame($expected, hash('sha256', $document->storage()->get($document->file_path)));
    }

    public function test_the_same_files_from_two_different_users_are_flagged(): void
    {
        $this->fakeModel();
        $first = $this->apply($this->thandi(), $this->files(20, 21, 22));
        $second = $this->apply($this->sipho(), $this->files(20, 21, 22));

        // the first applicant's result was decided before the copy arrived
        $this->assertNotFlagged($first);

        $this->assertSame('Manual Review', $second->status);
        $this->assertSame('high', $second->aiAssessment->fraud_risk_score);
        $this->assertSame(
            "Document check:\n- " . $this->flag('ID document')
            . "\n- " . $this->flag('Payslip')
            . "\n- " . $this->flag('Bank statement')
            . "\n\n" . self::MODEL_NOTES,
            $second->aiAssessment->reasoning_notes
        );
    }

    public function test_only_the_copied_document_is_named(): void
    {
        $this->fakeModel();
        $this->apply($this->thandi(), $this->files(20, 21, 22));
        $second = $this->apply($this->sipho(), $this->files(30, 21, 32));

        $this->assertSame('Manual Review', $second->status);
        $this->assertSame("Document check:\n- " . $this->flag('Payslip') . "\n\n" . self::MODEL_NOTES, $second->aiAssessment->reasoning_notes);
    }

    public function test_the_admin_page_shows_the_finding_without_the_other_applicants_details(): void
    {
        $this->fakeModel();
        $thandi = $this->thandi();
        $this->apply($thandi, $this->files(20, 21, 22));
        $second = $this->apply($this->sipho(), $this->files(20, 31, 32));

        /** @var User $admin */
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->get(route('admin.applications.show', $second))
            ->assertOk()
            ->assertSeeInOrder(['Document check:', $this->flag('ID document')])
            ->assertSee('Sipho Dlamini')
            ->assertDontSee('Thandi')
            ->assertDontSee('Mokoena')
            ->assertDontSee($thandi->email)
            ->assertDontSee($thandi->id_number);
    }

    public function test_the_same_user_submitting_the_same_files_again_is_not_flagged(): void
    {
        $this->fakeModel();
        $user = $this->thandi();
        $this->apply($user, $this->files(20, 21, 22));
        $again = $this->apply($user, $this->files(20, 21, 22));

        $this->assertNotFlagged($again);
    }

    public function test_reused_documents_copy_the_hash_and_are_not_flagged(): void
    {
        $this->fakeModel();
        $user = $this->thandi();
        $earlier = $this->apply($user, $this->files(20, 21, 22))->documents->keyBy('type');

        $application = $this->apply($user, [
            'id_document_reuse' => $earlier['id_document']->id,
            'payslip_reuse' => $earlier['payslip']->id,
            'bank_statement_reuse' => $earlier['bank_statement']->id,
        ]);

        foreach ($application->documents as $document) {
            $this->assertTrue($document->isReused());
            $this->assertNotNull($document->content_hash);
            $this->assertSame($earlier[$document->type]->content_hash, $document->content_hash);
        }

        $this->assertNotFlagged($application);
    }

    public function test_re_uploading_through_the_upload_form_counts_only_other_users(): void
    {
        $this->fakeModel();
        $thandi = $this->thandi();
        $sipho = $this->sipho();
        $this->apply($thandi, $this->files(20, 21, 22));
        $application = $this->apply($sipho, $this->files(30, 31, 32));

        // Sipho uploads his own payslip again: not flagged
        $this->actingAs($sipho)->post(route('applications.documents.store', $application), [
            'type' => 'payslip',
            'file' => $this->file(31, 'payslip.jpg'),
        ])->assertRedirect();
        $this->assertNotFlagged($application->refresh());

        // then a copy of Thandi's bank statement: flagged
        $this->actingAs($sipho)->post(route('applications.documents.store', $application), [
            'type' => 'bank_statement',
            'file' => $this->file(22, 'bank.png'),
        ])->assertRedirect();
        $application->refresh();

        $this->assertSame('Manual Review', $application->status);
        $this->assertSame('high', $application->aiAssessment->fraud_risk_score);
        $this->assertStringContainsString($this->flag('Bank statement'), $application->aiAssessment->reasoning_notes);
    }

    public function test_null_hashes_never_match(): void
    {
        // rows from before the hash column, on two different users' applications
        $applications = [];

        foreach ([$this->thandi(), $this->sipho()] as $user) {
            $application = LoanApplication::create(['user_id' => $user->id, 'amount_requested' => 2500, 'loan_term' => 12, 'status' => 'Pending']);

            foreach (FraudCheckService::DOCUMENT_LABELS as $type => $label) {
                Document::create([
                    'application_id' => $application->id,
                    'type' => $type,
                    'file_path' => 'loan-applications/' . $application->id . '/' . $type . '.jpg',
                    'uploaded_at' => now(),
                ]);
            }

            $applications[] = $application;
        }

        $this->assertNull(Document::first()->content_hash);

        foreach ($applications as $application) {
            foreach (app(FraudCheckService::class)->check($application)['flags'] as $flag) {
                $this->assertStringNotContainsString('also submitted by another applicant', $flag);
            }
        }
    }

    public function test_when_disabled_in_config_nothing_is_flagged(): void
    {
        config(['loan.duplicate_check_enabled' => false]);
        $this->fakeModel();
        $this->apply($this->thandi(), $this->files(20, 21, 22));
        $second = $this->apply($this->sipho(), $this->files(20, 21, 22));

        $this->assertNotFlagged($second);
        // the hash is still stored, so switching it back on covers these uploads
        $this->assertNotNull($second->documents->first()->content_hash);
    }

    public function test_a_retry_gives_the_same_finding(): void
    {
        Http::fakeSequence(self::ML_URL)
            ->push(['eligibility_outcome' => 'Approved', 'confidence_score' => 96, 'fraud_risk_score' => 'low', 'reasoning_notes' => self::MODEL_NOTES], 200)
            ->pushStatus(503)
            ->push(['eligibility_outcome' => 'Approved', 'confidence_score' => 96, 'fraud_risk_score' => 'low', 'reasoning_notes' => self::MODEL_NOTES], 200);

        $this->apply($this->thandi(), $this->files(20, 21, 22));
        $second = $this->apply($this->sipho(), $this->files(20, 31, 32));
        $this->assertSame('Pending', $second->status);

        $this->assertTrue(app(ApplicationAssessor::class)->retry($second->fresh()));
        $second->refresh();

        $this->assertSame('Manual Review', $second->status);
        $this->assertSame('high', $second->aiAssessment->fraud_risk_score);
        $this->assertSame("Document check:\n- " . $this->flag('ID document') . "\n\n" . self::MODEL_NOTES, $second->aiAssessment->reasoning_notes);
    }
}
