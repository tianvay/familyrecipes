@props(['title' => null])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ? "{$title} · " : '' }}{{ config('app.name') }}</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=fraunces:500,600,700|inter:400,500,600" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen">
    <header class="border-b border-line/70 bg-paper/90 backdrop-blur sticky top-0 z-20">
        <div class="mx-auto flex max-w-6xl items-center gap-6 px-4 py-4 sm:px-6">
            <a href="{{ route('recipes.index') }}" class="flex items-center gap-2.5">
                <span class="grid size-9 place-items-center rounded-full bg-accent text-lg text-white" aria-hidden="true">🍳</span>
                <span class="font-display text-xl font-semibold tracking-tight">Rezeptbuch</span>
            </a>

            <nav class="hidden items-center gap-1 text-sm font-medium text-ink-soft sm:flex">
                <a href="{{ route('recipes.index') }}"
                   @class(['rounded-full px-3 py-1.5 hover:text-ink', 'text-ink bg-paper-deep' => request()->routeIs('recipes.index') && ! request()->boolean('favoriten')])>
                    Alle Rezepte
                </a>
                <a href="{{ route('recipes.index', ['favoriten' => 1]) }}"
                   @class(['rounded-full px-3 py-1.5 hover:text-ink', 'text-ink bg-paper-deep' => request()->boolean('favoriten')])>
                    Favoriten
                </a>
            </nav>

            <a href="{{ route('recipes.create') }}" class="btn btn-primary ml-auto">
                <span aria-hidden="true">＋</span> Neues Rezept
            </a>
        </div>
    </header>

    @if (session('status'))
        <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 4000)" x-transition.opacity
             class="fixed bottom-6 left-1/2 z-30 -translate-x-1/2 rounded-full bg-ink px-5 py-3 text-sm font-medium text-paper shadow-lg"
             role="status">
            {{ session('status') }}
        </div>
    @endif

    <main class="mx-auto max-w-6xl px-4 py-8 sm:px-6 sm:py-12">
        {{ $slot }}
    </main>

    <footer class="mx-auto max-w-6xl px-4 pb-10 text-center text-xs text-ink-soft sm:px-6">
        Mit Liebe gekocht · {{ now()->year }}
    </footer>
</body>
</html>
