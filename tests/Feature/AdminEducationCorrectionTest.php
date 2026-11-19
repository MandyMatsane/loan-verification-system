<?php

namespace Tests\Feature;

use App\Models\AiAssessment;
use App\Models\EducationCorrection;
use App\Models\LoanApplication;
use App\Models\User;
use App\Services\FraudCheckService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AdminEducationCorrectionTest extends TestCase
{
    use RefreshDatabase;

    private const ML_URL = 'http://localhost:8000/api/ml/predict';

    private function admin(): User
    {
        /** @var User $admin */
        $admin = User::factory()->create(['role' => 'admin', 'name' => 'Ada Admin']);

        return $admin;
    }

    /**
     * An application assessed as Graduate: Approved by the model at 96%.
     */
    private function application(string $education = 'Graduate'): LoanApplication
    {
        /** @var User $applicant */
        $applicant = User::factory()->create(['name' => 'Thandi Mokoena', 'monthly_income' => 20000, 'education' => $education]);

        $application = LoanApplication::create([
            'user_id' => $applicant->id,
            'amount_requested' => 5000,
            'loan_term' => 12,
            'education' => $education,
            'cibil_score_band' => 'Good',
            'status' => 'Approved',
            'stated_monthly_income' => 20000,
        ]);

        AiAssessment::create([
            'application_id' => $application->id,
            'eligibility_outcome' => 'Approved',
            'confidence_score' => 96,
            'fraud_risk_score' => 'low',
            'reasoning_notes' => 'Strong repayment profile.',
        ]);

        return $application;
    }

    /**
     * The model answers by education: Graduate is approved, Not Graduate rejected. The document check finds nothing.
     */
    private function fakeServices(): void
    {
        Http::fake([self::ML_URL => fn (Request $request) => Http::response($request['education'] === 'Graduate'
            ? ['eligibility_outcome' => 'Approved', 'confidence_score' => 96, 'fraud_risk_score' => 'low', 'reasoning_notes' => 'Strong repayment profile.']
            : ['eligibility_outcome' => 'Rejected', 'confidence_score' => 88, 'fraud_risk_score' => 'low', 'reasoning_notes' => 'Weak repayment profile.'], 200)]);

        $this->partialMock(FraudCheckService::class, fn ($mock) => $mock->shouldReceive('check')
            ->andReturn(['flags' => [], 'notes' => [], 'name_similarity' => null]));
    }

    private function correct(User $user, LoanApplication $application, array $fields)
    {
        return $this->actingAs($user)
            ->from(route('admin.applications.show', $application))
            ->post(route('admin.applications.education', $application), $fields);
    }

    public function test_an_admin_corrects_the_education_and_the_decision_is_recalculated(): void
    {
        $this->fakeServices();
        $application = $this->application();

        $this->correct($this->admin(), $application, ['education' => 'Not Graduate', 'reason' => 'Applicant confirmed they have not graduated.'])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.applications.show', $application))
            ->assertSessionHas('success', 'Education corrected from Graduate to Not Graduate. The application was assessed again: Rejected.');

        $application->refresh();
        $this->assertSame('Not Graduate', $application->education);
        $this->assertSame('Rejected', $application->status);
        $this->assertSame('Rejected', $application->aiAssessment->eligibility_outcome);
        $this->assertSame('Weak repayment profile.', $application->aiAssessment->reasoning_notes);
        $this->assertSame(1, AiAssessment::where('application_id', $application->id)->count());

        // the model was asked again, with the corrected value
        Http::assertSent(fn (Request $request) => $request->url() === self::ML_URL && $request['education'] === 'Not Graduate');
    }

    public function test_the_audit_row_keeps_the_values_the_reason_the_admin_and_the_previous_decision(): void
    {
        $this->fakeServices();
        $application = $this->application();
        $admin = $this->admin();

        $this->correct($admin, $application, ['education' => 'Not Graduate', 'reason' => 'Applicant confirmed they have not graduated.']);

        $correction = EducationCorrection::sole();
        $this->assertSame($application->id, $correction->application_id);
        $this->assertSame($admin->id, $correction->admin_id);
        $this->assertSame('Graduate', $correction->old_education);
        $this->assertSame('Not Graduate', $correction->new_education);
        $this->assertSame('Applicant confirmed they have not graduated.', $correction->reason);
        $this->assertSame('Approved', $correction->previous_status);
        $this->assertSame('Approved', $correction->previous_outcome);
        $this->assertEquals(96, $correction->previous_confidence_score);
        $this->assertSame('low', $correction->previous_fraud_risk);
        $this->assertSame('Strong repayment profile.', $correction->previous_reasoning_notes);
        $this->assertSame('Rejected', $correction->new_status);
        $this->assertNotNull($correction->created_at);
    }

    public function test_the_review_page_shows_the_form_and_the_history(): void
    {
        $application = $this->application();
        $admin = $this->admin();

        $this->actingAs($admin)->get(route('admin.applications.show', $application))
            ->assertOk()
            ->assertSee('Correct education')
            ->assertSee('action="' . route('admin.applications.education', $application) . '"', false)
            ->assertSee('<option value="Not Graduate" selected', false)
            ->assertSee('name="reason"', false)
            ->assertSee('No corrections yet.');

        EducationCorrection::create([
            'application_id' => $application->id,
            'admin_id' => $admin->id,
            'old_education' => 'Graduate',
            'new_education' => 'Not Graduate',
            'reason' => 'Applicant confirmed they have not graduated.',
            'previous_status' => 'Approved',
            'previous_outcome' => 'Approved',
            'previous_confidence_score' => 96,
            'previous_fraud_risk' => 'low',
            'previous_reasoning_notes' => 'Strong repayment profile.',
            'new_status' => 'Rejected',
        ]);

        $this->actingAs($admin)->get(route('admin.applications.show', $application))
            ->assertOk()
            ->assertDontSee('No corrections yet.')
            ->assertSeeInOrder(['Correction history', 'Graduate &rarr; Not Graduate', 'by Ada Admin', 'Applicant confirmed they have not graduated.', 'Before:', 'Approved (model Approved, 96% confidence, low fraud risk)', 'After:', 'Rejected'], false);
    }

    public function test_a_non_admin_is_blocked(): void
    {
        $this->fakeServices();
        $application = $this->application();

        // the applicant themselves, and any other applicant
        foreach ([$application->user, User::factory()->create()] as $user) {
            $this->correct($user, $application, ['education' => 'Not Graduate', 'reason' => 'Trying anyway.'])->assertForbidden();
        }

        // and a guest is sent to log in
        auth()->logout();
        $this->post(route('admin.applications.education', $application), ['education' => 'Not Graduate', 'reason' => 'x'])
            ->assertRedirect(route('login'));

        $this->assertSame('Graduate', $application->fresh()->education);
        $this->assertSame(0, EducationCorrection::count());
        Http::assertNothingSent();
    }

    public function test_a_missing_reason_is_rejected(): void
    {
        $this->fakeServices();
        $application = $this->application();

        foreach (['', '   ', null] as $reason) {
            $this->correct($this->admin(), $application, ['education' => 'Not Graduate', 'reason' => $reason])
                ->assertRedirect(route('admin.applications.show', $application))
                ->assertSessionHasErrorsIn('educationCorrection', ['reason' => 'Give a reason for the correction.']);
        }

        $this->assertSame('Graduate', $application->fresh()->education);
        $this->assertSame(0, EducationCorrection::count());
        Http::assertNothingSent();
    }

    public function test_only_graduate_or_not_graduate_is_accepted_and_it_must_change_the_value(): void
    {
        $this->fakeServices();
        $application = $this->application();

        foreach (['Doctorate', '', null] as $education) {
            $this->correct($this->admin(), $application, ['education' => $education, 'reason' => 'Wrong value.'])
                ->assertSessionHasErrorsIn('educationCorrection', 'education');
        }

        $this->correct($this->admin(), $application, ['education' => 'Graduate', 'reason' => 'Same value.'])
            ->assertSessionHasErrorsIn('educationCorrection', ['education' => 'Choose a different value: the application already says Graduate.']);

        $this->assertSame(0, EducationCorrection::count());
        Http::assertNothingSent();
    }

    public function test_the_validation_error_shows_on_the_review_page(): void
    {
        $application = $this->application();

        $this->actingAs($this->admin())->followingRedirects()
            ->from(route('admin.applications.show', $application))
            ->post(route('admin.applications.education', $application), ['education' => 'Not Graduate', 'reason' => ''])
            ->assertOk()
            ->assertSee('Give a reason for the correction.')
            ->assertSee('aria-invalid="true" aria-describedby="correct_education_reason_error"', false);
    }

    public function test_when_the_ml_service_is_unavailable_the_application_is_pending_and_retryable(): void
    {
        Http::fake([self::ML_URL => Http::response('Service unavailable', 503)]);
        $this->partialMock(FraudCheckService::class, fn ($mock) => $mock->shouldReceive('check')
            ->andReturn(['flags' => [], 'notes' => [], 'name_similarity' => null]));
        $application = $this->application();

        $this->correct($this->admin(), $application, ['education' => 'Not Graduate', 'reason' => 'Applicant confirmed they have not graduated.'])
            ->assertSessionHas('warning', 'Education corrected from Graduate to Not Graduate. The prediction service did not answer, so the application is Pending until the assessment is retried.');

        $application->refresh();
        $this->assertSame('Not Graduate', $application->education);
        $this->assertSame('Pending', $application->status);
        $this->assertNull($application->aiAssessment);
        // the usual retry button and automatic retries pick it up
        $this->assertTrue($application->isRetryable());

        $correction = EducationCorrection::sole();
        $this->assertSame('Approved', $correction->previous_status);
        $this->assertSame('Approved', $correction->previous_outcome);
        $this->assertSame('Pending', $correction->new_status);
    }

    public function test_a_correction_is_refused_while_the_application_is_being_assessed(): void
    {
        $this->fakeServices();
        $application = $this->application();
        $lock = app(\App\Services\ApplicationAssessor::class)->lock($application, 60);
        $lock->get();

        $this->correct($this->admin(), $application, ['education' => 'Not Graduate', 'reason' => 'Applicant confirmed they have not graduated.'])
            ->assertSessionHas('warning', 'This application is already being assessed. Refresh in a minute and try again.');

        $lock->release();
        $this->assertSame('Graduate', $application->fresh()->education);
        $this->assertSame(0, EducationCorrection::count());
    }

    public function test_corrections_are_kept_one_row_each_and_shown_newest_first(): void
    {
        $this->fakeServices();
        $application = $this->application();
        $admin = $this->admin();

        $this->correct($admin, $application, ['education' => 'Not Graduate', 'reason' => 'First correction.']);
        $this->correct($admin, $application, ['education' => 'Graduate', 'reason' => 'Second correction: certificate seen.']);

        $this->assertSame(2, EducationCorrection::count());
        $application->refresh();
        $this->assertSame('Graduate', $application->education);
        $this->assertSame('Approved', $application->status);

        $second = EducationCorrection::latest('id')->first();
        $this->assertSame('Not Graduate', $second->old_education);
        $this->assertSame('Rejected', $second->previous_status);
        $this->assertSame('Approved', $second->new_status);

        // the page with the real document check service
        $this->app->forgetInstance(FraudCheckService::class);

        $this->actingAs($admin)->get(route('admin.applications.show', $application))
            ->assertOk()
            ->assertSeeInOrder(['Second correction: certificate seen.', 'First correction.']);
    }
}
