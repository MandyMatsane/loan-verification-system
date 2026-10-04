@props(['items' => [], 'limit' => null, 'thin' => false])

@php
    $names = [
        'cibil_score' => 'CIBIL score',
        'loan_term' => 'Loan term',
        'loan_amount' => 'Loan amount',
        'income_annum' => 'Annual income',
        'no_of_dependents' => 'Dependents',
        'education' => 'Education',
        'self_employed' => 'Self-employed',
        'residential_assets_value' => 'Residential assets',
        'commercial_assets_value' => 'Commercial assets',
        'luxury_assets_value' => 'Luxury assets',
        'bank_asset_value' => 'Bank assets',
    ];

    // importance is already a percentage, so it is also the bar width
    $rows = collect($items)->sortByDesc('importance')->values();

    if ($limit) {
        $rows = $rows->take($limit);
    }
@endphp

<ul {{ $attributes->merge(['class' => $thin ? 'space-y-3' : 'space-y-4']) }}>
    @foreach ($rows as $row)
        @php
            $key = strtolower(trim((string) $row['feature']));
            $name = $names[$key] ?? ucfirst(str_replace('_', ' ', $key));
            $value = (float) $row['importance'];
        @endphp
        <li>
            <div class="flex items-baseline justify-between gap-3 text-sm">
                <span class="font-semibold text-ink">{{ $name }}</span>
                <span class="tabular-nums text-body">{{ number_format($value, 1) }}%</span>
            </div>
            <div class="mt-1.5 overflow-hidden rounded-full bg-slate-200 {{ $thin ? 'h-1.5' : 'h-3' }}">
                <div class="h-full rounded-full bg-brand" style="width: {{ max(0, min(100, round($value, 2))) }}%"></div>
            </div>
        </li>
    @endforeach
</ul>
