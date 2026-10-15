<x-layouts.app :title="'Verlauf: ' . $recipe->title">
    <div class="mx-auto max-w-3xl">
        <a href="{{ route('recipes.show', $recipe) }}" class="mb-6 inline-flex items-center gap-1 text-sm font-medium text-ink-soft hover:text-ink">
            ← Zurück zum Rezept
        </a>

        <p class="mb-2 text-sm font-semibold tracking-widest text-accent uppercase">Verlauf</p>
        <h1 class="mb-8 font-display text-4xl font-semibold tracking-tight">{{ $recipe->title }}</h1>

        @if ($revisions->isEmpty())
            <p class="rounded-3xl border border-dashed border-line px-6 py-10 text-center text-ink-soft">
                Für dieses Rezept wurden noch keine Änderungen protokolliert.
            </p>
        @else
            <ol class="divide-y divide-line overflow-hidden rounded-3xl border border-line bg-white">
                @foreach ($revisions as $revision)
                    @include('revisions.partials.entry', ['revision' => $revision])
                @endforeach
            </ol>

            <div class="mt-8">{{ $revisions->links() }}</div>
        @endif
    </div>
</x-layouts.app>
