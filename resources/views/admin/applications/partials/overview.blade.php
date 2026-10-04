{{-- Admin "Operations overview": shared by /dashboard (admin) and /admin/dashboard --}}
@php
    $featureImportance = $featureImportance ?? [];

    // counted case-insensitively: the DB holds "Pending" and "pending"
    $statusCounts = $applications->countBy(fn ($application) => strtolower(trim(str_replace('_', ' ', (string) $application->status))));
@endphp

<div class="space-y-6">
    <x-page-header eyebrow="Dashboard" title="Operations overview" subtitle="Welcome back, {{ Auth::user()->name }}.">
        <x-slot name="actions">
            <form method="GET" action="{{ route('admin.applications.index') }}" class="relative hidden md:block" role="search">
                <label for="overview-search" class="sr-only">Search applications</label>
                <x-icon name="search" class="pointer-events-none absolute left-4 top-1/2 -translate-y-1/2 text-slate-500" />
                <x-text-input id="overview-search" type="search" name="q" placeholder="Search applications" class="block w-56 pl-11" />
            </form>
            <x-button-link variant="outline" href="{{ route('admin.applications.index') }}" class="hidden md:inline-flex">View all</x-button-link>
        </x-slot>
    </x-page-header>

    <div class="grid grid-cols-2 gap-3 md:gap-4 xl:grid-cols-4">
        <x-stat-card label="Total" :value="$applications->count()" />
        <x-stat-card label="Pending" :value="$statusCounts->get('pending', 0)" />
        <x-stat-card label="Manual review" :value="$statusCounts->get('manual review', 0)" tone="warning" />
        <x-stat-card label="Approved" :value="$statusCounts->get('approved', 0)" tone="success" />
    </div>

    <div class="grid gap-6 xl:grid-cols-3">
        <div class="min-w-0 xl:col-span-2">
            @include('admin.applications.partials.queue', [
                'rows' => $applications->take(5),
                'title' => 'Latest applications',
                'filters' => true,
                'filtersClass' => 'md:hidden',
                'actionUrl' => route('admin.applications.index'),
                'actionLabel' => 'View all',
            ])
        </div>

        <x-card title="What the model weighs" class="self-start">
            <p class="-mt-3 mb-4 text-sm text-body">Share of the model's decision, in %.</p>

            @if (empty($featureImportance))
                <p class="text-sm text-body">Feature importance is not available right now.</p>
            @else
                <x-feature-bars :items="$featureImportance" :limit="5" thin />
                <a href="{{ route('admin.feature-importance') }}" class="mt-3 inline-flex min-h-11 items-center text-sm font-bold text-brand hover:text-brand-dark">
                    See all {{ count($featureImportance) }} features
                </a>
            @endif
        </x-card>
    </div>
</div>
