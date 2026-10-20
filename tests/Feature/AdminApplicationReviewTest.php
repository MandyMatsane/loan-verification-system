<?php

namespace Tests\Feature;

use App\Models\AiAssessment;
use App\Models\Document;
use App\Models\LoanApplication;
use App\Models\OcrResult;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class AdminApplicationReviewTest extends TestCase
{
    use RefreshDatabase;

    private const OVERRIDE_LINE = 'Overridden by the document check. See the reasoning notes.';

    private const LOW_CONFIDENCE_LINE = 'Sent to manual review by low confidence and the document check. See the reasoning notes.';

    private function application(string $status, ?string $reasoningNotes, float $confidence = 96, ?int $loanTerm = null): LoanApplication
    {
        /** @var User $applicant */
        $applicant = User::factory()->create(['name' => 'Jane Applicant']);

        $application = LoanApplication::create([
            'user_id' => $applicant->id,
            'amount_requested' => 50000,
            'loan_term' => $loanTerm,
            'status' => $status,
        ]);

        AiAssessment::create([
            'application_id' => $application->id,
            'eligibility_outcome' => 'Approved',
            'confidence_score' => $confidence,
            'fraud_risk_score' => 'high',
            'reasoning_notes' => $reasoningNotes,
        ]);

        return $application;
    }

    private function review(LoanApplication $application): TestResponse
    {
        /** @var User $admin */
        $admin = User::factory()->create(['role' => 'admin']);

        return $this->actingAs($admin)->get(route('admin.applications.show', $application));
    }

    public function test_the_assessment_card_shows_the_model_outcome_as_a_label_and_value(): void
    {
        $application = $this->application('Approved', 'Strong repayment profile.');

        $this->review($application)
            ->assertOk()
            ->assertSeeInOrder(['Model outcome', 'Approved', 'Confidence'])
            ->assertDontSee(self::OVERRIDE_LINE)
            ->assertSee('<p class="mt-0.5 text-sm font-bold text-ink">Approved</p>', false)
            ->assertDontSee('text-2xl font-extrabold text-green-800', false);
    }

    public function test_the_override_line_shows_when_the_document_check_sent_it_to_manual_review(): void
    {
        $application = $this->application('Manual Review', "Document check:\n- ID number fails the Luhn checksum\n\nStrong repayment profile.");

        $this->review($application)
            ->assertOk()
            ->assertSeeInOrder(['Model outcome', 'Approved', self::OVERRIDE_LINE, 'Confidence'])
            ->assertDontSee(self::LOW_CONFIDENCE_LINE);
    }

    public function test_the_override_line_shows_at_exactly_70_percent_confidence(): void
    {
        $application = $this->application('Manual Review', "Document check:\n- ID document could not be read", 70);

        $this->review($application)
            ->assertOk()
            ->assertSee(self::OVERRIDE_LINE)
            ->assertDontSee(self::LOW_CONFIDENCE_LINE);
    }

    public function test_low_confidence_with_a_document_check_shows_the_low_confidence_line(): void
    {
        $application = $this->application('Manual Review', "Document check:\n- ID number fails the Luhn checksum\n\nProfile is borderline.", 55);

        $this->review($application)
            ->assertOk()
            ->assertSeeInOrder(['Model outcome', 'Approved', self::LOW_CONFIDENCE_LINE, 'Confidence'])
            ->assertDontSee(self::OVERRIDE_LINE);
    }

    public function test_the_override_line_is_hidden_for_manual_review_from_low_confidence(): void
    {
        $application = $this->application('Manual Review', 'Profile is borderline for approval.', 55);

        $this->review($application)->assertOk()->assertDontSee(self::OVERRIDE_LINE)->assertDontSee(self::LOW_CONFIDENCE_LINE);
    }

    public function test_the_override_line_is_hidden_when_the_status_is_not_manual_review(): void
    {
        $application = $this->application('Approved', "Document check:\n- ID document could not be read");

        $this->review($application)->assertOk()->assertDontSee(self::OVERRIDE_LINE)->assertDontSee(self::LOW_CONFIDENCE_LINE);
    }

    public function test_the_loan_term_says_month_for_one_and_months_otherwise(): void
    {
        $this->review($this->application('Approved', null, loanTerm: 1))
            ->assertOk()
            ->assertSeeInOrder(['Loan term', '1 month'])
            ->assertDontSee('1 months');

        $this->review($this->application('Approved', null, loanTerm: 12))
            ->assertOk()
            ->assertSeeInOrder(['Loan term', '12 months']);
    }

    public function test_the_applicant_application_page_says_month_for_one_and_months_otherwise(): void
    {
        $oneMonth = $this->application('Approved', null, loanTerm: 1);
        $twoMonths = $this->application('Approved', null, loanTerm: 2);

        $this->actingAs($oneMonth->user)->get(route('applications.show', $oneMonth))
            ->assertOk()
            ->assertSeeInOrder(['Loan term', '1 month'])
            ->assertDontSee('1 months');

        $this->actingAs($twoMonths->user)->get(route('applications.show', $twoMonths))
            ->assertOk()
            ->assertSeeInOrder(['Loan term', '2 months']);
    }

    public function test_the_loan_term_label(): void
    {
        $this->assertSame('1 month', (new LoanApplication(['loan_term' => 1]))->loanTermLabel());
        $this->assertSame('6 months', (new LoanApplication(['loan_term' => 6]))->loanTermLabel());
        $this->assertSame('-', (new LoanApplication())->loanTermLabel());
    }

    public function test_documents_without_ocr_text_say_no_readable_text_was_found(): void
    {
        $application = $this->application('Approved', null);

        $documents = [];

        foreach (['id_document', 'payslip', 'bank_statement'] as $type) {
            $documents[$type] = Document::create([
                'application_id' => $application->id,
                'type' => $type,
                'file_path' => 'loan-applications/' . $application->id . '/' . $type . '.jpg',
                'uploaded_at' => now(),
            ]);
        }

        // one with text, one with empty text, and one OCR never stored a result for
        OcrResult::create(['document_id' => $documents['id_document']->id, 'extracted_text' => 'REPUBLIC OF SOUTH AFRICA']);
        OcrResult::create(['document_id' => $documents['payslip']->id, 'extracted_text' => "  \n "]);

        $response = $this->review($application)->assertOk();

        $response
            ->assertSee('REPUBLIC OF SOUTH AFRICA')
            ->assertDontSee('Not processed yet')
            ->assertSeeInOrder(['ID document', 'REPUBLIC OF SOUTH AFRICA', 'Payslip', 'No readable text was found in this file.', 'Bank statement', 'No readable text was found in this file.']);

        $this->assertSame(2, substr_count($response->getContent(), 'No readable text was found in this file.'));
    }
}
