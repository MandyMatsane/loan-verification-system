<x-app-layout>
    @php
        $user = Auth::user();
        $isAdmin = $user && $user->role === 'admin';
        $applications = $applications ?? collect();
    @endphp

    @if ($isAdmin)
        @include('admin.applications.partials.overview')
    @else
        @php
            $latest = $applications->first();
            $statusOf = fn ($application) => strtolower(trim(str_replace('_', ' ', (string) $application->status)));
            $approvedCount = $applications->filter(fn ($application) => $statusOf($application) === 'approved')->count();
            $awaitingCount = $applications->filter(fn ($application) => in_array($statusOf($application), ['pending', 'manual review', ''], true))->count();
            $firstName = \Illuminate\Support\Str::before(trim($user->name), ' ');
            $initials = collect(preg_split('/\s+/', trim($user->name)))->filter()->take(2)
                ->map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)))->implode('');

            $tiles = array_filter([
                ['label' => 'New application', 'icon' => 'plus', 'href' => route('applications.create')],
                $latest ? ['label' => 'Latest result', 'icon' => 'document', 'href' => route('applications.show', $latest)] : null,
                ['label' => 'My applications', 'icon' => 'list', 'href' => '#my-applications'],
                ['label' => 'Profile', 'icon' => 'user', 'href' => route('profile.edit')],
            ]);
        @endphp

        <div class="space-y-6">
            {{-- Phone greeting --}}
            <div class="flex items-center gap-3 md:hidden">
                <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-brand text-sm font-bold text-white">{{ $initials }}</span>
                <div class="min-w-0">
                    <p class="text-sm text-slate-500">Welcome back</p>
                    <h1 class="truncate text-lg font-extrabold text-ink">{{ $user->name }}</h1>
                </div>
            </div>

            {{-- Laptop header --}}
            <x-page-header class="hidden md:flex" eyebrow="Dashboard" title="Welcome back, {{ $firstName }}" subtitle="Track your applications or start a new one.">
                <x-slot name="actions">
                    <x-button-link href="{{ route('applications.create') }}">
                        <x-icon name="plus" />
                        Start new application
                    </x-button-link>
                </x-slot>
            </x-page-header>

            <div class="grid gap-6 xl:grid-cols-3">
                {{-- Stat cards: md and up --}}
                <div class="hidden gap-4 md:order-1 md:grid md:grid-cols-3 xl:col-span-3">
                    <x-stat-card label="Applications" :value="$applications->count()">
                        <x-slot name="icon"><x-icon name="list" /></x-slot>
                    </x-stat-card>
                    <x-stat-card label="Approved" :value="$approvedCount" tone="success">
                        <x-slot name="icon"><x-icon name="check" /></x-slot>
                    </x-stat-card>
                    <x-stat-card label="Awaiting decision" :value="$awaitingCount">
                        <x-slot name="icon"><x-icon name="clock" /></x-slot>
                    </x-stat-card>
                </div>

                {{-- Hero: latest application --}}
                <div class="order-1 self-start rounded-2xl bg-brand-dark p-5 text-white md:order-2 md:p-6 xl:order-3">
                    @if ($latest)
                        <p class="text-sm text-brand-light">Latest application - #{{ $latest->id }}</p>
                        <p class="mt-1 text-3xl font-extrabold tabular-nums text-white">R{{ number_format($latest->amount_requested, 2) }}</p>
                        <div class="mt-3">
                            <x-status-badge :status="$latest->status" />
                        </div>
                        <x-confidence-bar class="mt-4" :score="$latest->aiAssessment?->confidence_score" on-dark />
                        <x-button-link variant="white" class="mt-4 w-full" href="{{ route('applications.show', $latest) }}">
                            View application
                        </x-button-link>
                    @else
                        <p class="text-sm text-brand-light">No applications yet</p>
                        <p class="mt-1 text-2xl font-extrabold text-white">Apply for a loan</p>
                        <p class="mt-2 text-sm leading-6 text-brand-mist">Enter your loan details, upload three documents and get a clear result.</p>
                        <x-button-link variant="white" class="mt-4 w-full" href="{{ route('applications.create') }}">
                            Start your application
                        </x-button-link>
                    @endif
                </div>

                {{-- Quick actions: phone only --}}
                <div class="order-2 grid gap-2 md:hidden {{ count($tiles) === 4 ? 'grid-cols-4' : 'grid-cols-3' }}">
                    @foreach ($tiles as $tile)
                        <a href="{{ $tile['href'] }}" class="flex min-h-11 flex-col items-center gap-2 rounded-xl p-1 text-center focus:outline-none focus-visible:ring-2 focus-visible:ring-brand">
                            <span class="flex h-12 w-12 items-center justify-center rounded-2xl bg-brand-tint text-brand">
                                <x-icon :name="$tile['icon']" class="h-6 w-6" />
                            </span>
                            <span class="text-xs font-semibold leading-tight text-ink">{{ $tile['label'] }}</span>
                        </a>
                    @endforeach
                </div>

                {{-- Applications: list below md, table from md up --}}
                <section id="my-applications" class="order-3 min-w-0 scroll-mt-6 md:rounded-2xl md:border md:border-slate-200 md:bg-white md:p-6 xl:order-2 xl:col-span-2">
                    <h2 class="mb-3 text-base font-bold text-ink md:mb-4">
                        <span class="md:hidden">Recent applications</span>
                        <span class="hidden md:inline">My applications</span>
                    </h2>

                    @if ($applications->isEmpty())
                        <p class="rounded-2xl border border-slate-200 bg-white p-5 text-sm text-body md:border-0 md:p-0">You have not submitted an application yet.</p>
                    @else
                        <div class="hidden overflow-x-auto md:block">
                            <table class="min-w-full text-left text-sm">
                                <thead>
                                    <tr class="border-b border-slate-200 text-xs font-semibold text-slate-500">
                                        <th scope="col" class="py-2 pr-4">Reference</th>
                                        <th scope="col" class="px-4 py-2">Amount</th>
                                        <th scope="col" class="px-4 py-2">Status</th>
                                        <th scope="col" class="px-4 py-2">Submitted</th>
                                        <th scope="col" class="py-2 pl-4"><span class="sr-only">Action</span></th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-200">
                                    @foreach ($applications as $application)
                                        <tr>
                                            <td class="py-2 pr-4 font-semibold text-ink">#{{ $application->id }}</td>
                                            <td class="whitespace-nowrap px-4 py-2 tabular-nums text-ink">R{{ number_format($application->amount_requested, 2) }}</td>
                                            <td class="px-4 py-2"><x-status-badge :status="$application->status" /></td>
                                            <td class="whitespace-nowrap px-4 py-2 text-body">{{ $application->created_at?->format('j M') }}</td>
                                            <td class="py-2 pl-4 text-right">
                                                <a href="{{ route('applications.show', $application) }}" class="inline-flex min-h-11 items-center px-2 font-bold text-brand hover:text-brand-dark">
                                                    View<span class="sr-only"> application #{{ $application->id }}</span>
                                                </a>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        <ul class="space-y-3 md:hidden">
                            @foreach ($applications as $application)
                                <li>
                                    <a href="{{ route('applications.show', $application) }}" class="flex items-center gap-3 rounded-2xl border border-slate-200 bg-white p-4 transition hover:border-brand focus:outline-none focus-visible:ring-2 focus-visible:ring-brand">
                                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-brand-tint text-brand">
                                            <x-icon name="document" />
                                        </span>
                                        <span class="min-w-0 flex-1">
                                            <span class="block truncate text-sm font-bold text-ink">#{{ $application->id }} - R{{ number_format($application->amount_requested, 2) }}</span>
                                            <span class="block text-xs text-slate-500">{{ $application->created_at?->format('j M') }}</span>
                                        </span>
                                        <x-status-badge :status="$application->status" />
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </section>
            </div>
        </div>
    @endif
</x-app-layout>
