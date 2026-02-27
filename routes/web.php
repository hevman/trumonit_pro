<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Web\DashboardController;
use App\Http\Controllers\Web\IncidentController;
use App\Http\Controllers\Web\MonitoredUrlController;
use App\Http\Controllers\Web\MonitoredUrlBulkController;
use App\Http\Controllers\Web\MaintenanceWindowController;
use App\Http\Controllers\Web\BillingController;
use App\Http\Controllers\Web\NotificationChannelController;
use App\Http\Controllers\Web\OnboardingController;
use App\Http\Controllers\Web\OpsCenterController;
use App\Http\Controllers\Web\SamlSettingsController;
use App\Http\Controllers\Web\SsoSettingsController;
use App\Http\Controllers\Web\StatusPageController;
use App\Http\Controllers\Web\SavedViewController;
use App\Http\Controllers\Web\SeoRiskController;
use App\Http\Controllers\Web\SnapshotController;
use App\Http\Controllers\Ops\BusinessAccountsController;
use App\Http\Controllers\Ops\BusinessSummaryController;
use App\Http\Controllers\Ops\MetricsController;
use App\Http\Controllers\Public\FaqController;
use App\Http\Controllers\Public\SolutionsController;
use App\Http\Controllers\Public\StatusPagePublicController;
use App\Models\StatusPage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Route;

$resolveMarketingLocale = static function (Request $request): string {
    $supported = ['en', 'pl'];
    $cookieName = 'trumonit_locale';
    $queryLang = (string) $request->query('lang', '');

    if ($queryLang !== '' && in_array($queryLang, $supported, true)) {
        if ($request->hasSession()) {
            $request->session()->put('locale', $queryLang);
        }
        Cookie::queue(cookie()->forever($cookieName, $queryLang));

        return $queryLang;
    }

    $sessionLocale = (string) ($request->hasSession() ? $request->session()->get('locale', '') : '');
    if ($sessionLocale !== '' && in_array($sessionLocale, $supported, true)) {
        return $sessionLocale;
    }

    $cookieLocale = (string) $request->cookie($cookieName, '');
    if ($cookieLocale !== '' && in_array($cookieLocale, $supported, true)) {
        if ($request->hasSession()) {
            $request->session()->put('locale', $cookieLocale);
        }

        return $cookieLocale;
    }

    $preferred = (string) $request->getPreferredLanguage($supported);
    if ($preferred !== '' && in_array($preferred, $supported, true)) {
        return $preferred;
    }

    $fallback = (string) config('app.locale', 'en');

    return in_array($fallback, $supported, true) ? $fallback : 'en';
};

Route::get('/', function (Request $request) use ($resolveMarketingLocale) {
    $statusPageResponse = app(StatusPagePublicController::class)->showForRequestHost($request);

    if ($statusPageResponse !== null) {
        return $statusPageResponse;
    }

    if (auth()->check()) {
        return redirect()->route('dashboard');
    }

    return redirect()->route('localized.public.home', ['locale' => $resolveMarketingLocale($request)], 301);
});

