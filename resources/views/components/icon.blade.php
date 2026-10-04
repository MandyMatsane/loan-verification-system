@props(['name'])

<svg {{ $attributes->merge(['class' => 'h-5 w-5 shrink-0']) }} xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
    @switch($name)
        @case('home')
            <path d="M3 11.5 12 4l9 7.5" /><path d="M5 10v10h5v-6h4v6h5V10" />
            @break
        @case('plus')
            <path d="M12 5v14M5 12h14" />
            @break
        @case('plus-circle')
            <circle cx="12" cy="12" r="9" /><path d="M12 8v8M8 12h8" />
            @break
        @case('list')
            <path d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01" />
            @break
        @case('chart')
            <path d="M2 20h20M5 20V10M11 20V4M17 20v-7" />
            @break
        @case('user')
            <circle cx="12" cy="8" r="4" /><path d="M4 21a8 8 0 0 1 16 0" />
            @break
        @case('logout')
            <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9" />
            @break
        @case('moon')
            <path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8z" />
            @break
        @case('check')
            <path d="m5 13 4 4L19 7" />
            @break
        @case('upload')
            <path d="M12 16V4M8 8l4-4 4 4M4 20h16" />
            @break
        @case('document')
            <path d="M14 3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9l-6-6z" /><path d="M14 3v6h6" />
            @break
        @case('clock')
            <circle cx="12" cy="12" r="9" /><path d="M12 7v5l3 2" />
            @break
        @case('search')
            <circle cx="11" cy="11" r="7" /><path d="m21 21-4.3-4.3" />
            @break
        @case('chevron-left')
            <path d="m15 18-6-6 6-6" />
            @break
        @case('help')
            <circle cx="12" cy="12" r="9" /><path d="M9.5 9a2.5 2.5 0 1 1 5 0c0 1.5-2.5 2-2.5 4M12 17h.01" />
            @break
    @endswitch
</svg>
