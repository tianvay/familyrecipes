<x-layouts.guest title="Anmelden">
    <form method="POST" action="{{ route('login') }}" class="card-section space-y-5">
        @csrf

        <div>
            <h1 class="font-display text-2xl font-semibold">Willkommen zurück</h1>
            <p class="mt-1 text-sm text-ink-soft">Melde dich an, um deine Lieblingsrezepte zu sehen.</p>
        </div>

        <div>
            <label for="username" class="field-label">Benutzername</label>
            <input id="username" name="username" value="{{ old('username') }}" required autofocus
                   autocomplete="username" autocapitalize="none" class="field">
            @error('username') <p class="field-error">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="password" class="field-label">Passwort</label>
            <input id="password" type="password" name="password" required autocomplete="current-password" class="field">
            @error('password') <p class="field-error">{{ $message }}</p> @enderror
        </div>

        <label class="flex items-center gap-2 text-sm text-ink-soft">
            {{-- Standardmäßig an: Familien-App auf dem eigenen Gerät; nach Fehleingabe bleibt die eigene Wahl erhalten --}}
            <input type="checkbox" name="remember" value="1" @checked($errors->any() ? old('remember') : true) class="size-4 accent-accent">
            Angemeldet bleiben
        </label>

        <button type="submit" class="btn btn-primary w-full">Anmelden</button>
    </form>

    <p class="mt-6 text-center text-xs text-ink-soft">Noch kein Zugang? Frag das Familienoberhaupt.</p>
</x-layouts.guest>
