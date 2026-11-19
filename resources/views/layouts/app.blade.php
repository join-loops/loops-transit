<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>@yield('title', config('transit.operator.name'))</title>
        <link rel="alternate" type="application/json" href="{{ route('well-known') }}">
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-screen bg-stone-50 text-stone-900 antialiased dark:bg-stone-950 dark:text-stone-100">
        <div class="mx-auto flex min-h-screen max-w-3xl flex-col px-6 py-10">
            <header class="flex items-center justify-between gap-4">
                <a href="{{ route('home') }}" class="flex items-center gap-3">
                    <span class="flex size-9 items-center justify-center rounded-lg bg-stone-900 text-sm font-semibold text-white dark:bg-white dark:text-stone-900">LT</span>
                    <span>
                        <span class="block font-semibold leading-tight">{{ config('transit.operator.name') }}</span>
                        <span class="block text-xs text-stone-500 dark:text-stone-400">Loops Transit</span>
                    </span>
                </a>
                <nav class="flex items-center gap-5 text-sm text-stone-600 dark:text-stone-300">
                    <a href="{{ route('lookup') }}" class="hover:text-stone-900 dark:hover:text-white">Lookup</a>
                    <a href="{{ route('well-known') }}" class="hover:text-stone-900 dark:hover:text-white">Discovery</a>
                    <a href="{{ route('log.head') }}" class="hover:text-stone-900 dark:hover:text-white">Tree head</a>
                </nav>
            </header>

            <main class="mt-12 flex-1">
                @yield('content')
            </main>

            <footer class="mt-16 border-t border-stone-200 pt-6 text-xs text-stone-500 dark:border-stone-800 dark:text-stone-400">
                <p>
                    Loops Transit {{ \App\Transit\Transit::VERSION }}
                    @if (config('transit.operator.contact'))
                        <span class="mx-1">&middot;</span> Contact {{ config('transit.operator.contact') }}
                    @endif
                    @if (config('transit.operator.url'))
                        <span class="mx-1">&middot;</span> <a href="{{ config('transit.operator.url') }}" class="underline" rel="noopener">Operator</a>
                    @endif
                </p>
            </footer>
        </div>
    </body>
</html>
