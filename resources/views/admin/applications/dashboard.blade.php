<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Admin Dashboard</h2>
            <a href="{{ route('admin.applications.index') }}" class="text-sm font-medium text-indigo-600 hover:text-indigo-900">View all applications</a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-8">
            <div class="bg-white shadow sm:rounded-lg p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-gray-900">Loan Applications</h3>
                    <a href="{{ route('admin.feature-importance') }}" class="text-indigo-600 hover:text-indigo-900">Feature Importance</a>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead>
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500">Applicant</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500">Amount</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500">Status</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500">Eligibility</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500">Confidence</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500">Fraud Risk</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($applications as $application)
                                <tr>
                                    <td class="px-4 py-3">{{ $application->user?->name ?? 'Unknown' }}</td>
                                    <td class="px-4 py-3">R{{ number_format($application->amount_requested, 2) }}</td>
                                    <td class="px-4 py-3">{{ $application->status }}</td>
                                    <td class="px-4 py-3">{{ $application->aiAssessment?->eligibility_outcome ?? '—' }}</td>
                                    <td class="px-4 py-3">{{ $application->aiAssessment?->confidence_score ?? '—' }}</td>
                                    <td class="px-4 py-3">{{ $application->aiAssessment?->fraud_risk_score ?? '—' }}</td>
                                    <td class="px-4 py-3">
                                        <a href="{{ route('admin.applications.show', $application) }}" class="text-indigo-600">View</a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="px-4 py-3 text-gray-500">No loan applications found.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="bg-white shadow sm:rounded-lg p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Feature Importance</h3>

                <table class="min-w-full divide-y divide-gray-200">
                    <thead>
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500">Feature</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500">Importance</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($featureImportance as $item)
                            <tr>
                                <td class="px-4 py-3">{{ $item['feature'] }}</td>
                                <td class="px-4 py-3">{{ number_format((float) $item['importance'], 2) }}%</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="2" class="px-4 py-3 text-gray-500">No feature importance data available.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
