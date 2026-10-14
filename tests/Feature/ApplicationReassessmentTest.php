<?php

namespace Tests\Feature;

use App\Models\AiAssessment;
use App\Models\Document;
use App\Models\LoanApplication;
use App\Models\OcrResult;
use App\Models\User;
use App\Services\ApplicationAssessor;
use App\Services\FraudCheckService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Mockery;
use Tests\TestCase;

class ApplicationReassessmentTest extends TestCase
{
    use RefreshDatabase;

    private const PREDICT_URL = 'http://localhost:8000/api/ml/predict';

    private const VALID_ID = '8001015009087';

    private const PENDING_MESSAGE = 'We are still assessing your application. Refresh this page in a minute.';

    /** @var array<int, int> timeout of each predict call */
    private array $predictTimeouts = [];

    private function applicant(string $name = 'Thandi Mokoena'): User
    {
        /** @var User $user */
        $user = User::factory()->create([
            'name' => $name,
            'id_number' => self::VALID_ID,
            'date_of_birth' => '1980-01-01',
        ]);

        return $user;
    }

    private function admin(): User
    {
        /** @var User $admin */
        $admin = User::factory()->create(['role' => 'admin']);

        return $admin;
    }

    /**
     * A stuck application whose ID document matches the owner, so the document check passes.
     */
    private function pendingApplication(User $owner, array $attributes = [], string $idName = 'THANDI MOKOENA'): LoanApplication
    {
        $application = LoanApplication::create(['user_id' => $owner->id, 'amount_requested' => 50000, 'status' => 'Pending']);
        $application->forceFill($attributes)->save();

        $document = Document::create([
            'application_id' => $application->id,
            'type' => 'id_document',
            'file_path' => 'loan-applications/' . $application->id . '/id-document.jpg',
            'uploaded_at' => now(),
        ]);

        OcrResult::create([
            'document_id' => $document->id,
            'extracted_text' => "Surname/Names: {$idName}\nIdentity Number: 800101 5009 087",
        ]);

        return $application;
    }

    /**
     * Fake the ML service: predict answers with each response in turn, feature importance is empty.
     */
    private function fakeMl(array ...$predictResponses): void
    {
        $responses = $predictResponses ?: [[503]];

        Http::fake(function (Request $request, array $options) use (&$responses) {
            if ($request->url() !== self::PREDICT_URL) {
                return Http::response([], 503);
            }

            $this->predictTimeouts[] = $options['timeout'];
            [$status, $body] = count($responses) > 1 ? array_shift($responses) : $responses[0];

            return Http::response($body ?? [], $status);
        });
    }

    private function approved(): array
    {
        return [200, [
            'eligibility_outcome' => 'Approved',
            'confidence_score' => 96,
            'fraud_risk_score' => 'low',
            'reasoning_notes' => 'Strong repayment profile.',
        ]];
    }

    private function predictCalls(): int
    {
        return count($this->predictTimeouts);
    }

    private function assessor(): ApplicationAssessor
    {
        return app(ApplicationAssessor::class);
    }

    public function test_a_failed_call_leaves_the_application_pending_and_counts_an_attempt(): void
    {
        $this->fakeMl([503]);
        $application = $this->pendingApplication($this->applicant());

        $this->assertFalse($this->assessor()->assess($application));

        $application->refresh();
        $this->assertSame('Pending', $application->status);
        $this->assertSame(1, $application->assessment_attempts);
        $this->assertNotNull($application->last_assessment_attempt_at);
        $this->assertNull($application->aiAssessment);
    }

    public function test_a_later_success_assesses_it_and_resets_the_attempts(): void
    {
        $this->fakeMl([503], [503], $this->approved());
        $application = $this->pendingApplication($this->applicant());

        $this->assertFalse($this->assessor()->assess($application));
        $this->assertFalse($this->assessor()->assess($application));
        $this->assertSame(2, $application->refresh()->assessment_attempts);

        $this->assertTrue($this->assessor()->assess($application));

        $application->refresh();
        $this->assertSame('Approved', $application->status);
        $this->assertSame(0, $application->assessment_attempts);
        $this->assertSame('low', $application->aiAssessment->fraud_risk_score);
    }

