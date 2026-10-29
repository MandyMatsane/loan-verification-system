<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable([
    'name',
    'email',
    'password',
    'role',
    'phone_number',
    'id_number',
    'date_of_birth',
    'address',
    'employment_status',
    'monthly_income',
    'education',
])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public const GRADUATE = 'Graduate';

    public const NOT_GRADUATE = 'Not Graduate';

    /** The values the application form has always used, and what the ML service receives. */
    public const EDUCATION_OPTIONS = [self::GRADUATE, self::NOT_GRADUATE];

    public function isGraduate(): bool
    {
        return $this->education === self::GRADUATE;
    }

    /**
     * What education may be set to now: a graduate stays a graduate; anyone else may choose either.
     *
     * @return string[]
     */
    public function educationChoices(): array
    {
        return $this->isGraduate() ? [self::GRADUATE] : self::EDUCATION_OPTIONS;
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'date_of_birth' => 'date',
            'password' => 'hashed',
        ];
    }
}
