<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class WidsProfileCheckCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_passes_with_secure_defaults(): void
    {
        $exitCode = Artisan::call('wids:profile-check', [
            '--format' => 'json',
        ]);
        $output = Artisan::output();

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('"profile": "smb"', $output);
        $this->assertStringContainsString('"status": "pass"', $output);
    }

    public function test_command_fails_when_api_token_has_wildcard_scope(): void
    {
        config()->set('wids.api.tokens.abilities', ['*']);

        $exitCode = Artisan::call('wids:profile-check', [
            '--format' => 'json',
        ]);
        $output = Artisan::output();

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('"key": "api_token_wildcard_disabled"', $output);
        $this->assertStringContainsString('"status": "fail"', $output);
    }

    public function test_command_fails_when_metrics_require_token_but_token_is_missing(): void
    {
        config()->set('ops.metrics.enabled', true);
        config()->set('ops.metrics.require_token', true);
        config()->set('ops.metrics.token', null);

        $exitCode = Artisan::call('wids:profile-check', [
            '--format' => 'json',
        ]);
        $output = Artisan::output();

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('"key": "ops_metrics_token_present_when_enabled"', $output);
        $this->assertStringContainsString('"status": "fail"', $output);
    }
}
