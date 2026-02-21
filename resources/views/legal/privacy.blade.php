<!DOCTYPE html>
@php
    $isPl = app()->getLocale() === 'pl';
    $title = $isPl ? 'Polityka prywatności - TruMonit' : 'Privacy Policy - TruMonit';
    $updatedAt = '2026-02-20';
    $homeUrl = route('localized.public.home', ['locale' => app()->getLocale()]);
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
            {{ $isPl ? 'Polityka prywatności' : 'Privacy Policy' }}
        </h1>
        <p class="mt-3 text-sm text-slate-600 dark:text-slate-300">
            {{ $isPl
                ? 'Dokument opisuje, jakie dane przetwarzamy w TruMonit, w jakim celu oraz jakie prawa przysługują użytkownikom.'
                : 'This document explains what data we process in TruMonit, for what purpose, and what rights users have.' }}
        </p>
        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
            {{ $isPl ? 'Ostatnia aktualizacja' : 'Last updated' }}: {{ $updatedAt }}
        </p>

        <section class="mt-8 space-y-5 text-sm leading-6 text-slate-700 dark:text-slate-200">
            <article>
                <h2 class="text-base font-semibold">{{ $isPl ? '1. Administrator danych' : '1. Data controller' }}</h2>
                <p class="mt-1">
                    {{ $isPl
                        ? 'Administratorem danych w związku z usługą TruMonit jest operator serwisu TruMonit. W sprawach prywatności można skontaktować się przez support@trumonit.pro.'
                        : 'The data controller for TruMonit services is the TruMonit service operator. For privacy matters, contact support@trumonit.pro.' }}
                </p>
            </article>

            <article>
                <h2 class="text-base font-semibold">{{ $isPl ? '2. Kategorie przetwarzanych danych' : '2. Categories of data processed' }}</h2>
                <ul class="mt-1 list-disc space-y-1 pl-5">
                    <li>{{ $isPl ? 'dane konta (np. email, nazwa użytkownika, role);' : 'account data (e.g., email, user name, roles);' }}</li>
                    <li>{{ $isPl ? 'konfiguracja monitoringu (URL, ustawienia monitorów, alerty, integracje);' : 'monitoring configuration (URLs, monitor settings, alerts, integrations);' }}</li>
                    <li>{{ $isPl ? 'dane operacyjne i telemetryczne (snapshoty, logi, incydenty, metryki);' : 'operational and telemetry data (snapshots, logs, incidents, metrics);' }}</li>
                    <li>{{ $isPl ? 'dane rozliczeniowe i statusy planu (w zakresie niezbędnym do obsługi billing).' : 'billing and plan status data (as required for billing operations).' }}</li>
                </ul>
            </article>

            <article>
                <h2 class="text-base font-semibold">{{ $isPl ? '3. Cele i podstawy przetwarzania' : '3. Purposes and legal bases' }}</h2>
                <ul class="mt-1 list-disc space-y-1 pl-5">
                    <li>{{ $isPl ? 'realizacja umowy o świadczenie usługi monitoringu;' : 'performance of the monitoring service agreement;' }}</li>
                    <li>{{ $isPl ? 'zapewnienie bezpieczeństwa, integralności i ciągłości usługi;' : 'security, integrity, and continuity of the service;' }}</li>
                    <li>{{ $isPl ? 'obsługa rozliczeń, nadużyć, reklamacji i obrony roszczeń;' : 'billing, abuse prevention, complaint handling, and legal claims defense;' }}</li>
                    <li>{{ $isPl ? 'wypełnienie obowiązków wynikających z przepisów prawa.' : 'compliance with legal obligations.' }}</li>
                </ul>
            </article>

            <article>
                <h2 class="text-base font-semibold">{{ $isPl ? '4. Udostępnianie danych i podwykonawcy' : '4. Data sharing and subprocessors' }}</h2>
                <p class="mt-1">
                    {{ $isPl
                        ? 'Dane mogą być powierzane podmiotom wspierającym świadczenie usługi (np. hosting, infrastruktura chmurowa, dostawcy płatności, email). Dane są przekazywane tylko w zakresie niezbędnym do realizacji celu.'
                        : 'Data may be shared with service providers supporting delivery of the service (e.g., hosting, cloud infrastructure, payment, email providers). Data is shared only to the extent necessary for the stated purpose.' }}
                </p>
            </article>

            <article>
                <h2 class="text-base font-semibold">{{ $isPl ? '5. Transfery międzynarodowe' : '5. International transfers' }}</h2>
                <p class="mt-1">
                    {{ $isPl
                        ? 'Jeżeli dane są przekazywane poza EOG, stosujemy odpowiednie zabezpieczenia wymagane przez obowiązujące przepisy (np. standardowe klauzule umowne).'
                        : 'If data is transferred outside the EEA, we use safeguards required by applicable law (e.g., standard contractual clauses).' }}
                </p>
            </article>

            <article>
                <h2 class="text-base font-semibold">{{ $isPl ? '6. Okres przechowywania' : '6. Data retention' }}</h2>
                <p class="mt-1">
                    {{ $isPl
                        ? 'Dane przechowujemy przez czas niezbędny do realizacji celu, okres wymagany przepisami albo okres wynikający z konfiguracji planu (np. retencja snapshotów i logów).'
                        : 'We retain data for as long as necessary for service delivery, legal compliance, or plan-configured retention periods (e.g., snapshots and logs).' }}
                </p>
            </article>

            <article>
                <h2 class="text-base font-semibold">{{ $isPl ? '7. Prawa użytkownika' : '7. User rights' }}</h2>
                <p class="mt-1">
                    {{ $isPl
                        ? 'Użytkownik może żądać dostępu do danych, ich sprostowania, usunięcia, ograniczenia przetwarzania lub przeniesienia - zgodnie z obowiązującymi przepisami. Wnioski przyjmujemy pod adresem support@trumonit.pro.'
                        : 'Users may request access, rectification, deletion, restriction, or portability of data, as permitted by applicable law. Requests can be sent to support@trumonit.pro.' }}
                </p>
            </article>

            <article>
                <h2 class="text-base font-semibold">{{ $isPl ? '8. Bezpieczeństwo' : '8. Security' }}</h2>
                <p class="mt-1">
                    {{ $isPl
                        ? 'Stosujemy środki techniczne i organizacyjne adekwatne do ryzyka, w tym kontrolę dostępu, segmentację uprawnień i monitoring zdarzeń bezpieczeństwa.'
                        : 'We apply technical and organizational safeguards appropriate to risk, including access controls, permission segmentation, and security event monitoring.' }}
                </p>
            </article>

            <article>
                <h2 class="text-base font-semibold">{{ $isPl ? '9. Zmiany dokumentu' : '9. Changes to this policy' }}</h2>
                <p class="mt-1">
                    {{ $isPl
                        ? 'Polityka może być aktualizowana. Aktualna wersja jest zawsze publikowana na tej stronie wraz z datą aktualizacji.'
                        : 'This policy may be updated. The current version is always published on this page with the update date.' }}
                </p>
            </article>
        </section>

        <div class="mt-10 flex flex-wrap gap-3">
            <a href="{{ $homeUrl }}" class="inline-flex rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium hover:bg-slate-100 dark:border-slate-700 dark:hover:bg-slate-800">
                {{ $isPl ? 'Powrót na stronę główną' : 'Back to homepage' }}
            </a>
            <a href="{{ $termsUrl }}" class="inline-flex rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium hover:bg-slate-100 dark:border-slate-700 dark:hover:bg-slate-800">
                {{ $isPl ? 'Warunki usługi' : 'Terms of Service' }}
            </a>
        </div>
    </main>
</body>
</html>
