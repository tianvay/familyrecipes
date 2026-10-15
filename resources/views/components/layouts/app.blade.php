@props(['title' => null])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    @include('partials.head', ['title' => $title])
</head>
<body class="min-h-screen">
    @php
        $navLink = 'rounded-full px-3 py-1.5 hover:text-ink';
        $navActive = 'text-ink bg-paper-deep';
    @endphp

    <header class="sticky top-0 z-20 border-b border-line/70 bg-paper/90 backdrop-blur">
        <div class="mx-auto flex max-w-6xl items-center gap-4 px-4 py-4 sm:gap-6 sm:px-6">
            <a href="{{ route('home') }}" class="flex items-center gap-2.5" aria-label="Startseite">
                <span class="grid size-9 place-items-center rounded-full bg-accent text-lg text-white" aria-hidden="true">🍳</span>
                <span class="hidden font-display text-xl font-semibold tracking-tight sm:inline">Rezeptbuch</span>
            </a>

            <nav class="hidden items-center gap-1 text-sm font-medium whitespace-nowrap text-ink-soft lg:flex">
                <a href="{{ route('home') }}" @class([$navLink, $navActive => request()->routeIs('home')])>
                    Heute
                </a>
                <a href="{{ route('recipes.index') }}"
                   @class([$navLink, $navActive => request()->routeIs('recipes.index') && ! request()->boolean('favoriten')])>
                    Rezepte
                </a>
                <a href="{{ route('recipes.index', ['favoriten' => 1]) }}"
                   @class([$navLink, $navActive => request()->routeIs('recipes.index') && request()->boolean('favoriten')])>
                    Favoriten
                </a>
                <a href="{{ route('meal-plan.show') }}" @class([$navLink, $navActive => request()->routeIs('meal-plan.*')])>
                    Essensplan
                </a>
                @can('view-shopping-list')
                    <a href="{{ route('shopping-list.show') }}" @class([$navLink, $navActive => request()->routeIs('shopping-list.*')])>
                        Einkaufsliste
                    </a>
                @endcan
                @can('view-family-favorites')
                    <a href="{{ route('family.favorites') }}" @class([$navLink, $navActive => request()->routeIs('family.*')])>
                        Familie
                    </a>
                @endcan
            </nav>

            <div class="ml-auto flex items-center gap-4 sm:gap-6">
                @can('create', App\Models\Recipe::class)
                    <a href="{{ route('recipes.create') }}" class="btn btn-primary whitespace-nowrap">
                        <span aria-hidden="true">＋</span> <span class="hidden sm:inline">Neues Rezept</span><span class="sm:hidden">Neu</span>
                    </a>
                @endcan

                {{-- Benutzermenü --}}
                <div class="relative" x-data="{ open: false }" @click.outside="open = false" @keydown.escape.window="open = false">
                    <button type="button" @click="open = ! open" :aria-expanded="open"
                            class="grid size-10 place-items-center rounded-full border border-line bg-white font-display text-sm font-semibold text-ink hover:border-ink-soft"
                            aria-label="Menü für {{ auth()->user()->name }}">
                        {{ auth()->user()->initials() }}
                    </button>

                    <div x-cloak x-show="open" x-transition.origin.top.right
                         class="absolute right-0 mt-2 w-60 overflow-hidden rounded-2xl border border-line bg-white py-2 text-sm shadow-[0_12px_32px_-12px_rgb(43_36_32/0.25)]">
                        <div class="border-b border-line px-4 pt-1 pb-3">
                            <p class="font-semibold">{{ auth()->user()->name }}</p>
                            <p class="text-xs text-ink-soft">{{ '@'.auth()->user()->username }} · {{ auth()->user()->role->label() }}</p>
                        </div>

                        {{-- Auf kleinen Bildschirmen steht die Navigation hier --}}
                        <div class="border-b border-line py-1 lg:hidden">
                            <a href="{{ route('home') }}" class="block px-4 py-2 hover:bg-paper">Heute</a>
                            <a href="{{ route('recipes.index') }}" class="block px-4 py-2 hover:bg-paper">Alle Rezepte</a>
                            <a href="{{ route('recipes.index', ['favoriten' => 1]) }}" class="block px-4 py-2 hover:bg-paper">Meine Favoriten</a>
                            <a href="{{ route('meal-plan.show') }}" class="block px-4 py-2 hover:bg-paper">Essensplan</a>
                            @can('view-shopping-list')
                                <a href="{{ route('shopping-list.show') }}" class="block px-4 py-2 hover:bg-paper">Einkaufsliste</a>
                            @endcan
                            @can('view-family-favorites')
                                <a href="{{ route('family.favorites') }}" class="block px-4 py-2 hover:bg-paper">Familie</a>
                            @endcan
                        </div>

                        <div class="py-1">
                            <a href="{{ route('account.edit') }}" class="block px-4 py-2 hover:bg-paper">Mein Konto</a>
                            @can('viewAny', App\Models\User::class)
                                <a href="{{ route('users.index') }}" class="block px-4 py-2 hover:bg-paper">Benutzer verwalten</a>
                            @endcan
                            @can('view-revision-log')
                                <a href="{{ route('revisions.index') }}" class="block px-4 py-2 hover:bg-paper">Änderungsprotokoll</a>
                            @endcan
                        </div>

                        <form method="POST" action="{{ route('logout') }}" class="border-t border-line pt-1">
                            @csrf
                            <button type="submit" class="block w-full px-4 py-2 text-left text-accent-dark hover:bg-paper">Abmelden</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <x-flash />

    <main class="mx-auto max-w-6xl px-4 py-8 sm:px-6 sm:py-12">
        {{ $slot }}
    </main>

    <footer class="mx-auto max-w-6xl px-4 pb-10 text-center text-xs text-ink-soft sm:px-6">
        Mit Liebe gekocht · {{ now()->year }}
    </footer>
</body>
</html>