    public function test_the_document_check_override_still_applies_on_a_retry(): void
    {
        $this->fakeMl([503], $this->approved());
        $application = $this->pendingApplication($this->applicant('John Smith'));

        $this->assessor()->assess($application);
        $this->assertSame('Pending', $application->refresh()->status);

        $this->actingAs($this->admin())
            ->post(route('admin.applications.retry', $application))
            ->assertRedirect(route('admin.applications.show', $application))
            ->assertSessionHas('success', 'The application was assessed.');

        $application->refresh();
        $this->assertSame('Manual Review', $application->status);
        $this->assertSame('high', $application->aiAssessment->fraud_risk_score);
        $this->assertStringStartsWith("Document check:\n- Name on the ID document", $application->aiAssessment->reasoning_notes);
    }

    public function test_an_applicant_visit_only_retries_their_own_application(): void
    {
        $this->fakeMl($this->approved());

        $owner = $this->applicant();
        $other = $this->applicant();
        $othersApplication = $this->pendingApplication($other);
        $ownApplication = $this->pendingApplication($owner);

        $this->actingAs($owner)->get('/dashboard')->assertOk();

        $this->assertSame(1, $this->predictCalls());
        $this->assertSame([ApplicationAssessor::AUTOMATIC_TIMEOUT_SECONDS], $this->predictTimeouts);
        $this->assertSame('Approved', $ownApplication->refresh()->status);
        $this->assertSame('Pending', $othersApplication->refresh()->status);
        $this->assertSame(0, $othersApplication->assessment_attempts);
        $this->assertNull($othersApplication->last_assessment_attempt_at);

        // nothing of the owner's is left to retry, so the next visit calls nothing
        $this->actingAs($owner)->get('/dashboard')->assertOk();
        $this->assertSame(1, $this->predictCalls());
    }

    public function test_the_confirmation_page_retries_that_application_and_shows_the_result(): void
    {
        $this->fakeMl($this->approved());

        $owner = $this->applicant();
        $older = $this->pendingApplication($owner);
        $viewed = $this->pendingApplication($owner);

        $this->actingAs($owner)
            ->get(route('applications.confirmation', $viewed))
            ->assertOk()
            ->assertSee('Application approved')
            ->assertDontSee(self::PENDING_MESSAGE);

        // one application per request, and the one being viewed comes first
        $this->assertSame(1, $this->predictCalls());
        $this->assertSame('Approved', $viewed->refresh()->status);
        $this->assertSame('Pending', $older->refresh()->status);
    }

    public function test_the_confirmation_page_tells_the_applicant_to_refresh_while_pending(): void
    {
        $this->fakeMl([503]);
        $owner = $this->applicant();
        $application = $this->pendingApplication($owner);

        $this->actingAs($owner)
            ->get(route('applications.confirmation', $application))
            ->assertOk()
            ->assertSee(self::PENDING_MESSAGE);

        $this->assertSame(1, $application->refresh()->assessment_attempts);
    }

    public function test_an_admin_visit_retries_at_most_one_application_per_request(): void
    {
        $this->fakeMl($this->approved());

        $first = $this->pendingApplication($this->applicant());
        $second = $this->pendingApplication($this->applicant());

        $this->actingAs($this->admin())->get(route('admin.applications.index'))->assertOk();
        $this->assertSame(1, $this->predictCalls());

        $this->actingAs($this->admin())->get(route('admin.dashboard'))->assertOk();
        $this->assertSame(2, $this->predictCalls());

        $this->assertSame('Approved', $first->refresh()->status);
        $this->assertSame('Approved', $second->refresh()->status);
        $this->assertSame([5, 5], $this->predictTimeouts);
    }

