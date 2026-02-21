<!DOCTYPE html>
@php
    $isPl = app()->getLocale() === 'pl';
    $seoTitle = $isPl
        ? 'TruMonit - Monitoring uptime, SSL i endpointow API'
        : 'TruMonit - Uptime, SSL, and API Endpoint Monitoring';
    $seoDescription = $isPl
        ? 'TruMonit zapewnia monitoring 24/7 stron i API, alerty w czasie rzeczywistym oraz status page dla klientow i zespolow.'
        : 'TruMonit delivers 24/7 website and API monitoring, real-time alerts, and status pages for teams and clients.';
    $homeUrl = route('localized.public.home', ['locale' => app()->getLocale()]);
    $pricingUrl = route('localized.public.pricing', ['locale' => app()->getLocale()]);
    $faqIndexUrl = route('localized.public.faq.index', ['locale' => app()->getLocale()]);
    $cookiesUrl = route('localized.legal.cookies', ['locale' => app()->getLocale()]);
    $privacyUrl = route('localized.legal.privacy', ['locale' => app()->getLocale()]);
    $termsUrl = route('localized.legal.terms', ['locale' => app()->getLocale()]);
    $canonicalUrl = $homeUrl;
    $ogImage = asset('favicon-512x512.png');
    $langSwitchEn = route('localized.public.home', ['locale' => 'en']);
    $langSwitchPl = route('localized.public.home', ['locale' => 'pl']);
    $organizationSchema = [
        '@context' => 'https://schema.org',
        '@type' => 'Organization',
        'name' => config('app.name', 'TruMonit'),
        'url' => url('/'),
        'logo' => $ogImage,
    ];
    $softwareSchema = [
        '@context' => 'https://schema.org',
        '@type' => 'SoftwareApplication',
        'name' => config('app.name', 'TruMonit'),
        'applicationCategory' => 'BusinessApplication',
        'operatingSystem' => 'Web',
        'url' => url('/'),
        'description' => $seoDescription,
        'offers' => [
            '@type' => 'Offer',
            'price' => '0',
            'priceCurrency' => 'USD',
        ],
    ];
