<?php

namespace Tests\Unit;

use App\Exceptions\UnsafeUrlException;
use App\Services\UrlSafetyService;
use Tests\TestCase;

class UrlSafetyServiceTest extends TestCase
{
    public function test_resolve_safe_target_allows_public_ip_literal(): void
    {
        $resolved = UrlSafetyService::resolveSafeTarget('https://1.1.1.1');

        $this->assertSame('1.1.1.1', $resolved['host']);
        $this->assertSame('1.1.1.1', $resolved['connect_ip']);
        $this->assertTrue((bool) $resolved['is_ip_literal']);
    }

    public function test_resolve_safe_target_rejects_private_ip_literal(): void
    {
        $this->expectException(UnsafeUrlException::class);

        UrlSafetyService::resolveSafeTarget('http://192.168.10.10');
    }

    public function test_assert_no_dns_drift_allows_public_ip_literal_host(): void
    {
        UrlSafetyService::assertNoDnsDrift('1.1.1.1', ['1.1.1.1']);

        $this->assertTrue(true);
    }

    public function test_assert_no_dns_drift_rejects_private_ip_literal_host(): void
    {
        $this->expectException(UnsafeUrlException::class);

        UrlSafetyService::assertNoDnsDrift('10.0.0.1', ['10.0.0.1']);
    }

    public function test_notification_endpoint_rejects_private_ip_literal(): void
    {
        $this->expectException(UnsafeUrlException::class);

        UrlSafetyService::assertSafeForNotificationEndpoint('http://10.1.1.1/hook');
    }

    public function test_notification_endpoint_allows_public_domain_without_dns_resolution(): void
    {
        config()->set('wids.notifications.url_safety_dns_strict', false);

        UrlSafetyService::assertSafeForNotificationEndpoint('https://slack.example.com/hook');

        $this->assertTrue(true);
    }
}
