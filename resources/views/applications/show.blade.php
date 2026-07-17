<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Application #{{ $application->id }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-xl mx-auto sm:px-6 lg:px-8 space-y-6">

            @if (session('success'))
                <div class="bg-green-100 text-green-700 p-3 rounded-md">
                    {{ session('success') }}
                </div>
            @endif

            <div class="bg-white p-6 shadow sm:rounded-lg">
                <p><strong>Amount Requested:</strong> R{{ number_format($application->amount_requested, 2) }}</p>
                <p><strong>Status:</strong> {{ $application->status }}</p>
            </div>

            <div class="bg-white p-6 shadow sm:rounded-lg">
                <h3 class="font-semibold mb-4">Upload a Document</h3>

                <form method="POST" action="{{ route('applications.documents.store', $application) }}" enctype="multipart/form-data">
                    @csrf

                    <div class="mb-4">
                        <label class="block font-medium text-sm text-gray-700">Document Type</label>
                        <select name="type" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
                            <option value="id">ID Document</option>
                            <option value="payslip">Payslip</option>
                            <option value="bank_statement">Bank Statement</option>
                        </select>
                    </div>

                    <div class="mb-4">
                        <label class="block font-medium text-sm text-gray-700">File (PDF, JPG, PNG — max 5MB)</label>
                        <input type="file" name="file" class="mt-1 block w-full">
                        @error('file')
                            <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <button type="submit" class="bg-indigo-600 text-white px-4 py-2 rounded-md">
                        Upload
                    </button>
                </form>
            </div>

            <div class="bg-white p-6 shadow sm:rounded-lg">
                <h3 class="font-semibold mb-4">Uploaded Documents</h3>
                @forelse ($application->documents as $document)
                    <p>{{ $document->type }} — uploaded {{ $document->uploaded_at }}</p>
                @empty
                    <p class="text-gray-500">No documents uploaded yet.</p>
                @endforelse
            </div>

        </div>
    </div>
</x-app-layout>