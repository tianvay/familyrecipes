<x-layouts.app title="Neues Rezept">
    <a href="{{ route('recipes.index') }}" class="mb-6 inline-flex items-center gap-1 text-sm font-medium text-ink-soft hover:text-ink">
        ← Alle Rezepte
    </a>
    <div class="mb-8 flex flex-wrap items-end justify-between gap-4">
        <h1 class="font-display text-4xl font-semibold tracking-tight">Neues Rezept</h1>
        @unless (old('source') && old('title'))
            <a href="{{ route('recipes.import') }}" class="btn btn-ghost">🔗 Aus Link importieren</a>
        @endunless
    </div>

    @include('recipes.partials.form', [
        'action' => route('recipes.store'),
        'method' => 'POST',
        'submitLabel' => 'Rezept speichern',
        'cancelUrl' => route('recipes.index'),
    ])
</x-layouts.app>
