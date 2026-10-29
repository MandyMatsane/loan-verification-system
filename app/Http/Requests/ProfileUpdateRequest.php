<?php

namespace App\Http\Requests;

use App\Models\User;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProfileUpdateRequest extends FormRequest
{
    public const EDUCATION_BACK_MESSAGE = 'Your profile says Graduate. Education can change from Not Graduate to Graduate, but not back.';

    /**
     * Education is set once: when nothing is sent (a graduate sees it as fixed text), keep what is saved.
     */
    protected function prepareForValidation(): void
    {
        if (blank($this->input('education')) && filled($this->user()->education)) {
            $this->merge(['education' => $this->user()->education]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique(User::class)->ignore($this->user()->id),
            ],
            'phone_number' => ['nullable', 'string', 'max:30'],
            'id_number' => ['nullable', 'string', 'digits:13'],
            'date_of_birth' => ['nullable', 'date'],
            'address' => ['nullable', 'string', 'max:500'],
            'employment_status' => ['nullable', 'string', 'max:50'],
            'monthly_income' => ['nullable', 'numeric', 'min:0'],
            'education' => [
                'nullable',
                Rule::in(User::EDUCATION_OPTIONS),
                function (string $attribute, mixed $value, Closure $fail) {
                    if ($this->user()->isGraduate() && $value === User::NOT_GRADUATE) {
                        $fail(self::EDUCATION_BACK_MESSAGE);
                    }
                },
            ],
        ];
    }
}
