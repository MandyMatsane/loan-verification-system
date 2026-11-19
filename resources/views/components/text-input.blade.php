@props(['disabled' => false])

<input @disabled($disabled) {{ $attributes->merge(['class' => 'min-h-12 rounded-xl border-slate-300 bg-white px-4 text-ink placeholder:text-slate-500 focus:border-brand focus:ring-brand disabled:bg-slate-100 disabled:text-slate-500']) }}>
