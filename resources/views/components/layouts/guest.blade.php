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
