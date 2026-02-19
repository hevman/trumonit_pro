<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BillingWebhookController;
use App\Http\Controllers\Api\IncidentController;
use App\Http\Controllers\Api\MonitoredUrlController;
use App\Http\Controllers\Api\PrivateProbeAgentTaskController;
use App\Http\Controllers\Api\PrivateProbeAgentController;
use App\Http\Controllers\Api\PrivateProbeController;
use App\Http\Controllers\Api\PrivateProbeTaskController;
use App\Http\Controllers\Api\SiemController;
use App\Http\Controllers\Scim\ScimController;
use Illuminate\Support\Facades\Route;

Route::name('api.')->group(function () {
    Route::prefix('scim/v2')
        ->middleware(['scim.auth', 'throttle:api-v1-public'])
        ->name('scim.')
        ->group(function (): void {
            Route::get('/ServiceProviderConfig', [ScimController::class, 'serviceProviderConfig'])->name('service-provider-config');
            Route::get('/ResourceTypes', [ScimController::class, 'resourceTypes'])->name('resource-types');
            Route::get('/Schemas', [ScimController::class, 'schemas'])->name('schemas');
            Route::get('/Users', [ScimController::class, 'usersIndex'])->name('users.index');
            Route::get('/Users/{id}', [ScimController::class, 'usersShow'])
                ->where('id', '[A-Za-z0-9._:-]{1,191}')
                ->name('users.show');
            Route::post('/Users', [ScimController::class, 'usersStore'])->name('users.store');
            Route::put('/Users/{id}', [ScimController::class, 'usersPut'])
                ->where('id', '[A-Za-z0-9._:-]{1,191}')
                ->name('users.put');
            Route::patch('/Users/{id}', [ScimController::class, 'usersPatch'])
                ->where('id', '[A-Za-z0-9._:-]{1,191}')
                ->name('users.patch');
            Route::delete('/Users/{id}', [ScimController::class, 'usersDestroy'])
                ->where('id', '[A-Za-z0-9._:-]{1,191}')
                ->name('users.destroy');
            Route::get('/Groups', [ScimController::class, 'groupsIndex'])->name('groups.index');
            Route::get('/Groups/{id}', [ScimController::class, 'groupsShow'])
                ->where('id', '[A-Za-z0-9._:-]{1,191}')
                ->name('groups.show');
            Route::post('/Groups', [ScimController::class, 'groupsStore'])->name('groups.store');
            Route::put('/Groups/{id}', [ScimController::class, 'groupsPut'])
                ->where('id', '[A-Za-z0-9._:-]{1,191}')
                ->name('groups.put');
            Route::patch('/Groups/{id}', [ScimController::class, 'groupsPatch'])
                ->where('id', '[A-Za-z0-9._:-]{1,191}')
                ->name('groups.patch');
            Route::delete('/Groups/{id}', [ScimController::class, 'groupsDestroy'])
                ->where('id', '[A-Za-z0-9._:-]{1,191}')
                ->name('groups.destroy');
            Route::post('/Bulk', [ScimController::class, 'bulk'])->name('bulk');
        });

    $registerApiRoutes = static function (): void {
        Route::post('/auth/register', [AuthController::class, 'register'])->middleware('throttle:api-v1-public');
        Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:api-v1-public');
        if ((bool) config('wids.billing.payment.webhook_route_enabled', true)) {
            Route::post('/billing/webhooks/{provider}', BillingWebhookController::class)
                ->where('provider', '[A-Za-z0-9_-]{2,32}')
                ->middleware('throttle:api-v1-public');
        }

        Route::post('/private-probes/heartbeat', [PrivateProbeAgentController::class, 'heartbeat'])
            ->middleware(['probe.auth', 'throttle:api-v1-public']);
        Route::post('/private-probes/tasks/claim', [PrivateProbeAgentTaskController::class, 'claim'])
            ->middleware(['probe.auth', 'throttle:api-v1-public']);
        Route::post('/private-probes/tasks/{privateProbeScanTask}/result', [PrivateProbeAgentTaskController::class, 'result'])
            ->middleware(['probe.auth', 'throttle:api-v1-public']);

        Route::middleware(['auth:sanctum', 'throttle:api-v1', 'meter.api.usage'])->group(function () {
            Route::middleware('ability:wids.read,wids.write')->group(function () {
                Route::get('/me', [AuthController::class, 'me']);
            });

            Route::middleware('abilities:wids.read')->group(function () {
                Route::apiResource('monitored-urls', MonitoredUrlController::class)->only(['index', 'show'])->except(['create', 'edit']);
                Route::get('/incidents', [IncidentController::class, 'index']);
            });

            Route::middleware('abilities:wids.write')->group(function () {
                Route::post('/auth/logout', [AuthController::class, 'logout']);
                Route::post('/incidents/{id}/comments', [IncidentController::class, 'comment']);
            });

            Route::middleware(['role.owner', 'abilities:wids.write'])->group(function () {
                Route::apiResource('monitored-urls', MonitoredUrlController::class)->except(['index', 'show', 'create', 'edit']);
                Route::post('/monitored-urls/{id}/scan', [MonitoredUrlController::class, 'scan']);
                Route::post('/monitored-urls/{id}/baseline', [MonitoredUrlController::class, 'baseline']);

                Route::patch('/incidents/{id}', [IncidentController::class, 'update']);

                Route::get('/siem/incidents', [SiemController::class, 'incidents']);

                Route::get('/private-probes', [PrivateProbeController::class, 'index']);
                Route::post('/private-probes', [PrivateProbeController::class, 'store']);
                Route::patch('/private-probes/{privateProbe}', [PrivateProbeController::class, 'update']);
                Route::post('/private-probes/{privateProbe}/rotate-token', [PrivateProbeController::class, 'rotateToken']);
                Route::get('/private-probes/{privateProbe}/tasks', [PrivateProbeTaskController::class, 'index']);
                Route::post('/private-probes/{privateProbe}/tasks', [PrivateProbeTaskController::class, 'store']);
            });
        });
    };

    if ((bool) config('wids.api.legacy_enabled', true)) {
        $registerApiRoutes();
    }

    $apiVersion = trim((string) config('wids.api.current_version', 'v1'), '/');
    $apiVersion = $apiVersion !== '' ? $apiVersion : 'v1';

    Route::prefix($apiVersion)
        ->name($apiVersion.'.')
        ->group($registerApiRoutes);
});