Route::get('/lang/{locale}', function (string $locale, Request $request): RedirectResponse {
    abort_unless(in_array($locale, ['en', 'pl'], true), 404);
    if ($request->hasSession()) {
        $request->session()->put('locale', $locale);
    }
    Cookie::queue(cookie()->forever('trumonit_locale', $locale));

    $target = (string) $request->query('redirect', '/');
    $fallbackHost = (string) parse_url(url('/'), PHP_URL_HOST);
    $targetHost = (string) parse_url($target, PHP_URL_HOST);
    $targetPath = (string) parse_url($target, PHP_URL_PATH);

    $isRelative = $targetHost === '';
    $isSameHost = $targetHost !== '' && strcasecmp($targetHost, $fallbackHost) === 0;
    if (! $isRelative && ! $isSameHost) {
        $targetPath = '/';
    }

    $normalizedPath = trim($targetPath, '/');

    if (preg_match('/^faq\/(.+)$/', $normalizedPath, $matches) === 1) {
        return redirect()->route('localized.public.faq.show', [
            'locale' => $locale,
            'slug' => $matches[1],
        ], 301);
    }

    if ($normalizedPath === 'faq') {
        return redirect()->route('localized.public.faq.index', ['locale' => $locale], 301);
    }

    if (preg_match('/^solutions\/(.+)$/', $normalizedPath, $matches) === 1) {
        return redirect()->route('localized.public.solutions.show', [
            'locale' => $locale,
            'slug' => $matches[1],
        ], 301);
    }

    if ($normalizedPath === 'solutions') {
        return redirect()->route('localized.public.solutions.index', ['locale' => $locale], 301);
    }

    if ($normalizedPath === 'privacy') {
        return redirect()->route('localized.legal.privacy', ['locale' => $locale], 301);
    }

    if ($normalizedPath === 'cookies') {
        return redirect()->route('localized.legal.cookies', ['locale' => $locale], 301);
    }

    if ($normalizedPath === 'terms') {
        return redirect()->route('localized.legal.terms', ['locale' => $locale], 301);
    }

    if ($normalizedPath === 'pricing') {
        return redirect()->route('localized.public.pricing', ['locale' => $locale], 301);
    }

    return redirect()->route('localized.public.home', ['locale' => $locale], 301);
})->name('lang.switch');

Route::get('/pricing', function (Request $request) use ($resolveMarketingLocale) {
    return redirect()->route('localized.public.pricing', ['locale' => $resolveMarketingLocale($request)], 301);
})->name('public.pricing');

Route::get('/privacy', function (Request $request) use ($resolveMarketingLocale) {
    return redirect()->route('localized.legal.privacy', ['locale' => $resolveMarketingLocale($request)], 301);
})->name('legal.privacy');

Route::get('/cookies', function (Request $request) use ($resolveMarketingLocale) {
    return redirect()->route('localized.legal.cookies', ['locale' => $resolveMarketingLocale($request)], 301);
})->name('legal.cookies');

Route::get('/terms', function (Request $request) use ($resolveMarketingLocale) {
    return redirect()->route('localized.legal.terms', ['locale' => $resolveMarketingLocale($request)], 301);
})->name('legal.terms');

Route::get('/faq', function (Request $request) use ($resolveMarketingLocale) {
    return redirect()->route('localized.public.faq.index', ['locale' => $resolveMarketingLocale($request)], 301);
})->name('public.faq.index');

Route::get('/faq/{slug}', function (string $slug, Request $request) use ($resolveMarketingLocale) {
    return redirect()->route('localized.public.faq.show', [
        'locale' => $resolveMarketingLocale($request),
        'slug' => $slug,
    ], 301);
})->name('public.faq.show');

Route::get('/solutions', function (Request $request) use ($resolveMarketingLocale) {
    return redirect()->route('localized.public.solutions.index', ['locale' => $resolveMarketingLocale($request)], 301);
})->name('public.solutions.index');

Route::get('/solutions/{slug}', function (string $slug, Request $request) use ($resolveMarketingLocale) {
    return redirect()->route('localized.public.solutions.show', [
        'locale' => $resolveMarketingLocale($request),
        'slug' => $slug,
    ], 301);
})->name('public.solutions.show');

