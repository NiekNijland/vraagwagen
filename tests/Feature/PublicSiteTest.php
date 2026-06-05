<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\QueryRun;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class PublicSiteTest extends TestCase
{
    public function test_home_page_renders_default_seo_meta_tags(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertSee('data-inertia="description" name="description" content="Ask plain-language questions about registered vehicles in the Netherlands and get shareable answers from RDW open data."', false)
            ->assertSee('data-inertia="og:type" property="og:type" content="website"', false)
            ->assertSee('data-inertia="twitter:card" name="twitter:card" content="summary_large_image"', false);
    }

    public function test_shared_query_page_renders_prompt_specific_meta_tags(): void
    {
        QueryRun::factory()->createOne([
            'slug' => 'sharedmeta1',
            'prompt' => 'How many Teslas are registered?',
            'plan' => ['explanation' => 'Counts Teslas'],
            'presentation' => ['explanation' => 'Counts all registered Teslas.'],
        ]);

        $this->get(route('rdw.query.shared', 'sharedmeta1'))
            ->assertOk()
            ->assertSee('data-inertia="og:type" property="og:type" content="article"', false)
            ->assertSee('How many Teslas are registered? | vraagwagen.nl', false)
            ->assertSee('Counts all registered Teslas.', false);
    }

    public function test_privacy_page_is_publicly_available(): void
    {
        $this->get(route('privacy'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('query/privacy'));
    }

    public function test_sitemap_lists_public_pages(): void
    {
        $this->get(route('sitemap'))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/xml; charset=UTF-8')
            ->assertSee(route('home', ['locale' => 'en'], absolute: true), false)
            ->assertSee(route('privacy', ['locale' => 'nl'], absolute: true), false);
    }
}
