<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}"  @class(['dark' => ($appearance ?? 'system') == 'dark'])>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        {{-- Apply the server-resolved theme (account > cookie > system) before first paint --}}
        <script>
            (function() {
                const appearance = '{{ $appearance ?? "system" }}';

                try {
                    localStorage.setItem('appearance', appearance);
                } catch (e) {}

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
                background-color: #ffffff;
            }

            html.dark {
                background-color: #0b1220;
            }
        </style>

        <link rel="icon" href="/favicon.ico" sizes="any">
        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png">
        {{-- Installable PWA (docs/15 §11) --}}
        <link rel="manifest" href="/manifest.webmanifest">
        <meta name="theme-color" content="#2563eb" media="(prefers-color-scheme: light)">
        <meta name="theme-color" content="#0b1220" media="(prefers-color-scheme: dark)">
        <meta name="mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-title" content="Budgeting">

        @fonts

        @php
            // Same lookup as the resolver in app.ts: modules/<feature>/pages first, then pages/.
            [$feature, $rest] = array_pad(explode('/', $page['component'], 2), 2, null);
            $modulePage = $rest !== null ? "resources/js/modules/{$feature}/pages/{$rest}.vue" : null;
            $pageEntry = $modulePage !== null && file_exists(base_path($modulePage))
                ? $modulePage
                : "resources/js/pages/{$page['component']}.vue";
        @endphp
        @vite(['resources/css/app.css', 'resources/js/app.ts', $pageEntry])
        <x-inertia::head>
            <title>{{ config('app.name', 'Laravel') }}</title>
        </x-inertia::head>
    </head>
    <body class="font-sans antialiased">
        <x-inertia::app />
    </body>
</html>
