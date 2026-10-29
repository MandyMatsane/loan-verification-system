<?php

namespace Tests\Feature;

use App\Http\Requests\ProfileUpdateRequest;
use App\Models\Document;
use App\Models\LoanApplication;
use App\Models\OcrResult;
use App\Models\User;
use App\Services\OcrService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Mockery;
use Tests\TestCase;

class EducationProfileTest extends TestCase
{
    use RefreshDatabase;

    private const ML_URL = 'http://localhost:8000/api/ml/predict';

    private function applicant(?string $education): User
    {
        /** @var User $user */
        $user = User::factory()->create([
            'name' => 'Thandi Mokoena',
            'id_number' => '8001015009087',
            'date_of_birth' => '1980-01-01',
            'monthly_income' => 9500,
            'education' => $education,
        ]);

        return $user;
    }

    /**
     * @param  array<string, mixed>  $fields  what the form sends besides the loan details
     */
    private function apply(User $user, array $fields)
    {
        Http::fake([self::ML_URL => Http::response([
            'eligibility_outcome' => 'Approved',
            'confidence_score' => 96,
            'fraud_risk_score' => 'low',
            'reasoning_notes' => 'Strong repayment profile.',
        ], 200)]);

        $mock = Mockery::mock(OcrService::class);
        $mock->shouldReceive('process')->andReturnUsing(fn (Document $document) => OcrResult::create([
            'document_id' => $document->id,
            'extracted_text' => '',
        ]));
        $this->app->instance(OcrService::class, $mock);

        return $this->actingAs($user)->post('/applications', array_merge([
            'amount_requested' => '2500',
            'no_of_dependents' => 0,
            'loan_term' => 12,
            'cibil_score_band' => 'Good',
            'residential_assets_value' => 0,
            'commercial_assets_value' => 0,
            'luxury_assets_value' => 0,
            'bank_asset_value' => 0,
            'id_document' => UploadedFile::fake()->image('id.jpg'),
            'payslip' => UploadedFile::fake()->image('payslip.jpg'),
            'bank_statement' => UploadedFile::fake()->image('bank.jpg'),
        ], $fields));
    }

    private function latestApplication(User $user): LoanApplication
    {
        return LoanApplication::where('user_id', $user->id)->latest('id')->firstOrFail();
    }

    /**
     * The fields the profile form sends, with education as given (null: not sent at all).
     */
    private function profile(User $user, ?string $education): array
    {
        $fields = ['name' => $user->name, 'email' => $user->email, 'id_number' => $user->id_number, 'date_of_birth' => '1980-01-01', 'monthly_income' => 9500];

        return $education === null ? $fields : $fields + ['education' => $education];
    }

    private function sentEducation(): string
    {
        $sent = null;
        Http::assertSent(function (Request $request) use (&$sent) {
            $sent = $request['education'] ?? null;

            return $request->url() === self::ML_URL;
        });

        return $sent;
    }

    // --- application form ---

    public function test_existing_users_without_education_can_apply_and_the_first_application_sets_the_profile(): void
    {
        $user = $this->applicant(null);

        $this->actingAs($user)->get(route('applications.create'))
            ->assertOk()
            ->assertSee('Select education')
            ->assertSee('Saved to your profile when you apply.');

        // a choice is still required
        $this->apply($user, [])->assertSessionHasErrors('education');
        $this->assertSame(0, LoanApplication::count());

        $this->apply($user, ['education' => 'Not Graduate'])->assertSessionHasNoErrors()->assertRedirect();

        $this->assertSame('Not Graduate', $this->latestApplication($user)->education);
        $this->assertSame('Not Graduate', $user->fresh()->education);
        $this->assertSame('Not Graduate', $this->sentEducation());
    }

    public function test_a_graduate_sees_fixed_text_and_a_posted_not_graduate_is_ignored(): void
    {
        $user = $this->applicant('Graduate');

        $this->actingAs($user)->get(route('applications.create'))
            ->assertOk()
            ->assertSee('<input type="hidden" name="education" value="Graduate">', false)
            ->assertDontSee('<select id="education"', false)
            ->assertDontSee('<option value="Not Graduate"', false)
            ->assertDontSee('From your profile');

        $this->apply($user, ['education' => 'Not Graduate'])->assertSessionHasNoErrors()->assertRedirect();

        $this->assertSame('Graduate', $this->latestApplication($user)->education);
        $this->assertSame('Graduate', $user->fresh()->education);
        $this->assertSame('Graduate', $this->sentEducation());
    }

