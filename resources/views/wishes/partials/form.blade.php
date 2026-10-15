{{--
    „Etwas wünschen“: Rezept (mit Suche und Favoriten-Filter) oder eigene Idee.
    $recipes: Liste [id, title, emoji, favorite]; $weeks: [diese, nächste]; $selectedWeek
--}}
<form method="POST" action="{{ route('wishes.store') }}" class="card-section space-y-5"
      x-data="{ mode: @js(old('title') ? 'text' : 'recipe') }">
    @csrf
    <h2 class="font-display text-2xl font-semibold">Etwas wünschen</h2>

    <div class="flex gap-1 rounded-full bg-paper-deep p-1 text-sm font-medium" role="tablist">
        <button type="button" role="tab" @click="mode = 'recipe'" :aria-selected="mode === 'recipe'"
                :class="mode === 'recipe' ? 'bg-white shadow-sm text-ink' : 'text-ink-soft'"
                class="flex-1 rounded-full px-3 py-2 whitespace-nowrap transition">Rezept</button>
        <button type="button" role="tab" @click="mode = 'text'" :aria-selected="mode === 'text'"
                :class="mode === 'text' ? 'bg-white shadow-sm text-ink' : 'text-ink-soft'"
                class="flex-1 rounded-full px-3 py-2 whitespace-nowrap transition">Eigene Idee</button>
    </div>

    {{-- Rezept auswählen --}}
    <div x-show="mode === 'recipe'" x-data="recipePicker({ recipes: @js($recipes), selected: @js(old('recipe_id') ? (int) old('recipe_id') : null) })">
        <input type="hidden" name="recipe_id" :value="selectedId ?? ''" :disabled="mode !== 'recipe'">

        {{-- Gewähltes Rezept --}}
        <template x-if="selected">
            <div class="flex items-center gap-3 rounded-2xl border-2 border-accent bg-accent/5 p-3">
                <span class="text-2xl" aria-hidden="true" x-text="selected.emoji"></span>
                <span class="min-w-0 flex-1 font-semibold" x-text="selected.title"></span>
                <button type="button" @click="clear()" class="btn btn-ghost py-1.5! text-xs">Ändern</button>
            </div>
        </template>

        <div x-show="! selected" class="space-y-3">
            <label for="recipe-search" class="field-label">Rezept suchen</label>
            <input id="recipe-search" x-ref="search" type="search" x-model="query" class="field" placeholder="z. B. Lasagne, Kartoffel …" autocomplete="off">

            <div class="flex flex-wrap gap-2" x-show="hasFavorites">
                <button type="button" @click="onlyFavorites = true" :aria-pressed="onlyFavorites"
                        :class="onlyFavorites ? 'chip-active' : ''" class="chip">♥ Nur meine Favoriten</button>
                <button type="button" @click="onlyFavorites = false" :aria-pressed="! onlyFavorites"
                        :class="! onlyFavorites ? 'chip-active' : ''" class="chip">Alle Rezepte</button>
            </div>

            <ul class="max-h-72 divide-y divide-line overflow-y-auto overscroll-contain rounded-2xl border border-line bg-white" role="listbox" aria-label="Rezepte">
                <template x-for="recipe in filtered" :key="recipe.id">
                    <li>
                        <button type="button" @click="select(recipe)" role="option" :aria-selected="selectedId === recipe.id"
                                class="flex w-full items-center gap-3 px-4 py-3 text-left hover:bg-paper">
                            <span class="text-xl" aria-hidden="true" x-text="recipe.emoji"></span>
                            <span class="min-w-0 flex-1" x-text="recipe.title"></span>
                            <span x-show="recipe.favorite" class="text-accent" aria-label="Favorit">♥</span>
                        </button>
                    </li>
                </template>
                <li x-show="filtered.length === 0" class="px-4 py-6 text-center text-sm text-ink-soft">
                    <span x-show="onlyFavorites">Keine passenden Favoriten – <button type="button" class="underline" @click="onlyFavorites = false">alle Rezepte durchsuchen</button>.</span>
                    <span x-show="! onlyFavorites">Kein passendes Rezept. Als „Eigene Idee“ wünschen?</span>
                </li>
            </ul>
        </div>
        @error('recipe_id') <p class="field-error">{{ $message }}</p> @enderror
    </div>

    {{-- Eigene Idee --}}
    <div x-show="mode === 'text'" x-cloak>
        <label for="title" class="field-label">Gericht</label>
        <input id="title" name="title" value="{{ old('title') }}" maxlength="100" class="field"
               placeholder="z. B. Spinat-Lasagne, Pizza bestellen, Essen bei Oma" :disabled="mode !== 'text'">
        @error('title') <p class="field-error">{{ $message }}</p> @enderror
    </div>

    {{-- Ab wann --}}
    <fieldset>
        <legend class="field-label">Ab wann?</legend>
        <div class="grid grid-cols-2 gap-2">
            @foreach ($weeks as $week)
                <label class="flex cursor-pointer flex-col rounded-2xl border border-line p-3 text-sm transition hover:border-ink-soft has-[:checked]:border-accent has-[:checked]:bg-accent/5">
                    <span class="flex items-center gap-2 font-semibold">
                        <input type="radio" name="week" value="{{ $week->iso() }}" class="size-4 accent-accent"
                               @checked(old('week', $selectedWeek->iso()) === $week->iso())>
                        {{ $week->relativeName() }}
                    </span>
                    <span class="pl-6 text-xs text-ink-soft">ab KW {{ $week->number() }}</span>
                </label>
            @endforeach
        </div>
        @error('week') <p class="field-error">{{ $message }}</p> @enderror
        <p class="mt-1 text-xs text-ink-soft">Der Wunsch bleibt auf der Liste, bis er im Essensplan steht.</p>
    </fieldset>

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

    <button type="submit" class="btn btn-primary w-full">Wünschen</button>
</form>
