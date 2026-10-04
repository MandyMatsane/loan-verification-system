@props(['status'])

@php
    $key = strtolower(trim(str_replace('_', ' ', (string) $status)));

    [$label, $classes] = match ($key) {
        'approved' => ['Approved', 'bg-green-100 text-green-800'],
        'manual review' => ['Manual review', 'bg-amber-100 text-amber-800'],
        'rejected' => ['Rejected', 'bg-red-100 text-red-800'],
        'pending', '' => ['Pending', 'bg-slate-200 text-slate-600'],
        default => [ucfirst($key), 'bg-slate-200 text-slate-600'],
    };
@endphp

<span {{ $attributes->merge(['class' => 'inline-flex items-center whitespace-nowrap rounded-full px-2.5 py-1 text-xs font-bold ' . $classes]) }}>{{ $label }}</span>
