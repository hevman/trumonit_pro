<?php

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withCommands()
    ->withSchedule(function (Schedule $schedule): void {
        $schedule->command('wids:scan')->everyMinute()->withoutOverlapping();

        if ((bool) config('wids.private_probes.enabled', false)) {
            if ((bool) config('wids.private_probes.fleet_policy.auto_enforce_enabled', false)) {
                $privateProbePolicyTime = trim((string) config('wids.private_probes.fleet_policy.auto_enforce_schedule_time', '02:55'));
                $privateProbePolicyTime = preg_match('/^\d{2}:\d{2}$/', $privateProbePolicyTime) === 1 ? $privateProbePolicyTime : '02:55';
                $schedule->command('wids:private-probes-enforce')->dailyAt($privateProbePolicyTime)->withoutOverlapping();
            }

            $schedule->command('wids:private-probes-dispatch')->everyMinute()->withoutOverlapping();
        }

        if ((bool) config('wids.queue.autoscaling.auto_apply_enabled', false)) {
            $schedule->command('wids:queue-autoscale-apply --save')->everyMinute()->withoutOverlapping();
        }

        if ((bool) config('wids.storage_lifecycle.enabled', false)) {
            $schedule->command('wids:archive-snapshots')->dailyAt('01:45')->withoutOverlapping();

            if ((bool) config('wids.storage_lifecycle.orphan_cleanup_enabled', false)) {
                $orphanCleanupTime = trim((string) config('wids.storage_lifecycle.orphan_cleanup_schedule_time', '03:10'));
                $orphanCleanupTime = preg_match('/^\d{2}:\d{2}$/', $orphanCleanupTime) === 1 ? $orphanCleanupTime : '03:10';

                $schedule->command('wids:storage-orphan-cleanup')->dailyAt($orphanCleanupTime)->withoutOverlapping();
            }
        }

        if ((bool) config('wids.snapshot_partition.enabled', false)) {
            $partitionTime = trim((string) config('wids.snapshot_partition.schedule_time', '03:40'));
            $partitionTime = preg_match('/^\d{2}:\d{2}$/', $partitionTime) === 1 ? $partitionTime : '03:40';
            $partitionCommand = 'wids:snapshots-partition-archive';

            if ((bool) config('wids.snapshot_partition.purge_source', false)) {
                $partitionCommand .= ' --purge-source';
            }

            $schedule->command($partitionCommand)->dailyAt($partitionTime)->withoutOverlapping();
        }

        if ((bool) config('wids.seo_risk.enabled', true)) {
            $seoSyncTime = trim((string) config('wids.seo_risk.sync_schedule_time', '06:10'));
            $seoSyncTime = preg_match('/^\d{2}:\d{2}$/', $seoSyncTime) === 1 ? $seoSyncTime : '06:10';

            $schedule->command('wids:seo-risk-sync')->dailyAt($seoSyncTime)->withoutOverlapping();
        }

        $schedule->command('wids:cleanup')->dailyAt('02:30')->withoutOverlapping();

        if ((bool) config('wids.reports.weekly_digest.enabled', true)) {
            $weeklyDigestDow = min(7, max(1, (int) config('wids.reports.weekly_digest.schedule_day_of_week', 1)));
            $weeklyDigestTime = trim((string) config('wids.reports.weekly_digest.schedule_time', '07:15'));
            $weeklyDigestTime = $weeklyDigestTime !== '' ? $weeklyDigestTime : '07:15';

            $schedule->command('wids:weekly-digest')
                ->weeklyOn($weeklyDigestDow, $weeklyDigestTime)
                ->withoutOverlapping();
        }

        $schedule->command('wids:ops-drill --save')
            ->monthlyOn(1, '06:40')
            ->withoutOverlapping();
    })
    ->withMiddleware(function (Middleware $middleware): void {
        // Trust only explicitly configured reverse-proxy hops.
        $trustedProxies = array_values(array_filter(array_map(
            static fn (string $proxy): string => trim($proxy),
            explode(',', (string) env('TRUSTED_PROXIES', '127.0.0.1,::1'))
        )));
        $middleware->trustProxies(at: $trustedProxies !== [] ? $trustedProxies : null);

        $middleware->alias([
            'role.owner' => \App\Http\Middleware\EnsureOwnerRole::class,
            'meter.api.usage' => \App\Http\Middleware\EnforceAndMeterApiUsage::class,
            'probe.auth' => \App\Http\Middleware\AuthenticatePrivateProbe::class,
            'scim.auth' => \App\Http\Middleware\AuthenticateScimProvisioning::class,
            'session.governance' => \App\Http\Middleware\EnforceSessionGovernance::class,
            'plugin_api' => \App\Http\Middleware\PluginApiAuth::class,
            'ability' => \Laravel\Sanctum\Http\Middleware\CheckForAnyAbility::class,
            'abilities' => \Laravel\Sanctum\Http\Middleware\CheckAbilities::class,
        ]);

        $middleware->validateCsrfTokens(except: [
            'auth/saml/*/acs',
            'auth/saml/*/slo',
        ]);

        $middleware->appendToGroup('web', \App\Http\Middleware\SetLocale::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
