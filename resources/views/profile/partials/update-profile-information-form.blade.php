<section>
    <header>
        <h2 class="text-base font-bold text-ink">
            {{ __('Applicant profile') }}
        </h2>

        <p class="mt-1 text-sm leading-6 text-body">
            {{ __('Complete your applicant profile so your loan verification can proceed smoothly.') }}
        </p>
    </header>

    <form id="send-verification" method="post" action="{{ route('verification.send') }}">
        @csrf
    </form>

    <form method="post" action="{{ route('profile.update') }}" class="mt-6 space-y-6">
        @csrf
        @method('patch')

        <div>
            <x-input-label for="name" :value="__('Name')" />
            <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name', $user->name)" required autofocus autocomplete="name" />
            <x-input-error class="mt-2" :messages="$errors->get('name')" />
        </div>

        <div>
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input id="email" name="email" type="email" class="mt-1 block w-full" :value="old('email', $user->email)" required autocomplete="username" />
            <x-input-error class="mt-2" :messages="$errors->get('email')" />

            @if ($user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && ! $user->hasVerifiedEmail())
                <div>
                    <p class="mt-2 text-sm text-body">
                        {{ __('Your email address is unverified.') }}

                        <button form="send-verification" class="inline-flex min-h-11 items-center rounded-md text-sm font-bold text-brand underline hover:text-brand-dark focus:outline-none focus:ring-2 focus:ring-brand focus:ring-offset-2">
                            {{ __('Click here to re-send the verification email.') }}
                        </button>
                    </p>

                    @if (session('status') === 'verification-link-sent')
                        <p class="mt-2 text-sm font-semibold text-green-800">
                            {{ __('A new verification link has been sent to your email address.') }}
                        </p>
                    @endif
                </div>
            @endif
        </div>

        <div class="grid gap-6 md:grid-cols-2">
            {{-- optional; the same check as ProfileUpdateRequest, shown while typing --}}
            <div x-data="{
                     phone: {{ Js::from((string) old('phone_number', $user->phone_number)) }},
                     get phoneError() {
                         const value = this.phone.trim();
                         return value === '' || (value.length <= 30 && /^(0\d{9}|\+27\d{9})$/.test(value.replace(/ /g, '')))
                             ? ''
                             : {{ Js::from(\App\Http\Requests\ProfileUpdateRequest::PHONE_MESSAGE) }};
                     },
                 }">
                <x-input-label for="phone_number" :value="__('Phone number')" />
                <x-text-input id="phone_number" name="phone_number" type="text" inputmode="tel" class="mt-1 block w-full" :value="old('phone_number', $user->phone_number)" autocomplete="tel"
                              x-model="phone"
                              ::aria-describedby="phoneError ? 'phone_number_error' : null"
                              ::aria-invalid="phoneError ? 'true' : 'false'"
                              ::class="phoneError && '!border-red-600 focus:!border-red-600 focus:!ring-red-600'" />
                <p id="phone_number_error" class="mt-1 text-sm font-medium text-red-700" x-show="phoneError" x-text="phoneError" x-cloak></p>
                <div x-show="! phoneError">
                    <x-input-error class="mt-2" :messages="$errors->get('phone_number')" />
                </div>
            </div>

            <div>
                <x-input-label for="id_number" :value="__('ID number')" />
                <x-text-input id="id_number" name="id_number" type="text" class="mt-1 block w-full" :value="old('id_number', $user->id_number)" />
                <x-input-error class="mt-2" :messages="$errors->get('id_number')" />
            </div>
        </div>

        <div class="grid gap-6 md:grid-cols-2">
            <div>
                <x-input-label for="date_of_birth" :value="__('Date of birth')" />
                <x-text-input id="date_of_birth" name="date_of_birth" type="date" class="mt-1 block w-full" :value="old('date_of_birth', $user->date_of_birth?->format('Y-m-d'))" />
                <x-input-error class="mt-2" :messages="$errors->get('date_of_birth')" />
            </div>

            <div>
                <x-input-label for="employment_status" :value="__('Employment status')" />
                <x-text-input id="employment_status" name="employment_status" type="text" class="mt-1 block w-full" :value="old('employment_status', $user->employment_status)" placeholder="Employed / Self-employed / Unemployed" />
                <x-input-error class="mt-2" :messages="$errors->get('employment_status')" />
            </div>
        </div>

        <div>
            <x-input-label for="address" :value="__('Address')" />
            <textarea id="address" name="address" rows="4" class="mt-1 block w-full rounded-xl border-slate-300 bg-white px-4 py-3 text-ink placeholder:text-slate-500 focus:border-brand focus:ring-brand" placeholder="Street address, city, province">{{ old('address', $user->address) }}</textarea>
            <x-input-error class="mt-2" :messages="$errors->get('address')" />
        </div>

        <div>
            <x-input-label for="monthly_income" :value="__('Monthly income')" />
            <x-text-input id="monthly_income" name="monthly_income" type="number" step="0.01" min="0" class="mt-1 block w-full" :value="old('monthly_income', $user->monthly_income)" placeholder="0.00" />
            <x-input-error class="mt-2" :messages="$errors->get('monthly_income')" />
        </div>

        <div>
            @if ($user->isGraduate())
                {{-- a graduate stays a graduate, so there is nothing to choose --}}
                <p class="text-sm font-semibold text-ink">{{ __('Education') }}</p>
                <p id="education" class="mt-1 text-sm font-bold text-ink">{{ \App\Models\User::GRADUATE }}</p>
            @else
                <x-input-label for="education" :value="__('Education')" />
                <select id="education" name="education" aria-describedby="education_hint"
                        class="mt-1 block min-h-11 w-full rounded-xl border-slate-300 bg-white px-4 py-3 text-ink focus:border-brand focus:ring-brand">
                    @if (blank($user->education))
                        <option value="">{{ __('Select education') }}</option>
                    @endif
                    @foreach ($user->educationChoices() as $option)
                        <option value="{{ $option }}" @selected(old('education', $user->education) === $option)>{{ $option }}</option>
                    @endforeach
                </select>
            @endif
            <p id="education_hint" class="mt-1 text-xs text-body">{{ __('You can change from Not Graduate to Graduate when you graduate, but not back.') }}</p>
            <x-input-error class="mt-2" :messages="$errors->get('education')" />
        </div>

        <div class="flex items-center gap-4">
            <x-primary-button>{{ __('Save') }}</x-primary-button>

            @if (session('status') === 'profile-updated')
                <p
                    x-data="{ show: true }"
                    x-show="show"
                    x-transition
                    x-init="setTimeout(() => show = false, 2000)"
                    class="text-sm font-semibold text-green-800"
                >{{ __('Saved.') }}</p>
            @endif
        </div>
    </form>
</section>
