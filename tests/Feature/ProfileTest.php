<?php

namespace Tests\Feature;

use App\Models\OcrResult;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    private function createUser(array $attributes = []): User
    {
        /** @var User $user */
        $user = User::factory()->create($attributes);

        return $user;
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
        $user = $this->createUser();

        Storage::fake('public');

        $response = $this
            ->actingAs($user)
            ->post('/applications', [
                'amount_requested' => '2500.00',
                'id_document' => UploadedFile::fake()->image('id-document.jpg', 800, 600),
                'payslip' => UploadedFile::fake()->create('payslip.pdf', 200, 'application/pdf'),
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
        $user = $this->createUser();

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
                'amount_requested' => '4500.00',
                'id_document' => UploadedFile::fake()->image('id-document.jpg', 800, 600),
                'payslip' => UploadedFile::fake()->create('payslip.pdf', 200, 'application/pdf'),
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
}
