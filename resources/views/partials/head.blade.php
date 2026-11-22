{{-- Gemeinsamer <head>-Inhalt beider Layouts --}}
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{{ $title ? "{$title} · " : '' }}{{ config('app.name') }}</title>

{{-- „Zum Home-Bildschirm“ auf iPhone/iPad: eigenes Icon, Vollbild ohne Safari-Leisten --}}
<link rel="manifest" href="{{ asset('manifest.json') }}">
<link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
<link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-title" content="Rezeptbuch">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
{{-- Farbe der Browserleiste passend zum Design (App\Enums\Theme) --}}
@switch(App\Enums\Theme::current(request()))
    @case(App\Enums\Theme::Mocha)
        <meta name="theme-color" content="#181825">
        @break
    @case(App\Enums\Theme::Auto)
        <meta name="theme-color" content="#fbf7f1" media="(prefers-color-scheme: light)">
        <meta name="theme-color" content="#181825" media="(prefers-color-scheme: dark)">
        @break
    @default
        <meta name="theme-color" content="#fbf7f1">
@endswitch

<link rel="preconnect" href="https://fonts.bunny.net">
<link href="https://fonts.bunny.net/css?family=fraunces:500,600,700|inter:400,500,600" rel="stylesheet">

@vite(['resources/css/app.css', 'resources/js/app.js'])
