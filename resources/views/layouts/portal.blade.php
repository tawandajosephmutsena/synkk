<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'Synkk Portal' }}</title>

    @include('partials.head')

    @vite(['resources/css/portal.css', 'resources/js/portal.js'])
    @fluxAppearance
</head>
<body class="min-h-screen antialiased selection:bg-amber-500 selection:text-zinc-950 bg-[#09090b] text-[#f4f4f5] overflow-x-hidden">
    <!-- Reading Progress Bar -->
    <div id="synkk-reading-bar" class="synkk-reading-progress"></div>

    {{ $slot }}

    @fluxScripts
</body>
</html>
