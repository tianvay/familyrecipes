<x-layouts.app title="Mitglied anlegen">
    <div class="mx-auto max-w-xl">
        <a href="{{ route('users.index') }}" class="mb-6 inline-flex items-center gap-1 text-sm font-medium text-ink-soft hover:text-ink">
            ← Benutzer verwalten
        </a>
        <h1 class="mb-8 font-display text-4xl font-semibold tracking-tight">Mitglied anlegen</h1>

        @include('users.partials.form', [
            'action' => route('users.store'),
            'method' => 'POST',
            'submitLabel' => 'Mitglied anlegen',
        ])
    </div>
</x-layouts.app>
