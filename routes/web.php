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
use App\Http\Controllers\Web\SnapshotController;
use App\Http\Controllers\Ops\BusinessSummaryController;
use App\Http\Controllers\Ops\MetricsController;
use App\Http\Controllers\Public\StatusPagePublicController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', function (Request $request) {
    $statusPageResponse = app(StatusPagePublicController::class)->showForRequestHost($request);

    if ($statusPageResponse !== null) {
        return $statusPageResponse;
    }

    if (auth()->check()) {
        return redirect()->route('dashboard');
    }

    return view('welcome');
});

Route::get('/lang/{locale}', function (string $locale, Request $request) {
    abort_unless(in_array($locale, ['en', 'pl'], true), 404);

    $request->session()->put('locale', $locale);

    return redirect()->back();
})->name('lang.switch');

Route::get('/ops/metrics', MetricsController::class)->name('ops.metrics');
Route::get('/ops/business-summary', BusinessSummaryController::class)->name('ops.business-summary');

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
