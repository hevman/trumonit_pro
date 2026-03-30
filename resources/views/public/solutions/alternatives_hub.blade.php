@php
    $isPl = app()->getLocale() === 'pl';
    $seoTitle = $isPl
        ? 'Alternatywy dla UptimeRobot, Better Stack i innych: 20 porównań | TruMonit'
        : 'UptimeRobot, Better Stack, and other monitoring alternatives: 20 comparisons | TruMonit';
    $seoDescription = $isPl
        ? 'Hub porównań narzędzi monitoringu dla SaaS i agencji. Zobacz 20 stron typu X alternative i porównaj alerty, status page, workflow incydentów i wdrożenie.'
        : 'Comparison hub for monitoring tools used by SaaS teams and agencies. Explore 20 X-alternative pages and compare alerting, status pages, incident workflow, and rollout speed.';
    $homeUrl = route('localized.public.home', ['locale' => app()->getLocale()]);
    $pricingUrl = route('localized.public.pricing', ['locale' => app()->getLocale()]);
    $faqIndexUrl = route('localized.public.faq.index', ['locale' => app()->getLocale()]);
    $solutionsIndexUrl = route('localized.public.solutions.index', ['locale' => app()->getLocale()]);
    $alternativesUrl = route('localized.public.alternatives.index', ['locale' => app()->getLocale()]);
    $privacyUrl = route('localized.legal.privacy', ['locale' => app()->getLocale()]);
    $termsUrl = route('localized.legal.terms', ['locale' => app()->getLocale()]);
    $langSwitchEn = route('localized.public.alternatives.index', ['locale' => 'en']);
    $langSwitchPl = route('localized.public.alternatives.index', ['locale' => 'pl']);
    $featuredRoutes = [
        [
            'title' => $isPl ? 'Dla agencji utrzymujących strony klientów' : 'For website maintenance agencies',
            'slug' => 'monitoring-for-client-websites-maintenance-agency',
        ],
        [
            'title' => $isPl ? 'Dla małego zespołu SaaS' : 'For small SaaS teams',
            'slug' => 'best-monitoring-setup-for-small-saas-team',
        ],
        [
            'title' => $isPl ? 'Dla Laravel w produkcji' : 'For Laravel apps in production',
            'slug' => 'monitor-laravel-apps-in-production',
        ],
        [
            'title' => $isPl ? 'Dla WooCommerce po update' : 'For WooCommerce after updates',
            'slug' => 'woocommerce-monitoring-after-plugin-updates',
        ],
    ];
    $breadcrumbSchema = [
        '@context' => 'https://schema.org',
        '@type' => 'BreadcrumbList',
        'itemListElement' => [
            [
                '@type' => 'ListItem',
                'position' => 1,
                'name' => config('app.name', 'TruMonit'),
                'item' => $homeUrl,
            ],
            [
                '@type' => 'ListItem',
                'position' => 2,
                'name' => $isPl ? 'Porównania' : 'Alternatives',
                'item' => $alternativesUrl,
            ],
        ],
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
    <link rel="canonical" href="{{ $alternativesUrl }}">
    <link rel="alternate" hreflang="en" href="{{ $langSwitchEn }}">
    <link rel="alternate" hreflang="pl" href="{{ $langSwitchPl }}">
    <link rel="alternate" hreflang="x-default" href="{{ $langSwitchEn }}">
    <meta property="og:type" content="website">
    <meta property="og:title" content="{{ $seoTitle }}">
    <meta property="og:description" content="{{ $seoDescription }}">
    <meta property="og:url" content="{{ $alternativesUrl }}">
    <meta property="og:image" content="{{ asset('android-chrome-512x512.png') }}">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $seoTitle }}">
    <meta name="twitter:description" content="{{ $seoDescription }}">
    <meta name="twitter:image" content="{{ asset('android-chrome-512x512.png') }}">
    <script type="application/ld+json">{!! json_encode($breadcrumbSchema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
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
                    <a href="{{ $solutionsIndexUrl }}" class="rounded-md px-3 py-2 text-slate-900 bg-slate-100 dark:text-slate-100 dark:bg-slate-800">{{ $isPl ? 'Przyklady wdrozen' : 'Implementation examples' }}</a>
                    <a href="{{ $faqIndexUrl }}" class="rounded-md px-3 py-2 text-slate-600 hover:bg-slate-100 hover:text-slate-900 dark:text-slate-300 dark:hover:bg-slate-800 dark:hover:text-slate-100">{{ __('faq.nav_faq') }}</a>
                    <a href="{{ $pricingUrl }}" class="rounded-md px-3 py-2 text-slate-600 hover:bg-slate-100 hover:text-slate-900 dark:text-slate-300 dark:hover:bg-slate-800 dark:hover:text-slate-100">{{ __('messages.welcome.nav_pricing') }}</a>
                </nav>

                <div class="flex items-center gap-2">
                    <a href="{{ $langSwitchEn }}" class="rounded-md px-2 py-1 text-xs {{ app()->getLocale() === 'en' ? 'bg-slate-900 text-white dark:bg-slate-100 dark:text-slate-900' : 'text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800' }}">EN</a>
                    <a href="{{ $langSwitchPl }}" class="rounded-md px-2 py-1 text-xs {{ app()->getLocale() === 'pl' ? 'bg-slate-900 text-white dark:bg-slate-100 dark:text-slate-900' : 'text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800' }}">PL</a>
                    <x-theme-toggle class="px-2.5 py-1.5" />
                    <a href="{{ route('register') }}" class="inline-flex rounded-lg bg-slate-900 px-3 py-1.5 text-sm font-medium text-white hover:bg-black dark:bg-indigo-600 dark:hover:bg-indigo-500">{{ __('messages.welcome.cta_primary') }}</a>
                </div>
            </div>
        </header>

        <main class="flex-1 max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-10 sm:py-14">
            <nav aria-label="Breadcrumb" class="text-sm text-slate-500 dark:text-slate-400">
                <ol class="flex flex-wrap items-center gap-2">
                    <li><a href="{{ $homeUrl }}" class="hover:text-slate-900 dark:hover:text-slate-100">{{ config('app.name', 'TruMonit') }}</a></li>
                    <li aria-hidden="true">/</li>
                    <li class="text-slate-700 dark:text-slate-200">{{ $isPl ? 'Porównania' : 'Alternatives' }}</li>
                </ol>
            </nav>

            <section class="rounded-3xl border border-slate-200 bg-white p-6 sm:p-8 dark:border-slate-800 dark:bg-slate-900">
                <div class="inline-flex rounded-full border border-indigo-300 bg-indigo-50 px-3 py-1 text-xs font-semibold uppercase tracking-[0.18em] text-indigo-800 dark:border-indigo-700 dark:bg-indigo-950/40 dark:text-indigo-200">
                    {{ $isPl ? 'Hub comparison intent' : 'Comparison intent hub' }}
                </div>
                <h1 class="text-3xl sm:text-4xl font-semibold tracking-tight">
                    {{ $isPl ? 'Hub porownan monitoringu (20 stron)' : 'Monitoring alternatives hub (20 pages)' }}
                </h1>
                <p class="mt-3 text-slate-600 dark:text-slate-300">
                    {{ $isPl
                        ? 'Tutaj znajdziesz wszystkie porownania typu X alternative. Kazda strona ma osobny URL i konkretne wskazowki wdrozeniowe.'
                        : 'This page groups all X-alternative comparisons. Each comparison has its own URL and implementation-focused guidance.' }}
                </p>
                <div class="mt-6 flex flex-wrap gap-3">
                    <a href="{{ $pricingUrl }}" class="inline-flex items-center rounded-xl bg-slate-900 px-5 py-3 text-sm font-semibold text-white hover:bg-black dark:bg-indigo-600 dark:hover:bg-indigo-500">{{ __('messages.welcome.nav_pricing') }}</a>
                    <a href="{{ route('register') }}" class="inline-flex items-center rounded-xl border border-slate-300 bg-white px-5 py-3 text-sm font-semibold text-slate-900 hover:bg-slate-100 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100 dark:hover:bg-slate-800">{{ __('messages.welcome.cta_primary') }}</a>
                </div>
            </section>

            <section class="mt-6 rounded-2xl border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900">
                <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                    <div>
                        <h2 class="text-xl font-semibold">{{ $isPl ? 'Nie każdy klik powinien lądować na comparison page' : 'Not every click should land on a comparison page' }}</h2>
                        <p class="mt-2 max-w-3xl text-sm text-slate-600 dark:text-slate-300">
                            {{ $isPl
                                ? 'Porównania służą intencji alternative. Jeśli ktoś szuka rozwiązania dla konkretnego setupu, lepiej wysłać go na dedykowany page solution.'
                                : 'Comparison pages serve alternative intent. If someone wants a concrete setup, send them to a dedicated solution page instead.' }}
                        </p>
                    </div>
                    <a href="{{ $solutionsIndexUrl }}" class="inline-flex rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-900 hover:bg-slate-100 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100 dark:hover:bg-slate-800">
                        {{ $isPl ? 'Przejdź do stron solution' : 'Open solution pages' }}
                    </a>
                </div>
                <div class="mt-5 grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4">
                    @foreach ($featuredRoutes as $featured)
                        <a href="{{ route('localized.public.solutions.show', ['locale' => app()->getLocale(), 'slug' => $featured['slug']]) }}" class="rounded-xl border border-slate-200 bg-slate-50 p-4 text-sm font-medium text-slate-700 hover:border-indigo-300 hover:bg-white hover:text-slate-900 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-200 dark:hover:border-indigo-500/60 dark:hover:bg-slate-900 dark:hover:text-slate-100">
                            {{ $featured['title'] }}
                        </a>
                    @endforeach
                </div>
            </section>

            <section class="mt-6 grid grid-cols-1 lg:grid-cols-3 gap-4">
                <article class="rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
                    <h2 class="text-base font-semibold">{{ $isPl ? 'Krok 1: wybierz 2-3 narzedzia' : 'Step 1: pick 2-3 tools' }}</h2>
                    <p class="mt-2 text-sm text-slate-600 dark:text-slate-300">
                        {{ $isPl ? 'Nie porownuj wszystkiego naraz. Wez tylko narzedzia realnie rozwazane przez Twoj zespol.' : 'Do not compare everything at once. Pick only tools your team seriously considers.' }}
                    </p>
                </article>
                <article class="rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
                    <h2 class="text-base font-semibold">{{ $isPl ? 'Krok 2: uruchom test rownolegly' : 'Step 2: run parallel test' }}</h2>
                    <p class="mt-2 text-sm text-slate-600 dark:text-slate-300">
                        {{ $isPl ? 'Sprawdz te same URL i endpointy przez 7-14 dni. Patrz na opoznienie detekcji i jakosc eskalacji.' : 'Run the same URLs and endpoints for 7-14 days. Compare detection lag and escalation quality.' }}
                    </p>
                </article>
                <article class="rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
                    <h2 class="text-base font-semibold">{{ $isPl ? 'Krok 3: ocen workflow incydentu' : 'Step 3: score incident workflow' }}</h2>
                    <p class="mt-2 text-sm text-slate-600 dark:text-slate-300">
                        {{ $isPl ? 'Najwazniejsze: alert -> owner -> status update -> zamkniecie incydentu. To decyduje o realnym wyniku.' : 'Most important path: alert -> owner -> status update -> incident closure. This decides real outcomes.' }}
                    </p>
                </article>
            </section>

            <section class="mt-8 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
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
                                {{ $isPl ? 'Zobacz porownanie' : 'Open comparison' }}
                            </a>
                        </div>
                    </article>
                @endforeach
            </section>

            <section class="mt-8 rounded-2xl border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900">
                <h2 class="text-xl font-semibold">{{ $isPl ? 'FAQ: porownania monitoringu' : 'FAQ: monitoring comparisons' }}</h2>
                <div class="mt-4 space-y-3 text-sm">
                    <details class="group">
                        <summary class="cursor-pointer font-medium">{{ $isPl ? 'Czy kazda strona porownania jest osobnym URL?' : 'Is each comparison page a separate URL?' }}</summary>
                        <p class="mt-2 text-slate-600 dark:text-slate-300">{{ $isPl ? 'Tak. Kazde porownanie ma osobny slug i jest indeksowalne.' : 'Yes. Every comparison has its own slug and is indexable.' }}</p>
                    </details>
                    <details class="group">
                        <summary class="cursor-pointer font-medium">{{ $isPl ? 'Jak szybko wybrac narzedzie po tych porownaniach?' : 'How fast can we decide after these comparisons?' }}</summary>
                        <p class="mt-2 text-slate-600 dark:text-slate-300">{{ $isPl ? 'Najczesciej po 7-14 dniach testu rownoleglego na krytycznych monitorach.' : 'Usually after 7-14 days of side-by-side testing on critical monitors.' }}</p>
                    </details>
                    <details class="group">
                        <summary class="cursor-pointer font-medium">{{ $isPl ? 'Czy porownania sa dla agencji i dla SaaS?' : 'Are these comparisons useful for agencies and SaaS?' }}</summary>
                        <p class="mt-2 text-slate-600 dark:text-slate-300">{{ $isPl ? 'Tak. Czesc stron jest stricte agency, czesc pod SMB SaaS i ecommerce.' : 'Yes. Some pages target agencies, others focus on SMB SaaS and ecommerce.' }}</p>
                    </details>
                </div>
            </section>
        </main>

        <footer class="border-t border-slate-200/70 bg-white/70 backdrop-blur dark:border-slate-800 dark:bg-slate-950/70">
            <div class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
                <div class="flex flex-wrap items-center justify-between gap-4 text-sm text-slate-500 dark:text-slate-400">
                    <span>&copy; {{ date('Y') }} {{ config('app.name', 'TruMonit') }}</span>
                    <div class="flex items-center gap-3">
                        <a class="hover:underline" href="{{ $pricingUrl }}">{{ __('messages.welcome.nav_pricing') }}</a>
                        <span class="opacity-40">&middot;</span>
                        <a class="hover:underline" href="{{ $solutionsIndexUrl }}">{{ $isPl ? 'Przyklady wdrozen' : 'Implementation examples' }}</a>
                        <span class="opacity-40">&middot;</span>
                        <a class="hover:underline" href="{{ $faqIndexUrl }}">{{ __('faq.nav_faq') }}</a>
                        <span class="opacity-40">&middot;</span>
                        <a class="hover:underline" href="{{ $privacyUrl }}">{{ $isPl ? 'Prywatnosc' : 'Privacy' }}</a>
                        <span class="opacity-40">&middot;</span>
                        <a class="hover:underline" href="{{ $termsUrl }}">{{ $isPl ? 'Warunki' : 'Terms' }}</a>
                    </div>
                </div>
            </div>
        </footer>
    </div>
</body>
</html>
