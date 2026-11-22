<x-layouts.app title="Mein Konto">
    <div class="mx-auto max-w-xl">
        <h1 class="mb-2 font-display text-4xl font-semibold tracking-tight">Mein Konto</h1>
        <p class="mb-8 text-ink-soft">
            Angemeldet als <strong class="text-ink">{{ $user->name }}</strong> ({{ '@'.$user->username }}).
            Name und Benutzername ändert das Familienoberhaupt.
        </p>

        {{-- Farbdesign: Auswahl wird sofort gespeichert --}}
        <form method="POST" action="{{ route('account.theme') }}" class="card-section mb-8 space-y-5">
            @csrf
            @method('PUT')

            <div>
                <h2 class="font-display text-xl font-semibold">Design</h2>
                <p class="mt-1 text-sm text-ink-soft">Gilt nur für dich – die anderen behalten ihr eigenes Design.</p>
            </div>

            <div class="grid gap-3 sm:grid-cols-3">
                @foreach (App\Enums\Theme::cases() as $theme)
                    <label class="group cursor-pointer">
                        <input type="radio" name="theme" value="{{ $theme->value }}" class="peer sr-only"
                               @checked(($user->theme ?? App\Enums\Theme::Paper) === $theme) onchange="this.form.requestSubmit()">
                        <span class="flex h-full flex-col gap-3 rounded-2xl border-2 border-line p-3 transition group-hover:border-ink-soft peer-checked:border-accent peer-focus-visible:ring-2 peer-focus-visible:ring-accent/40">
                            <span class="flex h-16 overflow-hidden rounded-xl border border-line" aria-hidden="true">
                                @foreach ($theme->previews() as [$bg, $card, $text, $accent, $herb])
                                    <span class="flex flex-1 items-center gap-1.5 p-2" style="background: {{ $bg }}">
                                        <span class="flex h-full flex-1 flex-col justify-center gap-1 rounded-md px-1.5" style="background: {{ $card }}">
                                            <span class="h-1.5 w-3/4 rounded-full" style="background: {{ $text }}"></span>
                                            <span class="h-1.5 w-1/2 rounded-full" style="background: {{ $herb }}"></span>
                                        </span>
                                        <span class="size-4 shrink-0 rounded-full" style="background: {{ $accent }}"></span>
                                    </span>
                                @endforeach
                            </span>
                            <span>
                                <span class="block text-sm font-semibold">{{ $theme->label() }}</span>
                                <span class="mt-0.5 block text-xs text-ink-soft">{{ $theme->description() }}</span>
                            </span>
                        </span>
                    </label>
                @endforeach
            </div>

            @error('theme') <p class="field-error">{{ $message }}</p> @enderror

            <noscript><button type="submit" class="btn btn-primary">Design speichern</button></noscript>
        </form>

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
