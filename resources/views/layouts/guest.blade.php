<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'TruMonit') }}</title>
        @include('partials.theme-head')
        @include('partials.favicons')

        <!-- Scripts -->
        @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
            @vite(['resources/css/app.css', 'resources/js/app.js'])
        @else
            <script src="https://cdn.tailwindcss.com"></script>
            <script>
                tailwind.config = Object.assign({}, tailwind.config || {}, { darkMode: 'class' });
            </script>
            @include('partials.tailwind-fallback-components')
        @endif
    </head>
    <body class="bg-gray-50 text-gray-900 antialiased dark:bg-gray-950 dark:text-gray-100">
        <div class="min-h-screen px-4 py-6 sm:py-10">
            <div class="max-w-6xl mx-auto">
                <div class="flex items-center justify-between gap-3 mb-8">
                    <a href="/" class="inline-flex items-center gap-3">
                        <x-application-logo class="w-10 h-10 fill-current text-gray-700 dark:text-gray-200" />
                        <span class="font-semibold text-base text-gray-900 dark:text-gray-100">{{ config('app.name', 'TruMonit') }}</span>
                    </a>
                    <div class="flex items-center gap-2">
                        <a href="{{ route('lang.switch', ['locale' => 'en']) }}" class="rounded-md px-2 py-1 text-sm {{ app()->getLocale() === 'en' ? 'bg-gray-900 text-white dark:bg-gray-100 dark:text-gray-900' : 'text-gray-600 hover:bg-gray-200 dark:text-gray-300 dark:hover:bg-gray-800' }}">EN</a>
                        <a href="{{ route('lang.switch', ['locale' => 'pl']) }}" class="rounded-md px-2 py-1 text-sm {{ app()->getLocale() === 'pl' ? 'bg-gray-900 text-white dark:bg-gray-100 dark:text-gray-900' : 'text-gray-600 hover:bg-gray-200 dark:text-gray-300 dark:hover:bg-gray-800' }}">PL</a>
                        <x-theme-toggle />
                    </div>
                </div>

                <div class="grid gap-8 lg:grid-cols-2 lg:items-center">
                    <div class="hidden lg:block">
                        <p class="text-sm uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-3">{{ __('messages.welcome.eyebrow') }}</p>
                        <h1 class="text-4xl font-semibold leading-tight mb-4">{{ __('messages.welcome.hero_title') }}</h1>
                        <p class="text-gray-700 dark:text-gray-300">{{ __('messages.welcome.hero_subtitle') }}</p>
                    </div>

                    <div class="w-full sm:max-w-md lg:ms-auto px-6 py-6 bg-white shadow-xl shadow-gray-200/60 overflow-hidden rounded-2xl border border-gray-200 dark:bg-gray-900 dark:border-gray-800 dark:shadow-none">
                        {{ $slot }}
                    </div>
                </div>
            </div>
        </div>
    </body>
</html>
