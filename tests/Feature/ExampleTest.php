<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * A basic test example.
     */
    public function test_the_application_returns_a_successful_response(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200)
            ->assertSee('<title>Buat RPP AI Gratis untuk Guru | RuangAjar</title>', false)
            ->assertSee('name="description"', false)
            ->assertSee('name="keywords"', false)
            ->assertSee('name="robots" content="index, follow', false)
            ->assertSee('rel="canonical"', false)
            ->assertSee('property="og:title"', false)
            ->assertSee('name="twitter:card"', false)
            ->assertSee('type="application/ld+json"', false)
            ->assertSee('SoftwareApplication')
            ->assertSee('Buat RPP AI lebih cepat.');
    }

    public function test_private_pages_are_not_indexed(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('name="robots" content="noindex, nofollow, noarchive"', false);
    }

    public function test_robots_file_references_the_public_sitemap(): void
    {
        $robots = file_get_contents(public_path('robots.txt'));
        $sitemap = file_get_contents(public_path('sitemap.xml'));

        $this->assertStringContainsString('Allow: /', $robots);
        $this->assertStringContainsString('Sitemap: https://ruangajar.id/sitemap.xml', $robots);
        $this->assertStringContainsString('<loc>https://ruangajar.id/</loc>', $sitemap);
    }
}
