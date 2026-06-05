<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" @class(['dark' => ($appearance ?? 'system') == 'dark'])>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>{{ $meta['title'] ?? config('app.name', 'Laravel') }}</title>
        <meta data-inertia="description" name="description" content="{{ $meta['description'] ?? config('app.name', 'Laravel') }}">
        <link data-inertia="canonical" rel="canonical" href="{{ $meta['canonical'] ?? url()->current() }}">
        <meta data-inertia="og:title" property="og:title" content="{{ $meta['ogTitle'] ?? ($meta['title'] ?? config('app.name', 'Laravel')) }}">
        <meta data-inertia="og:description" property="og:description" content="{{ $meta['ogDescription'] ?? ($meta['description'] ?? config('app.name', 'Laravel')) }}">
        <meta data-inertia="og:type" property="og:type" content="{{ $meta['ogType'] ?? 'website' }}">
        <meta data-inertia="og:url" property="og:url" content="{{ $meta['ogUrl'] ?? url()->current() }}">
        <meta data-inertia="og:image" property="og:image" content="{{ $meta['ogImage'] ?? url('/apple-touch-icon.png') }}">
        <meta data-inertia="twitter:card" name="twitter:card" content="{{ $meta['twitterCard'] ?? 'summary_large_image' }}">
        <meta data-inertia="twitter:title" name="twitter:title" content="{{ $meta['twitterTitle'] ?? ($meta['title'] ?? config('app.name', 'Laravel')) }}">
        <meta data-inertia="twitter:description" name="twitter:description" content="{{ $meta['twitterDescription'] ?? ($meta['description'] ?? config('app.name', 'Laravel')) }}">
        <meta data-inertia="twitter:image" name="twitter:image" content="{{ $meta['twitterImage'] ?? url('/apple-touch-icon.png') }}">

        {{-- Inline script to detect system dark mode preference and apply it immediately --}}
        <script>
            (function() {
                const appearance = '{{ $appearance ?? "system" }}';

                if (appearance === 'system') {
                    const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;

                    if (prefersDark) {
                        document.documentElement.classList.add('dark');
                    }
                }
            })();
        </script>

        {{-- Inline style to set the HTML background color based on our theme in app.css --}}
        <style>
            html {
                background-color: oklch(1 0 0);
            }

            html.dark {
                background-color: oklch(0.145 0 0);
            }
        </style>

        <link rel="icon" href="/favicon.ico" sizes="any">
        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png">

        @fonts

        @viteReactRefresh
        @vite(['resources/css/app.css', 'resources/js/app.tsx', "resources/js/pages/{$page['component']}.tsx"])
        <x-inertia::head />
    </head>
    <body class="font-sans antialiased">
        <x-inertia::app />
    </body>
</html>
