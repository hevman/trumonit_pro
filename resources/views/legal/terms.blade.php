<!DOCTYPE html>
@php
    $isPl = app()->getLocale() === 'pl';
    $title = $isPl ? 'Warunki usługi - TruMonit' : 'Terms of Service - TruMonit';
    $updatedAt = '2026-02-20';
    $homeUrl = route('localized.public.home', ['locale' => app()->getLocale()]);
    $privacyUrl = route('localized.legal.privacy', ['locale' => app()->getLocale()]);
@endphp
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }}</title>
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @else
        <script src="https://cdn.tailwindcss.com"></script>
    @endif
</head>
<body class="bg-slate-50 text-slate-900 antialiased dark:bg-slate-950 dark:text-slate-100">
    <main class="mx-auto max-w-4xl px-4 py-10 sm:px-6">
        <h1 class="text-3xl font-semibold tracking-tight">
            {{ $isPl ? 'Warunki usługi' : 'Terms of Service' }}
        </h1>
        <p class="mt-3 text-sm text-slate-600 dark:text-slate-300">
            {{ $isPl
                ? 'Warunki regulują zasady korzystania z usługi TruMonit przez użytkowników i organizacje.'
                : 'These terms govern the use of TruMonit by users and organizations.' }}
        </p>
        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
            {{ $isPl ? 'Ostatnia aktualizacja' : 'Last updated' }}: {{ $updatedAt }}
        </p>

        <section class="mt-8 space-y-5 text-sm leading-6 text-slate-700 dark:text-slate-200">
            <article>
                <h2 class="text-base font-semibold">{{ $isPl ? '1. Zakres usługi' : '1. Scope of service' }}</h2>
                <p class="mt-1">
                    {{ $isPl
                        ? 'TruMonit dostarcza funkcje monitoringu stron i API, alertingu, raportowania oraz narzędzi operacyjnych zgodnie z aktywnym planem.'
                        : 'TruMonit provides website/API monitoring, alerting, reporting, and operational tooling according to the active plan.' }}
                </p>
            </article>

            <article>
                <h2 class="text-base font-semibold">{{ $isPl ? '2. Konto i dostęp' : '2. Account and access' }}</h2>
                <ul class="mt-1 list-disc space-y-1 pl-5">
                    <li>{{ $isPl ? 'Użytkownik odpowiada za poufność danych logowania i tokenów API.' : 'Users are responsible for confidentiality of credentials and API tokens.' }}</li>
                    <li>{{ $isPl ? 'Użytkownik odpowiada za poprawną konfigurację monitorów i alertów.' : 'Users are responsible for correct monitor and alert configuration.' }}</li>
                    <li>{{ $isPl ? 'Dostęp może być ograniczony lub cofnięty w razie naruszeń lub zagrożenia bezpieczeństwa.' : 'Access may be limited or revoked in case of violations or security risk.' }}</li>
                </ul>
            </article>

            <article>
                <h2 class="text-base font-semibold">{{ $isPl ? '3. Dozwolone użycie' : '3. Acceptable use' }}</h2>
                <p class="mt-1">
                    {{ $isPl
                        ? 'Zabronione jest wykorzystywanie usługi do działań niezgodnych z prawem, zakłócania cudzych systemów, omijania zabezpieczeń lub testów bez uprawnień.'
                        : 'You must not use the service for unlawful activity, disruption of third-party systems, security bypass attempts, or unauthorized testing.' }}
                </p>
            </article>

            <article>
                <h2 class="text-base font-semibold">{{ $isPl ? '4. Plany, limity i rozliczenia' : '4. Plans, limits, and billing' }}</h2>
                <p class="mt-1">
                    {{ $isPl
                        ? 'Funkcje, limity usage i retencja danych zależą od planu. Zmiany planu i rozliczenia są realizowane przez panel billing oraz dostawców płatności.'
                        : 'Features, usage limits, and data retention depend on your plan. Plan changes and billing are managed through the billing panel and payment providers.' }}
                </p>
            </article>

            <article>
                <h2 class="text-base font-semibold">{{ $isPl ? '5. Dostępność i zmiany usługi' : '5. Availability and service changes' }}</h2>
                <p class="mt-1">
                    {{ $isPl
                        ? 'Dokładamy starań, aby usługa była dostępna i stabilna, jednak okresowe prace utrzymaniowe, awarie infrastruktury lub czynniki zewnętrzne mogą wpłynąć na dostępność.'
                        : 'We aim to keep the service available and stable, but maintenance windows, infrastructure failures, or external factors may affect availability.' }}
                </p>
            </article>

            <article>
                <h2 class="text-base font-semibold">{{ $isPl ? '6. Odpowiedzialność' : '6. Liability' }}</h2>
                <p class="mt-1">
                    {{ $isPl
                        ? 'W maksymalnym zakresie dopuszczalnym przez prawo usługa jest dostarczana "as is". Odpowiedzialność operatora jest ograniczona do przewidywalnych szkód bezpośrednich wynikających z naruszenia umowy.'
                        : 'To the maximum extent permitted by law, the service is provided on an "as is" basis. Liability is limited to foreseeable direct damages arising from breach of contract.' }}
                </p>
            </article>

            <article>
                <h2 class="text-base font-semibold">{{ $isPl ? '7. Zawieszenie i zakończenie' : '7. Suspension and termination' }}</h2>
                <p class="mt-1">
                    {{ $isPl
                        ? 'Możemy czasowo zawiesić lub zakończyć świadczenie usługi dla konta naruszającego warunki, przepisy prawa lub bezpieczeństwo platformy.'
                        : 'We may temporarily suspend or terminate service for accounts violating these terms, applicable law, or platform security requirements.' }}
                </p>
            </article>

            <article>
                <h2 class="text-base font-semibold">{{ $isPl ? '8. Zmiany warunków' : '8. Changes to these terms' }}</h2>
                <p class="mt-1">
                    {{ $isPl
                        ? 'Warunki mogą być aktualizowane. Aktualna wersja jest publikowana na tej stronie z datą aktualizacji.'
                        : 'These terms may be updated. The current version is published on this page with an update date.' }}
                </p>
            </article>

            <article>
                <h2 class="text-base font-semibold">{{ $isPl ? '9. Kontakt' : '9. Contact' }}</h2>
                <p class="mt-1">
                    {{ $isPl
                        ? 'W sprawach umownych i prawnych: support@trumonit.pro.'
                        : 'For contractual and legal matters: support@trumonit.pro.' }}
                </p>
            </article>
        </section>

        <div class="mt-10 flex flex-wrap gap-3">
            <a href="{{ $homeUrl }}" class="inline-flex rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium hover:bg-slate-100 dark:border-slate-700 dark:hover:bg-slate-800">
                {{ $isPl ? 'Powrót na stronę główną' : 'Back to homepage' }}
            </a>
            <a href="{{ $privacyUrl }}" class="inline-flex rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium hover:bg-slate-100 dark:border-slate-700 dark:hover:bg-slate-800">
                {{ $isPl ? 'Polityka prywatności' : 'Privacy Policy' }}
            </a>
        </div>
    </main>
</body>
</html>
