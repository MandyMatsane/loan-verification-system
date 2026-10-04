<x-app-layout>
    <div class="space-y-6">
        <x-page-header eyebrow="Dashboard / Feature importance" title="Feature importance" subtitle="What the model weighs when it assesses an application.">
            <x-slot name="actions">
                <x-button-link variant="outline" href="{{ route('dashboard') }}" class="hidden md:inline-flex">Back to dashboard</x-button-link>
            </x-slot>
        </x-page-header>

        <x-card title="Share of the model's decision" class="max-w-3xl">
            @if (empty($featureImportance))
                <p class="text-sm text-body">No feature importance data available.</p>
            @else
                <p class="-mt-3 mb-5 text-sm text-body">In %, sorted from most to least important. {{ count($featureImportance) }} features.</p>
                <x-feature-bars :items="$featureImportance" />
            @endif
        </x-card>
    </div>
</x-app-layout>
