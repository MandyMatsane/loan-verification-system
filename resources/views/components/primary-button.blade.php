<button {{ $attributes->merge(['type' => 'submit', 'class' => 'inline-flex min-h-12 items-center justify-center gap-2 rounded-xl border border-transparent bg-brand px-5 py-2 text-sm font-bold text-white transition hover:bg-brand-dark focus:outline-none focus:ring-2 focus:ring-brand focus:ring-offset-2 active:bg-brand-dark disabled:cursor-not-allowed disabled:bg-slate-200 disabled:text-slate-500']) }}>
    {{ $slot }}
</button>
