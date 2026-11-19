<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\LoanApplication;
use App\Models\OcrResult;
use App\Models\User;
use App\Services\OcrService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Mockery;
use Tests\TestCase;

class DocumentReuseTest extends TestCase
{
    use RefreshDatabase;

    // a 13-digit number that passes the Luhn checksum, born 1 January 1980
    private const VALID_ID = '8001015009087';

    private const ID_TEXT = "REPUBLIC OF SOUTH AFRICA\nSurname: MOKOENA\nNames: THANDI\nIdentity Number: 800101 5009 087\nDate of Birth: 01 JAN 1980";

    private const TYPES = ['id_document', 'payslip', 'bank_statement'];

    private const BANK_STATEMENT_TEXT = "BANK STATEMENT\nName: Thandi Mokoena\nClosing Balance: 0.00";

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        Http::fake([
            'http://localhost:8000/api/ml/predict' => Http::response([
                'eligibility_outcome' => 'Approved',
                'confidence_score' => 96,
                'fraud_risk_score' => 'low',
                'reasoning_notes' => 'Strong repayment profile.',
            ], 200),
        ]);
    }

    private function applicant(array $attributes = []): User
    {
        /** @var User $user */
        $user = User::factory()->create(array_merge([
            'name' => 'Thandi Mokoena',
            'id_number' => self::VALID_ID,
            'date_of_birth' => '1980-01-01',
            'monthly_income' => 20000,
        ], $attributes));

        return $user;
    }

    /**
     * An earlier application with all three documents stored and read.
     *
     * @return array<string, Document>
     */
    private function earlierDocuments(User $user, ?Carbon $uploadedAt = null, string $idText = self::ID_TEXT): array
    {
        $application = LoanApplication::create([
            'user_id' => $user->id,
            'amount_requested' => 1000,
            'status' => 'Approved',
        ]);

        $documents = [];

        foreach (self::TYPES as $type) {
            $path = 'loan-applications/' . $application->id . '/' . $type . '-' . fake()->uuid() . '-' . $type . '.jpg';
            Storage::disk('public')->put($path, 'image');

            $documents[$type] = Document::create([
                'application_id' => $application->id,
                'type' => $type,
                'file_path' => $path,
                'uploaded_at' => $uploadedAt ?? now()->subDays(10),
            ]);

            OcrResult::create([
                'document_id' => $documents[$type]->id,
                'extracted_text' => match ($type) {
                    'id_document' => $idText,
                    'bank_statement' => self::BANK_STATEMENT_TEXT,
                    default => strtoupper($type) . ' TEXT',
                },
            ]);
        }

        return $documents;
    }

    private function expectOcrRuns(int $times): void
    {
        $mock = Mockery::mock(OcrService::class);
        $mock->shouldReceive('process')->times($times)->andReturnUsing(fn (Document $document) => OcrResult::create([
            'document_id' => $document->id,
            'extracted_text' => match ($document->type) {
                'id_document' => self::ID_TEXT,
                'bank_statement' => self::BANK_STATEMENT_TEXT,
                default => 'NEW UPLOAD TEXT',
            },
        ]));
        $this->app->instance(OcrService::class, $mock);
    }

    private function submit(User $user, array $documentFields): TestResponse
    {
        return $this->actingAs($user)->post('/applications', [
            'amount_requested' => '2500.00',
            'no_of_dependents' => 2,
            'education' => 'Graduate',
            'loan_term' => 12,
            'cibil_score_band' => 'Good',
            'residential_assets_value' => 0,
            'commercial_assets_value' => 0,
            'luxury_assets_value' => 0,
            'bank_asset_value' => 0,
            ...$documentFields,
        ]);
    }

    /**
     * Form fields that reuse every given document.
     */
    private function reuseAll(array $documents): array
    {
        $fields = [];

        foreach ($documents as $type => $document) {
            $fields[$type . '_reuse'] = $document->id;
        }

        return $fields;
    }

    private function latestApplication(User $user): LoanApplication
    {
        return LoanApplication::with('documents.ocrResult', 'aiAssessment')->where('user_id', $user->id)->latest('id')->firstOrFail();
    }

    public function test_the_form_offers_the_latest_recent_document_for_each_type(): void
    {
        $user = $this->applicant();
        $this->earlierDocuments($user, now()->subDays(40));
        $latest = $this->earlierDocuments($user, now()->subDays(5));

        $this->actingAs($user)->get(route('applications.create'))
            ->assertOk()
            ->assertSee('Upload a new file')
            ->assertSee('Reuse latest (id_document.jpg, ' . now()->subDays(5)->format('j M Y') . ')')
            ->assertSee('value="' . $latest['payslip']->id . '"', false);
    }

    public function test_the_form_shows_only_the_upload_field_without_earlier_documents(): void
    {
        $user = $this->applicant();
        $this->earlierDocuments($this->applicant(['email' => 'someone@example.com']));

        $this->actingAs($user)->get(route('applications.create'))
            ->assertOk()
            ->assertSee('Choose file')
            ->assertDontSee('Upload a new file')
            ->assertDontSee('Reuse latest');
    }

    public function test_reusing_all_three_documents_copies_them_without_running_ocr(): void
    {
        $user = $this->applicant();
        $earlier = $this->earlierDocuments($user);
        $this->expectOcrRuns(0);

        $this->submit($user, $this->reuseAll($earlier))->assertSessionHasNoErrors()->assertRedirect();

        $application = $this->latestApplication($user);
        $this->assertCount(3, $application->documents);

        foreach ($application->documents as $document) {
            $source = $earlier[$document->type];
            $this->assertSame($source->file_path, $document->file_path);
            $this->assertSame($source->id, $document->reused_from_document_id);
            $this->assertSame($source->ocrResult->extracted_text, $document->ocrResult->extracted_text);
        }

        // the ID check passed and the model's result stands
        $this->assertSame('Approved', $application->status);
        $this->assertSame('low', $application->aiAssessment->fraud_risk_score);

        $this->actingAs($user)->get(route('applications.confirmation', $application))
            ->assertOk()
            ->assertSee('Reused from earlier application')
            ->assertSee('id_document.jpg');

        $this->actingAs($user)->get(route('applications.show', $application))
            ->assertOk()
            ->assertSee('Reused from earlier application');

        /** @var User $admin */
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->get(route('admin.applications.show', $application))
            ->assertOk()
            ->assertSee('Reused from earlier application')
            ->assertSee('PAYSLIP TEXT');
    }

    public function test_one_slot_can_be_reused_while_the_others_are_uploaded(): void
    {
        $user = $this->applicant();
        $earlier = $this->earlierDocuments($user);
        $this->expectOcrRuns(2);

        $this->submit($user, [
            'id_document_reuse' => $earlier['id_document']->id,
            'payslip_reuse' => '',
            'payslip' => UploadedFile::fake()->image('payslip.jpg'),
            'bank_statement' => UploadedFile::fake()->image('bank.jpg'),
        ])->assertSessionHasNoErrors();

        $documents = $this->latestApplication($user)->documents->keyBy('type');
        $this->assertTrue($documents['id_document']->isReused());
        $this->assertFalse($documents['payslip']->isReused());
        $this->assertFalse($documents['bank_statement']->isReused());
        $this->assertSame('NEW UPLOAD TEXT', $documents['payslip']->ocrResult->extracted_text);
    }

    public function test_a_new_upload_still_works_when_reuse_is_offered(): void
    {
        $user = $this->applicant();
        $this->earlierDocuments($user);
        $this->expectOcrRuns(3);

        $this->submit($user, [
            'id_document_reuse' => '',
            'payslip_reuse' => '',
            'bank_statement_reuse' => '',
            'id_document' => UploadedFile::fake()->image('id.jpg'),
            'payslip' => UploadedFile::fake()->image('payslip.jpg'),
            'bank_statement' => UploadedFile::fake()->image('bank.jpg'),
        ])->assertSessionHasNoErrors()->assertRedirect();

        $application = $this->latestApplication($user);
        $this->assertCount(3, $application->documents);
        $this->assertTrue($application->documents->every(fn (Document $document) => ! $document->isReused()));
        $this->assertTrue($application->documents->every(fn (Document $document) => $document->fileExists() && $document->disk === Document::PRIVATE_DISK));
        $this->assertSame('Approved', $application->status);
    }

    public function test_each_slot_needs_an_upload_or_a_reuse_choice(): void
    {
        $user = $this->applicant();
        $earlier = $this->earlierDocuments($user);
        $this->expectOcrRuns(0);

        $this->submit($user, ['id_document_reuse' => $earlier['id_document']->id])
            ->assertSessionHasErrors(['payslip', 'bank_statement'])
            ->assertSessionDoesntHaveErrors('id_document');

        $this->assertSame(1, LoanApplication::count());
    }

    public function test_another_users_document_cannot_be_reused(): void
    {
        $user = $this->applicant();
        $someoneElse = $this->applicant(['email' => 'someone@example.com']);
        $theirs = $this->earlierDocuments($someoneElse);
        $this->expectOcrRuns(0);

        $this->submit($user, $this->reuseAll($theirs))
            ->assertSessionHasErrors('id_document');

        $this->assertSame(0, LoanApplication::where('user_id', $user->id)->count());
        $this->assertSame(3, Document::count());
    }

    public function test_a_document_older_than_90_days_is_refused_and_not_offered(): void
    {
        $user = $this->applicant();
        $old = $this->earlierDocuments($user, now()->subDays(91));
        $this->expectOcrRuns(0);

        $this->actingAs($user)->get(route('applications.create'))
            ->assertOk()
            ->assertDontSee('Reuse latest');

        $this->submit($user, $this->reuseAll($old))
            ->assertSessionHasErrors(['id_document', 'payslip', 'bank_statement']);

        $this->assertSame(1, LoanApplication::count());
    }

    public function test_a_document_of_another_type_cannot_be_reused_in_a_slot(): void
    {
        $user = $this->applicant();
        $earlier = $this->earlierDocuments($user);
        $this->expectOcrRuns(0);

        $this->submit($user, [
            ...$this->reuseAll($earlier),
            'id_document_reuse' => $earlier['payslip']->id,
        ])->assertSessionHasErrors('id_document');

        $this->assertSame(1, LoanApplication::count());
    }

    public function test_a_missing_file_does_not_break_the_pages_and_the_ocr_text_still_shows(): void
    {
        $user = $this->applicant();
        $earlier = $this->earlierDocuments($user);
        $this->expectOcrRuns(0);

        $this->submit($user, $this->reuseAll($earlier))->assertSessionHasNoErrors();
        $application = $this->latestApplication($user);

        Storage::disk('public')->delete($earlier['payslip']->file_path);

        $this->actingAs($user)->get(route('applications.confirmation', $application))
            ->assertOk()
            ->assertSee('File no longer available')
            ->assertSee('Reused from earlier application');

        $this->actingAs($user)->get(route('applications.show', $application))
            ->assertOk()
            ->assertSee('File no longer available');

        /** @var User $admin */
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->get(route('admin.applications.show', $application))
            ->assertOk()
            ->assertSeeInOrder(['Payslip', 'File no longer available', 'PAYSLIP TEXT']);
    }

    public function test_the_id_check_still_flags_a_bad_reused_id_document(): void
    {
        $user = $this->applicant();
        // the earlier ID document shows a different ID number from the profile
        $earlier = $this->earlierDocuments($user, null, str_replace('800101 5009 087', '750505 5009 081', self::ID_TEXT));
        $this->expectOcrRuns(0);

        $this->submit($user, $this->reuseAll($earlier))->assertSessionHasNoErrors();

        $application = $this->latestApplication($user);
        $this->assertSame('Manual Review', $application->status);
        $this->assertSame('high', $application->aiAssessment->fraud_risk_score);
        $this->assertStringStartsWith('Document check:', $application->aiAssessment->reasoning_notes);
    }
}
