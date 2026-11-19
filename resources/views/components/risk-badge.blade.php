@props(['level', 'suffix' => false])

@php
    $key = strtolower(trim((string) $level));

    $classes = match ($key) {
        'low' => 'bg-green-100 text-green-800',
        'medium' => 'bg-amber-100 text-amber-800',
        'high' => 'bg-red-100 text-red-800',
        default => 'bg-slate-200 text-slate-600',
    };
@endphp

@if ($key === '')
    <span {{ $attributes->merge(['class' => 'text-body']) }}>-</span>
@else
    <span {{ $attributes->merge(['class' => 'inline-flex items-center whitespace-nowrap rounded-full px-2.5 py-1 text-xs font-bold ' . $classes]) }}>{{ ucfirst($key) }}{{ $suffix ? ' risk' : '' }}</span>
@endif
