<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        // the factory gives this account a random password
        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'role' => 'applicant',
        ]);

        // the admin comes from ADMIN_EMAIL and ADMIN_SEED_PASSWORD
        $this->call(AdminSeeder::class);
    }
}