    public function test_automatic_retries_wait_two_minutes_between_attempts(): void
    {
        $this->fakeMl([503]);
        $owner = $this->applicant();
        $application = $this->pendingApplication($owner, [
            'assessment_attempts' => 1,
            'last_assessment_attempt_at' => now()->subSeconds(90),
        ]);

        $this->actingAs($owner)->get('/dashboard')->assertOk();
        $this->assertSame(0, $this->predictCalls());

        $this->travel(31)->seconds();

        $this->actingAs($owner)->get('/dashboard')->assertOk();
        $this->assertSame(1, $this->predictCalls());
        $this->assertSame(2, $application->refresh()->assessment_attempts);

        // the attempt just made restarts the two minutes
        $this->actingAs($owner)->get('/dashboard')->assertOk();
        $this->assertSame(1, $this->predictCalls());
    }

    public function test_automatic_retries_stop_after_five_attempts_and_admins_see_needs_manual_retry(): void
    {
        $this->fakeMl($this->approved());
        $owner = $this->applicant();
        $application = $this->pendingApplication($owner, [
            'assessment_attempts' => 5,
            'last_assessment_attempt_at' => now()->subHour(),
        ]);

        $this->actingAs($owner)->get('/dashboard')->assertOk();
        $this->actingAs($this->admin())->get(route('admin.applications.index'))->assertOk()->assertSee('Needs manual retry');
        $this->actingAs($this->admin())->get(route('admin.applications.show', $application))->assertOk()->assertSee('Needs manual retry');

        $this->assertSame(0, $this->predictCalls());
        $this->assertSame(5, $application->refresh()->assessment_attempts);

        // an admin can still retry it by hand
        $this->actingAs($this->admin())->post(route('admin.applications.retry', $application));

        $this->assertSame(1, $this->predictCalls());
        $this->assertSame('Approved', $application->refresh()->status);
        $this->assertSame(0, $application->assessment_attempts);
    }

    public function test_four_failed_attempts_are_not_yet_flagged_for_manual_retry(): void
    {
        $this->fakeMl([503]);
        $application = $this->pendingApplication($this->applicant(), [
            'assessment_attempts' => 4,
            'last_assessment_attempt_at' => now(),
        ]);

        $this->actingAs($this->admin())->get(route('admin.applications.show', $application))
            ->assertOk()
            ->assertSee('Retry assessment')
            ->assertDontSee('Needs manual retry');
    }

    public function test_non_pending_applications_are_never_touched(): void
    {
        $this->fakeMl($this->approved());
        $owner = $this->applicant();

        $untouchable = [];

        foreach (['Approved', 'Manual Review', 'Rejected'] as $status) {
            $untouchable[] = $this->pendingApplication($owner, ['status' => $status]);
        }

        // pending, but already assessed
        $assessed = $this->pendingApplication($owner);
        AiAssessment::create([
            'application_id' => $assessed->id,
            'eligibility_outcome' => 'Pending',
            'confidence_score' => 50,
            'fraud_risk_score' => 'low',
        ]);
        $untouchable[] = $assessed;

        $admin = $this->admin();

        $this->actingAs($owner)->get('/dashboard')->assertOk();
        $this->actingAs($admin)->get('/dashboard')->assertOk();
        $this->actingAs($admin)->get(route('admin.applications.index'))->assertOk()->assertDontSee('Retry all pending');
        $this->actingAs($admin)->post(route('admin.applications.retry-pending'))
            ->assertSessionHas('success', 'No applications are waiting for an assessment.');

        foreach ($untouchable as $application) {
            $this->actingAs($admin)->get(route('admin.applications.show', $application))->assertOk()->assertDontSee('Retry assessment');
            $this->actingAs($admin)->post(route('admin.applications.retry', $application))
                ->assertSessionHas('warning', 'Only pending applications without an assessment can be retried.');
        }

        $this->assertSame(0, $this->predictCalls());

        foreach ($untouchable as $application) {
            $before = $application->only(['status', 'assessment_attempts', 'last_assessment_attempt_at', 'updated_at']);
            $this->assertEquals($before, $application->refresh()->only(['status', 'assessment_attempts', 'last_assessment_attempt_at', 'updated_at']));
        }
    }

