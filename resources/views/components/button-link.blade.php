@props(['variant' => 'primary'])

@php
    $classes = match ($variant) {
        'outline' => 'border-brand bg-white text-brand hover:bg-brand-tint focus-visible:ring-brand',
        'white' => 'border-transparent bg-white text-brand-dark hover:bg-brand-tint focus-visible:ring-white focus-visible:ring-offset-brand-dark',
        default => 'border-transparent bg-brand text-white hover:bg-brand-dark focus-visible:ring-brand',
    };
@endphp

<a {{ $attributes->merge(['class' => 'inline-flex min-h-12 items-center justify-center gap-2 rounded-xl border px-5 py-2 text-sm font-bold transition focus:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 ' . $classes]) }}>
    {{ $slot }}
</a>
