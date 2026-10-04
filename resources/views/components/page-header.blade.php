@props(['title', 'eyebrow' => null, 'subtitle' => null])

<div {{ $attributes->merge(['class' => 'flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between']) }}>
    <div class="min-w-0">
        @if ($eyebrow)
            <p class="text-sm text-slate-500">{{ $eyebrow }}</p>
        @endif
        <div class="flex flex-wrap items-center gap-3">
            <h1 class="text-2xl font-extrabold text-ink">{{ $title }}</h1>
            {{ $badge ?? '' }}
        </div>
        @if ($subtitle)
            <p class="mt-1 text-sm text-body">{{ $subtitle }}</p>
        @endif
    </div>

    @isset($actions)
        <div class="flex flex-wrap items-center gap-3">
            {{ $actions }}
        </div>
    @endisset
</div>
