<x-layouts.guest title="Kein Zugriff">
    <div class="card-section space-y-4 text-center">
        <span class="text-5xl" aria-hidden="true">🔒</span>
        <h1 class="font-display text-2xl font-semibold">Kein Zugriff</h1>
        <p class="text-sm text-ink-soft">Dafür fehlt deinem Account die Berechtigung. Frag bei Bedarf das Familienoberhaupt.</p>
        <a href="{{ route('recipes.index') }}" class="btn btn-primary">Zu den Rezepten</a>
    </div>
</x-layouts.guest>