Route::prefix('{locale}')
    ->where(['locale' => 'en|pl'])
    ->as('localized.')
    ->group(function () {
        Route::get('/', function () {
            return view('welcome');
        })->name('public.home');

        Route::get('/pricing', function () {
            return view('public.pricing');
        })->name('public.pricing');

        Route::get('/privacy', function () {
            return response()->view('legal.privacy', [], 200, [
                'Content-Type' => 'text/html; charset=UTF-8',
            ]);
        })->name('legal.privacy');

        Route::get('/cookies', function () {
            return response()->view('legal.cookies', [], 200, [
                'Content-Type' => 'text/html; charset=UTF-8',
            ]);
        })->name('legal.cookies');

        Route::get('/terms', function () {
            return response()->view('legal.terms', [], 200, [
                'Content-Type' => 'text/html; charset=UTF-8',
            ]);
        })->name('legal.terms');

        Route::get('/faq', [FaqController::class, 'index'])->name('public.faq.index');
        Route::get('/faq/{slug}', [FaqController::class, 'show'])->name('public.faq.show');

        Route::get('/solutions', [SolutionsController::class, 'index'])->name('public.solutions.index');
        Route::get('/solutions/{slug}', [SolutionsController::class, 'show'])->name('public.solutions.show');
    });

Route::get('/sitemap.xml', function () {
    $urls = [];
    $locales = ['en', 'pl'];
    $collectSlugs = static function (string $dir): array {
        $files = glob($dir . DIRECTORY_SEPARATOR . '*.php') ?: [];
        $slugs = [];

        foreach ($files as $file) {
            $slug = pathinfo($file, PATHINFO_FILENAME);
            if (! is_string($slug) || trim($slug) === '') {
                continue;
            }

            $slugs[] = $slug;
        }

        $slugs = array_values(array_unique($slugs));
        sort($slugs);

        return $slugs;
    };

    // Read slugs from filenames only. Do not require content files here, because any BOM
    // in content sources may emit bytes before XML declaration and break sitemap parsing.
    $faqSlugs = $collectSlugs(resource_path('content/faq/en'));
    $solutionSlugs = $collectSlugs(resource_path('content/solutions/en'));

    $atom = static function (?int $mtime): string {
        if (! is_int($mtime) || $mtime <= 0) {
            return now()->toAtomString();
        }

        // Sitemap timestamps should be stable and timezone-agnostic.
        return gmdate('c', $mtime);
    };

    $maxMtime = static function (array $paths): ?int {
        $max = null;

        foreach ($paths as $path) {
            if (! is_string($path) || $path === '' || ! is_file($path)) {
                continue;
            }

            $mtime = @filemtime($path);
            if (! is_int($mtime)) {
                continue;
            }

            $max = $max === null ? $mtime : max($max, $mtime);
        }

        return $max;
    };

    foreach ($locales as $locale) {
        $urls[] = [
            'loc' => route('localized.public.home', ['locale' => $locale]),
            'lastmod' => $atom($maxMtime([
                resource_path('views/welcome.blade.php'),
            ])),
            'changefreq' => 'daily',
            'priority' => '1.0',
        ];
        $urls[] = [
            'loc' => route('localized.public.pricing', ['locale' => $locale]),
            'lastmod' => $atom($maxMtime([
                resource_path('views/public/pricing.blade.php'),
            ])),
            'changefreq' => 'weekly',
            'priority' => '0.9',
        ];
        $urls[] = [
            'loc' => route('localized.legal.cookies', ['locale' => $locale]),
            'lastmod' => $atom($maxMtime([
                resource_path('views/legal/cookies.blade.php'),
            ])),
            'changefreq' => 'monthly',
            'priority' => '0.4',
        ];
        $urls[] = [
            'loc' => route('localized.legal.privacy', ['locale' => $locale]),
            'lastmod' => $atom($maxMtime([
                resource_path('views/legal/privacy.blade.php'),
            ])),
            'changefreq' => 'monthly',
            'priority' => '0.4',
        ];
        $urls[] = [
            'loc' => route('localized.legal.terms', ['locale' => $locale]),
            'lastmod' => $atom($maxMtime([
                resource_path('views/legal/terms.blade.php'),
            ])),
            'changefreq' => 'monthly',
            'priority' => '0.4',
        ];

        $urls[] = [
            'loc' => route('localized.public.solutions.index', ['locale' => $locale]),
            'lastmod' => $atom($maxMtime(array_merge(
                [resource_path('views/public/solutions/index.blade.php')],
                array_map(static fn (string $slug): string => resource_path("content/solutions/{$locale}/{$slug}.php"), $solutionSlugs)
            ))),
            'changefreq' => 'weekly',
            'priority' => '0.8',
        ];

        $urls[] = [
            'loc' => route('localized.public.faq.index', ['locale' => $locale]),
            'lastmod' => $atom($maxMtime(array_merge(
                [resource_path('views/public/faq/index.blade.php')],
                array_map(static fn (string $slug): string => resource_path("content/faq/{$locale}/{$slug}.php"), $faqSlugs)
            ))),
            'changefreq' => 'weekly',
            'priority' => '0.7',
        ];

        foreach ($solutionSlugs as $slug) {
            $urls[] = [
                'loc' => route('localized.public.solutions.show', ['locale' => $locale, 'slug' => $slug]),
                'lastmod' => $atom($maxMtime([
                    resource_path('views/public/solutions/show.blade.php'),
                    resource_path("content/solutions/{$locale}/{$slug}.php"),
                ])),
                'changefreq' => 'weekly',
                'priority' => '0.7',
            ];
        }

        foreach ($faqSlugs as $slug) {
            $urls[] = [
                'loc' => route('localized.public.faq.show', ['locale' => $locale, 'slug' => $slug]),
                'lastmod' => $atom($maxMtime([
                    resource_path('views/public/faq/show.blade.php'),
                    resource_path("content/faq/{$locale}/{$slug}.php"),
                ])),
                'changefreq' => 'weekly',
                'priority' => '0.6',
            ];
        }
    }

    try {
        $publicStatusPages = StatusPage::query()
            ->where('is_public', true)
            ->select(['slug', 'updated_at'])
            ->latest('updated_at')
            ->limit(1000)
            ->get();

        foreach ($publicStatusPages as $statusPage) {
            if (! is_string($statusPage->slug) || $statusPage->slug === '') {
                continue;
            }

            $urls[] = [
                'loc' => route('status-pages.public.show', ['slug' => $statusPage->slug]),
                'lastmod' => optional($statusPage->updated_at)?->toAtomString() ?? now()->toAtomString(),
                'changefreq' => 'hourly',
                'priority' => '0.8',
            ];
        }
    } catch (\Throwable) {
        // Keep sitemap available even during transient DB issues.
    }

    $normalizeSitemapUrl = static function (string $loc): string {
        if (str_starts_with($loc, 'http://')) {
            return 'https://' . substr($loc, 7);
        }

        return $loc;
    };

    $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

    foreach ($urls as $url) {
        $loc = htmlspecialchars($normalizeSitemapUrl((string) ($url['loc'] ?? '')), ENT_XML1 | ENT_COMPAT, 'UTF-8');
        $lastmod = htmlspecialchars((string) ($url['lastmod'] ?? now()->toAtomString()), ENT_XML1 | ENT_COMPAT, 'UTF-8');
        $changefreq = htmlspecialchars((string) ($url['changefreq'] ?? 'weekly'), ENT_XML1 | ENT_COMPAT, 'UTF-8');
        $priority = htmlspecialchars((string) ($url['priority'] ?? '0.5'), ENT_XML1 | ENT_COMPAT, 'UTF-8');

        $xml .= "    <url>\n";
        $xml .= "        <loc>{$loc}</loc>\n";
        $xml .= "        <lastmod>{$lastmod}</lastmod>\n";
        $xml .= "        <changefreq>{$changefreq}</changefreq>\n";
        $xml .= "        <priority>{$priority}</priority>\n";
        $xml .= "    </url>\n";
    }

    $xml .= '</urlset>';

    return response($xml, 200)->header('Content-Type', 'application/xml; charset=UTF-8');
})->name('public.sitemap');