@endphp
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $seoTitle }}</title>
    <meta name="description" content="{{ $seoDescription }}">
    <link rel="canonical" href="{{ $canonicalUrl }}">
    <link rel="alternate" hreflang="en" href="{{ route('localized.public.home', ['locale' => 'en']) }}">
    <link rel="alternate" hreflang="pl" href="{{ route('localized.public.home', ['locale' => 'pl']) }}">
    <meta property="og:type" content="website">
    <meta property="og:title" content="{{ $seoTitle }}">
    <meta property="og:description" content="{{ $seoDescription }}">
    <meta property="og:url" content="{{ $canonicalUrl }}">
    <meta property="og:image" content="{{ $ogImage }}">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $seoTitle }}">
    <meta name="twitter:description" content="{{ $seoDescription }}">
    <meta name="twitter:image" content="{{ $ogImage }}">
    <script type="application/ld+json">{!! json_encode($organizationSchema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}</script>
    <script type="application/ld+json">{!! json_encode($softwareSchema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}</script>
    @include('partials.theme-head')
    @include('partials.favicons')
    @include('partials.trucookie-banner')

    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @else
        <script src="https://cdn.tailwindcss.com"></script>
        <script>
            tailwind.config = Object.assign({}, tailwind.config || {}, { darkMode: 'class' });
        </script>
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
                    <a href="#features" class="rounded-md px-3 py-2 text-slate-600 hover:bg-slate-100 hover:text-slate-900 dark:text-slate-300 dark:hover:bg-slate-800 dark:hover:text-slate-100">{{ __('messages.welcome.nav_features') }}</a>
                    <a href="{{ $faqIndexUrl }}" class="rounded-md px-3 py-2 text-slate-600 hover:bg-slate-100 hover:text-slate-900 dark:text-slate-300 dark:hover:bg-slate-800 dark:hover:text-slate-100">{{ __('faq.nav_faq') }}</a>
                    <a href="{{ $pricingUrl }}" class="rounded-md px-3 py-2 text-slate-600 hover:bg-slate-100 hover:text-slate-900 dark:text-slate-300 dark:hover:bg-slate-800 dark:hover:text-slate-100">{{ __('messages.welcome.nav_pricing') }}</a>
                    <a href="#security" class="rounded-md px-3 py-2 text-slate-600 hover:bg-slate-100 hover:text-slate-900 dark:text-slate-300 dark:hover:bg-slate-800 dark:hover:text-slate-100">{{ __('messages.welcome.nav_security') }}</a>
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

        <main class="flex-1 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10 sm:py-14">
            <section class="grid gap-10 lg:grid-cols-2 lg:items-center">
                <div>
                    <div class="inline-flex items-center rounded-full border border-indigo-200 bg-indigo-50 px-3 py-1 text-xs font-medium text-indigo-700 dark:border-indigo-500/40 dark:bg-indigo-500/10 dark:text-indigo-200">
                        {{ __('messages.welcome.hero_badge') }}
                    </div>
                    <h1 class="mt-5 text-4xl sm:text-5xl lg:text-6xl font-semibold tracking-tight leading-tight">
                        {{ __('messages.welcome.hero_title') }}
                    </h1>
                    <p class="mt-5 text-base sm:text-lg text-slate-600 dark:text-slate-300 max-w-2xl">
                        {{ __('messages.welcome.hero_subtitle') }}
                    </p>

                    <div class="mt-8 flex flex-wrap gap-3">
                        <a href="{{ route('register') }}" class="inline-flex items-center rounded-xl bg-slate-900 px-5 py-3 text-sm font-semibold text-white hover:bg-black dark:bg-indigo-500 dark:hover:bg-indigo-400">
                            {{ __('messages.welcome.cta_primary') }}
                        </a>
                        <a href="{{ route('login') }}" class="inline-flex items-center rounded-xl border border-slate-300 bg-white px-5 py-3 text-sm font-semibold text-slate-800 hover:bg-slate-100 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100 dark:hover:bg-slate-800">
                            {{ __('messages.welcome.cta_secondary') }}
                        </a>
                    </div>

                    <p class="mt-4 text-sm text-slate-500 dark:text-slate-400">
                        {{ __('messages.welcome.hero_trust') }}
                    </p>

                    <div class="mt-8 grid grid-cols-3 gap-3">
                        <div class="rounded-xl border border-slate-200 bg-white p-3 dark:border-slate-800 dark:bg-slate-900">
                            <div class="text-xl font-semibold">{{ __('messages.welcome.stat_1_value') }}</div>
                            <div class="text-xs text-slate-500 dark:text-slate-400">{{ __('messages.welcome.stat_1_label') }}</div>
                        </div>
                        <div class="rounded-xl border border-slate-200 bg-white p-3 dark:border-slate-800 dark:bg-slate-900">
                            <div class="text-xl font-semibold">{{ __('messages.welcome.stat_2_value') }}</div>
                            <div class="text-xs text-slate-500 dark:text-slate-400">{{ __('messages.welcome.stat_2_label') }}</div>
                        </div>
                        <div class="rounded-xl border border-slate-200 bg-white p-3 dark:border-slate-800 dark:bg-slate-900">
                            <div class="text-xl font-semibold">{{ __('messages.welcome.stat_3_value') }}</div>
                            <div class="text-xs text-slate-500 dark:text-slate-400">{{ __('messages.welcome.stat_3_label') }}</div>
                        </div>
                    </div>
                </div>

                <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-xl shadow-slate-200/70 dark:border-slate-800 dark:bg-slate-900 dark:shadow-none">
                    <div class="flex items-center justify-between">
                        <h2 class="font-semibold">{{ __('messages.welcome.preview_title') }}</h2>
                        <span class="rounded-full bg-emerald-100 px-2 py-1 text-xs font-semibold text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-300">{{ __('messages.welcome.preview_live') }}</span>
                    </div>
                    <div class="mt-4 space-y-3">
                        <div class="rounded-xl border border-slate-200 p-3 dark:border-slate-700">
                            <div class="flex items-center justify-between">
                                <span class="font-medium text-sm">{{ __('messages.welcome.preview_item_1_title') }}</span>
                                <span class="text-xs text-emerald-600 dark:text-emerald-400">{{ __('messages.welcome.preview_item_1_status') }}</span>
                            </div>
                            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ __('messages.welcome.preview_item_1_text') }}</p>
                        </div>
                        <div class="rounded-xl border border-slate-200 p-3 dark:border-slate-700">
                            <div class="flex items-center justify-between">
                                <span class="font-medium text-sm">{{ __('messages.welcome.preview_item_2_title') }}</span>
                                <span class="text-xs text-amber-600 dark:text-amber-400">{{ __('messages.welcome.preview_item_2_status') }}</span>
                            </div>
                            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ __('messages.welcome.preview_item_2_text') }}</p>
                        </div>
                        <div class="rounded-xl border border-slate-200 p-3 dark:border-slate-700">
                            <div class="flex items-center justify-between">
                                <span class="font-medium text-sm">{{ __('messages.welcome.preview_item_3_title') }}</span>
                                <span class="text-xs text-sky-600 dark:text-sky-400">{{ __('messages.welcome.preview_item_3_status') }}</span>
                            </div>
                            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ __('messages.welcome.preview_item_3_text') }}</p>
                        </div>
                    </div>
                </div>
            </section>

            <section id="methodology" class="mt-10 rounded-2xl border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900">
                <h2 class="text-xl font-semibold tracking-tight">
                    {{ $isPl ? 'Jak mierzymy SLA i czas detekcji' : 'How We Measure SLA and Detection Time' }}
                </h2>
                <p class="mt-2 text-sm text-slate-600 dark:text-slate-300">
                    {{ $isPl ? 'Wyniki opieramy na pomiarach outside-in z wielu probe, deduplikacji incydentow i metrykach lagu kolejki. W panelu operacyjnym utrzymujemy raporty z drillow i kalibracji progow alertow.' : 'We base results on outside-in checks across multiple probes, incident deduplication, and queue lag metrics. Ops panel keeps drill artifacts and alert-threshold calibration history.' }}
                </p>
                <ul class="mt-4 grid gap-2 text-sm text-slate-600 dark:text-slate-300 md:grid-cols-2">
                    <li>• {{ $isPl ? 'SLA i uptime liczone na podstawie snapshotow monitorow oraz statusow publicznych.' : 'SLA and uptime are calculated from monitor snapshots and public statuses.' }}</li>
                    <li>• {{ $isPl ? 'Czas detekcji oparty o p95 opoznien harmonogramu i kolejek skanow.' : 'Detection time is based on p95 scheduler and scan-queue delays.' }}</li>
                    <li>• {{ $isPl ? 'Fałszywe alarmy ograniczane przez quorum multi-region oraz cooldowny.' : 'False alarms are reduced via multi-region quorum and cooldown rules.' }}</li>
                    <li>• {{ $isPl ? 'Dane operacyjne i progi sa przegladane cyklicznie w runbookach.' : 'Operational metrics and thresholds are reviewed regularly in runbooks.' }}</li>
                </ul>
            </section>

            <section id="features" class="mt-14">
                <div class="flex items-end justify-between gap-4">
                    <div>
                        <h2 class="text-2xl sm:text-3xl font-semibold tracking-tight">{{ __('messages.welcome.features_title') }}</h2>
                        <p class="mt-2 text-slate-600 dark:text-slate-300">{{ __('messages.welcome.features_subtitle') }}</p>
                    </div>
                </div>

                <div class="mt-6 grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                    <article class="rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
                        <h3 class="font-semibold">{{ __('messages.welcome.feature_1_title') }}</h3>
                        <p class="mt-2 text-sm text-slate-600 dark:text-slate-300">{{ __('messages.welcome.feature_1_text') }}</p>
                    </article>
                    <article class="rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
                        <h3 class="font-semibold">{{ __('messages.welcome.feature_2_title') }}</h3>
                        <p class="mt-2 text-sm text-slate-600 dark:text-slate-300">{{ __('messages.welcome.feature_2_text') }}</p>
                    </article>
                    <article class="rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
                        <h3 class="font-semibold">{{ __('messages.welcome.feature_3_title') }}</h3>
                        <p class="mt-2 text-sm text-slate-600 dark:text-slate-300">{{ __('messages.welcome.feature_3_text') }}</p>
                    </article>
                    <article class="rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
                        <h3 class="font-semibold">{{ __('messages.welcome.feature_4_title') }}</h3>
                        <p class="mt-2 text-sm text-slate-600 dark:text-slate-300">{{ __('messages.welcome.feature_4_text') }}</p>
                    </article>
                    <article class="rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
                        <h3 class="font-semibold">{{ __('messages.welcome.feature_5_title') }}</h3>
                        <p class="mt-2 text-sm text-slate-600 dark:text-slate-300">{{ __('messages.welcome.feature_5_text') }}</p>
                    </article>
                    <article class="rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
                        <h3 class="font-semibold">{{ __('messages.welcome.feature_6_title') }}</h3>
                        <p class="mt-2 text-sm text-slate-600 dark:text-slate-300">{{ __('messages.welcome.feature_6_text') }}</p>
                    </article>
                </div>
            </section>

            <section id="pricing" class="mt-14">
                <h2 class="text-2xl sm:text-3xl font-semibold tracking-tight">{{ __('messages.welcome.pricing_title') }}</h2>
                <p class="mt-2 text-slate-600 dark:text-slate-300">{{ __('messages.welcome.pricing_subtitle') }}</p>

                <div class="mt-6 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                    <article class="rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
                        <h3 class="font-semibold">{{ __('messages.welcome.price_free_name') }}</h3>
                        <p class="mt-1 text-2xl font-semibold">{{ __('messages.welcome.price_free_value') }}</p>
                        <p class="mt-2 text-sm text-slate-600 dark:text-slate-300">{{ __('messages.welcome.price_free_text') }}</p>
                    </article>
                    <article class="rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
                        <h3 class="font-semibold">{{ __('messages.welcome.price_lite_name') }}</h3>
                        <p class="mt-1 text-2xl font-semibold">{{ __('messages.welcome.price_lite_value') }}</p>
                        <p class="mt-2 text-sm text-slate-600 dark:text-slate-300">{{ __('messages.welcome.price_lite_text') }}</p>
                    </article>
                    <article class="rounded-2xl border-2 border-indigo-500 bg-white p-5 shadow-lg shadow-indigo-200/40 dark:bg-slate-900 dark:shadow-none">
                        <div class="inline-flex rounded-full bg-indigo-100 px-2 py-1 text-xs font-semibold text-indigo-700 dark:bg-indigo-500/20 dark:text-indigo-300">{{ __('messages.welcome.price_recommended') }}</div>
                        <h3 class="mt-2 font-semibold">{{ __('messages.welcome.price_pro_name') }}</h3>
                        <p class="mt-1 text-2xl font-semibold">{{ __('messages.welcome.price_pro_value') }}</p>
                        <p class="mt-2 text-sm text-slate-600 dark:text-slate-300">{{ __('messages.welcome.price_pro_text') }}</p>
                    </article>
                    <article class="rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
                        <h3 class="font-semibold">{{ __('messages.welcome.price_enterprise_name') }}</h3>
                        <p class="mt-1 text-2xl font-semibold">{{ __('messages.welcome.price_enterprise_value') }}</p>
                        <p class="mt-2 text-sm text-slate-600 dark:text-slate-300">{{ __('messages.welcome.price_enterprise_text') }}</p>
                    </article>
                </div>
            </section>

            <section id="security" class="mt-14 rounded-3xl border border-slate-200 bg-white p-6 sm:p-8 dark:border-slate-800 dark:bg-slate-900">
                <h2 class="text-2xl sm:text-3xl font-semibold tracking-tight">{{ __('messages.welcome.security_title') }}</h2>
                <p class="mt-2 text-slate-600 dark:text-slate-300">{{ __('messages.welcome.security_subtitle') }}</p>

                <div class="mt-5 grid gap-3 md:grid-cols-2">
                    <div class="rounded-xl border border-slate-200 bg-slate-50 p-4 text-sm dark:border-slate-700 dark:bg-slate-950/60">{{ __('messages.welcome.security_item_1') }}</div>
                    <div class="rounded-xl border border-slate-200 bg-slate-50 p-4 text-sm dark:border-slate-700 dark:bg-slate-950/60">{{ __('messages.welcome.security_item_2') }}</div>
                    <div class="rounded-xl border border-slate-200 bg-slate-50 p-4 text-sm dark:border-slate-700 dark:bg-slate-950/60">{{ __('messages.welcome.security_item_3') }}</div>
                    <div class="rounded-xl border border-slate-200 bg-slate-50 p-4 text-sm dark:border-slate-700 dark:bg-slate-950/60">{{ __('messages.welcome.security_item_4') }}</div>
                </div>
            </section>

            <section id="faq" class="mt-14 rounded-3xl border border-slate-200 bg-white p-6 sm:p-8 dark:border-slate-800 dark:bg-slate-900">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h2 class="text-2xl sm:text-3xl font-semibold tracking-tight">
                            {{ app()->getLocale() === 'pl' ? 'Najczestsze pytania o monitoring' : 'Most asked monitoring questions' }}
                        </h2>
                        <p class="mt-2 text-slate-600 dark:text-slate-300">
                            {{ app()->getLocale() === 'pl' ? 'FAQ pod konkretne use-case: uptime, API, SSL i incydenty.' : 'FAQ focused on real use cases: uptime, APIs, SSL, and incident workflows.' }}
                        </p>
                    </div>
                    <a href="{{ $faqIndexUrl }}" class="inline-flex rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-100 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800">
                        {{ __('faq.nav_faq') }}
                    </a>
                </div>

                <div class="mt-6 grid gap-3 md:grid-cols-2">
                    <a href="{{ route('localized.public.faq.show', ['locale' => app()->getLocale(), 'slug' => 'what-is-uptime-monitoring']) }}" class="rounded-xl border border-slate-200 bg-slate-50 p-4 text-sm hover:border-indigo-300 hover:bg-white dark:border-slate-700 dark:bg-slate-950 dark:hover:border-indigo-500/60 dark:hover:bg-slate-900">
                        {{ __('faq.what_is_uptime_monitoring_question') }}
                    </a>
                    <a href="{{ route('localized.public.faq.show', ['locale' => app()->getLocale(), 'slug' => 'how-do-i-monitor-api-endpoints']) }}" class="rounded-xl border border-slate-200 bg-slate-50 p-4 text-sm hover:border-indigo-300 hover:bg-white dark:border-slate-700 dark:bg-slate-950 dark:hover:border-indigo-500/60 dark:hover:bg-slate-900">
                        {{ __('faq.how_do_i_monitor_api_endpoints_question') }}
                    </a>
                    <a href="{{ route('localized.public.faq.show', ['locale' => app()->getLocale(), 'slug' => 'how-do-i-reduce-false-alerts']) }}" class="rounded-xl border border-slate-200 bg-slate-50 p-4 text-sm hover:border-indigo-300 hover:bg-white dark:border-slate-700 dark:bg-slate-950 dark:hover:border-indigo-500/60 dark:hover:bg-slate-900">
                        {{ __('faq.how_do_i_reduce_false_alerts_question') }}
                    </a>
                    <a href="{{ route('localized.public.faq.show', ['locale' => app()->getLocale(), 'slug' => 'what-is-a-status-page']) }}" class="rounded-xl border border-slate-200 bg-slate-50 p-4 text-sm hover:border-indigo-300 hover:bg-white dark:border-slate-700 dark:bg-slate-950 dark:hover:border-indigo-500/60 dark:hover:bg-slate-900">
                        {{ __('faq.what_is_a_status_page_question') }}
                    </a>
                </div>
            </section>

            <section class="mt-14 rounded-3xl bg-slate-900 p-8 text-white dark:bg-indigo-600">
                <h2 class="text-2xl font-semibold">{{ __('messages.welcome.final_title') }}</h2>
                <p class="mt-2 text-slate-200 dark:text-indigo-50">{{ __('messages.welcome.final_subtitle') }}</p>
                <div class="mt-5 flex flex-wrap gap-3">
                    <a href="{{ route('register') }}" class="inline-flex rounded-lg bg-white px-5 py-2.5 text-sm font-semibold text-slate-900 hover:bg-slate-100">
                        {{ __('messages.welcome.final_cta') }}
                    </a>
                    <a href="{{ route('login') }}" class="inline-flex rounded-lg border border-white/30 px-5 py-2.5 text-sm font-semibold text-white hover:bg-white/10">
                        {{ __('messages.welcome.cta_secondary') }}
                    </a>
                </div>
            </section>
        </main>

        <footer class="border-t border-slate-200/70 bg-white/70 backdrop-blur dark:border-slate-800 dark:bg-slate-950/70">
            <div class="mx-auto max-w-7xl px-4 py-10 sm:px-6 lg:px-8">
                <div class="flex flex-col gap-6 sm:flex-row sm:items-center sm:justify-between">
                    <div class="flex items-center gap-2">
                        <x-application-logo class="h-6 w-6 fill-current text-slate-900 dark:text-slate-100" />
                        <span class="font-semibold text-slate-900 dark:text-slate-100">{{ config('app.name', 'TruMonit') }}</span>
                    </div>

                    <div class="max-w-xl text-sm text-slate-500 dark:text-slate-400">
                        {{ app()->getLocale() === 'pl' ? 'Monitoring 24/7: uptime, SSL i kluczowe endpointy.' : '24/7 monitoring: uptime, SSL, and key endpoints.' }}
                    </div>

                    <div class="flex flex-wrap items-center gap-3 text-sm text-slate-500 dark:text-slate-400">
                        <a class="hover:underline" href="#features">{{ __('messages.welcome.nav_features') }}</a>
                        <span class="opacity-40">&middot;</span>
                        <a class="hover:underline" href="{{ $pricingUrl }}">{{ __('messages.welcome.nav_pricing') }}</a>
                        <span class="opacity-40">&middot;</span>
                        <a class="hover:underline" href="#security">{{ __('messages.welcome.nav_security') }}</a>
                        <span class="opacity-40">&middot;</span>
                        <a class="hover:underline" href="{{ $faqIndexUrl }}">{{ __('faq.nav_faq') }}</a>
                        <span class="opacity-40">&middot;</span>
                        <a class="hover:underline" href="{{ $cookiesUrl }}">{{ app()->getLocale() === 'pl' ? 'Cookies' : 'Cookies' }}</a>
                        <span class="opacity-40">&middot;</span>
                        <a class="hover:underline" href="{{ $privacyUrl }}">{{ app()->getLocale() === 'pl' ? 'Prywatnosc' : 'Privacy' }}</a>
                        <span class="opacity-40">&middot;</span>
                        <a class="hover:underline" href="{{ $termsUrl }}">{{ app()->getLocale() === 'pl' ? 'Warunki' : 'Terms' }}</a>
                    </div>

                    <div class="text-sm text-slate-500 dark:text-slate-400">&copy; {{ date('Y') }} {{ config('app.name', 'TruMonit') }}</div>
                </div>
            </div>
        </footer>
    </div>
</body>
</html>
