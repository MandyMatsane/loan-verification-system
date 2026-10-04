{{--
    Application queue: table from md up, stacked cards below md.
    $rows (collection), $title, optional $filters, $filtersClass, $search, $actionUrl, $actionLabel
--}}
@php
    $rows = $rows->values();
    $filters = $filters ?? false;
    $filtersClass = $filtersClass ?? '';
    $search = $search ?? false;

    $meta = $rows->map(fn ($application) => [
        'status' => strtolower(trim(str_replace('_', ' ', (string) $application->status))),
        'search' => strtolower(($application->user?->name ?? 'unknown') . ' #' . $application->id . ' ' . $application->amount_requested . ' ' . $application->status),
    ]);
@endphp

<section
    x-data="{
        filter: 'all',
        q: new URLSearchParams(window.location.search).get('q') || '',
        rows: {{ Js::from($meta) }},
        ok(row) {
            return (this.filter === 'all' || row.status === this.filter)
                && (this.q.trim() === '' || row.search.includes(this.q.trim().toLowerCase()));
        },
        get none() { return this.rows.length > 0 && ! this.rows.some((row) => this.ok(row)); },
    }"
    class="md:rounded-2xl md:border md:border-slate-200 md:bg-white md:p-6">

    <div class="mb-4 flex items-center justify-between gap-3">
        <h2 class="text-base font-bold text-ink">{{ $title }}</h2>
        @isset($actionUrl)
            <a href="{{ $actionUrl }}" class="inline-flex min-h-11 items-center text-sm font-bold text-brand hover:text-brand-dark">{{ $actionLabel ?? 'View all' }}</a>
        @endisset
    </div>

    @if ($search)
        <div class="relative mb-4">
            <label for="queue-search" class="sr-only">Search applications</label>
            <x-icon name="search" class="pointer-events-none absolute left-4 top-1/2 -translate-y-1/2 text-slate-500" />
            <x-text-input id="queue-search" type="search" x-model="q" placeholder="Search applications" class="block w-full pl-11" />
        </div>
    @endif

    @if ($filters)
        <div class="mb-4 flex flex-wrap gap-2 {{ $filtersClass }}" role="group" aria-label="Filter by status">
            @foreach (['all' => 'All', 'pending' => 'Pending', 'manual review' => 'Manual review'] as $key => $label)
                <button type="button" @click="filter = '{{ $key }}'" :aria-pressed="filter === '{{ $key }}'"
                        :class="filter === '{{ $key }}' ? 'border-brand bg-brand text-white' : 'border-slate-300 bg-white text-body hover:border-brand hover:text-brand'"
                        class="inline-flex min-h-11 items-center rounded-full border px-4 text-sm font-bold transition focus:outline-none focus-visible:ring-2 focus-visible:ring-brand focus-visible:ring-offset-2">
                    {{ $label }}
                </button>
            @endforeach
        </div>
    @endif

    @if ($rows->isEmpty())
        <p class="rounded-2xl border border-slate-200 bg-white p-5 text-sm text-body md:border-0 md:p-0">No applications have been submitted yet.</p>
    @else
        {{-- md and up: table --}}
        <div class="hidden overflow-x-auto md:block">
            <table class="min-w-full text-left text-sm">
                <thead>
                    <tr class="border-b border-slate-200 text-xs font-semibold text-slate-500">
                        <th scope="col" class="py-2 pr-4">Applicant</th>
                        <th scope="col" class="px-3 py-2">Amount</th>
                        <th scope="col" class="px-3 py-2">Status</th>
                        <th scope="col" class="px-3 py-2">Confidence</th>
                        <th scope="col" class="px-3 py-2">Fraud risk</th>
                        <th scope="col" class="py-2 pl-4"><span class="sr-only">Action</span></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200">
                    @foreach ($rows as $i => $application)
                        @php $confidence = $application->aiAssessment?->confidence_score; @endphp
                        <tr x-show="ok(rows[{{ $i }}])">
                            <td class="whitespace-nowrap py-2 pr-4 font-semibold text-ink">{{ $application->user?->name ?? 'Unknown' }}</td>
                            <td class="whitespace-nowrap px-3 py-2 tabular-nums text-ink">R{{ number_format($application->amount_requested, 2) }}</td>
                            <td class="px-3 py-2"><x-status-badge :status="$application->status" /></td>
                            <td class="whitespace-nowrap px-3 py-2 tabular-nums text-body">{{ $confidence !== null ? number_format((float) $confidence, 1) . '%' : '-' }}</td>
                            <td class="px-3 py-2"><x-risk-badge :level="$application->aiAssessment?->fraud_risk_score" /></td>
                            <td class="py-2 pl-2 text-right">
                                <a href="{{ route('admin.applications.show', $application) }}" class="inline-flex min-h-11 items-center pl-2 font-bold text-brand hover:text-brand-dark">
                                    View<span class="sr-only"> application #{{ $application->id }}</span>
                                </a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{-- below md: one card per application --}}
        <ul class="space-y-3 md:hidden">
            @foreach ($rows as $i => $application)
                @php $confidence = $application->aiAssessment?->confidence_score; @endphp
                <li x-show="ok(rows[{{ $i }}])">
                    <a href="{{ route('admin.applications.show', $application) }}" class="block rounded-2xl border border-slate-200 bg-white p-4 transition hover:border-brand focus:outline-none focus-visible:ring-2 focus-visible:ring-brand">
                        <div class="flex items-start justify-between gap-3">
                            <p class="min-w-0 truncate font-bold text-ink">{{ $application->user?->name ?? 'Unknown' }}</p>
                            <x-status-badge :status="$application->status" />
                        </div>
                        <div class="mt-2 flex items-center justify-between gap-3">
                            <p class="text-sm text-body">
                                R{{ number_format($application->amount_requested, 2) }} -
                                {{ $confidence !== null ? number_format((float) $confidence, 1) . '% confidence' : 'awaiting assessment' }}
                            </p>
                            @if ($application->aiAssessment?->fraud_risk_score)
                                <x-risk-badge :level="$application->aiAssessment->fraud_risk_score" suffix />
                            @endif
                        </div>
                    </a>
                </li>
            @endforeach
        </ul>

        <p x-show="none" x-cloak class="rounded-2xl border border-slate-200 bg-white p-5 text-sm text-body md:border-0 md:px-0">No applications match this filter.</p>
    @endif
</section>
