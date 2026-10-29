<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\LoanApplication;
use App\Models\OcrResult;
use App\Models\User;
use App\Services\AmountParser;
use App\Services\ApplicationAssessor;
use App\Services\BankStatementExtractor;
use App\Services\FraudCheckService;
use App\Services\OcrService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Mockery;
use Tests\TestCase;

class BankStatementCheckTest extends TestCase
{
    use RefreshDatabase;

    private const ML_URL = 'http://localhost:8000/api/ml/predict';

    // a 13-digit number that passes the Luhn checksum, born 1 January 1980
    private const VALID_ID = '8001015009087';

    private const ID_TEXT = "REPUBLIC OF SOUTH AFRICA\nSurname: MOKOENA\nNames: THANDI\nIdentity Number: 800101 5009 087\nDate of Birth: 01 JAN 1980";

    private const PAYSLIP_TEXT = "Payslip\nTotal Earnings 11600\nTotal Deductions 2100\nNet Pay 9500";

    // as Tesseract reads the sample statement (dummy_bankStatement.jpg), shortened
    private const SAMPLE_STATEMENT = "a bs a 7th Floor, Absa Towers West\n15 Troye Street, Johannesburg, 2001,\n\nSouth Africa\n"
        . "Date of issue: 10 May, 2022 Currency: ZAR\nName: John Citizen Limit of overdraft 1.00\n"
        . "Address: 19 2nd Ave, Westdene, Johannesburg, 2092,\nSouth Africa\n\n"
        . "Statement Period: 22 Mar, 2022 o 22 Apr, 2022\nClosing Balance: 350 ZAR\n\nACCOUNT TRANSACTION DETAILS\n\n"
        . "Date Description Withdrawals (ZAR) _ Deposits (ZAR) Balance (ZAR)\n22Mar  Opening Balance 500.00 85.00 500.00\n"
        . "25Mar  Payment for Intemet 50.00 450\n29Mar  Transfer 7229 15.00\n13Apr  Deposit 74.00\n\n"
        . "Account closing Balance 350 ZAR\n\nank (South Africa) Uimited\n\nPage1/1";

    private const NAME_FLAG = 'Name on the bank statement does not match the applicant';

    private function statement(string $name = 'Thandi Mokoena', string $balance = '0.00'): string
    {
        return "ABSA BANK\nName: {$name}\nStatement Period: 22 Mar, 2022 to 22 Apr, 2022\nClosing Balance: {$balance} ZAR\n22Mar Opening Balance 500.00";
    }

    private function extractor(): BankStatementExtractor
    {
        return new BankStatementExtractor(new AmountParser());
    }

    private function applicant(array $attributes = []): User
    {
        /** @var User $user */
        $user = User::factory()->create(array_merge([
            'name' => 'Thandi Mokoena',
            'id_number' => self::VALID_ID,
            'date_of_birth' => '1980-01-01',
            'monthly_income' => 9500,
        ], $attributes));

        return $user;
    }

    /**
     * A stored application whose documents OCR has already read (null: no bank statement document at all).
     */
    private function application(?string $bankText, float $bankAssets = 0, array $userAttributes = []): LoanApplication
    {
        $user = $this->applicant($userAttributes);
        $application = LoanApplication::create([
            'user_id' => $user->id,
            'amount_requested' => 2500,
            'loan_term' => 12,
            'status' => 'Pending',
            'bank_asset_value' => $bankAssets,
            'stated_monthly_income' => 9500,
        ]);

        $texts = ['id_document' => self::ID_TEXT, 'payslip' => self::PAYSLIP_TEXT];

        if ($bankText !== null) {
            $texts['bank_statement'] = $bankText;
        }

        foreach ($texts as $type => $text) {
            $document = Document::create([
                'application_id' => $application->id,
                'type' => $type,
                'file_path' => 'loan-applications/' . $application->id . '/' . $type . '.jpg',
                'disk' => Document::PRIVATE_DISK,
                'uploaded_at' => now(),
            ]);

            OcrResult::create(['document_id' => $document->id, 'extracted_text' => $text]);
        }

        return $application;
    }

    private function check(LoanApplication $application): array
    {
        return app(FraudCheckService::class)->check($application);
    }

    private function fakeModel(): void
    {
        Http::fake([self::ML_URL => Http::response([
            'eligibility_outcome' => 'Approved',
            'confidence_score' => 96,
            'fraud_risk_score' => 'low',
            'reasoning_notes' => 'Strong repayment profile.',
        ], 200)]);
    }

