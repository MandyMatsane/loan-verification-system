@props(['score' => null, 'label' => 'Model confidence', 'onDark' => false])

@php
    // confidence_score is already a percentage (0-100)
    $hasScore = $score !== null && $score !== '';
    $value = $hasScore ? max(0, min(100, (float) $score)) : 0;
@endphp

<div {{ $attributes }}>
    <div class="flex items-baseline justify-between gap-3 text-sm">
        <span class="{{ $onDark ? 'text-brand-mist' : 'text-body' }}">{{ $label }}</span>
        <span class="font-bold {{ $onDark ? 'text-white' : 'text-ink' }}">{{ $hasScore ? number_format($value, 1) . '%' : 'Awaiting assessment' }}</span>
    </div>

    <div class="relative mt-2">
        <div class="h-2 overflow-hidden rounded-full {{ $onDark ? 'bg-white/20' : 'bg-slate-200' }}"
             role="progressbar" aria-label="{{ $label }}" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $value }}">
            <div class="h-full rounded-full {{ $onDark ? 'bg-white' : 'bg-brand' }}" style="width: {{ $value }}%"></div>
        </div>
        <div class="absolute -top-1 h-4 w-0.5 {{ $onDark ? 'bg-brand-light' : 'bg-ink' }}" style="left: 70%" aria-hidden="true"></div>
    </div>

    <div class="relative mt-1 h-4">
        <span class="absolute -translate-x-1/2 whitespace-nowrap text-xs {{ $onDark ? 'text-brand-mist' : 'text-body' }}" style="left: 70%">70% review line</span>
    </div>
</div>
