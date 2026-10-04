<button {{ $attributes->merge(['type' => 'button', 'class' => 'inline-flex min-h-12 items-center justify-center gap-2 rounded-xl border border-brand bg-white px-5 py-2 text-sm font-bold text-brand transition hover:bg-brand-tint focus:outline-none focus:ring-2 focus:ring-brand focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50']) }}>
    {{ $slot }}
</button>