    public function test_lowercase_pending_is_retryable(): void
    {
        $this->fakeMl($this->approved());
        $owner = $this->applicant();
        $application = $this->pendingApplication($owner, ['status' => 'pending']);

        $this->actingAs($owner)->get('/dashboard')->assertOk();

        $this->assertSame('Approved', $application->refresh()->status);
    }

    public function test_retry_all_pending_handles_at_most_five_and_reports_how_many_worked(): void
    {
        $this->fakeMl($this->approved());
        $owner = $this->applicant();

        $applications = collect(range(1, 7))->map(fn () => $this->pendingApplication($owner));

        $this->actingAs($this->admin())
            ->get(route('admin.applications.index'))
            ->assertSee('Retry all pending');

        // the page visit itself retried one, so six are left
        $this->assertSame(1, $this->predictCalls());

        $this->actingAs($this->admin())
            ->post(route('admin.applications.retry-pending'))
            ->assertRedirect(route('admin.applications.index'))
            ->assertSessionHas('success', '5 of 5 retried applications were assessed.');

        $this->assertSame(6, $this->predictCalls());
        $this->assertSame(1, $applications->filter(fn ($application) => $application->refresh()->status === 'Pending')->count());
    }

    public function test_retry_all_pending_stops_at_the_first_failure(): void
    {
        $this->fakeMl($this->approved(), [503]);
        $owner = $this->applicant();

        foreach (range(1, 3) as $i) {
            $this->pendingApplication($owner, ['last_assessment_attempt_at' => now()]);
        }

        $this->actingAs($this->admin())
            ->post(route('admin.applications.retry-pending'))
            ->assertSessionHas('warning', '1 of 2 retried applications were assessed. Stopped early because the prediction service did not answer. Try again in a minute.');

        $this->assertSame(2, $this->predictCalls());
    }

    public function test_the_retry_buttons_are_admin_only(): void
    {
        $this->fakeMl($this->approved());
        $owner = $this->applicant();
        $application = $this->pendingApplication($owner, ['last_assessment_attempt_at' => now()]);

        $this->actingAs($owner)->post(route('admin.applications.retry', $application))->assertForbidden();
        $this->actingAs($owner)->post(route('admin.applications.retry-pending'))->assertForbidden();
        $this->post(route('admin.applications.retry-pending'))->assertForbidden();

        $this->actingAs($owner)
            ->get(route('applications.confirmation', $application))
            ->assertOk()
            ->assertDontSee('Retry assessment')
            ->assertDontSee('Retry all pending');

        $this->assertSame(0, $this->predictCalls());
        $this->assertSame('Pending', $application->refresh()->status);
    }

    public function test_an_application_locked_by_another_request_is_skipped(): void
    {
        $this->fakeMl($this->approved());
        $owner = $this->applicant();
        $application = $this->pendingApplication($owner);

        $lock = Cache::lock('application-assessment:' . $application->id, 60);
        $lock->get();

        $this->actingAs($owner)->get('/dashboard')->assertOk();
        $this->assertSame(0, $this->predictCalls());

        $lock->release();

        $this->actingAs($owner)->get('/dashboard')->assertOk();
        $this->assertSame(1, $this->predictCalls());
    }

    public function test_a_failure_during_the_automatic_retry_never_breaks_the_page(): void
    {
        $this->fakeMl($this->approved());
        $owner = $this->applicant();
        $application = $this->pendingApplication($owner);

        $mock = Mockery::mock(FraudCheckService::class);
        $mock->shouldReceive('check')->andThrow(new \RuntimeException('boom'));
        $this->app->instance(FraudCheckService::class, $mock);

        $this->actingAs($owner)->get('/dashboard')->assertOk();
        $this->actingAs($owner)->get(route('applications.confirmation', $application))->assertOk();
        $this->actingAs($this->admin())->get(route('admin.applications.index'))->assertOk();

        // the lock was released despite the failure
        $this->assertTrue(Cache::lock('application-assessment:' . $application->id, 1)->get());
    }
}
