<form method="POST" action="{{ $action }}" class="card-section space-y-5">
    @csrf
    @method($method)

    <div>
        <label for="name" class="field-label">Name *</label>
        <input id="name" name="name" value="{{ old('name', $user->name) }}" required maxlength="100" class="field" placeholder="z. B. Anna">
        @error('name') <p class="field-error">{{ $message }}</p> @enderror
    </div>

    <div>
        <label for="username" class="field-label">Benutzername *</label>
        <input id="username" name="username" value="{{ old('username', $user->username) }}" required maxlength="50"
               autocomplete="off" autocapitalize="none" class="field" placeholder="anna">
        <p class="mt-1 text-xs text-ink-soft">Zum Anmelden. Nur Buchstaben, Zahlen, - und _.</p>
        @error('username') <p class="field-error">{{ $message }}</p> @enderror
    </div>

    <div class="grid gap-5 sm:grid-cols-2">
        <div>
            <label for="password" class="field-label">{{ $user->exists ? 'Neues Passwort' : 'Passwort *' }}</label>
            <input id="password" type="password" name="password" @required(! $user->exists) autocomplete="new-password" class="field">
            @if ($user->exists)
                <p class="mt-1 text-xs text-ink-soft">Leer lassen, um es nicht zu ändern.</p>
            @endif
            @error('password') <p class="field-error">{{ $message }}</p> @enderror
        </div>
        <div>
            <label for="password_confirmation" class="field-label">Passwort wiederholen</label>
            <input id="password_confirmation" type="password" name="password_confirmation" autocomplete="new-password" class="field">
        </div>
    </div>

    <fieldset>
        <legend class="field-label">Rolle *</legend>
        <div class="grid gap-2">
            @foreach ($roles as $role)
                <label class="flex cursor-pointer items-start gap-3 rounded-2xl border border-line p-4 text-sm transition hover:border-ink-soft has-[:checked]:border-accent has-[:checked]:bg-accent/5">
                    <input type="radio" name="role" value="{{ $role->value }}" required
                           @checked(old('role', $user->role?->value) === $role->value) class="mt-0.5 size-4 shrink-0 accent-accent">
                    <span>
                        <span class="block font-semibold">{{ $role->label() }}</span>
                        <span class="block text-xs leading-relaxed text-ink-soft">{{ $role->description() }}</span>
                    </span>
                </label>
            @endforeach
        </div>
        @error('role') <p class="field-error">{{ $message }}</p> @enderror
    </fieldset>

    <div class="flex items-center gap-3 pt-2">
        <button type="submit" class="btn btn-primary">{{ $submitLabel }}</button>
        <a href="{{ route('users.index') }}" class="btn btn-ghost">Abbrechen</a>
    </div>
</form>
