<x-app-layout>
    <div class="space-y-6">
        <x-page-header eyebrow="Dashboard / Applications" title="Applications"
                       subtitle="{{ $applications->count() }} {{ \Illuminate\Support\Str::plural('application', $applications->count()) }} in the queue.">
            <x-slot name="actions">
                <x-button-link variant="outline" href="{{ route('dashboard') }}" class="hidden md:inline-flex">Back to dashboard</x-button-link>
            </x-slot>
        </x-page-header>

        @include('admin.applications.partials.queue', [
            'rows' => $applications,
            'title' => 'All applications',
            'filters' => true,
            'search' => true,
        ])
    </div>
</x-app-layout>
