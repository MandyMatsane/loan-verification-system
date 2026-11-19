<button {{ $attributes->merge(['type' => 'submit', 'class' => 'inline-flex min-h-12 items-center justify-center gap-2 rounded-xl border border-transparent bg-red-700 px-5 py-2 text-sm font-bold text-white transition hover:bg-red-800 focus:outline-none focus:ring-2 focus:ring-red-600 focus:ring-offset-2 active:bg-red-800']) }}>
    {{ $slot }}
</button>