Route::get('/ops/metrics', MetricsController::class)
    ->middleware('throttle:ops-endpoints')
    ->name('ops.metrics');
Route::get('/ops/business-summary', BusinessSummaryController::class)
    ->middleware('throttle:ops-endpoints')
    ->name('ops.business-summary');
Route::get('/ops/business-accounts', BusinessAccountsController::class)
    ->middleware('throttle:ops-endpoints')
    ->name('ops.business-accounts');

Route::get('/status/{slug}', [StatusPagePublicController::class, 'show'])
    ->where('slug', '[A-Za-z0-9\\-]{1,80}')
    ->name('status-pages.public.show');

Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware(['auth', 'session.governance'])
    ->name('dashboard');

Route::middleware(['auth', 'session.governance'])->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::resource('monitored-urls', MonitoredUrlController::class)
        ->only(['index', 'show'])
        ->whereNumber('monitored_url');

    Route::get('/incidents', [IncidentController::class, 'index'])->name('incidents.index');
    Route::get('/incidents/{incident}', [IncidentController::class, 'show'])->name('incidents.show');
    Route::post('/incidents/{incident}/comments', [IncidentController::class, 'storeComment'])->name('incidents.comments.store');

    Route::resource('status-pages', StatusPageController::class)
        ->only(['index', 'show'])
        ->whereNumber('status_page');

    Route::get('/seo-risk', [SeoRiskController::class, 'index'])->name('seo-risk.index');

    Route::get('/maintenance-windows', [MaintenanceWindowController::class, 'index'])->name('maintenance-windows.index');

    Route::get('/snapshots/{snapshot}', [SnapshotController::class, 'show'])->name('snapshots.show');
    Route::get('/snapshots/{snapshot}/body', [SnapshotController::class, 'body'])->name('snapshots.body');
    Route::get('/snapshots/{snapshot}/screenshot', [SnapshotController::class, 'screenshot'])->name('snapshots.screenshot');

    Route::middleware(['role.owner'])->group(function () {
        Route::get('/onboarding', [OnboardingController::class, 'show'])->name('onboarding.show');

        Route::post('/maintenance-windows', [MaintenanceWindowController::class, 'store'])->name('maintenance-windows.store');
        Route::delete('/maintenance-windows/{maintenance_window}', [MaintenanceWindowController::class, 'destroy'])
            ->whereNumber('maintenance_window')
            ->name('maintenance-windows.destroy');

        Route::resource('monitored-urls', MonitoredUrlController::class)
            ->except(['index', 'show'])
            ->whereNumber('monitored_url');
        Route::post('/monitored-urls/{monitored_url}/scan', [MonitoredUrlController::class, 'scan'])
            ->whereNumber('monitored_url')
            ->name('monitored-urls.scan');
        Route::post('/monitored-urls/{monitored_url}/baseline', [MonitoredUrlController::class, 'baseline'])
            ->whereNumber('monitored_url')
            ->name('monitored-urls.baseline');
        Route::post('/monitored-urls/{monitored_url}/mute', [MonitoredUrlController::class, 'mute'])
            ->whereNumber('monitored_url')
            ->name('monitored-urls.mute');
        Route::post('/monitored-urls/{monitored_url}/unmute', [MonitoredUrlController::class, 'unmute'])
            ->whereNumber('monitored_url')
            ->name('monitored-urls.unmute');

        Route::get('/monitored-urls/bulk', [MonitoredUrlBulkController::class, 'index'])->name('monitored-urls.bulk');
        Route::get('/monitored-urls/bulk/export', [MonitoredUrlBulkController::class, 'export'])->name('monitored-urls.bulk.export');
        Route::post('/monitored-urls/bulk/import', [MonitoredUrlBulkController::class, 'import'])->name('monitored-urls.bulk.import');
        Route::post('/monitored-urls/bulk/actions', [MonitoredUrlBulkController::class, 'actions'])->name('monitored-urls.bulk.actions');

        Route::resource('status-pages', StatusPageController::class)
            ->except(['index', 'show'])
            ->whereNumber('status_page');

        Route::post('/saved-views', [SavedViewController::class, 'store'])->name('saved-views.store');
        Route::get('/saved-views', [SavedViewController::class, 'index'])->name('saved-views.index');
        Route::patch('/saved-views/{saved_view}', [SavedViewController::class, 'update'])
            ->whereNumber('saved_view')
            ->name('saved-views.update');
        Route::delete('/saved-views/{saved_view}', [SavedViewController::class, 'destroy'])
            ->whereNumber('saved_view')
            ->name('saved-views.destroy');

        Route::get('/notification-channels', [NotificationChannelController::class, 'edit'])->name('notification-channels.edit');
        Route::put('/notification-channels', [NotificationChannelController::class, 'update'])->name('notification-channels.update');
        Route::get('/sso', [SsoSettingsController::class, 'edit'])->name('sso.edit');
        Route::put('/sso', [SsoSettingsController::class, 'update'])->name('sso.update');
        Route::get('/sso/saml', [SamlSettingsController::class, 'edit'])->name('sso.saml.edit');
        Route::put('/sso/saml', [SamlSettingsController::class, 'update'])->name('sso.saml.update');
        Route::get('/billing', [BillingController::class, 'index'])->name('billing.index');
        Route::get('/billing/weekly-digest-reports/{weeklyDigestReport}/{format}', [BillingController::class, 'downloadWeeklyDigestReport'])
            ->whereNumber('weeklyDigestReport')
            ->where('format', 'json|csv')
            ->name('billing.weekly-digest-reports.download');
        Route::post('/billing/plan-change', [BillingController::class, 'changePlan'])->name('billing.plan.change');
        Route::post('/seo-risk/integrations/{provider}/connect', [SeoRiskController::class, 'connectIntegration'])->name('seo-risk.integrations.connect');
        Route::post('/seo-risk/integrations/{provider}/disconnect', [SeoRiskController::class, 'disconnectIntegration'])->name('seo-risk.integrations.disconnect');
        Route::get('/seo-risk/google/callback', [SeoRiskController::class, 'googleCallback'])->name('seo-risk.google.callback');
        Route::get('/seo-risk/google/options', [SeoRiskController::class, 'googleOptions'])->name('seo-risk.google.options');
        Route::post('/seo-risk/sync', [SeoRiskController::class, 'syncNow'])->name('seo-risk.sync');
        Route::post('/seo-risk/properties', [SeoRiskController::class, 'storeProperty'])->name('seo-risk.properties.store');
        Route::patch('/seo-risk/properties/{seo_property}', [SeoRiskController::class, 'updateProperty'])
            ->whereNumber('seo_property')
            ->name('seo-risk.properties.update');
        Route::delete('/seo-risk/properties/{seo_property}', [SeoRiskController::class, 'destroyProperty'])
            ->whereNumber('seo_property')
            ->name('seo-risk.properties.destroy');
        Route::get('/ops', [OpsCenterController::class, 'index'])->name('ops-center.index');
        Route::post('/ops/drill/run', [OpsCenterController::class, 'runDrill'])->name('ops-center.drill.run');
        Route::post('/ops/tune/apply', [OpsCenterController::class, 'applyTune'])->name('ops-center.tune.apply');
        Route::post('/ops/tune/rollback', [OpsCenterController::class, 'rollbackTune'])->name('ops-center.tune.rollback');
        Route::get('/ops/drills/{filename}', [OpsCenterController::class, 'showArtifact'])
            ->where('filename', 'drill-[A-Za-z0-9_\\-]+\\.json')
            ->name('ops-center.drills.show');

        Route::patch('/incidents/{incident}', [IncidentController::class, 'update'])->name('incidents.update');
    });
});

require __DIR__.'/auth.php';
