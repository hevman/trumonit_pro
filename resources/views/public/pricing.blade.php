@php
    $isPl = app()->getLocale() === 'pl';
    $seoTitle = $isPl
        ? 'Cennik monitoringu dla SaaS, agencji i e-commerce | TruMonit'
        : 'Monitoring Pricing for SaaS, Agencies, and Ecommerce | TruMonit';
    $seoDescription = $isPl
        ? 'Porównaj plany TruMonit dla SaaS, software house i sklepów online: monitory, login/API/checkout, status page, SEO Risk i alerting.'
        : 'Compare TruMonit plans for SaaS teams, agencies, and ecommerce: monitors, login/API/checkout coverage, status pages, SEO risk, and alerting.';
    $homeUrl = route('localized.public.home', ['locale' => app()->getLocale()]);
    $pricingUrl = route('localized.public.pricing', ['locale' => app()->getLocale()]);
    $faqIndexUrl = route('localized.public.faq.index', ['locale' => app()->getLocale()]);
    $solutionsIndexUrl = route('localized.public.solutions.index', ['locale' => app()->getLocale()]);
    $cookiesUrl = route('localized.legal.cookies', ['locale' => app()->getLocale()]);
    $privacyUrl = route('localized.legal.privacy', ['locale' => app()->getLocale()]);
    $termsUrl = route('localized.legal.terms', ['locale' => app()->getLocale()]);
    $canonicalUrl = $pricingUrl;
    $langSwitchEn = route('localized.public.pricing', ['locale' => 'en']);
    $langSwitchPl = route('localized.public.pricing', ['locale' => 'pl']);
    $offersSchema = [
        '@context' => 'https://schema.org',
        '@type' => 'OfferCatalog',
        'name' => $isPl ? 'Plany TruMonit' : 'TruMonit plans',
        'itemListElement' => [
            ['@type' => 'Offer', 'name' => __('messages.welcome.price_free_name'), 'price' => '0', 'priceCurrency' => 'USD'],
            ['@type' => 'Offer', 'name' => __('messages.welcome.price_lite_name'), 'price' => '29', 'priceCurrency' => 'USD'],
            ['@type' => 'Offer', 'name' => __('messages.welcome.price_pro_name'), 'price' => '79', 'priceCurrency' => 'USD'],
            ['@type' => 'Offer', 'name' => __('messages.welcome.price_enterprise_name'), 'price' => '289', 'priceCurrency' => 'USD'],
        ],
    ];
    $matrixRows = $isPl
        ? [
            [
                'label' => 'Aktywne monitory',
                'free' => '5',
                'lite' => '100',
                'pro' => '300',
                'enterprise' => '1000',
            ],
            [
                'label' => 'Minimalny interwał sprawdzeń',
                'free' => '5 min',
                'lite' => '1 min',
                'pro' => '1 min',
                'enterprise' => '1 min',
            ],
            [
                'label' => 'Strony statusowe',
                'free' => '1',
                'lite' => '3',
                'pro' => '10',
                'enterprise' => '50',
            ],
            [
                'label' => 'Kanały alertów',
                'free' => 'E-mail',
                'lite' => 'E-mail + webhook',
                'pro' => 'E-mail + Slack/Teams/webhook',
                'enterprise' => 'E-mail + Slack/Teams/Discord/webhook + reguły routingu',
            ],
            [
                'label' => 'Retencja danych',
                'free' => '7 dni',
                'lite' => '30 dni',
                'pro' => '90 dni',
                'enterprise' => '365 dni',
            ],
            [
                'label' => 'Sondy prywatne (na własnej infrastrukturze)',
                'free' => 'Nie',
                'lite' => 'Nie',
                'pro' => 'Tak (do 30)',
                'enterprise' => 'Tak (bez limitu)',
            ],
            [
                'label' => 'Priorytetowe wdrożenie i wsparcie',
                'free' => 'Nie',
                'lite' => 'Nie',
                'pro' => 'Nie',
                'enterprise' => 'Tak',
            ],
            [
                'label' => 'Moduł SEO (GSC + GA4)',
                'free' => 'Tak',
                'lite' => 'Tak',
                'pro' => 'Tak',
                'enterprise' => 'Tak',
            ],
            [
                'label' => 'Limit właściwości SEO',
                'free' => '1',
                'lite' => '3',
                'pro' => '10',
                'enterprise' => '30',
            ],
            [
                'label' => 'Automatyczna synchronizacja SEO',
                'free' => 'Nie',
                'lite' => 'Tak',
                'pro' => 'Tak',
                'enterprise' => 'Tak',
            ],
            [
                'label' => 'Sygnały aktualizacji Google Core',
                'free' => 'Tak',
                'lite' => 'Tak',
                'pro' => 'Tak',
                'enterprise' => 'Tak',
            ],
            [
                'label' => 'Sygnał ryzyka utraty linków',
                'free' => 'Nie',
                'lite' => 'Nie',
                'pro' => 'Tak',
                'enterprise' => 'Tak',
            ],
        ]
        : [
            [
                'label' => 'Monitors',
                'free' => '5',
                'lite' => '100',
                'pro' => '300',
                'enterprise' => '1000',
            ],
            [
                'label' => 'Min check interval',
                'free' => '5 min',
                'lite' => '1 min',
                'pro' => '1 min',
                'enterprise' => '1 min',
            ],
            [
                'label' => 'Status pages',
                'free' => '1',
                'lite' => '3',
                'pro' => '10',
                'enterprise' => '50',
            ],
            [
                'label' => 'Alert integrations',
                'free' => 'Email',
                'lite' => 'Email + webhook',
                'pro' => 'Email + Slack/Teams/webhook',
                'enterprise' => 'Email + Slack/Teams/Discord/webhook + routing',
            ],
            [
                'label' => 'Data retention',
                'free' => '7 days',
                'lite' => '30 days',
                'pro' => '90 days',
                'enterprise' => '365 days',
            ],
            [
                'label' => 'Private probes (self-hosted)',
                'free' => 'X',
                'lite' => 'X',
                'pro' => 'Yes (up to 30)',
                'enterprise' => 'Yes (unlimited)',
            ],
            [
                'label' => 'Priority onboarding / support',
                'free' => 'X',
                'lite' => 'X',
                'pro' => 'X',
                'enterprise' => 'Yes',
            ],
            [
                'label' => 'SEO Risk (GSC + GA4)',
                'free' => 'Yes',
                'lite' => 'Yes',
                'pro' => 'Yes',
                'enterprise' => 'Yes',
            ],
            [
                'label' => 'SEO properties limit',
                'free' => '1',
                'lite' => '3',
                'pro' => '10',
                'enterprise' => '30',
            ],
            [
                'label' => 'Auto SEO sync',
                'free' => 'No',
                'lite' => 'Yes',
                'pro' => 'Yes',
                'enterprise' => 'Yes',
            ],
            [
                'label' => 'Core Update signal',
                'free' => 'Yes',
                'lite' => 'Yes',
                'pro' => 'Yes',
                'enterprise' => 'Yes',
            ],
            [
                'label' => 'Backlink risk signal',
                'free' => 'No',
                'lite' => 'No',
                'pro' => 'Yes',
                'enterprise' => 'Yes',
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
    <link rel="canonical" href="{{ $canonicalUrl }}">
    <link rel="alternate" hreflang="en" href="{{ $langSwitchEn }}">
    <link rel="alternate" hreflang="pl" href="{{ $langSwitchPl }}">
    <link rel="alternate" hreflang="x-default" href="{{ $langSwitchEn }}">
    <meta property="og:type" content="website">
    <meta property="og:title" content="{{ $seoTitle }}">
    <meta property="og:description" content="{{ $seoDescription }}">
    <meta property="og:url" content="{{ $canonicalUrl }}">
    <meta property="og:image" content="{{ asset('android-chrome-512x512.png') }}">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $seoTitle }}">
    <meta name="twitter:description" content="{{ $seoDescription }}">
    <meta name="twitter:image" content="{{ asset('android-chrome-512x512.png') }}">
    <script type="application/ld+json">{!! json_encode($offersSchema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
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
                    <a href="{{ $solutionsIndexUrl }}" class="rounded-md px-3 py-2 text-slate-600 hover:bg-slate-100 hover:text-slate-900 dark:text-slate-300 dark:hover:bg-slate-800 dark:hover:text-slate-100">{{ $isPl ? 'Przykłady wdrożeń' : 'Implementation examples' }}</a>
                    <a href="{{ $faqIndexUrl }}" class="rounded-md px-3 py-2 text-slate-600 hover:bg-slate-100 hover:text-slate-900 dark:text-slate-300 dark:hover:bg-slate-800 dark:hover:text-slate-100">{{ __('faq.nav_faq') }}</a>
                    <a href="{{ $pricingUrl }}" class="rounded-md px-3 py-2 text-slate-900 bg-slate-100 dark:text-slate-100 dark:bg-slate-800">{{ __('messages.welcome.nav_pricing') }}</a>
                </nav>

                <div class="flex items-center gap-2">
                    <a href="{{ $langSwitchEn }}" class="rounded-md px-2 py-1 text-xs {{ app()->getLocale() === 'en' ? 'bg-slate-900 text-white dark:bg-slate-100 dark:text-slate-900' : 'text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800' }}">EN</a>
                    <a href="{{ $langSwitchPl }}" class="rounded-md px-2 py-1 text-xs {{ app()->getLocale() === 'pl' ? 'bg-slate-900 text-white dark:bg-slate-100 dark:text-slate-900' : 'text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800' }}">PL</a>
                    <a href="{{ route('login') }}" class="hidden sm:inline-flex rounded-lg border border-slate-300 px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-100 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800">{{ __('messages.welcome.cta_secondary') }}</a>
                    <a href="{{ route('register') }}" class="inline-flex rounded-lg bg-slate-900 px-3 py-1.5 text-sm font-medium text-white hover:bg-black dark:bg-indigo-600 dark:hover:bg-indigo-500">{{ __('messages.welcome.cta_primary') }}</a>
                </div>
            </div>
        </header>

        <main class="flex-1 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10 sm:py-14">
            <section class="max-w-3xl">
                <div class="inline-flex items-center rounded-full border border-indigo-200 bg-indigo-50 px-3 py-1 text-xs font-medium text-indigo-700 dark:border-indigo-500/40 dark:bg-indigo-500/10 dark:text-indigo-200">
                    {{ $isPl ? 'Plan dla kazdego etapu wzrostu' : 'Plans for every growth stage' }}
                </div>
                <h1 class="mt-5 text-4xl sm:text-5xl font-semibold tracking-tight leading-tight">
                    {{ __('messages.welcome.pricing_title') }}
                </h1>
                <p class="mt-4 text-base sm:text-lg text-slate-600 dark:text-slate-300">
                    {{ __('messages.welcome.pricing_subtitle') }}
                </p>
            </section>

            <section class="mt-8 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                <article class="rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
                    <h2 class="font-semibold">{{ __('messages.welcome.price_free_name') }}</h2>
                    <p class="mt-1 text-2xl font-semibold">{{ __('messages.welcome.price_free_value') }}</p>
                    <p class="mt-2 text-sm text-slate-600 dark:text-slate-300">{{ __('messages.welcome.price_free_text') }}</p>
                    <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">{{ $isPl ? 'Moduł SEO w pakiecie' : 'SEO Risk included' }}</p>
                    <a href="{{ route('register') }}" class="mt-4 inline-flex rounded-lg border border-slate-300 px-4 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-100 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800">
                        {{ $isPl ? 'Zacznij za darmo' : 'Start free' }}
                    </a>
                </article>
                <article class="rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
                    <h2 class="font-semibold">{{ __('messages.welcome.price_lite_name') }}</h2>
                    <p class="mt-1 text-2xl font-semibold">{{ __('messages.welcome.price_lite_value') }}</p>
                    <p class="mt-2 text-sm text-slate-600 dark:text-slate-300">{{ __('messages.welcome.price_lite_text') }}</p>
                    <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">{{ $isPl ? 'Moduł SEO w pakiecie' : 'SEO Risk included' }}</p>
                    <a href="{{ route('register') }}" class="mt-4 inline-flex rounded-lg border border-slate-300 px-4 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-100 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800">
                        {{ $isPl ? 'Wybierz Lite' : 'Choose Lite' }}
                    </a>
                </article>
                <article class="rounded-2xl border-2 border-indigo-500 bg-white p-5 shadow-lg shadow-indigo-200/40 dark:bg-slate-900 dark:shadow-none">
                    <div class="inline-flex rounded-full bg-indigo-100 px-2 py-1 text-xs font-semibold text-indigo-700 dark:bg-indigo-500/20 dark:text-indigo-300">{{ __('messages.welcome.price_recommended') }}</div>
                    <h2 class="mt-2 font-semibold">{{ __('messages.welcome.price_pro_name') }}</h2>
                    <p class="mt-1 text-2xl font-semibold">{{ __('messages.welcome.price_pro_value') }}</p>
                    <p class="mt-2 text-sm text-slate-600 dark:text-slate-300">{{ __('messages.welcome.price_pro_text') }}</p>
                    <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">{{ $isPl ? 'Moduł SEO w pakiecie' : 'SEO Risk included' }}</p>
                    <a href="{{ route('register') }}" class="mt-4 inline-flex rounded-lg bg-slate-900 px-4 py-2 text-xs font-semibold text-white hover:bg-black dark:bg-indigo-600 dark:hover:bg-indigo-500">
                        {{ $isPl ? 'Wybierz Pro' : 'Choose Pro' }}
                    </a>
                </article>
                <article class="rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
                    <h2 class="font-semibold">{{ __('messages.welcome.price_enterprise_name') }}</h2>
                    <p class="mt-1 text-2xl font-semibold">{{ __('messages.welcome.price_enterprise_value') }}</p>
                    <p class="mt-2 text-sm text-slate-600 dark:text-slate-300">{{ __('messages.welcome.price_enterprise_text') }}</p>
                    <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">{{ $isPl ? 'Moduł SEO w pakiecie' : 'SEO Risk included' }}</p>
                    <a href="{{ route('register') }}" class="mt-4 inline-flex rounded-lg border border-slate-300 px-4 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-100 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800">
                        {{ $isPl ? 'Wybierz Enterprise' : 'Choose Enterprise' }}
                    </a>
                </article>
            </section>

            <section class="mt-8 rounded-2xl border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900 overflow-x-auto">
                <div class="border-b border-slate-200 px-4 py-3 text-sm text-slate-600 dark:border-slate-800 dark:text-slate-300">
                    {{ $isPl ? 'Każdy plan obejmuje moduł SEO (GSC + GA4). Sygnał ryzyka utraty linków jest dostępny od planu Pro.' : 'Every plan includes SEO Risk (GSC + GA4). Backlink risk signal is available from Pro.' }}
                </div>
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-slate-700 dark:bg-slate-950/60 dark:text-slate-200">
                        <tr>
                            <th class="px-4 py-3 text-left">{{ $isPl ? 'Funkcja' : 'Feature' }}</th>
                            <th class="px-4 py-3 text-center">{{ __('messages.welcome.price_free_name') }}</th>
                            <th class="px-4 py-3 text-center">{{ __('messages.welcome.price_lite_name') }}</th>
                            <th class="px-4 py-3 text-center">{{ __('messages.welcome.price_pro_name') }}</th>
                            <th class="px-4 py-3 text-center">{{ __('messages.welcome.price_enterprise_name') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 dark:divide-slate-800">
                        @foreach ($matrixRows as $row)
                            <tr>
                                <td class="px-4 py-3 font-medium text-slate-900 dark:text-slate-100">{{ $row['label'] }}</td>
                                <td class="px-4 py-3 text-center text-slate-600 dark:text-slate-300">{{ $row['free'] }}</td>
                                <td class="px-4 py-3 text-center text-slate-600 dark:text-slate-300">{{ $row['lite'] }}</td>
                                <td class="px-4 py-3 text-center text-slate-600 dark:text-slate-300">{{ $row['pro'] }}</td>
                                <td class="px-4 py-3 text-center text-slate-600 dark:text-slate-300">{{ $row['enterprise'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                <p class="px-4 pb-4 pt-2 text-xs text-slate-500 dark:text-slate-400">
                    {{ $isPl ? 'Limity dotyczą aktywnych monitorów w przestrzeni roboczej. Potrzebujesz większej skali? Napisz do nas.' : 'Limits apply to active monitors per workspace. Need bigger scale? Contact us.' }}
                </p>
            </section>

            <section class="mt-8 rounded-2xl bg-slate-900 p-6 sm:p-8 text-white dark:bg-indigo-600">
                <h2 class="text-2xl font-semibold">
                    {{ $isPl ? 'Potrzebujesz wsparcia przy wdrożeniu?' : 'Need onboarding support?' }}
                </h2>
                <p class="mt-2 text-slate-200 dark:text-indigo-50">
                    {{ $isPl ? 'Pomożemy z konfiguracją monitoringu, routingiem alertów i migracją z obecnego narzędzia.' : 'We can help with monitoring setup, alert routing, and migration from your current tool.' }}
                </p>
                <div class="mt-5 flex flex-wrap gap-3">
                    <a href="mailto:support@trumonit.pro" class="inline-flex rounded-lg bg-white px-5 py-2.5 text-sm font-semibold text-slate-900 hover:bg-slate-100">
                        {{ $isPl ? 'Napisz do nas' : 'Contact us' }}
                    </a>
                    <a href="{{ route('register') }}" class="inline-flex rounded-lg border border-white/30 px-5 py-2.5 text-sm font-semibold text-white hover:bg-white/10">
                        {{ __('messages.welcome.final_cta') }}
                    </a>
                </div>
            </section>
        </main>

        <footer class="border-t border-slate-200/70 bg-white/70 backdrop-blur dark:border-slate-800 dark:bg-slate-950/70">
            <div class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
                <div class="flex flex-wrap items-center justify-between gap-4 text-sm text-slate-500 dark:text-slate-400">
                    <span>&copy; {{ date('Y') }} {{ config('app.name', 'TruMonit') }}</span>
                    <div class="flex items-center gap-3">
                        <a class="hover:underline" href="{{ $homeUrl }}">{{ $isPl ? 'Strona główna' : 'Home' }}</a>
                        <span class="opacity-40">&middot;</span>
                        <a class="hover:underline" href="{{ $pricingUrl }}">{{ __('messages.welcome.nav_pricing') }}</a>
                        <span class="opacity-40">&middot;</span>
                        <a class="hover:underline" href="{{ $solutionsIndexUrl }}">{{ $isPl ? 'Przykłady wdrożeń' : 'Implementation examples' }}</a>
                        <span class="opacity-40">&middot;</span>
                        <a class="hover:underline" href="{{ $faqIndexUrl }}">{{ __('faq.nav_faq') }}</a>
                        <span class="opacity-40">&middot;</span>
                        <a class="hover:underline" href="{{ $cookiesUrl }}">{{ $isPl ? 'Cookies' : 'Cookies' }}</a>
                        <span class="opacity-40">&middot;</span>
                        <a class="hover:underline" href="{{ $privacyUrl }}">{{ $isPl ? 'Prywatność' : 'Privacy' }}</a>
                        <span class="opacity-40">&middot;</span>
                        <a class="hover:underline" href="{{ $termsUrl }}">{{ $isPl ? 'Warunki' : 'Terms' }}</a>
                    </div>
                </div>
                <div class="mt-3 text-xs text-slate-500 dark:text-slate-400">
                    {{ $isPl ? 'Produkt rozwijany przez' : 'A product by' }}
                    <a class="font-medium text-slate-700 hover:underline dark:text-slate-200" href="https://hevman.com" target="_blank" rel="noopener">
                        Hevman Core
                    </a>
                </div>
            </div>
        </footer>
    </div>
</body>
</html>
