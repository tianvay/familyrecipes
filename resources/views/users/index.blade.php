<x-layouts.app title="Benutzer verwalten">
    <div class="mb-8 flex flex-wrap items-end justify-between gap-4">
        <div>
            <p class="mb-2 text-sm font-semibold tracking-widest text-accent uppercase">Familie</p>
            <h1 class="font-display text-4xl font-semibold tracking-tight">Benutzer verwalten</h1>
        </div>
        <a href="{{ route('users.create') }}" class="btn btn-primary">＋ Mitglied anlegen</a>
    </div>

    <ul class="divide-y divide-line overflow-hidden rounded-3xl border border-line bg-white">
        @foreach ($users as $user)
            <li class="flex flex-wrap items-center gap-4 p-5 sm:px-6">
                <span class="grid size-11 shrink-0 place-items-center rounded-full bg-paper-deep font-display font-semibold">
                    {{ $user->initials() }}
                </span>

                <div class="min-w-0 flex-1">
                    <p class="font-semibold">
                        {{ $user->name }}
                        @if ($user->is(auth()->user()))
                            <span class="text-sm font-normal text-ink-soft">(du)</span>
                        @endif
                    </p>
                    <p class="text-sm text-ink-soft">
                        {{ '@'.$user->username }} · {{ $user->favorite_recipes_count }} {{ $user->favorite_recipes_count === 1 ? 'Favorit' : 'Favoriten' }}
                    </p>
                </div>

                <span @class([
                    'chip',
                    'border-accent/30 bg-accent/10 text-accent-dark' => $user->role === App\Enums\Role::Admin,
                    'border-herb/30 bg-herb/10 text-herb' => $user->role === App\Enums\Role::Child,
                ])>{{ $user->role->label() }}</span>

                <div class="flex items-center gap-1">
                    <a href="{{ route('users.edit', $user) }}" class="btn btn-ghost py-1.5!">Bearbeiten</a>
                    @can('delete', $user)
                        <form method="POST" action="{{ route('users.destroy', $user) }}"
                              onsubmit="return confirm(@js("{$user->name} wirklich löschen? Die Favoriten dieser Person gehen verloren."))">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-danger py-1.5!">Löschen</button>
                        </form>
                    @endcan
                </div>
            </li>
        @endforeach
    </ul>
</x-layouts.app>
