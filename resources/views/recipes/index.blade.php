<x-layouts.app :title="request()->boolean('favoriten') ? 'Meine Favoriten' : 'Alle Rezepte'">
    <section class="mb-10 max-w-2xl">
        <p class="mb-2 text-sm font-semibold tracking-widest text-accent uppercase">Hallo {{ auth()->user()->name }}</p>
        <h1 class="font-display text-4xl leading-tight font-semibold tracking-tight sm:text-5xl">
            {{ request()->boolean('favoriten') ? 'Meine Lieblingsrezepte' : 'Alle Lieblingsrezepte an einem Ort.' }}
        </h1>
    </section>

    <form method="GET" action="{{ route('recipes.index') }}" class="mb-6" role="search">
        @if (request('tag'))
            <input type="hidden" name="tag" value="{{ request('tag') }}">
        @endif
        @if (request()->boolean('favoriten'))
            <input type="hidden" name="favoriten" value="1">
        @endif

        <label for="q" class="sr-only">Rezepte durchsuchen</label>
        <div class="relative max-w-xl">
            <span class="pointer-events-none absolute inset-y-0 left-4 grid place-items-center text-ink-soft" aria-hidden="true">⌕</span>
            <input id="q" type="search" name="q" value="{{ request('q') }}"
                   placeholder="Rezept oder Zutat suchen …"
                   class="field rounded-full! py-3! pr-28 pl-10">
            <button type="submit" class="btn btn-primary absolute inset-y-1.5 right-1.5 py-0!">Suchen</button>
        </div>
    </form>

    @if ($tags->isNotEmpty())
        <nav class="mb-10 flex flex-wrap gap-2" aria-label="Nach Tag filtern">
            <a href="{{ route('recipes.index', request()->only('q', 'favoriten')) }}"
               @class(['chip', 'chip-active' => ! request('tag')])>Alle</a>
            @foreach ($tags as $tag)
                <a href="{{ route('recipes.index', [...request()->only('q', 'favoriten'), 'tag' => $tag->slug]) }}"
                   @class(['chip', 'chip-active' => request('tag') === $tag->slug])>
                    {{ $tag->name }}
                    <span class="ml-1.5 opacity-60">{{ $tag->recipes_count }}</span>
                </a>
            @endforeach
        </nav>
    @endif

    @if ($recipes->isEmpty())
        <div class="card-section flex flex-col items-center gap-4 py-16 text-center">
            <span class="text-5xl" aria-hidden="true">🥄</span>
            <h2 class="font-display text-2xl font-semibold">Keine Rezepte gefunden</h2>
            <p class="max-w-sm text-ink-soft">
                @if (request()->hasAny(['q', 'tag', 'favoriten']))
                    Probier einen anderen Suchbegriff oder setze die Filter zurück.
                @else
                    Dein Rezeptbuch ist noch leer – Zeit für das erste Rezept!
                @endif
            </p>
            <div class="flex gap-3">
                @if (request()->hasAny(['q', 'tag', 'favoriten']))
                    <a href="{{ route('recipes.index') }}" class="btn btn-ghost">Filter zurücksetzen</a>
                @endif
                @can('create', App\Models\Recipe::class)
                    <a href="{{ route('recipes.create') }}" class="btn btn-primary">Rezept anlegen</a>
                @endcan
            </div>
        </div>
    @else
        <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($recipes as $recipe)
                <x-recipe-card :recipe="$recipe" :favorite="$recipe->is_favorite" />
            @endforeach
        </div>

        <div class="mt-10">
            {{ $recipes->links() }}
        </div>
    @endif
</x-layouts.app>
