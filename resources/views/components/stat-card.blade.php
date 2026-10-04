@props(['label', 'value', 'tone' => 'default'])

@php
    $valueClass = match ($tone) {
        'success' => 'text-green-800',
        'warning' => 'text-amber-800',
        'danger' => 'text-red-800',
        default => 'text-ink',
    };

    $iconClass = match ($tone) {
        'success' => 'bg-green-100 text-green-800',
        'warning' => 'bg-amber-100 text-amber-800',
        'danger' => 'bg-red-100 text-red-800',
        default => 'bg-brand-tint text-brand',
    };
@endphp

<div {{ $attributes->merge(['class' => 'flex items-center gap-4 rounded-2xl border border-slate-200 bg-white p-4 md:p-5']) }}>
    @isset($icon)
        <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl {{ $iconClass }}">
            {{ $icon }}
        </div>
    @endisset

    <div class="min-w-0">
        <p class="text-sm text-body">{{ $label }}</p>
        <p class="text-2xl font-extrabold {{ $valueClass }}">{{ $value }}</p>
    </div>
</div>
