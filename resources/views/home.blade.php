<x-layouts.app title="Heute">
    @php
        $todayEntry = $upcoming->get($today->toDateString());
        $tomorrow = $today->addDay();
        $tomorrowEntry = $upcoming->get($tomorrow->toDateString());
        $laterDays = collect(range(2, 6))->map(fn (int $offset) => $today->addDays($offset));
        $dayName = fn ($date) => App\Support\Week::dayName($date->dayOfWeekIso);
        $tiles = array_filter([
            ['📖', 'Alle Rezepte', route('recipes.index'), true],
            ['♥', 'Meine Favoriten', route('recipes.index', ['favoriten' => 1]), true],
            ['📅', 'Essensplan', route('meal-plan.show'), true],
            ['🛒', 'Einkaufsliste', route('shopping-list.show'), auth()->user()->can('view-shopping-list')],
        ], fn (array $tile) => $tile[3]);
    @endphp

    <p class="mb-2 text-sm font-semibold tracking-widest text-accent uppercase">{{ $dayName($today) }}, {{ $today->format('d.m.') }}</p>
    <h1 class="mb-8 font-display text-4xl font-semibold tracking-tight sm:text-5xl">{{ $greeting }}, {{ auth()->user()->name }}!</h1>

    <div class="grid gap-6 lg:grid-cols-[minmax(0,1.6fr)_minmax(0,1fr)]">
        {{-- Heute --}}
        <section aria-labelledby="heute">
            @if ($todayEntry)
                @php($recipe = $todayEntry->recipe)
                <article class="recipe-tint relative overflow-hidden rounded-[2rem]" @if ($recipe) style="--recipe: {{ $recipe->accent_color }}" @else style="--recipe: #C8553D" @endif>
                    @if ($recipe?->image_url)
                        <img src="{{ $recipe->image_url }}" alt="" class="aspect-[16/9] w-full object-cover">
                    @endif
                    <div class="flex items-center gap-5 p-6 sm:p-8">
                        @unless ($recipe?->image_url)
                            <span class="text-6xl sm:text-7xl" aria-hidden="true">{{ $recipe?->emoji ?: ($recipe ? '🍽️' : '✏️') }}</span>
                        @endunless
                        <div class="min-w-0">
                            <h2 id="heute" class="text-sm font-semibold tracking-widest text-ink-soft uppercase">Heute gibt's</h2>
                            <p class="mt-1 font-display text-3xl leading-tight font-semibold sm:text-4xl">{{ $todayEntry->displayTitle() }}</p>
                            @if ($recipe?->total_minutes)
                                <p class="mt-2 text-sm text-ink-soft">⏱ ca. {{ $recipe->total_minutes }} Min.</p>
                            @endif
                        </div>
                    </div>
                    @if ($recipe)
                        <div class="px-6 pb-6 sm:px-8 sm:pb-8">
                            <a href="{{ route('recipes.show', $recipe) }}" class="btn btn-primary w-full sm:w-auto">Zum Rezept</a>
                        </div>
                    @endif
                </article>
            @else
                <article class="flex flex-col items-start gap-4 rounded-[2rem] border border-dashed border-line bg-white p-6 sm:p-8">
                    <h2 id="heute" class="text-sm font-semibold tracking-widest text-ink-soft uppercase">Heute gibt's</h2>
                    <p class="font-display text-2xl font-semibold">Noch nichts geplant</p>
                    @can('plan-meals', App\Support\Week::current())
                        <a href="{{ route('meal-plan.edit', App\Support\Week::current()) }}" class="btn btn-primary">Plan festlegen</a>
                    @else
                        <a href="{{ route('meal-plan.show') }}#vorschlaege" class="btn btn-primary">Etwas vorschlagen</a>
                    @endcan
                </article>
            @endif
        </section>

        <div class="flex flex-col gap-6">
            {{-- Morgen --}}
            <section class="rounded-3xl border border-line bg-white p-5" aria-labelledby="morgen">
                <h2 id="morgen" class="mb-2 text-sm font-semibold tracking-widest text-ink-soft uppercase">Morgen · {{ $dayName($tomorrow) }}</h2>
                @include('home.partials.entry', ['entry' => $tomorrowEntry, 'large' => true])
            </section>

            {{-- Nächste Woche --}}
            @if ($today->dayOfWeekIso >= 4 || $nextWeekPlanned > 0)
                <section class="rounded-3xl border border-line bg-white p-5" aria-labelledby="naechste-woche">
                    <h2 id="naechste-woche" class="mb-1 text-sm font-semibold tracking-widest text-ink-soft uppercase">Nächste Woche</h2>
                    <p class="mb-4 text-sm">
                        {{ $nextWeekPlanned }} von 7 Tagen geplant · {{ $nextWeekSuggestions }} {{ $nextWeekSuggestions === 1 ? 'Vorschlag' : 'Vorschläge' }}
                        @if ($ownSuggestionsNextWeek > 0)
                            <span class="text-ink-soft">(davon {{ $ownSuggestionsNextWeek }} von dir)</span>
                        @endif
                    </p>
                    <div class="flex flex-wrap gap-2">
                        <a href="{{ route('meal-plan.show', $nextWeek) }}#vorschlaege" class="btn btn-ghost">Vorschlagen</a>
                        @can('plan-meals', $nextWeek)
                            <a href="{{ route('meal-plan.edit', $nextWeek) }}" class="btn btn-ghost">Plan festlegen</a>
                        @endcan
                    </div>
                </section>
            @endif
        </div>
    </div>

    {{-- Rest der Woche --}}
    <section class="mt-10" aria-labelledby="demnaechst">
        <div class="mb-4 flex items-baseline justify-between gap-4">
            <h2 id="demnaechst" class="font-display text-2xl font-semibold">Demnächst</h2>
            <a href="{{ route('meal-plan.show') }}" class="text-sm font-medium text-ink-soft hover:text-ink">Ganzer Essensplan →</a>
        </div>
        <ol class="divide-y divide-line overflow-hidden rounded-3xl border border-line bg-white">
            @foreach ($laterDays as $date)
                <li class="flex items-center gap-4 px-5 py-3">
                    <span class="w-24 shrink-0 text-sm">
                        <span class="block font-semibold">{{ $dayName($date) }}</span>
                        <span class="block text-xs text-ink-soft">{{ $date->format('d.m.') }}</span>
                    </span>
                    @include('home.partials.entry', ['entry' => $upcoming->get($date->toDateString()), 'large' => false])
                </li>
            @endforeach
        </ol>
    </section>

    {{-- Schnellzugriffe --}}
    <nav class="mt-10 grid grid-cols-2 gap-3 sm:grid-cols-4" aria-label="Schnellzugriff">
        @foreach ($tiles as [$icon, $label, $url])
            <a href="{{ $url }}" class="flex flex-col items-start gap-2 rounded-3xl border border-line bg-white p-5 font-semibold transition hover:border-ink-soft">
                <span class="text-2xl" aria-hidden="true">{{ $icon }}</span>
                {{ $label }}
            </a>
        @endforeach
    </nav>
</x-layouts.app>
