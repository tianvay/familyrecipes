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
            Diese Woche ist vorbei – Plan und Vorschläge sind nur noch zum Nachlesen da.
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

        {{-- Vorschläge --}}
        <section id="vorschlaege" class="scroll-mt-24 space-y-6">
            <div>
                <h2 class="mb-1 font-display text-2xl font-semibold">Vorschläge</h2>
                <p class="mb-4 text-sm text-ink-soft">Was möchte die Familie in KW {{ $week->number() }} essen?</p>
                @include('meal-plan.partials.suggestions')
            </div>

            @can('create', [App\Models\MealSuggestion::class, $week])
                <form method="POST" action="{{ route('meal-suggestions.store', $week) }}" class="card-section space-y-4"
                      x-data="{ mode: @js(old('title') ? 'text' : 'recipe') }">
                    @csrf
                    <h3 class="font-display text-xl font-semibold">Gericht vorschlagen</h3>

                    <div class="flex gap-1 rounded-full bg-paper-deep p-1 text-sm font-medium" role="tablist">
                        <button type="button" role="tab" @click="mode = 'recipe'" :aria-selected="mode === 'recipe'"
                                :class="mode === 'recipe' ? 'bg-white shadow-sm text-ink' : 'text-ink-soft'"
                                class="flex-1 rounded-full px-3 py-1.5 whitespace-nowrap transition">Rezept</button>
                        <button type="button" role="tab" @click="mode = 'text'" :aria-selected="mode === 'text'"
                                :class="mode === 'text' ? 'bg-white shadow-sm text-ink' : 'text-ink-soft'"
                                class="flex-1 rounded-full px-3 py-1.5 whitespace-nowrap transition">Eigene Idee</button>
                    </div>

                    <div x-show="mode === 'recipe'">
                        <label for="recipe_id" class="field-label">Rezept</label>
                        <select id="recipe_id" name="recipe_id" class="field" :disabled="mode !== 'recipe'">
                            <option value="">Bitte wählen …</option>
                            @foreach ($recipes as $recipe)
                                <option value="{{ $recipe->id }}" @selected((int) old('recipe_id') === $recipe->id)>
                                    {{ $recipe->emoji }} {{ $recipe->title }}
                                </option>
                            @endforeach
                        </select>
                        @error('recipe_id') <p class="field-error">{{ $message }}</p> @enderror
                    </div>

                    <div x-show="mode === 'text'" x-cloak>
                        <label for="title" class="field-label">Gericht</label>
                        <input id="title" name="title" value="{{ old('title') }}" maxlength="100" class="field"
                               placeholder="z. B. Pizza bestellen, Reste, Essen bei Oma" :disabled="mode !== 'text'">
                        @error('title') <p class="field-error">{{ $message }}</p> @enderror
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label for="preferred_day" class="field-label">Wunschtag <span class="font-normal text-ink-soft">(optional)</span></label>
                            <select id="preferred_day" name="preferred_day" class="field">
                                <option value="">egal</option>
                                @foreach (range(1, 7) as $day)
                                    <option value="{{ $day }}" @selected((int) old('preferred_day') === $day)>{{ App\Support\Week::dayName($day) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label for="note" class="field-label">Notiz <span class="font-normal text-ink-soft">(optional)</span></label>
                            <input id="note" name="note" value="{{ old('note') }}" maxlength="200" class="field" placeholder="z. B. Lena hat Geburtstag">
                            @error('note') <p class="field-error">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary w-full">Vorschlagen</button>
                </form>
            @endcan
        </section>
    </div>
</x-layouts.app>
