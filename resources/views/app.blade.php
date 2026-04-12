<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        {{-- Inline script: apply theme before first paint to avoid flash --}}
        {{-- Dark is the default. 'system' checks OS preference. 'light' removes dark class. --}}
        <script>
            (function() {
                document.documentElement.classList.add('dark');
            })();
        </script>

        {{-- Inline background prevents white flash before CSS loads --}}
        <style>
            html { background-color: #F8F8F8; }
            html.dark { background-color: #0A0A0F; }
        </style>

        <link rel="icon" href="/favicon.png" type="image/png">
        <link rel="apple-touch-icon" href="/favicon.png">

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" rel="stylesheet" />

        @viteReactRefresh
        @vite(['resources/css/app.css', 'resources/js/app.tsx', "resources/js/pages/{$page['component']}.tsx"])
        <x-inertia::head>
            <title>{{ config('app.name', 'Laravel') }}</title>
        </x-inertia::head>
    </head>
    <body class="font-sans antialiased">
        <x-inertia::app />
    </body>
</html>
