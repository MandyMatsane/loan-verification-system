<x-app-layout>
    @php
        $stepFields = [
            1 => ['amount_requested', 'loan_term', 'no_of_dependents', 'education'],
            2 => ['cibil_score_band', 'residential_assets_value', 'commercial_assets_value', 'luxury_assets_value', 'bank_asset_value'],
            3 => ['id_document', 'payslip', 'bank_statement'],
        ];

        // open the step that contains the first validation error
        $firstErrorStep = 1;
        foreach ($stepFields as $number => $fields) {
            if ($errors->hasAny($fields)) {
                $firstErrorStep = $number;
                break;
            }
        }

        // education comes from the profile: a graduate's is fixed, anyone else may still choose Graduate
        $profileEducation = auth()->user()->education;

        $bands = ['Poor' => '300-549', 'Fair' => '550-649', 'Good' => '650-749', 'Excellent' => '750-900'];

        $assets = [
            'residential_assets_value' => 'Residential assets (R)',
            'commercial_assets_value' => 'Commercial assets (R)',
            'luxury_assets_value' => 'Luxury assets (R)',
            'bank_asset_value' => 'Bank assets (R)',
        ];

        $assetHints = [
            'residential_assets_value' => 'What the homes you own are worth today.',
            'commercial_assets_value' => 'What the business property you own is worth.',
            'luxury_assets_value' => 'What your cars, jewellery and other valuables are worth.',
            'bank_asset_value' => 'The balance shown on your bank statement.',
        ];

        $documents = [
            'id_document' => 'ID document',
            'payslip' => 'Payslip',
            'bank_statement' => 'Bank statement',
        ];

        // per slot: the id of the earlier document to reuse, or '' to upload a new file (the default)
        $reuseChoice = [];
        foreach ($documents as $field => $label) {
            $reuseChoice[$field] = isset($reusable[$field]) && (string) old($field . '_reuse') === (string) $reusable[$field]->id
                ? (string) $reusable[$field]->id
                : '';
        }

        $maxAmount = (int) config('loan.max_amount');
        $minTerm = (int) config('loan.min_term');
        $maxTerm = (int) config('loan.max_term');

        $ratePercent =rtrim(rtrim(number_format((float) config('loan.annual_interest_rate') * 100, 2), '0'), '.') . '%';

        $steps = [1 => 'Loan details', 2 => 'Financial profile', 3 => 'Documents'];

        $fieldClass = 'mt-2 block w-full';
        $selectClass = 'mt-2 block min-h-12 w-full rounded-xl border-slate-300 bg-white px-4 text-ink focus:border-brand focus:ring-brand';
    @endphp

    <script>
        function applicationForm(config) {
            return {
                step: config.step,
                form: config.form,
                submitting: false,
                files: { id_document: null, payslip: null, bank_statement: null },
                reuse: config.reuse,
                rate: config.rate,
                limits: config.limits,
                // empty fields only count as errors once touched, or once the user tried to move on
                touched: { amount_requested: false, loan_term: false },
                triedNext: false,
                // the same limits and wording as the server-side validation in LoanApplicationController
                get amountError() {
                    const raw = String(this.form.amount_requested ?? '').trim();
                    const most = 'The most you can apply for is R' + this.limits.max_amount.toLocaleString('en-US');
                    if (raw === '') {
                        return this.touched.amount_requested || this.triedNext
                            ? 'Enter an amount from R1 to R' + this.limits.max_amount.toLocaleString('en-US')
                            : '';
                    }
                    const amount = Number(raw);
                    if (! Number.isFinite(amount)) {
                        return 'Enter an amount from R1 to R' + this.limits.max_amount.toLocaleString('en-US');
                    }
                    if (amount < 1) {
                        return 'The least you can apply for is R1';
                    }
                    return amount > this.limits.max_amount ? most : '';
                },
                get termError() {
                    const raw = String(this.form.loan_term ?? '').trim();
                    const message = 'Choose a term between ' + this.limits.min_term + ' and ' + this.limits.max_term + ' months';
                    if (raw === '') {
                        return this.touched.loan_term || this.triedNext ? message : '';
                    }
                    const term = Number(raw);
                    return Number.isInteger(term) && term >= this.limits.min_term && term <= this.limits.max_term ? '' : message;
                },
                // filled in and inside the limits
                get amountValid() {
                    return String(this.form.amount_requested ?? '').trim() !== '' && this.amountError === '';
                },
                get termValid() {
                    return String(this.form.loan_term ?? '').trim() !== '' && this.termError === '';
                },
                get loanDetailsValid() {
                    return this.amountValid && this.termValid;
                },
                // number inputs let through e, E, + and -: refuse those keys (and decimals in the term).
                // Only keys and pastes are refused; a value already typed is never changed.
                blockKeys(event, wholeNumber) {
                    const blocked = wholeNumber ? ['e', 'E', '+', '-', '.', ','] : ['e', 'E', '+', '-'];
                    if (blocked.includes(event.key)) {
                        event.preventDefault();
                    }
                },
                blockPaste(event, wholeNumber) {
                    const text = (event.clipboardData || window.clipboardData).getData('text').trim();
                    if (! (wholeNumber ? /^\d+$/ : /^\d+(\.\d*)?$|^\.\d+$/).test(text)) {
                        event.preventDefault();
                    }
                },
                // same formula as App\Services\RepaymentCalculator; the server works it out again on submit
                get estimate() {
                    if (! this.loanDetailsValid) {
                        return null;
                    }
                    const amount = Number(this.form.amount_requested);
                    const term = Number(this.form.loan_term);
                    const round = (value) => Math.round((value + Number.EPSILON) * 100) / 100;
                    const monthlyRate = this.rate / 12;
                    const monthly = monthlyRate === 0
                        ? amount / term
                        : amount * monthlyRate / (1 - Math.pow(1 + monthlyRate, -term));
                    const total = round(monthly * term);
                    return { monthly: round(monthly), total: total, interest: round(total - amount) };
                },
                get ratePercent() {
                    return Number((this.rate * 100).toFixed(2)) + '%';
                },
                names: { id_document: 'ID document', payslip: 'payslip', bank_statement: 'bank statement' },
                steps: ['Loan details', 'Financial profile', 'Documents'],
                pick(key, event) {
                    const file = event.target.files[0];
                    this.files[key] = file
                        ? { name: file.name, size: (file.size / 1048576).toFixed(1) + ' MB', tooLarge: file.size > 5 * 1048576 }
                        : null;
                },
                ok(key) {
                    if (this.reuse[key] !== '') {
                        return true;
                    }
                    return this.files[key] !== null && ! this.files[key].tooLarge;
                },
                get chosen() {
                    return Object.keys(this.files).filter((key) => this.ok(key)).length;
                },
                get missing() {
                    return Object.keys(this.files).filter((key) => ! this.ok(key)).map((key) => this.names[key]);
                },
                get canSubmit() {
                    return this.loanDetailsValid && this.missing.length === 0;
                },
                get helper() {
                    if (! this.loanDetailsValid) {
                        return 'Check the amount and loan term to continue';
                    }
                    const missing = this.missing;
                    if (missing.length === 0) {
                        return 'All three documents are added.';
                    }
                    const list = missing.length === 1
                        ? missing[0]
                        : missing.slice(0, -1).join(', ') + ' and ' + missing[missing.length - 1];
                    return 'Add your ' + list + ' to continue';
                },
                done(step) {
                    if (step === 1) {
                        return this.loanDetailsValid
                            && ['no_of_dependents', 'education'].every((key) => String(this.form[key] ?? '') !== '');
                    }
                    if (step === 2) {
                        return String(this.form.cibil_score_band ?? '') !== '';
                    }
                    return this.canSubmit;
                },
                money(value) {
                    if (value === '' || value === null || isNaN(value)) {
                        return '-';
                    }
                    return 'R' + Number(value).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                },
                // a click on a disabled Next or Submit lands on its wrapper: show why it is disabled
                tryNext() {
                    this.triedNext = true;
                },
                termLabel() {
                    return this.termValid ? this.form.loan_term + (Number(this.form.loan_term) === 1 ? ' month' : ' months') : '-';
                },
                go(step) {
                    this.step = step;
                    window.scrollTo({ top: 0 });
                },
            };
        }
    </script>

    <form method="POST" action="{{ route('applications.store') }}" enctype="multipart/form-data"
          x-data="applicationForm({{ Js::from([
              'step' => $firstErrorStep,
              'form' => [
                  'amount_requested' => old('amount_requested', ''),
                  'loan_term' => old('loan_term', ''),
                  'no_of_dependents' => old('no_of_dependents', ''),
                  'education' => $profileEducation === 'Graduate' ? 'Graduate' : old('education', $profileEducation ?? ''),
                  'cibil_score_band' => old('cibil_score_band', ''),
              ],
              'reuse' => $reuseChoice,
              'rate' => (float) config('loan.annual_interest_rate'),
              'limits' => ['max_amount' => $maxAmount, 'min_term' => $minTerm, 'max_term' => $maxTerm],
          ]) }})"
          @submit="submitting = true"
          class="space-y-6">
        @csrf

        {{-- Phone header and progress --}}
        <div class="space-y-4 md:hidden">
            <div class="flex items-center gap-2">
                <a href="{{ route('dashboard') }}" class="-ml-2 flex h-11 w-11 items-center justify-center rounded-xl text-ink hover:bg-slate-200 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand" aria-label="Back to dashboard">
                    <x-icon name="chevron-left" class="h-6 w-6" />
                </a>
                <h1 class="text-lg font-extrabold text-ink">New application</h1>
            </div>
            <div>
                <p class="text-sm text-slate-500">Step <span x-text="step">{{ $firstErrorStep }}</span> of 3 - <span x-text="steps[step - 1]">{{ $steps[$firstErrorStep] }}</span></p>
                <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-slate-200" role="progressbar" aria-label="Application progress" aria-valuemin="1" aria-valuemax="3" :aria-valuenow="step">
                    <div class="h-full rounded-full bg-brand transition-all" style="width: {{ round($firstErrorStep / 3 * 100) }}%" :style="{ width: (step / 3 * 100) + '%' }"></div>
                </div>
            </div>
        </div>

        {{-- Laptop header and stepper --}}
        <div class="hidden space-y-6 md:block">
            <x-page-header eyebrow="Dashboard / New application" title="New application" />

            <ol class="flex items-center gap-3">
                @foreach ($steps as $number => $label)
                    <li class="flex items-center gap-2">
                        <a href="#step-{{ $number }}" class="flex min-h-11 items-center gap-2 rounded-xl pr-1 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand">
                            <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full border-2 border-brand text-xs font-bold"
                                  :class="done({{ $number }}) ? 'bg-brand text-white' : 'bg-white text-brand'">
                                <span x-show="! done({{ $number }})">{{ $number }}</span>
                                <x-icon name="check" class="h-4 w-4" x-show="done({{ $number }})" x-cloak />
                            </span>
                            <span class="text-sm font-bold text-ink">{{ $label }}</span>
                        </a>
                    </li>
                    @if (! $loop->last)
                        <li class="h-px flex-1 bg-slate-300" aria-hidden="true"></li>
                    @endif
                @endforeach
            </ol>
        </div>

        @if ($errors->any())
            <div class="rounded-2xl border border-red-200 bg-red-100 px-4 py-3 text-sm font-semibold text-red-800" role="alert">
                Some details need your attention. Check the fields marked below, then add your documents again.
            </div>
        @endif

        <div class="grid gap-6 xl:grid-cols-3">
            <div class="min-w-0 space-y-6 xl:col-span-2">
                {{-- Step 1: Loan details --}}
                <section id="step-1" class="scroll-mt-6 rounded-2xl border border-slate-200 bg-white p-5 md:block md:p-6" :class="{ 'hidden': step !== 1 }">
                    <h2 class="text-base font-bold text-ink">Loan details</h2>
                    <p class="mt-1 text-sm text-body">Tell us how much you need and for how long.</p>

                    <div class="mt-5 grid gap-5 md:grid-cols-2">
                        <div>
                            <x-input-label for="amount_requested" value="Amount requested (R)" />
                            <x-text-input id="amount_requested" type="number" name="amount_requested" step="0.01" min="1" max="{{ $maxAmount }}" inputmode="decimal"
                                          aria-describedby="amount_requested_hint"
                                          ::aria-describedby="amountError ? 'amount_requested_error' : 'amount_requested_hint'"
                                          ::aria-invalid="amountError ? 'true' : 'false'"
                                          ::class="amountError && '!border-red-600 focus:!border-red-600 focus:!ring-red-600'"
                                          @keydown="blockKeys($event, false)" @paste="blockPaste($event, false)"
                                          @input="touched.amount_requested = true" @blur="touched.amount_requested = true"
                                          x-model="form.amount_requested" value="{{ old('amount_requested') }}" :class="$fieldClass" />
                            <p id="amount_requested_hint" class="mt-1 text-xs text-body" x-show="! amountError">From R1 to R{{ number_format($maxAmount) }}</p>
                            <p id="amount_requested_error" class="mt-1 text-sm font-medium text-red-700" x-show="amountError" x-text="amountError" x-cloak></p>
                            <div x-show="! amountError">
                                <x-input-error :messages="$errors->get('amount_requested')" class="mt-2" />
                            </div>
                        </div>

                        <div>
                            <x-input-label for="loan_term" value="Loan term (months)" />
                            <x-text-input id="loan_term" type="number" name="loan_term" step="1" min="{{ $minTerm }}" max="{{ $maxTerm }}" inputmode="numeric"
                                          aria-describedby="loan_term_hint"
                                          ::aria-describedby="termError ? 'loan_term_error' : 'loan_term_hint'"
                                          ::aria-invalid="termError ? 'true' : 'false'"
                                          ::class="termError && '!border-red-600 focus:!border-red-600 focus:!ring-red-600'"
                                          @keydown="blockKeys($event, true)" @paste="blockPaste($event, true)"
                                          @input="touched.loan_term = true" @blur="touched.loan_term = true"
                                          x-model="form.loan_term" value="{{ old('loan_term') }}" :class="$fieldClass" />
                            <p id="loan_term_hint" class="mt-1 text-xs text-body" x-show="! termError">{{ $minTerm }} to {{ $maxTerm }} months</p>
                            <p id="loan_term_error" class="mt-1 text-sm font-medium text-red-700" x-show="termError" x-text="termError" x-cloak></p>
                            <div x-show="! termError">
                                <x-input-error :messages="$errors->get('loan_term')" class="mt-2" />
                            </div>
                        </div>

                        <div>
                            <x-input-label for="no_of_dependents" value="Number of dependents" />
                            <x-text-input id="no_of_dependents" type="number" name="no_of_dependents" min="0" inputmode="numeric"
                                          x-model="form.no_of_dependents" value="{{ old('no_of_dependents') }}" :class="$fieldClass" />
                            <x-input-error :messages="$errors->get('no_of_dependents')" class="mt-2" />
                        </div>

                        <div>
                            @if ($profileEducation === 'Graduate')
                                <p class="text-sm font-semibold text-ink">Education</p>
                                <p class="mt-1 flex min-h-12 items-center text-sm font-bold text-ink">Graduate</p>
                                <input type="hidden" name="education" value="Graduate">
                            @else
                                <x-input-label for="education" value="Education" />
                                <select id="education" name="education" x-model="form.education" class="{{ $selectClass }}" aria-describedby="education_hint">
                                    @if ($profileEducation === null)
                                        <option value="">Select education</option>
                                    @endif
                                    <option value="Graduate" {{ old('education', $profileEducation) == 'Graduate' ? 'selected' : '' }}>Graduate</option>
                                    <option value="Not Graduate" {{ old('education', $profileEducation) == 'Not Graduate' ? 'selected' : '' }}>Not Graduate</option>
                                </select>
                                <p id="education_hint" class="mt-1 text-xs text-body">{{ $profileEducation === null ? 'Saved to your profile when you apply.' : 'From your profile. Choose Graduate if you have graduated since.' }}</p>
                            @endif
                            <x-input-error :messages="$errors->get('education')" class="mt-2" />
                        </div>
                    </div>

                    <div class="mt-5 rounded-xl bg-brand-tint p-4" aria-live="polite">
                        <h3 class="text-sm font-bold text-ink">Repayment estimate</h3>
                        <dl class="mt-3 grid grid-cols-1 gap-3 text-sm sm:grid-cols-3">
                            <div>
                                <dt class="text-xs text-body">Monthly repayment</dt>
                                <dd class="font-bold tabular-nums text-ink" x-text="estimate ? money(estimate.monthly) : '-'">-</dd>
                            </div>
                            <div>
                                <dt class="text-xs text-body">Total repayable</dt>
                                <dd class="font-bold tabular-nums text-ink" x-text="estimate ? money(estimate.total) : '-'">-</dd>
                            </div>
                            <div>
                                <dt class="text-xs text-body">Interest (<span x-text="ratePercent">{{ $ratePercent }}</span> a year)</dt>
                                <dd class="font-bold tabular-nums text-ink" x-text="estimate ? money(estimate.interest) : '-'">-</dd>
                            </div>
                        </dl>
                        <p class="mt-3 text-xs text-body">Estimate only. Final terms are set by the lender.</p>
                    </div>
                </section>

                {{-- Step 2: Financial profile --}}
                <section id="step-2" class="scroll-mt-6 rounded-2xl border border-slate-200 bg-white p-5 md:block md:p-6" :class="{ 'hidden': step !== 2 }">
                    <h2 class="text-base font-bold text-ink">Financial profile</h2>
                    <p class="mt-1 text-sm text-body">Choose your credit score band and tell us what you own.</p>

                    <fieldset class="mt-5">
                        <legend class="text-sm font-semibold text-ink">Credit score band</legend>
                        <p class="mt-1 text-xs text-body">The range your latest credit score falls in.</p>
                        <div class="mt-2 grid grid-cols-2 gap-3 lg:grid-cols-4">
                            @foreach ($bands as $band => $range)
                                <div>
                                    <input type="radio" id="cibil_{{ strtolower($band) }}" name="cibil_score_band" value="{{ $band }}"
                                           x-model="form.cibil_score_band" class="peer sr-only" {{ old('cibil_score_band') == $band ? 'checked' : '' }}>
                                    <label for="cibil_{{ strtolower($band) }}"
                                           class="flex min-h-12 cursor-pointer flex-col justify-center rounded-xl border border-slate-300 bg-white px-4 py-2 transition hover:border-brand peer-checked:border-brand peer-checked:bg-brand-tint peer-checked:ring-1 peer-checked:ring-brand peer-focus-visible:ring-2 peer-focus-visible:ring-brand">
                                        <span class="text-sm font-bold text-ink">{{ $band }}</span>
                                        <span class="text-xs text-body">{{ $range }}</span>
                                    </label>
                                </div>
                            @endforeach
                        </div>
                        <x-input-error :messages="$errors->get('cibil_score_band')" class="mt-2" />
                    </fieldset>

                    <div class="mt-5 grid gap-5 md:grid-cols-2">
                        @foreach ($assets as $field => $label)
                            <div>
                                <x-input-label :for="$field" :value="$label" />
                                <x-text-input :id="$field" type="number" :name="$field" step="0.01" min="0" inputmode="decimal"
                                              value="{{ old($field, 0) }}" :class="$fieldClass" aria-describedby="{{ $field }}_hint" />
                                <p id="{{ $field }}_hint" class="mt-1 text-xs text-body">{{ $assetHints[$field] }}</p>
                                <x-input-error :messages="$errors->get($field)" class="mt-2" />
                            </div>
                        @endforeach
                    </div>
                </section>

                {{-- Step 3: Documents --}}
                <section id="step-3" class="scroll-mt-6 rounded-2xl border border-slate-200 bg-white p-5 md:block md:p-6" :class="{ 'hidden': step !== 3 }">
                    <h2 class="text-base font-bold text-ink"><span class="md:hidden">Upload your documents</span><span class="hidden md:inline">Documents</span></h2>
                    <p class="mt-1 text-sm text-body">JPG or PNG, max 5MB each. We read them automatically.</p>

                    <div class="mt-5 grid gap-3 xl:grid-cols-3">
                        @foreach ($documents as $field => $label)
                            <div>
                                @isset ($reusable[$field])
                                    <fieldset class="mb-3">
                                        <legend class="text-sm font-bold text-ink">{{ $label }}</legend>
                                        <label class="mt-1 flex min-h-11 cursor-pointer items-center gap-3 text-sm text-ink">
                                            <input type="radio" name="{{ $field }}_reuse" value="" x-model="reuse.{{ $field }}"
                                                   class="h-4 w-4 border-slate-300 text-brand focus:ring-brand" {{ $reuseChoice[$field] === '' ? 'checked' : '' }}>
                                            Upload a new file
                                        </label>
                                        <label class="flex min-h-11 cursor-pointer items-center gap-3 text-sm text-ink">
                                            <input type="radio" name="{{ $field }}_reuse" value="{{ $reusable[$field]->id }}" x-model="reuse.{{ $field }}"
                                                   class="h-4 w-4 shrink-0 border-slate-300 text-brand focus:ring-brand" {{ $reuseChoice[$field] !== '' ? 'checked' : '' }}>
                                            <span class="min-w-0 break-words">Reuse latest ({{ $reusable[$field]->displayName() }}, {{ $reusable[$field]->uploaded_at->format('j M Y') }})</span>
                                        </label>
                                    </fieldset>
                                @endisset

                                <div x-show="reuse.{{ $field }} === ''" @if ($reuseChoice[$field] !== '') x-cloak @endif>
                                    <input id="{{ $field }}" type="file" name="{{ $field }}" accept=".jpg,.jpeg,.png" class="peer sr-only"
                                           :disabled="reuse.{{ $field }} !== ''" @change="pick('{{ $field }}', $event)">
                                    <label for="{{ $field }}"
                                           class="flex min-h-12 cursor-pointer items-center gap-3 rounded-2xl border-2 border-dashed border-brand bg-white p-4 transition hover:bg-brand-tint peer-focus-visible:ring-2 peer-focus-visible:ring-brand peer-focus-visible:ring-offset-2 xl:h-full xl:flex-col xl:items-start">
                                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl"
                                              :class="ok('{{ $field }}') ? 'bg-green-100 text-green-800' : 'bg-brand-tint text-brand'">
                                            <x-icon name="upload" x-show="! ok('{{ $field }}')" />
                                            <x-icon name="check" x-show="ok('{{ $field }}')" x-cloak />
                                        </span>
                                        <span class="min-w-0 flex-1 xl:w-full xl:flex-none">
                                            <span class="block text-sm font-bold text-ink">{{ $label }}</span>
                                            <span class="block truncate text-xs text-body"
                                                  x-text="files.{{ $field }} ? files.{{ $field }}.name + ' - ' + files.{{ $field }}.size : 'JPG or PNG, max 5MB'">JPG or PNG, max 5MB</span>
                                            <span class="block text-xs font-semibold text-red-700" x-show="files.{{ $field }} && files.{{ $field }}.tooLarge" x-cloak>This file is larger than 5MB.</span>
                                        </span>
                                        <span class="shrink-0 text-sm font-bold text-brand" x-text="files.{{ $field }} ? 'Replace' : 'Choose file'">Choose file</span>
                                    </label>
                                </div>
                                <x-input-error :messages="$errors->get($field)" class="mt-2" />
                            </div>
                        @endforeach
                    </div>

                    <p class="mt-4 text-sm text-body">Your documents are only used to assess this application.</p>
                </section>

                {{-- Phone step controls --}}
                <div class="space-y-2 md:hidden">
                    <div class="flex gap-3">
                        <x-secondary-button class="flex-1" x-show="step > 1" x-cloak @click="go(step - 1)">Back</x-secondary-button>
                        <div class="flex flex-1" x-show="step < 3" @click="tryNext()">
                            <x-primary-button type="button" class="flex-1 disabled:pointer-events-none" ::disabled="step === 1 && ! loanDetailsValid" @click="go(step + 1)">Next</x-primary-button>
                        </div>
                        <div class="flex flex-1" x-show="step === 3" x-cloak @click="tryNext()">
                            <x-primary-button class="flex-1 disabled:pointer-events-none" ::disabled="! canSubmit || submitting">
                                <span x-text="submitting ? 'Submitting...' : 'Submit application'">Submit application</span>
                            </x-primary-button>
                        </div>
                    </div>
                    <p class="text-center text-sm text-body" x-show="step === 3" x-cloak x-text="helper" aria-live="polite"></p>
                </div>
            </div>

            {{-- Laptop summary panel --}}
            <aside class="hidden self-start rounded-2xl border border-slate-200 bg-white p-6 md:block xl:sticky xl:top-6">
                <h2 class="text-base font-bold text-ink">Your application</h2>

                <dl class="mt-4 space-y-3 text-sm">
                    <div class="flex items-baseline justify-between gap-3">
                        <dt class="text-body">Amount</dt>
                        <dd class="font-bold tabular-nums text-ink" x-text="amountValid ? money(form.amount_requested) : '-'">-</dd>
                    </div>
                    <div class="flex items-baseline justify-between gap-3">
                        <dt class="text-body">Term</dt>
                        <dd class="font-bold text-ink" x-text="termLabel()">-</dd>
                    </div>
                    <div class="flex items-baseline justify-between gap-3">
                        <dt class="text-body">Monthly repayment</dt>
                        <dd class="font-bold tabular-nums text-ink" x-text="estimate ? money(estimate.monthly) : '-'">-</dd>
                    </div>
                    <div class="flex items-baseline justify-between gap-3">
                        <dt class="text-body">Total repayable</dt>
                        <dd class="font-bold tabular-nums text-ink" x-text="estimate ? money(estimate.total) : '-'">-</dd>
                    </div>
                    <div class="flex items-baseline justify-between gap-3">
                        <dt class="text-body">Interest (<span x-text="ratePercent">{{ $ratePercent }}</span>)</dt>
                        <dd class="font-bold tabular-nums text-ink" x-text="estimate ? money(estimate.interest) : '-'">-</dd>
                    </div>
                    <div class="flex items-baseline justify-between gap-3">
                        <dt class="text-body">Credit score band</dt>
                        <dd class="font-bold text-ink" x-text="form.cibil_score_band || '-'">-</dd>
                    </div>
                    <div class="flex items-baseline justify-between gap-3">
                        <dt class="text-body">Documents</dt>
                        <dd class="font-bold text-ink"><span x-text="chosen">0</span> of 3</dd>
                    </div>
                </dl>
                <p class="mt-3 text-xs text-body">Estimate only. Final terms are set by the lender.</p>

                <div class="mt-5" @click="tryNext()">
                    <x-primary-button class="w-full disabled:pointer-events-none" ::disabled="! canSubmit || submitting">
                        <span x-text="submitting ? 'Submitting...' : 'Submit application'">Submit application</span>
                    </x-primary-button>
                </div>
                <p class="mt-2 text-center text-sm text-body" x-text="helper" aria-live="polite"></p>
            </aside>
        </div>
    </form>
</x-app-layout>
