<x-app-layout>
    @php
        $assessment = $application->aiAssessment;
        $money = fn ($value) => 'R' . number_format((float) ($value ?? 0), 2);

        // a check that sends an application to manual review puts its findings first, under its own prefix:
        // "Document check:" first, then "Affordability check:". Below 70% confidence the model would have
        // sent it to manual review anyway.
        $notes = (string) $assessment?->reasoning_notes;
        $inManualReview = $assessment && strtolower(trim((string) $application->status)) === 'manual review';
        $flaggedByDocumentCheck = $inManualReview && str_starts_with($notes, \App\Services\ApplicationAssessor::DOCUMENT_CHECK_PREFIX);
        $flaggedByAffordabilityCheck = $inManualReview
            && (str_starts_with($notes, \App\Services\ApplicationAssessor::AFFORDABILITY_CHECK_PREFIX)
                || str_contains($notes, "\n\n" . \App\Services\ApplicationAssessor::AFFORDABILITY_CHECK_PREFIX . "\n"));

        $checkLine = match (true) {
            $flaggedByDocumentCheck && $flaggedByAffordabilityCheck => 'Flagged by the document and affordability checks. See the reasoning notes.',
            $flaggedByAffordabilityCheck => 'Flagged by the affordability check. See the reasoning notes.',
            $flaggedByDocumentCheck && (float) $assessment->confidence_score >= 70 => 'Overridden by the document check. See the reasoning notes.',
            $flaggedByDocumentCheck => 'Sent to manual review by low confidence and the document check. See the reasoning notes.',
            default => null,
        };

        $statedIncome = $application->statedMonthlyIncome();
        $payslipIncome = collect(['Gross' => $payslipFigures['gross'] ?? null, 'Net' => $payslipFigures['net'] ?? null])
            ->filter(fn ($value) => $value !== null)
            ->map(fn ($value, $label) => $label . ' ' . $money($value))
            ->implode(' · ');

        $documentLabels = ['id_document' => 'ID document', 'payslip' => 'Payslip', 'bank_statement' => 'Bank statement'];

        $repayment = $application->repaymentEstimate();

        $details = [
            'Amount requested' => $money($application->amount_requested),
            'Loan term' => $application->loanTermLabel(),
            'Monthly repayment' => $repayment['monthly_repayment'],
            'Total repayable' => $repayment['total_repayable'],
            'Interest' => $repayment['interest'],
            'Dependents' => $application->no_of_dependents ?? '-',
            'Education' => $application->education ?? '-',
            'Stated monthly income' => $statedIncome === null ? '-' : $money($statedIncome),
            'Payslip income' => $payslipIncome !== '' ? $payslipIncome : 'Not found on the payslip',
            'Bank statement balance' => $bankStatementBalance === null ? 'Not found on the bank statement' : $money($bankStatementBalance),
        ];

        $assets = [
            'Residential assets' => $money($application->residential_assets_value),
            'Commercial assets' => $money($application->commercial_assets_value),
            'Luxury assets' => $money($application->luxury_assets_value),
            'Bank assets' => $money($application->bank_asset_value),
            'Submitted' => $application->created_at?->format('j M Y') ?? '-',
        ];
    @endphp

    <div class="space-y-6">
        <x-page-header eyebrow="Applications / #{{ $application->id }}" title="Application #{{ $application->id }}">
            <x-slot name="badge">
                <x-status-badge :status="$application->status" />
                @if ($application->needsManualRetry())
                    <x-manual-retry-badge />
                @endif
            </x-slot>
            <x-slot name="actions">
                @if ($application->isRetryable())
                    <form method="POST" action="{{ route('admin.applications.retry', $application) }}">
                        @csrf
                        <x-primary-button>Retry assessment</x-primary-button>
                    </form>
                @endif
                <x-button-link variant="outline" href="{{ route('admin.applications.index') }}">
                    <x-icon name="chevron-left" />
                    Back to queue
                </x-button-link>
            </x-slot>
        </x-page-header>

        @if (session('success'))
            <div class="rounded-2xl border border-green-200 bg-green-100 px-5 py-4 text-sm font-semibold text-green-800" role="status">
                {{ session('success') }}
            </div>
        @endif

        @if (session('warning'))
            <div class="rounded-2xl border border-amber-200 bg-amber-100 px-5 py-4 text-sm font-semibold text-amber-800" role="alert">
                {{ session('warning') }}
            </div>
        @endif

        <div class="grid gap-6 xl:grid-cols-3">
            <div class="min-w-0 space-y-6 xl:col-span-2">
                <x-card title="Applicant and loan">
                    <dl class="grid grid-cols-2 gap-x-4 gap-y-5 md:grid-cols-3">
                        <div class="col-span-2 md:col-span-1">
                            <dt class="text-xs text-slate-500">Applicant</dt>
                            <dd class="mt-0.5 text-sm font-bold text-ink">{{ $application->user?->name ?? 'Unknown' }}</dd>
                            <dd class="break-all text-xs text-body">{{ $application->user?->email ?? 'No email' }}</dd>
                        </div>

                        @foreach ($details as $label => $value)
                            {{-- the gross and net payslip figures need two columns to stay on one line --}}
                            <div @class(['col-span-2' => $label === 'Payslip income'])>
                                <dt class="text-xs text-slate-500">{{ $label }}</dt>
                                <dd @class(['mt-0.5 text-sm font-bold text-ink', 'whitespace-nowrap' => $label === 'Payslip income'])>{{ $value }}</dd>
                            </div>
                        @endforeach

                        <div>
                            <dt class="text-xs text-slate-500">Credit score band</dt>
                            <dd class="mt-0.5 flex flex-wrap items-center gap-2 text-sm font-bold text-ink">
                                {{ $application->cibil_score_band ?? '-' }}
                                <span class="inline-flex items-center whitespace-nowrap rounded-full bg-amber-100 px-2.5 py-1 text-xs font-bold text-amber-800">Self-reported</span>
                            </dd>
                        </div>

                        @foreach ($assets as $label => $value)
                            <div>
                                <dt class="text-xs text-slate-500">{{ $label }}</dt>
                                <dd class="mt-0.5 text-sm font-bold tabular-nums text-ink">{{ $value }}</dd>
                            </div>
                        @endforeach
                    </dl>
                </x-card>

                <x-card title="Documents and extracted text">
                    @forelse ($application->documents as $document)
                        <div class="{{ $loop->first ? '' : 'mt-5' }}">
                            <div class="flex items-center gap-3">
                                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-brand-tint text-brand">
                                    <x-icon name="document" />
                                </span>
                                <div class="min-w-0">
                                    <h3 class="text-sm font-bold text-ink">{{ $documentLabels[$document->type] ?? ucfirst(str_replace('_', ' ', $document->type)) }}</h3>
                                    <p class="truncate text-xs text-body">{{ $document->fileExists() ? $document->displayName() : 'File no longer available' }}</p>
                                    @if ($document->isReused())
                                        <p class="text-xs font-semibold text-brand">Reused from earlier application</p>
                                    @endif
                                </div>
                                @if ($document->fileExists())
                                    <a href="{{ route('documents.download', $document) }}" class="ml-auto flex min-h-11 shrink-0 items-center text-sm font-bold text-brand hover:text-brand-dark focus:outline-none focus-visible:ring-2 focus-visible:ring-brand">Download</a>
                                @endif
                            </div>
                            @if (filled(trim((string) $document->ocrResult?->extracted_text)))
                                <pre class="mt-2 max-h-48 overflow-auto whitespace-pre-wrap break-words rounded-xl bg-slate-100 p-3 font-mono text-xs leading-5 text-slate-700" tabindex="0">{{ $document->ocrResult->extracted_text }}</pre>
                            @elseif ($document->ocrResult === null && in_array($document->type, \App\Http\Controllers\LoanApplicationController::READ_AFTER_RESPONSE, true))
                                <p class="mt-2 rounded-xl bg-slate-100 p-3 text-sm text-body">Text not read yet</p>
                            @else
                                <p class="mt-2 rounded-xl bg-slate-100 p-3 text-sm text-body">No readable text was found in this file.</p>
                            @endif
                        </div>
                    @empty
                        <p class="text-sm text-body">No documents uploaded.</p>
                    @endforelse
                </x-card>
            </div>

            <div class="space-y-6">
                <x-card title="AI assessment">
                    @if ($assessment)
                        <p class="text-xs text-slate-500">Model outcome</p>
                        <p class="mt-0.5 text-sm font-bold text-ink">{{ $assessment->eligibility_outcome }}</p>

                        @if ($checkLine)
                            <p class="mt-1 text-xs font-semibold text-amber-800">{{ $checkLine }}</p>
                        @endif

                        <x-confidence-bar class="mt-4" label="Confidence" :score="$assessment->confidence_score" />

                        <div class="mt-4 flex items-center justify-between gap-3 text-sm">
                            <span class="text-body">Fraud risk</span>
                            <x-risk-badge :level="$assessment->fraud_risk_score" />
                        </div>

                        <div class="mt-4 rounded-xl bg-surface p-3">
                            <h3 class="text-xs font-bold text-ink">Reasoning notes</h3>
                            <p class="mt-1 whitespace-pre-wrap text-sm leading-6 text-body">{{ $assessment->reasoning_notes ?? 'No reasoning notes provided.' }}</p>
                        </div>
                    @else
                        <p class="text-sm text-body">Not yet assessed.</p>
                    @endif
                </x-card>

                <x-card title="Correct education">
                    @php
                        $correctionErrors = $errors->educationCorrection;
                        $currentEducation = $application->education;
                    @endphp

                    <p class="text-xs text-slate-500">Current value</p>
                    <p class="mt-0.5 text-sm font-bold text-ink">{{ $currentEducation ?? 'Not set' }}</p>

                    <form method="POST" action="{{ route('admin.applications.education', $application) }}" class="mt-4 space-y-4">
                        @csrf

                        <div>
                            <x-input-label for="correct_education" value="Corrected value" />
                            <select id="correct_education" name="education" required
                                    @if ($correctionErrors->has('education')) aria-invalid="true" aria-describedby="correct_education_error" @endif
                                    class="mt-1 block min-h-11 w-full rounded-xl border-slate-300 bg-white px-4 py-2 text-ink focus:border-brand focus:ring-brand">
                                @foreach (\App\Models\User::EDUCATION_OPTIONS as $option)
                                    {{-- the value it is not, unless the admin just chose otherwise --}}
                                    <option value="{{ $option }}" @selected(old('education', collect(\App\Models\User::EDUCATION_OPTIONS)->first(fn ($value) => $value !== $currentEducation)) === $option)>{{ $option }}</option>
                                @endforeach
                            </select>
                            <div id="correct_education_error">
                                <x-input-error class="mt-2" :messages="$correctionErrors->get('education')" />
                            </div>
                        </div>

                        <div>
                            <x-input-label for="correct_education_reason" value="Reason" />
                            <textarea id="correct_education_reason" name="reason" rows="3" required maxlength="1000"
                                      @if ($correctionErrors->has('reason')) aria-invalid="true" aria-describedby="correct_education_reason_error" @endif
                                      class="mt-1 block w-full rounded-xl border-slate-300 bg-white px-4 py-3 text-sm text-ink placeholder:text-slate-500 focus:border-brand focus:ring-brand"
                                      placeholder="For example: the applicant confirmed by phone that they have not graduated">{{ old('reason') }}</textarea>
                            <div id="correct_education_reason_error">
                                <x-input-error class="mt-2" :messages="$correctionErrors->get('reason')" />
                            </div>
                        </div>

                        <p class="text-xs leading-5 text-body">Saving runs the assessment again with the corrected value. The current decision is kept in the history below.</p>

                        <x-primary-button>Save and reassess</x-primary-button>
                    </form>

                    <div class="mt-6 border-t border-slate-200 pt-4">
                        <h3 class="text-xs font-bold text-ink">Correction history</h3>

                        @forelse ($application->educationCorrections->sortByDesc('id') as $correction)
                            <div class="mt-3 rounded-xl bg-surface p-3 text-sm">
                                <p class="font-bold text-ink">{{ $correction->old_education ?? 'Not set' }} &rarr; {{ $correction->new_education }}</p>
                                <p class="mt-0.5 text-xs text-slate-500">
                                    {{ $correction->created_at?->format('j M Y, H:i') }} by {{ $correction->admin?->name ?? 'a deleted admin' }}
                                </p>
                                <p class="mt-2 whitespace-pre-wrap break-words leading-6 text-body">{{ $correction->reason }}</p>
                                <dl class="mt-2 space-y-1 text-xs text-body">
                                    <div>
                                        <dt class="inline font-semibold text-ink">Before:</dt>
                                        @php
                                            $previousModel = collect([
                                                $correction->previous_outcome ? 'model ' . $correction->previous_outcome : null,
                                                $correction->previous_confidence_score !== null ? (float) $correction->previous_confidence_score . '% confidence' : null,
                                                $correction->previous_fraud_risk ? $correction->previous_fraud_risk . ' fraud risk' : null,
                                            ])->filter()->implode(', ');
                                        @endphp
                                        <dd class="inline">{{ $correction->previous_status ?? '-' }}{{ $previousModel !== '' ? ' (' . $previousModel . ')' : '' }}</dd>
                                    </div>
                                    <div>
                                        <dt class="inline font-semibold text-ink">After:</dt>
                                        <dd class="inline">{{ $correction->new_status ?? '-' }}</dd>
                                    </div>
                                </dl>
                                @if (filled($correction->previous_reasoning_notes))
                                    <details class="mt-2 text-xs">
                                        <summary class="cursor-pointer font-semibold text-brand">Previous reasoning notes</summary>
                                        <p class="mt-1 whitespace-pre-wrap leading-5 text-body">{{ $correction->previous_reasoning_notes }}</p>
                                    </details>
                                @endif
                            </div>
                        @empty
                            <p class="mt-1 text-sm text-body">No corrections yet.</p>
                        @endforelse
                    </div>
                </x-card>

                <div class="rounded-2xl border border-amber-200 bg-amber-100 p-5 text-amber-800">
                    <h2 class="text-sm font-bold">Self-reported credit score</h2>
                    <p class="mt-1 text-sm leading-6">The credit score band is entered by the applicant and is not verified. Check it with an external source before you finalise a decision.</p>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
