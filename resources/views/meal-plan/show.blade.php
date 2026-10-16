<x-layouts.app :title="'Essensplan ' . $week->label()">
    <div class="mb-8 flex flex-wrap items-end justify-between gap-4">
        <div>
            <p class="mb-2 text-sm font-semibold tracking-widest text-accent uppercase">{{ $week->relativeName() ?? 'Essensplan' }}</p>
            <h1 class="font-display text-4xl font-semibold tracking-tight sm:text-5xl">Essensplan</h1>
            <p class="mt-2 text-ink-soft">{{ $week->label() }}</p>
        </div>

        <div class="flex flex-wrap items-center gap-3">
            @include('meal-plan.partials.week-nav')
            @can('plan-meals', $week)
                <a href="{{ route('meal-plan.edit', $week) }}" class="btn btn-primary">Plan festlegen</a>
            @endcan
        </div>
    </div>

    @if (! $week->isOpen() && $week->start->isPast())
        <p class="mb-6 rounded-2xl bg-paper-deep px-5 py-3 text-sm text-ink-soft">
            Diese Woche ist vorbei – der Plan ist nur noch zum Nachlesen da.
        </p>
    @endif

    <div class="grid gap-10 lg:grid-cols-[minmax(0,1.5fr)_minmax(0,1fr)]">
        {{-- Die sieben Tage --}}
        <ol class="space-y-3">
            @foreach ($week->days() as $day => $date)
                @php($entry = $entries->get($day))
                @php($isToday = $date->isToday())
                <li @class([
                        'flex items-center gap-4 rounded-3xl border bg-white p-4 sm:p-5',
                        'border-accent ring-2 ring-accent/15' => $isToday,
                        'border-line' => ! $isToday,
                    ])
                    @if ($entry?->recipe) style="--recipe: {{ $entry->recipe->accent_color }}" @endif>
                    <div class="w-28 shrink-0">
                        <p class="font-display text-lg font-semibold">{{ App\Support\Week::dayName($day) }}</p>
                        <p class="text-xs text-ink-soft">
                            {{ $date->format('d.m.') }}
                            @if ($isToday)
                                <span class="ml-1 rounded-full bg-accent px-1.5 py-0.5 font-semibold text-white">Heute</span>
                            @endif
                        </p>
                    </div>

                    @if ($entry)
                        <span @class([
                            'grid size-12 shrink-0 place-items-center rounded-2xl text-2xl',
                            'recipe-tint' => $entry->recipe,
                            'bg-paper-deep' => ! $entry->recipe,
                        ]) aria-hidden="true">{{ $entry->recipe?->emoji ?: ($entry->recipe ? '🍽️' : '✏️') }}</span>
                        <div class="min-w-0">
                            @if ($entry->recipe)
                                <a href="{{ route('recipes.show', $entry->recipe) }}" class="font-semibold leading-snug hover:text-accent-dark">{{ $entry->displayTitle() }}</a>
                            @else
                                <p class="font-semibold leading-snug">{{ $entry->displayTitle() }}</p>
                            @endif
                            @if ($entry->plannedBy)
                                <p class="text-xs text-ink-soft">geplant von {{ $entry->plannedBy->name }}</p>
                            @endif
                        </div>
                    @else
                        <p class="text-sm text-ink-soft">Noch nichts geplant</p>
                    @endif
                </li>
            @endforeach
        </ol>

        {{-- Wünsche --}}
        <section id="wuensche" class="scroll-mt-24 space-y-4">
            <div class="flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h2 class="mb-1 font-display text-2xl font-semibold">Wünsche</h2>
                    <p class="text-sm text-ink-soft">Offen oder in KW {{ $week->number() }} eingeplant.</p>
                </div>
                @if ($week->isOpen() && auth()->user()->can('make-wishes'))
                    <a href="{{ route('wishes.index', ['woche' => $week->iso()]) }}" class="btn btn-ghost">＋ Etwas wünschen</a>
                @endif
            </div>
            @include('wishes.partials.groups', ['groups' => $wishGroups, 'empty' => 'Gerade gibt es keine offenen Wünsche.'])
        </section>
    </div>
</x-layouts.app>