    public function test_a_graduate_who_sends_no_education_still_applies_as_graduate(): void
    {
        $user = $this->applicant('Graduate');

        $this->apply($user, [])->assertSessionHasNoErrors()->assertRedirect();

        $this->assertSame('Graduate', $this->latestApplication($user)->education);
    }

    public function test_not_graduate_can_choose_graduate_on_the_form_and_the_profile_follows(): void
    {
        $user = $this->applicant('Not Graduate');

        $this->actingAs($user)->get(route('applications.create'))
            ->assertOk()
            ->assertDontSee('Select education')
            ->assertSee('<option value="Graduate"', false)
            ->assertSee('<option value="Not Graduate" selected', false);

        $this->apply($user, ['education' => 'Not Graduate'])->assertSessionHasNoErrors();
        $this->assertSame('Not Graduate', $user->fresh()->education);

        $this->apply($user, ['education' => 'Graduate'])->assertSessionHasNoErrors();
        $this->assertSame('Graduate', $this->latestApplication($user)->education);
        $this->assertSame('Graduate', $user->fresh()->education);
    }

    public function test_each_application_keeps_its_own_education(): void
    {
        $user = $this->applicant('Not Graduate');
        $this->apply($user, ['education' => 'Not Graduate']);
        $first = $this->latestApplication($user);

        $this->apply($user, ['education' => 'Graduate']);

        $this->assertSame('Not Graduate', $first->fresh()->education);
        $this->assertSame('Graduate', $this->latestApplication($user)->education);
    }

    public function test_an_unknown_value_is_refused(): void
    {
        $user = $this->applicant(null);

        $this->apply($user, ['education' => 'Doctorate'])->assertSessionHasErrors('education');
        $this->assertNull($user->fresh()->education);
    }

    // --- profile page ---

    public function test_the_profile_page_shows_education_with_the_hint(): void
    {
        $hint = 'You can change from Not Graduate to Graduate when you graduate, but not back.';

        $this->actingAs($this->applicant(null))->get('/profile')
            ->assertOk()
            ->assertSee($hint)
            ->assertSee('Select education')
            ->assertSee('<option value="Not Graduate"', false);

        $graduate = $this->applicant('Graduate');
        $graduate->forceFill(['email' => 'graduate@example.com'])->save();

        $this->actingAs($graduate)->get('/profile')
            ->assertOk()
            ->assertSee($hint)
            ->assertDontSee('<select id="education"', false)
            ->assertDontSee('<option value="Not Graduate"', false);
    }

    public function test_the_profile_can_set_education_and_change_not_graduate_to_graduate(): void
    {
        $user = $this->applicant(null);

        $this->actingAs($user)->patch('/profile', $this->profile($user, 'Not Graduate'))->assertSessionHasNoErrors();
        $this->assertSame('Not Graduate', $user->fresh()->education);

        $this->actingAs($user)->patch('/profile', $this->profile($user, 'Graduate'))->assertSessionHasNoErrors();
        $this->assertSame('Graduate', $user->fresh()->education);
    }

    public function test_the_profile_refuses_graduate_back_to_not_graduate(): void
    {
        $user = $this->applicant('Graduate');

        $this->actingAs($user)->from('/profile')->patch('/profile', $this->profile($user, 'Not Graduate'))
            ->assertRedirect('/profile')
            ->assertSessionHasErrors(['education' => ProfileUpdateRequest::EDUCATION_BACK_MESSAGE]);

        $this->assertSame('Graduate', $user->fresh()->education);

        $this->actingAs($user)->followingRedirects()->from('/profile')->patch('/profile', $this->profile($user, 'Not Graduate'))
            ->assertSee(ProfileUpdateRequest::EDUCATION_BACK_MESSAGE);
    }

    public function test_saving_the_profile_without_education_keeps_what_is_saved(): void
    {
        foreach (['Graduate', 'Not Graduate'] as $education) {
            $user = $this->applicant($education);
            $user->forceFill(['email' => str_replace(' ', '-', strtolower($education)) . '@example.com'])->save();

            // a graduate's page sends nothing; an emptied value must not clear it either
            $this->actingAs($user)->patch('/profile', $this->profile($user, null))->assertSessionHasNoErrors();
            $this->actingAs($user)->patch('/profile', $this->profile($user, ''))->assertSessionHasNoErrors();

            $this->assertSame($education, $user->fresh()->education);
        }
    }
}
