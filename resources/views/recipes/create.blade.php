<x-layouts.app title="Neues Rezept">
    <a href="{{ route('recipes.index') }}" class="mb-6 inline-flex items-center gap-1 text-sm font-medium text-ink-soft hover:text-ink">
        ← Alle Rezepte
    </a>
    <h1 class="mb-8 font-display text-4xl font-semibold tracking-tight">Neues Rezept</h1>

    @include('recipes.partials.form', [
        'action' => route('recipes.store'),
        'method' => 'POST',
        'submitLabel' => 'Rezept speichern',
        'cancelUrl' => route('recipes.index'),
    ])
</x-layouts.app>
