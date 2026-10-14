<x-layouts.app :title="$recipe->title . ' bearbeiten'">
    <a href="{{ route('recipes.show', $recipe) }}" class="mb-6 inline-flex items-center gap-1 text-sm font-medium text-ink-soft hover:text-ink">
        ← Zurück zum Rezept
    </a>
    <h1 class="mb-8 font-display text-4xl font-semibold tracking-tight">{{ $recipe->title }} bearbeiten</h1>

    @include('recipes.partials.form', [
        'action' => route('recipes.update', $recipe),
        'method' => 'PUT',
        'submitLabel' => 'Änderungen speichern',
        'cancelUrl' => route('recipes.show', $recipe),
    ])
</x-layouts.app>
