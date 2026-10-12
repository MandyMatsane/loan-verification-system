<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class AdminSeeder extends Seeder
{
    /**
     * Create the admin account from ADMIN_EMAIL and ADMIN_SEED_PASSWORD.
     * Safe to run many times: an existing account is left untouched.
     */
    public function run(): void
    {
        $email = env('ADMIN_EMAIL');
        $password = env('ADMIN_SEED_PASSWORD');

        if (blank($email) || blank($password)) {
            return;
        }

        $admin = User::firstOrCreate(
            ['email' => $email],
            [
                'name' => 'Admin User',
                'role' => 'admin',
                'password' => $password, // hashed by the User model cast
            ]
        );

        if ($admin->wasRecentlyCreated) {
            $admin->forceFill(['email_verified_at' => now()])->save();
        }
    }
}
