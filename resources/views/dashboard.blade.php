<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="text-sm font-medium uppercase tracking-[0.3em] text-cyan-300">Operations overview</p>
                <h2 class="text-2xl font-semibold text-white">Welcome back, {{ Auth::user()->name }}.</h2>
            </div>
            <div class="rounded-full border border-cyan-400/30 bg-cyan-400/10 px-3 py-1 text-sm font-medium text-cyan-200">
                Secure workspace ready
            </div>
        </div>
    </x-slot>

    <div class="space-y-6">
        <div class="grid gap-6 lg:grid-cols-[1.2fr_0.8fr]">
            <div class="rounded-[2rem] border border-white/10 bg-slate-900/70 p-6 shadow-2xl shadow-cyan-950/20">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="text-sm font-medium text-slate-400">Today’s progress</p>
                        <h3 class="mt-2 text-3xl font-semibold text-white">120 applications</h3>
                    </div>
                    <div class="rounded-full bg-emerald-500/15 px-3 py-1 text-sm font-medium text-emerald-300">
                        +18% faster review
                    </div>
                </div>

                <div class="mt-6 grid gap-4 sm:grid-cols-3">
                    <div class="rounded-2xl border border-white/10 bg-white/5 p-4">
                        <p class="text-sm text-slate-400">Pending</p>
                        <p class="mt-2 text-2xl font-semibold text-white">34</p>
                    </div>
                    <div class="rounded-2xl border border-white/10 bg-white/5 p-4">
                        <p class="text-sm text-slate-400">Reviewed</p>
                        <p class="mt-2 text-2xl font-semibold text-white">76</p>
                    </div>
                    <div class="rounded-2xl border border-white/10 bg-white/5 p-4">
                        <p class="text-sm text-slate-400">Flagged</p>
                        <p class="mt-2 text-2xl font-semibold text-white">10</p>
                    </div>
                </div>
            </div>

            <div class="rounded-[2rem] border border-white/10 bg-gradient-to-br from-cyan-500/15 to-emerald-500/10 p-6">
                <p class="text-sm font-medium text-cyan-200">Quick actions</p>
                <div class="mt-4 space-y-3">
                    <a href="{{ route('dashboard') }}" class="flex items-center justify-between rounded-2xl border border-white/10 bg-slate-950/70 px-4 py-3 text-sm text-slate-300 transition hover:bg-slate-900">
                        <span>Open dashboard</span>
                        <span class="text-cyan-300">→</span>
                    </a>
                    <a href="{{ route('profile.edit') }}" class="flex items-center justify-between rounded-2xl border border-white/10 bg-slate-950/70 px-4 py-3 text-sm text-slate-300 transition hover:bg-slate-900">
                        <span>Manage profile</span>
                        <span class="text-cyan-300">→</span>
                    </a>
                </div>
            </div>
        </div>

        <div class="grid gap-6 lg:grid-cols-3">
            <div class="rounded-[2rem] border border-white/10 bg-slate-900/70 p-6">
                <p class="text-sm font-medium text-cyan-300">Document verification</p>
                <h3 class="mt-3 text-xl font-semibold text-white">Modern intake workflow</h3>
                <p class="mt-2 text-sm leading-7 text-slate-400">Upload supporting documents and keep every review step organized in one place.</p>
            </div>
            <div class="rounded-[2rem] border border-white/10 bg-slate-900/70 p-6">
                <p class="text-sm font-medium text-cyan-300">AI review</p>
                <h3 class="mt-3 text-xl font-semibold text-white">Smarter decisions</h3>
                <p class="mt-2 text-sm leading-7 text-slate-400">Use structured insights to identify risk signals and support better approvals.</p>
            </div>
            <div class="rounded-[2rem] border border-white/10 bg-slate-900/70 p-6">
                <p class="text-sm font-medium text-cyan-300">Admin controls</p>
                <h3 class="mt-3 text-xl font-semibold text-white">Protected operations</h3>
                <p class="mt-2 text-sm leading-7 text-slate-400">Role-based access keeps sensitive workflows secure and easy to monitor.</p>
            </div>
        </div>
    </div>
</x-app-layout>
