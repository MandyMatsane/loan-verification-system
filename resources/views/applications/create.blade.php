<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            New Loan Application
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white p-6 shadow sm:rounded-lg">

                <form method="POST" action="{{ route('applications.store') }}">
                    @csrf

                    <div class="mb-4">
                        <label class="block font-medium text-sm text-gray-700">Amount Requested (R)</label>
                        <input type="number" name="amount_requested" step="0.01"
                               class="mt-1 block w-full border-gray-300 rounded-md shadow-sm"
                               value="{{ old('amount_requested') }}">
                        @error('amount_requested')
                            <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <button type="submit" class="bg-indigo-600 text-white px-4 py-2 rounded-md">
                        Submit Application
                    </button>
                </form>

            </div>
        </div>
    </div>
</x-app-layout>