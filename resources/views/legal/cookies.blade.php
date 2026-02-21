<!DOCTYPE html>
@php
    $isPl = app()->getLocale() === 'pl';
    $title = $isPl ? 'Polityka cookies - TruMonit' : 'Cookie Policy - TruMonit';
    $updatedAt = '2026-02-21';
    $homeUrl = route('localized.public.home', ['locale' => app()->getLocale()]);
    $privacyUrl = route('localized.legal.privacy', ['locale' => app()->getLocale()]);
    $termsUrl = route('localized.legal.terms', ['locale' => app()->getLocale()]);
@endphp
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }}</title>
    @include('partials.trucookie-banner')
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @else
        <script src="https://cdn.tailwindcss.com"></script>
    @endif
</head>
<body class="bg-slate-50 text-slate-900 antialiased dark:bg-slate-950 dark:text-slate-100">
    <main class="mx-auto max-w-4xl px-4 py-10 sm:px-6">
        <h1 class="text-3xl font-semibold tracking-tight">
            {{ $isPl ? 'Polityka cookies' : 'Cookie Policy' }}
        </h1>
        <p class="mt-3 text-sm text-slate-600 dark:text-slate-300">
            {{ $isPl
                ? 'Dokument opisuje, jakie pliki cookie i podobne technologie wykorzystuje TruMonit.'
                : 'This document explains what cookies and similar technologies are used by TruMonit.' }}
        </p>
        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
            {{ $isPl ? 'Ostatnia aktualizacja' : 'Last updated' }}: {{ $updatedAt }}
        </p>

        <section class="mt-8 space-y-5 text-sm leading-6 text-slate-700 dark:text-slate-200">
            <article>
                <h2 class="text-base font-semibold">{{ $isPl ? '1. Czym sa cookies' : '1. What cookies are' }}</h2>
                <p class="mt-1">
                    {{ $isPl
                        ? 'Cookies to male pliki tekstowe zapisywane w przegladarce, ktore pomagaja utrzymac sesje, zabezpieczyc konto i analizowac dzialanie serwisu.'
                        : 'Cookies are small text files stored in your browser to help keep sessions active, secure accounts, and analyze service performance.' }}
                </p>
            </article>

            <article>
                <h2 class="text-base font-semibold">{{ $isPl ? '2. Typy cookies' : '2. Cookie types' }}</h2>
                <ul class="mt-1 list-disc space-y-1 pl-5">
                    <li>{{ $isPl ? 'Niezbedne: logowanie, sesja, bezpieczenstwo.' : 'Essential: login, session, security.' }}</li>
                    <li>{{ $isPl ? 'Analityczne: pomiar wydajnosci i stabilnosci serwisu.' : 'Analytics: service performance and stability measurement.' }}</li>
                    <li>{{ $isPl ? 'Funkcjonalne: preferencje interfejsu, np. motyw i jezyk.' : 'Functional: UI preferences such as theme and language.' }}</li>
                </ul>
            </article>

            <article>
                <h2 class="text-base font-semibold">{{ $isPl ? '3. Cele wykorzystania' : '3. Purpose of use' }}</h2>
                <p class="mt-1">
                    {{ $isPl
                        ? 'Uzywamy cookies do utrzymania dzialania aplikacji, ochrony konta uzytkownika, monitorowania bledow oraz poprawy jakosci uslugi.'
                        : 'We use cookies to keep the application running, protect user accounts, monitor errors, and improve service quality.' }}
                </p>
            </article>

            <article>
                <h2 class="text-base font-semibold">{{ $isPl ? '4. Czas przechowywania' : '4. Retention period' }}</h2>
                <p class="mt-1">
                    {{ $isPl
                        ? 'Cookies sesyjne wygasaja po zamknieciu przegladarki. Cookies stale moga byc przechowywane do czasu ich usuniecia lub uplywu okresu waznosci ustawionego przez serwis.'
                        : 'Session cookies expire when the browser is closed. Persistent cookies may be stored until deleted or until their configured expiration period ends.' }}
                </p>
            </article>

            <article>
                <h2 class="text-base font-semibold">{{ $isPl ? '5. Zarzadzanie cookies' : '5. Managing cookies' }}</h2>
                <p class="mt-1">
                    {{ $isPl
                        ? 'Mozesz zarzadzac cookies w ustawieniach przegladarki (blokowanie, ograniczanie, usuwanie). Ograniczenie cookies moze wplynac na dostepnosc czesci funkcji serwisu.'
                        : 'You can manage cookies in browser settings (block, limit, delete). Restricting cookies may affect the availability of some service features.' }}
                </p>
            </article>

            <article>
                <h2 class="text-base font-semibold">{{ $isPl ? '6. Kontakt' : '6. Contact' }}</h2>
                <p class="mt-1">
                    {{ $isPl
                        ? 'W sprawach dotyczacych cookies i prywatnosci: support@trumonit.pro.'
                        : 'For questions about cookies and privacy: support@trumonit.pro.' }}
                </p>
            </article>
        </section>

        <div class="mt-10 flex flex-wrap gap-3">
            <a href="{{ $homeUrl }}" class="inline-flex rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium hover:bg-slate-100 dark:border-slate-700 dark:hover:bg-slate-800">
                {{ $isPl ? 'Powrot na strone glowna' : 'Back to homepage' }}
            </a>
            <a href="{{ $privacyUrl }}" class="inline-flex rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium hover:bg-slate-100 dark:border-slate-700 dark:hover:bg-slate-800">
                {{ $isPl ? 'Polityka prywatnosci' : 'Privacy Policy' }}
            </a>
            <a href="{{ $termsUrl }}" class="inline-flex rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium hover:bg-slate-100 dark:border-slate-700 dark:hover:bg-slate-800">
                {{ $isPl ? 'Warunki uslugi' : 'Terms of Service' }}
            </a>
        </div>
    </main>
</body>
</html>
