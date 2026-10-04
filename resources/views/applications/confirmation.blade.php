<x-app-layout>
    @php
        $statusKey = strtolower(trim(str_replace('_', ' ', (string) $application->status)));
        $confidence = $application->aiAssessment?->confidence_score;

        $result = match ($statusKey) {
            'approved' => [
                'headline' => 'Application approved',
                'line' => 'Your result is ready.',
                'icon' => 'check',
                'circle' => 'bg-success text-slate-900',
                'banner' => 'border-green-200 bg-green-100 text-green-800',
            ],
            'manual review' => [
                'headline' => 'Application under review',
                'line' => 'A person will check your application. You do not need to do anything for now.',
                'icon' => 'clock',
                'circle' => 'bg-amber-100 text-amber-800',
                'banner' => 'border-amber-200 bg-amber-100 text-amber-800',
            ],
            'rejected' => [
                'headline' => 'Application not approved',
                'line' => 'This application did not meet the requirements this time.',
                'icon' => 'x',
                'circle' => 'bg-red-100 text-red-800',
                'banner' => 'border-red-200 bg-red-100 text-red-800',
            ],
            default => [
                'headline' => 'Application received',
                'line' => 'We are still assessing your documents. Check back soon for your result.',
                'icon' => 'clock',
                'circle' => 'bg-slate-200 text-slate-600',
                'banner' => 'border-slate-300 bg-slate-200 text-slate-600',
            ],
        };

        $documentLabels = ['id_document' => 'ID document', 'payslip' => 'Payslip', 'bank_statement' => 'Bank statement'];

        // stored as "{type}-{uuid}-{original name}": show the original name only
        $fileName = fn ($document) => preg_replace('/^' . preg_quote($document->type, '/') . '-[0-9a-f-]{36}-/i', '', basename($document->file_path));
    @endphp

    {{-- Phone: full-screen result --}}
    <div class="-mx-4 -mb-24 -mt-6 flex min-h-screen flex-col bg-brand-dark md:hidden">
        <div class="px-6 pb-8 pt-12 text-center">
            <span class="mx-auto flex h-20 w-20 items-center justify-center rounded-full {{ $result['circle'] }}">
                <x-icon :name="$result['icon']" class="h-10 w-10" />
            </span>
            <h1 class="mt-6 text-2xl font-extrabold text-white">{{ $result['headline'] }}</h1>
            <p class="mt-2 text-sm leading-6 text-brand-mist">{{ $result['line'] }}</p>
            <p class="mt-4 text-3xl font-extrabold tabular-nums text-white">R{{ number_format($application->amount_requested, 2) }}</p>
        </div>

        <div class="flex-1 rounded-t-3xl bg-white px-5 pb-28 pt-6">
            <dl class="space-y-4 text-sm">
                <div class="flex items-center justify-between gap-3">
                    <dt class="text-body">Reference</dt>
                    <dd class="font-bold text-ink">#{{ $application->id }}</dd>
                </div>
                <div class="flex items-center justify-between gap-3">
                    <dt class="text-body">Submitted</dt>
                    <dd class="font-bold text-ink">{{ $application->created_at?->format('j M Y') }}</dd>
                </div>
                <div class="flex items-center justify-between gap-3">
                    <dt class="text-body">Status</dt>
                    <dd><x-status-badge :status="$application->status" /></dd>
                </div>
            </dl>

            <x-confidence-bar class="mt-4" :score="$confidence" />

            <x-button-link class="mt-6 w-full" href="{{ route('dashboard') }}">Done</x-button-link>
            <a href="{{ route('applications.show', $application) }}" class="mt-1 flex min-h-11 items-center justify-center text-sm font-bold text-brand hover:text-brand-dark">View application</a>
        </div>
    </div>

    {{-- Laptop --}}
    <div class="hidden space-y-6 md:block">
        <x-page-header eyebrow="Dashboard / Application #{{ $application->id }}" title="Application submitted" :subtitle="session('success')" />

        <div class="flex items-start gap-3 rounded-2xl border px-5 py-4 {{ $result['banner'] }}" role="status">
            <x-icon :name="$result['icon']" class="mt-0.5 h-6 w-6" />
            <div>
                <p class="font-bold">{{ $result['headline'] }}</p>
                <p class="text-sm">{{ $result['line'] }}</p>
            </div>
        </div>

        <div class="grid gap-6 xl:grid-cols-2">
            <x-card title="Application details">
                <dl class="space-y-4 text-sm">
                    <div class="flex items-center justify-between gap-3">
                        <dt class="text-body">Reference</dt>
                        <dd class="font-bold text-ink">#{{ $application->id }}</dd>
                    </div>
                    <div class="flex items-center justify-between gap-3">
                        <dt class="text-body">Amount requested</dt>
                        <dd class="font-bold tabular-nums text-ink">R{{ number_format($application->amount_requested, 2) }}</dd>
                    </div>
                    <div class="flex items-center justify-between gap-3">
                        <dt class="text-body">Submitted</dt>
                        <dd class="font-bold text-ink">{{ $application->created_at?->format('j M Y') }}</dd>
                    </div>
                    <div class="flex items-center justify-between gap-3">
                        <dt class="text-body">Status</dt>
                        <dd><x-status-badge :status="$application->status" /></dd>
                    </div>
                </dl>

                <x-confidence-bar class="mt-4" :score="$confidence" />
            </x-card>

            <x-card title="Uploaded files" class="self-start">
                @if ($application->documents->isEmpty())
                    <p class="text-sm text-body">No files were uploaded.</p>
                @else
                    <ul class="space-y-3">
                        @foreach ($application->documents as $document)
                            <li class="flex items-center gap-3">
                                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-brand-tint text-brand">
                                    <x-icon name="document" />
                                </span>
                                <span class="min-w-0">
                                    <span class="block text-sm font-bold text-ink">{{ $documentLabels[$document->type] ?? ucfirst(str_replace('_', ' ', $document->type)) }}</span>
                                    <span class="block truncate text-xs text-body">{{ $fileName($document) }}</span>
                                </span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-card>
        </div>

        <div class="flex flex-wrap gap-3">
            <x-button-link href="{{ route('applications.show', $application) }}">View application</x-button-link>
            <x-button-link variant="outline" href="{{ route('dashboard') }}">Back to dashboard</x-button-link>
        </div>
    </div>
</x-app-layout>
