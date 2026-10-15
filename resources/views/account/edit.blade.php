<x-layouts.app title="Mein Konto">
    <div class="mx-auto max-w-xl">
        <h1 class="mb-2 font-display text-4xl font-semibold tracking-tight">Mein Konto</h1>
        <p class="mb-8 text-ink-soft">
            Angemeldet als <strong class="text-ink">{{ $user->name }}</strong> ({{ '@'.$user->username }}).
            Name und Benutzername ändert das Familienoberhaupt.
        </p>

        <form method="POST" action="{{ route('account.password') }}" class="card-section space-y-5">
            @csrf
            @method('PUT')

            <h2 class="font-display text-xl font-semibold">Passwort ändern</h2>

            <div>
                <label for="current_password" class="field-label">Aktuelles Passwort</label>
                <input id="current_password" type="password" name="current_password" required autocomplete="current-password" class="field">
                @error('current_password', 'password') <p class="field-error">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="password" class="field-label">Neues Passwort</label>
                <input id="password" type="password" name="password" required autocomplete="new-password" class="field">
                @error('password', 'password') <p class="field-error">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="password_confirmation" class="field-label">Neues Passwort wiederholen</label>
                <input id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password" class="field">
            </div>

            <button type="submit" class="btn btn-primary">Passwort speichern</button>
        </form>
    </div>
</x-layouts.app>
