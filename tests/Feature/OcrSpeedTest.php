<?php

namespace Tests\Feature;

use App\Jobs\ReadDocumentText;
use App\Models\Document;
use App\Models\LoanApplication;
use App\Models\OcrResult;
use App\Models\User;
use App\Services\OcrService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class OcrSpeedTest extends TestCase
{
    use RefreshDatabase;

    // a 13-digit number that passes the Luhn checksum, born 1 January 1980
    private const VALID_ID = '8001015009087';

    private const ID_TEXT = "REPUBLIC OF SOUTH AFRICA\nSurname: MOKOENA\nNames: THANDI\nIdentity Number: 800101 5009 087\nDate of Birth: 01 JAN 1980";

    // as Tesseract reads the sample payslip
    private const PAYSLIP_TEXT = "Payslip\nTotal Earnings 11600\nTotal Deductions 2100\nNet Pay 9500";

    // the applicant's name and the R0 bank assets the form sends
    private const BANK_STATEMENT_TEXT = "Statement\nName: Thandi Mokoena\nClosing Balance: 0.00 ZAR";

    /**
     * A stored document holding a real image of the given size.
     */
    private function storedDocument(int $width, int $height, string $type = 'payslip'): Document
    {
        $application = LoanApplication::create([
            'user_id' => User::factory()->create()->id,
            'amount_requested' => 1000,
            'status' => 'Pending',
        ]);

        $path = UploadedFile::fake()->image('scan.jpg', $width, $height)
            ->storeAs('loan-applications/' . $application->id, $type . '-scan.jpg', Document::PRIVATE_DISK);

        return Document::create([
            'application_id' => $application->id,
            'type' => $type,
            'file_path' => $path,
            'disk' => Document::PRIVATE_DISK,
            'uploaded_at' => now(),
        ]);
    }

    /**
     * An OcrService whose Tesseract call is replaced: $read gets the path it would read.
     */
    private function ocrReading(callable $read): OcrService
    {
        return new class($read) extends OcrService
        {
            public array $paths = [];

            public function __construct(private $read)
            {
            }

            protected function readText(string $path): string
            {
                $this->paths[] = $path;

                return ($this->read)($path);
            }
        };
    }

    /** fake uploads delete their file once dropped, so keep them until the test ends */
    private array $images = [];

    private function fakeImage(int $width, int $height, string $name = 'scan.jpg'): string
    {
        return ($this->images[] = UploadedFile::fake()->image($name, $width, $height))->getRealPath();
    }

    public function test_a_large_image_is_copied_with_its_longest_side_at_1600_px(): void
    {
        foreach ([[3000, 2000, 1600, 1067], [1200, 4000, 480, 1600]] as [$width, $height, $newWidth, $newHeight]) {
            $original = $this->fakeImage($width, $height);
            $before = md5_file($original);

            $copy = app(OcrService::class)->shrunkCopy($original);

            $this->assertNotNull($copy);
            $this->assertSame([$newWidth, $newHeight], array_slice(getimagesize($copy), 0, 2));
            $this->assertSame($before, md5_file($original));
            @unlink($copy);
        }
    }

    public function test_a_png_is_copied_too(): void
    {
        $copy = app(OcrService::class)->shrunkCopy($this->fakeImage(2400, 1200, 'scan.png'));

        $this->assertNotNull($copy);
        $this->assertSame([1600, 800], array_slice(getimagesize($copy), 0, 2));
        @unlink($copy);
    }

    public function test_small_images_are_read_as_they_are_and_never_enlarged(): void
    {
        // the sample files are 1100x700, 1200x1451 and 791x1024
        foreach ([[1100, 700], [1200, 1451], [791, 1024], [1600, 1600]] as [$width, $height]) {
            $this->assertNull(app(OcrService::class)->shrunkCopy($this->fakeImage($width, $height)));
        }
    }

    public function test_anything_that_cannot_be_scaled_falls_back_to_the_original(): void
    {
        $service = app(OcrService::class);

        // not an image
        $text = tempnam(sys_get_temp_dir(), 'ocr-test-');
        file_put_contents($text, 'not an image');
        $this->assertNull($service->shrunkCopy($text));

        // a missing file
        $this->assertNull($service->shrunkCopy(sys_get_temp_dir() . '/no-such-file.jpg'));

        // a JPEG whose header says 3000x2000 but whose image data is cut off
        $broken = tempnam(sys_get_temp_dir(), 'ocr-test-');
        file_put_contents($broken, substr(file_get_contents($this->fakeImage(3000, 2000)), 0, 600));
        $this->assertSame(3000, getimagesize($broken)[0]);
        $this->assertNull($service->shrunkCopy($broken));

        @unlink($text);
        @unlink($broken);
    }

    public function test_the_copy_is_read_and_then_deleted(): void
    {
        $document = $this->storedDocument(3000, 2000);
        $original = $document->storage()->path($document->file_path);

        $service = $this->ocrReading(function (string $path) use ($original) {
            $this->assertNotSame($original, $path);
            $this->assertFileExists($path);
            $this->assertSame(1600, getimagesize($path)[0]);

            return self::PAYSLIP_TEXT;
        });

        $result = $service->process($document);

        $this->assertSame(self::PAYSLIP_TEXT, $result->extracted_text);
        $this->assertCount(1, $service->paths);
        $this->assertFileDoesNotExist($service->paths[0]);
        $this->assertFileExists($original);
    }

    public function test_a_small_image_is_read_from_the_original(): void
    {
        $document = $this->storedDocument(1100, 700, 'id_document');

        $service = $this->ocrReading(fn () => self::ID_TEXT);
        $service->process($document);

        $this->assertSame([$document->storage()->path($document->file_path)], $service->paths);
        $this->assertFileExists($service->paths[0]);
    }

    public function test_if_reading_the_copy_fails_the_original_is_read_and_the_copy_still_deleted(): void
    {
        $document = $this->storedDocument(3000, 2000);
        $original = $document->storage()->path($document->file_path);

        $service = $this->ocrReading(function (string $path) use ($original) {
            if ($path !== $original) {
                throw new RuntimeException('Tesseract could not read the copy');
            }

            return self::PAYSLIP_TEXT;
        });

        $this->assertSame(self::PAYSLIP_TEXT, $service->process($document)->extracted_text);
        $this->assertCount(2, $service->paths);
        $this->assertSame($original, $service->paths[1]);
        $this->assertFileDoesNotExist($service->paths[0]);
    }

    public function test_if_both_reads_fail_there_is_no_result_and_no_copy_left(): void
    {
        $document = $this->storedDocument(3000, 2000);

        $service = $this->ocrReading(fn () => throw new RuntimeException('Tesseract failed'));

        $this->assertNull($service->process($document));
        $this->assertCount(2, $service->paths);
        $this->assertFileDoesNotExist($service->paths[0]);
        $this->assertSame(0, OcrResult::count());
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

    /**
     * Submit with OCR mocked: the ID and payslip return their sample text, and it records each document it reads.
     */
    private function submit(User $user, array &$read): \Illuminate\Testing\TestResponse
    {
        Http::fake([
            'http://localhost:8000/api/ml/predict' => Http::response([
                'eligibility_outcome' => 'Approved',
                'confidence_score' => 96,
                'fraud_risk_score' => 'low',
                'reasoning_notes' => 'Strong repayment profile.',
            ], 200),
        ]);

        $mock = Mockery::mock(OcrService::class);
        $mock->shouldReceive('process')->andReturnUsing(function (Document $document) use (&$read) {
            $read[] = $document->type;

            return OcrResult::create([
                'document_id' => $document->id,
                'extracted_text' => match ($document->type) {
                    'id_document' => self::ID_TEXT,
                    'payslip' => self::PAYSLIP_TEXT,
                    default => self::BANK_STATEMENT_TEXT,
                },
            ]);
        });
        $this->app->instance(OcrService::class, $mock);

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
            'id_document' => UploadedFile::fake()->image('id-document.jpg', 800, 600),
            'payslip' => UploadedFile::fake()->image('payslip.jpg', 800, 600),
            'bank_statement' => UploadedFile::fake()->image('bank-statement.png', 800, 600),
        ]);
    }

    public function test_all_three_documents_are_read_during_the_submission_and_the_checks_see_them(): void
    {
        Bus::fake([ReadDocumentText::class]);
        $user = $this->applicant();
        $read = [];

        $response = $this->submit($user, $read)->assertRedirect();

        // nothing is left for after the response
        $this->assertSame([], \App\Http\Controllers\LoanApplicationController::READ_AFTER_RESPONSE);
        $this->assertSame(['id_document', 'payslip', 'bank_statement'], $read);
        Bus::assertNothingDispatched();
        $this->assertFalse($response->headers->has('Connection'));

        $application = LoanApplication::with('documents.ocrResult', 'aiAssessment')->where('user_id', $user->id)->firstOrFail();

        // the name, ID number, payslip income, and bank statement name and balance all matched
        $this->assertSame('Approved', $application->status);
        $this->assertSame('low', $application->aiAssessment->fraud_risk_score);
        $this->assertSame('Strong repayment profile.', $application->aiAssessment->reasoning_notes);
        $this->assertSame(self::BANK_STATEMENT_TEXT, $application->documents->firstWhere('type', 'bank_statement')->ocrResult->extracted_text);

        /** @var User $admin */
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->get(route('admin.applications.show', $application))
            ->assertOk()
            ->assertSee('Gross R11,600.00, Net R9,500.00')
            ->assertSeeInOrder(['Bank statement balance', 'R0.00'])
            ->assertDontSee('Text not read yet');
    }

    public function test_a_reused_bank_statement_is_not_read_again(): void
    {
        Bus::fake([ReadDocumentText::class]);
        $user = $this->applicant();
        $earlier = LoanApplication::create(['user_id' => $user->id, 'amount_requested' => 1000, 'status' => 'Approved']);
        Storage::disk(Document::PRIVATE_DISK)->put('loan-applications/' . $earlier->id . '/bank.jpg', 'image');
        $source = Document::create([
            'application_id' => $earlier->id,
            'type' => 'bank_statement',
            'file_path' => 'loan-applications/' . $earlier->id . '/bank.jpg',
            'disk' => Document::PRIVATE_DISK,
            'uploaded_at' => now()->subDays(3),
        ]);
        OcrResult::create(['document_id' => $source->id, 'extracted_text' => 'EARLIER BANK TEXT']);

        Http::fake(['http://localhost:8000/api/ml/predict' => Http::response([
            'eligibility_outcome' => 'Approved', 'confidence_score' => 96, 'fraud_risk_score' => 'low', 'reasoning_notes' => 'Fine.',
        ], 200)]);
        $mock = Mockery::mock(OcrService::class);
        $mock->shouldReceive('process')->twice()->andReturnNull();
        $this->app->instance(OcrService::class, $mock);

        $this->actingAs($user)->post('/applications', [
            'amount_requested' => '2500.00', 'no_of_dependents' => 2, 'education' => 'Graduate', 'loan_term' => 12,
            'cibil_score_band' => 'Good', 'residential_assets_value' => 0, 'commercial_assets_value' => 0,
            'luxury_assets_value' => 0, 'bank_asset_value' => 0,
            'id_document' => UploadedFile::fake()->image('id-document.jpg'),
            'payslip' => UploadedFile::fake()->image('payslip.jpg'),
            'bank_statement_reuse' => $source->id,
        ])->assertRedirect()->assertHeaderMissing('Connection');

        Bus::assertNothingDispatched();
    }

    public function test_the_job_never_throws_and_records_unreadable_text(): void
    {
        $document = $this->storedDocument(800, 600, 'bank_statement');

        // OCR found nothing: an empty result, so the page says no readable text instead of "not read yet"
        $nothing = Mockery::mock(OcrService::class);
        $nothing->shouldReceive('process')->once()->andReturnNull();
        (new ReadDocumentText($document->id))->handle($nothing);

        $this->assertSame('', $document->fresh()->ocrResult->extracted_text);

        // OCR throws, or the document is gone: nothing breaks
        $throws = Mockery::mock(OcrService::class);
        $throws->shouldReceive('process')->andThrow(new RuntimeException('boom'));
        $other = $this->storedDocument(800, 600, 'bank_statement');
        (new ReadDocumentText($other->id))->handle($throws);
        (new ReadDocumentText(999999))->handle($throws);

        $this->assertNull($other->fresh()->ocrResult);

        // already read: not read again
        $never = Mockery::mock(OcrService::class);
        $never->shouldNotReceive('process');
        (new ReadDocumentText($document->id))->handle($never);
    }
}
