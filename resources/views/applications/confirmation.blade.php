<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Application Submitted
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-2xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @if (session('success'))
                <div class="bg-green-100 border border-green-200 text-green-700 px-4 py-3 rounded-md">
                    {{ session('success') }}
                </div>
            @endif

            <div class="bg-white p-6 shadow sm:rounded-lg">
                <p class="text-sm uppercase tracking-wide text-gray-500">Application reference</p>
                <h3 class="mt-2 text-3xl font-bold text-gray-900">#{{ $application->id }}</h3>

                <div class="mt-6 space-y-3 text-gray-700">
                    <p><strong>Amount requested:</strong> R{{ number_format($application->amount_requested, 2) }}</p>
                    <p><strong>Status:</strong> {{ $application->status }}</p>
                </div>
            </div>

            <div class="bg-white p-6 shadow sm:rounded-lg">
                <h3 class="text-lg font-semibold text-gray-900">Uploaded files</h3>

                <ul class="mt-4 space-y-2">
                    @foreach ($application->documents as $document)
                        <li class="flex items-center justify-between rounded-md border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-700">
                            <span>{{ ucfirst(str_replace('_', ' ', $document->type)) }}</span>
                            <span>{{ basename($document->file_path) }}</span>
                        </li>
                    @endforeach
                </ul>
            </div>

            <div class="flex gap-3">
                <a href="{{ route('applications.show', $application) }}" class="inline-flex items-center rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                    View application
                </a>
                <a href="{{ route('dashboard') }}" class="inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                    Back to dashboard
                </a>
            </div>
        </div>
    </div>
</x-app-layout>
