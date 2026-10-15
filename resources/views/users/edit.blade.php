<x-layouts.app :title="$user->name . ' bearbeiten'">
    <div class="mx-auto max-w-xl">
        <a href="{{ route('users.index') }}" class="mb-6 inline-flex items-center gap-1 text-sm font-medium text-ink-soft hover:text-ink">
            ← Benutzer verwalten
        </a>
        <h1 class="mb-8 font-display text-4xl font-semibold tracking-tight">{{ $user->name }} bearbeiten</h1>

        @include('users.partials.form', [
            'action' => route('users.update', $user),
            'method' => 'PUT',
            'submitLabel' => 'Änderungen speichern',
        ])
    </div>
</x-layouts.app>
