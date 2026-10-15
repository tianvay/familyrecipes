@props(['title' => null])

{{-- Vollbild-Layout ohne Navigation, z. B. für den Kochmodus --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    @include('partials.head', ['title' => $title])
</head>
<body class="min-h-dvh overscroll-none">
    {{ $slot }}
</body>
</html>