    private function fakeOcr(string $bankText, string $payslipText = self::PAYSLIP_TEXT, string $idText = self::ID_TEXT): void
    {
        $mock = Mockery::mock(OcrService::class);
        $mock->shouldReceive('process')->andReturnUsing(fn (Document $document) => OcrResult::create([
            'document_id' => $document->id,
            'extracted_text' => match ($document->type) {
                'id_document' => $idText,
                'payslip' => $payslipText,
                default => $bankText,
            },
        ]));
        $this->app->instance(OcrService::class, $mock);
    }

    private function apply(User $user, string $bankAssets = '0', string $amount = '2500', int $term = 12): LoanApplication
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
            'bank_asset_value' => $bankAssets,
            'id_document' => UploadedFile::fake()->image('id.jpg'),
            'payslip' => UploadedFile::fake()->image('payslip.jpg'),
            'bank_statement' => UploadedFile::fake()->image('bank.jpg'),
        ])->assertSessionHasNoErrors()->assertRedirect();

        return LoanApplication::with('aiAssessment')->where('user_id', $user->id)->latest('id')->firstOrFail();
    }

    private function reviewPage(LoanApplication $application)
    {
        /** @var User $admin */
        $admin = User::factory()->create(['role' => 'admin']);

        return $this->actingAs($admin)->get(route('admin.applications.show', $application))->assertOk();
    }

    // --- extractor ---

    public function test_the_sample_statement_gives_the_holder_and_the_closing_balance(): void
    {
        $this->assertSame(
            ['holder' => 'John Citizen Limit of overdraft', 'balance' => 350.0, 'balance_type' => 'closing'],
            $this->extractor()->extract(self::SAMPLE_STATEMENT)
        );
    }

    public function test_balance_amount_formats_parse(): void
    {
        foreach ([
            'Closing Balance: R12,345.67' => 12345.67,
            'Closing balance R 12,345.67' => 12345.67,
            'Closing Balance: 12 345.67 ZAR' => 12345.67,
            'Closing Balance 12345,67' => 12345.67,
            'CLOSING BALANCE: ZAR 1 234 567,89' => 1234567.89,
            'Closing Balance: 350' => 350.0,
            'Closing Balance: 0.00' => 0.0,
            "Closing balance\n\n8 250,50" => 8250.5,
            "Available Balance: 1,500.00\nClosing Balance: 2,000.00" => 1500.0,
            "Closing Balance: 2,000.00\nAvailable balance 1 500,00" => 1500.0,
        ] as $text => $expected) {
            $this->assertSame($expected, $this->extractor()->extract($text)['balance'], $text);
        }

        $this->assertSame('available', $this->extractor()->extract('Available balance 10.00')['balance_type']);
    }

    public function test_what_cannot_be_found_is_null_and_it_never_throws(): void
    {
        foreach ([
            null,
            '',
            'Statement without a balance',
            'Opening Balance 500.00',
            'Closing Balance: -350.00',
            'Closing Balance: (350.00)',
            'Closing Balance: 350.00 Dr',
            'Closing Balance: abc',
            str_repeat("Closing Balance \x00\xff", 50),
        ] as $text) {
            $this->assertNull($this->extractor()->extract($text)['balance'], (string) $text);
        }

        $this->assertNull($this->extractor()->extract('Closing Balance: 350')['holder']);
        $this->assertSame('T. Mokoena', $this->extractor()->extract("Account holder: T. Mokoena  Branch 632005")['holder']);
    }

    // --- the check ---

    public function test_a_matching_name_and_balance_pass(): void
    {
        $result = $this->check($this->application($this->statement('Thandi Mokoena', '12,000.00'), 12000));

        $this->assertSame([], $result['flags']);
        $this->assertSame([], $result['notes']);
    }

    public function test_the_name_allows_ocr_noise_an_initial_and_any_case(): void
    {
        foreach (['THANDI MOKOEMA', 'thandi mokoena', 'Ms T Mokoena', 'MOKOENA T.', 'Mrs Thandi Grace Mokoena'] as $name) {
            $this->assertSame([], $this->check($this->application($this->statement($name)))['flags'], $name);
        }
    }

    public function test_a_wrong_name_is_flagged(): void
    {
        foreach (['John Citizen', 'Thandi Nkosi', 'Peter Mokoena', 'P Mokoena'] as $name) {
            $this->assertSame([self::NAME_FLAG], $this->check($this->application($this->statement($name)))['flags'], $name);
        }

        // the sample statement belongs to John Citizen
        $this->assertSame([self::NAME_FLAG], $this->check($this->application(self::SAMPLE_STATEMENT, 350))['flags']);
        // for John Citizen the statement passes (the ID document in these tests is still Thandi's)
        $flags = $this->check($this->application(self::SAMPLE_STATEMENT, 350, ['name' => 'John Citizen', 'email' => 'john@example.com']))['flags'];
        $this->assertNotContains(self::NAME_FLAG, $flags);
        $this->assertSame([], array_filter($flags, fn ($flag) => str_contains($flag, 'bank')));
    }

    public function test_without_a_holder_line_the_name_is_looked_for_anywhere_on_the_statement(): void
    {
        $text = "ABSA BANK\nMRS T MOKOENA\n19 2nd Ave, Westdene\nClosing Balance: 0.00";

        $this->assertSame([], $this->check($this->application($text))['flags']);
        $this->assertSame([self::NAME_FLAG], $this->check($this->application(str_replace('MOKOENA', 'CITIZEN', $text)))['flags']);
    }

    public function test_the_name_threshold_comes_from_config(): void
    {
        config(['loan.bank_statement_name_similarity' => 100]);

        $this->assertSame([self::NAME_FLAG], $this->check($this->application($this->statement('Thandi Mokoema')))['flags']);
    }

    public function test_a_balance_more_than_20_percent_off_is_flagged(): void
    {
        // statement R350: R280 to R420 pass
        foreach ([350, 280, 420] as $stated) {
            $this->assertSame([], $this->check($this->application($this->statement('Thandi Mokoena', '350'), $stated))['flags'], (string) $stated);
        }

        $this->assertSame(
            ['Stated bank assets do not match the bank statement (stated R279.00, statement R350.00)'],
            $this->check($this->application($this->statement('Thandi Mokoena', '350'), 279))['flags']
        );
        $this->assertSame(
            ['Stated bank assets do not match the bank statement (stated R80,000.00, statement R350.00)'],
            $this->check($this->application($this->statement('Thandi Mokoena', '350'), 80000))['flags']
        );

        // a zero balance only matches zero
        $this->assertSame(
            ['Stated bank assets do not match the bank statement (stated R100.00, statement R0.00)'],
            $this->check($this->application($this->statement('Thandi Mokoena', '0.00'), 100))['flags']
        );
    }

    public function test_the_balance_tolerance_comes_from_config(): void
    {
        config(['loan.bank_balance_tolerance' => 0.05]);

        $this->assertCount(1, $this->check($this->application($this->statement('Thandi Mokoena', '350'), 300))['flags']);
        $this->assertSame([], $this->check($this->application($this->statement('Thandi Mokoena', '350'), 340))['flags']);
    }

    public function test_no_balance_found_does_not_flag_and_adds_a_note(): void
    {
        $result = $this->check($this->application("Name: Thandi Mokoena\nOpening Balance 500.00", 80000));

        $this->assertSame([], $result['flags']);
        $this->assertSame([FraudCheckService::NOTE_BANK_BALANCE_UNREADABLE], $result['notes']);
    }

    public function test_an_unreadable_or_missing_statement_does_not_flag_and_adds_a_note(): void
    {
        foreach (['', "  \n ", '___ ...', null] as $text) {
            $result = $this->check($this->application($text, 80000));

            $this->assertSame([], $result['flags']);
            $this->assertSame([FraudCheckService::NOTE_BANK_STATEMENT_UNREADABLE], $result['notes']);
        }
    }

    // --- with the other checks, on submission ---

    public function test_a_wrong_name_sends_the_application_to_manual_review(): void
    {
        $this->fakeModel();
        $this->fakeOcr($this->statement('John Citizen', '350'));

        $application = $this->apply($this->applicant(), '350');

        $this->assertSame('Manual Review', $application->status);
        $this->assertSame('high', $application->aiAssessment->fraud_risk_score);
        $this->assertSame("Document check:\n- " . self::NAME_FLAG . "\n\nStrong repayment profile.", $application->aiAssessment->reasoning_notes);

        $this->reviewPage($application)
            ->assertSee('Overridden by the document check. See the reasoning notes.')
            ->assertSeeInOrder(['Payslip income', 'Gross R11,600.00, Net R9,500.00', 'Bank statement balance', 'R350.00'])
            ->assertSee(self::NAME_FLAG);
    }

    public function test_a_matching_statement_keeps_the_models_result(): void
    {
        $this->fakeModel();
        $this->fakeOcr($this->statement('Thandi Mokoena', '350.00'));

        $application = $this->apply($this->applicant(), '350');

        $this->assertSame('Approved', $application->status);
        $this->assertSame('low', $application->aiAssessment->fraud_risk_score);
        $this->assertSame('Strong repayment profile.', $application->aiAssessment->reasoning_notes);
        $this->reviewPage($application)->assertDontSee('Overridden by the document check');
    }

    public function test_no_balance_adds_only_a_note_and_the_page_says_not_found(): void
    {
        $this->fakeModel();
        $this->fakeOcr("Name: Thandi Mokoena\nOpening Balance 500.00");

        $application = $this->apply($this->applicant(), '80000');

        $this->assertSame('Approved', $application->status);
        $this->assertSame("Strong repayment profile.\n\nNote: Balance could not be read from the bank statement", $application->aiAssessment->reasoning_notes);
        $this->reviewPage($application)
            ->assertSeeInOrder(['Bank statement balance', 'Not found on the bank statement'])
            ->assertDontSee('Overridden by the document check');
    }

    public function test_all_findings_combine_in_order_with_the_affordability_check(): void
    {
        $this->fakeModel();
        // the ID shows another ID number, the payslip says R9,500 net, and the statement is John Citizen's with R350
        $this->fakeOcr(
            $this->statement('John Citizen', '350'),
            self::PAYSLIP_TEXT,
            str_replace('800101 5009 087', '750505 5009 081', self::ID_TEXT)
        );

        // stated R5,000 a month and R80,000 in the bank; R3,530.46 a month is 70.6% of R5,000
        $application = $this->apply($this->applicant(['monthly_income' => 5000]), '80000', '20000', 6);

        $this->assertSame('Manual Review', $application->status);
        $this->assertSame('high', $application->aiAssessment->fraud_risk_score);
        $this->assertSame(
            "Document check:\n- " . FraudCheckService::FLAG_ID_NOT_ON_DOCUMENT
            . "\n- Stated income does not match the payslip (stated R5,000.00, payslip R9,500.00)"
            . "\n- " . self::NAME_FLAG
            . "\n- Stated bank assets do not match the bank statement (stated R80,000.00, statement R350.00)"
            . "\n\nAffordability check:\n- Requested repayment is 70.6% of stated income (limit 35%)"
            . "\n\nStrong repayment profile.",
            $application->aiAssessment->reasoning_notes
        );

        $this->reviewPage($application)
            ->assertSee('Flagged by the document and affordability checks. See the reasoning notes.')
            ->assertDontSee('Overridden by the document check');
    }

    public function test_notes_from_the_payslip_and_the_statement_come_after_the_model_notes(): void
    {
        $this->fakeModel();
        $this->fakeOcr('', 'Payslip without figures');

        $application = $this->apply($this->applicant());

        $this->assertSame('Approved', $application->status);
        $this->assertSame(
            "Strong repayment profile.\n\nNote: Income could not be read from the payslip\nNote: Bank statement could not be read",
            $application->aiAssessment->reasoning_notes
        );
    }

    // --- retries and later uploads ---

    public function test_a_retry_gives_the_same_bank_statement_findings(): void
    {
        Http::fakeSequence(self::ML_URL)
            ->pushStatus(503)
            ->push(['eligibility_outcome' => 'Approved', 'confidence_score' => 96, 'fraud_risk_score' => 'low', 'reasoning_notes' => 'Strong repayment profile.'], 200);
        $this->fakeOcr($this->statement('John Citizen', '350'));

        $application = $this->apply($this->applicant(), '80000');
        $this->assertSame('Pending', $application->status);

        $this->assertTrue(app(ApplicationAssessor::class)->retry($application->fresh()));
        $application->refresh();

        $this->assertSame('Manual Review', $application->status);
        $this->assertSame('high', $application->aiAssessment->fraud_risk_score);
        $this->assertSame(
            "Document check:\n- " . self::NAME_FLAG
            . "\n- Stated bank assets do not match the bank statement (stated R80,000.00, statement R350.00)"
            . "\n\nStrong repayment profile.",
            $application->aiAssessment->reasoning_notes
        );
    }

    public function test_uploading_a_new_bank_statement_checks_its_text(): void
    {
        $this->fakeModel();
        $this->fakeOcr($this->statement('John Citizen', '350'));
        $user = $this->applicant();
        $application = $this->apply($user, '350');
        $this->assertStringContainsString(self::NAME_FLAG, $application->aiAssessment->reasoning_notes);

        // the applicant uploads their own statement: the latest one is checked
        $this->fakeOcr($this->statement('Thandi Mokoena', '350'));
        $this->actingAs($user)->post(route('applications.documents.store', $application), [
            'type' => 'bank_statement',
            'file' => UploadedFile::fake()->image('my-statement.jpg'),
        ])->assertRedirect();

        $application->refresh();
        $this->assertSame('Approved', $application->status);
        $this->assertSame('Strong repayment profile.', $application->aiAssessment->reasoning_notes);
    }
}
