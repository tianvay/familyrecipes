<x-layouts.app :title="'Essensplan festlegen ' . $week->label()">
    @php
        // Freitext-Wünsche nach Titel, um vorhandene Einträge wieder dem Wunsch zuzuordnen
        $textSuggestionChoice = $wishGroups
            ->whereNull('recipe')
            ->mapWithKeys(fn (array $group) => [mb_strtolower($group['title']) => $group['choice']]);

        // Wunschtage: Tag => Liste „Gericht (Name)“
        $wishes = $wishGroups->flatMap(fn (array $group) => $group['suggestions']
            ->whereNotNull('preferred_day')
            ->map(fn ($s) => ['day' => $s->preferred_day, 'text' => $group['title'].' ('.($s->user?->name ?? 'ehemalig').')']))
            ->groupBy('day');

        $currentChoice = function (int $day) use ($entries, $textSuggestionChoice): string {
            $entry = $entries->get($day);

            return match (true) {
                $entry === null => '',
                $entry->recipe_id !== null => "r{$entry->recipe_id}",
                default => $textSuggestionChoice->get(mb_strtolower($entry->title), 't'),
            };
        };

        $lastEatenLabel = fn (int $recipeId): string => App\Support\LastEaten::label($lastEaten->get($recipeId));
    @endphp

    <a href="{{ route('meal-plan.show', $week) }}" class="mb-6 inline-flex items-center gap-1 text-sm font-medium text-ink-soft hover:text-ink">
        ← Zurück zum Essensplan
    </a>

    <div class="mb-8">
        <p class="mb-2 text-sm font-semibold tracking-widest text-accent uppercase">{{ $week->relativeName() ?? 'Essensplan' }}</p>
        <h1 class="font-display text-4xl font-semibold tracking-tight">Essensplan festlegen</h1>
        <p class="mt-2 text-ink-soft">{{ $week->label() }} · pro Tag ein Gericht, jedes Gericht höchstens zweimal pro Woche.</p>
    </div>

    <div class="grid gap-10 lg:grid-cols-[minmax(0,1.5fr)_minmax(0,1fr)]">
        <form method="POST" action="{{ route('meal-plan.update', $week) }}" class="space-y-3">
            @csrf
            @method('PUT')

            @if ($errors->any())
                <div class="rounded-2xl border border-accent/30 bg-accent/5 p-4 text-sm text-accent-dark" role="alert">
                    Bitte prüfe die markierten Tage.
                </div>
            @endif

            @foreach ($week->days() as $day => $date)
                @php($choice = old("days.{$day}.choice", $currentChoice($day)))
                @php($title = old("days.{$day}.title", $choice === 't' ? $entries->get($day)?->title : ''))
                <div class="rounded-3xl border border-line bg-white p-4 sm:p-5" x-data="{ choice: @js($choice) }">
                    <div class="flex flex-wrap items-center gap-x-4 gap-y-3">
                        <label for="day-{{ $day }}" class="w-28 shrink-0">
                            <span class="block font-display text-lg font-semibold">{{ App\Support\Week::dayName($day) }}</span>
                            <span class="block text-xs text-ink-soft">{{ $date->format('d.m.') }}</span>
                        </label>

                        <div class="min-w-0 flex-1 space-y-2">
                            <select id="day-{{ $day }}" name="days[{{ $day }}][choice]" x-model="choice" class="field">
                                <option value="">— kein Gericht —</option>
                                @if ($wishGroups->isNotEmpty())
                                    <optgroup label="Wünsche der Familie">
                                        @foreach ($wishGroups as $group)
                                            <option value="{{ $group['choice'] }}">
                                                {{ $group['plannedOn'] ? '✓ ' : '' }}{{ $group['recipe']?->emoji ?: '✏️' }} {{ $group['title'] }} ({{ $group['suggestions']->count() }}× · {{ $group['suggestions']->map(fn ($s) => $s->user?->name ?? 'ehemalig')->join(', ') }}){{ $group['recipe'] ? ' · zuletzt '.$lastEatenLabel($group['recipe']->id) : '' }}
                                            </option>
                                        @endforeach
                                    </optgroup>
                                @endif
                                @if ($longAgo->isNotEmpty())
                                    <optgroup label="Lange nicht gegessen">
                                        @foreach ($longAgo as $recipe)
                                            <option value="r{{ $recipe->id }}">{{ $recipe->emoji }} {{ $recipe->title }} · zuletzt {{ $lastEatenLabel($recipe->id) }}</option>
                                        @endforeach
                                    </optgroup>
                                @endif
                                <optgroup label="Alle Rezepte">
                                    @foreach ($recipes as $recipe)
                                        <option value="r{{ $recipe->id }}">{{ $recipe->emoji }} {{ $recipe->title }} · zuletzt {{ $lastEatenLabel($recipe->id) }}</option>
                                    @endforeach
                                </optgroup>
                                <optgroup label="Sonstiges">
                                    <option value="t">✏️ Eigener Text …</option>
                                </optgroup>
                            </select>

                            <input x-show="choice === 't'" x-cloak name="days[{{ $day }}][title]" value="{{ $title }}" maxlength="100"
                                   class="field" placeholder="z. B. Pizza bestellen" aria-label="Gericht am {{ App\Support\Week::dayName($day) }}"
                                   :disabled="choice !== 't'">

                            @foreach (array_unique([...$errors->get("days.{$day}.choice"), ...$errors->get("days.{$day}.title")]) as $message)
                                <p class="field-error">{{ $message }}</p>
                            @endforeach

                            @if ($wishes->has($day))
                                <p class="text-xs text-ink-soft">
                                    Wunsch für diesen Tag: {{ $wishes->get($day)->pluck('text')->join(', ') }}
                                </p>
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach

            <div class="flex items-center gap-3 pt-2">
                <button type="submit" class="btn btn-primary">Plan speichern</button>
                <a href="{{ route('meal-plan.show', $week) }}" class="btn btn-ghost">Abbrechen</a>
            </div>
        </form>

        <aside class="lg:sticky lg:top-24 lg:self-start">
            <h2 class="mb-1 font-display text-2xl font-semibold">Wünsche</h2>
            <p class="mb-4 text-sm text-ink-soft">Offene Wünsche der Familie – auch ältere, die noch nicht dran waren.</p>
            @include('wishes.partials.groups', ['groups' => $wishGroups, 'empty' => 'Gerade gibt es keine offenen Wünsche.', 'compact' => true])
        </aside>
    </div>
</x-layouts.app>
