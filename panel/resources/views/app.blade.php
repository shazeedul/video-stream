<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" @class(['dark' => ($appearance ?? 'system') == 'dark'])>

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    {{-- Inline script to detect system dark mode preference and apply it immediately --}}
    <script>
        (function() {
            const appearance = '{{ $appearance ?? 'system' }}';

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

    <title inertia>{{ config('app.name', 'Laravel') }}</title>

    {{-- Favicons with dark/light mode support --}}
    <link rel="icon" href="{{ system_setting('app.favicon', '/favicon.ico', true) }}" sizes="any">
    <link rel="icon" href="{{ system_setting('app.favicon_dark', '/favicon-light.ico', true) }}" sizes="any"
        media="(prefers-color-scheme: light)">
    <link rel="icon" href="{{ system_setting('app.favicon_light', '/favicon-dark.ico', true) }}" sizes="any"
        media="(prefers-color-scheme: dark)">

    <link rel="icon" href="{{ system_setting('app.favicon_dark_svg', '/favicon-light.svg', true) }}"
        type="image/svg+xml" media="(prefers-color-scheme: light)">
    <link rel="icon" href="{{ system_setting('app.favicon_dark_svg', '/favicon-dark.svg', true) }}"
        type="image/svg+xml" media="(prefers-color-scheme: dark)">

    <link rel="apple-touch-icon"
        href="{{ system_setting('app.apple_touch_icon_dark', '/apple-touch-icon-dark.png', true) }}"
        media="(prefers-color-scheme: light)">
    <link rel="apple-touch-icon"
        href="{{ system_setting('app.apple_touch_icon_light', '/apple-touch-icon-light.png', true) }}"
        media="(prefers-color-scheme: dark)">

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" rel="stylesheet" />

    @viteReactRefresh
    @vite(['resources/js/app.tsx', "resources/js/pages/{$page['component']}.tsx"])
    @inertiaHead
</head>

<body class="font-sans antialiased">
    @inertia
</body>

</html>
