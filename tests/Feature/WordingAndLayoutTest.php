<?php

namespace Tests\Feature;

use App\Models\LoanApplication;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WordingAndLayoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_each_financial_profile_field_has_a_hint(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['id_number' => '8001015009087', 'date_of_birth' => '1980-01-01', 'monthly_income' => 9500]);

        $this->actingAs($user)->get(route('applications.create'))
            ->assertOk()
            ->assertSeeInOrder(['Credit score band', 'The range your latest credit score falls in.'])
            ->assertSeeInOrder(['Residential assets (R)', 'What the homes you own are worth today.'])
            ->assertSeeInOrder(['Commercial assets (R)', 'What the business property you own is worth.'])
            ->assertSeeInOrder(['Luxury assets (R)', 'What your cars, jewellery and other valuables are worth.'])
            ->assertSeeInOrder(['Bank assets (R)', 'The balance shown on your bank statement.'])
            ->assertSee('aria-describedby="bank_asset_value_hint"', false)
            ->assertSee('id="bank_asset_value_hint"', false);
    }

    public function test_the_admin_list_has_an_application_number_column(): void
    {
        /** @var User $applicant */
        $applicant = User::factory()->create(['name' => 'Thandi Mokoena']);
        $application = LoanApplication::create(['user_id' => $applicant->id, 'amount_requested' => 2500, 'loan_term' => 12, 'status' => 'Pending']);

        /** @var User $admin */
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->get(route('admin.applications.index'))
            ->assertOk()
            ->assertSeeInOrder(['<th scope="col" class="py-2 pr-3">#</th>', 'Applicant'], false)
            ->assertSeeInOrder(['>' . $application->id . '</td>', 'Thandi Mokoena'], false)
            // the stacked cards on small screens show it too
            ->assertSee('#' . $application->id . '</span> Thandi Mokoena', false);
    }
}
