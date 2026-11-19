<x-app-layout>
    <div class="space-y-6">
        <x-page-header eyebrow="Dashboard / Applications" title="Applications"
                       subtitle="{{ $applications->count() }} {{ \Illuminate\Support\Str::plural('application', $applications->count()) }} in the queue.">
            <x-slot name="actions">
                @if ($applications->contains(fn ($application) => $application->isRetryable()))
                    <form method="POST" action="{{ route('admin.applications.retry-pending') }}">
                        @csrf
                        <x-primary-button>Retry all pending</x-primary-button>
                    </form>
                @endif
                <x-button-link variant="outline" href="{{ route('dashboard') }}" class="hidden md:inline-flex">Back to dashboard</x-button-link>
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

        @include('admin.applications.partials.queue', [
            'rows' => $applications,
            'title' => 'All applications',
            'filters' => true,
            'search' => true,
        ])
    </div>
</x-app-layout>
