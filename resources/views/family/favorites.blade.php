<x-layouts.app title="Lieblingsrezepte der Familie">
    <section class="mb-10 max-w-2xl">
        <p class="mb-2 text-sm font-semibold tracking-widest text-accent uppercase">Familie</p>
        <h1 class="font-display text-4xl leading-tight font-semibold tracking-tight sm:text-5xl">Wer mag was?</h1>
        <p class="mt-3 text-ink-soft">Die Lieblingsrezepte aller Familienmitglieder auf einen Blick.</p>
    </section>

    @if ($popular->isNotEmpty() || $mostWished->isNotEmpty())
        <div class="mb-10 grid gap-6 lg:grid-cols-2">
            {{-- Beliebteste Rezepte (Favoriten) --}}
            @if ($popular->isNotEmpty())
                <section class="card-section">
                    <h2 class="mb-1 font-display text-2xl font-semibold">Am beliebtesten</h2>
                    <p class="mb-4 text-sm text-ink-soft">Top 5 – von den meisten als Favorit markiert.</p>
                    <ol class="divide-y divide-line">
                        @foreach ($popular as $recipe)
                            <li class="flex flex-wrap items-center gap-x-4 gap-y-2 py-3" style="--recipe: {{ $recipe->accent_color }}">
                                <span class="w-6 font-display text-lg font-semibold text-ink-soft tabular-nums">{{ $loop->iteration }}</span>
                                <span class="recipe-tint grid size-11 shrink-0 place-items-center rounded-xl text-2xl" aria-hidden="true">{{ $recipe->emoji ?: '🍽️' }}</span>
                                <a href="{{ route('recipes.show', $recipe) }}" class="min-w-0 flex-1 font-semibold hover:text-accent-dark">{{ $recipe->title }}</a>
                                <div class="flex items-center gap-3">
                                    <div class="flex -space-x-2">
                                        @foreach ($recipe->favoritedBy as $member)
                                            <span class="grid size-8 place-items-center rounded-full border-2 border-white bg-paper-deep text-xs font-semibold" title="{{ $member->name }}">
                                                {{ $member->initials() }}
                                            </span>
                                        @endforeach
                                    </div>
                                    <span class="text-sm font-medium text-accent">♥ {{ $recipe->favorited_by_count }}</span>
                                </div>
                            </li>
                        @endforeach
                    </ol>
                </section>
            @endif

            {{-- Am häufigsten gewünscht (alle Wünsche, auch erfüllte) --}}
            @if ($mostWished->isNotEmpty())
                <section class="card-section">
                    <h2 class="mb-1 font-display text-2xl font-semibold">Am häufigsten gewünscht</h2>
                    <p class="mb-4 text-sm text-ink-soft">Top 5 – alle Wünsche, auch die schon eingeplanten.</p>
                    <ol class="divide-y divide-line">
                        @foreach ($mostWished as $dish)
                            <li class="flex flex-wrap items-center gap-x-4 gap-y-2 py-3" @if ($dish['recipe']) style="--recipe: {{ $dish['recipe']->accent_color }}" @endif>
                                <span class="w-6 font-display text-lg font-semibold text-ink-soft tabular-nums">{{ $loop->iteration }}</span>
                                <span class="recipe-tint grid size-11 shrink-0 place-items-center rounded-xl text-2xl" aria-hidden="true">{{ $dish['recipe']?->emoji ?: '✏️' }}</span>
                                @if ($dish['recipe'])
                                    <a href="{{ route('recipes.show', $dish['recipe']) }}" class="min-w-0 flex-1 font-semibold hover:text-accent-dark">{{ $dish['title'] }}</a>
                                @else
                                    <span class="min-w-0 flex-1 font-semibold">{{ $dish['title'] }}</span>
                                @endif
                                <div class="flex items-center gap-3">
                                    <div class="flex -space-x-2">
                                        @foreach ($dish['wishers'] as $member)
                                            <span class="grid size-8 place-items-center rounded-full border-2 border-white bg-paper-deep text-xs font-semibold" title="{{ $member->name }}">
                                                {{ $member->initials() }}
                                            </span>
                                        @endforeach
                                    </div>
                                    <span class="text-sm font-medium text-accent">⭐ {{ $dish['count'] }}×</span>
                                </div>
                            </li>
                        @endforeach
                    </ol>
                </section>
            @endif
        </div>
    @endif

    {{-- Pro Familienmitglied --}}
    <div class="space-y-10">
        @foreach ($members as $member)
            <section>
                <div class="mb-4 flex items-center gap-3">
                    <span class="grid size-10 place-items-center rounded-full bg-ink font-display text-sm font-semibold text-paper">{{ $member->initials() }}</span>
                    <h2 class="font-display text-2xl font-semibold">{{ $member->name }}</h2>
                    <span class="text-sm text-ink-soft">
                        {{ $member->favoriteRecipes->count() }} {{ $member->favoriteRecipes->count() === 1 ? 'Favorit' : 'Favoriten' }}
                    </span>
                </div>

                @if ($member->favoriteRecipes->isEmpty())
                    <p class="rounded-3xl border border-dashed border-line px-6 py-8 text-center text-sm text-ink-soft">
                        Noch keine Favoriten markiert.
                    </p>
                @else
                    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        @foreach ($member->favoriteRecipes as $recipe)
                            <a href="{{ route('recipes.show', $recipe) }}" style="--recipe: {{ $recipe->accent_color }}"
                               class="group flex items-center gap-3 rounded-2xl border border-line bg-white p-3 transition hover:border-ink-soft">
                                <span class="recipe-tint grid size-12 shrink-0 place-items-center rounded-xl text-2xl" aria-hidden="true">{{ $recipe->emoji ?: '🍽️' }}</span>
                                <span class="text-sm leading-snug font-semibold group-hover:text-accent-dark">{{ $recipe->title }}</span>
                            </a>
                        @endforeach
                    </div>
                @endif
            </section>
        @endforeach
    </div>
</x-layouts.app>
