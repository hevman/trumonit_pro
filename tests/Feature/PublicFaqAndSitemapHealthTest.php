<?php

namespace Tests\Feature;

use Tests\TestCase;

class PublicFaqAndSitemapHealthTest extends TestCase
{
    public function test_localized_faq_article_routes_render_for_configured_slugs(): void
    {
        foreach (config('faq.articles', []) as $article) {
            $slug = $article['slug'] ?? null;
            if (! is_string($slug) || $slug === '') {
                continue;
            }

            $response = $this->get('/en/faq/' . $slug);

            $this->assertTrue(
                $response->isOk(),
                sprintf('FAQ slug "%s" returned %d', $slug, $response->getStatusCode())
            );
        }
    }

    public function test_sitemap_is_served_as_valid_xml_and_includes_faq_urls(): void
    {
        $response = $this->get('/sitemap.xml');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/xml; charset=UTF-8');
        $response->assertSee('<?xml version="1.0" encoding="UTF-8"?>', false);
        $response->assertSee('<urlset', false);
        $response->assertSee('/en/faq', false);
        $response->assertSee('/pl/faq', false);
    }
}

