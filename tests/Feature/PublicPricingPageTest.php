<?php

namespace Tests\Feature;

use Tests\TestCase;

class PublicPricingPageTest extends TestCase
{
    public function test_pricing_redirects_to_localized_route(): void
    {
        $response = $this->get('/pricing');

        $response->assertStatus(301);
        $response->assertRedirect(route('localized.public.pricing', ['locale' => config('app.locale', 'en')]));
    }

    public function test_localized_pricing_pages_render(): void
    {
        $this->get('/en/pricing')
            ->assertOk()
            ->assertSee('Website and API Monitoring Pricing | TruMonit', false);

        $this->get('/pl/pricing')
            ->assertOk()
            ->assertSee('Cennik monitoringu stron i API | TruMonit', false);
    }
}
