<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Feature Importance</h2>
            <a href="{{ route('admin.dashboard') }}" class="text-sm font-medium text-indigo-600 hover:text-indigo-900">Back to dashboard</a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white shadow sm:rounded-lg p-6">
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
                                <td class="px-4 py-3">{{ number_format((float) $item['importance'] * 100, 2) }}%</td>
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
