@php
    $question = (string) ($article['question'] ?? '');
    $answerHtml = (string) ($article['answer'] ?? '');
    $isPl = app()->getLocale() === 'pl';
    $seoTitle = trim((string) ($article['meta_title'] ?? ''));
    if ($seoTitle === '') {
        $seoTitle = $question . ' | ' . config('app.name', 'TruMonit');
    }
    $seoDescription = trim((string) ($article['meta_description'] ?? ''));
    if ($seoDescription === '') {
        $seoDescription = \Illuminate\Support\Str::limit(trim(strip_tags($answerHtml)), 160, '');
    }
    $homeUrl = route('localized.public.home', ['locale' => app()->getLocale()]);
    $pricingUrl = route('localized.public.pricing', ['locale' => app()->getLocale()]);
    $faqIndexUrl = route('localized.public.faq.index', ['locale' => app()->getLocale()]);
    $solutionsIndexUrl = \Illuminate\Support\Facades\Route::has('localized.public.solutions.index')
        ? route('localized.public.solutions.index', ['locale' => app()->getLocale()])
        : null;
    $solutionsShowRoute = \Illuminate\Support\Facades\Route::has('localized.public.solutions.show')
        ? 'localized.public.solutions.show'
        : null;
    $privacyUrl = route('localized.legal.privacy', ['locale' => app()->getLocale()]);
    $termsUrl = route('localized.legal.terms', ['locale' => app()->getLocale()]);
    $canonicalUrl = route('localized.public.faq.show', ['locale' => app()->getLocale(), 'slug' => $article['slug']]);
    $langSwitchEn = route('localized.public.faq.show', ['locale' => 'en', 'slug' => $article['slug']]);
    $langSwitchPl = route('localized.public.faq.show', ['locale' => 'pl', 'slug' => $article['slug']]);
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
                'name' => $isPl ? 'FAQ' : 'FAQ',
                'item' => $faqIndexUrl,
            ],
            [
                '@type' => 'ListItem',
                'position' => 3,
                'name' => $question,
                'item' => $canonicalUrl,
            ],
        ],
    ];
    $articleSchema = [
        '@context' => 'https://schema.org',
        '@type' => 'Article',
        'headline' => $question,
        'description' => $seoDescription,
        'articleBody' => trim(strip_tags($answerHtml)),
        'inLanguage' => $isPl ? 'pl-PL' : 'en-US',
        'url' => $canonicalUrl,
        'publisher' => [
            '@type' => 'Organization',
            'name' => config('app.name', 'TruMonit'),
            'url' => url('/'),
        ],
    ];
    $schemaGraph = [
        '@context' => 'https://schema.org',
        '@graph' => [$articleSchema, $breadcrumbSchema],
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
    <meta property="og:type" content="article">
    <meta property="og:title" content="{{ $seoTitle }}">
    <meta property="og:description" content="{{ $seoDescription }}">
    <meta property="og:url" content="{{ $canonicalUrl }}">
    <meta property="og:image" content="{{ asset('android-chrome-512x512.png') }}">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $seoTitle }}">
    <meta name="twitter:description" content="{{ $seoDescription }}">
    <meta name="twitter:image" content="{{ asset('android-chrome-512x512.png') }}">
    <script type="application/ld+json">{!! json_encode($schemaGraph, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
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
                    @if ($solutionsIndexUrl)
                        <a href="{{ $solutionsIndexUrl }}" class="rounded-md px-3 py-2 text-slate-600 hover:bg-slate-100 hover:text-slate-900 dark:text-slate-300 dark:hover:bg-slate-800 dark:hover:text-slate-100">{{ $isPl ? 'Przykłady wdrożeń' : 'Implementation examples' }}</a>
                    @endif
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

        <main class="flex-1 max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-10 sm:py-14">
            <nav aria-label="Breadcrumb" class="text-sm text-slate-500 dark:text-slate-400">
                <ol class="flex flex-wrap items-center gap-2">
                    <li><a href="{{ $homeUrl }}" class="hover:text-slate-900 dark:hover:text-slate-100">{{ config('app.name', 'TruMonit') }}</a></li>
                    <li aria-hidden="true">/</li>
                    <li><a href="{{ $faqIndexUrl }}" class="hover:text-slate-900 dark:hover:text-slate-100">{{ __('faq.nav_faq') }}</a></li>
                    <li aria-hidden="true">/</li>
                    <li class="text-slate-700 dark:text-slate-200">{{ $question }}</li>
                </ol>
            </nav>

            <a href="{{ $faqIndexUrl }}" class="inline-flex items-center gap-2 rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700 hover:bg-slate-100 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800">
                <span aria-hidden="true">&larr;</span>
                <span>{{ __('faq.back_to_index') }}</span>
            </a>

            <section class="mt-6 rounded-2xl border border-amber-200 bg-amber-50/80 p-5 dark:border-amber-700/60 dark:bg-amber-950/30">
                <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                    <div>
                        <div class="inline-flex rounded-full border border-amber-300 px-3 py-1 text-xs font-semibold uppercase tracking-[0.18em] text-amber-800 dark:border-amber-600 dark:text-amber-200">
                            {{ $isPl ? 'FAQ / strona informacyjna' : 'FAQ / educational page' }}
                        </div>
                        <p class="mt-3 max-w-3xl text-sm text-slate-700 dark:text-slate-200">
                            {{ $isPl
                                ? 'Ta strona odpowiada na konkretne pytanie i pomaga zrozumieć temat. Jeśli chcesz przejść do gotowego wdrożenia, otwórz powiązane przykłady poniżej albo zobacz pricing.'
                                : 'This page answers a specific question. If you need a ready implementation path, open the related solution pages below or jump to pricing.' }}
                        </p>
                    </div>
                    <div class="flex flex-wrap gap-3">
                        @if ($solutionsIndexUrl)
                            <a href="{{ $solutionsIndexUrl }}" class="inline-flex rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-900 hover:bg-slate-100 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100 dark:hover:bg-slate-800">
                                {{ $isPl ? 'Przejdź do wdrożeń' : 'Open solution pages' }}
                            </a>
                        @endif
                        <a href="{{ $pricingUrl }}" class="inline-flex rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-black dark:bg-indigo-600 dark:hover:bg-indigo-500">
                            {{ __('messages.welcome.nav_pricing') }}
                        </a>
                    </div>
                </div>
            </section>

            <article class="mt-6 rounded-2xl border border-slate-200 bg-white p-6 sm:p-8 dark:border-slate-800 dark:bg-slate-900">
                <h1 class="text-3xl sm:text-4xl font-semibold tracking-tight">{{ $question }}</h1>
                <div class="prose prose-lg prose-slate max-w-none mt-6 dark:prose-invert">
                    {!! $answerHtml !!}
                </div>
            </article>

            @if (! empty($relatedArticles))
                <section class="mt-8 rounded-2xl border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900">
                    <h2 class="text-xl font-semibold">{{ __('faq.related_questions') }}</h2>
                    <ul class="mt-4 space-y-3">
                        @foreach ($relatedArticles as $related)
                            <li>
                                <a href="{{ route('localized.public.faq.show', ['locale' => app()->getLocale(), 'slug' => $related['slug']]) }}" class="text-indigo-600 hover:underline dark:text-indigo-300">
                                    {{ $related['question'] }}
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endif

            @if (! empty($relatedSolutions) && $solutionsShowRoute)
                <section class="mt-8 rounded-2xl border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                        <h2 class="text-xl font-semibold">{{ $isPl ? 'Powiązane przykłady wdrożeń' : 'Related implementation examples' }}</h2>
                        @if ($solutionsIndexUrl)
                            <a href="{{ $solutionsIndexUrl }}" class="text-sm font-medium text-indigo-600 hover:underline dark:text-indigo-300">
                                {{ $isPl ? 'Zobacz wszystkie' : 'See all' }}
                            </a>
                        @endif
                    </div>
                    <div class="mt-4 grid grid-cols-1 md:grid-cols-3 gap-4">
                        @foreach ($relatedSolutions as $solution)
                            <a href="{{ route($solutionsShowRoute, ['locale' => app()->getLocale(), 'slug' => $solution['slug']]) }}" class="rounded-xl border border-slate-200 bg-slate-50 p-4 text-sm hover:border-indigo-300 hover:bg-white dark:border-slate-700 dark:bg-slate-950 dark:hover:border-indigo-500/60 dark:hover:bg-slate-900">
                                <div class="font-semibold">{{ $solution['title'] }}</div>
                                <div class="mt-2 text-slate-600 dark:text-slate-300">{{ $solution['summary'] }}</div>
                            </a>
                        @endforeach
                    </div>
                </section>
            @endif

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
                        @if ($solutionsIndexUrl)
                            <a class="hover:underline" href="{{ $solutionsIndexUrl }}">{{ $isPl ? 'Przykłady wdrożeń' : 'Implementation examples' }}</a>
                            <span class="opacity-40">&middot;</span>
                        @endif
                        <a class="hover:underline" href="{{ $faqIndexUrl }}">{{ __('faq.nav_faq') }}</a>
                        <span class="opacity-40">&middot;</span>
                        <a class="hover:underline" href="{{ $privacyUrl }}">{{ app()->getLocale() === 'pl' ? 'Prywatność' : 'Privacy' }}</a>
                        <span class="opacity-40">&middot;</span>
                        <a class="hover:underline" href="{{ $termsUrl }}">{{ app()->getLocale() === 'pl' ? 'Warunki' : 'Terms' }}</a>
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
