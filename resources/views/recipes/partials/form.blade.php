@php
    $ingredientRows = old('ingredients', $recipe->exists
        ? $recipe->ingredients->map->only('amount', 'unit', 'name', 'is_optional')->all()
        : []);
    $ingredientRows = collect($ingredientRows ?: array_fill(0, 3, ['amount' => '', 'unit' => '', 'name' => '', 'is_optional' => false]))
        ->values()
        ->map(fn (array $row, int $i) => [...$row, 'key' => $i]);

    $stepRows = old('steps', $recipe->exists ? $recipe->steps->pluck('body')->all() : []);
    $stepRows = collect($stepRows ?: ['', ''])
        ->values()
        ->map(fn (string $body, int $i) => ['body' => $body, 'key' => $i]);

    $tags = old('tags', $recipe->exists ? $recipe->tags->pluck('name')->all() : []);
    $tips = old('tips', $recipe->tips ?? []);
@endphp

<form method="POST" action="{{ $action }}" enctype="multipart/form-data" class="grid gap-8 lg:grid-cols-[minmax(0,1.6fr)_minmax(0,1fr)]">
    @csrf
    @method($method)

    @if ($errors->any())
        <div class="rounded-2xl border border-accent/30 bg-accent/5 p-4 text-sm text-accent-dark lg:col-span-2" role="alert">
            Bitte prüfe die markierten Felder.
        </div>
    @endif

    <div class="flex flex-col gap-8">
        {{-- Grunddaten --}}
        <section class="card-section space-y-5">
            <h2 class="font-display text-xl font-semibold">Grunddaten</h2>

            <div>
                <label for="title" class="field-label">Titel *</label>
                <input id="title" name="title" value="{{ old('title', $recipe->title) }}" required maxlength="255" class="field" placeholder="z. B. Ofengemüse mit Feta">
                @error('title') <p class="field-error">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="summary" class="field-label">Kurzbeschreibung</label>
                <input id="summary" name="summary" value="{{ old('summary', $recipe->summary) }}" maxlength="255" class="field" placeholder="Ein Satz für die Übersicht">
                @error('summary') <p class="field-error">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="description" class="field-label">Beschreibung</label>
                <textarea id="description" name="description" rows="3" class="field">{{ old('description', $recipe->description) }}</textarea>
                @error('description') <p class="field-error">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="tags" class="field-label">Tags</label>
                <input id="tags" name="tags" value="{{ implode(', ', (array) $tags) }}" class="field" placeholder="Vegetarisch, Schnell, Airfryer">
                <p class="mt-1 text-xs text-ink-soft">Mehrere Tags mit Komma trennen.</p>
                @error('tags.*') <p class="field-error">{{ $message }}</p> @enderror
            </div>
        </section>

        {{-- Zutaten --}}
        <section class="card-section"
                 x-data="{
                     rows: @js($ingredientRows),
                     add() { this.rows.push({ amount: '', unit: '', name: '', is_optional: false, key: Date.now() }) },
                     remove(index) { this.rows.splice(index, 1) },
                 }">
            <div class="mb-5 flex items-center justify-between">
                <h2 class="font-display text-xl font-semibold">Zutaten *</h2>
                <button type="button" @click="add()" class="btn btn-ghost py-1.5!">＋ Zutat</button>
            </div>

            @error('ingredients') <p class="field-error mb-3">{{ $message }}</p> @enderror

            <div class="mb-2 hidden grid-cols-[5rem_5rem_1fr_auto_2rem] gap-2 px-1 text-xs font-semibold tracking-wider text-ink-soft uppercase sm:grid">
                <span>Menge</span><span>Einheit</span><span>Zutat</span><span>Optional</span><span></span>
            </div>

            <div class="space-y-2">
                <template x-for="(row, index) in rows" :key="row.key">
                    <div class="grid grid-cols-[4.5rem_4.5rem_1fr_2rem] items-center gap-2 sm:grid-cols-[5rem_5rem_1fr_auto_2rem]">
                        <input :name="`ingredients[${index}][amount]`" x-model="row.amount" class="field px-2.5!" placeholder="200" aria-label="Menge">
                        <input :name="`ingredients[${index}][unit]`" x-model="row.unit" class="field px-2.5!" placeholder="g" aria-label="Einheit">
                        <input :name="`ingredients[${index}][name]`" x-model="row.name" class="field" placeholder="Zutat" aria-label="Zutat">
                        <label class="order-last col-span-3 flex items-center gap-2 text-xs text-ink-soft sm:order-none sm:col-span-1 sm:justify-center">
                            <input type="checkbox" :name="`ingredients[${index}][is_optional]`" value="1" x-model="row.is_optional" class="size-4 accent-accent">
                            <span class="sm:sr-only">optional</span>
                        </label>
                        <button type="button" @click="remove(index)" class="grid size-8 place-items-center rounded-full text-ink-soft hover:bg-paper-deep hover:text-accent-dark" aria-label="Zutat entfernen">✕</button>
                    </div>
                </template>
            </div>

            @foreach ($errors->get('ingredients.*') as $messages)
                <p class="field-error">{{ $messages[0] }}</p>
            @endforeach
        </section>

        {{-- Zubereitung --}}
        <section class="card-section"
                 x-data="{
                     rows: @js($stepRows),
                     add() { this.rows.push({ body: '', key: Date.now() }) },
                     remove(index) { this.rows.splice(index, 1) },
                 }">
            <div class="mb-5 flex items-center justify-between">
                <h2 class="font-display text-xl font-semibold">Zubereitung *</h2>
                <button type="button" @click="add()" class="btn btn-ghost py-1.5!">＋ Schritt</button>
            </div>

            @error('steps') <p class="field-error mb-3">{{ $message }}</p> @enderror

            <ol class="space-y-3">
                <template x-for="(row, index) in rows" :key="row.key">
                    <li class="flex items-start gap-3">
                        <span class="mt-2 grid size-7 shrink-0 place-items-center rounded-full bg-paper-deep font-display text-sm font-semibold text-accent" x-text="index + 1"></span>
                        <textarea :name="`steps[${index}]`" x-model="row.body" rows="2" class="field" :aria-label="`Schritt ${index + 1}`"></textarea>
                        <button type="button" @click="remove(index)" class="mt-1.5 grid size-8 shrink-0 place-items-center rounded-full text-ink-soft hover:bg-paper-deep hover:text-accent-dark" aria-label="Schritt entfernen">✕</button>
                    </li>
                </template>
            </ol>

            @foreach ($errors->get('steps.*') as $messages)
                <p class="field-error">{{ $messages[0] }}</p>
            @endforeach
        </section>

        {{-- Tipps & Notizen --}}
        <section class="card-section space-y-5">
            <h2 class="font-display text-xl font-semibold">Tipps & Notizen</h2>

            <div>
                <label for="tips" class="field-label">Tipps & Varianten</label>
                <textarea id="tips" name="tips" rows="4" class="field" placeholder="Ein Tipp pro Zeile">{{ implode("\n", (array) $tips) }}</textarea>
                @error('tips.*') <p class="field-error">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="notes" class="field-label">Notizen</label>
                <textarea id="notes" name="notes" rows="3" class="field">{{ old('notes', $recipe->notes) }}</textarea>
                @error('notes') <p class="field-error">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="source" class="field-label">Quelle</label>
                <input id="source" name="source" value="{{ old('source', $recipe->source) }}" class="field" placeholder="Link, Buch oder Oma">
                @error('source') <p class="field-error">{{ $message }}</p> @enderror
            </div>
        </section>
    </div>

    {{-- Seitenleiste --}}
    <aside class="flex flex-col gap-8">
        <section class="card-section space-y-5">
            <h2 class="font-display text-xl font-semibold">Eckdaten</h2>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label for="servings" class="field-label">Portionen</label>
                    <input id="servings" type="number" name="servings" min="1" max="100" value="{{ old('servings', $recipe->servings) }}" class="field">
                    @error('servings') <p class="field-error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="difficulty" class="field-label">Schwierigkeit</label>
                    <select id="difficulty" name="difficulty" class="field">
                        <option value="">–</option>
                        @foreach ($difficulties as $difficulty)
                            <option value="{{ $difficulty->value }}" @selected(old('difficulty', $recipe->difficulty?->value) === $difficulty->value)>
                                {{ $difficulty->label() }}
                            </option>
                        @endforeach
                    </select>
                    @error('difficulty') <p class="field-error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="prep_minutes" class="field-label">Vorbereitung (Min.)</label>
                    <input id="prep_minutes" type="number" name="prep_minutes" min="0" max="1440" value="{{ old('prep_minutes', $recipe->prep_minutes) }}" class="field">
                    @error('prep_minutes') <p class="field-error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="cook_minutes" class="field-label">Kochen (Min.)</label>
                    <input id="cook_minutes" type="number" name="cook_minutes" min="0" max="1440" value="{{ old('cook_minutes', $recipe->cook_minutes) }}" class="field">
                    @error('cook_minutes') <p class="field-error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="calories" class="field-label">kcal / Portion</label>
                    <input id="calories" type="number" name="calories" min="0" value="{{ old('calories', $recipe->calories) }}" class="field">
                    @error('calories') <p class="field-error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="protein" class="field-label">Protein (g)</label>
                    <input id="protein" type="number" name="protein" min="0" value="{{ old('protein', $recipe->protein) }}" class="field">
                    @error('protein') <p class="field-error">{{ $message }}</p> @enderror
                </div>
            </div>
        </section>

        @php
            // Foto aus dem Import (noch im Zwischenspeicher) hat Vorrang vor dem bisherigen Foto
            $importedImage = old('imported_image');
            $imageUrl = $importedImage ? asset('storage/'.$importedImage) : $recipe->image_url;
        @endphp
        <section class="card-section space-y-5"
                 x-data="recipeAppearance({ emoji: @js(old('emoji', $recipe->emoji) ?: '🍽️'), color: @js(old('color', $recipe->color) ?: '#C8553D'), imageUrl: @js($imageUrl) })">
            <h2 class="font-display text-xl font-semibold">Aussehen</h2>

            <div class="recipe-tint relative grid aspect-[4/3] place-items-center overflow-hidden rounded-2xl text-7xl" :style="`--recipe: ${color}`">
                <img x-show="preview" :src="preview" alt="" class="absolute inset-0 size-full object-cover"
                     @if ($imageUrl) src="{{ $imageUrl }}" @else x-cloak @endif>
                <span x-show="! preview" x-text="emoji" aria-hidden="true">{{ $imageUrl ? '' : (old('emoji', $recipe->emoji) ?: '🍽️') }}</span>
                <span x-cloak x-show="processing" class="absolute inset-0 grid place-items-center bg-white/70 text-sm font-semibold text-ink">Foto wird verkleinert …</span>
            </div>

            <div class="grid grid-cols-[1fr_auto] gap-4">
                <div>
                    <label for="emoji" class="field-label">Emoji</label>
                    <input id="emoji" name="emoji" x-model="emoji" maxlength="16" class="field text-lg">
                    @error('emoji') <p class="field-error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="color" class="field-label">Farbe</label>
                    <input id="color" type="color" name="color" x-model="color" class="h-[42px] w-16 cursor-pointer rounded-xl border border-line bg-white p-1">
                    @error('color') <p class="field-error">{{ $message }}</p> @enderror
                </div>
            </div>

            <div>
                <label for="image" class="field-label">Foto</label>
                <input id="image" type="file" name="image" accept="image/*" @change="onFileChange($event)"
                       class="block w-full text-sm text-ink-soft file:mr-3 file:rounded-full file:border-0 file:bg-paper-deep file:px-4 file:py-2 file:text-sm file:font-semibold file:text-ink hover:file:bg-line">
                <p class="mt-1 text-xs text-ink-soft" x-show="! info">Am iPhone/iPad direkt mit der Kamera möglich – große Fotos werden automatisch verkleinert.</p>
                <p class="mt-1 text-xs text-herb" x-cloak x-show="info" x-text="info"></p>
                @error('image') <p class="field-error">{{ $message }}</p> @enderror

                @if ($importedImage)
                    <input type="hidden" name="imported_image" value="{{ $importedImage }}">
                    <p class="mt-2 text-xs text-herb">Foto aus dem Import wird übernommen.</p>
                @endif

                @if ($recipe->image_path || $importedImage)
                    <label class="mt-3 flex items-center gap-2 text-sm text-ink-soft">
                        <input type="checkbox" name="remove_image" value="1" @checked(old('remove_image')) class="size-4 accent-accent">
                        {{ $importedImage ? 'Importiertes Foto nicht übernehmen' : 'Aktuelles Foto entfernen' }}
                    </label>
                @endif
            </div>
        </section>

        <div class="flex items-center gap-3 lg:sticky lg:top-24">
            <button type="submit" class="btn btn-primary flex-1">{{ $submitLabel }}</button>
            <a href="{{ $cancelUrl }}" class="btn btn-ghost">Abbrechen</a>
        </div>
    </aside>
</form>
