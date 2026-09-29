<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            New Loan Application
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-2xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white p-6 shadow sm:rounded-lg">
                <form method="POST" action="{{ route('applications.store') }}" enctype="multipart/form-data">
                    @csrf

                    <div class="mb-6">
                        <label class="block font-medium text-sm text-gray-700">Amount Requested (R)</label>
                        <input type="number" name="amount_requested" step="0.01" min="0.01"
                               class="mt-1 block w-full border-gray-300 rounded-md shadow-sm"
                               value="{{ old('amount_requested') }}">
                        @error('amount_requested')
                            <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="space-y-5">
                        <div>
                            <label class="block font-medium text-sm text-gray-700">Number of Dependents</label>
                            <input type="number" name="no_of_dependents" min="0"
                                   class="mt-1 block w-full border-gray-300 rounded-md shadow-sm"
                                   value="{{ old('no_of_dependents') }}">
                            @error('no_of_dependents')
                                <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label class="block font-medium text-sm text-gray-700">Education</label>
                            <select name="education" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
                                <option value="">Select education</option>
                                <option value="Graduate" {{ old('education') == 'Graduate' ? 'selected' : '' }}>Graduate</option>
                                <option value="Not Graduate" {{ old('education') == 'Not Graduate' ? 'selected' : '' }}>Not Graduate</option>
                            </select>
                            @error('education')
                                <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label class="block font-medium text-sm text-gray-700">Loan term (months)</label>
                            <input type="number" name="loan_term" min="1"
                                   class="mt-1 block w-full border-gray-300 rounded-md shadow-sm"
                                   value="{{ old('loan_term') }}">
                            @error('loan_term')
                                <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label class="block font-medium text-sm text-gray-700">Credit score band</label>
                            <select name="cibil_score_band" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
                                <option value="">Select credit score band</option>
                                <option value="Poor" {{ old('cibil_score_band') == 'Poor' ? 'selected' : '' }}>Poor (300-549)</option>
                                <option value="Fair" {{ old('cibil_score_band') == 'Fair' ? 'selected' : '' }}>Fair (550-649)</option>
                                <option value="Good" {{ old('cibil_score_band') == 'Good' ? 'selected' : '' }}>Good (650-749)</option>
                                <option value="Excellent" {{ old('cibil_score_band') == 'Excellent' ? 'selected' : '' }}>Excellent (750-900)</option>
                            </select>
                            @error('cibil_score_band')
                                <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label class="block font-medium text-sm text-gray-700">Residential asset value (Rand)</label>
                            <input type="number" name="residential_assets_value" step="0.01" min="0" value="{{ old('residential_assets_value', 0) }}"
                                   class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
                            @error('residential_assets_value')
                                <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label class="block font-medium text-sm text-gray-700">Commercial asset value (Rand)</label>
                            <input type="number" name="commercial_assets_value" step="0.01" min="0" value="{{ old('commercial_assets_value', 0) }}"
                                   class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
                            @error('commercial_assets_value')
                                <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label class="block font-medium text-sm text-gray-700">Luxury asset value (Rand)</label>
                            <input type="number" name="luxury_assets_value" step="0.01" min="0" value="{{ old('luxury_assets_value', 0) }}"
                                   class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
                            @error('luxury_assets_value')
                                <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label class="block font-medium text-sm text-gray-700">Bank asset value (Rand)</label>
                            <input type="number" name="bank_asset_value" step="0.01" min="0" value="{{ old('bank_asset_value', 0) }}"
                                   class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
                            @error('bank_asset_value')
                                <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="id_document" class="block font-medium text-sm text-gray-700">ID Document</label>
                            <input id="id_document" type="file" name="id_document" accept=".pdf,.jpg,.jpeg,.png" class="mt-1 block w-full text-sm text-gray-700">
                            @error('id_document')
                                <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="payslip" class="block font-medium text-sm text-gray-700">Payslip</label>
                            <input id="payslip" type="file" name="payslip" accept=".pdf,.jpg,.jpeg,.png" class="mt-1 block w-full text-sm text-gray-700">
                            @error('payslip')
                                <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="bank_statement" class="block font-medium text-sm text-gray-700">Bank Statement</label>
                            <input id="bank_statement" type="file" name="bank_statement" accept=".pdf,.jpg,.jpeg,.png" class="mt-1 block w-full text-sm text-gray-700">
                            @error('bank_statement')
                                <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <div class="mt-6">
                        <button type="submit" class="bg-indigo-600 text-white px-4 py-2 rounded-md hover:bg-indigo-700">
                            Submit Application
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>