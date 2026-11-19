@props(['title' => null, 'padding' => 'p-5 md:p-6'])

<div {{ $attributes->merge(['class' => 'rounded-2xl border border-slate-200 bg-white ' . $padding]) }}>
    @if ($title || isset($action))
        <div class="mb-4 flex items-center justify-between gap-3">
            @if ($title)
                <h2 class="text-base font-bold text-ink">{{ $title }}</h2>
            @endif
            {{ $action ?? '' }}
        </div>
    @endif

    {{ $slot }}
</div>
