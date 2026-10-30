<?php

namespace Tests\Feature;

use App\Http\Requests\ProfileUpdateRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PhoneNumberValidationTest extends TestCase
{
    use RefreshDatabase;

    private function saveProfile(User $user, ?string $phone)
    {
        return $this->actingAs($user)->from('/profile')->patch('/profile', [
            'name' => $user->name,
            'email' => $user->email,
            'phone_number' => $phone,
        ]);
    }

    public function test_south_african_numbers_are_accepted(): void
    {
        $user = User::factory()->create();

        foreach (['0712345678', '071 234 5678', '+27712345678', '+27 71 234 5678', ' 082 123 4567 '] as $phone) {
            $this->saveProfile($user, $phone)->assertSessionHasNoErrors()->assertRedirect('/profile');
            $this->assertSame(trim($phone), $user->fresh()->phone_number);
        }
    }

    public function test_the_phone_number_stays_optional(): void
    {
        $user = User::factory()->create(['phone_number' => '0712345678']);

        $this->saveProfile($user, '')->assertSessionHasNoErrors();
        $this->assertNull($user->fresh()->phone_number);

        $this->saveProfile($user, null)->assertSessionHasNoErrors();
    }

    public function test_other_numbers_are_refused_with_a_clear_message(): void
    {
        $user = User::factory()->create(['phone_number' => '0712345678']);

        $refused = [
            'letters' => '07123abcde',
            'only letters' => 'phone',
            'too short' => '071234567',
            'too long' => '07123456789',
            'not starting with 0' => '7123456789',
            '+27 too short' => '+2771234567',
            '+27 too long' => '+277123456789',
            '+27 then 0' => '+270712345678',
            'other country code' => '+44712345678',
            'dashes' => '071-234-5678',
        ];

        foreach ($refused as $case => $phone) {
            $this->saveProfile($user, $phone)
                ->assertRedirect('/profile')
                ->assertSessionHasErrors(['phone_number' => ProfileUpdateRequest::PHONE_MESSAGE]);

            $this->assertSame('0712345678', $user->fresh()->phone_number, $case);
        }
    }

    public function test_the_message_is_shown_on_the_page(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->followingRedirects()->from('/profile')
            ->patch('/profile', ['name' => $user->name, 'email' => $user->email, 'phone_number' => '12345'])
            ->assertSee(ProfileUpdateRequest::PHONE_MESSAGE);
    }

    public function test_the_profile_form_checks_the_number_while_typing(): void
    {
        $this->actingAs(User::factory()->create())->get('/profile')
            ->assertOk()
            ->assertSee('x-model="phone"', false)
            ->assertSee('inputmode="tel"', false)
            ->assertSee('id="phone_number_error"', false)
            ->assertSee('/^(0\d{9}|\+27\d{9})$/', false)
            ->assertSee(ProfileUpdateRequest::PHONE_MESSAGE);
    }

    public function test_the_registration_form_has_no_phone_field(): void
    {
        $this->get('/register')->assertOk()->assertDontSee('phone_number', false);
    }
}
