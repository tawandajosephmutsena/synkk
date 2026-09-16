<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'Synkk Portal' }}</title>

    @include('partials.head')

    <style>
        /* Synkk Portal Micro-interactions & Polish */
        .synkk-reading-progress {
            position: fixed;
            top: 0;
            left: 0;
            height: 3px;
            z-index: 100;
            transition: width 0.1s ease-out;
        }
        .synkk-wikilink-preview-card {
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.3), 0 8px 10px -6px rgba(0, 0, 0, 0.3);
            animation: synkk-popover 0.15s cubic-bezier(0.16, 1, 0.3, 1);
        }
        @keyframes synkk-popover {
            from { opacity: 0; transform: translateY(6px) scale(0.98); }
            to { opacity: 1; transform: translateY(0) scale(1); }
        }
        .prose pre {
            position: relative;
            border-radius: 0.75rem;
            border: 1px solid rgba(255, 255, 255, 0.08);
            background-color: #0c0d0e !important;
        }
        .prose a.anchor-link {
            text-decoration: none;
            opacity: 0;
            transition: opacity 0.15s ease;
        }
        h1:hover .anchor-link, h2:hover .anchor-link, h3:hover .anchor-link {
            opacity: 0.8;
        }
    </style>
</head>
<body class="min-h-screen antialiased selection:bg-amber-500 selection:text-zinc-950 bg-zinc-950 text-zinc-100">
    {{ $slot }}
</body>
</html>
