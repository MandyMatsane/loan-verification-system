<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Application #{{ $application->id }}</h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6">

            <div class="bg-white p-6 shadow sm:rounded-lg">
                <h3 class="font-semibold text-lg mb-4">Applicant & Loan Summary</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <p><strong>Applicant:</strong> {{ $application->user?->name ?? 'Unknown' }} ({{ $application->user?->email ?? 'No email' }})</p>
                    <p><strong>Status:</strong> {{ $application->status }}</p>
                    <p><strong>Amount Requested:</strong> R{{ number_format($application->amount_requested, 2) }}</p>
                    <p><strong>No. of Dependents:</strong> {{ $application->no_of_dependents ?? '—' }}</p>
                    <p><strong>Education:</strong> {{ $application->education ?? '—' }}</p>
                    <p><strong>Loan Term:</strong> {{ $application->loan_term ?? '—' }}</p>
                    <p><strong>CIBIL Score Band:</strong> {{ $application->cibil_score_band ?? '—' }}</p>
                    <p><strong>Residential Assets Value:</strong> R{{ number_format((float) ($application->residential_assets_value ?? 0), 2) }}</p>
                    <p><strong>Commercial Assets Value:</strong> R{{ number_format((float) ($application->commercial_assets_value ?? 0), 2) }}</p>
                    <p><strong>Luxury Assets Value:</strong> R{{ number_format((float) ($application->luxury_assets_value ?? 0), 2) }}</p>
                    <p><strong>Bank Asset Value:</strong> R{{ number_format((float) ($application->bank_asset_value ?? 0), 2) }}</p>
                </div>
            </div>

            <div class="bg-white p-6 shadow sm:rounded-lg">
                <h3 class="font-semibold mb-2">AI Assessment</h3>
                @if ($application->aiAssessment)
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <p><strong>Eligibility Outcome:</strong> {{ $application->aiAssessment->eligibility_outcome }}</p>
                        <p><strong>Confidence Score:</strong> {{ $application->aiAssessment->confidence_score }}</p>
                        <p><strong>Fraud Risk Score:</strong> {{ $application->aiAssessment->fraud_risk_score }}</p>
                    </div>
                    <div class="mt-4">
                        <p><strong>Reasoning Notes:</strong></p>
                        <div class="mt-2 rounded-md bg-gray-50 p-4 text-gray-700 whitespace-pre-wrap">
                            {{ $application->aiAssessment->reasoning_notes ?? 'No reasoning notes provided.' }}
                        </div>
                    </div>
                @else
                    <p class="text-gray-500">Not yet assessed.</p>
                @endif
            </div>

            <div class="bg-white p-6 shadow sm:rounded-lg">
                <h3 class="font-semibold mb-2">Documents</h3>
                @forelse ($application->documents as $document)
                    <div class="border-b py-3">
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
