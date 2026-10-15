@props(['title' => null])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    @include('partials.head', ['title' => $title])
</head>
<body class="grid min-h-screen place-items-center px-4 py-12">
    <main class="w-full max-w-sm">
        <div class="mb-8 flex flex-col items-center gap-3 text-center">
            <span class="grid size-14 place-items-center rounded-full bg-accent text-2xl text-white" aria-hidden="true">🍳</span>
            <p class="font-display text-2xl font-semibold tracking-tight">Rezeptbuch</p>
        </div>

        {{ $slot }}
    </main>
</body>
</html>
