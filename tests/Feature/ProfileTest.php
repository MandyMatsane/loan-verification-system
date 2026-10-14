<?php

namespace Tests\Feature;

use App\Models\OcrResult;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    // what an applicant's profile needs before they can apply
    private const COMPLETE_PROFILE = ['id_number' => '8001015009087', 'date_of_birth' => '1980-01-01'];

    private const PROFILE_MESSAGE = 'Add your ID number and date of birth before you apply.';

    private function createUser(array $attributes = []): User
    {
        /** @var User $user */
        $user = User::factory()->create($attributes);

        return $user;
    }

    /**
     * The non-file fields the application form requires.
     */
    private function loanDetails(array $overrides = []): array
    {
        return array_merge([
            'amount_requested' => '2500.00',
            'no_of_dependents' => 2,
            'education' => 'Graduate',
            'loan_term' => 12,
            'cibil_score_band' => 'Good',
            'residential_assets_value' => 0,
            'commercial_assets_value' => 0,
            'luxury_assets_value' => 0,
            'bank_asset_value' => 0,
        ], $overrides);
    }

    public function test_profile_page_is_displayed(): void
    {
        $user = $this->createUser();

        $response = $this
            ->actingAs($user)
            ->get('/profile');

        $response->assertOk();
    }

    public function test_profile_page_includes_applicant_details_form(): void
    {
        $user = $this->createUser();

        $response = $this
            ->actingAs($user)
            ->get('/profile');

        $response
            ->assertOk()
            ->assertSee('Applicant profile')
            ->assertSee('Phone number')
            ->assertSee('ID number');
    }

    public function test_new_users_are_redirected_to_profile_setup_after_registration(): void
    {
        $response = $this->post('/register', [
            'name' => 'Applicant User',
            'email' => 'applicant@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

        $response->assertRedirect('/profile');
    }

    public function test_applicant_dashboard_shows_application_cta(): void
    {
        $user = $this->createUser(['role' => 'applicant']);

        $response = $this
            ->actingAs($user)
            ->get('/dashboard');

        $response
            ->assertOk()
            ->assertSee('Apply for a loan')
            ->assertSee('Start your application');
    }

    public function test_profile_information_can_be_updated(): void
    {
        $user = $this->createUser();

        $response = $this
            ->actingAs($user)
            ->patch('/profile', [
                'name' => 'Test User',
                'email' => 'test@example.com',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $user->refresh();

        $this->assertSame('Test User', $user->name);
        $this->assertSame('test@example.com', $user->email);
        $this->assertNull($user->email_verified_at);
    }

    public function test_email_verification_status_is_unchanged_when_the_email_address_is_unchanged(): void
    {
        $user = $this->createUser();

        $response = $this
            ->actingAs($user)
            ->patch('/profile', [
                'name' => 'Test User',
                'email' => $user->email,
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $this->assertNotNull($user->refresh()->email_verified_at);
    }

    public function test_user_can_delete_their_account(): void
    {
        $user = $this->createUser();

        $response = $this
            ->actingAs($user)
            ->delete('/profile', [
                'password' => 'password',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/');

        $this->assertGuest();
        $this->assertNull($user->fresh());
    }

    public function test_correct_password_must_be_provided_to_delete_account(): void
    {
        $user = $this->createUser();

        $response = $this
            ->actingAs($user)
            ->from('/profile')
            ->delete('/profile', [
                'password' => 'wrong-password',
            ]);

        $response
            ->assertSessionHasErrorsIn('userDeletion', 'password')
            ->assertRedirect('/profile');

        $this->assertNotNull($user->fresh());
    }

    public function test_user_can_submit_a_loan_application_with_required_documents(): void
    {
        $user = $this->createUser(self::COMPLETE_PROFILE);

        Storage::fake('public');

        $response = $this
            ->actingAs($user)
            ->post('/applications', [
                ...$this->loanDetails(['amount_requested' => '2500.00']),
                'id_document' => UploadedFile::fake()->image('id-document.jpg', 800, 600),
                'payslip' => UploadedFile::fake()->image('payslip.jpg', 800, 600),
                'bank_statement' => UploadedFile::fake()->create('bank-statement.png', 200, 'image/png'),
            ]);

        $response->assertRedirect(
            route('applications.confirmation', ['application' => 1])
        );

        $this->assertDatabaseHas('loan_applications', [
            'user_id' => $user->id,
            'amount_requested' => '2500.00',
            'status' => 'Pending',
        ]);

        $this->assertDatabaseHas('documents', [
            'type' => 'id_document',
        ]);
        $this->assertDatabaseHas('documents', [
            'type' => 'payslip',
        ]);
        $this->assertDatabaseHas('documents', [
            'type' => 'bank_statement',
        ]);

        $this->assertDatabaseCount('documents', 3);
        $this->assertTrue(Storage::disk('public')->exists('loan-applications/' . $user->id . '/'));
    }

    public function test_uploaded_documents_create_ocr_results(): void
    {
        $user = $this->createUser(self::COMPLETE_PROFILE);

        Storage::fake('public');

        $mock = Mockery::mock(\App\Services\OcrService::class);
        $mock->shouldReceive('process')->times(3)->withArgs(function ($document) {
            $this->assertNotNull($document);
            $this->assertContains($document->type, ['id_document', 'payslip', 'bank_statement']);

            return true;
        })->andReturnUsing(function ($document) {
            return OcrResult::create([
                'document_id' => $document->id,
                'extracted_text' => 'OCR sample text',
                'extracted_fields' => null,
                'confidence_score' => null,
            ]);
        });

        $this->app->instance(\App\Services\OcrService::class, $mock);

        $response = $this->actingAs($user)
            ->post('/applications', [
                ...$this->loanDetails(['amount_requested' => '4500.00']),
                'id_document' => UploadedFile::fake()->image('id-document.jpg', 800, 600),
                'payslip' => UploadedFile::fake()->image('payslip.jpg', 800, 600),
                'bank_statement' => UploadedFile::fake()->create('bank-statement.png', 200, 'image/png'),
            ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('documents', [
            'type' => 'id_document',
        ]);
        $this->assertDatabaseHas('ocr_results', [
            'extracted_text' => 'OCR sample text',
            'confidence_score' => null,
        ]);
    }

    public function test_applicant_without_id_number_and_date_of_birth_is_redirected_from_the_application_form(): void
    {
        foreach ([
            [],
            ['id_number' => '8001015009087'],
            ['date_of_birth' => '1980-01-01'],
            ['id_number' => '80010150090', 'date_of_birth' => '1980-01-01'],
        ] as $profile) {
            $this->actingAs($this->createUser($profile))
                ->get('/applications/create')
                ->assertRedirect('/profile')
                ->assertSessionHas('warning', self::PROFILE_MESSAGE);
        }
    }

    public function test_applicant_without_id_number_and_date_of_birth_cannot_submit_an_application(): void
    {
        Storage::fake('public');

        $this->actingAs($this->createUser())
            ->post('/applications', [
                ...$this->loanDetails(),
                'id_document' => UploadedFile::fake()->image('id-document.jpg', 800, 600),
                'payslip' => UploadedFile::fake()->image('payslip.jpg', 800, 600),
                'bank_statement' => UploadedFile::fake()->image('bank-statement.png', 800, 600),
            ])
            ->assertRedirect('/profile')
            ->assertSessionHas('warning', self::PROFILE_MESSAGE);

        $this->assertDatabaseCount('loan_applications', 0);
    }

    public function test_profile_page_shows_the_message_after_the_redirect(): void
    {
        $this->actingAs($this->createUser())
            ->followingRedirects()
            ->get('/applications/create')
            ->assertOk()
            ->assertSee(self::PROFILE_MESSAGE);
    }

    public function test_applicant_with_id_number_and_date_of_birth_can_open_the_application_form(): void
    {
        $this->actingAs($this->createUser(self::COMPLETE_PROFILE))
            ->get('/applications/create')
            ->assertOk();
    }

    public function test_admin_can_open_the_application_form_without_profile_details(): void
    {
        $this->actingAs($this->createUser(['role' => 'admin']))
            ->get('/applications/create')
            ->assertOk();
    }

    public function test_profile_id_number_must_be_exactly_13_digits_when_provided(): void
    {
        $user = $this->createUser();

        foreach (['80010150090', '80010150090871', '80010150090AB'] as $idNumber) {
            $this->actingAs($user)
                ->from('/profile')
                ->patch('/profile', ['name' => $user->name, 'email' => $user->email, 'id_number' => $idNumber])
                ->assertSessionHasErrors('id_number');
        }

        $this->actingAs($user)
            ->patch('/profile', ['name' => $user->name, 'email' => $user->email, 'id_number' => '8001015009087'])
            ->assertSessionHasNoErrors();

        $this->actingAs($user)
            ->patch('/profile', ['name' => $user->name, 'email' => $user->email, 'id_number' => ''])
            ->assertSessionHasNoErrors();

        $this->assertNull($user->refresh()->id_number);
    }

    public function test_profile_form_shows_the_saved_date_of_birth(): void
    {
        $this->actingAs($this->createUser(self::COMPLETE_PROFILE))
            ->get('/profile')
            ->assertOk()
            ->assertSee('name="date_of_birth" type="date"', false)
            ->assertSee('value="1980-01-01"', false);
    }

    public function test_profile_form_keeps_the_submitted_date_of_birth_after_a_validation_error(): void
    {
        $user = $this->createUser(self::COMPLETE_PROFILE);

        $this->actingAs($user)
            ->from('/profile')
            ->followingRedirects()
            ->patch('/profile', [
                'name' => $user->name,
                'email' => $user->email,
                'id_number' => 'not-an-id',
                'date_of_birth' => '1990-05-05',
            ])
            ->assertOk()
            ->assertSee('value="1990-05-05"', false);

        $this->assertSame('1980-01-01', $user->refresh()->date_of_birth->format('Y-m-d'));
    }

    /**
     * The fields the profile form sends, filled with the user's current values.
     */
    private function profileFormFor(User $user, array $overrides = []): array
    {
        return array_merge([
            'name' => $user->name,
            'email' => $user->email,
            'phone_number' => $user->phone_number,
            'id_number' => $user->id_number,
            'date_of_birth' => $user->date_of_birth?->format('Y-m-d'),
            'address' => $user->address,
            'employment_status' => $user->employment_status,
            'monthly_income' => $user->monthly_income,
        ], $overrides);
    }

    public function test_submitting_the_profile_form_unchanged_keeps_the_date_of_birth(): void
    {
        $user = $this->createUser(self::COMPLETE_PROFILE);

        $this->actingAs($user)
            ->patch('/profile', $this->profileFormFor($user))
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $this->assertSame('1980-01-01', $user->refresh()->date_of_birth->format('Y-m-d'));
    }

    public function test_editing_only_the_phone_number_keeps_the_date_of_birth(): void
    {
        $user = $this->createUser(self::COMPLETE_PROFILE);

        // as the form sends it: every field, only the phone number changed
        $this->actingAs($user)
            ->patch('/profile', $this->profileFormFor($user, ['phone_number' => '0821234567']))
            ->assertSessionHasNoErrors();

        $user->refresh();
        $this->assertSame('0821234567', $user->phone_number);
        $this->assertSame('1980-01-01', $user->date_of_birth->format('Y-m-d'));

        // a request without the date field at all leaves it alone too
        $this->actingAs($user)
            ->patch('/profile', ['name' => $user->name, 'email' => $user->email, 'phone_number' => '0831234567'])
            ->assertSessionHasNoErrors();

        $user->refresh();
        $this->assertSame('0831234567', $user->phone_number);
        $this->assertSame('1980-01-01', $user->date_of_birth->format('Y-m-d'));
    }

    public function test_submitting_an_application_logs_timings(): void
    {
        $user = $this->createUser(self::COMPLETE_PROFILE);

        Storage::fake('public');
        Log::spy();

        $mock = Mockery::mock(\App\Services\OcrService::class);
        $mock->shouldReceive('process')->times(3)->andReturnNull();
        $this->app->instance(\App\Services\OcrService::class, $mock);

        $this->actingAs($user)
            ->post('/applications', [
                ...$this->loanDetails(),
                'id_document' => UploadedFile::fake()->image('id-document.jpg', 800, 600),
                'payslip' => UploadedFile::fake()->image('payslip.jpg', 800, 600),
                'bank_statement' => UploadedFile::fake()->create('bank-statement.png', 200, 'image/png'),
            ])
            ->assertRedirect();

        foreach ([
            '/^TIMING ocr id_document \(\d+\.\d KB\) took \d+\.\d\ds$/',
            '/^TIMING ocr payslip \(\d+\.\d KB\) took \d+\.\d\ds$/',
            '/^TIMING ocr bank_statement \(200\.0 KB\) took \d+\.\d\ds$/',
            '/^TIMING ml prediction took \d+\.\d\ds$/',
            '/^TIMING submission total took \d+\.\d\ds$/',
        ] as $pattern) {
            Log::shouldHaveReceived('info')
                ->withArgs(fn ($message) => preg_match($pattern, $message) === 1)
                ->once();
        }
    }
}
