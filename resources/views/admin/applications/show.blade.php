<x-app-layout>
    @php
        $assessment = $application->aiAssessment;
        $money = fn ($value) => 'R' . number_format((float) ($value ?? 0), 2);

        $outcomeClass = match (strtolower(trim((string) $assessment?->eligibility_outcome))) {
            'approved' => 'text-green-800',
            'rejected' => 'text-red-800',
            default => 'text-amber-800',
        };

        $documentLabels = ['id_document' => 'ID document', 'payslip' => 'Payslip', 'bank_statement' => 'Bank statement'];

        $details = [
            'Amount requested' => $money($application->amount_requested),
            'Loan term' => $application->loan_term !== null ? $application->loan_term . ' months' : '-',
            'Dependents' => $application->no_of_dependents ?? '-',
            'Education' => $application->education ?? '-',
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
            </x-slot>
            <x-slot name="actions">
                <x-button-link variant="outline" href="{{ route('admin.applications.index') }}">
                    <x-icon name="chevron-left" />
                    Back to queue
                </x-button-link>
            </x-slot>
        </x-page-header>

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
                            <div>
                                <dt class="text-xs text-slate-500">{{ $label }}</dt>
                                <dd class="mt-0.5 text-sm font-bold text-ink">{{ $value }}</dd>
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
                                <h3 class="text-sm font-bold text-ink">{{ $documentLabels[$document->type] ?? ucfirst(str_replace('_', ' ', $document->type)) }}</h3>
                            </div>
                            <pre class="mt-2 max-h-48 overflow-auto whitespace-pre-wrap break-words rounded-xl bg-slate-100 p-3 font-mono text-xs leading-5 text-slate-700" tabindex="0">{{ $document->ocrResult->extracted_text ?? 'Not processed yet' }}</pre>
                        </div>
                    @empty
                        <p class="text-sm text-body">No documents uploaded.</p>
                    @endforelse
                </x-card>
            </div>

            <div class="space-y-6">
                <x-card title="AI assessment">
                    @if ($assessment)
                        <p class="text-2xl font-extrabold {{ $outcomeClass }}">{{ $assessment->eligibility_outcome }}</p>

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

                <div class="rounded-2xl border border-amber-200 bg-amber-100 p-5 text-amber-800">
                    <h2 class="text-sm font-bold">Self-reported credit score</h2>
                    <p class="mt-1 text-sm leading-6">The credit score band is entered by the applicant and is not verified. Check it with an external source before you finalise a decision.</p>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
