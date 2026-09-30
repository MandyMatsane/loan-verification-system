<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">All Applications</h2>
            <a href="{{ route('admin.dashboard') }}" class="text-sm font-medium text-indigo-600 hover:text-indigo-900">Back to dashboard</a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white shadow sm:rounded-lg overflow-hidden">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead>
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500">ID</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500">Applicant</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500">Amount</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500">Eligibility</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500">Confidence</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500">Fraud Risk</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @foreach ($applications as $application)
                            <tr>
                                <td class="px-6 py-4">{{ $application->id }}</td>
                                <td class="px-6 py-4">{{ $application->user?->name ?? 'Unknown' }}</td>
                                <td class="px-6 py-4">R{{ number_format($application->amount_requested, 2) }}</td>
                                <td class="px-6 py-4">{{ $application->status }}</td>
                                <td class="px-6 py-4">{{ $application->aiAssessment?->eligibility_outcome ?? '—' }}</td>
                                <td class="px-6 py-4">{{ $application->aiAssessment?->confidence_score ?? '—' }}</td>
                                <td class="px-6 py-4">{{ $application->aiAssessment?->fraud_risk_score ?? '—' }}</td>
                                <td class="px-6 py-4">
                                    <a href="{{ route('admin.applications.show', $application) }}" class="text-indigo-600">View</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>