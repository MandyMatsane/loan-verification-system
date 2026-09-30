<x-app-layout>
    @php
        $user = Auth::user();
        $isAdmin = $user && $user->role === 'admin';
        $applications = $applications ?? collect();
        $totalApplications = $applications->count();
        $pendingApplications = $applications->where('status', 'Pending')->count();
        $reviewedApplications = $applications->where('status', 'Reviewed')->count();
        $flaggedApplications = $applications
            ->filter(function ($application) {
                return $application->aiAssessment &&
                    $application->aiAssessment->fraud_risk_score !== null &&
                    $application->aiAssessment->fraud_risk_score >= 70;
            })
            ->count();
    @endphp

    <x-slot name="header">
        @if ($isAdmin)
            <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <p class="text-sm font-medium uppercase tracking-[0.3em] text-cyan-300">Operations overview</p>
                    <h2 class="text-2xl font-semibold text-white">Welcome back, {{ $user->name }}.</h2>
                </div>
                <div
                    class="rounded-full border border-cyan-400/30 bg-cyan-400/10 px-3 py-1 text-sm font-medium text-cyan-200">
                    Secure workspace ready
                </div>
            </div>
        @else
            <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <p class="text-sm font-medium uppercase tracking-[0.3em] text-emerald-300">Applicant dashboard</p>
                    <h2 class="text-2xl font-semibold text-white">Welcome back, {{ $user->name }}.</h2>
                </div>
                <div
                    class="rounded-full border border-emerald-400/30 bg-emerald-500/10 px-3 py-1 text-sm font-medium text-emerald-200">
                    Ready to apply
                </div>
            </div>
        @endif
    </x-slot>

    @if ($isAdmin)
        <div class="space-y-6">
            <div class="grid gap-6 lg:grid-cols-[1.2fr_0.8fr]">
                <div class="rounded-[2rem] border border-white/10 bg-slate-900/70 p-6 shadow-2xl shadow-cyan-950/20">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <p class="text-sm font-medium text-slate-400">Today’s progress</p>
                            <h3 class="mt-2 text-3xl font-semibold text-white">{{ $totalApplications }} applications
                            </h3>
                        </div>
                        <div class="rounded-full bg-emerald-500/15 px-3 py-1 text-sm font-medium text-emerald-300">
                            {{ $applications->count() > 0 ? 'Live data active' : 'No applications yet' }}
                        </div>
                    </div>

                    <div class="mt-6 grid gap-4 sm:grid-cols-3">
                        <div class="rounded-2xl border border-white/10 bg-white/5 p-4">
                            <p class="text-sm text-slate-400">Pending</p>
                            <p class="mt-2 text-2xl font-semibold text-white">{{ $pendingApplications }}</p>
                        </div>
                        <div class="rounded-2xl border border-white/10 bg-white/5 p-4">
                            <p class="text-sm text-slate-400">Reviewed</p>
                            <p class="mt-2 text-2xl font-semibold text-white">{{ $reviewedApplications }}</p>
                        </div>
                        <div class="rounded-2xl border border-white/10 bg-white/5 p-4">
                            <p class="text-sm text-slate-400">Flagged</p>
                            <p class="mt-2 text-2xl font-semibold text-white">{{ $flaggedApplications }}</p>
                        </div>
                    </div>
                </div>

                <div
                    class="rounded-[2rem] border border-white/10 bg-gradient-to-br from-cyan-500/15 to-emerald-500/10 p-6">
                    <p class="text-sm font-medium text-cyan-200">Quick actions</p>
                    <div class="mt-4 space-y-3">
                        <a href="{{ route('admin.dashboard') }}"
                            class="flex items-center justify-between rounded-2xl border border-white/10 bg-slate-950/70 px-4 py-3 text-sm text-slate-300 transition hover:bg-slate-900">
                            <span>Open admin dashboard</span>
                            <span class="text-cyan-300">→</span>
                        </a>
                        <a href="{{ route('admin.feature-importance') }}"
                            class="flex items-center justify-between rounded-2xl border border-white/10 bg-slate-950/70 px-4 py-3 text-sm text-slate-300 transition hover:bg-slate-900">
                            <span>Feature importance</span>
                            <span class="text-cyan-300">→</span>
                        </a>
                        <a href="{{ route('profile.edit') }}"
                            class="flex items-center justify-between rounded-2xl border border-white/10 bg-slate-950/70 px-4 py-3 text-sm text-slate-300 transition hover:bg-slate-900">
                            <span>Manage profile</span>
                            <span class="text-cyan-300">→</span>
                        </a>
                    </div>
                </div>
            </div>

            <div class="rounded-[2rem] border border-white/10 bg-slate-900/70 p-6">
                <div class="mb-4 flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-cyan-300">Latest applications</p>
                        <h3 class="mt-2 text-xl font-semibold text-white">Queue overview</h3>
                    </div>
                    <a href="{{ route('admin.applications.index') }}"
                        class="text-sm font-medium text-cyan-300 hover:text-cyan-200">View all</a>
                </div>

                @if ($applications->isEmpty())
                    <p class="text-sm text-slate-400">No applications have been submitted yet.</p>
                @else
                    <div class="overflow-hidden rounded-2xl border border-white/10">
                        <table class="min-w-full divide-y divide-white/10 text-left text-sm text-slate-300">
                            <thead class="bg-slate-950/60 text-slate-400">
                                <tr>
                                    <th class="px-4 py-3">Applicant</th>
                                    <th class="px-4 py-3">Amount</th>
                                    <th class="px-4 py-3">Status</th>
                                    <th class="px-4 py-3">Risk</th>
                                    <th class="px-4 py-3"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-white/10 bg-slate-950/30">
                                @foreach ($applications->take(5) as $application)
                                    <tr>
                                        <td class="px-4 py-3 text-white">
                                            {{ $application->user?->name ?? 'Unknown user' }}</td>
                                        <td class="px-4 py-3">R{{ number_format($application->amount_requested, 2) }}
                                        </td>
                                        <td class="px-4 py-3">{{ $application->status }}</td>
                                        <td class="px-4 py-3">
                                            {{ $application->aiAssessment?->fraud_risk_score ?? '—' }}</td>
                                        <td class="px-4 py-3 text-cyan-300">
                                            <a href="{{ route('admin.applications.show', $application) }}">View</a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>

            <div class="grid gap-6 lg:grid-cols-3">
                <div class="rounded-[2rem] border border-white/10 bg-slate-900/70 p-6">
                    <p class="text-sm font-medium text-cyan-300">Document verification</p>
                    <h3 class="mt-3 text-xl font-semibold text-white">Modern intake workflow</h3>
                    <p class="mt-2 text-sm leading-7 text-slate-400">Upload supporting documents and keep every review
                        step organized in one place.</p>
                </div>
                <div class="rounded-[2rem] border border-white/10 bg-slate-900/70 p-6">
                    <p class="text-sm font-medium text-cyan-300">AI review</p>
                    <h3 class="mt-3 text-xl font-semibold text-white">Smarter decisions</h3>
                    <p class="mt-2 text-sm leading-7 text-slate-400">Use structured insights to identify risk signals
                        and support better approvals.</p>
                </div>
                <div class="rounded-[2rem] border border-white/10 bg-slate-900/70 p-6">
                    <p class="text-sm font-medium text-cyan-300">Admin controls</p>
                    <h3 class="mt-3 text-xl font-semibold text-white">Protected operations</h3>
                    <p class="mt-2 text-sm leading-7 text-slate-400">Role-based access keeps sensitive workflows secure
                        and easy to monitor.</p>
                </div>
            </div>
        </div>
    @else
        <div class="space-y-6">
            <div
                class="rounded-[2rem] border border-emerald-500/20 bg-gradient-to-br from-emerald-500/10 via-slate-900/80 to-cyan-500/10 p-6 shadow-2xl shadow-emerald-950/20">
                <div class="flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
                    <div class="max-w-2xl">
                        <p class="text-sm font-medium uppercase tracking-[0.3em] text-emerald-300">Loan application</p>
                        <h3 class="mt-3 text-3xl font-semibold text-white">Apply for a loan</h3>
                        <p class="mt-3 text-base leading-7 text-slate-300">
                            Start your application in a few steps. Upload your ID, payslip, and bank statement, then
                            track the status of your request in one place.
                        </p>
                    </div>

                    <div class="flex flex-col gap-3 sm:flex-row">
                        <a href="{{ route('applications.create') }}"
                            class="inline-flex items-center justify-center rounded-full bg-emerald-500 px-5 py-3 text-sm font-semibold text-slate-950 transition hover:bg-emerald-400">
                            Start your application
                        </a>
                        <a href="{{ route('profile.edit') }}"
                            class="inline-flex items-center justify-center rounded-full border border-white/15 bg-slate-900/70 px-5 py-3 text-sm font-semibold text-white transition hover:bg-slate-800">
                            Update profile
                        </a>
                    </div>
                </div>
            </div>

            <div class="grid gap-6 lg:grid-cols-3">
                <div class="rounded-[2rem] border border-white/10 bg-slate-900/70 p-6">
                    <p class="text-sm font-medium text-emerald-300">Step 1</p>
                    <h3 class="mt-3 text-xl font-semibold text-white">Complete your profile</h3>
                    <p class="mt-2 text-sm leading-7 text-slate-400">Keep your personal and employment details up to
                        date so your application is ready for review.</p>
                </div>
                <div class="rounded-[2rem] border border-white/10 bg-slate-900/70 p-6">
                    <p class="text-sm font-medium text-cyan-300">Step 2</p>
                    <h3 class="mt-3 text-xl font-semibold text-white">Upload documents</h3>
                    <p class="mt-2 text-sm leading-7 text-slate-400">Add your ID document, payslip, and bank statement
                        to complete your submission.</p>
                </div>
                <div class="rounded-[2rem] border border-white/10 bg-slate-900/70 p-6">
                    <p class="text-sm font-medium text-violet-300">Step 3</p>
                    <h3 class="mt-3 text-xl font-semibold text-white">Track your status</h3>
                    <p class="mt-2 text-sm leading-7 text-slate-400">Follow your application as it moves from intake to
                        review and decision.</p>
                </div>
            </div>
        </div>
    @endif
</x-app-layout>
