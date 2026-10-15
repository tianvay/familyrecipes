<x-layouts.app :title="$recipe->title">
    <article style="--recipe: {{ $recipe->accent_color }}">
        <a href="{{ route('recipes.index') }}" class="mb-6 inline-flex items-center gap-1 text-sm font-medium text-ink-soft hover:text-ink">
            ← Alle Rezepte
        </a>

        {{-- Kopfbereich --}}
        <header class="recipe-tint mb-10 grid overflow-hidden rounded-[2rem] md:grid-cols-[1.1fr_1fr]">
            <div class="flex flex-col justify-center gap-5 p-8 sm:p-12">
                @if ($recipe->tags->isNotEmpty())
                    <div class="flex flex-wrap gap-2">
                        @foreach ($recipe->tags as $tag)
                            <a href="{{ route('recipes.index', ['tag' => $tag->slug]) }}" class="chip">{{ $tag->name }}</a>
                        @endforeach
                    </div>
                @endif

                <h1 class="font-display text-4xl leading-tight font-semibold tracking-tight sm:text-5xl">{{ $recipe->title }}</h1>

                @if ($recipe->description || $recipe->summary)
                    <p class="text-lg leading-relaxed text-ink/80">{{ $recipe->description ?: $recipe->summary }}</p>
                @endif

                <div class="flex flex-wrap items-center gap-2 pt-2">
                    <form method="POST" action="{{ route('recipes.favorite', $recipe) }}">
                        @csrf
                        @method('PATCH')
                        <button type="submit" class="btn btn-ghost" aria-pressed="{{ $isFavorite ? 'true' : 'false' }}">
                            <span @class(['text-accent' => $isFavorite])>{{ $isFavorite ? '♥' : '♡' }}</span>
                            {{ $isFavorite ? 'Mein Favorit' : 'Merken' }}
                        </button>
                    </form>

                    {{-- Schnell für den Essensplan vorschlagen --}}
                    <div class="relative" x-data="{ open: false }" @click.outside="open = false">
                        <button type="button" class="btn btn-ghost" @click="open = ! open" :aria-expanded="open">📅 Vorschlagen</button>
                        <div x-cloak x-show="open" x-transition.origin.top.left
                             class="absolute left-0 z-10 mt-2 w-56 overflow-hidden rounded-2xl border border-line bg-white py-2 text-sm shadow-[0_12px_32px_-12px_rgb(43_36_32/0.25)]">
                            <p class="px-4 pb-1 text-xs text-ink-soft">Für den Essensplan vorschlagen:</p>
                            @foreach ([App\Support\Week::current(), App\Support\Week::current()->next()] as $suggestWeek)
                                <form method="POST" action="{{ route('meal-suggestions.store', $suggestWeek) }}">
                                    @csrf
                                    <input type="hidden" name="recipe_id" value="{{ $recipe->id }}">
                                    <button type="submit" class="block w-full px-4 py-2 text-left hover:bg-paper">
                                        {{ $suggestWeek->relativeName() }}
                                        <span class="text-xs text-ink-soft">(KW {{ $suggestWeek->number() }})</span>
                                    </button>
                                </form>
                            @endforeach
                        </div>
                    </div>

                    @can('update', $recipe)
                        <a href="{{ route('recipes.edit', $recipe) }}" class="btn btn-ghost">Bearbeiten</a>
                    @endcan
                    @can('delete', $recipe)
                        <form method="POST" action="{{ route('recipes.destroy', $recipe) }}"
                              onsubmit="return confirm('Dieses Rezept wirklich löschen?')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-danger">Löschen</button>
                        </form>
                    @endcan
                </div>

                @error('recipe_id')
                    <p class="field-error" role="alert">{{ $message }}</p>
                @enderror

                <p class="text-xs text-ink-soft">
                    @if ($revision = $recipe->latestRevision)
                        Zuletzt {{ $revision->action->verb() }} von {{ $revision->authorName() }}
                        {{ $revision->created_at->diffForHumans() }} ·
                    @endif
                    <a href="{{ route('recipes.history', $recipe) }}" class="underline decoration-line underline-offset-2 hover:text-ink">Verlauf ansehen</a>
                </p>
            </div>

            <div class="relative min-h-64">
                @if ($recipe->image_url)
                    <img src="{{ $recipe->image_url }}" alt="{{ $recipe->title }}" class="absolute inset-0 size-full object-cover">
                @else
                    <span class="absolute inset-0 grid place-items-center text-[9rem]" aria-hidden="true">{{ $recipe->emoji ?: '🍽️' }}</span>
                @endif
            </div>
        </header>

        {{-- Eckdaten --}}
        @php
            $facts = array_filter([
                'Portionen' => $recipe->servings,
                'Vorbereitung' => $recipe->prep_minutes ? "{$recipe->prep_minutes} Min." : null,
                'Kochen/Backen' => $recipe->cook_minutes ? "{$recipe->cook_minutes} Min." : null,
                'Schwierigkeit' => $recipe->difficulty?->label(),
                'Kalorien' => $recipe->calories ? "{$recipe->calories} kcal" : null,
                'Protein' => $recipe->protein ? "{$recipe->protein} g" : null,
            ]);
        @endphp

        @if ($facts)
            <dl class="mb-10 grid grid-cols-2 divide-line overflow-hidden rounded-3xl border border-line bg-white sm:grid-cols-3 lg:grid-cols-6 lg:divide-x">
                @foreach ($facts as $label => $value)
                    <div class="p-5">
                        <dt class="text-xs font-semibold tracking-wider text-ink-soft uppercase">{{ $label }}</dt>
                        <dd class="mt-1 font-display text-xl font-semibold">{{ $value }}</dd>
                    </div>
                @endforeach
            </dl>
        @endif

        <div class="grid gap-8 lg:grid-cols-[minmax(0,1fr)_minmax(0,1.6fr)]">
            {{-- Zutaten --}}
            <section class="card-section self-start lg:sticky lg:top-24" x-data="{ checked: [] }">
                <h2 class="mb-1 font-display text-2xl font-semibold">Zutaten</h2>
                @if ($recipe->servings)
                    <p class="mb-5 text-sm text-ink-soft">für {{ $recipe->servings }} {{ $recipe->servings === 1 ? 'Portion' : 'Portionen' }}</p>
                @endif

                <ul class="divide-y divide-line">
                    @foreach ($recipe->ingredients as $ingredient)
                        <li>
                            <label class="flex cursor-pointer items-baseline gap-3 py-2.5"
                                   :class="checked.includes({{ $ingredient->id }}) && 'opacity-40 line-through'">
                                <input type="checkbox" value="{{ $ingredient->id }}" x-model.number="checked"
                                       class="size-4 translate-y-0.5 rounded accent-accent">
                                <span class="w-20 shrink-0 font-semibold tabular-nums">
                                    {{ trim("{$ingredient->amount} {$ingredient->unit}") }}
                                </span>
                                <span>
                                    {{ $ingredient->name }}
                                    @if ($ingredient->is_optional)
                                        <span class="text-xs text-ink-soft">(optional)</span>
                                    @endif
                                </span>
                            </label>
                        </li>
                    @endforeach
                </ul>
            </section>

            <div class="flex flex-col gap-8">
                {{-- Zubereitung --}}
                <section class="card-section" x-data="{ done: [] }">
                    <h2 class="mb-6 font-display text-2xl font-semibold">Zubereitung</h2>
                    <ol class="space-y-5">
                        @foreach ($recipe->steps as $step)
                            <li class="flex gap-4">
                                <button type="button"
                                        @click="done.includes({{ $loop->iteration }}) ? done.splice(done.indexOf({{ $loop->iteration }}), 1) : done.push({{ $loop->iteration }})"
                                        :class="done.includes({{ $loop->iteration }}) ? 'bg-herb text-white border-herb' : 'border-line text-accent'"
                                        class="grid size-9 shrink-0 place-items-center rounded-full border-2 font-display font-semibold transition"
                                        aria-label="Schritt {{ $loop->iteration }} als erledigt markieren">
                                    <span x-text="done.includes({{ $loop->iteration }}) ? '✓' : '{{ $loop->iteration }}'">{{ $loop->iteration }}</span>
                                </button>
                                <p class="pt-1.5 leading-relaxed" :class="done.includes({{ $loop->iteration }}) && 'text-ink-soft'">
                                    {{ $step->body }}
                                </p>
                            </li>
                        @endforeach
                    </ol>
                </section>

                @if ($recipe->tips)
                    <section class="rounded-3xl border border-herb/25 bg-herb/5 p-6 sm:p-8">
                        <h2 class="mb-4 font-display text-xl font-semibold text-herb">Tipps & Varianten</h2>
                        <ul class="space-y-2">
                            @foreach ($recipe->tips as $tip)
                                <li class="flex gap-3"><span class="text-herb" aria-hidden="true">✦</span> {{ $tip }}</li>
                            @endforeach
                        </ul>
                    </section>
                @endif

                @if ($recipe->notes || $recipe->source)
                    <section class="card-section text-sm text-ink-soft">
                        @if ($recipe->notes)
                            <h2 class="mb-2 font-display text-lg font-semibold text-ink">Notizen</h2>
                            <p class="whitespace-pre-line">{{ $recipe->notes }}</p>
                        @endif
                        @if ($recipe->source)
                            <p @class(['mt-4' => $recipe->notes])>
                                Quelle:
                                @if (Str::startsWith($recipe->source, ['https://', 'http://']))
                                    <a href="{{ $recipe->source }}" class="underline hover:text-ink" target="_blank" rel="noopener">{{ $recipe->source }}</a>
                                @else
                                    {{ $recipe->source }}
                                @endif
                            </p>
                        @endif
                    </section>
                @endif
            </div>
        </div>
    </article>
</x-layouts.app>
