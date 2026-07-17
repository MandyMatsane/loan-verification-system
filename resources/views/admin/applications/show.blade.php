<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Application #{{ $application->id }}</h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-2xl mx-auto sm:px-6 lg:px-8 space-y-6">

            <div class="bg-white p-6 shadow sm:rounded-lg">
                <p><strong>Applicant:</strong> {{ $application->user->name }} ({{ $application->user->email }})</p>
                <p><strong>Amount Requested:</strong> R{{ number_format($application->amount_requested, 2) }}</p>
                <p><strong>Status:</strong> {{ $application->status }}</p>
            </div>

            <div class="bg-white p-6 shadow sm:rounded-lg">
                <h3 class="font-semibold mb-2">AI Assessment</h3>
                @if ($application->aiAssessment)
                    <p><strong>Eligibility:</strong> {{ $application->aiAssessment->eligibility_outcome }}</p>
                    <p><strong>Fraud Risk:</strong> {{ $application->aiAssessment->fraud_risk_score }}</p>
                    <p><strong>Notes:</strong> {{ $application->aiAssessment->reasoning_notes }}</p>
                @else
                    <p class="text-gray-500">Not yet assessed.</p>
                @endif
            </div>

            <div class="bg-white p-6 shadow sm:rounded-lg">
                <h3 class="font-semibold mb-2">Documents</h3>
                @forelse ($application->documents as $document)
                    <div class="border-b py-2">
                        <p><strong>Type:</strong> {{ $document->type }}</p>
                        <p><strong>OCR Extracted:</strong> {{ $document->ocrResult->extracted_text ?? 'Not processed yet' }}</p>
                    </div>
                @empty
                    <p class="text-gray-500">No documents uploaded.</p>
                @endforelse
            </div>

        </div>
    </div>
</x-app-layout>