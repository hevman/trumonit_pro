@php
    $isPl = app()->getLocale() === 'pl';
    $seoTitle = $isPl
        ? 'FAQ: monitoring stron, API, loginu i redirectów | TruMonit'
        : 'FAQ: website, API, login, and redirect monitoring | TruMonit';
    $seoDescription = $isPl
        ? 'Odpowiedzi na pytania o monitoring loginu, API, redirectów, SSL, checkoutu, status page i redukcję fałszywych alertów.'
        : 'Answers about monitoring login flows, APIs, redirects, SSL, checkout, status pages, and reducing false alerts.';
    $homeUrl = route('localized.public.home', ['locale' => app()->getLocale()]);
    $pricingUrl = route('localized.public.pricing', ['locale' => app()->getLocale()]);
    $faqIndexUrl = route('localized.public.faq.index', ['locale' => app()->getLocale()]);
    $solutionsIndexUrl = route('localized.public.solutions.index', ['locale' => app()->getLocale()]);
    $privacyUrl = route('localized.legal.privacy', ['locale' => app()->getLocale()]);
    $termsUrl = route('localized.legal.terms', ['locale' => app()->getLocale()]);
    $canonicalUrl = $faqIndexUrl;
    $langSwitchEn = route('localized.public.faq.index', ['locale' => 'en']);
    $langSwitchPl = route('localized.public.faq.index', ['locale' => 'pl']);
    $faqSchema = [
        '@context' => 'https://schema.org',
        '@type' => 'FAQPage',
        'mainEntity' => collect($articles)->map(static function (array $article): array {
            return [
                '@type' => 'Question',
                'name' => (string) ($article['question'] ?? ''),
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text' => trim(strip_tags((string) ($article['answer'] ?? ''))),
                ],
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
    <script type="application/ld+json">{!! json_encode($faqSchema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
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
                    <a href="{{ $faqIndexUrl }}" class="rounded-md px-3 py-2 text-slate-900 bg-slate-100 dark:text-slate-100 dark:bg-slate-800">{{ __('faq.nav_faq') }}</a>
                    <a href="{{ $pricingUrl }}" class="rounded-md px-3 py-2 text-slate-600 hover:bg-slate-100 hover:text-slate-900 dark:text-slate-300 dark:hover:bg-slate-800 dark:hover:text-slate-100">{{ __('messages.welcome.nav_pricing') }}</a>
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
            <section class="rounded-2xl border border-slate-200 bg-white p-6 sm:p-8 dark:border-slate-800 dark:bg-slate-900">
                <h1 class="text-3xl sm:text-4xl font-semibold tracking-tight">{{ __('faq.page_title') }}</h1>
                <p class="mt-3 text-slate-600 dark:text-slate-300">{{ __('faq.page_description') }}</p>

                <div class="mt-6">
                    <label for="faq-search" class="sr-only">{{ __('faq.search_label') }}</label>
                    <input
                        id="faq-search"
                        type="search"
                        class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900 outline-none ring-0 placeholder:text-slate-400 focus:border-indigo-400 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100"
                        placeholder="{{ __('faq.search_placeholder') }}"
                        autocomplete="off"
                    >
                </div>

                <div id="faq-list" class="mt-6 space-y-3">
                    @foreach ($articles as $article)
                        <article
                            data-faq-item
                            data-question="{{ \Illuminate\Support\Str::lower((string) ($article['question'] ?? '')) }}"
                            class="rounded-xl border border-slate-200 bg-slate-50 p-4 transition hover:border-indigo-300 hover:bg-white dark:border-slate-700 dark:bg-slate-950 dark:hover:border-indigo-500/60 dark:hover:bg-slate-900"
                        >
                            <a href="{{ route('localized.public.faq.show', ['locale' => app()->getLocale(), 'slug' => $article['slug']]) }}" class="block">
                                <h2 class="text-base font-semibold text-slate-900 dark:text-slate-100">{{ $article['question'] }}</h2>
                                <p class="mt-2 text-sm text-slate-600 dark:text-slate-300">
                                    {{ \Illuminate\Support\Str::limit(trim(strip_tags((string) ($article['answer'] ?? ''))), 180, '...') }}
                                </p>
                                <span class="mt-3 inline-flex text-sm font-medium text-indigo-600 dark:text-indigo-300">{{ __('faq.read_answer') }}</span>
                            </a>
                        </article>
                    @endforeach
                </div>

                <p id="faq-empty" class="mt-5 hidden text-sm text-slate-500 dark:text-slate-400">{{ __('faq.no_results') }}</p>
            </section>

            <section class="mt-8 rounded-2xl bg-slate-900 p-6 sm:p-8 text-white dark:bg-indigo-600">
                <h2 class="text-2xl font-semibold">{{ __('faq.cta_title') }}</h2>
                <p class="mt-2 text-slate-200 dark:text-indigo-50">{{ __('faq.cta_body') }}</p>
                <div class="mt-5 flex flex-wrap gap-3">
                    <a href="{{ route('register') }}" class="inline-flex rounded-lg bg-white px-5 py-2.5 text-sm font-semibold text-slate-900 hover:bg-slate-100">{{ __('faq.cta_primary') }}</a>
                    <a href="{{ route('login') }}" class="inline-flex rounded-lg border border-white/30 px-5 py-2.5 text-sm font-semibold text-white hover:bg-white/10">{{ __('faq.cta_secondary') }}</a>
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

    <script>
        (function () {
            const input = document.getElementById('faq-search');
            const empty = document.getElementById('faq-empty');
            const items = Array.from(document.querySelectorAll('[data-faq-item]'));
            if (!input || !empty || items.length === 0) return;

            const applyFilter = function () {
                const query = (input.value || '').trim().toLowerCase();
                let visible = 0;

                for (const item of items) {
                    const text = (item.dataset.question || '').toLowerCase();
                    const show = query === '' || text.includes(query);
                    item.classList.toggle('hidden', !show);
                    if (show) visible += 1;
                }

                empty.classList.toggle('hidden', query === '' || visible > 0);
            };

            input.addEventListener('input', applyFilter, { passive: true });
            applyFilter();
        })();
    </script>
</body>
</html>
