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
            <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
        @endif
</head>
    <body class="antialiased bg-gray-50 text-gray-900 dark:bg-gray-950 dark:text-gray-100" x-data="{ sidebarOpen: false }">
        <div class="min-h-screen flex">
            <x-app-sidebar />

            <div class="relative z-10 flex min-h-screen min-w-0 flex-1 flex-col">
                @include('layouts.navigation')

                <!-- Page Heading -->
                @isset($header)
                    <header class="relative z-0 border-b border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900">
                        <div class="mx-auto max-w-7xl px-4 py-5 sm:px-6 lg:px-8">
                            {{ $header }}
                        </div>
                    </header>
                @endisset

                <!-- Page Content -->
                <main class="flex-1 overflow-auto">
                    @if (session('status'))
                        <div class="mx-auto max-w-7xl px-4 pt-6 sm:px-6 lg:px-8">
                            <div class="app-card app-card-success">
                                {{ session('status') }}
                            </div>
                        </div>
                    @endif

                    @if ($errors->any())
                        <div class="mx-auto max-w-7xl px-4 pt-4 sm:px-6 lg:px-8">
                            <div class="app-card app-card-danger">
                                <ul class="list-disc pl-5">
                                    @foreach ($errors->all() as $error)
                                        <li>{{ $error }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        </div>
                    @endif

                    {{ $slot }}
                </main>
            </div>
        </div>
    </body>
</html>
