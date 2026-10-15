<x-layouts.app title="Änderungsprotokoll">
    <div class="mx-auto max-w-3xl">
        <p class="mb-2 text-sm font-semibold tracking-widest text-accent uppercase">Familie</p>
        <h1 class="font-display text-4xl font-semibold tracking-tight">Änderungsprotokoll</h1>
        <p class="mt-3 mb-8 text-ink-soft">Wer hat wann welches Rezept angelegt, geändert oder gelöscht.</p>

        @if ($revisions->isEmpty())
            <p class="rounded-3xl border border-dashed border-line px-6 py-10 text-center text-ink-soft">
                Bisher wurden keine Änderungen protokolliert.
            </p>
        @else
            <ol class="divide-y divide-line overflow-hidden rounded-3xl border border-line bg-white">
                @foreach ($revisions as $revision)
                    @include('revisions.partials.entry', ['revision' => $revision, 'showRecipe' => true])
                @endforeach
            </ol>

            <div class="mt-8">{{ $revisions->links() }}</div>
        @endif
    </div>
</x-layouts.app>
