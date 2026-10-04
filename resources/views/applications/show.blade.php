<x-app-layout>
    @php
        $money = fn ($value) => 'R' . number_format((float) ($value ?? 0), 2);

        $documentLabels = ['id_document' => 'ID document', 'payslip' => 'Payslip', 'bank_statement' => 'Bank statement'];

        $details = [
            'Amount requested' => $money($application->amount_requested),
            'Loan term' => $application->loan_term !== null ? $application->loan_term . ' months' : '-',
            'Dependents' => $application->no_of_dependents ?? '-',
            'Education' => $application->education ?? '-',
            'Credit score band' => $application->cibil_score_band ?? '-',
            'Residential assets' => $money($application->residential_assets_value),
            'Commercial assets' => $money($application->commercial_assets_value),
            'Luxury assets' => $money($application->luxury_assets_value),
            'Bank assets' => $money($application->bank_asset_value),
            'Submitted' => $application->created_at?->format('j M Y') ?? '-',
        ];

        // stored as "{type}-{uuid}-{original name}": show the original name only
        $fileName = fn ($document) => preg_replace('/^' . preg_quote($document->type, '/') . '-[0-9a-f-]{36}-/i', '', basename($document->file_path));
    @endphp

    <div class="space-y-6">
        <x-page-header eyebrow="Dashboard / Application #{{ $application->id }}" title="Application #{{ $application->id }}">
            <x-slot name="badge">
                <x-status-badge :status="$application->status" />
            </x-slot>
            <x-slot name="actions">
                <x-button-link variant="outline" href="{{ route('dashboard') }}">
                    <x-icon name="chevron-left" />
                    Back to dashboard
                </x-button-link>
            </x-slot>
        </x-page-header>

        @if (session('success'))
            <div class="rounded-2xl border border-green-200 bg-green-100 px-5 py-4 text-sm font-semibold text-green-800" role="status">
                {{ session('success') }}
            </div>
        @endif

        <div class="grid gap-6 xl:grid-cols-3">
            <div class="min-w-0 space-y-6 xl:col-span-2">
                <x-card title="Loan details">
                    <dl class="grid grid-cols-2 gap-x-4 gap-y-5 md:grid-cols-3">
                        @foreach ($details as $label => $value)
                            <div>
                                <dt class="text-xs text-slate-500">{{ $label }}</dt>
                                <dd class="mt-0.5 text-sm font-bold text-ink">{{ $value }}</dd>
                            </div>
                        @endforeach
                    </dl>
                </x-card>

                <x-card title="Uploaded documents">
                    @forelse ($application->documents as $document)
                        <div class="flex items-center gap-3 {{ $loop->first ? '' : 'mt-3' }}">
                            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-brand-tint text-brand">
                                <x-icon name="document" />
                            </span>
                            <span class="min-w-0">
                                <span class="block text-sm font-bold text-ink">{{ $documentLabels[$document->type] ?? ucfirst(str_replace('_', ' ', $document->type)) }}</span>
                                <span class="block truncate text-xs text-body">{{ $fileName($document) }} - uploaded {{ \Illuminate\Support\Carbon::parse($document->uploaded_at)->format('j M Y') }}</span>
                            </span>
                        </div>
                    @empty
                        <p class="text-sm text-body">No documents uploaded yet.</p>
                    @endforelse
                </x-card>
            </div>

            <div class="space-y-6">
                <x-card title="Result">
                    <x-status-badge :status="$application->status" />
                    <x-confidence-bar class="mt-4" :score="$application->aiAssessment?->confidence_score" />
                </x-card>

                <x-card title="Upload a document">
                    <form method="POST" action="{{ route('applications.documents.store', $application) }}" enctype="multipart/form-data" class="space-y-4">
                        @csrf

                        <div>
                            <x-input-label for="type" value="Document type" />
                            <select id="type" name="type" class="mt-2 block min-h-12 w-full rounded-xl border-slate-300 bg-white px-4 text-ink focus:border-brand focus:ring-brand">
                                <option value="id_document" {{ old('type') == 'id_document' ? 'selected' : '' }}>ID document</option>
                                <option value="payslip" {{ old('type') == 'payslip' ? 'selected' : '' }}>Payslip</option>
                                <option value="bank_statement" {{ old('type') == 'bank_statement' ? 'selected' : '' }}>Bank statement</option>
                            </select>
                            <x-input-error :messages="$errors->get('type')" class="mt-2" />
                        </div>

                        <div>
                            <x-input-label for="file" value="File (JPG or PNG, max 5MB)" />
                            <input id="file" type="file" name="file" accept=".jpg,.jpeg,.png"
                                   class="mt-2 block w-full rounded-xl border-2 border-dashed border-brand p-2 text-sm text-body file:mr-3 file:min-h-11 file:cursor-pointer file:rounded-xl file:border-0 file:bg-brand-tint file:px-4 file:text-sm file:font-bold file:text-brand focus:outline-none focus-visible:ring-2 focus-visible:ring-brand focus-visible:ring-offset-2">
                            <x-input-error :messages="$errors->get('file')" class="mt-2" />
                        </div>

                        <x-primary-button class="w-full">Upload</x-primary-button>
                    </form>
                </x-card>
            </div>
        </div>
    </div>
</x-app-layout>
