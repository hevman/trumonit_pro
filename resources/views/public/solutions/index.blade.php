@php
    $isPl = app()->getLocale() === 'pl';
    $seoTitle = $isPl ? 'Przykłady wdrożeń monitoringu | TruMonit' : 'Monitoring implementation examples | TruMonit';
    $seoDescription = $isPl
        ? 'Gotowe scenariusze: monitoring uptime, API, SSL, strony statusu i procesy incydentowe. Strony pod decyzje zakupowe.'
        : 'Buying-intent pages for uptime, API, SSL monitoring, status pages, and incident workflows.';
    $homeUrl = route('localized.public.home', ['locale' => app()->getLocale()]);
    $pricingUrl = route('localized.public.pricing', ['locale' => app()->getLocale()]);
    $faqIndexUrl = route('localized.public.faq.index', ['locale' => app()->getLocale()]);
    $solutionsIndexUrl = route('localized.public.solutions.index', ['locale' => app()->getLocale()]);
    $privacyUrl = route('localized.legal.privacy', ['locale' => app()->getLocale()]);
    $termsUrl = route('localized.legal.terms', ['locale' => app()->getLocale()]);
    $canonicalUrl = $solutionsIndexUrl;
    $langSwitchEn = route('localized.public.solutions.index', ['locale' => 'en']);
    $langSwitchPl = route('localized.public.solutions.index', ['locale' => 'pl']);

    $itemListSchema = [
        '@context' => 'https://schema.org',
        '@type' => 'ItemList',
        'name' => $seoTitle,
        'itemListElement' => collect($pages)->values()->map(static function ($page, $index) {
            return [
                '@type' => 'ListItem',
                'position' => $index + 1,
                'name' => $page['title'],
                'url' => route('localized.public.solutions.show', ['locale' => app()->getLocale(), 'slug' => $page['slug']]),
            ];
        })->all(),
    ];
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $seoTitle }}</title>
    <meta name="description" content="{{ $seoDescription }}">
    <link rel="canonical" href="{{ $canonicalUrl }}">
    <link rel="alternate" hreflang="en" href="{{ $langSwitchEn }}">
    <link rel="alternate" hreflang="pl" href="{{ $langSwitchPl }}">
    <meta property="og:type" content="website">
    <meta property="og:title" content="{{ $seoTitle }}">
    <meta property="og:description" content="{{ $seoDescription }}">
    <meta property="og:url" content="{{ $canonicalUrl }}">
    <meta property="og:image" content="{{ asset('android-chrome-512x512.png') }}">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $seoTitle }}">
    <meta name="twitter:description" content="{{ $seoDescription }}">
    <meta name="twitter:image" content="{{ asset('android-chrome-512x512.png') }}">
    <script type="application/ld+json">{!! json_encode($itemListSchema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
    @include('partials.theme-head')
    @include('partials.favicons')
    @include('partials.trucookie-banner')

    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @else
        @include('partials.local-css-fallback')
    @endif
</head>
<body class="bg-slate-50 text-slate-900 antialiased dark:bg-slate-950 dark:text-slate-100">
    <div class="relative min-h-screen overflow-hidden flex flex-col">
        <div class="pointer-events-none absolute -top-32 left-1/2 h-72 w-72 -translate-x-1/2 rounded-full bg-indigo-300/30 blur-3xl dark:bg-indigo-500/20"></div>
        <div class="pointer-events-none absolute top-72 -right-16 h-72 w-72 rounded-full bg-cyan-300/20 blur-3xl dark:bg-cyan-500/20"></div>

        <header class="sticky top-0 z-40 border-b border-slate-200/70 bg-white/80 backdrop-blur-lg dark:border-slate-800 dark:bg-slate-950/80">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between gap-3">
                <a href="{{ $homeUrl }}" class="inline-flex items-center gap-3">
                    <x-application-logo class="h-9 w-9 fill-current text-slate-900 dark:text-slate-100" />
                    <span class="font-semibold tracking-tight">{{ config('app.name', 'TruMonit') }}</span>
                </a>

                <nav class="hidden lg:flex items-center gap-1 text-sm">
                    <a href="{{ $homeUrl }}#features" class="rounded-md px-3 py-2 text-slate-600 hover:bg-slate-100 hover:text-slate-900 dark:text-slate-300 dark:hover:bg-slate-800 dark:hover:text-slate-100">{{ __('messages.welcome.nav_features') }}</a>
                    <a href="{{ $solutionsIndexUrl }}" class="rounded-md px-3 py-2 text-slate-900 bg-slate-100 dark:text-slate-100 dark:bg-slate-800">{{ $isPl ? 'Przykłady wdrożeń' : 'Implementation examples' }}</a>
                    <a href="{{ $faqIndexUrl }}" class="rounded-md px-3 py-2 text-slate-600 hover:bg-slate-100 hover:text-slate-900 dark:text-slate-300 dark:hover:bg-slate-800 dark:hover:text-slate-100">{{ __('faq.nav_faq') }}</a>
                    <a href="{{ $pricingUrl }}" class="rounded-md px-3 py-2 text-slate-600 hover:bg-slate-100 hover:text-slate-900 dark:text-slate-300 dark:hover:bg-slate-800 dark:hover:text-slate-100">{{ __('messages.welcome.nav_pricing') }}</a>
                    <a href="{{ $homeUrl }}#security" class="rounded-md px-3 py-2 text-slate-600 hover:bg-slate-100 hover:text-slate-900 dark:text-slate-300 dark:hover:bg-slate-800 dark:hover:text-slate-100">{{ __('messages.welcome.nav_security') }}</a>
                </nav>

                <div class="flex items-center gap-2">
                    <a href="{{ $langSwitchEn }}" class="rounded-md px-2 py-1 text-xs {{ app()->getLocale() === 'en' ? 'bg-slate-900 text-white dark:bg-slate-100 dark:text-slate-900' : 'text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800' }}">EN</a>
                    <a href="{{ $langSwitchPl }}" class="rounded-md px-2 py-1 text-xs {{ app()->getLocale() === 'pl' ? 'bg-slate-900 text-white dark:bg-slate-100 dark:text-slate-900' : 'text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800' }}">PL</a>
                    <x-theme-toggle class="px-2.5 py-1.5" />
                    <a href="{{ route('login') }}" class="hidden sm:inline-flex rounded-lg border border-slate-300 px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-100 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800">{{ __('messages.welcome.cta_secondary') }}</a>
                    <a href="{{ route('register') }}" class="inline-flex rounded-lg bg-slate-900 px-3 py-1.5 text-sm font-medium text-white hover:bg-black dark:bg-indigo-500 dark:hover:bg-indigo-400">{{ __('messages.welcome.cta_primary') }}</a>
                </div>
            </div>
        </header>

        <main class="flex-1 max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-10 sm:py-14">
            <section class="rounded-3xl border border-slate-200 bg-white p-6 sm:p-8 dark:border-slate-800 dark:bg-slate-900">
                <h1 class="text-3xl sm:text-4xl font-semibold tracking-tight">{{ $isPl ? 'Przykłady wdrożeń' : 'Implementation examples' }}</h1>
                <p class="mt-3 text-slate-600 dark:text-slate-300">{{ $seoDescription }}</p>
                <div class="mt-6 flex flex-wrap gap-3">
                    <a href="{{ $pricingUrl }}" class="inline-flex items-center rounded-xl bg-slate-900 px-5 py-3 text-sm font-semibold text-white hover:bg-black dark:bg-indigo-500 dark:hover:bg-indigo-400">{{ __('messages.welcome.nav_pricing') }}</a>
                    <a href="{{ route('register') }}" class="inline-flex items-center rounded-xl border border-slate-300 bg-white px-5 py-3 text-sm font-semibold text-slate-900 hover:bg-slate-100 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100 dark:hover:bg-slate-800">{{ __('messages.welcome.cta_primary') }}</a>
                </div>
            </section>

            <section class="mt-8 grid grid-cols-1 md:grid-cols-2 gap-5">
                @foreach ($pages as $page)
                    <article class="rounded-2xl border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900">
                        <h2 class="text-lg font-semibold">
                            <a href="{{ route('localized.public.solutions.show', ['locale' => app()->getLocale(), 'slug' => $page['slug']]) }}" class="hover:underline">
                                {{ $page['title'] }}
                            </a>
                        </h2>
                        <p class="mt-2 text-sm text-slate-600 dark:text-slate-300">{{ $page['summary'] }}</p>
                        <div class="mt-4">
                            <a href="{{ route('localized.public.solutions.show', ['locale' => app()->getLocale(), 'slug' => $page['slug']]) }}" class="text-sm font-medium text-indigo-600 hover:underline dark:text-indigo-300">
                                {{ $isPl ? 'Zobacz szczegóły' : 'Read details' }}
                            </a>
                        </div>
                    </article>
                @endforeach
            </section>
        </main>

        <footer class="border-t border-slate-200/70 bg-white/70 backdrop-blur dark:border-slate-800 dark:bg-slate-950/70">
            <div class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
                <div class="flex flex-wrap items-center justify-between gap-4 text-sm text-slate-500 dark:text-slate-400">
                    <span>&copy; {{ date('Y') }} {{ config('app.name', 'TruMonit') }}</span>
                    <div class="flex items-center gap-3">
                        <a class="hover:underline" href="{{ $pricingUrl }}">{{ __('messages.welcome.nav_pricing') }}</a>
                        <span class="opacity-40">&middot;</span>
                        <a class="hover:underline" href="{{ $solutionsIndexUrl }}">{{ $isPl ? 'Przykłady wdrożeń' : 'Implementation examples' }}</a>
                        <span class="opacity-40">&middot;</span>
                        <a class="hover:underline" href="{{ $faqIndexUrl }}">{{ __('faq.nav_faq') }}</a>
                        <span class="opacity-40">&middot;</span>
                        <a class="hover:underline" href="{{ $privacyUrl }}">{{ app()->getLocale() === 'pl' ? 'Prywatność' : 'Privacy' }}</a>
                        <span class="opacity-40">&middot;</span>
                        <a class="hover:underline" href="{{ $termsUrl }}">{{ app()->getLocale() === 'pl' ? 'Warunki' : 'Terms' }}</a>
                    </div>
                </div>
            </div>
        </footer>
    </div>
</body>
</html>

